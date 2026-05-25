<?php

declare(strict_types=1);

namespace App\Tests\Unit\Identity\Security;

use App\Catalog\Domain\Entity\ObjectType;
use App\Catalog\Domain\ObjectKind;
use App\Identity\Application\PermissionResolverInterface;
use App\Identity\Domain\Entity\User;
use App\Identity\Domain\Rbac\PermissionSet;
use App\Identity\Infrastructure\Security\ObjectScopedVoter;
use App\Shared\Domain\Tenant;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use stdClass;
use Symfony\Component\Security\Core\Authentication\Token\NullToken;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;
use Symfony\Component\Security\Core\Authorization\Voter\VoterInterface;

/**
 * ULV-04a (#985) — covers the generic per-ObjectType voter that
 * resolves `object.{action}` PRD codes against the user permission set.
 */
final class ObjectScopedVoterTest extends TestCase
{
    #[Test]
    public function grantsViewWhenPermissionPresent(): void
    {
        $tenant = new Tenant('alpha', 'Alpha');
        $user = $this->user($tenant);
        $objectType = $this->objectType('cars', ObjectKind::Custom);

        $voter = new ObjectScopedVoter($this->resolverWith($user, ['object.view']));

        self::assertSame(
            VoterInterface::ACCESS_GRANTED,
            $voter->vote($this->token($user), [$objectType, 'view'], ['view']),
        );
    }

    #[Test]
    public function deniesDeleteWhenPermissionMissing(): void
    {
        $tenant = new Tenant('alpha', 'Alpha');
        $user = $this->user($tenant);
        $objectType = $this->objectType('product', ObjectKind::Product);

        $voter = new ObjectScopedVoter($this->resolverWith($user, ['object.view', 'object.edit']));

        self::assertSame(
            VoterInterface::ACCESS_DENIED,
            $voter->vote($this->token($user), [$objectType, 'delete'], ['delete']),
        );
    }

    #[Test]
    public function abstainsOnUnknownAttribute(): void
    {
        $tenant = new Tenant('alpha', 'Alpha');
        $user = $this->user($tenant);
        $objectType = $this->objectType('product', ObjectKind::Product);

        $voter = new ObjectScopedVoter($this->resolverWith($user, ['object.view']));

        self::assertSame(
            VoterInterface::ACCESS_ABSTAIN,
            $voter->vote($this->token($user), [$objectType, 'unknown'], ['UNHANDLED']),
        );
    }

    #[Test]
    public function abstainsOnNonTupleSubject(): void
    {
        $tenant = new Tenant('alpha', 'Alpha');
        $user = $this->user($tenant);

        $voter = new ObjectScopedVoter($this->resolverWith($user, ['object.view']));

        self::assertSame(
            VoterInterface::ACCESS_ABSTAIN,
            $voter->vote($this->token($user), 'arbitrary-string', ['view']),
        );
    }

    #[Test]
    public function abstainsOnTupleWithWrongSubjectClass(): void
    {
        $tenant = new Tenant('alpha', 'Alpha');
        $user = $this->user($tenant);

        $voter = new ObjectScopedVoter($this->resolverWith($user, ['object.view']));

        // Subject[0] must be ObjectType — a CatalogObject (or anything else)
        // routes to the legacy per-kind voters, not this one.
        self::assertSame(
            VoterInterface::ACCESS_ABSTAIN,
            $voter->vote($this->token($user), [new stdClass(), 'view'], ['view']),
        );
    }

    #[Test]
    public function grantsExportWhenPermissionPresent(): void
    {
        $tenant = new Tenant('alpha', 'Alpha');
        $user = $this->user($tenant);
        $objectType = $this->objectType('product', ObjectKind::Product);

        $voter = new ObjectScopedVoter($this->resolverWith($user, ['object.export']));

        self::assertSame(
            VoterInterface::ACCESS_GRANTED,
            $voter->vote($this->token($user), [$objectType, 'export'], ['export']),
        );
    }

    #[Test]
    public function deniesWhenUserIsAnonymous(): void
    {
        $objectType = $this->objectType('product', ObjectKind::Product);
        $voter = new ObjectScopedVoter($this->createStub(PermissionResolverInterface::class));

        self::assertSame(
            VoterInterface::ACCESS_DENIED,
            $voter->vote(new NullToken(), [$objectType, 'view'], ['view']),
        );
    }

    private function objectType(string $code, ObjectKind $kind): ObjectType
    {
        return new ObjectType($code, $kind, ['en' => ucfirst($code)]);
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
