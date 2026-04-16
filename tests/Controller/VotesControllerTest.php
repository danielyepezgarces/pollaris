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

use App\Tests\Helper;
use App\Tests\Factory;
use App\Utils;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Zenstruck\Foundry;

class VotesControllerTest extends WebTestCase
{
    use Foundry\Test\Factories;
    use Foundry\Test\ResetDatabase;
    use Helper\CsrfHelper;
    use Helper\FactoryHelper;

    public function testGetEditRendersCorrectly(): void
    {
        $client = static::createClient();
        $owner = Factory\UserFactory::createOne([
            'username' => 'vote-owner-show',
        ]);
        $client->loginUser($owner);
        $poll = Factory\PollFactory::new([
            'title' => 'My poll',
        ])->completed()->create();
        $vote = Factory\VoteFactory::createOne([
            'poll' => $poll,
            'owner' => $owner,
            'authorName' => $owner->getUserIdentifier(),
        ]);

        $client->request(Request::METHOD_GET, "/polls/{$poll->getSlug()}/votes/{$vote->getId()}/edit");

        $this->assertResponseIsSuccessful();
        $this->assertSelectorTextContains('h1', 'My poll');
    }

    public function testGetEditRedirectsToPollIfPollIsClosed(): void
    {
        $client = static::createClient();
        $owner = Factory\UserFactory::createOne([
            'username' => 'vote-owner-closed',
        ]);
        $client->loginUser($owner);
        $poll = Factory\PollFactory::new([
            'title' => 'My poll',
            'closedAt' => Utils\Time::ago(1, 'day'),
        ])->completed()->create();
        $vote = Factory\VoteFactory::createOne([
            'poll' => $poll,
            'owner' => $owner,
            'authorName' => $owner->getUserIdentifier(),
        ]);

        $client->request(Request::METHOD_GET, "/polls/{$poll->getSlug()}/votes/{$vote->getId()}/edit");

        $this->assertResponseRedirects("/polls/{$poll->getSlug()}", 302);
    }

    public function testGetEditRedirectsIfEditionIsDisabled(): void
    {
        $client = static::createClient();
        $owner = Factory\UserFactory::createOne([
            'username' => 'vote-owner-disabled',
        ]);
        $client->loginUser($owner);
        $poll = Factory\PollFactory::new([
            'title' => 'My poll',
            'editVoteMode' => 'no',
        ])->completed()->create();
        $vote = Factory\VoteFactory::createOne([
            'poll' => $poll,
            'owner' => $owner,
            'authorName' => $owner->getUserIdentifier(),
        ]);

        $client->request(Request::METHOD_GET, "/polls/{$poll->getSlug()}/votes/{$vote->getId()}/edit");

        $this->assertResponseRedirects("/polls/{$poll->getSlug()}", 302);
    }

    public function testGetEditFailsIfPollIdDoesNotMatch(): void
    {
        $client = static::createClient();
        $poll1 = Factory\PollFactory::new()->completed()->create();
        $poll2 = Factory\PollFactory::new()->completed()->create();
        $vote = Factory\VoteFactory::createOne([
            'poll' => $poll1,
        ]);

        $this->expectException(NotFoundHttpException::class);

        $client->catchExceptions(false);
        $client->request(Request::METHOD_GET, "/polls/{$poll2->getSlug()}/votes/{$vote->getId()}/edit");
    }

    public function testGuestCannotEditVoteOwnedByLoggedInUser(): void
    {
        $client = static::createClient();
        $poll = Factory\PollFactory::new()->completed()->create();
        $owner = Factory\UserFactory::createOne();
        $vote = Factory\VoteFactory::createOne([
            'poll' => $poll,
            'owner' => $owner,
            'authorName' => $owner->getUserIdentifier(),
        ]);

        $client->request(Request::METHOD_GET, "/polls/{$poll->getSlug()}/votes/{$vote->getId()}/edit");

        $this->assertResponseRedirects("/polls/{$poll->getSlug()}", 302);
    }

    public function testLoggedInUserCannotEditAnotherUsersVote(): void
    {
        $client = static::createClient();
        $poll = Factory\PollFactory::new()->completed()->create();
        $owner = Factory\UserFactory::createOne([
            'username' => 'vote-owner',
        ]);
        $intruder = Factory\UserFactory::createOne([
            'username' => 'vote-intruder',
        ]);
        $client->loginUser($intruder);
        $vote = Factory\VoteFactory::createOne([
            'poll' => $poll,
            'owner' => $owner,
            'authorName' => $owner->getUserIdentifier(),
        ]);

        $client->request(Request::METHOD_GET, "/polls/{$poll->getSlug()}/votes/{$vote->getId()}/edit");

        $this->assertResponseRedirects("/polls/{$poll->getSlug()}", 302);
    }

    public function testPostEditDoesNotAllowLoggedInUserToEditAnotherUsersVote(): void
    {
        $client = static::createClient();
        $poll = Factory\PollFactory::new()->completed()->create();
        $proposal = $poll->getProposals()->first();
        $owner = Factory\UserFactory::createOne([
            'username' => 'vote-owner-post',
        ]);
        $intruder = Factory\UserFactory::createOne([
            'username' => 'vote-intruder-post',
        ]);
        $client->loginUser($intruder);
        $vote = Factory\VoteFactory::createOne([
            'poll' => $poll,
            'owner' => $owner,
            'authorName' => $owner->getUserIdentifier(),
        ]);
        $answer = Factory\AnswerFactory::createOne([
            'vote' => $vote,
            'proposal' => $proposal,
            'value' => 'no',
        ]);

        $client->request(Request::METHOD_POST, "/polls/{$poll->getSlug()}/votes/{$vote->getId()}/edit", [
            'vote' => [
                '_token' => $this->getCsrf($client, 'vote'),
                'authorName' => $intruder->getUserIdentifier(),
                'answers' => [
                    ['value' => 'yes'],
                ],
            ],
        ]);

        $this->assertResponseRedirects("/polls/{$poll->getSlug()}", 302);
        $this->refresh($vote);
        $this->assertSame($owner->getUserIdentifier(), $vote->getAuthorName());
        $this->assertSame($owner->getId(), $vote->getOwner()?->getId());
        $this->refresh($answer);
        $this->assertSame('no', $answer->getValue());
    }

    public function testPostEditChangesTheVoteValues(): void
    {
        $client = static::createClient();
        $owner = Factory\UserFactory::createOne([
            'username' => 'vote-owner-edit',
        ]);
        $client->loginUser($owner);
        $poll = Factory\PollFactory::new()->completed()->create();
        $proposal = $poll->getProposals()->first();
        $oldValue = 'no';
        $newValue = 'yes';
        $vote = Factory\VoteFactory::createOne([
            'poll' => $poll,
            'owner' => $owner,
            'authorName' => $owner->getUserIdentifier(),
        ]);
        $answer = Factory\AnswerFactory::createOne([
            'vote' => $vote,
            'proposal' => $proposal,
            'value' => $oldValue,
        ]);

        $client->request(Request::METHOD_POST, "/polls/{$poll->getSlug()}/votes/{$vote->getId()}/edit", [
            'vote' => [
                '_token' => $this->getCsrf($client, 'vote'),
                'authorName' => 'should-not-change',
                'answers' => [
                    ['value' => $newValue],
                ],
            ],
        ]);

        $this->refresh($vote);
        $this->assertSame($owner->getUserIdentifier(), $vote->getAuthorName());
        $this->refresh($answer);
        $this->assertSame($newValue, $answer->getValue());
    }

    public function testPostEditFailsIfRequiredPasswordIsIncorrect(): void
    {
        $client = static::createClient();
        $owner = Factory\UserFactory::createOne([
            'username' => 'vote-owner-password',
        ]);
        $client->loginUser($owner);
        $poll = Factory\PollFactory::new([
            'password' => 'secret',
            'isPasswordForVotesOnly' => true,
        ])->completed()->create();
        $proposal = $poll->getProposals()->first();
        $oldValue = 'no';
        $newValue = 'yes';
        $vote = Factory\VoteFactory::createOne([
            'poll' => $poll,
            'owner' => $owner,
            'authorName' => $owner->getUserIdentifier(),
        ]);
        $answer = Factory\AnswerFactory::createOne([
            'vote' => $vote,
            'proposal' => $proposal,
            'value' => $oldValue,
        ]);

        $client->request(Request::METHOD_POST, "/polls/{$poll->getSlug()}/votes/{$vote->getId()}/edit", [
            'vote' => [
                '_token' => $this->getCsrf($client, 'vote'),
                'authorName' => $owner->getUserIdentifier(),
                'answers' => [
                    ['value' => $newValue],
                ],
                'password' => 'not the password',
            ],
        ]);

        $this->assertSelectorTextContains('#vote_password_error', 'The password is incorrect');
        $vote = Factory\VoteFactory::find($vote->getId());
        $this->assertSame($owner->getUserIdentifier(), $vote->getAuthorName());
        $answer = Factory\AnswerFactory::find($answer->getId());
        $this->assertSame($oldValue, $answer->getValue());
    }

    public function testPostEditFailsIfCsrfIsInvalid(): void
    {
        $client = static::createClient();
        $owner = Factory\UserFactory::createOne([
            'username' => 'vote-owner-csrf',
        ]);
        $client->loginUser($owner);
        $poll = Factory\PollFactory::new()->completed()->create();
        $proposal = $poll->getProposals()->first();
        $oldValue = 'no';
        $newValue = 'yes';
        $vote = Factory\VoteFactory::createOne([
            'poll' => $poll,
            'owner' => $owner,
            'authorName' => $owner->getUserIdentifier(),
        ]);
        $answer = Factory\AnswerFactory::createOne([
            'vote' => $vote,
            'proposal' => $proposal,
            'value' => $oldValue,
        ]);

        $client->request(Request::METHOD_POST, "/polls/{$poll->getSlug()}/votes/{$vote->getId()}/edit", [
            'vote' => [
                '_token' => 'not the token',
                'authorName' => $owner->getUserIdentifier(),
                'answers' => [
                    ['value' => $newValue],
                ],
            ],
        ]);

        $this->assertSelectorTextContains('#vote_error', 'please submit the form again');
        $vote = Factory\VoteFactory::find($vote->getId());
        $this->assertSame($owner->getUserIdentifier(), $vote->getAuthorName());
        $answer = Factory\AnswerFactory::find($answer->getId());
        $this->assertSame($oldValue, $answer->getValue());
    }

    public function testGuestCannotEditUnownedVote(): void
    {
        $client = static::createClient();
        $poll = Factory\PollFactory::new()->completed()->create();
        $vote = Factory\VoteFactory::createOne([
            'poll' => $poll,
            'authorName' => 'guest-vote',
        ]);

        $client->request(Request::METHOD_GET, "/polls/{$poll->getSlug()}/votes/{$vote->getId()}/edit");

        $this->assertResponseRedirects("/polls/{$poll->getSlug()}", 302);
    }

    public function testPostDeletionDeletesTheVote(): void
    {
        $client = static::createClient();
        $owner = Factory\UserFactory::createOne();
        $client->loginUser($owner);
        $poll = Factory\PollFactory::new([
            'owner' => $owner,
        ])->completed()->create();
        $vote = Factory\VoteFactory::createOne([
            'poll' => $poll,
        ]);

        $client->request(
            Request::METHOD_POST,
            "/polls/{$poll->getId()}/{$poll->getAdminToken()}/votes/{$vote->getId()}/deletion",
            [
                '_csrf_token' => $this->getCsrf($client, 'delete vote'),
            ]
        );

        $this->assertResponseRedirects("/polls/{$poll->getId()}/{$poll->getAdminToken()}/admin", 302);
        Factory\VoteFactory::assert()->notExists(['id' => $vote->getId()]);
    }

    public function testPostDeletionFailsIfCsrfIsInvalid(): void
    {
        $client = static::createClient();
        $owner = Factory\UserFactory::createOne();
        $client->loginUser($owner);
        $poll = Factory\PollFactory::new([
            'owner' => $owner,
        ])->completed()->create();
        $vote = Factory\VoteFactory::createOne([
            'poll' => $poll,
        ]);

        $client->request(
            Request::METHOD_POST,
            "/polls/{$poll->getId()}/{$poll->getAdminToken()}/votes/{$vote->getId()}/deletion",
            [
                '_csrf_token' => 'not the token',
            ]
        );

        $this->assertResponseRedirects("/polls/{$poll->getId()}/{$poll->getAdminToken()}/admin", 302);
        Factory\VoteFactory::assert()->exists(['id' => $vote->getId()]);
    }

    public function testPostDeletionFailsIfAdminTokenIsInvalid(): void
    {
        $client = static::createClient();
        $owner = Factory\UserFactory::createOne();
        $client->loginUser($owner);
        $poll = Factory\PollFactory::new([
            'owner' => $owner,
        ])->completed()->create();
        $vote = Factory\VoteFactory::createOne([
            'poll' => $poll,
        ]);

        $this->expectException(NotFoundHttpException::class);

        $client->catchExceptions(false);
        $client->request(
            Request::METHOD_POST,
            "/polls/{$poll->getId()}/not-the-token/votes/{$vote->getId()}/deletion",
            [
                '_csrf_token' => $this->getCsrf($client, 'delete vote'),
            ]
        );
    }

    public function testPostDeletionFailsIfVoteIsNotPartOfPoll(): void
    {
        $client = static::createClient();
        $owner = Factory\UserFactory::createOne();
        $client->loginUser($owner);
        $poll = Factory\PollFactory::new([
            'owner' => $owner,
        ])->completed()->create();
        $otherPoll = Factory\PollFactory::new()->completed()->create();
        $vote = Factory\VoteFactory::createOne([
            'poll' => $otherPoll,
        ]);

        $this->expectException(NotFoundHttpException::class);

        $client->catchExceptions(false);
        $client->request(
            Request::METHOD_POST,
            "/polls/{$poll->getId()}/{$poll->getAdminToken()}/votes/{$vote->getId()}/deletion",
            [
                '_csrf_token' => $this->getCsrf($client, 'delete vote'),
            ]
        );
    }
}
