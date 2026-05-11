<?php

declare(strict_types=1);

namespace App\Identity\Infrastructure\Security;

use App\Identity\Domain\Entity\User;
use App\Import\Domain\Entity\ImportSource;
use App\Shared\Application\Auth\ApiKeyPrincipal;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;

/**
 * VIEW-IMP-03 (#500) — per-user ownership for {@see ImportSource}.
 */
final class ImportSourceVoter extends AbstractRbacVoter
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
        return 'import_source';
    }

    protected function subjectClass(): string
    {
        return ImportSource::class;
    }

    protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token): bool
    {
        if (!parent::voteOnAttribute($attribute, $subject, $token)) {
            return false;
        }

        if (\is_string($subject) || !$subject instanceof ImportSource) {
            return \is_string($subject);
        }

        $principal = $token->getUser();
        if ($principal instanceof ApiKeyPrincipal) {
            return true;
        }

        if (!$principal instanceof User) {
            return false;
        }

        return $subject->getUserId()->toRfc4122() === $principal->getId()->toRfc4122();
    }
}
