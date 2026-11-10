<?php

// This file is part of Pollaris.
// Copyright 2026 Daniel Yepez Garces
// SPDX-License-Identifier: AGPL-3.0-or-later

namespace App\Service;

use App\Entity;
use App\Repository;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Address;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

class CommunityNotificationService
{
    public function __construct(
        private MailerInterface $mailer,
        private TelegramService $telegramService,
        private TranslatorInterface $translator,
        private UrlGeneratorInterface $router,
        private EntityManagerInterface $entityManager,
        private Repository\CommunityMemberRepository $memberRepository,
        private Repository\PollNotificationOptOutRepository $optOutRepository,
        private LoggerInterface $logger,
        #[Autowire('%app.name%')]
        private string $appName,
        #[Autowire('%kernel.secret%')]
        private string $appSecret,
    ) {
    }

    public function generateOptOutToken(Entity\Poll $poll, Entity\User $user): string
    {
        return hash_hmac('sha256', 'poll:' . $poll->getId() . ':' . $user->getId(), $this->appSecret);
    }

    public function verifyOptOutToken(Entity\Poll $poll, Entity\User $user, string $token): bool
    {
        return hash_equals($this->generateOptOutToken($poll, $user), $token);
    }

    public function generateCommunityOptOutToken(Entity\Community $community, Entity\User $user): string
    {
        return hash_hmac('sha256', 'comm:' . $community->getId() . ':' . $user->getId(), $this->appSecret);
    }

    public function verifyCommunityOptOutToken(Entity\Community $community, Entity\User $user, string $token): bool
    {
        return hash_equals($this->generateCommunityOptOutToken($community, $user), $token);
    }

    public function notifyNewPoll(Entity\Poll $poll): void
    {
        $community = $poll->getCommunity();
        if (!$community || !$poll->isNotifyCommunityMembers()) {
            return;
        }

        $pollUrl = $this->router->generate('poll', [
            'slug' => $poll->getSlug() ?: $poll->getId(),
        ], UrlGeneratorInterface::ABSOLUTE_URL);

        // 1. Send Telegram Notification
        if ($community->isTelegramEnabled() && $community->getTelegramChatId()) {
            $telegramMsg = sprintf(
                "📢 <b>[%s] %s</b>\n\n<b>%s</b>\n%s",
                htmlspecialchars($this->appName),
                htmlspecialchars($community->getName()),
                htmlspecialchars($poll->getTitle()),
                htmlspecialchars(mb_substr(strip_tags($poll->getDescription()), 0, 200) . '...')
            );
            $buttonText = $this->getVoteButtonText();
            $this->telegramService->sendMessageWithButton($community->getTelegramChatId(), $telegramMsg, $buttonText, $pollUrl);
        }

        // 2. Send Emails to Active Members
        if (!$community->isEmailEnabled()) {
            return;
        }

        $members = $this->memberRepository->findActiveMembers($community);
        foreach ($members as $member) {
            $user = $member->getUser();
            if (!$user || !$member->isReceiveEmails()) {
                continue;
            }

            $userEmail = $user->getEmail();
            if (empty($userEmail)) {
                continue;
            }

            // Check if opted out of this poll
            if ($this->optOutRepository->isOptedOut($poll, $user, $userEmail)) {
                continue;
            }

            $pollOptOutUrl = $this->router->generate('community_opt_out_poll', [
                'pollSlug' => $poll->getSlug() ?: $poll->getId(),
                'userId' => $user->getId(),
                'token' => $this->generateOptOutToken($poll, $user),
            ], UrlGeneratorInterface::ABSOLUTE_URL);

            $communityOptOutUrl = $this->router->generate('community_opt_out_community', [
                'communitySlug' => $community->getSlug(),
                'userId' => $user->getId(),
                'token' => $this->generateCommunityOptOutToken($community, $user),
            ], UrlGeneratorInterface::ABSOLUTE_URL);

            $userLocale = $user->getLocale() ?: 'en';
            $pollTitle = $poll->getTitleForLocale($userLocale);
            $communityName = $community->getNameForLocale($userLocale);
            $subject = sprintf('[%s] %s: %s', $this->appName, $communityName, $pollTitle);

            try {
                $email = (new TemplatedEmail())
                    ->to(new Address($userEmail, $user->getUsername()))
                    ->subject($subject)
                    ->textTemplate('emails/community_new_poll.txt.twig')
                    ->context([
                        'user' => $user,
                        'community' => $community,
                        'communityName' => $communityName,
                        'poll' => $poll,
                        'pollTitle' => $pollTitle,
                        'pollUrl' => $pollUrl,
                        'pollOptOutUrl' => $pollOptOutUrl,
                        'communityOptOutUrl' => $communityOptOutUrl,
                        'locale' => $userLocale,
                    ]);

                $this->mailer->send($email);
            } catch (\Throwable $e) {
                $this->logger->error('Error sending new community poll email: ' . $e->getMessage(), ['exception' => $e]);
            }
        }
    }

    /**
     * Send daily reminders for polls expiring in the next 6 days.
     * Only sends at most one reminder per day per poll.
     * Only reminds members who have not voted yet.
     */
    public function sendDueReminders(): int
    {
        $now = new \DateTimeImmutable();
        $startOfToday = $now->setTime(0, 0, 0);

        $polls = $this->entityManager->createQuery(
            'SELECT p FROM App\Entity\Poll p
             JOIN p.community c
             WHERE p.closedAt IS NOT NULL
             AND p.notifyCommunityMembers = true
             AND (p.remindedAt IS NULL OR p.remindedAt < :startOfToday)'
        )
        ->setParameter('startOfToday', $startOfToday)
        ->getResult();

        $count = 0;
        foreach ($polls as $poll) {
            $closingTs = $poll->getClosingTimestamp();
            if (!$closingTs) {
                continue;
            }

            $diffSeconds = $closingTs - $now->getTimestamp();
            // Send daily reminder if poll is active and closing in <= 6 days
            if ($diffSeconds > 0 && $diffSeconds <= (6 * 86400)) {
                $this->sendPollReminder($poll);
                $poll->setRemindedAt(new \DateTimeImmutable());
                $this->entityManager->flush();
                $count++;
            }
        }

        return $count;
    }

    public function buildTelegramReminderMessage(Entity\Poll $poll, string $locale = 'es'): string
    {
        $community = $poll->getCommunity();
        $communityName = $community ? $community->getNameForLocale($locale) : '';
        $pollTitle = $poll->getTitleForLocale($locale);
        $pollUrl = $this->router->generate('poll', [
            'slug' => $poll->getSlug() ?: $poll->getId(),
        ], UrlGeneratorInterface::ABSOLUTE_URL);

        $closingTs = $poll->getClosingTimestamp();
        $closingInfo = '';
        $timeRemainingStr = '';

        if ($closingTs) {
            $now = new \DateTimeImmutable();
            $diffSeconds = $closingTs - $now->getTimestamp();

            // Date in UTC (general)
            $closingUtc = (new \DateTimeImmutable('@' . $closingTs))->setTimezone(new \DateTimeZone('UTC'));
            $utcFormatted = $closingUtc->format('Y-m-d H:i') . ' UTC';

            // Poll specific timezone (if configured and different from UTC)
            $pollTz = $poll->getTimezoneName() ?: 'UTC';
            if ($pollTz !== 'UTC') {
                $closingLocal = (new \DateTimeImmutable('@' . $closingTs))->setTimezone(new \DateTimeZone($pollTz));
                $localFormatted = $closingLocal->format('Y-m-d H:i') . ' ' . $pollTz;
                $closingInfo = sprintf('%s (%s)', $utcFormatted, $localFormatted);
            } else {
                $closingInfo = $utcFormatted;
            }

            if ($diffSeconds < 86400) {
                $hoursLeft = max(1, (int) round($diffSeconds / 3600));
                $timeRemainingStr = match (substr($locale, 0, 2)) {
                    'pt' => sprintf('⏳ <b>Encerramento:</b> em menos de %d horas!', $hoursLeft),
                    'fr' => sprintf('⏳ <b>Clôture :</b> dans moins de %d heures !', $hoursLeft),
                    'en' => sprintf('⏳ <b>Closing:</b> in less than %d hours!', $hoursLeft),
                    default => sprintf('⏳ <b>Cierre:</b> ¡en menos de %d horas!', $hoursLeft),
                };
            } else {
                $daysLeft = (int) floor($diffSeconds / 86400);
                $timeRemainingStr = match (substr($locale, 0, 2)) {
                    'pt' => sprintf('⏳ <b>Encerramento:</b> faltam %d dias', $daysLeft),
                    'fr' => sprintf('⏳ <b>Clôture :</b> dans %d jours', $daysLeft),
                    'en' => sprintf('⏳ <b>Closing:</b> in %d days', $daysLeft),
                    default => sprintf('⏳ <b>Cierre:</b> faltan %d días', $daysLeft),
                };
            }
        }

        $voteCount = count($poll->getVotes());
        $votesStr = match (substr($locale, 0, 2)) {
            'pt' => sprintf('👥 <b>Participação atual:</b> %d %s', $voteCount, $voteCount === 1 ? 'voto registrado' : 'votos registrados'),
            'fr' => sprintf('👥 <b>Participation actuelle :</b> %d %s', $voteCount, $voteCount === 1 ? 'vote enregistré' : 'votes enregistrés'),
            'en' => sprintf('👥 <b>Current turnout:</b> %d %s', $voteCount, $voteCount === 1 ? 'vote recorded' : 'votes recorded'),
            default => sprintf('👥 <b>Participación actual:</b> %d %s', $voteCount, $voteCount === 1 ? 'voto registrado' : 'votos registrados'),
        };

        $headerTitle = match (substr($locale, 0, 2)) {
            'pt' => 'Lembrete de Votação',
            'fr' => 'Rappel de vote',
            'en' => 'Voting Reminder',
            default => 'Recordatorio de Votación',
        };

        $dateLabel = match (substr($locale, 0, 2)) {
            'pt' => 'Prazo',
            'fr' => 'Date limite',
            'en' => 'Deadline',
            default => 'Fecha límite',
        };

        return sprintf(
            "⏰ <b>[%s] %s</b>\n<b>%s</b>\n\n🗳️ <b>%s</b>\n%s\n%s%s",
            htmlspecialchars($this->appName),
            htmlspecialchars($communityName),
            $headerTitle,
            htmlspecialchars($pollTitle),
            $timeRemainingStr,
            $closingInfo ? sprintf("📅 <b>%s:</b> %s\n", $dateLabel, htmlspecialchars($closingInfo)) : '',
            $votesStr
        );
    }

    public function getVoteButtonText(string $locale = 'es'): string
    {
        return match (substr($locale, 0, 2)) {
            'pt' => '🗳️ Emitir meu voto',
            'fr' => '🗳️ Voter maintenant',
            'en' => '🗳️ Cast my vote',
            default => '🗳️ Emitir mi voto',
        };
    }

    public function sendPollReminder(Entity\Poll $poll, string $locale = 'es'): void
    {
        $community = $poll->getCommunity();
        if (!$community || !$poll->isNotifyCommunityMembers()) {
            return;
        }

        $pollUrl = $this->router->generate('poll', [
            'slug' => $poll->getSlug() ?: $poll->getId(),
        ], UrlGeneratorInterface::ABSOLUTE_URL);

        // 1. Telegram Reminder
        if ($community->isTelegramEnabled() && $community->getTelegramChatId()) {
            $telegramMsg = $this->buildTelegramReminderMessage($poll, $locale);
            $buttonText = $this->getVoteButtonText($locale);
            $this->telegramService->sendMessageWithButton($community->getTelegramChatId(), $telegramMsg, $buttonText, $pollUrl);
        }

        // 2. Identify who has already voted
        $votedUsernames = [];
        foreach ($poll->getVotes() as $vote) {
            $votedUsernames[] = mb_strtolower($vote->getAuthorName());
        }

        // 3. Email only members who have NOT voted yet
        if (!$community->isEmailEnabled()) {
            return;
        }

        $members = $this->memberRepository->findActiveMembers($community);
        foreach ($members as $member) {
            $user = $member->getUser();
            if (!$user || !$member->isReceiveEmails()) {
                continue;
            }

            if (in_array(mb_strtolower($user->getUsername()), $votedUsernames, true)) {
                // Member has already voted! Skip reminder.
                continue;
            }

            $userEmail = $user->getEmail();
            if (empty($userEmail)) {
                continue;
            }

            // Check if opted out of this poll
            if ($this->optOutRepository->isOptedOut($poll, $user, $userEmail)) {
                continue;
            }

            $pollOptOutUrl = $this->router->generate('community_opt_out_poll', [
                'pollSlug' => $poll->getSlug() ?: $poll->getId(),
                'userId' => $user->getId(),
                'token' => $this->generateOptOutToken($poll, $user),
            ], UrlGeneratorInterface::ABSOLUTE_URL);

            $communityOptOutUrl = $this->router->generate('community_opt_out_community', [
                'communitySlug' => $community->getSlug(),
                'userId' => $user->getId(),
                'token' => $this->generateCommunityOptOutToken($community, $user),
            ], UrlGeneratorInterface::ABSOLUTE_URL);

            $userLocale = $user->getLocale() ?: 'en';
            $pollTitle = $poll->getTitleForLocale($userLocale);
            $communityName = $community->getNameForLocale($userLocale);
            $reminderTitle = $this->translator->trans('community.notifications.reminder_title', [], null, $userLocale);

            $subject = sprintf(
                '[%s] %s: %s (%s)',
                $this->appName,
                $reminderTitle,
                $pollTitle,
                $communityName
            );

            try {
                $email = (new TemplatedEmail())
                    ->to(new Address($userEmail, $user->getUsername()))
                    ->subject($subject)
                    ->textTemplate('emails/community_reminder.txt.twig')
                    ->context([
                        'user' => $user,
                        'community' => $community,
                        'communityName' => $communityName,
                        'poll' => $poll,
                        'pollTitle' => $pollTitle,
                        'pollUrl' => $pollUrl,
                        'pollOptOutUrl' => $pollOptOutUrl,
                        'communityOptOutUrl' => $communityOptOutUrl,
                        'locale' => $userLocale,
                    ]);

                $this->mailer->send($email);
            } catch (\Throwable $e) {
                $this->logger->error('Error sending community reminder email: ' . $e->getMessage(), ['exception' => $e]);
            }
        }
    }
}
