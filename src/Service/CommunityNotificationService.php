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
                    ->htmlTemplate('emails/community_new_poll.html.twig')
                    ->context([
                        'app_name' => $this->appName,
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
     * Send milestone-based reminders (6 days, 72 hours, 12 hours, 6 hours)
     * and leader-change notifications for active community polls.
     * Between milestones, no notifications are sent unless the leading option changes.
     */
    public function sendDueReminders(): int
    {
        $now = new \DateTimeImmutable();

        $polls = $this->entityManager->createQuery(
            'SELECT p FROM App\Entity\Poll p
             JOIN p.community c
             WHERE p.closedAt IS NOT NULL
             AND p.closedAt > :now
             AND p.notifyCommunityMembers = true'
        )
        ->setParameter('now', $now)
        ->getResult();

        $count = 0;
        foreach ($polls as $poll) {
            $closingTs = $poll->getClosingTimestamp();
            if (!$closingTs) {
                continue;
            }

            $diffSeconds = $closingTs - $now->getTimestamp();
            if ($diffSeconds <= 0) {
                continue;
            }

            // Determine if a milestone is reached and not yet sent
            $dueMilestone = null;
            if ($diffSeconds <= (6 * 3600) && !$poll->hasRemindedMilestone('6h')) {
                $dueMilestone = '6h';
                $poll->addRemindedMilestone('6h');
                $poll->addRemindedMilestone('12h');
                $poll->addRemindedMilestone('72h');
                $poll->addRemindedMilestone('6d');
            } elseif ($diffSeconds <= (12 * 3600) && !$poll->hasRemindedMilestone('12h')) {
                $dueMilestone = '12h';
                $poll->addRemindedMilestone('12h');
                $poll->addRemindedMilestone('72h');
                $poll->addRemindedMilestone('6d');
            } elseif ($diffSeconds <= (72 * 3600) && !$poll->hasRemindedMilestone('72h')) {
                $dueMilestone = '72h';
                $poll->addRemindedMilestone('72h');
                $poll->addRemindedMilestone('6d');
            } elseif ($diffSeconds <= (6 * 86400) && !$poll->hasRemindedMilestone('6d')) {
                $dueMilestone = '6d';
                $poll->addRemindedMilestone('6d');
            }

            if ($dueMilestone !== null) {
                $this->sendPollReminder($poll, locale: 'es', reason: 'milestone', milestone: $dueMilestone);
                $poll->setRemindedAt(new \DateTimeImmutable());
                $this->entityManager->flush();
                $count++;
            } else {
                // If no milestone triggered, check if the leading option has changed
                if ($this->checkLeaderChange($poll)) {
                    $count++;
                }
            }
        }

        return $count;
    }

    /**
     * Check if the poll's leading proposal has changed.
     * If so (and anti-spam cooldown of 1h has passed), send a notification.
     */
    public function checkLeaderChange(Entity\Poll $poll): bool
    {
        $community = $poll->getCommunity();
        if (!$community || !$poll->isNotifyCommunityMembers() || $poll->isClosed()) {
            return false;
        }

        $preferredChoices = $poll->getPreferredChoices();
        if (count($preferredChoices) !== 1) {
            // Either tie or no positive votes
            return false;
        }

        $currentLeader = $preferredChoices[0];
        $currentLeaderId = $currentLeader->getId();
        if ($currentLeaderId === null) {
            return false;
        }

        $previousLeaderId = $poll->getLastLeadingProposalId();

        // If no leader recorded yet, initialize it without sending notification
        if ($previousLeaderId === null) {
            $poll->setLastLeadingProposalId($currentLeaderId);
            $this->entityManager->flush();
            return false;
        }

        // Leader has not changed
        if ($currentLeaderId === $previousLeaderId) {
            return false;
        }

        // LEADER CHANGED! Check anti-spam cooldown (at least 1 hour between leader change notifications)
        $now = new \DateTimeImmutable();
        $lastNotified = $poll->getLastLeaderNotifiedAt();
        if ($lastNotified !== null && ($now->getTimestamp() - $lastNotified->getTimestamp()) < 3600) {
            $poll->setLastLeadingProposalId($currentLeaderId);
            $this->entityManager->flush();
            return false;
        }

        // Send leader change notification!
        $this->sendPollReminder($poll, locale: 'es', reason: 'leader_change');
        $poll->setLastLeadingProposalId($currentLeaderId);
        $poll->setLastLeaderNotifiedAt($now);
        $poll->setRemindedAt($now);
        $this->entityManager->flush();

        return true;
    }

    public function buildTelegramReminderMessage(
        Entity\Poll $poll,
        string $locale = 'es',
        string $reason = 'milestone',
        ?string $milestone = null
    ): string {
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
                $days = (int) floor($diffSeconds / 86400);
                $remainingHours = (int) round(($diffSeconds % 86400) / 3600);
                if ($remainingHours === 24) {
                    $days++;
                    $remainingHours = 0;
                }

                if ($remainingHours > 0) {
                    $timeRemainingStr = match (substr($locale, 0, 2)) {
                        'pt' => sprintf('⏳ <b>Encerramento:</b> faltam %d %s e %d %s', $days, $days === 1 ? 'dia' : 'dias', $remainingHours, $remainingHours === 1 ? 'hora' : 'horas'),
                        'fr' => sprintf('⏳ <b>Clôture :</b> dans %d %s et %d %s', $days, $days === 1 ? 'jour' : 'jours', $remainingHours, $remainingHours === 1 ? 'heure' : 'heures'),
                        'en' => sprintf('⏳ <b>Closing:</b> in %d %s and %d %s', $days, $days === 1 ? 'day' : 'days', $remainingHours, $remainingHours === 1 ? 'hour' : 'hours'),
                        default => sprintf('⏳ <b>Cierre:</b> faltan %d %s y %d %s', $days, $days === 1 ? 'día' : 'días', $remainingHours, $remainingHours === 1 ? 'hora' : 'horas'),
                    };
                } else {
                    $timeRemainingStr = match (substr($locale, 0, 2)) {
                        'pt' => sprintf('⏳ <b>Encerramento:</b> faltam %d %s', $days, $days === 1 ? 'dia' : 'dias'),
                        'fr' => sprintf('⏳ <b>Clôture :</b> dans %d %s', $days, $days === 1 ? 'jour' : 'jours'),
                        'en' => sprintf('⏳ <b>Closing:</b> in %d %s', $days, $days === 1 ? 'day' : 'days'),
                        default => sprintf('⏳ <b>Cierre:</b> faltan %d %s', $days, $days === 1 ? 'día' : 'días'),
                    };
                }
            }
        }

        $voteCount = count($poll->getVotes());
        $votesStr = match (substr($locale, 0, 2)) {
            'pt' => sprintf('👥 <b>Participação atual:</b> %d %s', $voteCount, $voteCount === 1 ? 'voto registrado' : 'votos registrados'),
            'fr' => sprintf('👥 <b>Participation actuelle :</b> %d %s', $voteCount, $voteCount === 1 ? 'vote enregistré' : 'votes enregistrés'),
            'en' => sprintf('👥 <b>Current turnout:</b> %d %s', $voteCount, $voteCount === 1 ? 'vote recorded' : 'votes recorded'),
            default => sprintf('👥 <b>Participación actual:</b> %d %s', $voteCount, $voteCount === 1 ? 'voto registrado' : 'votos registrados'),
        };

        $leadingStr = '';
        $preferredChoices = $poll->getPreferredChoices();
        if (count($preferredChoices) === 1) {
            $winningProposal = $preferredChoices[0];
            $yesCount = $winningProposal->countAnswers('yes');
            if ($yesCount > 0) {
                $winningLabel = $this->formatProposalLabelForTelegram($winningProposal, $poll);
                $leadingPrefix = ($reason === 'leader_change')
                    ? match (substr($locale, 0, 2)) {
                        'pt' => '🏆 <b>Nova opção na liderança:</b>',
                        'fr' => '🏆 <b>Nouvelle option en tête :</b>',
                        'en' => '🏆 <b>New leading option:</b>',
                        default => '🏆 <b>Nueva opción en cabeza:</b>',
                    }
                    : match (substr($locale, 0, 2)) {
                        'pt' => '🏆 <b>Opção na liderança:</b>',
                        'fr' => '🏆 <b>Option en tête :</b>',
                        'en' => '🏆 <b>Leading option:</b>',
                        default => '🏆 <b>Opción en cabeza:</b>',
                    };

                $votesLabel = match (substr($locale, 0, 2)) {
                    'pt' => $yesCount === 1 ? 'voto a favor' : 'votos a favor',
                    'fr' => $yesCount === 1 ? 'vote pour' : 'votes pour',
                    'en' => $yesCount === 1 ? 'vote in favor' : 'votes in favor',
                    default => $yesCount === 1 ? 'voto a favor' : 'votos a favor',
                };

                $leadingStr = sprintf("\n%s %s (%d %s)", $leadingPrefix, htmlspecialchars($winningLabel), $yesCount, $votesLabel);
            }
        }

        if ($reason === 'leader_change') {
            $headerTitle = match (substr($locale, 0, 2)) {
                'pt' => 'Mudança na Opção Líder!',
                'fr' => 'Changement de l\'option en tête !',
                'en' => 'Leading Option Changed!',
                default => '¡Cambio en la opción en cabeza!',
            };
            $headerIcon = '🚨';
        } elseif ($milestone === '6h') {
            $headerTitle = match (substr($locale, 0, 2)) {
                'pt' => 'Últimas 6 horas para votar!',
                'fr' => 'Dernières 6 heures pour voter !',
                'en' => 'Final 6 hours to vote!',
                default => '¡Últimas 6 horas para votar!',
            };
            $headerIcon = '🔥';
        } elseif ($milestone === '12h') {
            $headerTitle = match (substr($locale, 0, 2)) {
                'pt' => 'Últimas 12 horas para votar!',
                'fr' => 'Dernières 12 heures pour voter !',
                'en' => 'Last 12 hours to vote!',
                default => '¡Últimas 12 horas para votar!',
            };
            $headerIcon = '🚨';
        } elseif ($milestone === '72h') {
            $headerTitle = match (substr($locale, 0, 2)) {
                'pt' => 'Lembrete: Faltam 72 horas (3 dias)',
                'fr' => 'Rappel : 72 heures restantes (3 jours)',
                'en' => 'Reminder: 72 hours left (3 days)',
                default => 'Recordatorio: Faltan 72 horas (3 días)',
            };
            $headerIcon = '⏰';
        } elseif ($milestone === '6d') {
            $headerTitle = match (substr($locale, 0, 2)) {
                'pt' => 'Lembrete: Faltam 6 dias',
                'fr' => 'Rappel : 6 jours restants',
                'en' => 'Reminder: 6 days left',
                default => 'Recordatorio: Faltan 6 días',
            };
            $headerIcon = '⏰';
        } else {
            $headerTitle = match (substr($locale, 0, 2)) {
                'pt' => 'Lembrete de Votação',
                'fr' => 'Rappel de vote',
                'en' => 'Voting Reminder',
                default => 'Recordatorio de Votación',
            };
            $headerIcon = '⏰';
        }

        $dateLabel = match (substr($locale, 0, 2)) {
            'pt' => 'Prazo',
            'fr' => 'Date limite',
            'en' => 'Deadline',
            default => 'Fecha límite',
        };

        $callToAction = '';
        if ($reason === 'leader_change') {
            $callToAction = match (substr($locale, 0, 2)) {
                'pt' => "\n\n📢 <i>A votação está acirrada! Seu voto pode fazer a diferença.</i>",
                'fr' => "\n\n📢 <i>Le vote est serré ! Votre voix peut faire la différence.</i>",
                'en' => "\n\n📢 <i>The race is close! Cast your vote to make a difference.</i>",
                default => "\n\n📢 <i>¡La votación está reñida! Tu voto puede marcar la diferencia.</i>",
            };
        }

        return sprintf(
            "%s <b>[%s] %s</b>\n<b>%s</b>\n\n🗳️ <b>%s</b>\n%s\n%s%s%s%s",
            $headerIcon,
            htmlspecialchars($this->appName),
            htmlspecialchars($communityName),
            $headerTitle,
            htmlspecialchars($pollTitle),
            $timeRemainingStr,
            $closingInfo ? sprintf("📅 <b>%s:</b> %s\n", $dateLabel, htmlspecialchars($closingInfo)) : '',
            $votesStr,
            $leadingStr,
            $callToAction
        );
    }

    private function formatProposalLabelForTelegram(Entity\Proposal $proposal, Entity\Poll $poll): string
    {
        if ($poll->isDatePoll() && $proposal->getDate()) {
            $date = $proposal->getDate()->getValue();
            $tz = $poll->getTimezoneName() ?: 'UTC';
            $dateFormatted = (new \DateTimeImmutable('@' . $date->getTimestamp()))
                ->setTimezone(new \DateTimeZone($tz))
                ->format('Y-m-d');

            $startTime = $proposal->getStartTime();
            $endTime = $proposal->getEndTime();
            if ($startTime) {
                $start = substr($startTime, 0, 5);
                $timeSlot = $start;
                if ($endTime) {
                    $timeSlot .= ' - ' . substr($endTime, 0, 5);
                }
                return sprintf('%s (%s %s)', $dateFormatted, $timeSlot, $tz);
            }

            if ($proposal->getLabel()) {
                $cleanLabel = preg_replace('/(\d{1,2}:\d{2}):00/', '$1', $proposal->getLabel());
                return sprintf('%s (%s %s)', $dateFormatted, $cleanLabel, $tz);
            }

            return sprintf('%s (%s)', $dateFormatted, $tz);
        }

        return $proposal->getLabel() ?: '—';
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

    public function sendPollReminder(
        Entity\Poll $poll,
        string $locale = 'es',
        string $reason = 'milestone',
        ?string $milestone = null
    ): void {
        $community = $poll->getCommunity();
        if (!$community || !$poll->isNotifyCommunityMembers()) {
            return;
        }

        $pollUrl = $this->router->generate('poll', [
            'slug' => $poll->getSlug() ?: $poll->getId(),
        ], UrlGeneratorInterface::ABSOLUTE_URL);

        // 1. Telegram Reminder
        if ($community->isTelegramEnabled() && $community->getTelegramChatId()) {
            $telegramMsg = $this->buildTelegramReminderMessage($poll, $locale, $reason, $milestone);
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

        $preferredChoices = $poll->getPreferredChoices();
        $winningProposal = count($preferredChoices) === 1 ? $preferredChoices[0] : null;
        $yesCount = $winningProposal ? $winningProposal->countAnswers('yes') : 0;
        $winningLabel = $winningProposal ? $this->formatProposalLabelForTelegram($winningProposal, $poll) : '';

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

            if ($reason === 'leader_change') {
                $leaderTitle = $this->translator->trans('community.notifications.leader_changed', [], null, $userLocale);
                $subject = sprintf(
                    '[%s] 🏆 %s: %s (%s)',
                    $this->appName,
                    $leaderTitle != 'community.notifications.leader_changed' ? $leaderTitle : '¡Cambio en la opción en cabeza!',
                    $pollTitle,
                    $communityName
                );
            } elseif ($milestone === '6h') {
                $rem6h = $this->translator->trans('community.notifications.reminder_6h', [], null, $userLocale);
                $subject = sprintf(
                    '[%s] 🔥 %s: %s (%s)',
                    $this->appName,
                    $rem6h != 'community.notifications.reminder_6h' ? $rem6h : '¡Últimas 6 horas para votar!',
                    $pollTitle,
                    $communityName
                );
            } elseif ($milestone === '12h') {
                $rem12h = $this->translator->trans('community.notifications.reminder_12h', [], null, $userLocale);
                $subject = sprintf(
                    '[%s] 🚨 %s: %s (%s)',
                    $this->appName,
                    $rem12h != 'community.notifications.reminder_12h' ? $rem12h : '¡Últimas 12 horas para votar!',
                    $pollTitle,
                    $communityName
                );
            } elseif ($milestone === '72h') {
                $rem72h = $this->translator->trans('community.notifications.reminder_72h', [], null, $userLocale);
                $subject = sprintf(
                    '[%s] ⏰ %s: %s (%s)',
                    $this->appName,
                    $rem72h != 'community.notifications.reminder_72h' ? $rem72h : 'Recordatorio: Faltan 72 horas',
                    $pollTitle,
                    $communityName
                );
            } elseif ($milestone === '6d') {
                $rem6d = $this->translator->trans('community.notifications.reminder_6d', [], null, $userLocale);
                $subject = sprintf(
                    '[%s] ⏰ %s: %s (%s)',
                    $this->appName,
                    $rem6d != 'community.notifications.reminder_6d' ? $rem6d : 'Recordatorio: Faltan 6 días',
                    $pollTitle,
                    $communityName
                );
            } else {
                $reminderTitle = $this->translator->trans('community.notifications.reminder_title', [], null, $userLocale);
                $subject = sprintf(
                    '[%s] %s: %s (%s)',
                    $this->appName,
                    $reminderTitle != 'community.notifications.reminder_title' ? $reminderTitle : 'Recordatorio de votación',
                    $pollTitle,
                    $communityName
                );
            }

            try {
                $email = (new TemplatedEmail())
                    ->to(new Address($userEmail, $user->getUsername()))
                    ->subject($subject)
                    ->textTemplate('emails/community_reminder.txt.twig')
                    ->htmlTemplate('emails/community_reminder.html.twig')
                    ->context([
                        'app_name' => $this->appName,
                        'user' => $user,
                        'community' => $community,
                        'communityName' => $communityName,
                        'poll' => $poll,
                        'pollTitle' => $pollTitle,
                        'pollUrl' => $pollUrl,
                        'pollOptOutUrl' => $pollOptOutUrl,
                        'communityOptOutUrl' => $communityOptOutUrl,
                        'locale' => $userLocale,
                        'reason' => $reason,
                        'milestone' => $milestone,
                        'leadingProposal' => $winningProposal,
                        'leadingLabel' => $winningLabel,
                        'leadingVotes' => $yesCount,
                    ]);

                $this->mailer->send($email);
            } catch (\Throwable $e) {
                $this->logger->error('Error sending community reminder email: ' . $e->getMessage(), ['exception' => $e]);
            }
        }
    }
}
