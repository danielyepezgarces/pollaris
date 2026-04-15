<?php

// This file is part of Pollaris.
// Copyright 2024-2026 Marien Fressinaud
// SPDX-License-Identifier: AGPL-3.0-or-later

namespace App\Flow;

use App\Entity;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

class DatePollFlow extends PollFlow
{
    /** @var string[] */
    protected array $steps = [
        'init',
        'dates',
        'slots',
        'summary',
        'end',
    ];

    public function checkStep(string $stepName): bool
    {
        if ($stepName === 'init') {
            return $this->poll->isTitleSet();
        } elseif ($stepName === 'dates') {
            return !$this->poll->getDates()->isEmpty();
        } elseif ($stepName === 'slots') {
            return !$this->poll->getProposals()->isEmpty();
        } elseif ($stepName === 'summary') {
            return $this->poll->isCompleted();
        } else {
            throw new \LogicException("{$stepName} is an invalid step name");
        }
    }

    public function getStepUrl(string $stepName): string
    {
        $routeName = match ($stepName) {
            'init' => 'edit poll',
            'dates' => 'edit poll dates',
            'slots' => 'edit poll slots',
            'summary' => 'poll summary',
            'end' => 'poll complete',
            default => throw new \LogicException("{$stepName} is an invalid step name"),
        };

        return $this->urlGenerator->generate($routeName, [
            'id' => $this->poll->getId(),
            'token' => $this->poll->getAdminToken(),
            'flow' => 'on',
        ]);
    }

    public function getOutOfFlowPreviousStepUrl(string $stepName): string
    {
        return $this->urlGenerator->generate('poll admin', [
            'id' => $this->poll->getId(),
            'token' => $this->poll->getAdminToken(),
        ]);
    }

    public function getOutOfFlowNextStepUrl(string $stepName): string
    {
        if ($stepName === 'dates') {
            return $this->urlGenerator->generate('edit poll slots', [
                'id' => $this->poll->getId(),
                'token' => $this->poll->getAdminToken(),
            ]);
        } else {
            return $this->urlGenerator->generate('poll admin', [
                'id' => $this->poll->getId(),
                'token' => $this->poll->getAdminToken(),
            ]);
        }
    }
}
