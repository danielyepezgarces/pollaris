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

        // Expect 1 email and 1 telegram message with button
        $mailer->expects($this->once())->method('send');
        $telegramService->expects($this->once())->method('sendMessageWithButton')
            ->with('-100123', $this->anything(), $this->stringContains('Emitir mi voto'), 'https://example.org/poll/test');

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

    public function testBuildTelegramReminderMessageFormatting(): void
    {
        $mailer = $this->createMock(MailerInterface::class);
        $telegramService = $this->createMock(TelegramService::class);
        $translator = $this->createMock(TranslatorInterface::class);
        $router = $this->createMock(UrlGeneratorInterface::class);
        $router->method('generate')->willReturn('https://pollaris.toolforge.org/polls/test-poll');
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

        $community = new Community();
        $community->setName('Wikitec Latam');

        $poll = new Poll();
        $poll->setTitle('Elección de Coordinador');
        $poll->setCommunity($community);
        $poll->setTimezoneName('America/Bogota');
        $poll->setClosedAt((new \DateTimeImmutable('now'))->modify('+3 days'));

        // Test Spanish
        $msgEs = $service->buildTelegramReminderMessage($poll, 'es');
        $this->assertStringContainsString('UTC', $msgEs);
        $this->assertStringContainsString('America/Bogota', $msgEs);
        $this->assertStringContainsString('Recordatorio de Votación', $msgEs);
        $this->assertStringContainsString('Elección de Coordinador', $msgEs);
        $this->assertStringContainsString('Wikitec Latam', $msgEs);
        $this->assertStringNotContainsString('https://pollaris.toolforge.org', $msgEs);
        $this->assertSame('🗳️ Emitir mi voto', $service->getVoteButtonText('es'));

        // Test Portuguese
        $msgPt = $service->buildTelegramReminderMessage($poll, 'pt');
        $this->assertStringContainsString('Lembrete de Votação', $msgPt);
        $this->assertStringNotContainsString('https://pollaris.toolforge.org', $msgPt);
        $this->assertSame('🗳️ Emitir meu voto', $service->getVoteButtonText('pt'));

        // Test English
        $msgEn = $service->buildTelegramReminderMessage($poll, 'en');
        $this->assertStringContainsString('Voting Reminder', $msgEn);
        $this->assertStringNotContainsString('https://pollaris.toolforge.org', $msgEn);
        $this->assertSame('🗳️ Cast my vote', $service->getVoteButtonText('en'));
    }

    public function testSendPollClosedNotificationForDatePollIncludesCalendarButtons(): void
    {
        $mailer = $this->createMock(MailerInterface::class);
        $telegramService = $this->createMock(TelegramService::class);
        $translator = $this->createMock(TranslatorInterface::class);
        $router = $this->createMock(UrlGeneratorInterface::class);
        $router->method('generate')->willReturnCallback(function (string $route, array $params = []): string {
            $slug = $params['slug'] ?? 'test-poll';
            if ($route === 'poll ics') {
                return 'https://pollaris.toolforge.org/polls/' . $slug . '/calendar.ics';
            }
            return 'https://pollaris.toolforge.org/polls/' . $slug;
        });
        $em = $this->createMock(EntityManagerInterface::class);
        $memberRepo = $this->createMock(CommunityMemberRepository::class);
        $optOutRepo = $this->createMock(PollNotificationOptOutRepository::class);

        $community = new Community();
        $community->setName('Wikitec Latam');
        $community->setTelegramEnabled(true);
        $community->setTelegramChatId('-100123456789');

        $poll = new Poll();
        $poll->setType('date');
        $poll->setTitle('Reunión Mensual');
        $poll->setSlug('reunion-mensual');
        $poll->setTimezoneName('America/Bogota');
        $poll->setCommunity($community);
        $poll->setNotifyCommunityMembers(true);

        $date = new \App\Entity\Date();
        $date->setValue(new \DateTimeImmutable('2026-12-01 00:00:00', new \DateTimeZone('America/Bogota')));

        $proposal = new \App\Entity\Proposal();
        $proposal->setDate($date);
        $proposal->setStartTime('18:00');
        $proposal->setEndTime('19:30');
        $proposal->setLabel('18:00 - 19:30');
        $poll->addProposal($proposal);

        $vote = new \App\Entity\Vote();
        $vote->setAuthorName('Daniel');
        $answer = new \App\Entity\Answer();
        $answer->setProposal($proposal);
        $answer->setValue('yes');
        $answer->setVote($vote);
        $proposal->addAnswer($answer);
        $poll->addVote($vote);

        $telegramService->expects($this->once())
            ->method('sendMessageWithButtons')
            ->with(
                '-100123456789',
                $this->callback(function (string $html): bool {
                    return str_contains($html, '¡Encuesta finalizada!')
                        && str_contains($html, 'Reunión Mensual')
                        && str_contains($html, 'Opción ganadora');
                }),
                $this->callback(function (array $rows): bool {
                    return count($rows) === 2
                        && $rows[0][0]['text'] === '📅 Google Calendar'
                        && str_contains($rows[0][0]['url'], 'calendar.google.com')
                        && $rows[0][1]['text'] === '📥 iCalendar (.ics)'
                        && str_contains($rows[0][1]['url'], '/calendar.ics')
                        && $rows[1][0]['text'] === '📊 Ver resultados';
                })
            )
            ->willReturn(true);

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

        $service->sendPollClosedNotification($poll, 'es');
    }

    public function testSendPollClosedNotificationForClassicPollHasNoCalendarButtons(): void
    {
        $mailer = $this->createMock(MailerInterface::class);
        $telegramService = $this->createMock(TelegramService::class);
        $translator = $this->createMock(TranslatorInterface::class);
        $router = $this->createMock(UrlGeneratorInterface::class);
        $router->method('generate')->willReturn('https://pollaris.toolforge.org/polls/eleccion');
        $em = $this->createMock(EntityManagerInterface::class);
        $memberRepo = $this->createMock(CommunityMemberRepository::class);
        $optOutRepo = $this->createMock(PollNotificationOptOutRepository::class);

        $community = new Community();
        $community->setName('Wikitec Latam');
        $community->setTelegramEnabled(true);
        $community->setTelegramChatId('-100123456789');

        $poll = new Poll();
        $poll->setType('classic');
        $poll->setTitle('Elección');
        $poll->setSlug('eleccion');
        $poll->setCommunity($community);
        $poll->setNotifyCommunityMembers(true);

        $telegramService->expects($this->once())
            ->method('sendMessageWithButtons')
            ->with(
                '-100123456789',
                $this->anything(),
                $this->callback(function (array $rows): bool {
                    return count($rows) === 1
                        && $rows[0][0]['text'] === '📊 Ver resultados';
                })
            )
            ->willReturn(true);

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

        $service->sendPollClosedNotification($poll, 'es');
    }

    public function testSendPollClosedNotificationForTiedDatePollHasNoCalendarButtons(): void
    {
        $mailer = $this->createMock(MailerInterface::class);
        $telegramService = $this->createMock(TelegramService::class);
        $translator = $this->createMock(TranslatorInterface::class);
        $router = $this->createMock(UrlGeneratorInterface::class);
        $router->method('generate')->willReturn('https://pollaris.toolforge.org/polls/empate');
        $em = $this->createMock(EntityManagerInterface::class);
        $memberRepo = $this->createMock(CommunityMemberRepository::class);
        $optOutRepo = $this->createMock(PollNotificationOptOutRepository::class);

        $community = new Community();
        $community->setName('Wikitec Latam');
        $community->setTelegramEnabled(true);
        $community->setTelegramChatId('-100123456789');

        $poll = new Poll();
        $poll->setType('date');
        $poll->setTitle('Reunión Empatada');
        $poll->setSlug('empate');
        $poll->setTimezoneName('UTC');
        $poll->setCommunity($community);
        $poll->setNotifyCommunityMembers(true);

        $date = new \App\Entity\Date();
        $date->setValue(new \DateTimeImmutable('2026-12-01 00:00:00', new \DateTimeZone('UTC')));

        $p1 = (new \App\Entity\Proposal())->setDate($date)->setStartTime('10:00')->setLabel('10:00');
        $p2 = (new \App\Entity\Proposal())->setDate($date)->setStartTime('15:00')->setLabel('15:00');
        $poll->addProposal($p1)->addProposal($p2);

        $vote = new \App\Entity\Vote();
        $vote->setAuthorName('Daniel');
        $a1 = (new \App\Entity\Answer())->setProposal($p1)->setValue('yes')->setVote($vote);
        $a2 = (new \App\Entity\Answer())->setProposal($p2)->setValue('yes')->setVote($vote);
        $p1->addAnswer($a1);
        $p2->addAnswer($a2);
        $poll->addVote($vote);

        $telegramService->expects($this->once())
            ->method('sendMessageWithButtons')
            ->with(
                '-100123456789',
                $this->callback(function (string $html): bool {
                    return str_contains($html, 'empate')
                        && !str_contains($html, 'añadir el evento a tu calendario');
                }),
                $this->callback(function (array $rows): bool {
                    return count($rows) === 1
                        && $rows[0][0]['text'] === '📊 Ver resultados';
                })
            )
            ->willReturn(true);

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

        $service->sendPollClosedNotification($poll, 'es');
    }
}
