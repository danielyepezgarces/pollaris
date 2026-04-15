<?php

// This file is part of Pollaris.
// Copyright 2024-2026 Marien Fressinaud
// SPDX-License-Identifier: AGPL-3.0-or-later

namespace App\Tests\Controller;

use App\Tests\Helper;
use App\Tests\Factory;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Zenstruck\Foundry;

class CommentsControllerTest extends WebTestCase
{
    use Foundry\Test\Factories;
    use Foundry\Test\ResetDatabase;
    use Helper\CsrfHelper;
    use Helper\FactoryHelper;

    public function testPostDeletionDeletesTheComment(): void
    {
        $client = static::createClient();
        $poll = Factory\PollFactory::new()->completed()->create();
        $comment = Factory\CommentFactory::createOne([
            'poll' => $poll,
        ]);

        $client->request(
            Request::METHOD_POST,
            "/polls/{$poll->getId()}/{$poll->getAdminToken()}/comments/{$comment->getId()}/deletion",
            [
                '_csrf_token' => $this->getCsrf($client, 'delete comment'),
            ]
        );

        $this->assertResponseRedirects("/polls/{$poll->getId()}/{$poll->getAdminToken()}/admin", 302);
        Factory\CommentFactory::assert()->notExists(['id' => $comment->getId()]);
    }

    public function testPostDeletionFailsIfCsrfIsInvalid(): void
    {
        $client = static::createClient();
        $poll = Factory\PollFactory::new()->completed()->create();
        $comment = Factory\CommentFactory::createOne([
            'poll' => $poll,
        ]);

        $client->request(
            Request::METHOD_POST,
            "/polls/{$poll->getId()}/{$poll->getAdminToken()}/comments/{$comment->getId()}/deletion",
            [
                '_csrf_token' => 'not the token',
            ]
        );

        $this->assertResponseRedirects("/polls/{$poll->getId()}/{$poll->getAdminToken()}/admin", 302);
        Factory\CommentFactory::assert()->exists(['id' => $comment->getId()]);
    }

    public function testPostDeletionFailsIfAdminTokenIsInvalid(): void
    {
        $client = static::createClient();
        $poll = Factory\PollFactory::new()->completed()->create();
        $comment = Factory\CommentFactory::createOne([
            'poll' => $poll,
        ]);

        $this->expectException(NotFoundHttpException::class);

        $client->catchExceptions(false);
        $client->request(
            Request::METHOD_POST,
            "/polls/{$poll->getId()}/not-the-token/comments/{$comment->getId()}/deletion",
            [
                '_csrf_token' => $this->getCsrf($client, 'delete comment'),
            ]
        );
    }

    public function testPostDeletionFailsIfCommentIsNotPartOfPoll(): void
    {
        $client = static::createClient();
        $poll = Factory\PollFactory::new()->completed()->create();
        $otherPoll = Factory\PollFactory::new()->completed()->create();
        $comment = Factory\CommentFactory::createOne([
            'poll' => $otherPoll,
        ]);

        $this->expectException(NotFoundHttpException::class);

        $client->catchExceptions(false);
        $client->request(
            Request::METHOD_POST,
            "/polls/{$poll->getId()}/{$poll->getAdminToken()}/comments/{$comment->getId()}/deletion",
            [
                '_csrf_token' => $this->getCsrf($client, 'delete comment'),
            ]
        );
    }
}
