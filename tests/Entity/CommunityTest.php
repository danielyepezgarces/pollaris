<?php

namespace App\Tests\Entity;

use App\Entity\Community;
use App\Entity\CommunityMember;
use App\Entity\Poll;
use App\Entity\User;
use PHPUnit\Framework\TestCase;

class CommunityTest extends TestCase
{
    public function testCommunityInitializationAndMembers(): void
    {
        $owner = new User();
        $owner->setUsername('OwnerUser');

        $community = new Community();
        $community->setName('Wikimedia Colombia');
        $community->setSlug('wikimedia-colombia');
        $community->setOwner($owner);

        $this->assertSame('Wikimedia Colombia', $community->getName());
        $this->assertSame('wikimedia-colombia', $community->getSlug());
        $this->assertSame($owner, $community->getOwner());
        $this->assertNotEmpty($community->getInviteToken());
        $this->assertSame(Community::JOIN_POLICY_APPROVAL, $community->getJoinPolicy());

        // Owner is always an admin
        $this->assertTrue($community->isAdmin($owner));

        // Add member
        $memberUser = new User();
        $memberUser->setUsername('MemberUser');

        $member = new CommunityMember();
        $member->setUser($memberUser);
        $member->setRole(CommunityMember::ROLE_MEMBER);
        $member->setStatus(CommunityMember::STATUS_ACTIVE);
        $community->addMember($member);

        $this->assertTrue($community->isMember($memberUser));
        $this->assertFalse($community->isAdmin($memberUser));

        // Promote member to admin
        $member->setRole(CommunityMember::ROLE_ADMIN);
        $this->assertTrue($community->isAdmin($memberUser));

        // Pending member is not active
        $pendingUser = new User();
        $pendingUser->setUsername('PendingUser');
        $pendingMember = new CommunityMember();
        $pendingMember->setUser($pendingUser);
        $pendingMember->setStatus(CommunityMember::STATUS_PENDING);
        $community->addMember($pendingMember);

        $this->assertFalse($community->isMember($pendingUser));

        // Invite token regeneration
        $oldToken = $community->getInviteToken();
        $community->regenerateInviteToken();
        $this->assertNotSame($oldToken, $community->getInviteToken());
    }

    public function testPollCommunityAssociation(): void
    {
        $community = new Community();
        $community->setName('Community Polls');
        $community->setSlug('community-polls');

        $poll = new Poll();
        $poll->setTitle('Test Community Poll');
        $poll->setCommunity($community);
        $poll->setCommunityOnlyVote(true);

        $this->assertSame($community, $poll->getCommunity());
        $this->assertTrue($poll->isCommunityOnlyVote());

        // Test non-member user eligibility check
        $outsider = new User();
        $outsider->setUsername('OutsiderUser');
        $this->assertFalse($community->isMember($outsider));

        // Test member user eligibility check
        $memberUser = new User();
        $memberUser->setUsername('MemberUser');
        $member = new CommunityMember();
        $member->setUser($memberUser);
        $member->setStatus(CommunityMember::STATUS_ACTIVE);
        $community->addMember($member);
        $this->assertTrue($community->isMember($memberUser));
    }

    public function testEmailEnabledAndPollNotifySettings(): void
    {
        $community = new Community();
        $this->assertTrue($community->isEmailEnabled());

        $community->setEmailEnabled(false);
        $this->assertFalse($community->isEmailEnabled());

        $poll = new Poll();
        $this->assertTrue($poll->isNotifyCommunityMembers());

        $poll->setNotifyCommunityMembers(false);
        $this->assertFalse($poll->isNotifyCommunityMembers());

        // Test User locale
        $user = new User();
        $this->assertNull($user->getLocale());
        $user->setLocale('fr');
        $this->assertSame('fr', $user->getLocale());

        // Test Poll getTitleForLocale
        $poll->setTitle('Default Title');
        $poll->setLocalizedTitles([
            ['locale' => 'es', 'text' => 'Título en Español'],
            ['locale' => 'fr', 'text' => 'Titre en Français'],
        ]);
        $this->assertSame('Título en Español', $poll->getTitleForLocale('es'));
        $this->assertSame('Titre en Français', $poll->getTitleForLocale('fr'));
        $this->assertSame('Default Title', $poll->getTitleForLocale('de'));
    }

    public function testMultipleAdministrators(): void
    {
        $owner = new User();
        $owner->setUsername('OwnerUser');

        $community = new Community();
        $community->setName('Multi Admin Community');
        $community->setOwner($owner);

        $admin1 = new User();
        $admin1->setUsername('Admin1');
        $m1 = new CommunityMember();
        $m1->setUser($admin1);
        $m1->setRole(CommunityMember::ROLE_ADMIN);
        $m1->setStatus(CommunityMember::STATUS_ACTIVE);
        $community->addMember($m1);

        $admin2 = new User();
        $admin2->setUsername('Admin2');
        $m2 = new CommunityMember();
        $m2->setUser($admin2);
        $m2->setRole(CommunityMember::ROLE_ADMIN);
        $m2->setStatus(CommunityMember::STATUS_ACTIVE);
        $community->addMember($m2);

        $regularMember = new User();
        $regularMember->setUsername('RegularMember');
        $m3 = new CommunityMember();
        $m3->setUser($regularMember);
        $m3->setRole(CommunityMember::ROLE_MEMBER);
        $m3->setStatus(CommunityMember::STATUS_ACTIVE);
        $community->addMember($m3);

        $this->assertTrue($community->isAdmin($owner));
        $this->assertTrue($community->isAdmin($admin1));
        $this->assertTrue($community->isAdmin($admin2));
        $this->assertFalse($community->isAdmin($regularMember));

        // Demote admin2
        $m2->setRole(CommunityMember::ROLE_MEMBER);
        $this->assertFalse($community->isAdmin($admin2));
        $this->assertTrue($community->isAdmin($admin1));
    }

    public function testCommunityLocalizedNames(): void
    {
        $community = new Community();
        $community->setName('Default Name');

        $this->assertSame([], $community->getLocalizedNames());
        $this->assertSame('Default Name', $community->getNameForLocale('es'));

        $community->setLocalizedNames([
            ['locale' => 'es', 'text' => 'Nombre en Español'],
            ['locale' => 'fr_FR', 'text' => 'Nom en Français'],
            ['locale' => 'de', 'text' => ''], // empty should be filtered
        ]);

        $this->assertCount(2, $community->getLocalizedNames());
        $this->assertSame('Nombre en Español', $community->getNameForLocale('es'));
        $this->assertSame('Nom en Français', $community->getNameForLocale('fr'));
        $this->assertSame('Default Name', $community->getNameForLocale('it'));
    }
}
