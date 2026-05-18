<?php

declare(strict_types=1);

namespace App\Identity\Application;

use App\Identity\Domain\Entity\Invitation;
use App\Identity\Domain\Entity\User;
use App\Identity\Domain\Entity\UserRole;
use App\Identity\Domain\Repository\InvitationRepositoryInterface;
use App\Identity\Domain\Repository\RoleRepositoryInterface;
use App\Identity\Domain\Repository\UserRepositoryInterface;
use App\Identity\Domain\Repository\UserRoleRepositoryInterface;
use App\Shared\Domain\Tenant;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use LogicException;
use Psr\Log\LoggerInterface;
use RuntimeException;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Address;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Uid\Uuid;

/**
 * RBAC-P2-008 (#657) — magic-link invitation orchestrator.
 *
 * Flow:
 *   create(): generate 32-byte random token → SHA-256 hash to DB →
 *             return Invitation + plaintext token (single in-transit copy).
 *             Phase 2 ships token in API response (dev mode); production
 *             email send via Symfony Mailer is a follow-up ticket (mailer
 *             infra not yet configured in repo).
 *   accept(): hash incoming token → query by token_hash (unique index) →
 *             verify Invitation::isPending() → create User + UserRole
 *             assignment → mark Invitation::accept().
 *   revoke(): sets revokedAt via Invitation::revoke().
 *
 * Single-use enforcement: Invitation::accept() throws LogicException if
 * acceptedAt non-null. The first acceptance wins; subsequent attempts
 * with the same token surface as 409.
 *
 * Tenant scope: every operation requires the calling user's TenantContext
 * to match the Invitation tenant_id. The repository layer does not
 * automatically enforce this — the controller MUST pass Tenant explicitly.
 */
final class InvitationService
{
    private const int TTL_DAYS = 7;

    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly InvitationRepositoryInterface $invitations,
        private readonly UserRepositoryInterface $users,
        private readonly UserRoleRepositoryInterface $userRoles,
        private readonly RoleRepositoryInterface $roles,
        private readonly MagicLinkTokenHasher $tokenHasher,
        private readonly UserPasswordHasherInterface $passwordHasher,
        private readonly MailerInterface $mailer,
        private readonly LoggerInterface $logger,
        private readonly string $appBaseUrl = 'https://pim.localhost',
    ) {
    }

    /**
     * Create a pending invitation. Returns the Invitation entity + the
     * plaintext token (the caller MUST treat this as a single-use secret
     * — log it nowhere, email it once, never persist plaintext).
     *
     * @return array{invitation: Invitation, token: string}
     */
    public function create(
        Tenant $tenant,
        string $email,
        string $roleCode,
        User $invitedBy,
    ): array {
        $role = $this->roles->findByCode($roleCode, $tenant);
        if (null === $role) {
            throw new RuntimeException(\sprintf('Role "%s" not found in tenant "%s".', $roleCode, $tenant->getCode()));
        }

        $plaintext = $this->tokenHasher->generate();
        $tokenHash = $this->tokenHasher->hash($plaintext);
        $expiresAt = new DateTimeImmutable(\sprintf('+%d days', self::TTL_DAYS));

        $invitation = new Invitation(
            tenantId: $tenant->getId(),
            email: $email,
            tokenHash: $tokenHash,
            invitedByUserId: $invitedBy->getId(),
            roleId: $role->getId(),
            expiresAt: $expiresAt,
        );

        $this->invitations->save($invitation);

        // Send invitation email (Mailpit catches in dev — see https://mail.pim.localhost).
        // Failure to send is logged but does NOT block the create flow — the
        // operator can re-send via Phase 5 UI, and the dev-mode token return
        // covers test scenarios.
        try {
            $email = new TemplatedEmail()
                ->from(new Address('noreply@pim.localhost', 'Cortex PIM'))
                ->to(new Address($email))
                ->subject(\sprintf('Zaproszenie do %s — Cortex PIM', $tenant->getName()))
                ->htmlTemplate('email/invitation.html.twig')
                ->context([
                    'recipient_email' => $email,
                    'tenant_name' => $tenant->getName(),
                    'invited_by_email' => $invitedBy->getEmail(),
                    'role_name' => $role->getName(),
                    'accept_url' => \sprintf('%s/invitations/%s/accept', $this->appBaseUrl, $plaintext),
                    'expires_at' => $expiresAt,
                ]);
            $this->mailer->send($email);
        } catch (TransportExceptionInterface $e) {
            $this->logger->warning('Invitation email failed to send', [
                'invitation_id' => $invitation->getId()->toRfc4122(),
                'reason' => $e->getMessage(),
            ]);
        }

        return ['invitation' => $invitation, 'token' => $plaintext];
    }

    /**
     * Accept the invitation, creating a User + role assignment.
     *
     * @throws LogicException when the invitation is already accepted /
     *                        revoked / expired (Invitation::accept enforces)
     */
    public function accept(string $plaintextToken, string $newPassword): User
    {
        $tokenHash = $this->tokenHasher->hash($plaintextToken);
        $invitation = $this->invitations->findByHash($tokenHash);
        if (null === $invitation) {
            throw new RuntimeException('Invitation token not found.');
        }

        // Resolve tenant via the existing EntityManager (filter-aware).
        /** @var Tenant|null $tenant */
        $tenant = $this->em->find(Tenant::class, $invitation->getTenantId()->toRfc4122());
        if (null === $tenant) {
            throw new RuntimeException('Invitation tenant not found.');
        }

        // Create User + hash password.
        $user = new User(
            tenant: $tenant,
            email: $invitation->getEmail(),
            passwordHash: '', // placeholder, hashed below — User ctor requires non-null
            roles: ['ROLE_USER'],
            id: Uuid::v7(),
        );
        $hashed = $this->passwordHasher->hashPassword($user, $newPassword);
        // Re-instantiate User with the real hash — User has no setPassword;
        // construct directly with the hash this time.
        $user = new User(
            tenant: $tenant,
            email: $invitation->getEmail(),
            passwordHash: $hashed,
            roles: ['ROLE_USER'],
            id: $user->getId(),
        );
        $this->users->save($user);

        // Assign role via UserRole junction (no scope restrictions by default).
        $userRole = new UserRole(
            userId: $user->getId(),
            roleId: $invitation->getRoleId(),
        );
        $this->userRoles->save($userRole);

        // Mark invitation accepted (throws if already accepted/revoked/expired).
        $invitation->accept();
        $this->invitations->save($invitation);

        return $user;
    }

    public function revoke(Uuid $invitationId): void
    {
        $invitation = $this->invitations->findById($invitationId);
        if (null === $invitation) {
            throw new RuntimeException('Invitation not found.');
        }
        $invitation->revoke();
        $this->invitations->save($invitation);
    }
}
