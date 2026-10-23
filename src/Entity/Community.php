<?php

// This file is part of Pollaris.
// Copyright 2026 Daniel Yepez Garces
// SPDX-License-Identifier: AGPL-3.0-or-later

namespace App\Entity;

use App\ActivityMonitor;
use App\Repository;
use App\Utils\Random;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: Repository\CommunityRepository::class)]
#[ORM\Table(name: 'communities')]
#[ORM\UniqueConstraint(name: 'UNIQ_COMMUNITY_SLUG', fields: ['slug'])]
#[UniqueEntity(fields: 'slug', message: 'community.slug.already_used')]
class Community implements ActivityMonitor\TrackableEntityInterface
{
    public const JOIN_POLICY_OPEN = 'open';
    public const JOIN_POLICY_APPROVAL = 'approval';

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 100)]
    #[Assert\NotBlank]
    #[Assert\Length(max: 100)]
    private string $name = '';

    #[ORM\Column(length: 100)]
    #[Assert\NotBlank]
    #[Assert\Length(max: 100)]
    #[Assert\Regex(pattern: '/^[a-zA-Z0-9_-]+$/')]
    private string $slug = '';

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $description = null;

    #[ORM\Column(length: 20)]
    private string $joinPolicy = self::JOIN_POLICY_APPROVAL;

    #[ORM\Column(length: 64, nullable: true)]
    private ?string $inviteToken = null;

    #[ORM\Column(length: 100, nullable: true)]
    private ?string $telegramChatId = null;

    #[ORM\Column(type: Types::BOOLEAN, options: ['default' => false])]
    private bool $telegramEnabled = false;

    #[ORM\Column(type: Types::BOOLEAN, options: ['default' => true])]
    private bool $emailEnabled = true;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: false)]
    private ?User $owner = null;

    #[ORM\Column(type: Types::DATETIMETZ_IMMUTABLE)]
    private ?\DateTimeImmutable $createdAt = null;

    #[ORM\Column(type: Types::DATETIMETZ_IMMUTABLE)]
    private ?\DateTimeImmutable $updatedAt = null;

    /**
     * @var Collection<int, CommunityMember>
     */
    #[ORM\OneToMany(mappedBy: 'community', targetEntity: CommunityMember::class, cascade: ['persist', 'remove'], orphanRemoval: true)]
    private Collection $members;

    /**
     * @var Collection<int, Poll>
     */
    #[ORM\OneToMany(mappedBy: 'community', targetEntity: Poll::class)]
    private Collection $polls;

    public function __construct()
    {
        $this->members = new ArrayCollection();
        $this->polls = new ArrayCollection();
        $this->inviteToken = Random::hex(32);
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function setName(string $name): static
    {
        $this->name = $name;
        return $this;
    }

    public function getSlug(): string
    {
        return $this->slug;
    }

    public function setSlug(string $slug): static
    {
        $this->slug = $slug;
        return $this;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription(?string $description): static
    {
        $this->description = $description;
        return $this;
    }

    public function getJoinPolicy(): string
    {
        return $this->joinPolicy;
    }

    public function setJoinPolicy(string $joinPolicy): static
    {
        $this->joinPolicy = $joinPolicy;
        return $this;
    }

    public function getInviteToken(): ?string
    {
        return $this->inviteToken;
    }

    public function setInviteToken(?string $inviteToken): static
    {
        $this->inviteToken = $inviteToken;
        return $this;
    }

    public function regenerateInviteToken(): static
    {
        $this->inviteToken = Random::hex(32);
        return $this;
    }

    public function getTelegramChatId(): ?string
    {
        return $this->telegramChatId;
    }

    public function setTelegramChatId(?string $telegramChatId): static
    {
        $this->telegramChatId = $telegramChatId;
        return $this;
    }

    public function isTelegramEnabled(): bool
    {
        return $this->telegramEnabled;
    }

    public function setTelegramEnabled(bool $telegramEnabled): static
    {
        $this->telegramEnabled = $telegramEnabled;
        return $this;
    }

    public function isEmailEnabled(): bool
    {
        return $this->emailEnabled;
    }

    public function setEmailEnabled(bool $emailEnabled): static
    {
        $this->emailEnabled = $emailEnabled;
        return $this;
    }

    public function getOwner(): ?User
    {
        return $this->owner;
    }

    public function setOwner(?User $owner): static
    {
        $this->owner = $owner;
        return $this;
    }

    public function getCreatedAt(): ?\DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function setCreatedAt(\DateTimeImmutable $createdAt): self
    {
        $this->createdAt = $createdAt;
        return $this;
    }

    public function getUpdatedAt(): ?\DateTimeImmutable
    {
        return $this->updatedAt;
    }

    public function setUpdatedAt(\DateTimeImmutable $updatedAt): self
    {
        $this->updatedAt = $updatedAt;
        return $this;
    }

    /**
     * @return Collection<int, CommunityMember>
     */
    public function getMembers(): Collection
    {
        return $this->members;
    }

    public function addMember(CommunityMember $member): static
    {
        if (!$this->members->contains($member)) {
            $this->members->add($member);
            $member->setCommunity($this);
        }

        return $this;
    }

    public function removeMember(CommunityMember $member): static
    {
        if ($this->members->removeElement($member)) {
            if ($member->getCommunity() === $this) {
                $member->setCommunity(null);
            }
        }

        return $this;
    }

    /**
     * @return Collection<int, Poll>
     */
    public function getPolls(): Collection
    {
        return $this->polls;
    }

    public function isMember(User $user): bool
    {
        foreach ($this->members as $member) {
            if ($member->getUser() === $user && $member->getStatus() === CommunityMember::STATUS_ACTIVE) {
                return true;
            }
        }

        return false;
    }

    public function isAdmin(User $user): bool
    {
        if ($this->owner === $user) {
            return true;
        }

        foreach ($this->members as $member) {
            if ($member->getUser() === $user && $member->getRole() === CommunityMember::ROLE_ADMIN && $member->getStatus() === CommunityMember::STATUS_ACTIVE) {
                return true;
            }
        }

        return false;
    }

    public function getMemberForUser(User $user): ?CommunityMember
    {
        foreach ($this->members as $member) {
            if ($member->getUser() === $user) {
                return $member;
            }
        }

        return null;
    }
}
