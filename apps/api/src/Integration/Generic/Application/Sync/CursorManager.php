<?php

declare(strict_types=1);

namespace App\Integration\Generic\Application\Sync;

use App\Integration\Generic\Domain\Entity\SyncBinding;
use App\Integration\Generic\Domain\Enum\CursorType;
use App\Integration\Generic\Domain\Repository\SyncBindingRepositoryInterface;
use DateTimeImmutable;
use Exception;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

/**
 * Moves a SyncBinding's delta-sync cursor forward, atomically and monotonically
 * (ADR-0022, epic APIC, ticket APIC-P3-03).
 *
 * The inbound handler advances the cursor once per processed batch, so the
 * persisted value always trails fully-committed work: a crash mid-batch leaves
 * the cursor at the last batch and the re-run re-pulls only that window (the
 * upsert is idempotent — no duplicates). `advance()` rejects a value that would
 * move the cursor backward (a paging glitch or out-of-order page), guarding
 * against skipped or re-processed records; an `opaque` token is accepted as-is
 * since the remote owns its ordering.
 */
final readonly class CursorManager
{
    public function __construct(
        private SyncBindingRepositoryInterface $bindings,
        private LoggerInterface $logger = new NullLogger(),
    ) {
    }

    public function current(SyncBinding $binding): ?CursorState
    {
        return CursorState::fromArray($binding->getCursor());
    }

    /**
     * Advances the binding's cursor to `$newValue` and persists it atomically.
     * Returns false (and persists nothing) when the binding has no cursor
     * configured or the new value is not strictly forward.
     */
    public function advance(SyncBinding $binding, string $newValue): bool
    {
        $state = $this->current($binding);
        if (null === $state) {
            return false;
        }

        if (!self::isForward($state->type, $state->value, $newValue)) {
            $this->logger->warning('Rejected non-monotonic cursor advance.', [
                'binding' => $binding->getId()->toRfc4122(),
                'type' => $state->type->value,
                'from' => $state->value,
                'to' => $newValue,
            ]);

            return false;
        }

        $binding->setCursor($state->withValue($newValue)->toArray());
        $this->bindings->save($binding);

        return true;
    }

    /**
     * Whether `$new` moves the cursor forward (or is the first value). Opaque
     * tokens are always accepted; comparable types require `new >= current`.
     */
    private static function isForward(CursorType $type, ?string $current, string $new): bool
    {
        if (null === $current || '' === $current || !$type->isComparable()) {
            return true;
        }

        return match ($type) {
            CursorType::IncrementalId => (int) $new >= (int) $current,
            CursorType::UpdatedAt => self::timestampForward($current, $new),
            CursorType::Opaque => true,
        };
    }

    /**
     * An unparseable new timestamp is rejected (cannot validate forward
     * progress); an unparseable stored value is treated as a reset (accept).
     */
    private static function timestampForward(string $current, string $new): bool
    {
        $newTs = self::tryTimestamp($new);
        if (null === $newTs) {
            return false;
        }

        $currentTs = self::tryTimestamp($current);

        return null === $currentTs || $newTs >= $currentTs;
    }

    private static function tryTimestamp(string $value): ?int
    {
        try {
            return new DateTimeImmutable($value)->getTimestamp();
        } catch (Exception) {
            return null;
        }
    }
}
