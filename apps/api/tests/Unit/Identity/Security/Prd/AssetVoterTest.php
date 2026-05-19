<?php

declare(strict_types=1);

namespace App\Tests\Unit\Identity\Security\Prd;

use App\Asset\Domain\Entity\Asset;
use App\Identity\Application\PermissionResolverInterface;
use App\Identity\Domain\Entity\User;
use App\Identity\Domain\Rbac\PermissionSet;
use App\Identity\Infrastructure\Security\Prd\AssetVoter;
use App\Shared\Domain\Tenant;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use stdClass;
use Symfony\Component\Security\Core\Authentication\Token\NullToken;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;
use Symfony\Component\Security\Core\Authorization\Voter\VoterInterface;

/**
 * RBAC-P3-003 (#666) — unit coverage of AssetVoter against the
 * PRD §3.2 macierz `multimedia.*` codes.
 *
 * Scope assertions:
 *   - broad PRD §3.2 gate works for every action (view, add_edit_own,
 *     add_edit_any, delete);
 *   - cross-tenant subject denies even when permission is present;
 *   - own-vs-any ownership semantics are NOT exercised here — they
 *     plug in via OwnershipPolicy (RBAC-P3-010, #673) once the schema
 *     carries an `uploaded_by` column.
 */
final class AssetVoterTest extends TestCase
{
    #[Test]
    public function grantsViewWhenPermissionPresent(): void
    {
        $tenant = new Tenant('alpha', 'Alpha');
        $asset = $this->asset($tenant);
        $user = $this->user($tenant);

        $voter = new AssetVoter($this->resolverWith($user, ['multimedia.view']));

        self::assertSame(
            VoterInterface::ACCESS_GRANTED,
            $voter->vote($this->token($user), $asset, ['view']),
        );
    }

    #[Test]
    public function grantsAddEditOwnWhenPermissionPresent(): void
    {
        $tenant = new Tenant('alpha', 'Alpha');
        $asset = $this->asset($tenant);
        $user = $this->user($tenant);

        $voter = new AssetVoter($this->resolverWith($user, ['multimedia.add_edit_own']));

        self::assertSame(
            VoterInterface::ACCESS_GRANTED,
            $voter->vote($this->token($user), $asset, ['add_edit_own']),
        );
    }

    #[Test]
    public function grantsAddEditAnyWhenPermissionPresent(): void
    {
        $tenant = new Tenant('alpha', 'Alpha');
        $asset = $this->asset($tenant);
        $user = $this->user($tenant);

        $voter = new AssetVoter($this->resolverWith($user, ['multimedia.add_edit_any']));

        self::assertSame(
            VoterInterface::ACCESS_GRANTED,
            $voter->vote($this->token($user), $asset, ['add_edit_any']),
        );
    }

    #[Test]
    public function deniesAddEditAnyWhenOnlyOwnGranted(): void
    {
        $tenant = new Tenant('alpha', 'Alpha');
        $asset = $this->asset($tenant);
        $user = $this->user($tenant);

        $voter = new AssetVoter($this->resolverWith($user, ['multimedia.view', 'multimedia.add_edit_own']));

        self::assertSame(
            VoterInterface::ACCESS_DENIED,
            $voter->vote($this->token($user), $asset, ['add_edit_any']),
        );
    }

    #[Test]
    public function deniesDeleteWhenPermissionMissing(): void
    {
        $tenant = new Tenant('alpha', 'Alpha');
        $asset = $this->asset($tenant);
        $user = $this->user($tenant);

        $voter = new AssetVoter($this->resolverWith($user, ['multimedia.view', 'multimedia.add_edit_any']));

        self::assertSame(
            VoterInterface::ACCESS_DENIED,
            $voter->vote($this->token($user), $asset, ['delete']),
        );
    }

    #[Test]
    public function deniesAcrossTenantsEvenWithPermission(): void
    {
        $alpha = new Tenant('alpha', 'Alpha');
        $beta = new Tenant('beta', 'Beta');
        $asset = $this->asset($beta);
        $user = $this->user($alpha);

        $voter = new AssetVoter($this->resolverWith($user, ['multimedia.delete']));

        self::assertSame(
            VoterInterface::ACCESS_DENIED,
            $voter->vote($this->token($user), $asset, ['delete']),
        );
    }

    #[Test]
    public function abstainsOnUnknownAttribute(): void
    {
        $tenant = new Tenant('alpha', 'Alpha');
        $asset = $this->asset($tenant);
        $user = $this->user($tenant);

        $voter = new AssetVoter($this->resolverWith($user, ['multimedia.view']));

        self::assertSame(
            VoterInterface::ACCESS_ABSTAIN,
            $voter->vote($this->token($user), $asset, ['UNHANDLED_ATTRIBUTE']),
        );
    }

    #[Test]
    public function abstainsOnNonAssetSubject(): void
    {
        $tenant = new Tenant('alpha', 'Alpha');
        $user = $this->user($tenant);

        $voter = new AssetVoter($this->resolverWith($user, ['multimedia.view']));

        self::assertSame(
            VoterInterface::ACCESS_ABSTAIN,
            $voter->vote($this->token($user), new stdClass(), ['view']),
        );
    }

    #[Test]
    public function grantsClassLevelOnAddEditAny(): void
    {
        $tenant = new Tenant('alpha', 'Alpha');
        $user = $this->user($tenant);

        $voter = new AssetVoter($this->resolverWith($user, ['multimedia.add_edit_any']));

        self::assertSame(
            VoterInterface::ACCESS_GRANTED,
            $voter->vote($this->token($user), Asset::class, ['add_edit_any']),
        );
    }

    #[Test]
    public function deniesAnonymous(): void
    {
        $tenant = new Tenant('alpha', 'Alpha');
        $asset = $this->asset($tenant);

        $voter = new AssetVoter($this->createStub(PermissionResolverInterface::class));

        self::assertSame(
            VoterInterface::ACCESS_DENIED,
            $voter->vote(new NullToken(), $asset, ['view']),
        );
    }

    private function asset(Tenant $tenant): Asset
    {
        $asset = new Asset(
            code: 'asset-'.$tenant->getCode(),
            originalFilename: 'sample.jpg',
            mimeType: 'image/jpeg',
            size: 1024,
            storagePath: 'tenants/'.$tenant->getCode().'/sample.jpg',
        );
        $asset->assignTenant($tenant);

        return $asset;
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
