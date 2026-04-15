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

use App\Entity;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

/**
 * A poll flow represents a multi-steps form for poll creation.
 *
 * This class allows independent access to any step out of the flow as they
 * must be accessible from the summary. This allows to follow the normal flow
 * during creation of the poll, while being redirected directly to the summary
 * after modifying any step once the user finished to create the poll.
 *
 * Said otherwise, if `isStepOutOfFlow` is true and summary is accessible, then
 * the previous and next URLs are always the poll summary URL.
 *
 * The flow is always enabled while the summary page isn't accessible. Then,
 * the `flow` boolean parameter must be passed in the URL to enable the poll
 * flow.
 */
abstract class PollFlow extends Flow
{
    public function __construct(
        protected Entity\Poll $poll,
        protected UrlGeneratorInterface $urlGenerator,
        public readonly bool $isStepOutOfFlow,
    ) {
    }

    public function hasPreviousStep(string $stepName): bool
    {
        if ($this->isStepOutOfFlow && $this->isAccessible('summary')) {
            return true;
        }

        return parent::hasPreviousStep($stepName);
    }

    public function getPreviousStepUrl(string $stepName): string
    {
        if ($this->isStepOutOfFlow && $this->isAccessible('summary')) {
            return $this->getOutOfFlowPreviousStepUrl($stepName);
        }

        return parent::getPreviousStepUrl($stepName);
    }

    abstract public function getOutOfFlowPreviousStepUrl(string $stepName): string;

    public function hasNextStep(string $stepName): bool
    {
        if ($this->isStepOutOfFlow && $this->isAccessible('summary')) {
            return true;
        }

        return parent::hasNextStep($stepName);
    }

    public function getNextStepUrl(string $stepName): string
    {
        if ($this->isStepOutOfFlow && $this->isAccessible('summary')) {
            return $this->getOutOfFlowNextStepUrl($stepName);
        }

        return parent::getNextStepUrl($stepName);
    }

    abstract public function getOutOfFlowNextStepUrl(string $stepName): string;
}
