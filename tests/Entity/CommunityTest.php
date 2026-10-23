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
    }
}
