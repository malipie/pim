<?php

declare(strict_types=1);

/*
 * Backwards-compatibility bridge for the Tenant aggregate, which moved out of
 * Identity into the Shared bounded context (RF-02). Cross-BC consumers still
 * type-hint App\Identity\Domain\Entity\Tenant; this alias keeps them green
 * until RF-04 rewrites every call site, at which point this file is removed.
 *
 * Because the aliased class lives in App\Shared\Domain, every existing
 * `instanceof Tenant` / `Tenant::class` check stays functionally identical:
 * PHP treats both names as the same class object after class_alias().
 */

class_alias(
    App\Shared\Domain\Tenant::class,
    App\Identity\Domain\Entity\Tenant::class,
);
