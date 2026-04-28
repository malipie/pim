<?php

declare(strict_types=1);

namespace App\Identity\Application\Exception;

use RuntimeException;

/**
 * Domain failure when consuming a refresh token. Each factory carries a stable
 * RFC 7807 `detail` string that the controller surfaces verbatim — the
 * `title` is always "Unauthorized" so clients can branch on the code/detail.
 *
 * The reasons stay coarse on purpose: a stolen token reused after rotation
 * looks indistinguishable from a benign retry until the family revocation
 * fires, and the client has no productive way to react differently anyway.
 * The audit trail (used_at / revoked_at) is the real source of truth.
 */
final class RefreshTokenException extends RuntimeException
{
    public const string REASON_MISSING = 'missing';
    public const string REASON_INVALID = 'invalid';
    public const string REASON_EXPIRED = 'expired';
    public const string REASON_REVOKED = 'revoked';
    public const string REASON_REUSED = 'reused';

    private function __construct(
        public readonly string $reason,
        string $detail,
    ) {
        parent::__construct($detail);
    }

    public static function missing(): self
    {
        return new self(self::REASON_MISSING, 'Refresh token cookie is missing.');
    }

    public static function invalid(): self
    {
        return new self(self::REASON_INVALID, 'Refresh token is invalid.');
    }

    public static function expired(): self
    {
        return new self(self::REASON_EXPIRED, 'Refresh token has expired.');
    }

    public static function revoked(): self
    {
        return new self(self::REASON_REVOKED, 'Refresh token has been revoked.');
    }

    public static function reused(): self
    {
        return new self(self::REASON_REUSED, 'Refresh token has already been used.');
    }
}
