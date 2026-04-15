<?php

// This file is part of Pollaris.
// Copyright 2024-2026 Marien Fressinaud
// SPDX-License-Identifier: AGPL-3.0-or-later

namespace App\Flow;

use App\Entity;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

class PollFlowBuilder
{
    public function __construct(
        private RequestStack $requestStack,
        private UrlGeneratorInterface $urlGenerator,
    ) {
    }

    public function build(Entity\Poll $poll): Flow
    {
        $request = $this->requestStack->getCurrentRequest();

        if (!$request) {
            throw new \LogicException('Flow can only be built in a HTTP request context.');
        }

        $isStepOutOfFlow = !$request->query->getBoolean('flow');

        if ($poll->isClassicPoll()) {
            return new ClassicPollFlow($poll, $this->urlGenerator, $isStepOutOfFlow);
        } else {
            return new DatePollFlow($poll, $this->urlGenerator, $isStepOutOfFlow);
        }
    }
}
