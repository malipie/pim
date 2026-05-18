<?php

declare(strict_types=1);

namespace App\Identity\Presentation\Command;

use App\Identity\Domain\Entity\Role;
use App\Identity\Domain\Rbac\PrdRoleTemplates;
use App\Identity\Domain\Repository\PermissionRepositoryInterface;
use App\Identity\Domain\Repository\RoleRepositoryInterface;
use App\Shared\Domain\Tenant;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * RBAC-P1-007 (#646) — seed the 8 PRD-PIM-rbac §3.2 tenant-level role
 * templates (tenant_owner / admin / catalog_manager / marketing / modeler /
 * integration_manager / channel_manager / approver / viewer) into a target
 * tenant.
 *
 * Run after Phase 1 deploy for each existing tenant; Phase 2 wires a
 * Doctrine `OnTenantCreatedListener` (#653 territory) that invokes this
 * service automatically for every new tenant.
 *
 * Idempotent: existing rows matched by (tenant_id, code) are kept; the
 * command only fills the gap. Permission attachments are diffed — if a
 * role gains a new permission via a PRD update, re-run picks it up.
 */
#[AsCommand(
    name: 'cortex:tenant:seed-roles',
    description: 'Seed the 8 PRD-PIM-rbac §3.2 tenant-level role templates for a target tenant',
)]
final class SeedTenantPrdRolesCommand extends Command
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly RoleRepositoryInterface $roleRepository,
        private readonly PermissionRepositoryInterface $permissionRepository,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addArgument('tenant-id', InputArgument::REQUIRED, 'UUID of the tenant to seed roles for');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        /** @var string $tenantId */
        $tenantId = $input->getArgument('tenant-id');

        $tenant = $this->em->find(Tenant::class, $tenantId);
        if (!$tenant instanceof Tenant) {
            $io->error(\sprintf('Tenant %s not found.', $tenantId));

            return Command::FAILURE;
        }

        $io->title(\sprintf('Seeding PRD role templates for tenant %s (%s)', $tenant->getCode(), $tenantId));

        $rolePermissions = PrdRoleTemplates::tenantRolePermissions();
        $roleNames = PrdRoleTemplates::tenantRoleNames();

        $created = 0;
        $updated = 0;
        $missingPermissions = [];

        foreach ($rolePermissions as $code => $permissionCodes) {
            $role = $this->roleRepository->findByCode($code, $tenant);
            if (null === $role) {
                $role = new Role(
                    code: $code,
                    name: $roleNames[$code],
                    tenant: $tenant,
                );
                $this->em->persist($role);
                ++$created;
            } else {
                ++$updated;
            }

            $existingPermissionCodes = [];
            foreach ($role->getPermissions() as $existing) {
                $existingPermissionCodes[] = $existing->getCode();
            }

            foreach ($permissionCodes as $permissionCode) {
                if (\in_array($permissionCode, $existingPermissionCodes, true)) {
                    continue;
                }
                $permission = $this->permissionRepository->findByCode($permissionCode);
                if (null === $permission) {
                    $missingPermissions[] = $permissionCode;
                    continue;
                }
                $role->getPermissions()->add($permission);
            }
        }

        if ([] !== $missingPermissions) {
            $io->warning(\sprintf(
                'Skipped %d permission(s) not found in DB (run PrdPermissionFixtures first): %s',
                \count($missingPermissions),
                implode(', ', array_unique($missingPermissions)),
            ));
        }

        $this->em->flush();

        $io->success(\sprintf(
            'Done. Roles created: %d, updated: %d, permissions skipped: %d.',
            $created,
            $updated,
            \count($missingPermissions),
        ));

        return Command::SUCCESS;
    }
}
