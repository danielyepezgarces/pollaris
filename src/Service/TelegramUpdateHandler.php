<?php

// This file is part of Pollaris.
// Copyright 2026 Daniel Yepez Garces
// SPDX-License-Identifier: AGPL-3.0-or-later

namespace App\Service;

use App\Repository;
use Psr\Log\LoggerInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

class TelegramUpdateHandler
{
    public function __construct(
        private TelegramService $telegramService,
        private CommunityNotificationService $notificationService,
        private Repository\CommunityRepository $communityRepository,
        private Repository\PollRepository $pollRepository,
        private UrlGeneratorInterface $router,
        private LoggerInterface $logger,
    ) {
    }

    /**
     * Processes a single Telegram Update array.
     *
     * @param array<string, mixed> $update
     */
    public function handleUpdate(array $update): void
    {
        $message = $update['message'] ?? null;
        if (!$message || !isset($message['text'], $message['chat']['id'])) {
            return;
        }

        $chatId = (string) $message['chat']['id'];
        $rawText = trim($message['text']);

        // Check if message is a command starting with /
        if (!str_starts_with($rawText, '/')) {
            return;
        }

        // Extract command name (remove / and @BotUsername if present)
        $commandWord = explode(' ', $rawText)[0];
        $commandName = strtolower(explode('@', substr($commandWord, 1))[0]);

        $locale = match ($commandName) {
            'recordatorio', 'recordatorios', 'encuesta', 'encuestas', 'ayuda' => 'es',
            'reminder', 'reminders', 'poll', 'polls', 'help' => 'en',
            'lembrete', 'lembretes', 'enquete', 'enquetes', 'ajuda' => 'pt',
            'rappel', 'rappels', 'sondage', 'sondages' => 'fr',
            default => null,
        };

        if ($locale === null) {
            // Unrecognized command; ignore silently
            return;
        }

        // Handle help commands
        if (in_array($commandName, ['help', 'ayuda', 'ajuda'], true)) {
            $helpMsg = match ($locale) {
                'pt' => "🤖 <b>Comandos do bot Pollaris:</b>\n\n• /lembrete ou /enquetes — Consulta as enquetes abertas da comunidade e seus prazos\n• /ajuda — Mostra esta mensagem de ajuda",
                'fr' => "🤖 <b>Commandes du bot Pollaris :</b>\n\n• /rappel ou /sondages — Consultez les sondages ouverts de la communauté et leurs délais\n• /help — Affiche ce message d'aide",
                'en' => "🤖 <b>Pollaris Bot Commands:</b>\n\n• /reminder or /polls — Check active community polls, deadlines, and vote links\n• /help — Show this help message",
                default => "🤖 <b>Comandos del bot de Pollaris:</b>\n\n• /recordatorio o /encuestas — Consulta las encuestas abiertas de la comunidad y sus plazos\n• /ayuda — Muestra este mensaje de ayuda",
            };
            $this->telegramService->sendMessage($chatId, $helpMsg);
            return;
        }

        // Find community by telegramChatId
        $community = $this->communityRepository->findOneBy(['telegramChatId' => $chatId]);
        if (!$community) {
            $notLinkedMsg = match ($locale) {
                'pt' => "⚠️ Este grupo do Telegram ainda não está vinculado a nenhuma comunidade do Pollaris. Você pode configurar o ID do grupo nos ajustes da comunidade no Pollaris.",
                'fr' => "⚠️ Ce groupe Telegram n'est pas encore lié à une communauté Pollaris. Vous pouvez configurer l'identifiant du groupe dans les paramètres de la communauté.",
                'en' => "⚠️ This Telegram group is not linked to any Pollaris community yet. You can configure this Group ID in your community settings in Pollaris.",
                default => "⚠️ Este grupo de Telegram aún no está vinculado a ninguna comunidad de Pollaris. Puedes configurar el ID del grupo en los ajustes de tu comunidad en Pollaris.",
            };
            $this->telegramService->sendMessage($chatId, $notLinkedMsg);
            return;
        }

        // Find active polls for this community
        $now = new \DateTimeImmutable();
        $polls = $this->pollRepository->createQueryBuilder('p')
            ->where('p.community = :community')
            ->andWhere('p.closedAt IS NULL OR p.closedAt >= :today')
            ->setParameter('community', $community)
            ->setParameter('today', $now->format('Y-m-d'))
            ->orderBy('p.createdAt', 'DESC')
            ->getQuery()
            ->getResult();

        $activePolls = [];
        foreach ($polls as $poll) {
            $closingTs = $poll->getClosingTimestamp();
            if ($closingTs === null || $closingTs > $now->getTimestamp()) {
                $activePolls[] = $poll;
            }
        }

        if (empty($activePolls)) {
            $communityName = $community->getNameForLocale($locale);
            $noneMsg = match ($locale) {
                'pt' => sprintf("ℹ️ Não há enquetes abertas no momento para a comunidade <b>%s</b>.", htmlspecialchars($communityName)),
                'fr' => sprintf("ℹ️ Il n'y a aucun sondage ouvert pour le moment dans la communauté <b>%s</b>.", htmlspecialchars($communityName)),
                'en' => sprintf("ℹ️ There are no open polls at the moment for the <b>%s</b> community.", htmlspecialchars($communityName)),
                default => sprintf("ℹ️ No hay encuestas abiertas en este momento para la comunidad <b>%s</b>.", htmlspecialchars($communityName)),
            };
            $this->telegramService->sendMessage($chatId, $noneMsg);
            return;
        }

        // Send a reminder message for each active poll
        foreach ($activePolls as $poll) {
            $reminderHtml = $this->notificationService->buildTelegramReminderMessage($poll, $locale);
            $buttonText = $this->notificationService->getVoteButtonText($locale);
            try {
                $pollUrl = $this->router->generate('poll', ['slug' => $poll->getSlug() ?: $poll->getId()], UrlGeneratorInterface::ABSOLUTE_URL);
            } catch (\Throwable) {
                $pollUrl = sprintf('https://pollaris.toolforge.org/polls/%s', $poll->getSlug() ?: $poll->getId());
            }
            $this->telegramService->sendMessageWithButton($chatId, $reminderHtml, $buttonText, $pollUrl);
        }
    }
}
