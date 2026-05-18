<?php

declare(strict_types=1);

namespace App\Identity\Infrastructure\Http;

use App\Identity\Domain\Exception\PermissionDeniedException;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * RBAC-P3-001 (#664) — converts {@see PermissionDeniedException} into an
 * RFC 7807 Problem Details JSON response so the SPA + integrators can
 * read both the human-friendly title and the machine-readable
 * `permission_required` field without parsing the body shape.
 *
 * Subscribes to `kernel.exception` with a priority that fires before
 * Symfony's stock JsonResponseExceptionListener, otherwise the framework
 * would emit its own generic 403 page first.
 */
final class PermissionDeniedProblemListener implements EventSubscriberInterface
{
    public static function getSubscribedEvents(): array
    {
        // Run before Symfony's ErrorListener (priority -128) and any
        // generic JSON exception handler — high priority guarantees we
        // win the race for permission-denied flows.
        return [KernelEvents::EXCEPTION => ['onException', 128]];
    }

    public function onException(ExceptionEvent $event): void
    {
        $throwable = $event->getThrowable();
        if (!$throwable instanceof PermissionDeniedException) {
            return;
        }

        $payload = [
            'type' => 'https://docs.pim.dev/errors/permission-denied',
            'title' => 'Permission denied',
            'status' => Response::HTTP_FORBIDDEN,
            'detail' => $throwable->getMessage(),
            'permission_required' => $throwable->permissionCode,
        ];

        $response = new JsonResponse(
            data: $payload,
            status: Response::HTTP_FORBIDDEN,
            headers: ['content-type' => 'application/problem+json'],
        );

        $event->setResponse($response);
    }
}
