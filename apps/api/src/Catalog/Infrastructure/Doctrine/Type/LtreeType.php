<?php

declare(strict_types=1);

namespace App\Catalog\Infrastructure\Doctrine\Type;

use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Types\Type;

/**
 * Custom Doctrine type for Postgres `LTREE`.
 *
 * Doctrine ORM 3 ships no native ltree type, so we map it as text on the
 * PHP side (`?string`) and declare `LTREE` as the SQL column type. This
 * is the minimal possible adapter — no parsing, no value object — because
 * the only domain operation we need on `path` is "store and look up
 * descendants", which Postgres handles natively via the `<@` / `@>`
 * operators on the partial GIST index.
 *
 * Format validation lives in the Doctrine listener
 * {@see \App\Catalog\Infrastructure\Doctrine\EventListener\CategoryPathValidator},
 * not here, because the listener also enforces the kind ↔ path invariant
 * which is a higher-level constraint than a single column type.
 */
final class LtreeType extends Type
{
    public const string NAME = 'ltree';

    public function getSQLDeclaration(array $column, AbstractPlatform $platform): string
    {
        return 'LTREE';
    }

    public function getName(): string
    {
        return self::NAME;
    }

    public function convertToDatabaseValue(mixed $value, AbstractPlatform $platform): ?string
    {
        if (null === $value) {
            return null;
        }
        if (\is_string($value)) {
            return $value;
        }
        if (\is_scalar($value) || (\is_object($value) && method_exists($value, '__toString'))) {
            return (string) $value;
        }

        return null;
    }

    public function convertToPHPValue(mixed $value, AbstractPlatform $platform): ?string
    {
        if (null === $value) {
            return null;
        }
        if (\is_string($value)) {
            return $value;
        }
        if (\is_scalar($value) || (\is_object($value) && method_exists($value, '__toString'))) {
            return (string) $value;
        }

        return null;
    }

    /*
     * Postgres returns LTREE values via libpq as plain text — no binary
     * marshalling needed, so we don't need to override
     * {@see Type::requiresSQLCommentHint()} or the BC type hint.
     */
}
