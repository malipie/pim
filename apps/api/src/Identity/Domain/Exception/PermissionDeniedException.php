<?php

declare(strict_types=1);

namespace App\Identity\Domain\Exception;

use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Throwable;

/**
 * Phase 3 RBAC-P3-001 (#664) — domain exception raised by the
 * EndpointGuardListener when an authenticated user is missing the RBAC
 * permission required by the matched controller method.
 *
 * Carries the permission code (`{module}.{action}`) so the
 * PermissionDeniedProblemListener can include it in the RFC 7807 Problem
 * Details response under `permission_required` — giving the frontend a
 * machine-readable hint about which capability is missing.
 *
 * Extends Symfony's AccessDeniedHttpException so the framework's default
 * firewall mapping still applies (HTTP 403, no leakage to the front
 * controller).
 */
final class PermissionDeniedException extends AccessDeniedHttpException
{
    public function __construct(
        public readonly string $permissionCode,
        ?Throwable $previous = null,
    ) {
        parent::__construct(
            \sprintf('Permission "%s" is required.', $permissionCode),
            $previous,
        );
    }
}
