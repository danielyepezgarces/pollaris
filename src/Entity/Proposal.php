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
use Symfony\Component\Translation\TranslatableMessage;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: Repository\ProposalRepository::class)]
class Proposal implements ActivityMonitor\TrackableEntityInterface
{
    public const MAX_LABEL_LENGTH = 200;

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(type: Types::DATETIMETZ_IMMUTABLE)]
    private ?\DateTimeImmutable $createdAt = null;

    #[ORM\Column(type: Types::DATETIMETZ_IMMUTABLE)]
    private ?\DateTimeImmutable $updatedAt = null;

    #[ORM\ManyToOne(inversedBy: 'proposals')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?Poll $poll = null;

    #[ORM\Column(length: self::MAX_LABEL_LENGTH)]
    #[Assert\NotBlank(
        message: new TranslatableMessage('proposal.label.required', domain: 'validators'),
    )]
    #[Assert\Length(
        max: self::MAX_LABEL_LENGTH,
        maxMessage: new TranslatableMessage('proposal.label.max_length', domain: 'validators'),
    )]
    private ?string $label = null;

    /** @var Collections\Collection<int, Answer> */
    #[ORM\OneToMany(
        targetEntity: Answer::class,
        mappedBy: 'proposal',
        orphanRemoval: true,
    )]
    #[ORM\OrderBy(['createdAt' => 'ASC'])]
    private Collections\Collection $answers;

    #[ORM\ManyToOne(inversedBy: 'proposals')]
    #[ORM\JoinColumn(onDelete: 'CASCADE')]
    private ?Date $date = null;

    public function __construct()
    {
        $this->label = '';
        $this->answers = new Collections\ArrayCollection();
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

    public function getPoll(): ?Poll
    {
        return $this->poll;
    }

    public function setPoll(?Poll $poll): static
    {
        $this->poll = $poll;

        return $this;
    }

    public function getLabel(): ?string
    {
        return $this->label;
    }

    public function setLabel(string $label): static
    {
        $this->label = $label;

        return $this;
    }

    /**
     * @return Collections\Collection<int, Answer>
     */
    public function getAnswers(): Collections\Collection
    {
        return $this->answers;
    }

    public function addAnswer(Answer $answer): static
    {
        if (!$this->answers->contains($answer)) {
            $this->answers->add($answer);
            $answer->setProposal($this);
        }

        return $this;
    }

    public function removeAnswer(Answer $answer): static
    {
        if ($this->answers->removeElement($answer)) {
            if ($answer->getProposal() === $this) {
                $answer->setProposal(null);
            }
        }

        return $this;
    }

    public function countAnswers(string $answerValue, ?Vote $excludeVote = null): int
    {
        $expressionBuilder = Collections\Criteria::expr();

        if ($excludeVote === null) {
            $expression = $expressionBuilder->eq('value', $answerValue);
        } else {
            $expression = $expressionBuilder->andX(
                $expressionBuilder->eq('value', $answerValue),
                $expressionBuilder->neq('vote', $excludeVote),
            );
        }

        $criteria = new Collections\Criteria($expression);

        return count($this->answers->matching($criteria));
    }

    /**
     * Return a list of (missing) answers for the votes on this proposal.
     *
     * This can happen when an admin adds new proposals to a poll that already
     * has votes.
     *
     * @return Answer[]
     */
    public function buildMissingAnswers(): array
    {
        $poll = $this->getPoll();

        if (!$poll) {
            return [];
        }

        $answers = [];
        $votes = $poll->getVotes();

        foreach ($votes as $vote) {
            $hasAnswer = $vote->hasAnswerForProposal($this);

            if ($hasAnswer) {
                continue;
            }

            $answer = new Answer();
            $answer->setValue('');

            $vote->addAnswer($answer);
            $this->addAnswer($answer);

            $answers[] = $answer;
        }

        return $answers;
    }

    public function getDate(): ?Date
    {
        return $this->date;
    }

    public function setDate(?Date $date): static
    {
        $this->date = $date;

        return $this;
    }
    /**
     * Hora de inicio (opcional, formato HH:MM)
     */
    #[ORM\Column(type: Types::STRING, length: 5, nullable: true)]
    private ?string $startTime = null;

    /**
     * Hora de fin (opcional, formato HH:MM)
     */
    #[ORM\Column(type: Types::STRING, length: 5, nullable: true)]
    private ?string $endTime = null;

    public function getStartTime(): ?string
    {
        if ($this->startTime && !preg_match('/^([01]?\d|2[0-3]):([0-5]\d)$/', $this->startTime)) {
            return null;
        }
        return $this->startTime;
    }

    public function setStartTime(?string $startTime): static
    {
        if ($startTime !== null && $startTime !== '') {
            $parts = explode(':', $startTime);
            if (count($parts) >= 2) {
                $this->startTime = str_pad(substr($parts[0], -2), 2, '0', STR_PAD_LEFT) . ':' . str_pad(substr($parts[1], 0, 2), 2, '0', STR_PAD_LEFT);
            } else {
                $this->startTime = null;
            }
        } else {
            $this->startTime = null;
        }
        return $this;
    }

    public function getEndTime(): ?string
    {
        if ($this->endTime && !preg_match('/^([01]?\d|2[0-3]):([0-5]\d)$/', $this->endTime)) {
            return null;
        }
        return $this->endTime;
    }

    public function setEndTime(?string $endTime): static
    {
        if ($endTime !== null && $endTime !== '') {
            $parts = explode(':', $endTime);
            if (count($parts) >= 2) {
                $this->endTime = str_pad(substr($parts[0], -2), 2, '0', STR_PAD_LEFT) . ':' . str_pad(substr($parts[1], 0, 2), 2, '0', STR_PAD_LEFT);
            } else {
                $this->endTime = null;
            }
        } else {
            $this->endTime = null;
        }
        return $this;
    }
}
