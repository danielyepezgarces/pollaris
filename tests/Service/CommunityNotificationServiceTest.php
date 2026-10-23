<?php

namespace App\Tests\Service;

use App\Entity\Community;
use App\Entity\CommunityMember;
use App\Entity\Poll;
use App\Entity\User;
use App\Entity\Vote;
use App\Repository\CommunityMemberRepository;
use App\Repository\PollNotificationOptOutRepository;
use App\Service\CommunityNotificationService;
use App\Service\TelegramService;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

class CommunityNotificationServiceTest extends TestCase
{
    public function testOptOutTokenGenerationAndVerification(): void
    {
        $mailer = $this->createMock(MailerInterface::class);
        $telegramService = $this->createMock(TelegramService::class);
        $translator = $this->createMock(TranslatorInterface::class);
        $router = $this->createMock(UrlGeneratorInterface::class);
        $em = $this->createMock(EntityManagerInterface::class);
        $memberRepo = $this->createMock(CommunityMemberRepository::class);
        $optOutRepo = $this->createMock(PollNotificationOptOutRepository::class);

        $service = new CommunityNotificationService(
            $mailer,
            $telegramService,
            $translator,
            $router,
            $em,
            $memberRepo,
            $optOutRepo,
            new NullLogger(),
            'Pollaris',
            'test_secret_key_123'
        );

        $poll = new Poll();
        $user = new User();
        $community = new Community();

        // Use reflection to set IDs
        $pollIdProp = new \ReflectionProperty(Poll::class, 'id');
        $pollIdProp->setAccessible(true);
        $pollIdProp->setValue($poll, 'poll-123');

        $userIdProp = new \ReflectionProperty(User::class, 'id');
        $userIdProp->setAccessible(true);
        $userIdProp->setValue($user, 42);

        $commIdProp = new \ReflectionProperty(Community::class, 'id');
        $commIdProp->setAccessible(true);
        $commIdProp->setValue($community, 7);

        $pollToken = $service->generateOptOutToken($poll, $user);
        $this->assertNotEmpty($pollToken);
        $this->assertTrue($service->verifyOptOutToken($poll, $user, $pollToken));
        $this->assertFalse($service->verifyOptOutToken($poll, $user, 'wrong-token'));

        $commToken = $service->generateCommunityOptOutToken($community, $user);
        $this->assertNotEmpty($commToken);
        $this->assertTrue($service->verifyCommunityOptOutToken($community, $user, $commToken));
        $this->assertFalse($service->verifyCommunityOptOutToken($community, $user, 'wrong-token'));
    }

    public function testNotifyNewPollSendsEmailAndTelegram(): void
    {
        $mailer = $this->createMock(MailerInterface::class);
        $telegramService = $this->createMock(TelegramService::class);
        $translator = $this->createMock(TranslatorInterface::class);
        $router = $this->createMock(UrlGeneratorInterface::class);
        $em = $this->createMock(EntityManagerInterface::class);
        $memberRepo = $this->createMock(CommunityMemberRepository::class);
        $optOutRepo = $this->createMock(PollNotificationOptOutRepository::class);

        $router->method('generate')->willReturn('https://example.org/poll/test');
        $translator->method('trans')->willReturn('Vote now');

        $community = new Community();
        $community->setName('Wiki Group');
        $community->setSlug('wiki-group');
        $community->setTelegramEnabled(true);
        $community->setTelegramChatId('-100123');

        $poll = new Poll();
        $poll->setTitle('Planning Poll');
        $poll->setCommunity($community);

        $pollIdProp = new \ReflectionProperty(Poll::class, 'id');
        $pollIdProp->setAccessible(true);
        $pollIdProp->setValue($poll, 'poll-test');

        $user = new User();
        $user->setUsername('ActiveMember');
        $user->setEmail('member@example.org');

        $userIdProp = new \ReflectionProperty(User::class, 'id');
        $userIdProp->setAccessible(true);
        $userIdProp->setValue($user, 10);

        $member = new CommunityMember();
        $member->setUser($user);
        $member->setCommunity($community);
        $member->setReceiveEmails(true);

        $memberRepo->method('findActiveMembers')->willReturn([$member]);
        $optOutRepo->method('isOptedOut')->willReturn(false);

        // Expect 1 email and 1 telegram message
        $mailer->expects($this->once())->method('send');
        $telegramService->expects($this->once())->method('sendMessage');

        $service = new CommunityNotificationService(
            $mailer,
            $telegramService,
            $translator,
            $router,
            $em,
            $memberRepo,
            $optOutRepo,
            new NullLogger(),
            'Pollaris',
            'test_secret_key_123'
        );

        $service->notifyNewPoll($poll);
    }

    public function testSendPollReminderSkipsAlreadyVotedMembers(): void
    {
        $mailer = $this->createMock(MailerInterface::class);
        $telegramService = $this->createMock(TelegramService::class);
        $translator = $this->createMock(TranslatorInterface::class);
        $router = $this->createMock(UrlGeneratorInterface::class);
        $em = $this->createMock(EntityManagerInterface::class);
        $memberRepo = $this->createMock(CommunityMemberRepository::class);
        $optOutRepo = $this->createMock(PollNotificationOptOutRepository::class);

        $router->method('generate')->willReturn('https://example.org/poll/test');

        $community = new Community();
        $community->setName('Wiki Group');

        $poll = new Poll();
        $poll->setTitle('Urgent Poll');
        $poll->setCommunity($community);

        // User 1 has voted
        $user1 = new User();
        $user1->setUsername('VotedUser');
        $user1->setEmail('voted@example.org');
        $member1 = new CommunityMember();
        $member1->setUser($user1);
        $member1->setReceiveEmails(true);

        $vote = new Vote();
        $vote->setAuthorName('VotedUser');
        $poll->addVote($vote);

        // User 2 has NOT voted
        $user2 = new User();
        $user2->setUsername('UnvotedUser');
        $user2->setEmail('unvoted@example.org');
        $userIdProp = new \ReflectionProperty(User::class, 'id');
        $userIdProp->setAccessible(true);
        $userIdProp->setValue($user2, 22);

        $pollIdProp = new \ReflectionProperty(Poll::class, 'id');
        $pollIdProp->setAccessible(true);
        $pollIdProp->setValue($poll, 'poll-urgent');

        $member2 = new CommunityMember();
        $member2->setUser($user2);
        $member2->setReceiveEmails(true);

        $memberRepo->method('findActiveMembers')->willReturn([$member1, $member2]);
        $optOutRepo->method('isOptedOut')->willReturn(false);

        // Expect ONLY 1 email (for user2, not user1)
        $mailer->expects($this->once())->method('send');

        $service = new CommunityNotificationService(
            $mailer,
            $telegramService,
            $translator,
            $router,
            $em,
            $memberRepo,
            $optOutRepo,
            new NullLogger(),
            'Pollaris',
            'test_secret_key_123'
        );

        $service->sendPollReminder($poll);
    }

    public function testNotifyNewPollDoesNotSendWhenCommunityEmailDisabled(): void
    {
        $mailer = $this->createMock(MailerInterface::class);
        $telegramService = $this->createMock(TelegramService::class);
        $translator = $this->createMock(TranslatorInterface::class);
        $router = $this->createMock(UrlGeneratorInterface::class);
        $em = $this->createMock(EntityManagerInterface::class);
        $memberRepo = $this->createMock(CommunityMemberRepository::class);
        $optOutRepo = $this->createMock(PollNotificationOptOutRepository::class);

        $community = new Community();
        $community->setName('Disabled Email Community');
        $community->setEmailEnabled(false);

        $poll = new Poll();
        $poll->setTitle('No Email Poll');
        $poll->setCommunity($community);

        // Mailer should NEVER be called
        $mailer->expects($this->never())->method('send');

        $service = new CommunityNotificationService(
            $mailer,
            $telegramService,
            $translator,
            $router,
            $em,
            $memberRepo,
            $optOutRepo,
            new NullLogger(),
            'Pollaris',
            'test_secret_key_123'
        );

        $service->notifyNewPoll($poll);
    }

    public function testNotifyNewPollDoesNotSendWhenPollNotificationDisabled(): void
    {
        $mailer = $this->createMock(MailerInterface::class);
        $telegramService = $this->createMock(TelegramService::class);
        $translator = $this->createMock(TranslatorInterface::class);
        $router = $this->createMock(UrlGeneratorInterface::class);
        $em = $this->createMock(EntityManagerInterface::class);
        $memberRepo = $this->createMock(CommunityMemberRepository::class);
        $optOutRepo = $this->createMock(PollNotificationOptOutRepository::class);

        $community = new Community();
        $community->setName('Community');
        $community->setTelegramEnabled(true);
        $community->setTelegramChatId('12345');

        $poll = new Poll();
        $poll->setTitle('Silent Poll');
        $poll->setCommunity($community);
        $poll->setNotifyCommunityMembers(false);

        $mailer->expects($this->never())->method('send');
        $telegramService->expects($this->never())->method('sendMessage');

        $service = new CommunityNotificationService(
            $mailer,
            $telegramService,
            $translator,
            $router,
            $em,
            $memberRepo,
            $optOutRepo,
            new NullLogger(),
            'Pollaris',
            'test_secret_key_123'
        );

        $service->notifyNewPoll($poll);
    }
}
