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
use App\Doctrine;
use App\Repository;
use App\Validator as AppAssert;
use Doctrine\Common\Collections;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;
use Symfony\Component\Translation\TranslatableMessage;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: Repository\VoteRepository::class)]
#[AppAssert\MaxVotes(
    message: new TranslatableMessage('vote.max_votes.limited', domain: 'validators'),
)]
#[UniqueEntity(
    fields: ['authorName', 'poll'],
    repositoryMethod: 'findByPollAndAuthorName',
    message: new TranslatableMessage('user.author_name.already_used', domain: 'validators'),
)]
#[UniqueEntity(
    fields: ['owner', 'poll'],
    message: new TranslatableMessage('vote.owner.already_voted', domain: 'validators'),
    ignoreNull: ['owner'],
)]
class Vote implements ActivityMonitor\TrackableEntityInterface
{
    public const MAX_AUTHOR_NAME_LENGTH = 100;

    #[ORM\Id]
    #[ORM\Column(length: 20)]
    #[ORM\GeneratedValue(strategy: 'CUSTOM')]
    #[ORM\CustomIdGenerator(class: Doctrine\HexIdGenerator::class)]
    private ?string $id = null;

    #[ORM\Column(type: Types::DATETIMETZ_IMMUTABLE)]
    private ?\DateTimeImmutable $createdAt = null;

    #[ORM\Column(type: Types::DATETIMETZ_IMMUTABLE)]
    private ?\DateTimeImmutable $updatedAt = null;

    #[ORM\Column(length: self::MAX_AUTHOR_NAME_LENGTH)]
    #[Assert\NotBlank(
        message: new TranslatableMessage('vote.author_name.required', domain: 'validators'),
    )]
    #[Assert\Length(
        max: self::MAX_AUTHOR_NAME_LENGTH,
        maxMessage: new TranslatableMessage('vote.author_name.max_length', domain: 'validators'),
    )]
    private ?string $authorName = null;

    #[ORM\ManyToOne(inversedBy: 'votes')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?Poll $poll = null;

    #[ORM\ManyToOne(inversedBy: 'votes')]
    #[ORM\JoinColumn(nullable: true, onDelete: 'CASCADE')]
    private ?User $owner = null;

    /** @var Collections\Collection<int, Answer> */
    #[ORM\OneToMany(
        targetEntity: Answer::class,
        mappedBy: 'vote',
        cascade: ['persist'],
        orphanRemoval: true,
    )]
    #[Assert\Valid]
    private Collections\Collection $answers;

    public function __construct()
    {
        $this->answers = new Collections\ArrayCollection();
    }

    public function getId(): ?string
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

    public function getAuthorName(): ?string
    {
        return $this->authorName;
    }

    public function setAuthorName(string $authorName): static
    {
        $this->authorName = $authorName;

        return $this;
    }

    public function getPoll(): Poll
    {
        assert($this->poll !== null);

        return $this->poll;
    }

    public function setPoll(?Poll $poll): static
    {
        $this->poll = $poll;

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

    /**
     * @return Collections\Collection<int, Answer>
     */
    public function getAnswers(): Collections\Collection
    {
        return $this->answers;
    }

    public function getAnswerForProposal(Proposal $proposal): ?Answer
    {
        return $this->answers->findFirst(function ($key, $answer) use ($proposal): bool {
            $answerProposal = $answer->getProposal();

            if (!$answerProposal) {
                return false;
            }

            if ($proposal->getId() !== null && $answerProposal->getId() !== null) {
                // The proposals are persisted in database so we compare the
                // ids of the two entities as it's more reliable.
                return $answerProposal->getId() === $proposal->getId();
            } else {
                // The proposals are not persisted yet in the database and are
                // only in memory. Hopefully in this case, if the two proposals
                // must match, there are the same PHP objects so it's fine.
                return $answerProposal === $proposal;
            }
        });
    }

    public function hasAnswerForProposal(Proposal $proposal): bool
    {
        return $this->getAnswerForProposal($proposal) !== null;
    }

    public function addAnswer(Answer $answer): static
    {
        if (!$this->answers->contains($answer)) {
            $this->answers->add($answer);
            $answer->setVote($this);
        }

        return $this;
    }

    public function removeAnswer(Answer $answer): static
    {
        if ($this->answers->removeElement($answer)) {
            if ($answer->getVote() === $this) {
                $answer->setVote(null);
            }
        }

        return $this;
    }
}
