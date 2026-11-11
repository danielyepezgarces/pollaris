<?php

// This file is part of Pollaris.
// Copyright 2026 Daniel Yepez Garces
// SPDX-License-Identifier: AGPL-3.0-or-later

namespace App\Command\Test;

use App\Repository\PollRepository;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Address;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

#[AsCommand(
    name: 'app:test:email',
    description: 'Send a test email with the Codex HTML layout and plain text fallback',
)]
class SendTestEmailCommand extends Command
{
    public function __construct(
        private MailerInterface $mailer,
        private PollRepository $pollRepository,
        private UrlGeneratorInterface $router,
        #[Autowire('%env(default::APP_NAME)%')]
        private ?string $appName = 'Pollaris',
        #[Autowire('%env(default::MAILER_FROM)%')]
        private ?string $mailerFrom = 'pollaris@dyepezg.dev',
    ) {
        $this->appName = $this->appName ?: 'Pollaris';
        $this->mailerFrom = $this->mailerFrom ?: 'pollaris@dyepezg.dev';
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addArgument('to', InputArgument::REQUIRED, 'Recipient email address');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $to = $input->getArgument('to');

        // Look for the latest real poll in the database
        $latestPoll = $this->pollRepository->findOneBy([], ['id' => 'DESC']);

        if ($latestPoll) {
            $poll = $latestPoll;
            $pollSlug = $poll->getSlug() ?: (string) $poll->getId();
            try {
                $pollUrl = $this->router->generate('poll', ['slug' => $pollSlug], UrlGeneratorInterface::ABSOLUTE_URL);
            } catch (\Throwable) {
                $pollUrl = sprintf('https://pollaris.toolforge.org/polls/%s', $pollSlug);
            }
            $pollTitle = $poll->getTitle();
            $community = $poll->getCommunity();
            $communityName = $community ? $community->getName() : 'Wikimedia España';

            try {
                $pollOptOutUrl = $this->router->generate('community_opt_out_poll', [
                    'pollSlug' => $pollSlug,
                    'userId' => 1,
                    'token' => 'sample-token',
                ], UrlGeneratorInterface::ABSOLUTE_URL);
            } catch (\Throwable) {
                $pollOptOutUrl = sprintf('https://pollaris.toolforge.org/notifications/opt-out/poll/%s/1/token', $pollSlug);
            }

            try {
                $communitySlug = $community ? $community->getSlug() : 'wikimedia-es';
                $communityOptOutUrl = $this->router->generate('community_opt_out_community', [
                    'communitySlug' => $communitySlug,
                    'userId' => 1,
                    'token' => 'sample-token',
                ], UrlGeneratorInterface::ABSOLUTE_URL);
            } catch (\Throwable) {
                $communityOptOutUrl = 'https://pollaris.toolforge.org/notifications/opt-out/community/wikimedia-es/1/token';
            }
        } else {
            $poll = (object) [
                'title' => 'Planificación Wikimedia Hackathon 2026',
                'description' => 'Consulta comunitaria para definir los temas prioritarios, fechas y sedes propuestas para la próxima edición del hackathon.',
                'closedAt' => new \DateTimeImmutable('+14 days'),
                'timezoneName' => 'Europe/Madrid',
            ];
            $pollTitle = $poll->title;
            $communityName = 'Wikimedia España';
            $pollUrl = 'https://pollaris.toolforge.org/polls/planificacion-hackathon-2026';
            $pollOptOutUrl = 'https://pollaris.toolforge.org/notifications/opt-out/poll/planificacion-hackathon-2026/1/token';
            $communityOptOutUrl = 'https://pollaris.toolforge.org/notifications/opt-out/community/wikimedia-es/1/token';
        }

        $email = (new TemplatedEmail())
            ->from(new Address($this->mailerFrom ?: 'pollaris@dyepezg.dev', $this->appName))
            ->to(new Address($to))
            ->subject(sprintf('[%s] %s: %s', $this->appName, $communityName, $pollTitle))
            ->textTemplate('emails/community_new_poll.txt.twig')
            ->htmlTemplate('emails/community_new_poll.html.twig')
            ->context([
                'app_name' => $this->appName,
                'user' => (object) ['username' => 'Daniel'],
                'communityName' => $communityName,
                'pollTitle' => $pollTitle,
                'poll' => $poll,
                'pollUrl' => $pollUrl,
                'pollOptOutUrl' => $pollOptOutUrl,
                'communityOptOutUrl' => $communityOptOutUrl,
                'locale' => 'es',
            ]);

        $this->mailer->send($email);
        $io->success(sprintf('Test email queued/sent to %s for poll "%s" (%s)!', $to, $pollTitle, $pollUrl));

        return Command::SUCCESS;
    }
}
