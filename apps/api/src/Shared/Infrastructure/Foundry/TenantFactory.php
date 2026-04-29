<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure\Foundry;

use App\Shared\Domain\Tenant;
use Zenstruck\Foundry\Persistence\PersistentProxyObjectFactory;

/**
 * @extends PersistentProxyObjectFactory<Tenant>
 */
final class TenantFactory extends PersistentProxyObjectFactory
{
    public static function class(): string
    {
        return Tenant::class;
    }

    /**
     * @return array<string, mixed>
     */
    protected function defaults(): array
    {
        return [
            'code' => self::faker()->unique()->slug(2),
            'name' => self::faker()->company(),
        ];
    }
}
