<?php

declare(strict_types=1);

namespace App\Tests\Unit\Identity\Security\Prd;

use App\Catalog\Domain\Entity\AttributeGroup;
use App\Identity\Application\PermissionResolverInterface;
use App\Identity\Domain\Entity\User;
use App\Identity\Domain\Rbac\PermissionSet;
use App\Identity\Infrastructure\Security\Prd\AttributeGroupVoter;
use App\Shared\Domain\Tenant;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;
use Symfony\Component\Security\Core\Authorization\Voter\VoterInterface;

/**
 * RBAC-P3-004 (#667) — AttributeGroupVoter unit coverage.
 */
final class AttributeGroupVoterTest extends TestCase
{
    #[Test]
    public function grantsView(): void
    {
        $tenant = new Tenant('alpha', 'Alpha');
        $group = $this->attributeGroup($tenant);
        $user = $this->user($tenant);

        $voter = new AttributeGroupVoter($this->resolverWith($user, ['modeling.view']));

        self::assertSame(
            VoterInterface::ACCESS_GRANTED,
            $voter->vote($this->token($user), $group, ['view']),
        );
    }

    #[Test]
    public function grantsAddEdit(): void
    {
        $tenant = new Tenant('alpha', 'Alpha');
        $group = $this->attributeGroup($tenant);
        $user = $this->user($tenant);

        $voter = new AttributeGroupVoter($this->resolverWith($user, ['modeling.attribute_groups.add_edit']));

        self::assertSame(
            VoterInterface::ACCESS_GRANTED,
            $voter->vote($this->token($user), $group, ['add_edit']),
        );
    }

    #[Test]
    public function abstainsOnDelete(): void
    {
        $tenant = new Tenant('alpha', 'Alpha');
        $group = $this->attributeGroup($tenant);
        $user = $this->user($tenant);

        $voter = new AttributeGroupVoter($this->resolverWith($user, ['modeling.attribute_groups.add_edit']));

        // No `delete` code in the macierz — the voter abstains so the
        // global affirmative strategy denies (no other voter covers it).
        self::assertSame(
            VoterInterface::ACCESS_ABSTAIN,
            $voter->vote($this->token($user), $group, ['delete']),
        );
    }

    #[Test]
    public function deniesAddEditWithoutPermission(): void
    {
        $tenant = new Tenant('alpha', 'Alpha');
        $group = $this->attributeGroup($tenant);
        $user = $this->user($tenant);

        $voter = new AttributeGroupVoter($this->resolverWith($user, ['modeling.view']));

        self::assertSame(
            VoterInterface::ACCESS_DENIED,
            $voter->vote($this->token($user), $group, ['add_edit']),
        );
    }

    #[Test]
    public function deniesAcrossTenants(): void
    {
        $alpha = new Tenant('alpha', 'Alpha');
        $beta = new Tenant('beta', 'Beta');
        $group = $this->attributeGroup($beta);
        $user = $this->user($alpha);

        $voter = new AttributeGroupVoter($this->resolverWith($user, ['modeling.attribute_groups.add_edit']));

        self::assertSame(
            VoterInterface::ACCESS_DENIED,
            $voter->vote($this->token($user), $group, ['add_edit']),
        );
    }

    private function attributeGroup(Tenant $tenant): AttributeGroup
    {
        $group = new AttributeGroup('pricing', ['en' => 'Pricing']);
        $group->assignTenant($tenant);

        return $group;
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
