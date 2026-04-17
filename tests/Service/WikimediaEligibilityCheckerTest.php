<?php

namespace App\Tests\Service;

use App\Entity;
use App\Service\WikimediaEligibilityChecker;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;
use Symfony\Contracts\Translation\TranslatorInterface;

class WikimediaEligibilityCheckerTest extends TestCase
{
    public function testGetVoteEligibilityErrorsSupportsNonWikipediaProjectsFromSiteMatrix(): void
    {
        $httpClient = new MockHttpClient([
            new MockResponse(json_encode([
                'sitematrix' => [
                    'count' => 2,
                    '0' => [
                        'code' => 'en',
                        'site' => [
                            [
                                'url' => 'https://en.wikipedia.org',
                                'dbname' => 'enwiki',
                                'code' => 'wiki',
                                'sitename' => 'Wikipedia',
                            ],
                        ],
                    ],
                    'specials' => [
                        [
                            'url' => 'https://commons.wikimedia.org',
                            'dbname' => 'commonswiki',
                            'code' => 'commons',
                            'sitename' => 'Commons',
                        ],
                    ],
                ],
            ])),
            new MockResponse(json_encode([
                'query' => [
                    'users' => [
                        [
                            'name' => 'ExampleUser',
                            'editcount' => 120,
                        ],
                    ],
                ],
            ])),
        ]);

        $translator = $this->createMock(TranslatorInterface::class);
        $translator
            ->method('trans')
            ->willReturnCallback(static fn (string $id): string => $id);

        $checker = new WikimediaEligibilityChecker(
            $httpClient,
            $translator,
            'https://meta.wikimedia.org/w/rest.php/oauth',
        );

        $poll = new Entity\Poll();
        $poll->setMinWikimediaEditsProject('commonswiki');
        $poll->setMinWikimediaEditsCount(500);

        $user = new Entity\User();
        $user->setUsername('ExampleUser');

        $errors = $checker->getVoteEligibilityErrors($poll, $user);

        self::assertSame([
            'polls.show.wikimedia_requirements.edit_count',
        ], $errors);
    }
}
