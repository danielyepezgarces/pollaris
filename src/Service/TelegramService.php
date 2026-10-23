<?php

// This file is part of Pollaris.
// Copyright 2026 Daniel Yepez Garces
// SPDX-License-Identifier: AGPL-3.0-or-later

namespace App\Service;

use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class TelegramService
{
    public function __construct(
        private HttpClientInterface $httpClient,
        private LoggerInterface $logger,
        #[Autowire('%env(default::TELEGRAM_BOT_TOKEN)%')]
        private ?string $botToken = null,
    ) {
    }

    public function isConfigured(): bool
    {
        return !empty($this->botToken);
    }

    /**
     * Sends a message to a Telegram chat or group.
     * Uses HTML parse mode.
     */
    public function sendMessage(string $chatId, string $htmlMessage): bool
    {
        if (!$this->isConfigured()) {
            $this->logger->warning('TelegramService: TELEGRAM_BOT_TOKEN is not configured.');
            return false;
        }

        if (empty($chatId)) {
            $this->logger->warning('TelegramService: Cannot send message, chatId is empty.');
            return false;
        }

        $url = sprintf('https://api.telegram.org/bot%s/sendMessage', $this->botToken);

        try {
            $response = $this->httpClient->request('POST', $url, [
                'json' => [
                    'chat_id' => $chatId,
                    'text' => $htmlMessage,
                    'parse_mode' => 'HTML',
                    'disable_web_page_preview' => false,
                ],
                'timeout' => 10,
            ]);

            $statusCode = $response->getStatusCode();
            if ($statusCode >= 200 && $statusCode < 300) {
                return true;
            }

            $this->logger->error(sprintf('TelegramService error: HTTP %d - %s', $statusCode, $response->getContent(false)));
            return false;
        } catch (\Throwable $e) {
            $this->logger->error('TelegramService exception: ' . $e->getMessage(), ['exception' => $e]);
            return false;
        }
    }
}
