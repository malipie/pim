<?php

declare(strict_types=1);

namespace App\Tests\Unit\Channel\Application\Locale;

use App\Channel\Application\Locale\LocaleFallbackCycleDetector;
use App\Channel\Domain\Entity\Locale;
use App\Channel\Domain\Entity\TenantLocale;
use App\Channel\Domain\Repository\TenantLocaleRepositoryInterface;
use App\Shared\Domain\Tenant;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class LocaleFallbackCycleDetectorTest extends TestCase
{
    #[Test]
    public function selfFallbackIsACycle(): void
    {
        $tenant = new Tenant('demo', 'Demo');
        $repo = $this->stubRepo([]);
        $detector = new LocaleFallbackCycleDetector($repo);

        self::assertTrue($detector->wouldCreateCycle('pl_PL', 'pl_PL', $tenant));
    }

    #[Test]
    public function nullFallbackIsNeverACycle(): void
    {
        $tenant = new Tenant('demo', 'Demo');
        $repo = $this->stubRepo([]);
        $detector = new LocaleFallbackCycleDetector($repo);

        self::assertFalse($detector->wouldCreateCycle('pl_PL', null, $tenant));
        self::assertFalse($detector->wouldCreateCycle('pl_PL', '', $tenant));
    }

    #[Test]
    public function twoCycleIsRejected(): void
    {
        // en_US currently has fallback=pl_PL. Setting pl_PL.fallback=en_US
        // would create pl_PL → en_US → pl_PL.
        $tenant = new Tenant('demo', 'Demo');
        $pl = new Locale('pl_PL', 'Polski');
        $en = new Locale('en_US', 'English');

        $plRow = new TenantLocale($pl, true, true, null, 0, $tenant);
        $enRow = new TenantLocale($en, false, true, $pl, 1, $tenant);

        $repo = $this->stubRepo(['pl_PL' => $plRow, 'en_US' => $enRow]);
        $detector = new LocaleFallbackCycleDetector($repo);

        self::assertTrue($detector->wouldCreateCycle('pl_PL', 'en_US', $tenant));
    }

    #[Test]
    public function threeCycleIsRejected(): void
    {
        // a → b, b → c. Proposing c → a creates a → b → c → a.
        $tenant = new Tenant('demo', 'Demo');
        $a = new Locale('a_AA', 'A');
        $b = new Locale('b_BB', 'B');
        $c = new Locale('c_CC', 'C');

        $aRow = new TenantLocale($a, true, true, $b, 0, $tenant);
        $bRow = new TenantLocale($b, false, false, $c, 1, $tenant);
        $cRow = new TenantLocale($c, false, false, null, 2, $tenant);

        $repo = $this->stubRepo(['a_AA' => $aRow, 'b_BB' => $bRow, 'c_CC' => $cRow]);
        $detector = new LocaleFallbackCycleDetector($repo);

        self::assertTrue($detector->wouldCreateCycle('c_CC', 'a_AA', $tenant));
    }

    #[Test]
    public function newChainWithoutCycleIsAllowed(): void
    {
        $tenant = new Tenant('demo', 'Demo');
        $pl = new Locale('pl_PL', 'Polski');
        $en = new Locale('en_US', 'English');
        $de = new Locale('de_DE', 'Deutsch');

        $plRow = new TenantLocale($pl, true, true, null, 0, $tenant);
        $enRow = new TenantLocale($en, false, true, $pl, 1, $tenant);
        $deRow = new TenantLocale($de, false, false, null, 2, $tenant);

        $repo = $this->stubRepo(['pl_PL' => $plRow, 'en_US' => $enRow, 'de_DE' => $deRow]);
        $detector = new LocaleFallbackCycleDetector($repo);

        self::assertFalse($detector->wouldCreateCycle('de_DE', 'en_US', $tenant));
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
                return array_values($this->rows);
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
