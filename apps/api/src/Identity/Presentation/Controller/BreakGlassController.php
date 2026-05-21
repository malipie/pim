<?php

declare(strict_types=1);

namespace App\Identity\Presentation\Controller;

use App\Identity\Application\SuperAdmin\SuperAdminContext;
use App\Identity\Application\TotpEnrolmentService;
use App\Identity\Contracts\Attribute\RequiresPermission;
use App\Identity\Domain\Entity\AuditLog;
use App\Identity\Domain\Entity\User;
use App\Identity\Domain\Rbac\RbacMatrix;
use App\Identity\Domain\Repository\AuditLogRepositoryInterface;
use App\Identity\Domain\Repository\RoleRepositoryInterface;
use App\Identity\Domain\Repository\UserRepositoryInterface;
use App\Shared\Domain\Repository\TenantRepositoryInterface;
use DateTimeImmutable;
use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Uid\Uuid;

/**
 * RBAC-P5-022 (#712) — HTTP twin of the `cortex:rescue-admin` CLI from
 * #677. Lets a Super Admin grant the `tenant_owner` role to a user
 * inside a target tenant when the existing owners are locked out.
 *
 * Endpoints (both gated by `super_admin` role check):
 *
 *   - GET  /api/admin/break-glass/usage
 *       Rate-limit status. Returns `{used, limit, remaining,
 *       reset_at, recent_invocations[]}`. `recent_invocations` lists
 *       the last 5 audit log entries this Super Admin produced via
 *       break-glass within the last 24h (timestamp + target user +
 *       outcome).
 *
 *   - POST /api/admin/break-glass
 *       Body: `{tenant_code, user_email, reason, mfa_totp}`.
 *       Flow:
 *         1. Verify caller's TOTP code via {@see TotpEnrolmentService}.
 *         2. Check rate limit (5 invocations / 24h / Super Admin) by
 *            counting matching audit_logs rows.
 *         3. Resolve target tenant + user; refuse cross-tenant moves.
 *         4. Wrap the rescue in
 *            {@see SuperAdminContext::runCrossTenant()} so the
 *            TenantFilter is disabled exactly for the duration.
 *         5. Assign `tenant_owner` role (skipping permission stack),
 *            persist, audit with `special_flags=["SUPER_ADMIN_RECOVERY"]`
 *            and `cross_tenant_access=true`.
 *
 * Every attempt — success OR failure — is audited; failed attempts
 * still count against the rate-limit budget to prevent brute force.
 *
 * Mirrors the existing CLI step-by-step so the audit trail is
 * indistinguishable; operators can pick whichever interface is more
 * convenient (UI for typical recovery, CLI for emergency-without-UI).
 */
final readonly class BreakGlassController
{
    private const int DAILY_LIMIT = 5;
    private const string SPECIAL_FLAG = 'SUPER_ADMIN_RECOVERY';
    private const string OWNER_ROLE_CODE = 'tenant_owner';

    public function __construct(
        private Security $security,
        private TotpEnrolmentService $totp,
        private SuperAdminContext $superAdminContext,
        private UserRepositoryInterface $users,
        private RoleRepositoryInterface $roles,
        private TenantRepositoryInterface $tenants,
        private AuditLogRepositoryInterface $auditLog,
        private EntityManagerInterface $entityManager,
        private Connection $connection,
    ) {
    }

    #[Route(path: '/api/admin/break-glass/usage', methods: ['GET'], name: 'api_admin_break_glass_usage')]
    #[RequiresPermission(module: 'user', action: 'admin')]
    public function usage(): JsonResponse
    {
        $caller = $this->requireSuperAdmin();
        if ($caller instanceof JsonResponse) {
            return $caller;
        }

        $used = $this->countRecentInvocations($caller->getId());
        $recent = $this->listRecentInvocations($caller->getId());

        return new JsonResponse([
            'used' => $used,
            'limit' => self::DAILY_LIMIT,
            'remaining' => max(0, self::DAILY_LIMIT - $used),
            'window_hours' => 24,
            'recent_invocations' => $recent,
        ]);
    }

    #[Route(path: '/api/admin/break-glass', methods: ['POST'], name: 'api_admin_break_glass_invoke')]
    #[RequiresPermission(module: 'user', action: 'admin')]
    public function invoke(Request $request): JsonResponse
    {
        $caller = $this->requireSuperAdmin();
        if ($caller instanceof JsonResponse) {
            return $caller;
        }

        $payload = $this->decode($request);
        if ($payload instanceof JsonResponse) {
            return $payload;
        }

        $tenantCode = self::pickString($payload, 'tenant_code');
        $userEmail = self::pickString($payload, 'user_email');
        $reason = self::pickString($payload, 'reason');
        $mfaTotp = self::pickString($payload, 'mfa_totp');

        if (null === $tenantCode || null === $userEmail || null === $reason || null === $mfaTotp) {
            return $this->problem(
                Response::HTTP_BAD_REQUEST,
                'Missing fields',
                '`tenant_code`, `user_email`, `reason`, and `mfa_totp` are all required.',
            );
        }

        if (mb_strlen($reason) < 10) {
            return $this->problem(
                Response::HTTP_BAD_REQUEST,
                'Reason too short',
                'Provide at least 10 characters describing why this break-glass is necessary — the reason is permanently audited.',
            );
        }

        // Rate limit check BEFORE MFA verify so an attacker can't trigger
        // unlimited TOTP guessing rounds for free; failed attempts also
        // count to keep the budget realistic.
        $used = $this->countRecentInvocations($caller->getId());
        if ($used >= self::DAILY_LIMIT) {
            $this->recordAttempt($caller->getId(), null, null, $tenantCode, $userEmail, $reason, 'rate_limit_exceeded', 'denied');

            return $this->problem(
                Response::HTTP_TOO_MANY_REQUESTS,
                'Rate limit reached',
                \sprintf('Super Admin break-glass capped at %d invocations per 24h. Existing budget refills as old entries age out.', self::DAILY_LIMIT),
                ['code' => 'rate_limit_exceeded'],
            );
        }

        // MFA verify — must be enabled on the Super Admin account and
        // the submitted code (TOTP or backup) must match.
        if (!$caller->isTotpEnabled()) {
            $this->recordAttempt($caller->getId(), null, null, $tenantCode, $userEmail, $reason, 'mfa_not_enrolled', 'denied');

            return $this->problem(
                Response::HTTP_PRECONDITION_REQUIRED,
                'MFA required',
                'Enable two-factor authentication on your account before using break-glass.',
                ['code' => 'mfa_required'],
            );
        }

        if (!$this->totp->verify($caller, $mfaTotp)) {
            $this->recordAttempt($caller->getId(), null, null, $tenantCode, $userEmail, $reason, 'mfa_invalid', 'denied');

            return $this->problem(
                Response::HTTP_UNPROCESSABLE_ENTITY,
                'Invalid MFA code',
                'The TOTP / backup code did not match. This attempt counts against the 24h budget.',
                ['code' => 'mfa_invalid'],
            );
        }

        // Cross-tenant lookup + role assignment via SuperAdminContext —
        // mirrors RescueAdminCommand exactly so the audit story stays
        // identical between CLI and UI.
        $result = $this->superAdminContext->runCrossTenant(
            $caller->getId(),
            function () use ($caller, $tenantCode, $userEmail, $reason): JsonResponse {
                $tenant = $this->tenants->findByCode($tenantCode);
                if (null === $tenant) {
                    $this->recordAttempt($caller->getId(), null, null, $tenantCode, $userEmail, $reason, 'tenant_not_found', 'denied');

                    return $this->problem(
                        Response::HTTP_NOT_FOUND,
                        'Tenant not found',
                        \sprintf('No tenant matches code `%s`.', $tenantCode),
                        ['code' => 'tenant_not_found'],
                    );
                }

                $target = $this->users->findByEmail($userEmail);
                if (null === $target) {
                    $this->recordAttempt($caller->getId(), $tenant->getId(), null, $tenantCode, $userEmail, $reason, 'user_not_found', 'denied');

                    return $this->problem(
                        Response::HTTP_NOT_FOUND,
                        'User not found',
                        \sprintf('No user matches email `%s`.', $userEmail),
                        ['code' => 'user_not_found'],
                    );
                }

                if (!$target->getTenant()->getId()->equals($tenant->getId())) {
                    $this->recordAttempt($caller->getId(), $tenant->getId(), $target->getId(), $tenantCode, $userEmail, $reason, 'user_tenant_mismatch', 'denied');

                    return $this->problem(
                        Response::HTTP_CONFLICT,
                        'User outside target tenant',
                        'The user does not belong to the target tenant. Cross-tenant user moves are out of scope for break-glass.',
                        ['code' => 'user_tenant_mismatch'],
                    );
                }

                $role = $this->roles->findByCode(self::OWNER_ROLE_CODE, $tenant);
                if (null === $role) {
                    $this->recordAttempt($caller->getId(), $tenant->getId(), $target->getId(), $tenantCode, $userEmail, $reason, 'role_not_seeded', 'denied');

                    return $this->problem(
                        Response::HTTP_CONFLICT,
                        'Owner role missing',
                        '`tenant_owner` role is not seeded for this tenant. Run cortex:tenant:seed-roles first.',
                        ['code' => 'role_not_seeded'],
                    );
                }

                $target->addRole($role);
                $this->entityManager->flush();
                $auditId = $this->recordAttempt(
                    $caller->getId(),
                    $tenant->getId(),
                    $target->getId(),
                    $tenantCode,
                    $userEmail,
                    $reason,
                    'success',
                    'super_admin_bypass',
                );

                return new JsonResponse([
                    'audit_id' => $auditId,
                    'tenant' => ['id' => $tenant->getId()->toRfc4122(), 'code' => $tenant->getCode()],
                    'user' => ['id' => $target->getId()->toRfc4122(), 'email' => $target->getEmail()],
                    'role_assigned' => self::OWNER_ROLE_CODE,
                ]);
            },
        );

        return $result;
    }

    /**
     * Returns the caller's User aggregate on success, or a 403 problem
     * response when they do not hold the `super_admin` role.
     */
    private function requireSuperAdmin(): User|JsonResponse
    {
        $user = $this->security->getUser();
        if (!$user instanceof User) {
            return $this->problem(Response::HTTP_UNAUTHORIZED, 'Unauthorized', 'No authenticated user.');
        }

        foreach ($user->getAssignedRoles() as $role) {
            if (RbacMatrix::ROLE_SUPER_ADMIN === $role->getCode()) {
                return $user;
            }
        }

        return $this->problem(
            Response::HTTP_FORBIDDEN,
            'Forbidden',
            'Super Admin role required.',
            ['code' => 'super_admin_required'],
        );
    }

    private function countRecentInvocations(Uuid $superAdminId): int
    {
        $sql = <<<'SQL'
            SELECT COUNT(*)
            FROM audit_logs
            WHERE super_admin_id = :super_admin_id
              AND special_flags::jsonb @> :flag::jsonb
              AND created_at > NOW() - INTERVAL '24 hours'
            SQL;
        $count = $this->connection->fetchOne($sql, [
            'super_admin_id' => $superAdminId->toRfc4122(),
            'flag' => \sprintf('["%s"]', self::SPECIAL_FLAG),
        ]);

        return \is_numeric($count) ? (int) $count : 0;
    }

    /**
     * @return list<array{audit_id: string, created_at: string, target_user: ?string, target_tenant: ?string, outcome: string}>
     */
    private function listRecentInvocations(Uuid $superAdminId): array
    {
        $sql = <<<'SQL'
            SELECT id, created_at, new_value, permission_check_result
            FROM audit_logs
            WHERE super_admin_id = :super_admin_id
              AND special_flags::jsonb @> :flag::jsonb
              AND created_at > NOW() - INTERVAL '24 hours'
            ORDER BY created_at DESC
            LIMIT 5
            SQL;
        $rows = $this->connection->fetchAllAssociative($sql, [
            'super_admin_id' => $superAdminId->toRfc4122(),
            'flag' => \sprintf('["%s"]', self::SPECIAL_FLAG),
        ]);

        $out = [];
        foreach ($rows as $row) {
            $newValue = null;
            if (isset($row['new_value']) && \is_string($row['new_value'])) {
                $decoded = json_decode($row['new_value'], true);
                if (\is_array($decoded)) {
                    $newValue = $decoded;
                }
            }
            $auditId = $row['id'] ?? '';
            $createdAt = $row['created_at'] ?? '';
            $outcome = $row['permission_check_result'] ?? '';
            $out[] = [
                'audit_id' => \is_string($auditId) ? $auditId : '',
                'created_at' => \is_string($createdAt) ? $createdAt : '',
                'target_user' => \is_string($newValue['target_email'] ?? null) ? $newValue['target_email'] : null,
                'target_tenant' => \is_string($newValue['target_tenant'] ?? null) ? $newValue['target_tenant'] : null,
                'outcome' => \is_string($outcome) ? $outcome : '',
            ];
        }

        return $out;
    }

    private function recordAttempt(
        Uuid $superAdminId,
        ?Uuid $tenantId,
        ?Uuid $userId,
        string $tenantCode,
        string $userEmail,
        string $reason,
        string $outcome,
        string $permissionCheckResult,
    ): string {
        $entry = new AuditLog(
            id: Uuid::v7(),
            tenantId: $tenantId,
            userId: $userId,
            superAdminId: $superAdminId,
            action: 'rescue_admin',
            resourceType: 'api:admin:break-glass',
            resourceId: $userId?->toRfc4122(),
            oldValue: null,
            newValue: [
                'target_email' => $userEmail,
                'target_tenant' => $tenantCode,
                'reason' => $reason,
                'outcome' => $outcome,
            ],
            permissionCheckResult: $permissionCheckResult,
            crossTenantAccess: true,
            specialFlags: [self::SPECIAL_FLAG],
            ipAddress: null,
            userAgent: 'http:api:admin:break-glass',
            createdAt: new DateTimeImmutable(),
        );
        $this->auditLog->save($entry);

        return $entry->getId()->toRfc4122();
    }

    /**
     * @return array<string, mixed>|JsonResponse
     */
    private function decode(Request $request): array|JsonResponse
    {
        /** @var array<string, mixed>|null $payload */
        $payload = json_decode($request->getContent(), true);
        if (!\is_array($payload)) {
            return $this->problem(Response::HTTP_BAD_REQUEST, 'Bad Request', 'Request body must be JSON.');
        }

        return $payload;
    }

    /**
     * @param array<string, mixed> $payload
     */
    private static function pickString(array $payload, string $key): ?string
    {
        $value = $payload[$key] ?? null;
        if (!\is_string($value)) {
            return null;
        }
        $value = trim($value);

        return '' === $value ? null : $value;
    }

    /**
     * @param array<string, mixed> $extras
     */
    private function problem(int $status, string $title, string $detail, array $extras = []): JsonResponse
    {
        return new JsonResponse(
            array_merge(
                [
                    'type' => 'about:blank',
                    'title' => $title,
                    'status' => $status,
                    'detail' => $detail,
                ],
                $extras,
            ),
            $status,
            ['Content-Type' => 'application/problem+json; charset=utf-8'],
        );
    }
}
