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

namespace App\Flow;

/**
 * A class helping representing multi-steps forms.
 */
abstract class Flow
{
    /** @var string[] */
    protected array $steps = [];

    /**
     * Return whether if the requirements of the step are met or not.
     */
    abstract public function checkStep(string $stepName): bool;

    /**
     * Return the URL of the step.
     */
    abstract public function getStepUrl(string $stepName): string;

    /**
     * Return whether the step is accessible or not.
     *
     * A step is accessible if all the previous steps pass the checkStep()
     * test. The first step is always accessible.
     */
    public function isAccessible(string $stepName): bool
    {
        foreach ($this->steps as $step) {
            if ($stepName === $step) {
                return true;
            }

            if (!$this->checkStep($step)) {
                return false;
            }
        }

        throw new \LogicException("{$stepName} is an invalid step name");
    }

    /**
     * Return whether the step has a previous step or not.
     */
    public function hasPreviousStep(string $stepName): bool
    {
        $stepNumber = $this->getStepNumber($stepName);
        return $stepNumber > 1;
    }

    /**
     * Return the URL of the step before the given one.
     */
    public function getPreviousStepUrl(string $stepName): string
    {
        $stepIndex = $this->getStepNumber($stepName) - 1;

        if ($stepIndex === 0) {
            throw new \LogicException("{$stepName} has no previous step");
        }

        $previousStepIndex = $stepIndex - 1;
        $previousStepName = $this->steps[$previousStepIndex];
        return $this->getStepUrl($previousStepName);
    }

    /**
     * Return whether the step has a next step or not.
     */
    public function hasNextStep(string $stepName): bool
    {
        $stepNumber = $this->getStepNumber($stepName);
        return $stepNumber < $this->getTotalSteps();
    }

    /**
     * Return the URL of the step after the given one.
     */
    public function getNextStepUrl(string $stepName): string
    {
        $stepIndex = $this->getStepNumber($stepName) - 1;

        if ($stepIndex === $this->getTotalSteps()) {
            throw new \LogicException("{$stepName} has no next step");
        }

        $nextStepIndex = $stepIndex + 1;
        $nextStepName = $this->steps[$nextStepIndex];
        return $this->getStepUrl($nextStepName);
    }

    /**
     * Return the number of the step.
     */
    public function getStepNumber(string $stepName): int
    {
        $number = array_search($stepName, $this->steps);

        if (!is_int($number)) {
            throw new \LogicException("{$stepName} is an invalid step name");
        }

        return $number + 1;
    }

    /**
     * Return the total number of steps.
     */
    public function getTotalSteps(): int
    {
        // There is always a final "end" step that is not considered in the
        // interface, so we subtract 1 from the count.
        return count($this->steps) - 1;
    }
}
