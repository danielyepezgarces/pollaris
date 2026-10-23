<?php

// This file is part of Pollaris.
// Copyright 2026 Daniel Yepez Garces
// SPDX-License-Identifier: AGPL-3.0-or-later

namespace App\EventListener;

use App\Service\MaintenanceScheduler;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

#[AsEventListener(event: 'kernel.request', priority: 10)]
class MaintenanceListener
{
    public function __construct(
        private readonly RouterInterface $router,
        private readonly TranslatorInterface $translator,
        private readonly MaintenanceScheduler $scheduler,
        private readonly string $maintenanceReadOnly = 'false',
        private readonly ?string $maintenanceSchedule = null
    ) {}

    public function onKernelRequest(RequestEvent $event): void
    {
        $isActive = ($this->maintenanceReadOnly === 'true')
            || $this->scheduler->isMaintenanceActive($this->maintenanceSchedule);

        if (!$isActive) {
            return;
        }

        $request = $event->getRequest();
        $method = $request->getMethod();

        // Check if the request is trying to write data (POST, PUT, DELETE, PATCH)
        if (in_array($method, ['POST', 'PUT', 'DELETE', 'PATCH'], true)) {
            // Check if it's an AJAX or JSON request
            if ($request->isXmlHttpRequest() || str_contains($request->headers->get('Accept', ''), 'application/json')) {
                $response = new JsonResponse([
                    'error' => $this->translator->trans('maintenance.read_only_error')
                ], 503);
                $event->setResponse($response);
                return;
            }

            // Set a flash error message and redirect back or to homepage
            $session = $request->hasSession() ? $request->getSession() : null;
            if ($session) {
                if (method_exists($session, 'getFlashBag')) {
                    $session->getFlashBag()->add('error', $this->translator->trans('maintenance.read_only_error'));
                }
            }

            $referer = $request->headers->get('referer');
            $redirectUrl = $referer ?: $this->router->generate('home');

            $event->setResponse(new RedirectResponse($redirectUrl));
        }
    }
}
