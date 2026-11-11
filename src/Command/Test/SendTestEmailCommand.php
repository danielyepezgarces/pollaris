<?php

// This file is part of Pollaris.
// Copyright 2026 Daniel Yepez Garces
// SPDX-License-Identifier: AGPL-3.0-or-later

namespace App\Command\Test;

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

#[AsCommand(
    name: 'app:test:email',
    description: 'Send a test email with the Codex HTML layout and plain text fallback',
)]
class SendTestEmailCommand extends Command
{
    public function __construct(
        private MailerInterface $mailer,
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

        $email = (new TemplatedEmail())
            ->from(new Address($this->mailerFrom ?: 'pollaris@dyepezg.dev', $this->appName))
            ->to(new Address($to))
            ->subject(sprintf('[%s] Consulta de prueba: Planificación Wikimedia Hackathon 2026', $this->appName))
            ->textTemplate('emails/community_new_poll.txt.twig')
            ->htmlTemplate('emails/community_new_poll.html.twig')
            ->context([
                'app_name' => $this->appName,
                'user' => (object) ['username' => 'Daniel'],
                'communityName' => 'Wikimedia España',
                'pollTitle' => 'Planificación Wikimedia Hackathon 2026',
                'poll' => (object) [
                    'title' => 'Planificación Wikimedia Hackathon 2026',
                    'description' => 'Consulta comunitaria para definir los temas prioritarios, fechas y sedes propuestas para la próxima edición del hackathon.',
                ],
                'pollUrl' => 'https://pollaris.toolforge.org',
                'pollOptOutUrl' => 'https://pollaris.toolforge.org/notifications/opt-out/poll/demo/1/token',
                'communityOptOutUrl' => 'https://pollaris.toolforge.org/notifications/opt-out/community/wikimedia-es/1/token',
                'locale' => 'es',
            ]);

        $this->mailer->send($email);
        $io->success(sprintf('Test email successfully sent to %s!', $to));

        return Command::SUCCESS;
    }
}
