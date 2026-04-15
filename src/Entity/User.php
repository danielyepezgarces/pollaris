<?php

// This file is part of Pollaris.
// Copyright 2024-2026 Marien Fressinaud
// Copyright 2026 Daniel Yepez Garces
// SPDX-License-Identifier: AGPL-3.0-or-later
//
// Modified by Daniel Yepez Garces on 2026-04-15:
// - Migrated database backend from PostgreSQL to MariaDB for Toolforge deployment
// - Added Wikimedia login support
// - Removed local username/password authentication
// - Added multilingual survey support
// - Added user timezone display for survey times when different from server UTC

namespace App\Entity;

use App\ActivityMonitor;
use App\Repository;
use Doctrine\Common\Collections;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Translation\TranslatableMessage;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: Repository\UserRepository::class)]
#[ORM\Table(name: '`users`')]
#[ORM\UniqueConstraint(name: 'UNIQ_IDENTIFIER_USERNAME', fields: ['username'])]
#[UniqueEntity(
    fields: 'username',
    message: new TranslatableMessage('user.username.already_used', domain: 'validators'),
)]
class User implements ActivityMonitor\TrackableEntityInterface, UserInterface, PasswordAuthenticatedUserInterface
{
    public const MAX_USERNAME_LENGTH = 100;
    public const MAX_REALNAME_LENGTH = 255;
    public const MAX_EMAIL_LENGTH = 255;

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(type: Types::DATETIMETZ_IMMUTABLE)]
    private ?\DateTimeImmutable $createdAt = null;

    #[ORM\Column(type: Types::DATETIMETZ_IMMUTABLE)]
    private ?\DateTimeImmutable $updatedAt = null;

    #[ORM\Column(length: self::MAX_USERNAME_LENGTH)]
    #[Assert\NotBlank(
        message: new TranslatableMessage('user.username.required', domain: 'validators'),
    )]
    #[Assert\Length(
        max: self::MAX_USERNAME_LENGTH,
        maxMessage: new TranslatableMessage('user.username.max_length', domain: 'validators'),
    )]
    private ?string $username = null;

    #[ORM\Column(length: 100, unique: true, nullable: true)]
    private ?string $wikimediaId = null;

    #[ORM\Column(length: self::MAX_REALNAME_LENGTH, nullable: true)]
    private ?string $realName = null;

    #[ORM\Column(length: self::MAX_EMAIL_LENGTH, nullable: true)]
    private ?string $email = null;

    /**
     * @var list<string>
     */
    #[ORM\Column]
    private array $roles = [];

    #[ORM\Column]
    private ?string $password = null;

    /**
     * @var Collections\Collection<int, Poll>
     */
    #[ORM\OneToMany(targetEntity: Poll::class, mappedBy: 'owner')]
    private Collections\Collection $ownedPolls;

    /**
     * @var Collections\Collection<int, Vote>
     */
    #[ORM\OneToMany(targetEntity: Vote::class, mappedBy: 'owner')]
    private Collections\Collection $votes;

    public function __construct()
    {
        $this->ownedPolls = new Collections\ArrayCollection();
        $this->votes = new Collections\ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getCreatedAt(): ?\DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function setCreatedAt(\DateTimeImmutable $createdAt): static
    {
        $this->createdAt = $createdAt;

        return $this;
    }

    public function getUpdatedAt(): ?\DateTimeImmutable
    {
        return $this->updatedAt;
    }

    public function setUpdatedAt(\DateTimeImmutable $updatedAt): static
    {
        $this->updatedAt = $updatedAt;

        return $this;
    }

    public function getUsername(): ?string
    {
        return $this->username;
    }

    public function setUsername(string $username): static
    {
        $this->username = $username;

        return $this;
    }

    public function getWikimediaId(): ?string
    {
        return $this->wikimediaId;
    }

    public function setWikimediaId(?string $wikimediaId): static
    {
        $this->wikimediaId = $wikimediaId;

        return $this;
    }

    public function getRealName(): ?string
    {
        return $this->realName;
    }

    public function setRealName(?string $realName): static
    {
        $this->realName = $realName;

        return $this;
    }

    public function getEmail(): ?string
    {
        return $this->email;
    }

    public function setEmail(?string $email): static
    {
        $this->email = $email;

        return $this;
    }

    public function getDisplayName(): string
    {
        if ($this->realName) {
            return $this->realName;
        }

        return $this->getUserIdentifier();
    }

    /**
     * A visual identifier that represents this user.
     *
     * @see UserInterface
     */
    public function getUserIdentifier(): string
    {
        $identifier = $this->username;

        if (!$identifier) {
            throw new \LogicException('User identifier (username) cannot be empty');
        }

        return $identifier;
    }

    /**
     * @see UserInterface
     */
    public function getRoles(): array
    {
        $roles = $this->roles;
        // guarantee every user at least has ROLE_USER
        $roles[] = 'ROLE_USER';

        return array_unique($roles);
    }

    /**
     * @param list<string> $roles
     */
    public function setRoles(array $roles): static
    {
        $this->roles = $roles;

        return $this;
    }

    /**
     * @see PasswordAuthenticatedUserInterface
     */
    public function getPassword(): ?string
    {
        return $this->password;
    }

    public function setPassword(string $password): static
    {
        $this->password = $password;

        return $this;
    }

    /**
     * @see UserInterface
     */
    public function eraseCredentials(): void
    {
        // If you store any temporary, sensitive data on the user, clear it here
        // $this->plainPassword = null;
    }

    /**
     * @return Collections\Collection<int, Poll>
     */
    public function getOwnedPolls(): Collections\Collection
    {
        return $this->ownedPolls;
    }

    /**
     * @return Collections\Collection<int, Vote>
     */
    public function getVotes(): Collections\Collection
    {
        return $this->votes;
    }
}
