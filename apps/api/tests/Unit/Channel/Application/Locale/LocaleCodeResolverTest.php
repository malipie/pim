<?php

declare(strict_types=1);

namespace App\Tests\Unit\Channel\Application\Locale;

use App\Channel\Application\Locale\LocaleCodeResolver;
use App\Channel\Domain\Entity\Locale;
use App\Channel\Domain\Entity\TenantLocale;
use App\Channel\Domain\Repository\TenantLocaleRepositoryInterface;
use App\Shared\Domain\Tenant;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Uid\Uuid;

final class LocaleCodeResolverTest extends TestCase
{
    #[Test]
    public function toShortDelegatesToThePureHelper(): void
    {
        $resolver = new LocaleCodeResolver($this->repo([]));

        self::assertSame('pl', $resolver->toShort('pl_PL'));
        self::assertSame('en', $resolver->toShort('en-US'));
    }

    #[Test]
    public function toBcp47ResolvesAShortCodeAgainstActiveTenantLocales(): void
    {
        $tenant = new Tenant('demo', 'Demo');
        $resolver = new LocaleCodeResolver(
            $this->repo([$this->tenantLocale('pl_PL', 'pl'), $this->tenantLocale('en_US', 'en')]),
        );

        self::assertSame('pl_PL', $resolver->toBcp47('pl', $tenant));
        self::assertSame('en_US', $resolver->toBcp47('en', $tenant));
    }

    #[Test]
    public function toBcp47AcceptsAFullCodeAndNormalisesItFirst(): void
    {
        $tenant = new Tenant('demo', 'Demo');
        $resolver = new LocaleCodeResolver($this->repo([$this->tenantLocale('pl_PL', 'pl')]));

        self::assertSame('pl_PL', $resolver->toBcp47('pl_PL', $tenant));
    }

    #[Test]
    public function toBcp47ReturnsNullWhenNoActiveLocaleMatches(): void
    {
        $tenant = new Tenant('demo', 'Demo');
        $resolver = new LocaleCodeResolver($this->repo([$this->tenantLocale('pl_PL', 'pl')]));

        self::assertNull($resolver->toBcp47('fr', $tenant));
    }

    #[Test]
    public function toBcp47ReturnsTheFirstMatchBySortOrderWhenALanguageIsAmbiguous(): void
    {
        $tenant = new Tenant('demo', 'Demo');
        // de_AT first (sortOrder 0), de_DE second — first by order wins.
        $resolver = new LocaleCodeResolver(
            $this->repo([$this->tenantLocale('de_AT', 'de'), $this->tenantLocale('de_DE', 'de')]),
        );

        self::assertSame('de_AT', $resolver->toBcp47('de', $tenant));
    }

    private function tenantLocale(string $code, string $language): TenantLocale
    {
        return new TenantLocale(new Locale($code, $code, null, $language));
    }

    /**
     * @param list<TenantLocale> $active
     */
    private function repo(array $active): TenantLocaleRepositoryInterface
    {
        return new class($active) implements TenantLocaleRepositoryInterface {
            /**
             * @param list<TenantLocale> $active
             */
            public function __construct(private array $active)
            {
            }

            public function findById(Uuid $id): ?TenantLocale
            {
                return null;
            }

            public function findByTenantAndLocale(Tenant $tenant, Locale $locale): ?TenantLocale
            {
                return null;
            }

            public function findByTenantAndCode(Tenant $tenant, string $code): ?TenantLocale
            {
                return null;
            }

            public function findActiveForTenant(Tenant $tenant): array
            {
                return $this->active;
            }

            public function findAllForTenant(Tenant $tenant): array
            {
                return $this->active;
            }

            public function findDefaultForTenant(Tenant $tenant): ?TenantLocale
            {
                return $this->active[0] ?? null;
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
