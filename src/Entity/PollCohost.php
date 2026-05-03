<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'poll_cohost')]
class PollCohost
{
    #[ORM\Id]
    #[ORM\ManyToOne(targetEntity: Poll::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?Poll $poll = null;

    #[ORM\Id]
    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?User $user = null;

    #[ORM\Column(name: 'cohost_right', type: 'string', length: 10)]
    private string $right = 'edit'; // 'edit' o 'full'

    public function getPoll(): ?Poll { return $this->poll; }
    public function setPoll(?Poll $poll): static { $this->poll = $poll; return $this; }

    public function getUser(): ?User { return $this->user; }
    public function setUser(?User $user): static { $this->user = $user; return $this; }

    public function getRight(): string { return $this->right; }
    public function setRight(string $right): static { $this->right = $right; return $this; }
}
