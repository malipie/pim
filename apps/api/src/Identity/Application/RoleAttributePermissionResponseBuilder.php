<?php

declare(strict_types=1);

namespace App\Identity\Application;

use App\Catalog\Contracts\Query\AttributeSummary;
use App\Identity\Domain\Entity\RoleAttributePermission;

/**
 * RBAC-P5-007 (#697) — builds the per-role, per-attribute matrix the
 * "Uprawnienia per atrybut" tab consumes.
 *
 * The wire shape groups attributes by AttributeGroup so the FE renders
 * the accordion panels without an extra grouping pass; each attribute
 * row carries the current override level (or `null` to indicate
 * "no override, fall back to the role matrix"), the localised label
 * (PL + EN), and a `type` tag the FE shows as a small badge.
 *
 * Response envelope:
 *   {
 *     "role_id":     "uuid",
 *     "groups": [
 *       {
 *         "group_id":     "uuid|null",   // null = ungrouped bucket
 *         "group_code":   "string|null",
 *         "group_label":  { "pl": "...", "en": "..." } | null,
 *         "attributes": [
 *           {
 *             "id":               "uuid",
 *             "code":             "name",
 *             "label":            { "pl": "...", "en": "..." },
 *             "type":             "text|number|...",
 *             "is_localizable":   bool,
 *             "is_required":      bool,
 *             "permission_level": "view|edit|restricted|null"
 *           }, ...
 *         ]
 *       }, ...
 *     ]
 *   }
 */
final class RoleAttributePermissionResponseBuilder
{
    /**
     * @param iterable<AttributeSummary>        $attributes
     * @param iterable<RoleAttributePermission> $overrides
     *
     * @return array{
     *     role_id: string,
     *     groups: list<array{
     *         group_id: ?string,
     *         group_code: ?string,
     *         group_label: ?array<string, string>,
     *         attributes: list<array{
     *             id: string,
     *             code: string,
     *             label: array<string, string>,
     *             type: string,
     *             is_localizable: bool,
     *             is_required: bool,
     *             permission_level: ?string
     *         }>
     *     }>
     * }
     */
    public function build(string $roleId, iterable $attributes, iterable $overrides): array
    {
        $overrideByAttr = [];
        foreach ($overrides as $override) {
            $overrideByAttr[$override->getAttributeId()->toRfc4122()] = $override->getPermissionLevel();
        }

        /**
         * @var array<string, array{
         *     group_id: ?string,
         *     group_code: ?string,
         *     group_label: ?array<string, string>,
         *     attributes: list<array{
         *         id: string,
         *         code: string,
         *         label: array<string, string>,
         *         type: string,
         *         is_localizable: bool,
         *         is_required: bool,
         *         permission_level: ?string
         *     }>
         * }> $bucketed
         */
        $bucketed = [];

        foreach ($attributes as $summary) {
            $groupKey = null === $summary->groupId ? '__ungrouped__' : $summary->groupId->toRfc4122();

            if (!isset($bucketed[$groupKey])) {
                $bucketed[$groupKey] = [
                    'group_id' => $summary->groupId?->toRfc4122(),
                    'group_code' => $summary->groupCode,
                    'group_label' => [] === $summary->groupLabel ? null : $summary->groupLabel,
                    'attributes' => [],
                ];
            }

            $attrId = $summary->id->toRfc4122();
            $bucketed[$groupKey]['attributes'][] = [
                'id' => $attrId,
                'code' => $summary->code,
                'label' => self::ensureLabelMap($summary->label),
                'type' => $summary->type,
                'is_localizable' => $summary->isLocalizable,
                'is_required' => $summary->isRequired,
                'permission_level' => $overrideByAttr[$attrId] ?? null,
            ];
        }

        return [
            'role_id' => $roleId,
            'groups' => array_values($bucketed),
        ];
    }

    /**
     * @param array<string, string> $raw
     *
     * @return array<string, string>
     */
    private static function ensureLabelMap(array $raw): array
    {
        // Guard against legacy rows missing the JSONB locale map — keep
        // the response shape stable so the FE can always read `label.pl`
        // / `label.en` without conditional fallbacks.
        return [] === $raw ? ['pl' => '', 'en' => ''] : $raw;
    }
}
