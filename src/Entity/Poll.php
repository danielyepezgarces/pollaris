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
use App\Utils;
use Doctrine\Common\Collections;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Event\PostPersistEventArgs;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;
use Symfony\Component\Translation\TranslatableMessage;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: Repository\PollRepository::class)]
#[ORM\HasLifecycleCallbacks]
#[UniqueEntity(
    fields: 'slug',
    message: new TranslatableMessage('poll.slug.already_used', domain: 'validators'),
)]
class Poll implements ActivityMonitor\TrackableEntityInterface
{
    public const MAX_TITLE_LENGTH = 200;
    public const MAX_AUTHOR_NAME_LENGTH = 100;
    public const MAX_SLUG_LENGTH = 20;
    public const SLUG_PATTERN = '/^[\w\-]+$/';

    public const TYPES = ['date', 'classic'];
    public const DEFAULT_TYPE = 'classic';

    public const EDIT_VOTE_MODES = ['own', 'no', 'any'];
    public const DEFAULT_EDIT_VOTE_MODE = 'own';

    public const TIMEZONE_MODES = ['server', 'browser'];
    public const DEFAULT_TIMEZONE_MODE = 'server';

    #[ORM\Id]
    #[ORM\Column(length: 20)]
    #[ORM\GeneratedValue(strategy: 'CUSTOM')]
    #[ORM\CustomIdGenerator(class: Doctrine\HexIdGenerator::class)]
    private ?string $id = null;

    #[ORM\Column(type: Types::DATETIMETZ_IMMUTABLE)]
    private ?\DateTimeImmutable $createdAt = null;

    #[ORM\Column(type: Types::DATETIMETZ_IMMUTABLE)]
    private ?\DateTimeImmutable $updatedAt = null;

    #[ORM\Column(type: Types::DATETIMETZ_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $completedAt = null;

    #[ORM\Column(length: 20, unique: true, nullable: true)]
    #[Assert\Length(
        max: self::MAX_TITLE_LENGTH,
        maxMessage: new TranslatableMessage('poll.slug.max_length', domain: 'validators'),
    )]
    #[Assert\Regex(
        pattern: self::SLUG_PATTERN,
        message: new TranslatableMessage('poll.slug.pattern', domain: 'validators'),
    )]
    private ?string $slug = null;

    #[ORM\Column(length: 20)]
    private ?string $adminToken = null;

    #[ORM\Column(length: 20, options: ['default' => self::DEFAULT_TYPE])]
    #[Assert\Choice(
        choices: self::TYPES,
        message: new TranslatableMessage('poll.type.invalid', domain: 'validators'),
    )]
    private ?string $type = null;

    #[ORM\Column(length: self::MAX_TITLE_LENGTH)]
    #[Assert\NotBlank(
        message: new TranslatableMessage('poll.title.required', domain: 'validators'),
    )]
    #[Assert\Length(
        max: self::MAX_TITLE_LENGTH,
        maxMessage: new TranslatableMessage('poll.title.max_length', domain: 'validators'),
    )]
    private ?string $title = null;

    #[ORM\Column(type: Types::TEXT)]
    private ?string $description = null;

    #[ORM\Column(type: Types::JSON, nullable: true)]
    private ?array $localizedDescriptions = null;

    #[ORM\Column(length: 255, options: ['default' => ''])]
    private ?string $password = null;

    #[ORM\Column(options: ['default' => false])]
    private ?bool $isPasswordForVotesOnly = null;

    #[ORM\Column(length: self::MAX_AUTHOR_NAME_LENGTH)]
    #[Assert\Length(
        max: self::MAX_AUTHOR_NAME_LENGTH,
        maxMessage: new TranslatableMessage('poll.author_name.max_length', domain: 'validators'),
    )]
    private string $authorName = '';

    #[ORM\Column(length: 255)]
    #[Assert\Email(
        message: new TranslatableMessage('poll.author_email.invalid', domain: 'validators'),
    )]
    private ?string $authorEmail = null;

    #[ORM\Column(length: 10, options: ['default' => 'fr_FR'])]
    private string $locale = '';

    #[ORM\ManyToOne(inversedBy: 'ownedPolls')]
    #[ORM\JoinColumn(onDelete: 'SET NULL')]
    private ?User $owner = null;

    #[ORM\Column(length: 20, options: ['default' => self::DEFAULT_TIMEZONE_MODE])]
    #[Assert\Choice(
        choices: self::TIMEZONE_MODES,
        message: new TranslatableMessage('poll.timezone_mode.invalid', domain: 'validators'),
    )]
    private string $timezoneMode = self::DEFAULT_TIMEZONE_MODE;

    #[ORM\Column(length: 100)]
    private string $timezoneName;

    /** @var Collections\Collection<int, Proposal> */
    #[ORM\OneToMany(
        targetEntity: Proposal::class,
        mappedBy: 'poll',
        cascade: ['persist'],
        orphanRemoval: true,
    )]
    #[Assert\Valid]
    private Collections\Collection $proposals;

    /**
     * @var Collections\Collection<int, Date>
     */
    #[ORM\OneToMany(
        targetEntity: Date::class,
        mappedBy: 'poll',
        cascade: ['persist'],
        orphanRemoval: true,
    )]
    #[ORM\OrderBy(['value' => 'ASC'])]
    #[Assert\Valid]
    private Collections\Collection $dates;

    /** @var Collections\Collection<int, Vote> */
    #[ORM\OneToMany(
        targetEntity: Vote::class,
        mappedBy: 'poll',
        orphanRemoval: true,
    )]
    #[ORM\OrderBy(['createdAt' => 'ASC'])]
    private Collections\Collection $votes;

    #[ORM\Column(nullable: true)]
    private ?int $maxVotes = null;

    #[ORM\Column(nullable: true)]
    private ?int $minWikimediaAccountAgeMonths = null;

    #[ORM\Column(length: 50, nullable: true)]
    #[Assert\Regex(
        pattern: '/^[a-z0-9_-]+$/',
        message: new TranslatableMessage('poll.wikimedia_edits_project.invalid', domain: 'validators'),
    )]
    private ?string $minWikimediaEditsProject = null;

    #[ORM\Column(nullable: true)]
    private ?int $minWikimediaEditsCount = null;

    #[ORM\Column(options: ['default' => false])]
    private bool $notifyOnVotes = false;

    /**
     * @var Collections\Collection<int, Comment>
     */
    #[ORM\OneToMany(
        targetEntity: Comment::class,
        mappedBy: 'poll',
        orphanRemoval: true,
    )]
    #[ORM\OrderBy(['createdAt' => 'ASC'])]
    private Collections\Collection $comments;

    #[ORM\Column(options: ['default' => false])]
    private bool $notifyOnComments = false;

    #[ORM\Column(type: Types::DATETIMETZ_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $closedAt = null;

    #[ORM\Column(options: ['default' => true])]
    private ?bool $areResultsPublic = null;

    #[ORM\Column(options: ['default' => false])]
    private bool $isPubliclyListed = false;

    #[ORM\Column(length: 20, options: ['default' => self::DEFAULT_EDIT_VOTE_MODE])]
    #[Assert\Choice(
        choices: self::EDIT_VOTE_MODES,
        message: new TranslatableMessage('poll.edit_vote_mode.invalid', domain: 'validators'),
    )]
    private ?string $editVoteMode = null;

    #[ORM\Column(options: ['default' => false])]
    private bool $disableMaybe = false;

    #[ORM\Column(options: ['default' => false])]
    private bool $voteNoByDefault = false;

    public function __construct()
    {
        $this->type = self::DEFAULT_TYPE;
        $this->title = '';
        $this->description = '';
        $this->closedAt = Utils\Time::fromNow(1, 'month');
        $this->password = '';
        $this->isPasswordForVotesOnly = false;
        $this->authorName = '';
        $this->authorEmail = '';
        $this->notifyOnVotes = true;
        $this->notifyOnComments = true;
        $this->areResultsPublic = true;
        $this->isPubliclyListed = false;
        $this->editVoteMode = self::DEFAULT_EDIT_VOTE_MODE;
        $this->timezoneMode = self::DEFAULT_TIMEZONE_MODE;
        $this->timezoneName = Utils\Time::getServerTimezoneName();
        $this->proposals = new Collections\ArrayCollection();
        $this->votes = new Collections\ArrayCollection();
        $this->dates = new Collections\ArrayCollection();
        $this->comments = new Collections\ArrayCollection();
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

    public function isCompleted(): bool
    {
        return $this->completedAt !== null;
    }

    public function getCompletedAt(): ?\DateTimeImmutable
    {
        return $this->completedAt;
    }

    public function setCompletedAt(?\DateTimeImmutable $completedAt): static
    {
        $this->completedAt = $completedAt;

        return $this;
    }

    public function getType(): ?string
    {
        return $this->type;
    }

    public function setType(string $type): static
    {
        $this->type = $type;

        return $this;
    }

    public function isClassicPoll(): bool
    {
        return $this->type === 'classic';
    }

    public function isDatePoll(): bool
    {
        return $this->type === 'date';
    }

    public function getTitle(): ?string
    {
        return $this->title;
    }

    public function setTitle(string $title): static
    {
        $this->title = $title;

        return $this;
    }

    public function isTitleSet(): bool
    {
        return $this->title !== null && $this->title !== '';
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription(string $description): static
    {
        $this->description = $description;

        return $this;
    }

    /** @return list<array{locale: string, text: string}> */
    public function getLocalizedDescriptions(): array
    {
        return $this->localizedDescriptions ?? [];
    }

    /** @param list<array{locale: string, text: string}> $localizedDescriptions */
    public function setLocalizedDescriptions(array $localizedDescriptions): static
    {
        // Filter out empty entries
        $filtered = array_values(array_filter(
            $localizedDescriptions,
            static fn (array $item) => isset($item['locale'], $item['text']) && $item['text'] !== '',
        ));
        $this->localizedDescriptions = $filtered ?: null;

        return $this;
    }

    public function getPassword(): ?string
    {
        return $this->password;
    }

    public function setPassword(string $password): static
    {
        $this->password = $password;

        return $this;
    }

    public function isPasswordProtected(): bool
    {
        return $this->password !== '';
    }

    public function isVotePasswordProtected(): bool
    {
        return $this->isPasswordProtected() && $this->isPasswordForVotesOnly();
    }

    public function isFullPasswordProtected(): bool
    {
        return $this->isPasswordProtected() && !$this->isPasswordForVotesOnly();
    }

    public function isPasswordForVotesOnly(): ?bool
    {
        return $this->isPasswordForVotesOnly;
    }

    public function setIsPasswordForVotesOnly(bool $isPasswordForVotesOnly): static
    {
        $this->isPasswordForVotesOnly = $isPasswordForVotesOnly;

        return $this;
    }

    public function getAuthorName(): string
    {
        return $this->authorName;
    }

    public function setAuthorName(string $authorName): static
    {
        $this->authorName = $authorName;

        return $this;
    }

    public function getAuthorEmail(): ?string
    {
        return $this->authorEmail;
    }

    public function setAuthorEmail(string $authorEmail): static
    {
        $this->authorEmail = $authorEmail;

        return $this;
    }

    public function getLocale(): string
    {
        return $this->locale;
    }

    public function setLocale(string $locale): static
    {
        $this->locale = $locale;

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

    public function getTimezoneMode(): string
    {
        return $this->timezoneMode;
    }

    public function setTimezoneMode(string $timezoneMode): static
    {
        $this->timezoneMode = $timezoneMode;

        return $this;
    }

    public function getTimezoneName(): string
    {
        return $this->timezoneName;
    }

    public function setTimezoneName(string $timezoneName): static
    {
        $this->timezoneName = $timezoneName;

        return $this;
    }

    /**
     * @return Collections\Collection<int, Proposal>
     */
    public function getProposals(): Collections\Collection
    {
        return $this->proposals;
    }

    /**
     * @return array<array{Date, Proposal[]}>
     */
    public function getProposalsByDates(): array
    {
        return self::groupDateProposals($this->proposals);
    }

    /**
     * @return Proposal[]
     */
    public function getPreferredChoices(): array
    {
        $maxYes = 0;
        $preferredChoices = [];

        foreach ($this->proposals as $proposal) {
            $countYes = $proposal->countAnswers('yes');

            if ($countYes === 0) {
                continue;
            }

            if ($countYes > $maxYes) {
                $maxYes = $countYes;
                $preferredChoices = [$proposal];
            } elseif ($countYes === $maxYes) {
                $preferredChoices[] = $proposal;
            }
        }

        return $preferredChoices;
    }

    public function getPositiveAnswersMaxCount(): int
    {
        $counts = array_map(function (Proposal $proposal): int {
            return $proposal->countAnswers('yes') + $proposal->countAnswers('maybe');
        }, $this->proposals->toArray());

        if (count($counts) === 0) {
            return 0;
        }

        return max($counts);
    }

    public function addProposal(Proposal $proposal): static
    {
        if (!$this->proposals->contains($proposal)) {
            $this->proposals->add($proposal);
            $proposal->setPoll($this);
        }

        return $this;
    }

    public function removeProposal(Proposal $proposal): static
    {
        if ($this->proposals->removeElement($proposal)) {
            if ($proposal->getPoll() === $this) {
                $proposal->setPoll(null);
            }
        }

        return $this;
    }

    /**
     * @return Collections\Collection<int, Date>
     */
    public function getDates(): Collections\Collection
    {
        return $this->dates;
    }

    public function addDate(Date $date): static
    {
        if (!$this->dates->contains($date)) {
            $this->dates->add($date);
            $date->setPoll($this);
        }

        return $this;
    }

    public function removeDate(Date $date): static
    {
        if ($this->dates->removeElement($date)) {
            if ($date->getPoll() === $this) {
                $date->setPoll(null);
            }
        }

        return $this;
    }

    public function getSlug(): ?string
    {
        return $this->slug;
    }

    public function setSlug(?string $slug): static
    {
        $this->slug = $slug;

        return $this;
    }

    #[ORM\PostPersist]
    public function setDefaultSlug(PostPersistEventArgs $eventArgs): void
    {
        if (!$this->slug) {
            $this->setSlug($this->getId());
            $eventArgs->getObjectManager()->flush();
        }
    }

    public function isSlugCustomized(): bool
    {
        return $this->slug !== $this->id;
    }

    public function getAdminToken(): ?string
    {
        return $this->adminToken;
    }

    #[ORM\PrePersist]
    public function setAdminToken(): void
    {
        $this->adminToken = Utils\Random::hex(20);
    }

    /**
     * @return Collections\Collection<int, Vote>
     */
    public function getVotes(): Collections\Collection
    {
        return $this->votes;
    }

    public function countVotes(): int
    {
        return $this->votes->count();
    }

    public function addVote(Vote $vote): static
    {
        if (!$this->votes->contains($vote)) {
            $this->votes->add($vote);
            $vote->setPoll($this);
        }

        return $this;
    }

    public function removeVote(Vote $vote): static
    {
        if ($this->votes->removeElement($vote)) {
            if ($vote->getPoll() === $this) {
                $vote->setPoll(null);
            }
        }

        return $this;
    }

    public function getMaxVotes(): ?int
    {
        return $this->maxVotes;
    }

    public function setMaxVotes(?int $maxVotes): static
    {
        $this->maxVotes = $maxVotes;

        return $this;
    }

    public function getMinWikimediaAccountAgeMonths(): ?int
    {
        return $this->minWikimediaAccountAgeMonths;
    }

    public function setMinWikimediaAccountAgeMonths(?int $minWikimediaAccountAgeMonths): static
    {
        $this->minWikimediaAccountAgeMonths = $minWikimediaAccountAgeMonths;

        return $this;
    }

    public function getMinWikimediaEditsProject(): ?string
    {
        return $this->minWikimediaEditsProject;
    }

    public function setMinWikimediaEditsProject(?string $minWikimediaEditsProject): static
    {
        $this->minWikimediaEditsProject = $minWikimediaEditsProject;

        return $this;
    }

    public function getMinWikimediaEditsCount(): ?int
    {
        return $this->minWikimediaEditsCount;
    }

    public function setMinWikimediaEditsCount(?int $minWikimediaEditsCount): static
    {
        $this->minWikimediaEditsCount = $minWikimediaEditsCount;

        return $this;
    }

    public function isNotifyOnVotes(): bool
    {
        return $this->notifyOnVotes;
    }

    public function setNotifyOnVotes(bool $notifyOnVotes): static
    {
        $this->notifyOnVotes = $notifyOnVotes;

        return $this;
    }

    /**
     * @return Collections\Collection<int, Comment>
     */
    public function getComments(): Collections\Collection
    {
        return $this->comments;
    }

    public function countComments(): int
    {
        return $this->comments->count();
    }

    public function addComment(Comment $comment): static
    {
        if (!$this->comments->contains($comment)) {
            $this->comments->add($comment);
            $comment->setPoll($this);
        }

        return $this;
    }

    public function removeComment(Comment $comment): static
    {
        if ($this->comments->removeElement($comment)) {
            if ($comment->getPoll() === $this) {
                $comment->setPoll(null);
            }
        }

        return $this;
    }

    public function isNotifyOnComments(): ?bool
    {
        return $this->notifyOnComments;
    }

    public function setNotifyOnComments(bool $notifyOnComments): static
    {
        $this->notifyOnComments = $notifyOnComments;

        return $this;
    }

    public function isClosed(): bool
    {
        if (!$this->closedAt) {
            return false;
        }

        return Utils\Time::relative('today') > $this->closedAt->modify('today');
    }

    public function getClosedAt(): ?\DateTimeImmutable
    {
        return $this->closedAt;
    }

    public function setClosedAt(?\DateTimeImmutable $closedAt): static
    {
        $this->closedAt = $closedAt;

        return $this;
    }

    public function areResultsPublic(): ?bool
    {
        return $this->areResultsPublic;
    }

    public function setAreResultsPublic(bool $areResultsPublic): static
    {
        $this->areResultsPublic = $areResultsPublic;

        return $this;
    }

    public function isPubliclyListed(): bool
    {
        return $this->isPubliclyListed;
    }

    public function setIsPubliclyListed(bool $isPubliclyListed): static
    {
        $this->isPubliclyListed = $isPubliclyListed;

        return $this;
    }

    /**
     * @param Proposal[]|Collections\Collection<int, Proposal> $proposals
     *
     * @return array<array{Date, Proposal[]}>
     */
    public static function groupDateProposals(mixed $proposals): array
    {
        $datesAndChoices = [];

        foreach ($proposals as $proposal) {
            $date = $proposal->getDate();

            if (!$date || !$date->getValue()) {
                throw new \LogicException('Expecting a "date" proposal, but date is not set');
            }

            $dateKey = $date->getValue()->format('Y-m-d');

            if (!isset($datesAndChoices[$dateKey])) {
                $datesAndChoices[$dateKey] = [$date, []];
            }

            $datesAndChoices[$dateKey][1][] = $proposal;
        }

        foreach ($datesAndChoices as $key => $dateAndProposals) {
            $proposals = $dateAndProposals[1];
            usort($proposals, function (Proposal $proposal1, Proposal $proposal2): int {
                return $proposal1->getId() <=> $proposal2->getId();
            });

            $datesAndChoices[$key][1] = $proposals;
        }

        ksort($datesAndChoices);

        return $datesAndChoices;
    }

    public function getEditVoteMode(): ?string
    {
        return $this->editVoteMode;
    }

    public function setEditVoteMode(string $editVoteMode): static
    {
        $this->editVoteMode = $editVoteMode;

        return $this;
    }

    public static function translateEditVoteMode(string $value): TranslatableMessage
    {
        if ($value === 'own') {
            return new TranslatableMessage('polls.edit_vote_mode.own');
        } elseif ($value === 'no') {
            return new TranslatableMessage('polls.edit_vote_mode.no');
        } elseif ($value === 'any') {
            return new TranslatableMessage('polls.edit_vote_mode.any');
        } else {
            throw new \LogicException("Cannot translate edit vote mode {$value}");
        }
    }

    public function isDisableMaybe(): bool
    {
        return $this->disableMaybe;
    }

    public function setDisableMaybe(bool $disableMaybe): static
    {
        $this->disableMaybe = $disableMaybe;

        return $this;
    }

    public function isVoteNoByDefault(): bool
    {
        return $this->voteNoByDefault;
    }

    public function setVoteNoByDefault(bool $voteNoByDefault): static
    {
        $this->voteNoByDefault = $voteNoByDefault;

        return $this;
    }
}
