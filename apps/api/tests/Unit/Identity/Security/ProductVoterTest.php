<?php

declare(strict_types=1);

namespace App\Tests\Unit\Identity\Security;

use App\Catalog\Domain\Entity\CatalogObject;
use App\Catalog\Domain\Entity\ObjectType;
use App\Catalog\Domain\ObjectKind;
use App\Identity\Application\PermissionResolverInterface;
use App\Identity\Domain\Entity\User;
use App\Identity\Domain\Rbac\PermissionSet;
use App\Identity\Infrastructure\Security\ProductVoter;
use App\Shared\Domain\Tenant;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;
use Symfony\Component\Security\Core\Authorization\Voter\VoterInterface;

/**
 * RBAC-P3-002 (#665) — unit coverage of ProductVoter against the
 * PRD §3.2 macierz codes (`products.*`).
 */
final class ProductVoterTest extends TestCase
{
    #[Test]
    public function grantsViewWhenPermissionPresent(): void
    {
        $tenant = new Tenant('alpha', 'Alpha');
        $product = $this->product($tenant);
        $user = $this->user($tenant);

        $voter = new ProductVoter($this->resolverWith($user, ['products.view']));

        self::assertSame(
            VoterInterface::ACCESS_GRANTED,
            $voter->vote($this->token($user), $product, ['view']),
        );
    }

    #[Test]
    public function deniesDeleteWhenPermissionMissing(): void
    {
        $tenant = new Tenant('alpha', 'Alpha');
        $product = $this->product($tenant);
        $user = $this->user($tenant);

        $voter = new ProductVoter($this->resolverWith($user, ['products.view', 'products.edit']));

        self::assertSame(
            VoterInterface::ACCESS_DENIED,
            $voter->vote($this->token($user), $product, ['delete']),
        );
    }

    #[Test]
    public function grantsBulkOperationsWhenPermissionPresent(): void
    {
        $tenant = new Tenant('alpha', 'Alpha');
        $product = $this->product($tenant);
        $user = $this->user($tenant);

        $voter = new ProductVoter($this->resolverWith($user, ['products.bulk_operations']));

        self::assertSame(
            VoterInterface::ACCESS_GRANTED,
            $voter->vote($this->token($user), $product, ['bulk_operations']),
        );
    }

    #[Test]
    public function deniesAcrossTenantsEvenWithPermission(): void
    {
        $alpha = new Tenant('alpha', 'Alpha');
        $beta = new Tenant('beta', 'Beta');
        $product = $this->product($beta);
        $user = $this->user($alpha);

        $voter = new ProductVoter($this->resolverWith($user, ['products.edit']));

        self::assertSame(
            VoterInterface::ACCESS_DENIED,
            $voter->vote($this->token($user), $product, ['edit']),
        );
    }

    #[Test]
    public function abstainsOnUnknownAttribute(): void
    {
        $tenant = new Tenant('alpha', 'Alpha');
        $product = $this->product($tenant);
        $user = $this->user($tenant);

        $voter = new ProductVoter($this->resolverWith($user, ['products.view']));

        self::assertSame(
            VoterInterface::ACCESS_ABSTAIN,
            $voter->vote($this->token($user), $product, ['UNHANDLED_ATTRIBUTE']),
        );
    }

    #[Test]
    public function abstainsOnNonProductCatalogObject(): void
    {
        $tenant = new Tenant('alpha', 'Alpha');
        $category = $this->catalogObject($tenant, ObjectKind::Category);
        $user = $this->user($tenant);

        $voter = new ProductVoter($this->resolverWith($user, ['products.view']));

        // The Category voter (#666) covers kind=Category — this voter must
        // stay out so the affirmative strategy doesn't accidentally grant
        // a category permission through the product permission code.
        self::assertSame(
            VoterInterface::ACCESS_ABSTAIN,
            $voter->vote($this->token($user), $category, ['view']),
        );
    }

    #[Test]
    public function grantsClassLevelCreateWhenAddPermissionPresent(): void
    {
        $tenant = new Tenant('alpha', 'Alpha');
        $user = $this->user($tenant);

        $voter = new ProductVoter($this->resolverWith($user, ['products.add']));

        self::assertSame(
            VoterInterface::ACCESS_GRANTED,
            $voter->vote($this->token($user), CatalogObject::class, ['add']),
        );
    }

    #[Test]
    public function deniesWhenUserIsAnonymous(): void
    {
        $tenant = new Tenant('alpha', 'Alpha');
        $product = $this->product($tenant);

        $voter = new ProductVoter($this->createStub(PermissionResolverInterface::class));

        $token = new UsernamePasswordToken(new \stdClass() instanceof User ? new \stdClass() : new User($tenant, 'anon@x.y', ''), 'main');

        self::assertSame(
            VoterInterface::ACCESS_DENIED,
            $voter->vote(new \Symfony\Component\Security\Core\Authentication\Token\NullToken(), $product, ['view']),
        );
    }

    private function product(Tenant $tenant): CatalogObject
    {
        return $this->catalogObject($tenant, ObjectKind::Product);
    }

    private function catalogObject(Tenant $tenant, ObjectKind $kind): CatalogObject
    {
        $type = new ObjectType($kind->value, $kind, ['en' => ucfirst($kind->value)]);
        $object = new CatalogObject($type, 'SKU-'.$kind->value);
        $object->assignTenant($tenant);

        return $object;
    }

    private function user(Tenant $tenant): User
    {
        return new User($tenant, 'tester@'.$tenant->getCode().'.localhost', 'placeholder');
    }

    /**
     * @param list<string> $codes
     */
    private function resolverWith(User $user, array $codes): PermissionResolverInterface
    {
        $resolver = $this->createMock(PermissionResolverInterface::class);
        $resolver->method('resolve')->with($user)->willReturn(new PermissionSet($codes));

        return $resolver;
    }

    private function token(User $user): UsernamePasswordToken
    {
        return new UsernamePasswordToken($user, 'main');
    }
}
