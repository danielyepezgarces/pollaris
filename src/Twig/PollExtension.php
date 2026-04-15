<?php

// This file is part of Pollaris.
// Copyright 2024-2026 Marien Fressinaud
// SPDX-License-Identifier: AGPL-3.0-or-later

namespace App\Twig;

use App\Entity;
use App\Security;
use Doctrine\Common\Collections;
use Symfony\Component\Form\FormView;
use Twig\Attribute\AsTwigFilter;
use Twig\Attribute\AsTwigFunction;

class PollExtension
{
    public function __construct(
        private Security\PollSecurity $pollSecurity,
    ) {
    }

    #[AsTwigFunction('hasAccessToAdmin')]
    public function hasAccessToAdmin(Entity\Poll $poll): bool
    {
        return $this->pollSecurity->hasAccessToAdmin($poll);
    }

    #[AsTwigFunction('canViewResults')]
    public function canViewResults(Entity\Poll $poll): bool
    {
        return $this->pollSecurity->canViewResults($poll);
    }

    #[AsTwigFunction('canEditVotes')]
    public function canEditVotes(Entity\Poll $poll, bool $ignoreAdminAccess = false): bool
    {
        return $this->pollSecurity->canEditVotes($poll, $ignoreAdminAccess);
    }

    #[AsTwigFunction('canEditVote')]
    public function canEditVote(Entity\Vote $vote, bool $ignoreAdminAccess = false): bool
    {
        return $this->pollSecurity->canEditVote($vote, $ignoreAdminAccess);
    }

    /**
     * Return an AnswerForm corresponding to a proposal in the given VoteForm.
     */
    #[AsTwigFilter('getAnswerFormForProposal')]
    public function getAnswerFormForProposal(FormView $voteForm, Entity\Proposal $proposal): FormView
    {
        if (!isset($voteForm->children['answers'])) {
            throw new \LogicException('Expected a VoteForm in argument.');
        }

        $answers = $voteForm->children['answers'];

        foreach ($answers->children as $childFormView) {
            if (!isset($childFormView->children['value'])) {
                continue;
            }

            $valueForm = $childFormView->children['value'];

            if (
                !isset($valueForm->vars['attr']['data-proposal-id']) ||
                $valueForm->vars['attr']['data-proposal-id'] !== $proposal->getId()
            ) {
                continue;
            }

            return $childFormView;
        }

        throw new \LogicException('Proposal not found');
    }

    /**
     * @param Entity\Proposal[]|Collections\Collection<int, Entity\Proposal> $proposals
     *
     * @return array<array{Entity\Date, Entity\Proposal[]}>
     */
    #[AsTwigFilter('groupDateProposals')]
    public function groupDateProposals(mixed $proposals): array
    {
        return Entity\Poll::groupDateProposals($proposals);
    }

    /**
     * @param Entity\Answer[]|Collections\Collection<int, Entity\Answer> $answers
     *
     * @return array<string, Entity\Answer[]>
     */
    #[AsTwigFilter('groupAnswersByValues')]
    public function groupAnswersByValues(mixed $answers): array
    {
        if ($answers instanceof Collections\Collection) {
            $answers = $answers->toArray();
        }

        $answersByValues = [
            'yes' => [],
            'maybe' => [],
            'no' => [],
        ];

        foreach ($answers as $answer) {
            $answersByValues[$answer->getValue()][] = $answer;
        }

        return $answersByValues;
    }

    /**
     * @template T of mixed
     *
     * @param array<T[]> $arrays
     * @return T[]
     */
    #[AsTwigFilter('flatten')]
    public function flatten(array $arrays): array
    {
        $result = [];

        foreach ($arrays as $array) {
            $result = array_merge($result, $array);
        }

        return $result;
    }
}
