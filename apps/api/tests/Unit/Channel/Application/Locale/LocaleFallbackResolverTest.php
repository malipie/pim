<?php

declare(strict_types=1);

namespace App\Tests\Unit\Channel\Application\Locale;

use App\Channel\Application\Locale\LocaleFallbackResolver;
use App\Channel\Domain\Entity\Locale;
use App\Channel\Domain\Entity\TenantLocale;
use App\Channel\Domain\Repository\TenantLocaleRepositoryInterface;
use App\Shared\Domain\Tenant;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * LOC-04 (#872) — resolver walks fallback chain, terminates on default,
 * handles missing or inactive links gracefully, and survives bad data
 * (data-level cycles) without spinning forever.
 */
final class LocaleFallbackResolverTest extends TestCase
{
    #[Test]
    public function chainTerminatesAtDefault(): void
    {
        $tenant = new Tenant('demo', 'Demo');
        $pl = new Locale('pl_PL', 'Polski');
        $en = new Locale('en_US', 'English');
        $de = new Locale('de_DE', 'Deutsch');

        $plRow = new TenantLocale($pl, true, true, null, 0, $tenant);
        $enRow = new TenantLocale($en, false, true, $pl, 1, $tenant);
        $deRow = new TenantLocale($de, false, false, $en, 2, $tenant);

        $repo = $this->stubRepo([
            'pl_PL' => $plRow,
            'en_US' => $enRow,
            'de_DE' => $deRow,
        ]);

        $resolver = new LocaleFallbackResolver($repo);
        self::assertSame(['de_DE', 'en_US', 'pl_PL'], $resolver->resolve('de_DE', $tenant));
    }

    #[Test]
    public function chainHaltsWhenFallbackIsInactive(): void
    {
        $tenant = new Tenant('demo', 'Demo');
        $en = new Locale('en_US', 'English');
        $de = new Locale('de_DE', 'Deutsch');

        // en_US is non-default so it can be deactivated.
        $enRow = new TenantLocale($en, false, true, null, 0, $tenant);
        $enRow->deactivate();

        // de_DE is the chain root; we fallback to the deactivated en_US.
        $deRow = new TenantLocale($de, false, false, $en, 1, $tenant);

        $repo = $this->stubRepo([
            'en_US' => $enRow,
            'de_DE' => $deRow,
        ]);

        $resolver = new LocaleFallbackResolver($repo);
        self::assertSame(['de_DE'], $resolver->resolve('de_DE', $tenant));
    }

    #[Test]
    public function dataLevelCycleStopsAtRepeatedNode(): void
    {
        // Constructed deliberately — production should never see this thanks
        // to the cycle detector, but the resolver must not loop.
        $tenant = new Tenant('demo', 'Demo');
        $a = new Locale('a_AA', 'A');
        $b = new Locale('b_BB', 'B');

        $aRow = new TenantLocale($a, true, true, null, 0, $tenant);
        $bRow = new TenantLocale($b, false, false, $a, 1, $tenant);
        // Force cycle by reflecting fallback back into A.
        $aRow->setFallback($b);

        $repo = $this->stubRepo(['a_AA' => $aRow, 'b_BB' => $bRow]);

        $resolver = new LocaleFallbackResolver($repo);
        $chain = $resolver->resolve('a_AA', $tenant);
        self::assertSame(['a_AA', 'b_BB'], $chain);
    }

    #[Test]
    public function pickFirstAvailableReturnsFirstHit(): void
    {
        $tenant = new Tenant('demo', 'Demo');
        $pl = new Locale('pl_PL', 'Polski');
        $en = new Locale('en_US', 'English');
        $de = new Locale('de_DE', 'Deutsch');

        $plRow = new TenantLocale($pl, true, true, null, 0, $tenant);
        $enRow = new TenantLocale($en, false, true, $pl, 1, $tenant);
        $deRow = new TenantLocale($de, false, false, $en, 2, $tenant);

        $repo = $this->stubRepo([
            'pl_PL' => $plRow,
            'en_US' => $enRow,
            'de_DE' => $deRow,
        ]);
        $resolver = new LocaleFallbackResolver($repo);

        $availableLocales = ['pl_PL', 'en_US'];
        $pick = $resolver->pickFirstAvailable('de_DE', $tenant, static fn (string $c): bool => \in_array($c, $availableLocales, true));
        self::assertSame('en_US', $pick);
    }

    #[Test]
    public function pickFirstAvailableReturnsNullWhenChainHasNoValue(): void
    {
        $tenant = new Tenant('demo', 'Demo');
        $pl = new Locale('pl_PL', 'Polski');
        $plRow = new TenantLocale($pl, true, true, null, 0, $tenant);
        $repo = $this->stubRepo(['pl_PL' => $plRow]);
        $resolver = new LocaleFallbackResolver($repo);

        $pick = $resolver->pickFirstAvailable('pl_PL', $tenant, static fn (): bool => false);
        self::assertNull($pick);
    }

    /**
     * @param array<string, TenantLocale> $rows
     */
    private function stubRepo(array $rows): TenantLocaleRepositoryInterface
    {
        return new class($rows) implements TenantLocaleRepositoryInterface {
            /**
             * @param array<string, TenantLocale> $rows
             */
            public function __construct(private readonly array $rows)
            {
            }

            public function findById(\Symfony\Component\Uid\Uuid $id): ?TenantLocale
            {
                foreach ($this->rows as $row) {
                    if ($row->getId()->equals($id)) {
                        return $row;
                    }
                }

                return null;
            }

            public function findByTenantAndLocale(Tenant $tenant, Locale $locale): ?TenantLocale
            {
                return $this->rows[$locale->getCode()] ?? null;
            }

            public function findByTenantAndCode(Tenant $tenant, string $code): ?TenantLocale
            {
                return $this->rows[$code] ?? null;
            }

            public function findActiveForTenant(Tenant $tenant): array
            {
                return array_values(array_filter($this->rows, static fn (TenantLocale $r): bool => $r->isActive()));
            }

            public function findAllForTenant(Tenant $tenant): array
            {
                return array_values($this->rows);
            }

            public function findDefaultForTenant(Tenant $tenant): ?TenantLocale
            {
                foreach ($this->rows as $row) {
                    if ($row->isDefault()) {
                        return $row;
                    }
                }

                return null;
            }

            public function save(TenantLocale $entity): void
            {
            }

            public function remove(TenantLocale $entity): void
            {
            }
        };
    }
}
