<?php

declare(strict_types=1);

namespace App\Identity\Presentation\Command;

use App\Identity\Application\SuperAdmin\SuperAdminContext;
use App\Identity\Domain\Entity\AuditLog;
use App\Identity\Domain\Repository\AuditLogRepositoryInterface;
use App\Identity\Domain\Repository\RoleRepositoryInterface;
use App\Identity\Domain\Repository\UserRepositoryInterface;
use App\Shared\Domain\Repository\TenantRepositoryInterface;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use InvalidArgumentException;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Uid\Uuid;

/**
 * RBAC-P3-014 (#677) — break-glass CLI assigning the `tenant_owner`
 * role to a user inside a target tenant. The command is the recovery
 * path for the scenarios documented in
 * `docs/operations/break-glass-runbook.md` (TBD, Phase 5):
 *
 *   - the only Tenant Owner left the company without handoff,
 *   - the only Tenant Owner is locked out (password reset email broken),
 *   - MFA lockout on the only Owner.
 *
 * Workflow:
 *
 *   1. Resolve target user by email + tenant by slug.
 *   2. Activate Super Admin cross-tenant mode via
 *      {@see SuperAdminContext} (disables `tenant_filter` for the
 *      duration of the rescue).
 *   3. Look up the seeded `tenant_owner` role for the target tenant
 *      (per-tenant rows seeded by `cortex:tenant:seed-roles`).
 *   4. Add the role to the user, persist, audit with
 *      `special_flags=["SUPER_ADMIN_RECOVERY"]` and
 *      `cross_tenant_access=true`.
 *
 * Guard rails deferred to follow-up (`--mfa-totp` argument scaffolded
 * but verification path lands once the Super Admin MFA secret + TOTP
 * verifier integrate; rate limit `5/24h` lands with audit query plus
 * a Symfony RateLimiter binding):
 *
 *   - Interactive TOTP prompt + verification against the platform
 *     Super Admin's `mfa_secret` (Phase 5 #712 break-glass UI also
 *     wires this).
 *   - Rate limit lookup against `audit_logs` where
 *     `special_flags @> '["SUPER_ADMIN_RECOVERY"]'` AND
 *     `created_at > NOW() - INTERVAL '24 hours'`.
 *
 * The command always audits the attempt — even failed ones — so
 * forensic traceability is complete.
 */
#[AsCommand(
    name: 'cortex:rescue-admin',
    description: 'Break-glass: assign tenant_owner role to a user (skipping permission stack). Cross-tenant Super Admin only.',
)]
final class RescueAdminCommand extends Command
{
    public function __construct(
        private readonly UserRepositoryInterface $users,
        private readonly RoleRepositoryInterface $roles,
        private readonly TenantRepositoryInterface $tenants,
        private readonly AuditLogRepositoryInterface $auditLog,
        private readonly SuperAdminContext $superAdminContext,
        private readonly EntityManagerInterface $entityManager,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('email', InputArgument::REQUIRED, 'Target user email')
            ->addArgument('tenant-slug', InputArgument::REQUIRED, 'Target tenant code (slug)')
            ->addOption('super-admin-id', null, InputOption::VALUE_REQUIRED, 'Super Admin UUID performing the rescue (for audit trail). Required.')
            ->addOption('reason', null, InputOption::VALUE_REQUIRED, 'Reason text recorded in the audit log entry.', 'break-glass recovery');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        /** @var string $email */
        $email = $input->getArgument('email');
        /** @var string $tenantSlug */
        $tenantSlug = $input->getArgument('tenant-slug');
        $superAdminIdString = $input->getOption('super-admin-id');
        /** @var string $reason */
        $reason = $input->getOption('reason');

        if (!\is_string($superAdminIdString) || '' === $superAdminIdString) {
            $io->error('--super-admin-id is required so the audit log can attribute the recovery action.');

            return Command::FAILURE;
        }

        try {
            $superAdminId = Uuid::fromString($superAdminIdString);
        } catch (InvalidArgumentException) {
            $io->error('--super-admin-id must be a valid UUID.');

            return Command::FAILURE;
        }

        return $this->superAdminContext->runCrossTenant(
            $superAdminId,
            function () use ($email, $tenantSlug, $superAdminId, $reason, $io): int {
                $tenant = $this->tenants->findByCode($tenantSlug);
                if (null === $tenant) {
                    $io->error(\sprintf('Tenant slug "%s" not found.', $tenantSlug));
                    $this->recordFailedRescue($superAdminId, $email, $tenantSlug, $reason, 'tenant_not_found');

                    return Command::FAILURE;
                }

                $user = $this->users->findByEmail($email);
                if (null === $user) {
                    $io->error(\sprintf('User "%s" not found.', $email));
                    $this->recordFailedRescue($superAdminId, $email, $tenantSlug, $reason, 'user_not_found');

                    return Command::FAILURE;
                }

                if ($user->getTenant()->getId()->toRfc4122() !== $tenant->getId()->toRfc4122()) {
                    $io->error('User does not belong to the target tenant. Cross-tenant user moves are out of scope for break-glass.');
                    $this->recordFailedRescue($superAdminId, $email, $tenantSlug, $reason, 'user_tenant_mismatch');

                    return Command::FAILURE;
                }

                $role = $this->roles->findByCode('tenant_owner', $tenant);
                if (null === $role) {
                    $io->error('tenant_owner role not seeded for this tenant. Run cortex:tenant:seed-roles first.');
                    $this->recordFailedRescue($superAdminId, $email, $tenantSlug, $reason, 'role_not_seeded');

                    return Command::FAILURE;
                }

                $user->addRole($role);
                $this->entityManager->flush();

                $audit = new AuditLog(
                    id: Uuid::v7(),
                    tenantId: $tenant->getId(),
                    userId: $user->getId(),
                    superAdminId: $superAdminId,
                    action: 'rescue_admin',
                    resourceType: 'cortex:rescue-admin',
                    resourceId: $user->getId()->toRfc4122(),
                    oldValue: null,
                    newValue: ['role_added' => 'tenant_owner', 'reason' => $reason],
                    permissionCheckResult: 'super_admin_bypass',
                    crossTenantAccess: true,
                    specialFlags: ['SUPER_ADMIN_RECOVERY'],
                    ipAddress: null,
                    userAgent: 'cli:cortex:rescue-admin',
                    createdAt: new DateTimeImmutable(),
                );
                $this->auditLog->save($audit);

                $io->success(\sprintf(
                    'Granted tenant_owner role to %s in tenant "%s". Audit entry %s recorded.',
                    $email,
                    $tenantSlug,
                    $audit->getId()->toRfc4122(),
                ));

                return Command::SUCCESS;
            },
        );
    }

    private function recordFailedRescue(
        Uuid $superAdminId,
        string $email,
        string $tenantSlug,
        string $reason,
        string $failureReason,
    ): void {
        $audit = new AuditLog(
            id: Uuid::v7(),
            tenantId: null,
            userId: null,
            superAdminId: $superAdminId,
            action: 'rescue_admin',
            resourceType: 'cortex:rescue-admin',
            resourceId: null,
            oldValue: null,
            newValue: ['target_email' => $email, 'target_tenant' => $tenantSlug, 'reason' => $reason, 'failure' => $failureReason],
            permissionCheckResult: 'denied',
            crossTenantAccess: true,
            specialFlags: ['SUPER_ADMIN_RECOVERY', 'RESCUE_FAILED'],
            ipAddress: null,
            userAgent: 'cli:cortex:rescue-admin',
            createdAt: new DateTimeImmutable(),
        );
        $this->auditLog->save($audit);
    }
}
