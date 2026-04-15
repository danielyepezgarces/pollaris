<?php

// This file is part of Pollaris.
// Copyright 2024-2026 Marien Fressinaud
// SPDX-License-Identifier: AGPL-3.0-or-later

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
        $poll = Factory\PollFactory::new([
            'title' => 'My poll',
        ])->completed()->create();
        $vote = Factory\VoteFactory::createOne([
            'poll' => $poll,
        ]);

        $client->request(Request::METHOD_GET, "/polls/{$poll->getSlug()}/votes/{$vote->getId()}/edit");

        $this->assertResponseIsSuccessful();
        $this->assertSelectorTextContains('h1', 'My poll');
    }

    public function testGetEditRedirectsToPollIfPollIsClosed(): void
    {
        $client = static::createClient();
        $poll = Factory\PollFactory::new([
            'title' => 'My poll',
            'closedAt' => Utils\Time::ago(1, 'day'),
        ])->completed()->create();
        $vote = Factory\VoteFactory::createOne([
            'poll' => $poll,
        ]);

        $client->request(Request::METHOD_GET, "/polls/{$poll->getSlug()}/votes/{$vote->getId()}/edit");

        $this->assertResponseRedirects("/polls/{$poll->getSlug()}", 302);
    }

    public function testGetEditRedirectsIfEditionIsDisabled(): void
    {
        $client = static::createClient();
        $poll = Factory\PollFactory::new([
            'title' => 'My poll',
            'editVoteMode' => 'no',
        ])->completed()->create();
        $vote = Factory\VoteFactory::createOne([
            'poll' => $poll,
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

    public function testPostEditChangesTheVoteValues(): void
    {
        $client = static::createClient();
        $poll = Factory\PollFactory::new()->completed()->create();
        $proposal = $poll->getProposals()->first();
        $oldName = 'Alix';
        $newName = 'Benedict';
        $oldValue = 'no';
        $newValue = 'yes';
        $vote = Factory\VoteFactory::createOne([
            'poll' => $poll,
            'authorName' => $oldName,
        ]);
        $answer = Factory\AnswerFactory::createOne([
            'vote' => $vote,
            'proposal' => $proposal,
            'value' => $oldValue,
        ]);

        $client->request(Request::METHOD_POST, "/polls/{$poll->getSlug()}/votes/{$vote->getId()}/edit", [
            'vote' => [
                '_token' => $this->getCsrf($client, 'vote'),
                'authorName' => $newName,
                'answers' => [
                    ['value' => $newValue],
                ],
            ],
        ]);

        $this->refresh($vote);
        $this->assertSame($newName, $vote->getAuthorName());
        $this->refresh($answer);
        $this->assertSame($newValue, $answer->getValue());
    }

    public function testPostEditFailsIfRequiredPasswordIsIncorrect(): void
    {
        $client = static::createClient();
        $poll = Factory\PollFactory::new([
            'password' => 'secret',
            'isPasswordForVotesOnly' => true,
        ])->completed()->create();
        $proposal = $poll->getProposals()->first();
        $oldName = 'Alix';
        $newName = 'Benedict';
        $oldValue = 'no';
        $newValue = 'yes';
        $vote = Factory\VoteFactory::createOne([
            'poll' => $poll,
            'authorName' => $oldName,
        ]);
        $answer = Factory\AnswerFactory::createOne([
            'vote' => $vote,
            'proposal' => $proposal,
            'value' => $oldValue,
        ]);

        $client->request(Request::METHOD_POST, "/polls/{$poll->getSlug()}/votes/{$vote->getId()}/edit", [
            'vote' => [
                '_token' => $this->getCsrf($client, 'vote'),
                'authorName' => $newName,
                'answers' => [
                    ['value' => $newValue],
                ],
                'password' => 'not the password',
            ],
        ]);

        $this->assertSelectorTextContains('#vote_password_error', 'The password is incorrect');
        $vote = Factory\VoteFactory::find($vote->getId());
        $this->assertSame($oldName, $vote->getAuthorName());
        $answer = Factory\AnswerFactory::find($answer->getId());
        $this->assertSame($oldValue, $answer->getValue());
    }

    public function testPostEditFailsIfCsrfIsInvalid(): void
    {
        $client = static::createClient();
        $poll = Factory\PollFactory::new()->completed()->create();
        $proposal = $poll->getProposals()->first();
        $oldName = 'Alix';
        $newName = 'Benedict';
        $oldValue = 'no';
        $newValue = 'yes';
        $vote = Factory\VoteFactory::createOne([
            'poll' => $poll,
            'authorName' => $oldName,
        ]);
        $answer = Factory\AnswerFactory::createOne([
            'vote' => $vote,
            'proposal' => $proposal,
            'value' => $oldValue,
        ]);

        $client->request(Request::METHOD_POST, "/polls/{$poll->getSlug()}/votes/{$vote->getId()}/edit", [
            'vote' => [
                '_token' => 'not the token',
                'authorName' => $newName,
                'answers' => [
                    ['value' => $newValue],
                ],
            ],
        ]);

        $this->assertSelectorTextContains('#vote_error', 'please submit the form again');
        $vote = Factory\VoteFactory::find($vote->getId());
        $this->assertSame($oldName, $vote->getAuthorName());
        $answer = Factory\AnswerFactory::find($answer->getId());
        $this->assertSame($oldValue, $answer->getValue());
    }

    public function testPostVoteFailsIfAuthorNameAlreadyUsed(): void
    {
        $client = static::createClient();
        $poll = Factory\PollFactory::new()->completed()->create();
        $proposal = $poll->getProposals()->first();
        $vote = Factory\VoteFactory::createOne([
            'poll' => $poll,
            'authorName' => 'Alix',
        ]);
        $answer = Factory\AnswerFactory::createOne([
            'vote' => $vote,
            'proposal' => $proposal,
            'value' => 'no',
        ]);
        $newVote = Factory\VoteFactory::createOne([
            'poll' => $poll,
            'authorName' => 'Kael',
        ]);
        $newAnswer = Factory\AnswerFactory::createOne([
            'vote' => $newVote,
            'proposal' => $proposal,
            'value' => 'yes',
        ]);

        $client->request(Request::METHOD_POST, "/polls/{$poll->getSlug()}/votes/{$newVote->getId()}/edit", [
            'vote' => [
                '_token' => $this->getCsrf($client, 'vote'),
                'authorName' => ' ALiX ',
                'answers' => [
                    ['value' => 'no'],
                ],
            ],
        ]);

        $this->assertSelectorTextContains('#vote_authorName_error', 'Enter a different name');
        $newVote = Factory\VoteFactory::find($newVote->getId());
        $this->assertSame('Kael', $newVote->getAuthorName());
        $newAnswer = Factory\AnswerFactory::find($newAnswer->getId());
        $this->assertSame('yes', $newAnswer->getValue());
    }

    public function testPostDeletionDeletesTheVote(): void
    {
        $client = static::createClient();
        $poll = Factory\PollFactory::new()->completed()->create();
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
        $poll = Factory\PollFactory::new()->completed()->create();
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
        $poll = Factory\PollFactory::new()->completed()->create();
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
        $poll = Factory\PollFactory::new()->completed()->create();
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
