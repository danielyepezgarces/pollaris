<?php

// This file is part of Pollaris.
// Copyright 2026 Daniel Yepez Garces
// SPDX-License-Identifier: AGPL-3.0-or-later

namespace App\Controller;

use App\Service\TelegramUpdateHandler;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class TelegramWebhookController extends AbstractController
{
    #[Route('/api/telegram/webhook', name: 'api_telegram_webhook', methods: ['POST'])]
    public function handle(
        Request $request,
        TelegramUpdateHandler $updateHandler,
        LoggerInterface $logger,
    ): Response {
        $content = $request->getContent();
        if (empty($content)) {
            return new JsonResponse(['ok' => true]);
        }

        try {
            $update = json_decode($content, true, 512, JSON_THROW_ON_ERROR);
        } catch (\Throwable $e) {
            $logger->warning('TelegramWebhook: Invalid JSON payload: ' . $e->getMessage());
            return new JsonResponse(['ok' => false, 'error' => 'Invalid JSON'], 400);
        }

        $updateHandler->handleUpdate($update);

        return new JsonResponse(['ok' => true]);
    }
}
