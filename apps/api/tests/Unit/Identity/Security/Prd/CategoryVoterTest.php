<?php

declare(strict_types=1);

namespace App\Tests\Unit\Identity\Security\Prd;

use App\Catalog\Domain\Entity\CatalogObject;
use App\Catalog\Domain\Entity\ObjectType;
use App\Catalog\Domain\ObjectKind;
use App\Identity\Application\PermissionResolverInterface;
use App\Identity\Domain\Entity\User;
use App\Identity\Domain\Rbac\PermissionSet;
use App\Identity\Infrastructure\Security\Prd\CategoryVoter;
use App\Shared\Domain\Tenant;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Security\Core\Authentication\Token\NullToken;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;
use Symfony\Component\Security\Core\Authorization\Voter\VoterInterface;

/**
 * RBAC-P3-003 (#666) — unit coverage of CategoryVoter against the
 * PRD §3.2 macierz codes (`categories.*`).
 */
final class CategoryVoterTest extends TestCase
{
    #[Test]
    public function grantsViewWhenPermissionPresent(): void
    {
        $tenant = new Tenant('alpha', 'Alpha');
        $category = $this->category($tenant);
        $user = $this->user($tenant);

        $voter = new CategoryVoter($this->resolverWith($user, ['categories.view']));

        self::assertSame(
            VoterInterface::ACCESS_GRANTED,
            $voter->vote($this->token($user), $category, ['view']),
        );
    }

    #[Test]
    public function grantsAddEditWhenPermissionPresent(): void
    {
        $tenant = new Tenant('alpha', 'Alpha');
        $category = $this->category($tenant);
        $user = $this->user($tenant);

        $voter = new CategoryVoter($this->resolverWith($user, ['categories.add_edit']));

        self::assertSame(
            VoterInterface::ACCESS_GRANTED,
            $voter->vote($this->token($user), $category, ['add_edit']),
        );
    }

    #[Test]
    public function deniesDeleteWhenPermissionMissing(): void
    {
        $tenant = new Tenant('alpha', 'Alpha');
        $category = $this->category($tenant);
        $user = $this->user($tenant);

        $voter = new CategoryVoter($this->resolverWith($user, ['categories.view', 'categories.add_edit']));

        self::assertSame(
            VoterInterface::ACCESS_DENIED,
            $voter->vote($this->token($user), $category, ['delete']),
        );
    }

    #[Test]
    public function deniesAcrossTenantsEvenWithPermission(): void
    {
        $alpha = new Tenant('alpha', 'Alpha');
        $beta = new Tenant('beta', 'Beta');
        $category = $this->category($beta);
        $user = $this->user($alpha);

        $voter = new CategoryVoter($this->resolverWith($user, ['categories.add_edit']));

        self::assertSame(
            VoterInterface::ACCESS_DENIED,
            $voter->vote($this->token($user), $category, ['add_edit']),
        );
    }

    #[Test]
    public function abstainsOnUnknownAttribute(): void
    {
        $tenant = new Tenant('alpha', 'Alpha');
        $category = $this->category($tenant);
        $user = $this->user($tenant);

        $voter = new CategoryVoter($this->resolverWith($user, ['categories.view']));

        self::assertSame(
            VoterInterface::ACCESS_ABSTAIN,
            $voter->vote($this->token($user), $category, ['UNHANDLED_ATTRIBUTE']),
        );
    }

    #[Test]
    public function abstainsOnNonCategoryCatalogObject(): void
    {
        $tenant = new Tenant('alpha', 'Alpha');
        $product = $this->catalogObject($tenant, ObjectKind::Product);
        $user = $this->user($tenant);

        $voter = new CategoryVoter($this->resolverWith($user, ['categories.view']));

        self::assertSame(
            VoterInterface::ACCESS_ABSTAIN,
            $voter->vote($this->token($user), $product, ['view']),
        );
    }

    #[Test]
    public function grantsClassLevelOnAddEdit(): void
    {
        $tenant = new Tenant('alpha', 'Alpha');
        $user = $this->user($tenant);

        $voter = new CategoryVoter($this->resolverWith($user, ['categories.add_edit']));

        self::assertSame(
            VoterInterface::ACCESS_GRANTED,
            $voter->vote($this->token($user), CatalogObject::class, ['add_edit']),
        );
    }

    #[Test]
    public function deniesAnonymous(): void
    {
        $tenant = new Tenant('alpha', 'Alpha');
        $category = $this->category($tenant);

        $voter = new CategoryVoter($this->createStub(PermissionResolverInterface::class));

        self::assertSame(
            VoterInterface::ACCESS_DENIED,
            $voter->vote(new NullToken(), $category, ['view']),
        );
    }

    private function category(Tenant $tenant): CatalogObject
    {
        return $this->catalogObject($tenant, ObjectKind::Category);
    }

    private function catalogObject(Tenant $tenant, ObjectKind $kind): CatalogObject
    {
        $type = new ObjectType($kind->value, $kind, ['en' => ucfirst($kind->value)]);
        $object = new CatalogObject($type, 'CODE-'.$kind->value);
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
