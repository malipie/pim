<?php

declare(strict_types=1);

/*
 * PHPStan bootstrap — force-loads the legacy-FQCN bridges so static analysis
 * resolves class_alias() the same way the runtime does. Each class_exists()
 * triggers the autoloader, which executes the bridge file (e.g.
 * src/Identity/Domain/Entity/Tenant.php) and registers the alias on the
 * canonical class (App\Shared\Domain\Tenant).
 *
 * Removed alongside the runtime bridges themselves in RF-04.
 */

\class_exists(\App\Identity\Domain\Entity\Tenant::class);
\class_exists(\App\Identity\Infrastructure\Doctrine\Repository\TenantRepository::class);
