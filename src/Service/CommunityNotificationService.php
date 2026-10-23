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
                "📢 <b>[%s] %s</b>\n\n<b>%s</b>\n%s\n\n🗳 <a href=\"%s\">%s</a>",
                htmlspecialchars($this->appName),
                htmlspecialchars($community->getName()),
                htmlspecialchars($poll->getTitle()),
                htmlspecialchars(mb_substr(strip_tags($poll->getDescription()), 0, 200) . '...'),
                htmlspecialchars($pollUrl),
                $this->translator->trans('community.notifications.vote_now')
            );
            $this->telegramService->sendMessage($community->getTelegramChatId(), $telegramMsg);
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

            $subject = sprintf('[%s] %s: %s', $this->appName, $community->getName(), $poll->getTitle());

            try {
                $email = (new TemplatedEmail())
                    ->to(new Address($userEmail, $user->getUsername()))
                    ->subject($subject)
                    ->textTemplate('emails/community_new_poll.txt.twig')
                    ->context([
                        'user' => $user,
                        'community' => $community,
                        'poll' => $poll,
                        'pollUrl' => $pollUrl,
                        'pollOptOutUrl' => $pollOptOutUrl,
                        'communityOptOutUrl' => $communityOptOutUrl,
                    ]);

                $this->mailer->send($email);
            } catch (\Throwable $e) {
                $this->logger->error('Error sending new community poll email: ' . $e->getMessage(), ['exception' => $e]);
            }
        }
    }

    /**
     * Send reminders for polls expiring in the next 24 hours.
     * Only reminds members who have not voted yet.
     */
    public function sendDueReminders(): int
    {
        $now = new \DateTimeImmutable();
        $in24Hours = $now->modify('+24 hours');

        $polls = $this->entityManager->createQuery(
            'SELECT p FROM App\Entity\Poll p
             JOIN p.community c
             WHERE p.closedAt IS NOT NULL
             AND p.closedAt > :now
             AND p.closedAt <= :in24Hours
             AND p.remindedAt IS NULL'
        )
        ->setParameter('now', $now)
        ->setParameter('in24Hours', $in24Hours)
        ->getResult();

        $count = 0;
        foreach ($polls as $poll) {
            $this->sendPollReminder($poll);
            $poll->setRemindedAt(new \DateTimeImmutable());
            $this->entityManager->flush();
            $count++;
        }

        return $count;
    }

    public function sendPollReminder(Entity\Poll $poll): void
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
            $telegramMsg = sprintf(
                "⏰ <b>[%s] %s</b>\n\n<b>%s:</b> %s\n⏳ <b>%s</b>\n\n🗳 <a href=\"%s\">%s</a>",
                htmlspecialchars($this->appName),
                htmlspecialchars($community->getName()),
                $this->translator->trans('community.notifications.reminder_title'),
                htmlspecialchars($poll->getTitle()),
                $this->translator->trans('community.notifications.closing_soon'),
                htmlspecialchars($pollUrl),
                $this->translator->trans('community.notifications.vote_now')
            );
            $this->telegramService->sendMessage($community->getTelegramChatId(), $telegramMsg);
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

            $subject = sprintf(
                '[%s] %s: %s (%s)',
                $this->appName,
                $this->translator->trans('community.notifications.reminder_title'),
                $poll->getTitle(),
                $community->getName()
            );

            try {
                $email = (new TemplatedEmail())
                    ->to(new Address($userEmail, $user->getUsername()))
                    ->subject($subject)
                    ->textTemplate('emails/community_reminder.txt.twig')
                    ->context([
                        'user' => $user,
                        'community' => $community,
                        'poll' => $poll,
                        'pollUrl' => $pollUrl,
                        'pollOptOutUrl' => $pollOptOutUrl,
                        'communityOptOutUrl' => $communityOptOutUrl,
                    ]);

                $this->mailer->send($email);
            } catch (\Throwable $e) {
                $this->logger->error('Error sending community reminder email: ' . $e->getMessage(), ['exception' => $e]);
            }
        }
    }
}
