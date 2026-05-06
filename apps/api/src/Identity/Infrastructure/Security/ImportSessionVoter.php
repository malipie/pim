<?php

declare(strict_types=1);

namespace App\Identity\Infrastructure\Security;

use App\Identity\Domain\Entity\User;
use App\Import\Domain\Entity\ImportSession;
use App\Shared\Application\Auth\ApiKeyPrincipal;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;

/**
 * Per-user ownership for {@see ImportSession}.
 *
 * Standard tenant + RBAC checks live in {@see AbstractRbacVoter}; the
 * extra rule here is that a session is only visible / mutable by the
 * user who started it. Class-level votes (Post / GetCollection) skip
 * the ownership check because the data layer scopes the listing.
 */
final class ImportSessionVoter extends AbstractRbacVoter
{
    /**
     * @return array<string, string>
     */
    protected function attributeMap(): array
    {
        return [
            'READ' => 'read',
            'CREATE' => 'write',
            'UPDATE' => 'write',
            'WRITE' => 'write',
            'DELETE' => 'delete',
        ];
    }

    protected function resource(): string
    {
        return 'import_session';
    }

    protected function subjectClass(): string
    {
        return ImportSession::class;
    }

    protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token): bool
    {
        if (!parent::voteOnAttribute($attribute, $subject, $token)) {
            return false;
        }

        if (\is_string($subject) || !$subject instanceof ImportSession) {
            return \is_string($subject);
        }

        $principal = $token->getUser();
        if ($principal instanceof ApiKeyPrincipal) {
            // API-key reads are already tenant-scoped by the parent; ownership
            // does not apply to integration keys.
            return true;
        }

        if (!$principal instanceof User) {
            return false;
        }

        return $subject->getUserId()->toRfc4122() === $principal->getId()->toRfc4122();
    }
}
