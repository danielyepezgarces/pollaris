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

namespace App\Service;

use App\Repository;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Address;
use Symfony\Contracts\Translation\TranslatorInterface;

class PollsFinder
{
    public function __construct(
        private Repository\PollRepository $pollRepository,
        private MailerInterface $mailer,
        private TranslatorInterface $translator,
        #[Autowire('%app.name%')]
        private string $appName,
    ) {
    }

    public function sendEmailLinks(string $email): void
    {
        $polls = $this->pollRepository->findBy(['authorEmail' => $email]);

        if (!$polls) {
            return;
        }

        $to = new Address($email);
        $locale = $polls[0]->getLocale();

        $subject = "[{$this->appName}] ";
        $subject .= $this->translator->trans('emails.polls_list.subject', locale: $locale);

        $email = (new TemplatedEmail())
            ->to($to)
            ->subject($subject)
            ->textTemplate('emails/polls_list.txt.twig')
            ->locale($locale)
            ->context([
                'polls' => $polls,
            ]);

        $this->mailer->send($email);
    }
}
