<?php

// This file is part of Pollaris.
// Copyright 2024-2026 Marien Fressinaud
// Copyright 2026 Daniel Yepez Garces
// SPDX-License-Identifier: AGPL-3.0-or-later
//
// Modified by Daniel Yepez Garces on 2026-04-15:
// - Migrated database backend from PostgreSQL to MariaDB for Toolforge deployment
// - Added Wikimedia login support
// - Removed local username/password authentication
// - Added multilingual survey support
// - Added user timezone display for survey times when different from server UTC

namespace App\Tests\Controller;

use App\Security;
use App\Service;
use App\Tests\Helper;
use App\Tests\Factory;
use App\Utils;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Zenstruck\Foundry;

class PollsControllerTest extends WebTestCase
{
    use Foundry\Test\Factories;
    use Foundry\Test\ResetDatabase;
    use Helper\CsrfHelper;
    use Helper\FactoryHelper;

    public function testGetChooseRendersCorrectly(): void
    {
        $client = static::createClient();

        $client->request(Request::METHOD_GET, '/polls/choose');

        $this->assertResponseIsSuccessful();
        $this->assertSelectorTextContains('h1', 'Choose the type of poll');
    }

    public function testGetNewRendersCorrectly(): void
    {
        $client = static::createClient();
        $user = Factory\UserFactory::createOne([
            'username' => 'new-owner',
        ]);
        $client->loginUser($user);

        $client->request(Request::METHOD_GET, '/polls/new');

        $this->assertResponseIsSuccessful();
        $this->assertSelectorTextContains('h1', 'Poll creation');
    }

    public function testPostNewCreatesAPoll(): void
    {
        $client = static::createClient();
        $user = Factory\UserFactory::createOne([
            'username' => 'new-owner',
            'email' => 'owner@example.org',
        ]);
        $client->loginUser($user);
        $title = 'My poll';
        $description = 'Description of my poll';
        $name = 'Alix';
        $email = 'alix@example.org';

        $crawler = $client->request(Request::METHOD_GET, '/polls/new');
        $form = $crawler->filter('form[name="poll"]')->form([
            'poll[title]' => $title,
            'poll[description]' => $description,
            'poll[closedAt]' => '2026-04-20',
            'poll[timezoneMode]' => 'server',
        ]);
        $client->submit($form);

        $poll = Factory\PollFactory::last();
        $this->assertSame($title, $poll->getTitle());
        $this->assertSame($description, $poll->getDescription());
        $this->assertSame('new-owner', $poll->getAuthorName());
        $this->assertSame('owner@example.org', $poll->getAuthorEmail());
        $this->assertSame('classic', $poll->getType());
        $this->assertSame('en_GB', $poll->getLocale());
        $this->assertSame($user->getId(), $poll->getOwner()?->getId());
        $id = $poll->getId();
        $adminToken = $poll->getAdminToken();
        $this->assertSame(20, strlen($id ?? ''));
        $this->assertSame(20, strlen($adminToken ?? ''));
        $this->assertResponseRedirects("/polls/{$id}/{$adminToken}/proposals?flow=on", 302);
    }

    public function testPostNewDatePollRedirectsToPollDates(): void
    {
        $client = static::createClient();
        $user = Factory\UserFactory::createOne([
            'username' => 'date-owner',
            'email' => 'date-owner@example.org',
        ]);
        $client->loginUser($user);
        $title = 'My poll';
        $name = 'Alix';
        $email = 'alix@example.org';

        $crawler = $client->request(Request::METHOD_GET, '/polls/new?type=date');
        $form = $crawler->filter('form[name="poll"]')->form([
            'poll[title]' => $title,
            'poll[closedAt]' => '2026-04-20',
            'poll[timezoneMode]' => 'server',
        ]);
        $client->submit($form);

        $poll = Factory\PollFactory::last();
        $this->assertSame($title, $poll->getTitle());
        $this->assertSame('date', $poll->getType());
        $this->assertSame('date-owner', $poll->getAuthorName());
        $this->assertSame('date-owner@example.org', $poll->getAuthorEmail());
        $id = $poll->getId();
        $adminToken = $poll->getAdminToken();
        $this->assertResponseRedirects("/polls/{$id}/{$adminToken}/dates?flow=on", 302);
    }

    public function testPostNewUsesLoggedInUserProfileDetails(): void
    {
        $client = static::createClient();
        $user = Factory\UserFactory::createOne([
            'username' => 'new-owner',
            'email' => 'owner@example.org',
        ]);
        $client->loginUser($user);
        $title = 'My poll';
        $description = 'Description of my poll';

        $crawler = $client->request(Request::METHOD_GET, '/polls/new');
        $form = $crawler->filter('form[name="poll"]')->form([
            'poll[title]' => $title,
            'poll[description]' => $description,
            'poll[closedAt]' => '2026-04-20',
            'poll[timezoneMode]' => 'server',
        ]);
        $client->submit($form);

        $poll = Factory\PollFactory::last();
        $this->assertSame($title, $poll->getTitle());
        $this->assertSame($description, $poll->getDescription());
        $this->assertSame('new-owner', $poll->getAuthorName());
        $this->assertSame('owner@example.org', $poll->getAuthorEmail());
    }

    public function testPostNewFailsIfCsrfIsInvalid(): void
    {
        $client = static::createClient();
        $user = Factory\UserFactory::createOne([
            'username' => 'new-owner',
        ]);
        $client->loginUser($user);
        $title = 'My poll';
        $name = 'Alix';
        $description = 'Description of my poll';

        $client->request(Request::METHOD_POST, '/polls/new', [
            'poll' => [
                '_token' => 'not the token',
                'title' => $title,
                'description' => $description,
                'authorName' => $name,
                'closedAt' => [
                    'year' => '2026',
                    'month' => '4',
                    'day' => '20',
                ],
                'timezoneMode' => 'server',
            ],
        ]);

        $this->assertSelectorTextContains('#poll_error', 'please submit the form again');
        Factory\PollFactory::assert()->count(0);
    }

    public function testGetShowRendersCorrectly(): void
    {
        $client = static::createClient();
        $poll = Factory\PollFactory::new([
            'title' => 'My poll',
        ])->completed()->create();

        $client->request(Request::METHOD_GET, "/polls/{$poll->getSlug()}");

        $this->assertResponseIsSuccessful();
        $this->assertSelectorTextContains('h1', 'My poll');
    }

    public function testGetShowDisplaysWikimediaEligibilityErrorsForLoggedInUsers(): void
    {
        $client = static::createClient();
        $user = Factory\UserFactory::createOne([
            'username' => 'restricted-user',
        ]);
        $client->loginUser($user);
        $poll = Factory\PollFactory::new([
            'title' => 'My poll',
            'minWikimediaAccountAgeMonths' => 6,
        ])->completed()->create();

        $eligibilityChecker = $this->createMock(Service\WikimediaEligibilityChecker::class);
        $eligibilityChecker
            ->method('getVoteEligibilityErrors')
            ->willReturn([
                'Your Wikimedia account must be at least 6 months old to vote in this poll.',
            ]);
        static::getContainer()->set(Service\WikimediaEligibilityChecker::class, $eligibilityChecker);

        $client->request(Request::METHOD_GET, "/polls/{$poll->getSlug()}");

        $this->assertResponseIsSuccessful();
        $this->assertSelectorTextContains(
            'main',
            'Your Wikimedia account must be at least 6 months old to vote in this poll.'
        );
    }

    public function testGetShowWithCustomSlugRendersCorrectly(): void
    {
        $client = static::createClient();
        $poll = Factory\PollFactory::new([
            'title' => 'My poll',
            'slug' => 'my-slug',
        ])->completed()->create();

        $client->request(Request::METHOD_GET, '/polls/my-slug');

        $this->assertResponseIsSuccessful();
        $this->assertSelectorTextContains('h1', 'My poll');
    }

    public function testGetShowDoesNotRedirectIfAuthenticatedToPasswordProtectedPoll(): void
    {
        $client = static::createClient();
        $poll = Factory\PollFactory::new([
            'title' => 'My poll',
            'password' => 'secret',
            'isPasswordForVotesOnly' => false,
        ])->completed()->create();
        $session = $this->getSession($client);
        /** @var Security\PollSecurity */
        $pollSecurity = $client->getContainer()->get(Security\PollSecurity::class);
        $session->set(
            $pollSecurity->generateKey($poll),
            $pollSecurity->generateHash($poll)
        );
        $session->save();

        $client->request(Request::METHOD_GET, "/polls/{$poll->getSlug()}");

        $this->assertResponseIsSuccessful();
        $this->assertSelectorTextContains('h1', 'My poll');
    }

    public function testGetShowDoesNotRedirectIfProtectedPollAllowsToSeeResults(): void
    {
        $client = static::createClient();
        $poll = Factory\PollFactory::new([
            'title' => 'My poll',
            'password' => 'secret',
            'isPasswordForVotesOnly' => true,
        ])->completed()->create();

        $client->request(Request::METHOD_GET, "/polls/{$poll->getSlug()}");

        $this->assertResponseIsSuccessful();
        $this->assertSelectorTextContains('h1', 'My poll');
    }

    public function testGetShowRedirectsIfNotAuthenticatedToPasswordProtectedPoll(): void
    {
        $client = static::createClient();
        $poll = Factory\PollFactory::new([
            'title' => 'My poll',
            'password' => 'secret',
            'isPasswordForVotesOnly' => false,
        ])->completed()->create();

        $client->request(Request::METHOD_GET, "/polls/{$poll->getSlug()}");

        $this->assertResponseRedirects("/polls/{$poll->getId()}/authenticate", 302);
    }

    public function testGetShowFailsIfPollIsNotComplete(): void
    {
        $client = static::createClient();
        $poll = Factory\PollFactory::createOne([
            'completedAt' => null,
        ]);

        $this->expectException(NotFoundHttpException::class);

        $client->catchExceptions(false);
        $client->request(Request::METHOD_GET, "/polls/{$poll->getSlug()}");
    }

    public function testPostShowWithVoteCreatesAVote(): void
    {
        $client = static::createClient();
        $user = Factory\UserFactory::createOne([
            'username' => 'alix-user',
        ]);
        $client->loginUser($user);
        $authorEmail = 'charlie@example.com';
        $poll = Factory\PollFactory::new([
            'authorEmail' => $authorEmail,
            'notifyOnVotes' => true,
        ])->completed()->create();
        $proposal = $poll->getProposals()->first();
        $name = 'Alix';

        $this->assertNotFalse($proposal);

        $client->request(Request::METHOD_POST, "/polls/{$poll->getSlug()}", [
            'vote' => [
                '_token' => $this->getCsrf($client, 'vote'),
                'authorName' => $name,
                'answers' => [
                    ['value' => 'yes'],
                ],
            ],
        ]);

        $this->assertResponseRedirects("/polls/{$poll->getSlug()}", 302);
        $votes = Factory\VoteFactory::all();
        $this->assertSame(1, count($votes));
        $this->assertSame('alix-user', $votes[0]->getAuthorName());
        $this->assertSame($poll->getId(), $votes[0]->getPoll()->getId());
        $answers = $votes[0]->getAnswers()->toArray();
        $this->assertSame(1, count($answers));
        $this->assertSame('yes', $answers[0]->getValue());
        $this->assertSame($proposal->getId(), $answers[0]->getProposal()?->getId());
        $this->assertEmailCount(1);
        $email = $this->getMailerMessage();
        $this->assertNotNull($email);
        $this->assertEmailTextBodyContains($email, 'alix-user');
        $this->assertEmailAddressContains($email, 'To', $authorEmail);
    }

    public function testPostShowWithVoteUsesLoggedUserUsername(): void
    {
        $client = static::createClient();
        $user = Factory\UserFactory::createOne([
            'username' => 'wikimedia-user',
        ]);
        $client->loginUser($user);
        $poll = Factory\PollFactory::new()->completed()->create();
        $proposal = $poll->getProposals()->first();

        $this->assertNotFalse($proposal);

        $client->request(Request::METHOD_POST, "/polls/{$poll->getSlug()}", [
            'vote' => [
                '_token' => $this->getCsrf($client, 'vote'),
                'authorName' => 'ignored',
                'answers' => [
                    ['value' => 'yes'],
                ],
            ],
        ]);

        $this->assertResponseRedirects("/polls/{$poll->getSlug()}", 302);
        $votes = Factory\VoteFactory::all();
        $this->assertSame(1, count($votes));
        $this->assertSame('wikimedia-user', $votes[0]->getAuthorName());
    }

    public function testPostShowWithMissingVoteIsConsideredAsNoIfOptionIsTrue(): void
    {
        $client = static::createClient();
        $user = Factory\UserFactory::createOne([
            'username' => 'alix-user',
        ]);
        $client->loginUser($user);
        $poll = Factory\PollFactory::new([
            'voteNoByDefault' => true,
        ])->completed()->create();
        $proposal = $poll->getProposals()->first();
        $name = 'Alix';

        $this->assertNotFalse($proposal);

        $client->request(Request::METHOD_POST, "/polls/{$poll->getSlug()}", [
            'vote' => [
                '_token' => $this->getCsrf($client, 'vote'),
                'authorName' => $name,
                'answers' => [
                ],
            ],
        ]);

        $this->assertResponseRedirects("/polls/{$poll->getSlug()}", 302);
        $votes = Factory\VoteFactory::all();
        $this->assertSame(1, count($votes));
        $this->assertSame('alix-user', $votes[0]->getAuthorName());
        $this->assertSame($poll->getId(), $votes[0]->getPoll()->getId());
        $answers = $votes[0]->getAnswers()->toArray();
        $this->assertSame(1, count($answers));
        $this->assertSame('no', $answers[0]->getValue());
        $this->assertSame($proposal->getId(), $answers[0]->getProposal()?->getId());
    }

    public function testPostShowWithVoteDoesNothingIfPollIsClosed(): void
    {
        $client = static::createClient();
        $user = Factory\UserFactory::createOne([
            'username' => 'alix-user',
        ]);
        $client->loginUser($user);
        $authorEmail = 'charlie@example.com';
        $poll = Factory\PollFactory::new([
            'authorEmail' => $authorEmail,
            'notifyOnVotes' => true,
            'closedAt' => Utils\Time::ago(1, 'day'),
        ])->completed()->create();
        $proposal = $poll->getProposals()->first();
        $name = 'Alix';

        $this->assertNotFalse($proposal);

        $client->request(Request::METHOD_POST, "/polls/{$poll->getSlug()}", [
            'vote' => [
                '_token' => $this->getCsrf($client, 'vote'),
                'authorName' => $name,
                'answers' => [
                    ['value' => 'yes'],
                ],
            ],
        ]);

        $votes = Factory\VoteFactory::all();
        $this->assertSame(0, count($votes));
        $this->assertEmailCount(0);
    }

    public function testPostShowWithMaybeVoteFailsIfMaybeDisabled(): void
    {
        $client = static::createClient();
        $user = Factory\UserFactory::createOne([
            'username' => 'alix-user',
        ]);
        $client->loginUser($user);
        $poll = Factory\PollFactory::new([
            'disableMaybe' => true,
        ])->completed()->create();
        $proposal = $poll->getProposals()->first();
        $name = 'Alix';

        $this->assertNotFalse($proposal);

        $client->request(Request::METHOD_POST, "/polls/{$poll->getSlug()}", [
            'vote' => [
                '_token' => $this->getCsrf($client, 'vote'),
                'authorName' => $name,
                'answers' => [
                    ['value' => 'maybe'],
                ],
            ],
        ]);

        $this->assertSelectorTextContains(
            '#vote_answers_0_value_error',
            'The selected choice is invalid.'
        );
        $votes = Factory\VoteFactory::all();
        $this->assertSame(0, count($votes));
    }

    public function testPostShowWithVoteFailsIfMaxVoteIsReached(): void
    {
        $client = static::createClient();
        $user = Factory\UserFactory::createOne([
            'username' => 'alix-user',
        ]);
        $client->loginUser($user);
        $poll = Factory\PollFactory::new([
            'maxVotes' => 1,
        ])->completed()->create();
        $proposal = $poll->getProposals()->first();
        $vote = Factory\VoteFactory::createOne([
            'poll' => $poll,
        ]);
        $answer = Factory\AnswerFactory::createOne([
            'vote' => $vote,
            'proposal' => $proposal,
            'value' => 'yes',
        ]);
        $name = 'Alix';

        $client->request(Request::METHOD_POST, "/polls/{$poll->getSlug()}", [
            'vote' => [
                '_token' => $this->getCsrf($client, 'vote'),
                'authorName' => $name,
                'answers' => [
                    ['value' => 'yes'],
                ],
            ],
        ]);

        $this->assertSelectorTextContains(
            '#vote_answers_0_value_error',
            'There have already been 1 vote(s) for this proposal, you cannot vote for it.'
        );
        $votes = Factory\VoteFactory::all();
        $this->assertSame(1, count($votes));
    }

    public function testPostShowWithVoteFailsIfRequiredPasswordIsIncorrect(): void
    {
        $client = static::createClient();
        $user = Factory\UserFactory::createOne([
            'username' => 'alix-user',
        ]);
        $client->loginUser($user);
        $poll = Factory\PollFactory::new([
            'password' => 'secret',
            'isPasswordForVotesOnly' => true,
        ])->completed()->create();
        $proposal = $poll->getProposals()->first();
        $name = 'Alix';

        $this->assertNotFalse($proposal);

        $client->request(Request::METHOD_POST, "/polls/{$poll->getSlug()}", [
            'vote' => [
                '_token' => $this->getCsrf($client, 'vote'),
                'authorName' => $name,
                'answers' => [
                    ['value' => 'yes'],
                ],
                'password' => 'not the password',
            ],
        ]);

        $this->assertSelectorTextContains('#vote_password_error', 'The password is incorrect');
        Factory\VoteFactory::assert()->count(0);
    }

    public function testPostShowWithVoteFailsIfCsrfIsInvalid(): void
    {
        $client = static::createClient();
        $user = Factory\UserFactory::createOne([
            'username' => 'alix-user',
        ]);
        $client->loginUser($user);
        $poll = Factory\PollFactory::new()->completed()->create();
        $proposal = $poll->getProposals()->first();
        $name = 'Alix';

        $client->request(Request::METHOD_POST, "/polls/{$poll->getSlug()}", [
            'vote' => [
                '_token' => 'not the token',
                'authorName' => $name,
                'answers' => [
                    ['value' => 'yes'],
                ],
            ],
        ]);

        $this->assertSelectorTextContains('#vote_error', 'please submit the form again');
        Factory\VoteFactory::assert()->count(0);
    }

    public function testPostShowWithCommentCreatesAComment(): void
    {
        $client = static::createClient();
        $authorEmail = 'charlie@example.com';
        $user = Factory\UserFactory::createOne([
            'username' => 'wikimedia-user',
        ]);
        $client->loginUser($user);
        $poll = Factory\PollFactory::new([
            'authorEmail' => $authorEmail,
            'notifyOnComments' => true,
        ])->completed()->create();
        $content = 'Lorem ipsum';

        $client->request(Request::METHOD_POST, "/polls/{$poll->getSlug()}", [
            'comment' => [
                '_token' => $this->getCsrf($client, 'comment'),
                'authorName' => 'ignored',
                'content' => $content,
            ],
        ]);

        $this->assertResponseRedirects("/polls/{$poll->getSlug()}", 302);
        $comments = Factory\CommentFactory::all();
        $this->assertSame(1, count($comments));
        $this->assertSame('wikimedia-user', $comments[0]->getAuthorName());
        $this->assertSame($content, $comments[0]->getContent());
        $this->assertSame($poll->getId(), $comments[0]->getPoll()->getId());
        $this->assertEmailCount(1);
        $email = $this->getMailerMessage();
        $this->assertNotNull($email);
        $this->assertEmailTextBodyContains($email, 'wikimedia-user');
        $this->assertEmailAddressContains($email, 'To', $authorEmail);
    }

    public function testPostShowWithCommentDoesNothingIfUserIsNotLoggedIn(): void
    {
        $client = static::createClient();
        $poll = Factory\PollFactory::new([
            'notifyOnComments' => true,
        ])->completed()->create();

        $client->request(Request::METHOD_POST, "/polls/{$poll->getSlug()}", [
            'comment' => [
                '_token' => $this->getCsrf($client, 'comment'),
                'authorName' => 'Anonymous',
                'content' => 'Lorem ipsum',
            ],
        ]);

        $this->assertResponseIsSuccessful();
        Factory\CommentFactory::assert()->count(0);
    }

    public function testPostShowWithCommentDoesNothingIfPollIsClosed(): void
    {
        $client = static::createClient();
        $user = Factory\UserFactory::createOne([
            'username' => 'wikimedia-user',
        ]);
        $client->loginUser($user);
        $authorEmail = 'charlie@example.com';
        $poll = Factory\PollFactory::new([
            'authorEmail' => $authorEmail,
            'notifyOnComments' => true,
            'closedAt' => Utils\Time::ago(1, 'day'),
        ])->completed()->create();
        $name = 'Alix';
        $content = 'Lorem ipsum';

        $client->request(Request::METHOD_POST, "/polls/{$poll->getSlug()}", [
            'comment' => [
                '_token' => $this->getCsrf($client, 'comment'),
                'authorName' => 'ignored',
                'content' => $content,
            ],
        ]);

        $comments = Factory\CommentFactory::all();
        $this->assertSame(0, count($comments));
        $this->assertEmailCount(0);
    }

    public function testPostShowWithCommentFailsIfContentIsEmpty(): void
    {
        $client = static::createClient();
        $user = Factory\UserFactory::createOne([
            'username' => 'wikimedia-user',
        ]);
        $client->loginUser($user);
        $poll = Factory\PollFactory::new()->completed()->create();
        $name = 'Alix';
        $content = '';

        $client->request(Request::METHOD_POST, "/polls/{$poll->getSlug()}", [
            'comment' => [
                '_token' => $this->getCsrf($client, 'comment'),
                'authorName' => 'ignored',
                'content' => $content,
            ],
        ]);

        $this->assertSelectorTextContains('#comment_content_error', 'Enter a message.');
        $comments = Factory\CommentFactory::all();
        $this->assertSame(0, count($comments));
    }

    public function testPostShowWithCommentFailsIfCsrfIsInvalid(): void
    {
        $client = static::createClient();
        $user = Factory\UserFactory::createOne([
            'username' => 'wikimedia-user',
        ]);
        $client->loginUser($user);
        $poll = Factory\PollFactory::new()->completed()->create();
        $name = 'Alix';
        $content = 'Lorem ipsum';

        $client->request(Request::METHOD_POST, "/polls/{$poll->getSlug()}", [
            'comment' => [
                '_token' => 'not the token',
                'authorName' => 'ignored',
                'content' => $content,
            ],
        ]);

        $this->assertSelectorTextContains('#comment_error', 'please submit the form again');
        $comments = Factory\CommentFactory::all();
        $this->assertSame(0, count($comments));
    }

    public function testGetShowCsvRendersCorrectly(): void
    {
        $client = static::createClient();
        $poll = Factory\PollFactory::new([
            'title' => 'My poll',
        ])->completed()->create();

        $response = $client->request(Request::METHOD_GET, "/polls/{$poll->getSlug()}.csv");

        $this->assertResponseIsSuccessful();
        $this->assertResponseHeaderSame('Content-Type', 'text/csv; charset=UTF-8');
        $this->assertResponseHeaderSame('Content-Disposition', 'attachment; filename="My_poll.csv"');
    }

    public function testGetShowCsvRedirectsIfNotAuthenticatedToPasswordProtectedPoll(): void
    {
        $client = static::createClient();
        $poll = Factory\PollFactory::new([
            'title' => 'My poll',
            'password' => 'secret',
            'isPasswordForVotesOnly' => false,
        ])->completed()->create();

        $client->request(Request::METHOD_GET, "/polls/{$poll->getSlug()}.csv");

        $this->assertResponseRedirects("/polls/{$poll->getId()}/authenticate", 302);
    }

    public function testGetShowCsvFailsIfPollIsNotComplete(): void
    {
        $client = static::createClient();
        $poll = Factory\PollFactory::createOne([
            'completedAt' => null,
        ]);

        $this->expectException(NotFoundHttpException::class);

        $client->catchExceptions(false);
        $client->request(Request::METHOD_GET, "/polls/{$poll->getSlug()}.csv");
    }

    public function testGetShowCsvFailsIfCannotSeeResults(): void
    {
        $client = static::createClient();
        $poll = Factory\PollFactory::new([
            'title' => 'My poll',
            'areResultsPublic' => false,
        ])->completed()->create();

        $this->expectException(NotFoundHttpException::class);

        $client->catchExceptions(false);
        $client->request(Request::METHOD_GET, "/polls/{$poll->getSlug()}.csv");
    }

    public function testGetAuthenticateRendersCorrectly(): void
    {
        $client = static::createClient();
        $poll = Factory\PollFactory::new([
            'title' => 'My poll',
            'password' => 'secret',
            'isPasswordForVotesOnly' => false,
        ])->completed()->create();

        $client->request(Request::METHOD_GET, "/polls/{$poll->getSlug()}/authenticate");

        $this->assertResponseIsSuccessful();
        $this->assertSelectorTextContains('h1', 'Authentication to a protected poll');
    }

    public function testGetAuthenticateRedirectsIfPollIsNotPasswordProtected(): void
    {
        $client = static::createClient();
        $poll = Factory\PollFactory::new([
            'title' => 'My poll',
            'password' => '',
            'isPasswordForVotesOnly' => false,
        ])->completed()->create();

        $client->request(Request::METHOD_GET, "/polls/{$poll->getSlug()}/authenticate");

        $this->assertResponseRedirects("/polls/{$poll->getId()}", 302);
    }

    public function testGetAuthenticateRedirectsIfAlreadyAuthenticated(): void
    {
        $client = static::createClient();
        $poll = Factory\PollFactory::new([
            'title' => 'My poll',
            'password' => 'secret',
            'isPasswordForVotesOnly' => false,
        ])->completed()->create();
        $session = $this->getSession($client);
        /** @var Security\PollSecurity */
        $pollSecurity = $client->getContainer()->get(Security\PollSecurity::class);
        $session->set(
            $pollSecurity->generateKey($poll),
            $pollSecurity->generateHash($poll)
        );
        $session->save();

        $client->request(Request::METHOD_GET, "/polls/{$poll->getSlug()}/authenticate");

        $this->assertResponseRedirects("/polls/{$poll->getId()}", 302);
    }

    public function testPostAuthenticateAuthenticatesAndRedirects(): void
    {
        $client = static::createClient();
        $poll = Factory\PollFactory::new([
            'title' => 'My poll',
            'password' => 'secret',
            'isPasswordForVotesOnly' => false,
        ])->completed()->create();

        $client->request(Request::METHOD_POST, "/polls/{$poll->getSlug()}/authenticate", [
            'poll_authentication' => [
                '_token' => $this->getCsrf($client, 'poll_authentication'),
                'password' => 'secret',
            ],
        ]);

        $this->assertResponseRedirects("/polls/{$poll->getId()}", 302);
        $session = $this->getSession($client);
        /** @var Security\PollSecurity */
        $pollSecurity = static::getContainer()->get(Security\PollSecurity::class);
        $hash = $session->get($pollSecurity->generateKey($poll));
        $this->assertNotNull($hash);
        $this->assertTrue($pollSecurity->compareHash($poll, $hash));
    }

    public function testPostAuthenticateFailsIfPasswordIsInvalid(): void
    {
        $client = static::createClient();
        $poll = Factory\PollFactory::new([
            'title' => 'My poll',
            'password' => 'secret',
            'isPasswordForVotesOnly' => false,
        ])->completed()->create();

        $client->request(Request::METHOD_POST, "/polls/{$poll->getSlug()}/authenticate", [
            'poll_authentication' => [
                '_token' => $this->getCsrf($client, 'poll_authentication'),
                'password' => 'not the password',
            ],
        ]);

        $this->assertSelectorTextContains('#poll_authentication_password_error', 'The password is incorrect');
        $session = $this->getSession($client);
        /** @var Security\PollSecurity */
        $pollSecurity = static::getContainer()->get(Security\PollSecurity::class);
        $hash = $session->get($pollSecurity->generateKey($poll));
        $this->assertNull($hash);
    }

    public function testPostAuthenticateFailsIfCsrfIsInvalid(): void
    {
        $client = static::createClient();
        $poll = Factory\PollFactory::new([
            'title' => 'My poll',
            'password' => 'secret',
            'isPasswordForVotesOnly' => false,
        ])->completed()->create();

        $client->request(Request::METHOD_POST, "/polls/{$poll->getSlug()}/authenticate", [
            'poll_authentication' => [
                '_token' => 'not the token',
                'password' => 'secret',
            ],
        ]);

        $this->assertSelectorTextContains('#poll_authentication_error', 'please submit the form again');
        $session = $this->getSession($client);
        /** @var Security\PollSecurity */
        $pollSecurity = static::getContainer()->get(Security\PollSecurity::class);
        $hash = $session->get($pollSecurity->generateKey($poll));
        $this->assertNull($hash);
    }

    public function testGetEditRendersCorrectly(): void
    {
        $client = static::createClient();
        $user = Factory\UserFactory::createOne([
            'username' => 'poll-owner',
        ]);
        $client->loginUser($user);
        $poll = Factory\PollFactory::createOne([
            'owner' => $user,
        ]);

        $client->request(Request::METHOD_GET, "/polls/{$poll->getId()}/{$poll->getAdminToken()}/edit");

        $this->assertResponseIsSuccessful();
        $this->assertSelectorTextContains('h1', 'Poll creation');
    }

    public function testGetEditFailsIfAdminTokenDoesNotMatch(): void
    {
        $client = static::createClient();
        $poll = Factory\PollFactory::createOne();

        $this->expectException(NotFoundHttpException::class);

        $client->catchExceptions(false);
        $client->request(Request::METHOD_GET, "/polls/{$poll->getId()}/not-the-token/edit");
    }

    public function testPostEditChangesTheTitleAndDescription(): void
    {
        $client = static::createClient();
        $user = Factory\UserFactory::createOne([
            'username' => 'poll-owner',
        ]);
        $client->loginUser($user);
        $oldTitle = 'The poll';
        $newTitle = 'My poll';
        $oldDescription = 'Outdated description';
        $newDescription = 'The new description';
        $oldName = 'Alix';
        $newName = 'Charlie';
        $oldEmail = 'alix@example.org';
        $newEmail = 'charlie@example.org';
        $poll = Factory\PollFactory::new()->classic()->create([
            'owner' => $user,
            'title' => $oldTitle,
            'description' => $oldDescription,
            'authorName' => $oldName,
            'authorEmail' => $oldEmail,
        ]);

        $crawler = $client->request(Request::METHOD_GET, "/polls/{$poll->getId()}/{$poll->getAdminToken()}/edit");
        $form = $crawler->filter('form[name="poll"]')->form([
            'poll[title]' => $newTitle,
            'poll[description]' => $newDescription,
            'poll[authorName]' => $newName,
            'poll[authorEmail]' => $newEmail,
            'poll[closedAt]' => '2026-04-20',
            'poll[timezoneMode]' => 'server',
        ]);
        $client->submit($form);

        $updatedPoll = Factory\PollFactory::find(['id' => $poll->getId()]);
        $this->assertNotNull($updatedPoll);
        $this->assertSame($newTitle, $updatedPoll->getTitle());
        $this->assertSame($newDescription, $updatedPoll->getDescription());
        $this->assertSame($newName, $updatedPoll->getAuthorName());
        $this->assertSame($newEmail, $updatedPoll->getAuthorEmail());
        $this->assertResponseRedirects("/polls/{$poll->getId()}/{$poll->getAdminToken()}/proposals?flow=on", 302);
    }

    public function testPostEditFailsIfCsrfIsInvalid(): void
    {
        $client = static::createClient();
        $user = Factory\UserFactory::createOne([
            'username' => 'poll-owner',
        ]);
        $client->loginUser($user);
        $oldTitle = 'The poll';
        $newTitle = 'My poll';
        $oldDescription = 'Outdated description';
        $newDescription = 'The new description';
        $oldName = 'Alix';
        $newName = 'Charlie';
        $oldEmail = 'alix@example.org';
        $newEmail = 'charlie@example.org';
        $poll = Factory\PollFactory::new()->classic()->create([
            'owner' => $user,
            'title' => $oldTitle,
            'description' => $oldDescription,
            'authorName' => $oldName,
            'authorEmail' => $oldEmail,
        ]);

        $client->request(Request::METHOD_POST, "/polls/{$poll->getId()}/{$poll->getAdminToken()}/edit", [
            'poll' => [
                '_token' => 'not the token',
                'title' => $newTitle,
                'description' => $newDescription,
                'authorName' => $newName,
                'authorEmail' => $newEmail,
                'closedAt' => [
                    'year' => '2026',
                    'month' => '4',
                    'day' => '20',
                ],
                'timezoneMode' => 'server',
            ],
        ]);

        $this->assertSelectorTextContains('#poll_error', 'please submit the form again');
        $this->refresh($poll);
        $this->assertSame($oldTitle, $poll->getTitle());
        $this->assertSame($oldDescription, $poll->getDescription());
        $this->assertSame($oldName, $poll->getAuthorName());
        $this->assertSame($oldEmail, $poll->getAuthorEmail());
    }

    public function testGetProposalsRendersCorrectly(): void
    {
        $client = static::createClient();
        $user = Factory\UserFactory::createOne([
            'username' => 'poll-owner',
        ]);
        $client->loginUser($user);
        $poll = Factory\PollFactory::new([
            'owner' => $user,
        ])->classic()->create();

        $client->request(Request::METHOD_GET, "/polls/{$poll->getId()}/{$poll->getAdminToken()}/proposals");

        $this->assertResponseIsSuccessful();
        $this->assertSelectorTextContains('h1', 'Choose the proposals');
    }

    public function testGetProposalsFailsIfTypeIsDate(): void
    {
        $client = static::createClient();
        $user = Factory\UserFactory::createOne([
            'username' => 'poll-owner',
        ]);
        $client->loginUser($user);
        $poll = Factory\PollFactory::new([
            'owner' => $user,
        ])->date()->create();

        $this->expectException(NotFoundHttpException::class);

        $client->catchExceptions(false);
        $client->request(Request::METHOD_GET, "/polls/{$poll->getId()}/{$poll->getAdminToken()}/proposals");
    }

    public function testGetProposalsFailsIfAdminTokenDoesNotMatch(): void
    {
        $client = static::createClient();
        $poll = Factory\PollFactory::new()->classic()->create();

        $this->expectException(NotFoundHttpException::class);

        $client->catchExceptions(false);
        $client->request(Request::METHOD_GET, "/polls/{$poll->getId()}/not-the-token/proposals");
    }

    public function testPostProposalsCreatesProposals(): void
    {
        $client = static::createClient();
        $user = Factory\UserFactory::createOne([
            'username' => 'poll-owner',
        ]);
        $client->loginUser($user);
        $poll = Factory\PollFactory::new([
            'owner' => $user,
        ])->classic()->create();

        $client->request(Request::METHOD_POST, "/polls/{$poll->getId()}/{$poll->getAdminToken()}/proposals", [
            'poll_proposals' => [
                '_token' => $this->getCsrf($client, 'poll_proposals'),
                'proposals' => [
                    ['label' => 'Foo'],
                    ['label' => 'Bar'],
                ],
            ],
        ]);

        $proposals = Factory\ProposalFactory::all();
        $this->assertSame(2, count($proposals));
        $this->assertSame('Foo', $proposals[0]->getLabel());
        $this->assertSame($poll, $proposals[0]->getPoll());
        $this->assertSame('Bar', $proposals[1]->getLabel());
        $this->assertSame($poll, $proposals[1]->getPoll());
        $this->assertResponseRedirects("/polls/{$poll->getId()}/{$poll->getAdminToken()}/admin", 302);
    }

    public function testPostProposalsSynchronizesExistingVotesWithNewProposals(): void
    {
        $client = static::createClient();
        $user = Factory\UserFactory::createOne([
            'username' => 'poll-owner',
        ]);
        $client->loginUser($user);
        $poll = Factory\PollFactory::new([
            'owner' => $user,
        ])->classic()->create();
        $existingProposal = Factory\ProposalFactory::createOne([
            'label' => 'Foo',
            'poll' => $poll,
        ]);
        $existingVote = Factory\VoteFactory::createOne([
            'poll' => $poll,
        ]);
        $existingAnswer = Factory\AnswerFactory::createOne([
            'vote' => $existingVote,
            'proposal' => $existingProposal,
            'value' => 'yes',
        ]);

        $client->request(Request::METHOD_POST, "/polls/{$poll->getId()}/{$poll->getAdminToken()}/proposals", [
            'poll_proposals' => [
                '_token' => $this->getCsrf($client, 'poll_proposals'),
                'proposals' => [
                    ['label' => 'Foo'],
                    ['label' => 'Bar'],
                    ['label' => 'Baz'],
                ],
            ],
        ]);

        $proposals = Factory\ProposalFactory::all();
        $this->assertSame(3, count($proposals));
        $this->assertSame('Foo', $proposals[0]->getLabel());
        $this->assertSame('Bar', $proposals[1]->getLabel());
        $this->assertSame('Baz', $proposals[2]->getLabel());
        $this->refresh($existingVote);
        $voteAnswers = $existingVote->getAnswers()->toArray();
        $this->assertSame(3, count($voteAnswers));
        $this->assertSame($proposals[0], $voteAnswers[0]->getProposal());
        $this->assertSame('yes', $voteAnswers[0]->getValue());
        $this->assertSame($proposals[1], $voteAnswers[1]->getProposal());
        $this->assertSame('', $voteAnswers[1]->getValue());
        $this->assertSame($proposals[2], $voteAnswers[2]->getProposal());
        $this->assertSame('', $voteAnswers[2]->getValue());
    }

    public function testPostProposalsReplacesExistingProposals(): void
    {
        $client = static::createClient();
        $user = Factory\UserFactory::createOne([
            'username' => 'poll-owner',
        ]);
        $client->loginUser($user);
        $poll = Factory\PollFactory::new([
            'owner' => $user,
        ])->classic()->create();
        $proposal1 = Factory\ProposalFactory::createOne([
            'label' => 'Bar',
            'poll' => $poll,
        ]);
        $proposal2 = Factory\ProposalFactory::createOne([
            'label' => 'Baz',
            'poll' => $poll,
        ]);

        $client->request(Request::METHOD_POST, "/polls/{$poll->getId()}/{$poll->getAdminToken()}/proposals", [
            'poll_proposals' => [
                '_token' => $this->getCsrf($client, 'poll_proposals'),
                'proposals' => [
                    ['label' => 'Bar'],
                    ['label' => 'Foo'],
                ],
            ],
        ]);

        $proposals = Factory\ProposalFactory::all();
        $this->assertSame(2, count($proposals));
        $this->assertSame('Bar', $proposals[0]->getLabel());
        $this->assertSame($poll, $proposals[0]->getPoll());
        $this->assertSame($proposal1, $proposals[0]);
        $this->assertSame('Foo', $proposals[1]->getLabel());
        $this->assertSame($poll, $proposals[1]->getPoll());
    }

    public function testPostProposalsFailsIfCsrfIsInvalid(): void
    {
        $client = static::createClient();
        $user = Factory\UserFactory::createOne([
            'username' => 'poll-owner',
        ]);
        $client->loginUser($user);
        $poll = Factory\PollFactory::new([
            'owner' => $user,
        ])->classic()->create();

        $client->request(Request::METHOD_POST, "/polls/{$poll->getId()}/{$poll->getAdminToken()}/proposals", [
            'poll_proposals' => [
                '_token' => 'not the token',
                'proposals' => [
                    ['label' => 'Foo'],
                    ['label' => 'Bar'],
                ],
            ],
        ]);

        $this->assertSelectorTextContains('#poll_proposals_error', 'please submit the form again');
        Factory\ProposalFactory::assert()->count(0);
    }

    public function testGetDatesRendersCorrectly(): void
    {
        $client = static::createClient();
        $user = Factory\UserFactory::createOne([
            'username' => 'poll-owner',
        ]);
        $client->loginUser($user);
        $poll = Factory\PollFactory::new([
            'owner' => $user,
        ])->date()->create();

        $client->request(Request::METHOD_GET, "/polls/{$poll->getId()}/{$poll->getAdminToken()}/dates");

        $this->assertResponseIsSuccessful();
        $this->assertSelectorTextContains('h1', 'Choose the dates');
    }

    public function testGetDatesFailsIfTypeIsClassic(): void
    {
        $client = static::createClient();
        $user = Factory\UserFactory::createOne([
            'username' => 'poll-owner',
        ]);
        $client->loginUser($user);
        $poll = Factory\PollFactory::new([
            'owner' => $user,
        ])->classic()->create();

        $this->expectException(NotFoundHttpException::class);

        $client->catchExceptions(false);
        $client->request(Request::METHOD_GET, "/polls/{$poll->getId()}/{$poll->getAdminToken()}/dates");
    }

    public function testGetDatesFailsIfAdminTokenDoesNotMatch(): void
    {
        $client = static::createClient();
        $poll = Factory\PollFactory::new()->date()->create();

        $this->expectException(NotFoundHttpException::class);

        $client->catchExceptions(false);
        $client->request(Request::METHOD_GET, "/polls/{$poll->getId()}/not-the-token/dates");
    }

    public function testPostDatesCreatesDates(): void
    {
        $client = static::createClient();
        $user = Factory\UserFactory::createOne([
            'username' => 'poll-owner',
        ]);
        $client->loginUser($user);
        $poll = Factory\PollFactory::new([
            'owner' => $user,
        ])->date()->create();

        $client->request(Request::METHOD_POST, "/polls/{$poll->getId()}/{$poll->getAdminToken()}/dates", [
            'poll_dates' => [
                '_token' => $this->getCsrf($client, 'poll_dates'),
                'timezoneMode' => 'server',
                'dates' => [
                    ['value' => '2024-11-01'],
                    ['value' => '2024-11-02'],
                ],
            ],
        ]);

        $dates = Factory\DateFactory::all();
        $this->assertSame(2, count($dates));
        $this->assertSame('2024-11-01', $dates[0]->getValue()?->format('Y-m-d'));
        $this->assertSame($poll, $dates[0]->getPoll());
        $this->assertSame('2024-11-02', $dates[1]->getValue()?->format('Y-m-d'));
        $this->assertSame($poll, $dates[1]->getPoll());
        $this->assertResponseRedirects("/polls/{$poll->getId()}/{$poll->getAdminToken()}/slots?flow=on", 302);
    }

    public function testPostDatesSynchronizesExistingVotesWithNewDateSlots(): void
    {
        $client = static::createClient();
        $user = Factory\UserFactory::createOne([
            'username' => 'poll-owner',
        ]);
        $client->loginUser($user);
        $poll = Factory\PollFactory::createOne([
            'type' => 'date',
            'owner' => $user,
        ]);
        $existingDate = Factory\DateFactory::createOne([
            'poll' => $poll,
            'value' => new \DateTimeImmutable('2024-11-01'),
        ]);
        $existingSlot = $existingDate->getProposals()[0];
        $existingVote = Factory\VoteFactory::createOne([
            'poll' => $poll,
        ]);
        $existingAnswer = Factory\AnswerFactory::createOne([
            'vote' => $existingVote,
            'proposal' => $existingSlot,
            'value' => 'yes',
        ]);

        $client->request(Request::METHOD_POST, "/polls/{$poll->getId()}/{$poll->getAdminToken()}/dates", [
            'poll_dates' => [
                '_token' => $this->getCsrf($client, 'poll_dates'),
                'timezoneMode' => 'server',
                'dates' => [
                    ['value' => '2024-11-01'],
                    ['value' => '2024-11-02'],
                    ['value' => '2024-11-03'],
                ],
            ],
        ]);

        $dates = Factory\DateFactory::all();
        $this->assertSame(3, count($dates));
        $slots = Factory\ProposalFactory::all();
        $this->assertSame(3, count($slots));
        $this->assertSame('Day', $slots[0]->getLabel());
        $this->assertSame($dates[0], $slots[0]->getDate());
        $this->assertSame('Day', $slots[1]->getLabel());
        $this->assertSame($dates[1], $slots[1]->getDate());
        $this->assertSame('Day', $slots[2]->getLabel());
        $this->assertSame($dates[2], $slots[2]->getDate());
        $this->refresh($existingVote);
        $voteAnswers = $existingVote->getAnswers()->toArray();
        $this->assertSame(3, count($voteAnswers));
        $this->assertSame($slots[0], $voteAnswers[0]->getProposal());
        $this->assertSame('yes', $voteAnswers[0]->getValue());
        $this->assertSame($slots[1], $voteAnswers[1]->getProposal());
        $this->assertSame('', $voteAnswers[1]->getValue());
        $this->assertSame($slots[2], $voteAnswers[2]->getProposal());
        $this->assertSame('', $voteAnswers[2]->getValue());
    }

    public function testPostDatesFailsIfCsrfIsInvalid(): void
    {
        $client = static::createClient();
        $user = Factory\UserFactory::createOne([
            'username' => 'poll-owner',
        ]);
        $client->loginUser($user);
        $poll = Factory\PollFactory::new([
            'owner' => $user,
        ])->date()->create();

        $client->request(Request::METHOD_POST, "/polls/{$poll->getId()}/{$poll->getAdminToken()}/dates", [
            'poll_dates' => [
                '_token' => 'not the token',
                'timezoneMode' => 'server',
                'dates' => [
                    ['value' => '2024-11-01'],
                    ['value' => '2024-11-02'],
                ],
            ],
        ]);

        $this->assertSelectorTextContains('#poll_dates_error', 'please submit the form again');
        Factory\DateFactory::assert()->count(0);
    }

    public function testGetSlotsRendersCorrectly(): void
    {
        $client = static::createClient();
        $user = Factory\UserFactory::createOne([
            'username' => 'poll-owner',
        ]);
        $client->loginUser($user);
        $poll = Factory\PollFactory::new([
            'owner' => $user,
        ])->withDate()->create();

        $client->request(Request::METHOD_GET, "/polls/{$poll->getId()}/{$poll->getAdminToken()}/slots");

        $this->assertResponseIsSuccessful();
        $this->assertSelectorTextContains('h1', 'Choose the time slots');
    }

    public function testGetSlotsFailsIfTypeIsClassic(): void
    {
        $client = static::createClient();
        $user = Factory\UserFactory::createOne([
            'username' => 'poll-owner',
        ]);
        $client->loginUser($user);
        $poll = Factory\PollFactory::new([
            'owner' => $user,
        ])->classic()->create();

        $this->expectException(NotFoundHttpException::class);

        $client->catchExceptions(false);
        $client->request(Request::METHOD_GET, "/polls/{$poll->getId()}/{$poll->getAdminToken()}/slots");
    }

    public function testGetSlotsRedirectsIfThereAreNoDates(): void
    {
        $client = static::createClient();
        $user = Factory\UserFactory::createOne([
            'username' => 'poll-owner',
        ]);
        $client->loginUser($user);
        $poll = Factory\PollFactory::new([
            'owner' => $user,
        ])->date()->create();

        $client->request(Request::METHOD_GET, "/polls/{$poll->getId()}/{$poll->getAdminToken()}/slots");

        $this->assertResponseRedirects("/polls/{$poll->getId()}/{$poll->getAdminToken()}/dates?flow=on", 302);
    }

    public function testGetSlotsFailsIfAdminTokenDoesNotMatch(): void
    {
        $client = static::createClient();
        $poll = Factory\PollFactory::new()->withDate()->create();

        $this->expectException(NotFoundHttpException::class);

        $client->catchExceptions(false);
        $client->request(Request::METHOD_GET, "/polls/{$poll->getId()}/not-the-token/slots");
    }

    public function testPostSlotsCreatesAProposal(): void
    {
        $client = static::createClient();
        $user = Factory\UserFactory::createOne([
            'username' => 'poll-owner',
        ]);
        $client->loginUser($user);
        $poll = Factory\PollFactory::new([
            'owner' => $user,
        ])->date()->create();
        $date = Factory\DateFactory::createOne([
            'poll' => $poll,
        ]);
        $slot1 = '19:00';
        $slot2 = '20:00';

        $client->request(Request::METHOD_POST, "/polls/{$poll->getId()}/{$poll->getAdminToken()}/slots", [
            'poll_slots' => [
                '_token' => $this->getCsrf($client, 'poll_slots'),
                'dates' => [
                    [
                        'proposals' => [
                            ['label' => $slot1],
                            ['label' => $slot2],
                        ],
                    ],
                ],
            ],
        ]);

        $this->refresh($poll);
        $proposals = $poll->getProposals()->toArray();
        $this->assertSame(2, count($proposals));
        $this->assertSame('19:00:00', $proposals[0]->getLabel());
        $this->assertSame($date, $proposals[0]->getDate());
        $this->assertSame('20:00:00', $proposals[1]->getLabel());
        $this->assertSame($date, $proposals[1]->getDate());
        $this->assertResponseRedirects("/polls/{$poll->getId()}/{$poll->getAdminToken()}/admin", 302);
    }

    public function testPostSlotsCreatesADefaultProposalIfNoneArePosted(): void
    {
        $client = static::createClient();
        $user = Factory\UserFactory::createOne([
            'username' => 'poll-owner',
        ]);
        $client->loginUser($user);
        $poll = Factory\PollFactory::new([
            'owner' => $user,
        ])->date()->create();
        $date = Factory\DateFactory::createOne([
            'poll' => $poll,
        ]);

        $client->request(Request::METHOD_POST, "/polls/{$poll->getId()}/{$poll->getAdminToken()}/slots", [
            'poll_slots' => [
                '_token' => $this->getCsrf($client, 'poll_slots'),
                'dates' => [
                    [
                        'proposals' => [],
                    ],
                ],
            ],
        ]);

        $this->refresh($poll);
        $proposals = $poll->getProposals()->toArray();
        $this->assertSame(1, count($proposals));
        $this->assertSame('Day', $proposals[0]->getLabel());
        $this->assertSame($date, $proposals[0]->getDate());
        $this->assertResponseRedirects("/polls/{$poll->getId()}/{$poll->getAdminToken()}/admin", 302);
    }

    public function testPostSlotsFailsIfCsrfTokenIsInvalid(): void
    {
        $client = static::createClient();
        $user = Factory\UserFactory::createOne([
            'username' => 'poll-owner',
        ]);
        $client->loginUser($user);
        $poll = Factory\PollFactory::new([
            'owner' => $user,
        ])->date()->create();
        $date1 = Factory\DateFactory::createOne([
            'poll' => $poll,
        ]);
        $date2 = Factory\DateFactory::createOne([
            'poll' => $poll,
        ]);
        $slot1 = '19h';
        $slot2 = '20h';

        Factory\ProposalFactory::assert()->count(2);

        $client->request(Request::METHOD_POST, "/polls/{$poll->getId()}/{$poll->getAdminToken()}/slots", [
            'poll_slots' => [
                '_token' => 'not the token',
                'dates' => [
                    [
                        'proposals' => [
                            ['label' => $slot1],
                            ['label' => $slot2],
                        ],
                    ],
                    [
                        'proposals' => [
                            ['label' => $slot2],
                        ],
                    ],
                ],
            ],
        ]);

        $this->assertSelectorTextContains('#poll_slots_error', 'please submit the form again');
        Factory\ProposalFactory::assert()->count(2);
    }

    public function testGetSettingsRendersCorrectly(): void
    {
        $client = static::createClient();
        $user = Factory\UserFactory::createOne([
            'username' => 'settings-owner',
        ]);
        $client->loginUser($user);
        $poll = Factory\PollFactory::new([
            'owner' => $user,
        ])->withProposal()->create();

        $client->request(Request::METHOD_GET, "/polls/{$poll->getId()}/{$poll->getAdminToken()}/settings");

        $this->assertResponseIsSuccessful();
        $this->assertSelectorTextContains('h1', 'Configure the poll');
    }

    public function testGetSettingsRedirectsIfThereAreNoProposals(): void
    {
        $client = static::createClient();
        $user = Factory\UserFactory::createOne([
            'username' => 'settings-owner',
        ]);
        $client->loginUser($user);
        $poll = Factory\PollFactory::createOne([
            'owner' => $user,
        ]);

        $client->request(Request::METHOD_GET, "/polls/{$poll->getId()}/{$poll->getAdminToken()}/settings");

        $this->assertResponseRedirects("/polls/{$poll->getId()}/{$poll->getAdminToken()}/proposals?flow=on", 302);
    }

    public function testGetSettingsFailsIfAdminTokenDoesNotMatch(): void
    {
        $client = static::createClient();
        $poll = Factory\PollFactory::new()->withProposal()->create();

        $this->expectException(NotFoundHttpException::class);

        $client->catchExceptions(false);
        $client->request(Request::METHOD_GET, "/polls/{$poll->getId()}/not-the-token/settings");
    }

    public function testPostSettingsCanChangeOptions(): void
    {
        $client = static::createClient();
        $user = Factory\UserFactory::createOne([
            'username' => 'settings-owner',
        ]);
        $client->loginUser($user);
        $poll = Factory\PollFactory::new([
            'owner' => $user,
        ])->withProposal()->create();
        $maxVotes = 1;
        $slug = 'my-slug';
        $password = 'secret';
        $notifyOnVotes = true;
        $notifyOnComments = true;
        $minWikimediaAccountAgeMonths = 6;
        $minWikimediaEditsProject = 'eswiki';
        $minWikimediaEditsCount = 500;

        $client->request(Request::METHOD_POST, "/polls/{$poll->getId()}/{$poll->getAdminToken()}/settings", [
            'poll_settings' => [
                '_token' => $this->getCsrf($client, 'poll_settings'),
                'maxVotes' => $maxVotes,
                'minWikimediaAccountAgeMonths' => $minWikimediaAccountAgeMonths,
                'minWikimediaEditsProject' => $minWikimediaEditsProject,
                'minWikimediaEditsCount' => $minWikimediaEditsCount,
                'slug' => $slug,
                'isPasswordProtected' => true,
                'plainPassword' => [
                    'first' => $password,
                    'second' => $password,
                ],
                'notifyOnVotes' => $notifyOnVotes,
                'notifyOnComments' => $notifyOnComments,
            ]
        ]);

        $this->refresh($poll);
        $this->assertSame($maxVotes, $poll->getMaxVotes());
        $this->assertSame($minWikimediaAccountAgeMonths, $poll->getMinWikimediaAccountAgeMonths());
        $this->assertSame($minWikimediaEditsProject, $poll->getMinWikimediaEditsProject());
        $this->assertSame($minWikimediaEditsCount, $poll->getMinWikimediaEditsCount());
        $this->assertSame($slug, $poll->getSlug());
        $this->assertTrue($poll->isNotifyOnVotes());
        $this->assertTrue($poll->isNotifyOnComments());
        /** @var Service\PollPassword */
        $pollPassword = static::getContainer()->get(Service\PollPassword::class);
        $this->assertTrue($pollPassword->verify($poll->getPassword() ?? '', $password));
    }

    public function testPostSettingsRequiresProjectAndEditCountTogether(): void
    {
        $client = static::createClient();
        $user = Factory\UserFactory::createOne([
            'username' => 'settings-owner-2',
        ]);
        $client->loginUser($user);
        $poll = Factory\PollFactory::new([
            'owner' => $user,
        ])->withProposal()->create();

        $client->request(Request::METHOD_POST, "/polls/{$poll->getId()}/{$poll->getAdminToken()}/settings", [
            'poll_settings' => [
                '_token' => $this->getCsrf($client, 'poll_settings'),
                'slug' => 'my-slug',
                'minWikimediaEditsProject' => 'eswiki',
            ]
        ]);

        $this->assertSelectorTextContains(
            '#poll_settings_minWikimediaEditsProject_error',
            'Project and minimum edits must be configured together.'
        );
    }

    public function testPostSettingsDoesNotChangePasswordIfNotSet(): void
    {
        $client = static::createClient();
        $user = Factory\UserFactory::createOne([
            'username' => 'settings-owner',
        ]);
        $client->loginUser($user);
        $poll = Factory\PollFactory::new([
            'password' => 'secret',
            'owner' => $user,
        ])->withProposal()->create();
        $maxVotes = 1;
        $slug = 'my-slug';

        $client->request(Request::METHOD_POST, "/polls/{$poll->getId()}/{$poll->getAdminToken()}/settings", [
            'poll_settings' => [
                '_token' => $this->getCsrf($client, 'poll_settings'),
                'maxVotes' => $maxVotes,
                'slug' => $slug,
                'isPasswordProtected' => true,
                'plainPassword' => [
                    'first' => '',
                    'second' => '',
                ],
            ]
        ]);

        $this->refresh($poll);
        /** @var Service\PollPassword */
        $pollPassword = static::getContainer()->get(Service\PollPassword::class);
        $this->assertTrue($pollPassword->verify($poll->getPassword() ?? '', 'secret'));
    }

    public function testPostSettingsRemovesPasswordIfIsPasswordProtectedIsNotSent(): void
    {
        $client = static::createClient();
        $user = Factory\UserFactory::createOne([
            'username' => 'settings-owner',
        ]);
        $client->loginUser($user);
        $poll = Factory\PollFactory::new([
            'password' => 'secret',
            'owner' => $user,
        ])->withProposal()->create();
        $maxVotes = 1;
        $slug = 'my-slug';

        $client->request(Request::METHOD_POST, "/polls/{$poll->getId()}/{$poll->getAdminToken()}/settings", [
            'poll_settings' => [
                '_token' => $this->getCsrf($client, 'poll_settings'),
                'maxVotes' => $maxVotes,
                'slug' => $slug,
                'plainPassword' => [
                    'first' => '',
                    'second' => '',
                ],
            ]
        ]);

        $this->refresh($poll);
        $this->assertFalse($poll->isPasswordProtected());
    }

    public function testPostSettingsFailsIfCsrfIsInvalid(): void
    {
        $client = static::createClient();
        $user = Factory\UserFactory::createOne([
            'username' => 'settings-owner',
        ]);
        $client->loginUser($user);
        $poll = Factory\PollFactory::new([
            'owner' => $user,
        ])->withProposal()->create();
        $slug = 'my-slug';

        $client->request(Request::METHOD_POST, "/polls/{$poll->getId()}/{$poll->getAdminToken()}/settings", [
            'poll_settings' => [
                '_token' => 'not the token',
                'slug' => $slug,
            ]
        ]);

        $this->assertSelectorTextContains('#poll_settings_error', 'please submit the form again');
        $this->refresh($poll);
        $this->assertSame($poll->getId(), $poll->getSlug());
    }

    public function testGetSummaryRendersCorrectly(): void
    {
        $client = static::createClient();
        $user = Factory\UserFactory::createOne([
            'username' => 'summary-owner',
        ]);
        $client->loginUser($user);
        $poll = Factory\PollFactory::new([
            'owner' => $user,
        ])->withProposal()->withAuthor()->create();

        $client->request(Request::METHOD_GET, "/polls/{$poll->getId()}/{$poll->getAdminToken()}/summary");

        $this->assertResponseIsSuccessful();
        $this->assertSelectorTextContains('h1', 'Summary of your poll');
    }

    public function testGetSummaryRedirectsIfThereAreNoProposals(): void
    {
        $client = static::createClient();
        $user = Factory\UserFactory::createOne([
            'username' => 'summary-owner',
        ]);
        $client->loginUser($user);
        $poll = Factory\PollFactory::createOne([
            'owner' => $user,
        ]);

        $client->request(Request::METHOD_GET, "/polls/{$poll->getId()}/{$poll->getAdminToken()}/summary");

        $this->assertResponseRedirects("/polls/{$poll->getId()}/{$poll->getAdminToken()}/proposals?flow=on", 302);
    }

    public function testGetSummaryFailsIfAdminTokenDoesNotMatch(): void
    {
        $client = static::createClient();
        $poll = Factory\PollFactory::new()->withProposal()->withAuthor()->create();

        $this->expectException(NotFoundHttpException::class);

        $client->catchExceptions(false);
        $client->request(Request::METHOD_GET, "/polls/{$poll->getId()}/not-the-token/summary");
    }

    public function testPostSummaryCompletesThePoll(): void
    {
        $client = static::createClient();
        $user = Factory\UserFactory::createOne([
            'username' => 'summary-owner',
        ]);
        $client->loginUser($user);
        $title = 'My poll';
        $authorEmail = 'alix@example.org';
        $poll = Factory\PollFactory::new([
            'title' => $title,
            'authorEmail' => $authorEmail,
            'owner' => $user,
        ])->withProposal()->withAuthor()->create();

        $client->request(Request::METHOD_POST, "/polls/{$poll->getId()}/{$poll->getAdminToken()}/summary", [
            'poll_summary' => [
                '_token' => $this->getCsrf($client, 'poll_summary'),
            ]
        ]);

        $this->refresh($poll);
        $this->assertTrue($poll->isCompleted());
        $this->assertEmailCount(1);
        $email = $this->getMailerMessage();
        $this->assertNotNull($email);
        $this->assertEmailTextBodyContains($email, $title);
        $this->assertEmailAddressContains($email, 'To', $authorEmail);
    }

    public function testPostSummaryFailsIfCsrfIsInvalid(): void
    {
        $client = static::createClient();
        $user = Factory\UserFactory::createOne([
            'username' => 'summary-owner',
        ]);
        $client->loginUser($user);
        $poll = Factory\PollFactory::new([
            'owner' => $user,
        ])->withProposal()->withAuthor()->create();

        $client->request(Request::METHOD_POST, "/polls/{$poll->getId()}/{$poll->getAdminToken()}/summary", [
            'poll_summary' => [
                '_token' => 'not the token',
            ]
        ]);

        $this->assertSelectorTextContains('#poll_summary_error', 'please submit the form again');
        $this->refresh($poll);
        $this->assertFalse($poll->isCompleted());
    }

    public function testGetCompleteRendersCorrectly(): void
    {
        $client = static::createClient();
        $user = Factory\UserFactory::createOne([
            'username' => 'poll-owner',
        ]);
        $client->loginUser($user);
        $poll = Factory\PollFactory::new([
            'owner' => $user,
        ])->completed()->create();

        $client->request(Request::METHOD_GET, "/polls/{$poll->getId()}/{$poll->getAdminToken()}/complete");

        $this->assertResponseIsSuccessful();
        $this->assertSelectorTextContains('h1', 'Your poll is ready');
        $this->assertSelectorExists('[data-controller="storage"][data-storage-namespace-value="polls"]');
    }

    public function testGetCompleteRedirectsIfNotCompleted(): void
    {
        $client = static::createClient();
        $user = Factory\UserFactory::createOne([
            'username' => 'poll-owner',
        ]);
        $client->loginUser($user);
        $poll = Factory\PollFactory::new([
            'owner' => $user,
        ])->withProposal()->withAuthor()->create();

        $client->request(Request::METHOD_GET, "/polls/{$poll->getId()}/{$poll->getAdminToken()}/complete");

        $this->assertResponseRedirects("/polls/{$poll->getId()}/{$poll->getAdminToken()}/admin", 302);
    }

    public function testGetCompleteFailsIfAdminTokenDoesNotMatch(): void
    {
        $client = static::createClient();
        $poll = Factory\PollFactory::new()->completed()->create();

        $this->expectException(NotFoundHttpException::class);

        $client->catchExceptions(false);
        $client->request(Request::METHOD_GET, "/polls/{$poll->getId()}/not-the-token/complete");
    }

    public function testGetAdminRendersCorrectly(): void
    {
        $client = static::createClient();
        $user = Factory\UserFactory::createOne([
            'username' => 'poll-owner',
        ]);
        $client->loginUser($user);
        $poll = Factory\PollFactory::new([
            'owner' => $user,
        ])->completed()->create();

        $client->request(Request::METHOD_GET, "/polls/{$poll->getId()}/{$poll->getAdminToken()}/admin");

        $this->assertResponseIsSuccessful();
        $this->assertSelectorTextContains('h1', 'Administration of the poll');
    }

    public function testGetAdminRedirectsIfNotCompleted(): void
    {
        $client = static::createClient();
        $user = Factory\UserFactory::createOne([
            'username' => 'poll-owner',
        ]);
        $client->loginUser($user);
        $poll = Factory\PollFactory::new([
            'owner' => $user,
        ])->withProposal()->withAuthor()->create();

        $client->request(Request::METHOD_GET, "/polls/{$poll->getId()}/{$poll->getAdminToken()}/admin");

        $this->assertResponseRedirects("/polls/{$poll->getId()}/{$poll->getAdminToken()}/summary?flow=on", 302);
    }

    public function testGetAdminFailsIfAdminTokenDoesNotMatch(): void
    {
        $client = static::createClient();
        $poll = Factory\PollFactory::new()->completed()->create();

        $this->expectException(NotFoundHttpException::class);

        $client->catchExceptions(false);
        $client->request(Request::METHOD_GET, "/polls/{$poll->getId()}/not-the-token/admin");
    }

    public function testGetDeletionRendersCorrectly(): void
    {
        $client = static::createClient();
        $user = Factory\UserFactory::createOne([
            'username' => 'poll-owner',
        ]);
        $client->loginUser($user);
        $poll = Factory\PollFactory::new([
            'owner' => $user,
        ])->completed()->create();

        $client->request(
            Request::METHOD_GET,
            "/polls/{$poll->getId()}/{$poll->getAdminToken()}/deletion",
        );

        $this->assertResponseIsSuccessful();
        $this->assertSelectorTextContains('h1', 'Deletion of a poll');
    }

    public function testGetDeletionFailsIfAdminTokenIsInvalid(): void
    {
        $client = static::createClient();
        $poll = Factory\PollFactory::new()->completed()->create();

        $this->expectException(NotFoundHttpException::class);

        $client->catchExceptions(false);
        $client->request(
            Request::METHOD_GET,
            "/polls/{$poll->getId()}/not-the-token/deletion",
        );
    }

    public function testPostDeletionDeletesThePoll(): void
    {
        $client = static::createClient();
        $user = Factory\UserFactory::createOne([
            'username' => 'poll-owner',
        ]);
        $client->loginUser($user);
        $poll = Factory\PollFactory::new([
            'owner' => $user,
        ])->completed()->create();

        $client->request(
            Request::METHOD_POST,
            "/polls/{$poll->getId()}/{$poll->getAdminToken()}/deletion",
            [
                'poll_deletion' => [
                    '_token' => $this->getCsrf($client, 'poll_deletion'),
                ],
            ]
        );

        $this->assertResponseRedirects('/', 302);
        Factory\PollFactory::assert()->notExists(['id' => $poll->getId()]);
    }

    public function testPostDeletionFailsIfCsrfIsInvalid(): void
    {
        $client = static::createClient();
        $user = Factory\UserFactory::createOne([
            'username' => 'poll-owner',
        ]);
        $client->loginUser($user);
        $poll = Factory\PollFactory::new([
            'owner' => $user,
        ])->completed()->create();

        $client->request(
            Request::METHOD_POST,
            "/polls/{$poll->getId()}/{$poll->getAdminToken()}/deletion",
            [
                'poll_deletion' => [
                    '_token' => 'not the token',
                ],
            ]
        );

        Factory\PollFactory::assert()->exists(['id' => $poll->getId()]);
    }
}
