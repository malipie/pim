<?php

declare(strict_types=1);

namespace App\Identity\Infrastructure\Security\Prd;

use App\Catalog\Domain\Entity\ObjectType;
use App\Identity\Infrastructure\Security\AbstractPrdVoter;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;

/**
 * RBAC-P3-004 (#667) — per-ObjectType authorization aligned with the
 * PRD §3.2 Modeling row (`modeling.view`, `modeling.object_types.add`,
 * `modeling.delete_custom`).
 *
 * Mapping rationale:
 *   - `view`        → `modeling.view` (broad modeling read gate),
 *   - `add` / `edit` → `modeling.object_types.add` (the macierz collapses
 *     creation and structural edits onto the same Modeler-owned action;
 *     built-ins are protected separately below),
 *   - `delete`      → `modeling.delete_custom` **plus** an `is_built_in=false`
 *     runtime guard. The platform-owned built-ins (Product / Category /
 *     Asset / Brand) cannot be deleted by anyone — Tenant Owner included —
 *     because they back the API sugar paths. The 409 response shape comes
 *     from the controller; the voter denies before the controller runs.
 *
 * Sibling voters in this ticket — AttributeVoter, AttributeGroupVoter —
 * piggyback on the same PRD row but with simpler resolution (no
 * is_built_in check; the protected built-ins live on ObjectType only).
 *
 * Auto-grant of `{kind}.view` / `{kind}.edit` to roles flagged with
 * `modeling.auto_grant_new_object_types` lives in a Doctrine post-persist
 * listener — deferred to a focused follow-up ticket because it requires
 * a new dynamic-permission code namespace plus role_permissions writes
 * tied to schema operations.
 */
final class ObjectTypeVoter extends AbstractPrdVoter
{
    /**
     * @return array<string, string>
     */
    protected function permissionMap(): array
    {
        return [
            'view' => 'modeling.view',
            'add' => 'modeling.object_types.add',
            'edit' => 'modeling.object_types.add',
            'delete' => 'modeling.delete_custom',
        ];
    }

    protected function subjectClass(): string
    {
        return ObjectType::class;
    }

    protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token): bool
    {
        if (!parent::voteOnAttribute($attribute, $subject, $token)) {
            return false;
        }

        if ('delete' !== $attribute) {
            return true;
        }

        // Built-in protection: even a Modeler with `modeling.delete_custom`
        // must not delete a built-in row. The repository / controller may
        // also enforce this, but the voter is the authoritative gate so
        // every entry point (REST + GraphQL + future agent) inherits it.
        return !($subject instanceof ObjectType && $subject->isBuiltIn());
    }
}
