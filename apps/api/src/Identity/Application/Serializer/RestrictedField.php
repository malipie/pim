<?php

declare(strict_types=1);

namespace App\Identity\Application\Serializer;

/**
 * RBAC-P3-012 (#675) — value object describing the response shape per
 * attribute, per PRD §3.5.
 *
 *   - `value`     — the attribute value (already serialised by the
 *                   normaliser),
 *   - `editable`  — false when the caller can read but not write
 *                   (`view` permission); true for `edit`,
 *   - `reason`    — explanation surfaced to the frontend so it can
 *                   render the input as read-only with a tooltip
 *                   (`view_only`, `integration_visible`).
 *
 * The `restricted` permission case produces NO `RestrictedField` — the
 * filter removes the key entirely. See {@see FieldRestrictionFilter}.
 */
final readonly class RestrictedField
{
    public const string REASON_VIEW_ONLY = 'view_only';
    public const string REASON_INTEGRATION_HIDDEN = 'integration_visible';

    private function __construct(
        public mixed $value,
        public bool $editable,
        public ?string $reason,
    ) {
    }

    public static function editable(mixed $value): self
    {
        return new self($value, true, null);
    }

    public static function viewOnly(mixed $value, string $reason = self::REASON_VIEW_ONLY): self
    {
        return new self($value, false, $reason);
    }

    /**
     * @return array{value: mixed, editable: bool, reason?: string}
     */
    public function toArray(): array
    {
        $shape = ['value' => $this->value, 'editable' => $this->editable];
        if (null !== $this->reason) {
            $shape['reason'] = $this->reason;
        }

        return $shape;
    }
}
