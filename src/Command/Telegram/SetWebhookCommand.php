<?php

// This file is part of Pollaris.
// Copyright 2026 Daniel Yepez Garces
// SPDX-License-Identifier: AGPL-3.0-or-later

namespace App\Command\Telegram;

use App\Service\TelegramService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

#[AsCommand(
    name: 'app:telegram:set-webhook',
    description: 'Registers the Pollaris webhook URL with Telegram Bot API so commands like /recordatorio work interactively',
)]
class SetWebhookCommand extends Command
{
    public function __construct(
        private TelegramService $telegramService,
        private UrlGeneratorInterface $router,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addArgument('url', InputArgument::OPTIONAL, 'The public webhook URL (defaults to the route URL)');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        if (!$this->telegramService->isConfigured()) {
            $io->error('TELEGRAM_BOT_TOKEN is not configured in your environment.');
            return Command::FAILURE;
        }

        $url = $input->getArgument('url');
        if (!$url) {
            $url = $this->router->generate('api_telegram_webhook', [], UrlGeneratorInterface::ABSOLUTE_URL);
        }

        $io->info(sprintf('Registering Telegram webhook: %s', $url));

        $success = $this->telegramService->setWebhook($url);
        if ($success) {
            $io->success('Telegram webhook successfully registered!');
            return Command::SUCCESS;
        }

        $io->error('Failed to register Telegram webhook. Check your TELEGRAM_BOT_TOKEN and server logs.');
        return Command::FAILURE;
    }
}
