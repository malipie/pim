<?php

declare(strict_types=1);

namespace App\Tests\Unit\Identity\Security;

use App\Backup\Domain\Entity\Backup;
use App\Backup\Domain\Enum\BackupTriggerAction;
use App\Catalog\Domain\Entity\ObjectType;
use App\Catalog\Domain\ObjectKind;
use App\Identity\Domain\Entity\Permission;
use App\Identity\Domain\Entity\Role;
use App\Identity\Domain\Entity\User;
use App\Identity\Infrastructure\Security\BackupVoter;
use App\Identity\Infrastructure\Security\ImportProfileVoter;
use App\Identity\Infrastructure\Security\ImportSessionVoter;
use App\Import\Domain\Entity\ImportProfile;
use App\Import\Domain\Entity\ImportSession;
use App\Shared\Domain\Tenant;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;
use Symfony\Component\Security\Core\Authorization\Voter\VoterInterface;
use Symfony\Component\Uid\Uuid;

/**
 * IMP-01 voters — owner-only ImportSession / ImportProfile,
 * tenant-only Backup. Heavy lifting (RBAC walk + tenant compare) is
 * inherited from {@see \App\Identity\Infrastructure\Security\AbstractRbacVoter};
 * these cases pin down the additional ownership predicate.
 */
final class ImportVotersTest extends TestCase
{
    #[Test]
    public function importSessionVoterGrantsReadToOwner(): void
    {
        $tenant = new Tenant('alpha', 'Alpha');
        $owner = $this->userWithPermission($tenant, 'import_session', 'read');
        $session = $this->makeSession($tenant, $owner->getId());

        self::assertSame(
            VoterInterface::ACCESS_GRANTED,
            new ImportSessionVoter()->vote($this->token($owner), $session, ['READ']),
        );
    }

    #[Test]
    public function importSessionVoterDeniesCrossUserSameTenant(): void
    {
        $tenant = new Tenant('alpha', 'Alpha');
        $intruder = $this->userWithPermission($tenant, 'import_session', 'read');
        $strangerId = Uuid::v7();
        $session = $this->makeSession($tenant, $strangerId);

        self::assertSame(
            VoterInterface::ACCESS_DENIED,
            new ImportSessionVoter()->vote($this->token($intruder), $session, ['READ']),
        );
    }

    #[Test]
    public function importSessionVoterDeniesCrossTenant(): void
    {
        $alpha = new Tenant('alpha', 'Alpha');
        $beta = new Tenant('beta', 'Beta');
        $user = $this->userWithPermission($alpha, 'import_session', 'read');
        $session = $this->makeSession($beta, $user->getId());

        self::assertSame(
            VoterInterface::ACCESS_DENIED,
            new ImportSessionVoter()->vote($this->token($user), $session, ['READ']),
        );
    }

    #[Test]
    public function importSessionVoterGrantsClassLevelCreate(): void
    {
        $tenant = new Tenant('alpha', 'Alpha');
        $user = $this->userWithPermission($tenant, 'import_session', 'write');

        self::assertSame(
            VoterInterface::ACCESS_GRANTED,
            new ImportSessionVoter()->vote($this->token($user), ImportSession::class, ['CREATE']),
        );
    }

    #[Test]
    public function importProfileVoterGrantsUpdateToOwner(): void
    {
        $tenant = new Tenant('alpha', 'Alpha');
        $owner = $this->userWithPermission($tenant, 'import_profile', 'write');
        $profile = $this->makeProfile($tenant, $owner->getId());

        self::assertSame(
            VoterInterface::ACCESS_GRANTED,
            new ImportProfileVoter()->vote($this->token($owner), $profile, ['UPDATE']),
        );
    }

    #[Test]
    public function importProfileVoterDeniesDeleteForNonOwner(): void
    {
        $tenant = new Tenant('alpha', 'Alpha');
        $intruder = $this->userWithPermission($tenant, 'import_profile', 'delete');
        $profile = $this->makeProfile($tenant, Uuid::v7());

        self::assertSame(
            VoterInterface::ACCESS_DENIED,
            new ImportProfileVoter()->vote($this->token($intruder), $profile, ['DELETE']),
        );
    }

    #[Test]
    public function backupVoterGrantsReadOnSameTenant(): void
    {
        $tenant = new Tenant('alpha', 'Alpha');
        $user = $this->userWithPermission($tenant, 'backup', 'read');
        $backup = $this->makeBackup($tenant);

        self::assertSame(
            VoterInterface::ACCESS_GRANTED,
            new BackupVoter()->vote($this->token($user), $backup, ['READ']),
        );
    }

    #[Test]
    public function backupVoterDeniesCrossTenant(): void
    {
        $alpha = new Tenant('alpha', 'Alpha');
        $beta = new Tenant('beta', 'Beta');
        $user = $this->userWithPermission($alpha, 'backup', 'read');
        $backup = $this->makeBackup($beta);

        self::assertSame(
            VoterInterface::ACCESS_DENIED,
            new BackupVoter()->vote($this->token($user), $backup, ['READ']),
        );
    }

    private function makeSession(Tenant $tenant, Uuid $userId): ImportSession
    {
        $type = new ObjectType('product', ObjectKind::Product, ['en' => 'Product']);
        $session = new ImportSession(
            userId: $userId,
            targetObjectType: $type,
            fileName: 'test.xlsx',
            fileSizeBytes: 1024,
        );
        $session->assignTenant($tenant);

        return $session;
    }

    private function makeProfile(Tenant $tenant, Uuid $userId): ImportProfile
    {
        $type = new ObjectType('product', ObjectKind::Product, ['en' => 'Product']);
        $profile = new ImportProfile(
            userId: $userId,
            name: 'Test Profile',
            targetObjectType: $type,
        );
        $profile->assignTenant($tenant);

        return $profile;
    }

    private function makeBackup(Tenant $tenant): Backup
    {
        $backup = new Backup(
            triggeredByUserId: Uuid::v7(),
            triggeredByAction: BackupTriggerAction::Manual,
        );
        $backup->assignTenant($tenant);

        return $backup;
    }

    private function userWithPermission(Tenant $tenant, string $resource, string $action): User
    {
        $user = new User($tenant, 'kasia@'.$tenant->getCode().'.test', '', ['ROLE_USER']);
        $role = new Role('test_role_'.$resource.'_'.$action, 'Test Role');
        $role->grantPermission(new Permission($resource, $action));
        $user->addRole($role);

        return $user;
    }

    private function token(User $user): UsernamePasswordToken
    {
        return new UsernamePasswordToken($user, 'main', $user->getRoles());
    }
}
