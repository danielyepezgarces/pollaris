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
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Translation\TranslatableMessage;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: Repository\AnswerRepository::class)]
class Answer implements ActivityMonitor\TrackableEntityInterface
{
    public const VALID_VALUES = ['yes', 'maybe', 'no'];

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(type: Types::DATETIMETZ_IMMUTABLE)]
    private ?\DateTimeImmutable $createdAt = null;

    #[ORM\Column(type: Types::DATETIMETZ_IMMUTABLE)]
    private ?\DateTimeImmutable $updatedAt = null;

    #[ORM\ManyToOne(inversedBy: 'answers')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?Vote $vote = null;

    #[ORM\ManyToOne(inversedBy: 'answers')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?Proposal $proposal = null;

    #[ORM\Column(length: 10, nullable: true)]
    #[Assert\Choice(
        choices: self::VALID_VALUES,
        message: new TranslatableMessage('answer.value.invalid', domain: 'validators'),
    )]
    private ?string $value = null;

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

    public function getVote(): ?Vote
    {
        return $this->vote;
    }

    public function setVote(?Vote $vote): static
    {
        $this->vote = $vote;

        return $this;
    }

    public function getProposal(): ?Proposal
    {
        return $this->proposal;
    }

    public function setProposal(?Proposal $proposal): static
    {
        $this->proposal = $proposal;

        return $this;
    }

    public function getValue(): string
    {
        return $this->value ?? '';
    }

    public function getHumanValue(): TranslatableMessage
    {
        return self::translateValue($this->getValue());
    }

    public function setValue(?string $value): static
    {
        $this->value = $value;

        return $this;
    }

    public static function translateValue(string $value): TranslatableMessage
    {
        if ($value === 'yes') {
            return new TranslatableMessage('answers.value.yes');
        } elseif ($value === 'maybe') {
            return new TranslatableMessage('answers.value.maybe');
        } elseif ($value === 'no') {
            return new TranslatableMessage('answers.value.no');
        } else {
            throw new \LogicException("Cannot translate value {$value}");
        }
    }
}
