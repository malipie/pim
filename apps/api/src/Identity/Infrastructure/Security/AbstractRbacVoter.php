<?php

declare(strict_types=1);

namespace App\Identity\Infrastructure\Security;

use App\Identity\Domain\Entity\User;
use App\Shared\Application\Auth\ApiKeyPrincipal;
use App\Shared\Domain\Tenant;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

/**
 * Shared RBAC voter scaffolding for tenant-scoped domain resources.
 *
 * Concrete voters declare:
 *  - the resource string they protect (e.g. "object", "channel"),
 *  - the FQCN of the subject they accept,
 *  - which Voter attributes (READ/WRITE/DELETE) map to which RBAC actions.
 *
 * The base class handles the rest:
 *  - resolving the User from the security token (anonymous = deny),
 *  - checking the (resource, action) pair against the user's M2M role graph,
 *  - rejecting cross-tenant access whenever the subject is an instance and
 *    implements TenantAware.
 *
 * Class-level votes (Post / GetCollection where the subject is the FQCN
 * string) skip the tenant check because there is no instance to compare
 * against — the action permission alone gates create/list, while existing
 * row scoping comes from the Doctrine TenantFilter.
 */
/**
 * @extends Voter<string, object|string>
 */
abstract class AbstractRbacVoter extends Voter
{
    /**
     * Map Voter attribute (READ/WRITE/UPDATE/DELETE) to the RBAC action.
     *
     * Concrete voters override or extend this for resources that need a
     * different mapping (e.g. an "approve" attribute mapping to write).
     *
     * @return array<string, string>
     */
    abstract protected function attributeMap(): array;

    abstract protected function resource(): string;

    /**
     * The FQCN this voter handles, both for instances and for class-level
     * (Post / GetCollection) operations passing the FQCN string as subject.
     */
    abstract protected function subjectClass(): string;

    protected function supports(string $attribute, mixed $subject): bool
    {
        if (!\array_key_exists($attribute, $this->attributeMap())) {
            return false;
        }

        if (\is_string($subject)) {
            return $subject === $this->subjectClass();
        }

        return $subject instanceof ($this->subjectClass());
    }

    protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token): bool
    {
        $action = $this->attributeMap()[$attribute] ?? null;
        if (null === $action) {
            return false;
        }

        $principal = $token->getUser();

        // API-key principals are read-only (#94). Any non-`read` action
        // is denied regardless of the key's profile scope; the public
        // API surface is intentionally a projection, not a write path.
        if ($principal instanceof ApiKeyPrincipal) {
            if ('read' !== $action) {
                return false;
            }

            return $this->matchesTenantId($subject, $principal->tenantId());
        }

        if (!$principal instanceof User) {
            return false;
        }

        if (!$this->userHasPermission($principal, $action)) {
            return false;
        }

        // Class-level subject (e.g. on Post / GetCollection): no instance to
        // tenant-scope against. Permission alone decides; the Doctrine
        // TenantFilter still scopes any subsequent reads.
        if (\is_string($subject)) {
            return true;
        }

        $subjectTenant = $this->extractTenant($subject);
        if (null !== $subjectTenant && $subjectTenant->getId()->toRfc4122() !== $principal->getTenant()->getId()->toRfc4122()) {
            return false;
        }

        return true;
    }

    private function matchesTenantId(mixed $subject, \Symfony\Component\Uid\Uuid $tenantId): bool
    {
        // Class-level subject — TenantFilter narrows reads, so listing
        // is always granted to a key bound to the same authentication
        // tenant context.
        if (\is_string($subject)) {
            return true;
        }
        if (!\is_object($subject)) {
            return false;
        }

        $subjectTenant = $this->extractTenant($subject);
        if (null === $subjectTenant) {
            return true;
        }

        return $subjectTenant->getId()->toRfc4122() === $tenantId->toRfc4122();
    }

    /**
     * Concrete voters override when their subject does not expose getTenant()
     * directly (e.g. join-table rows). Default implementation handles every
     * domain entity that has a getTenant() accessor — Sprint-0 entities use
     * `?Tenant` because the assignment listener stamps tenant_id on
     * PrePersist; null means the row is mid-construction and the listener
     * will reject it before flush, so no leak.
     */
    protected function extractTenant(object $subject): ?Tenant
    {
        if (!method_exists($subject, 'getTenant')) {
            return null;
        }

        $tenant = $subject->getTenant();

        return $tenant instanceof Tenant ? $tenant : null;
    }

    private function userHasPermission(User $user, string $action): bool
    {
        $resource = $this->resource();

        foreach ($user->getAssignedRoles() as $role) {
            foreach ($role->getPermissions() as $permission) {
                if ($permission->getResource() === $resource && $permission->getAction() === $action) {
                    return true;
                }
            }
        }

        return false;
    }
}
