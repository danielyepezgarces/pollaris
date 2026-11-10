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
     * Uses HTML parse mode. Supports optional inline reply markup.
     */
    public function sendMessage(string $chatId, string $htmlMessage, ?array $replyMarkup = null): bool
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

        $payload = [
            'chat_id' => $chatId,
            'text' => $htmlMessage,
            'parse_mode' => 'HTML',
            'disable_web_page_preview' => false,
        ];

        if ($replyMarkup !== null) {
            $payload['reply_markup'] = $replyMarkup;
        }

        try {
            $response = $this->httpClient->request('POST', $url, [
                'json' => $payload,
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

    /**
     * Sends an HTML message with an inline button URL (native Telegram button below the message).
     */
    public function sendMessageWithButton(string $chatId, string $htmlMessage, string $buttonText, string $buttonUrl): bool
    {
        $replyMarkup = [
            'inline_keyboard' => [
                [
                    [
                        'text' => $buttonText,
                        'url' => $buttonUrl,
                    ],
                ],
            ],
        ];

        return $this->sendMessage($chatId, $htmlMessage, $replyMarkup);
    }

    public function setWebhook(string $url): bool
    {
        if (!$this->isConfigured()) {
            $this->logger->warning('TelegramService: TELEGRAM_BOT_TOKEN is not configured.');
            return false;
        }

        $apiUrl = sprintf('https://api.telegram.org/bot%s/setWebhook', $this->botToken);

        try {
            $response = $this->httpClient->request('POST', $apiUrl, [
                'json' => ['url' => $url],
                'timeout' => 10,
            ]);

            return $response->getStatusCode() === 200;
        } catch (\Throwable $e) {
            $this->logger->error('TelegramService setWebhook exception: ' . $e->getMessage(), ['exception' => $e]);
            return false;
        }
    }
}
