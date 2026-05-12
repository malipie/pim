<?php

declare(strict_types=1);

namespace App\Tests\Unit\Shared\Infrastructure\Audit;

use App\Shared\Infrastructure\Audit\CursorCodec;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * HARD-05 — encoder/decoder round-trip + invalid-input tolerance.
 *
 * The audit-log endpoints accept a client-supplied `?cursor=` query
 * param. A stale or fat-fingered value cannot 500 the endpoint —
 * decoding has to either return null (skip the cursor predicate, serve
 * the first page) or surface a structured error. We choose null so the
 * frontend survives URL mistakes silently; the test pins both halves
 * of that contract.
 */
final class CursorCodecTest extends TestCase
{
    #[Test]
    public function roundTripsCreatedAtAndId(): void
    {
        $cursor = CursorCodec::encode('2026-05-12 14:30:00', 4242);

        $decoded = CursorCodec::decode($cursor);

        self::assertNotNull($decoded);
        self::assertSame('2026-05-12 14:30:00', $decoded['t']);
        self::assertSame(4242, $decoded['i']);
    }

    #[Test]
    public function decodesNullForEmptyOrMissingCursor(): void
    {
        self::assertNull(CursorCodec::decode(null));
        self::assertNull(CursorCodec::decode(''));
    }

    #[Test]
    public function decodesNullForGibberishBase64(): void
    {
        self::assertNull(CursorCodec::decode('@@@not-base64@@@'));
    }

    #[Test]
    public function decodesNullForValidBase64ButInvalidJson(): void
    {
        self::assertNull(CursorCodec::decode(base64_encode('{not json')));
    }

    #[Test]
    public function decodesNullWhenJsonShapeIsWrong(): void
    {
        self::assertNull(CursorCodec::decode(base64_encode((string) json_encode(['t' => 'ok']))));
        self::assertNull(CursorCodec::decode(base64_encode((string) json_encode(['t' => 'ok', 'i' => 'not-int']))));
        self::assertNull(CursorCodec::decode(base64_encode((string) json_encode(['t' => 42, 'i' => 1]))));
        self::assertNull(CursorCodec::decode(base64_encode((string) json_encode([1, 2, 3]))));
    }
}
