<?php

declare(strict_types=1);

namespace App\Identity\Application;

use App\Identity\Domain\Entity\ApiToken;
use DateTimeInterface;

/**
 * RBAC-P5-009 (#699) — maps {@see ApiToken} aggregates onto the JSON
 * shape consumed by the Settings → API tokens list.
 *
 * Sensitive fields (`tokenHash`) are NEVER projected — only the visible
 * last-4 suffix surfaces, matching the PRD §4.3 storage contract. The
 * plaintext exists in the create-response payload exactly once and the
 * server never sees it again.
 */
final class ApiTokenListResponseBuilder
{
    /**
     * @param iterable<ApiToken>    $tokens
     * @param array<string, string> $ownerEmailsById user id (rfc4122) => email
     *
     * @return list<array{
     *     id: string,
     *     name: string,
     *     token_last4: string,
     *     scopes: list<string>,
     *     owner_id: string,
     *     owner_email: ?string,
     *     last_used_at: ?string,
     *     last_used_ip: ?string,
     *     expires_at: ?string,
     *     revoked_at: ?string,
     *     created_at: string,
     *     status: string
     * }>
     */
    public function buildList(iterable $tokens, array $ownerEmailsById): array
    {
        $out = [];
        foreach ($tokens as $token) {
            $ownerId = $token->getUserId()->toRfc4122();
            $out[] = [
                'id' => $token->getId()->toRfc4122(),
                'name' => $token->getName(),
                'token_last4' => $token->getTokenLast4(),
                'scopes' => $token->getScopes(),
                'owner_id' => $ownerId,
                'owner_email' => $ownerEmailsById[$ownerId] ?? null,
                'last_used_at' => $token->getLastUsedAt()?->format(DateTimeInterface::ATOM),
                'last_used_ip' => $token->getLastUsedIp(),
                'expires_at' => $token->getExpiresAt()?->format(DateTimeInterface::ATOM),
                'revoked_at' => $token->getRevokedAt()?->format(DateTimeInterface::ATOM),
                'created_at' => $token->getCreatedAt()->format(DateTimeInterface::ATOM),
                'status' => $this->resolveStatus($token),
            ];
        }

        return $out;
    }

    private function resolveStatus(ApiToken $token): string
    {
        if ($token->isRevoked()) {
            return 'revoked';
        }
        if ($token->isExpired()) {
            return 'expired';
        }

        return 'active';
    }
}
