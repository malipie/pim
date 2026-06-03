/**
 * Legacy AttributeGroup codes that were once seeded as
 * `is_system_group=true` but are now user-managed modeling configuration:
 *
 * - `audit`     — un-seeded by #1074 / Version20260527100000
 * - `relations` — un-seeded by #1080 (MODRC-01) / Version20260528100000
 *
 * Old databases may still carry the legacy rows; the BE
 * {@link App\Catalog\Application\Command\DeleteAttributeGroup\DeleteAttributeGroupHandler}
 * allow-lists the same set so DELETE returns 204. The admin lock UX uses
 * this helper everywhere it previously special-cased `code === 'audit'`,
 * keeping a single source of truth.
 */
export const LEGACY_OPTIONAL_SYSTEM_GROUP_CODES = ['audit', 'relations'] as const;

export type LegacyOptionalSystemGroupCode = (typeof LEGACY_OPTIONAL_SYSTEM_GROUP_CODES)[number];

export function isLegacyOptionalSystemGroupCode(
  code: string | undefined | null,
): code is LegacyOptionalSystemGroupCode {
  return (
    typeof code === 'string' &&
    (LEGACY_OPTIONAL_SYSTEM_GROUP_CODES as readonly string[]).includes(code)
  );
}
