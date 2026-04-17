<?php

namespace App\Service;

use App\Entity;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Contracts\HttpClient\Exception\ExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

class WikimediaEligibilityChecker
{
    /** @var array<string, string>|null */
    private ?array $siteMatrixApiUrlsByDbName = null;

    public function __construct(
        private readonly HttpClientInterface $httpClient,
        private readonly TranslatorInterface $translator,
        #[Autowire('%env(string:WIKIMEDIA_OAUTH_BASE_URL)%')]
        private readonly string $oauthBaseUrl,
    ) {
    }

    /**
     * @return string[]
     */
    public function getVoteEligibilityErrors(Entity\Poll $poll, Entity\User $user): array
    {
        if (!$this->hasEligibilityRestrictions($poll)) {
            return [];
        }

        try {
            return $this->doGetVoteEligibilityErrors($poll, $user);
        } catch (\Throwable) {
            return [
                $this->translator->trans('polls.show.wikimedia_requirements.unavailable'),
            ];
        }
    }

    public function hasEligibilityRestrictions(Entity\Poll $poll): bool
    {
        return $poll->getMinWikimediaAccountAgeMonths() !== null
            || (
                $poll->getMinWikimediaEditsProject() !== null
                && $poll->getMinWikimediaEditsCount() !== null
            );
    }

    /**
     * @return string[]
     */
    private function doGetVoteEligibilityErrors(Entity\Poll $poll, Entity\User $user): array
    {
        $errors = [];
        $username = $user->getUserIdentifier();

        if ($poll->getMinWikimediaAccountAgeMonths() !== null) {
            $globalInfo = $this->fetchGlobalUserInfo($username);
            $registration = $globalInfo['registration'] ?? null;

            if (!is_string($registration) || $registration === '') {
                throw new \RuntimeException('Missing Wikimedia registration timestamp.');
            }

            $registeredAt = new \DateTimeImmutable($registration);
            $minimumDate = new \DateTimeImmutable(sprintf('-%d months', $poll->getMinWikimediaAccountAgeMonths()));

            if ($registeredAt > $minimumDate) {
                $errors[] = $this->translator->trans(
                    'polls.show.wikimedia_requirements.account_age',
                    ['months' => $poll->getMinWikimediaAccountAgeMonths()]
                );
            }
        }

        if ($poll->getMinWikimediaEditsProject() !== null && $poll->getMinWikimediaEditsCount() !== null) {
            $project = $poll->getMinWikimediaEditsProject();
            $projectInfo = $this->fetchProjectUserInfo($username, $project);
            $editCount = $projectInfo['editcount'] ?? null;

            if (!is_int($editCount)) {
                throw new \RuntimeException('Missing Wikimedia edit count.');
            }

            if ($editCount < $poll->getMinWikimediaEditsCount()) {
                $errors[] = $this->translator->trans(
                    'polls.show.wikimedia_requirements.edit_count',
                    [
                        'count' => $poll->getMinWikimediaEditsCount(),
                        'project' => $project,
                    ]
                );
            }
        }

        return $errors;
    }

    /**
     * @return array<string, mixed>
     */
    private function fetchGlobalUserInfo(string $username): array
    {
        $payload = $this->requestJson($this->getMetaApiUrl(), [
            'action' => 'query',
            'meta' => 'globaluserinfo',
            'guiuser' => $username,
            'guiprop' => 'editcount|merged',
            'format' => 'json',
        ]);

        $globalInfo = $payload['query']['globaluserinfo'] ?? null;
        if (!is_array($globalInfo)) {
            throw new \RuntimeException('Invalid Wikimedia globaluserinfo payload.');
        }

        return $globalInfo;
    }

    /**
     * @return array<string, mixed>
     */
    private function fetchProjectUserInfo(string $username, string $project): array
    {
        $payload = $this->requestJson($this->getProjectApiUrl($project), [
            'action' => 'query',
            'list' => 'users',
            'ususers' => $username,
            'usprop' => 'editcount|registration',
            'format' => 'json',
        ]);

        $users = $payload['query']['users'] ?? null;
        if (!is_array($users) || !isset($users[0]) || !is_array($users[0])) {
            throw new \RuntimeException('Invalid Wikimedia users payload.');
        }

        return $users[0];
    }

    private function getMetaApiUrl(): string
    {
        if (str_ends_with($this->oauthBaseUrl, '/w/rest.php/oauth')) {
            return substr($this->oauthBaseUrl, 0, -strlen('/w/rest.php/oauth')) . '/w/api.php';
        }

        return 'https://meta.wikimedia.org/w/api.php';
    }

    private function getProjectApiUrl(string $projectDbName): string
    {
        $projectDbName = mb_strtolower(trim($projectDbName));

        if (!preg_match('/^[a-z0-9_-]+$/', $projectDbName)) {
            throw new \RuntimeException('Invalid Wikipedia project code.');
        }

        $apiUrlsByDbName = $this->getSiteMatrixApiUrlsByDbName();

        if (!isset($apiUrlsByDbName[$projectDbName])) {
            throw new \RuntimeException('Unknown Wikimedia project database name.');
        }

        return $apiUrlsByDbName[$projectDbName];
    }

    /**
     * @return array<string, string>
     */
    private function getSiteMatrixApiUrlsByDbName(): array
    {
        if ($this->siteMatrixApiUrlsByDbName !== null) {
            return $this->siteMatrixApiUrlsByDbName;
        }

        $payload = $this->requestJson($this->getMetaApiUrl(), [
            'action' => 'sitematrix',
            'formatversion' => '2',
            'format' => 'json',
        ]);

        $siteMatrix = $payload['sitematrix'] ?? null;
        if (!is_array($siteMatrix)) {
            throw new \RuntimeException('Invalid Wikimedia sitematrix payload.');
        }

        $apiUrlsByDbName = [];

        foreach ($siteMatrix as $key => $entry) {
            if ($key === 'count') {
                continue;
            }

            if ($key === 'specials' && is_array($entry)) {
                foreach ($entry as $site) {
                    $this->registerSiteMatrixSite($apiUrlsByDbName, $site);
                }

                continue;
            }

            if (!is_array($entry)) {
                continue;
            }

            $sites = $entry['site'] ?? [];
            if (!is_array($sites)) {
                continue;
            }

            foreach ($sites as $site) {
                $this->registerSiteMatrixSite($apiUrlsByDbName, $site);
            }
        }

        $this->siteMatrixApiUrlsByDbName = $apiUrlsByDbName;

        return $this->siteMatrixApiUrlsByDbName;
    }

    /**
     * @param array<string, string> $apiUrlsByDbName
     * @param mixed $site
     */
    private function registerSiteMatrixSite(array &$apiUrlsByDbName, mixed $site): void
    {
        if (!is_array($site)) {
            return;
        }

        $dbname = $site['dbname'] ?? null;
        $url = $site['url'] ?? null;

        if (!is_string($dbname) || $dbname === '' || !is_string($url) || $url === '') {
            return;
        }

        if (($site['closed'] ?? false) === true) {
            return;
        }

        $apiUrlsByDbName[mb_strtolower($dbname)] = rtrim($url, '/') . '/w/api.php';
    }

    /**
     * @param array<string, string> $query
     *
     * @return array<string, mixed>
     */
    private function requestJson(string $url, array $query): array
    {
        try {
            $response = $this->httpClient->request('GET', $url, [
                'query' => $query,
            ]);
            $payload = $response->toArray();
        } catch (ExceptionInterface $exception) {
            throw new \RuntimeException('Unable to fetch Wikimedia eligibility data.', previous: $exception);
        }

        if (!is_array($payload)) {
            throw new \RuntimeException('Invalid Wikimedia response payload.');
        }

        return $payload;
    }
}
