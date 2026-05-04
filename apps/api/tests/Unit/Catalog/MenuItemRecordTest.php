<?php

declare(strict_types=1);

namespace App\Tests\Unit\Identity;

use App\Catalog\Domain\Value\MenuItemRecord;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Uid\Uuid;

/**
 * VIEW-08 (#427) — value-object invariants for `MenuConfiguration.items`.
 */
final class MenuItemRecordTest extends TestCase
{
    #[Test]
    public function systemKindAcceptsSlugRef(): void
    {
        $record = new MenuItemRecord(MenuItemRecord::KIND_SYSTEM, 'dashboard', 0, true);
        self::assertSame('dashboard', $record->ref);
        self::assertSame(MenuItemRecord::KIND_SYSTEM, $record->kind);
    }

    #[Test]
    public function objectTypeKindRejectsNonUuid(): void
    {
        self::expectException(InvalidArgumentException::class);
        self::expectExceptionMessage('UUID');

        new MenuItemRecord(MenuItemRecord::KIND_OBJECT_TYPE, 'not-a-uuid', 0, true);
    }

    #[Test]
    public function objectTypeKindAcceptsUuidRef(): void
    {
        $uuid = Uuid::v7()->toRfc4122();
        $record = new MenuItemRecord(MenuItemRecord::KIND_OBJECT_TYPE, $uuid, 5, false);
        self::assertSame($uuid, $record->ref);
        self::assertSame(5, $record->position);
        self::assertFalse($record->visible);
    }

    #[Test]
    public function unknownKindIsRejected(): void
    {
        self::expectException(InvalidArgumentException::class);
        self::expectExceptionMessage('kind');

        new MenuItemRecord('weird', 'dashboard', 0, true);
    }

    #[Test]
    public function negativePositionIsRejected(): void
    {
        self::expectException(InvalidArgumentException::class);
        self::expectExceptionMessage('position');

        new MenuItemRecord(MenuItemRecord::KIND_SYSTEM, 'dashboard', -1, true);
    }

    #[Test]
    public function emptyRefIsRejected(): void
    {
        self::expectException(InvalidArgumentException::class);
        self::expectExceptionMessage('ref');

        new MenuItemRecord(MenuItemRecord::KIND_SYSTEM, '   ', 0, true);
    }

    #[Test]
    public function toArrayAndFromArrayRoundTrip(): void
    {
        $record = new MenuItemRecord(MenuItemRecord::KIND_SYSTEM, 'multimedia', 3, true);
        $payload = $record->toArray();
        self::assertSame(
            ['kind' => 'system', 'ref' => 'multimedia', 'position' => 3, 'visible' => true],
            $payload,
        );

        $restored = MenuItemRecord::fromArray($payload);
        self::assertEquals($record, $restored);
    }

    #[Test]
    public function fromArrayRequiresAllKeys(): void
    {
        self::expectException(InvalidArgumentException::class);

        MenuItemRecord::fromArray(['kind' => 'system', 'ref' => 'x']);
    }

    #[Test]
    public function withMutatorsReturnNewInstancesAndPreserveOtherFields(): void
    {
        $record = new MenuItemRecord(MenuItemRecord::KIND_SYSTEM, 'dashboard', 0, true);

        $moved = $record->withPosition(7);
        self::assertSame(7, $moved->position);
        self::assertSame(0, $record->position, 'original is immutable');

        $hidden = $record->withVisible(false);
        self::assertFalse($hidden->visible);
        self::assertTrue($record->visible);
    }
}
