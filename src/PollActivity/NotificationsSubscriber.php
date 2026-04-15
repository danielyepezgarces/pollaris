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

namespace App\PollActivity;

use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Address;
use Symfony\Contracts\Translation\TranslatorInterface;

class NotificationsSubscriber implements EventSubscriberInterface
{
    public static function getSubscribedEvents(): array
    {
        return [
            PollEvent::COMPLETED => 'sendAdminEmail',
            VoteEvent::NEW => 'notifyNewVote',
            CommentEvent::NEW => 'notifyNewComment',
        ];
    }

    public function __construct(
        private MailerInterface $mailer,
        private TranslatorInterface $translator,
        #[Autowire('%app.name%')]
        private string $appName,
    ) {
    }

    public function sendAdminEmail(PollEvent $event): void
    {
        $poll = $event->getPoll();

        if (!$poll->getAuthorEmail() || !$poll->isCompleted()) {
            return;
        }

        $to = new Address($poll->getAuthorEmail(), $poll->getAuthorName());
        $locale = $poll->getLocale();

        $subject = "[{$this->appName}] ";
        $subject .= $this->translator->trans('emails.new_poll_admin.subject', [
            'poll_name' => $poll->getTitle(),
        ], locale: $locale);

        $email = (new TemplatedEmail())
            ->to($to)
            ->subject($subject)
            ->textTemplate('emails/new_poll_admin.txt.twig')
            ->locale($locale)
            ->context([
                'poll' => $poll,
            ]);

        $this->mailer->send($email);
    }

    public function notifyNewVote(VoteEvent $event): void
    {
        $vote = $event->getVote();
        $poll = $vote->getPoll();

        if (!$poll->getAuthorEmail() || !$poll->isNotifyOnVotes()) {
            return;
        }

        $to = new Address($poll->getAuthorEmail(), $poll->getAuthorName());
        $locale = $poll->getLocale();

        $subject = "[{$this->appName}] ";
        $subject .= $this->translator->trans('emails.new_vote.subject', [
            'poll_name' => $poll->getTitle(),
        ], locale: $locale);

        $email = (new TemplatedEmail())
            ->to($to)
            ->subject($subject)
            ->textTemplate('emails/new_vote.txt.twig')
            ->locale($locale)
            ->context([
                'admin_name' => $poll->getAuthorName(),
                'voter_name' => $vote->getAuthorName(),
                'poll_name' => $poll->getTitle(),
                'poll_slug' => $poll->getSlug(),
            ]);

        $this->mailer->send($email);
    }

    public function notifyNewComment(CommentEvent $event): void
    {
        $comment = $event->getComment();
        $poll = $comment->getPoll();

        if (!$poll->getAuthorEmail() || !$poll->isNotifyOnComments()) {
            return;
        }

        $to = new Address($poll->getAuthorEmail(), $poll->getAuthorName());
        $locale = $poll->getLocale();

        $subject = "[{$this->appName}] ";
        $subject .= $this->translator->trans('emails.new_comment.subject', [
            'poll_name' => $poll->getTitle(),
        ], locale: $locale);

        $email = (new TemplatedEmail())
            ->to($to)
            ->subject($subject)
            ->textTemplate('emails/new_comment.txt.twig')
            ->locale($locale)
            ->context([
                'admin_name' => $poll->getAuthorName(),
                'commenter_name' => $comment->getAuthorName(),
                'poll_name' => $poll->getTitle(),
                'poll_slug' => $poll->getSlug(),
            ]);

        $this->mailer->send($email);
    }
}
