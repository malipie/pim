/**
 * #1209/#1210 follow-up — single source of truth for the user-creatable
 * attribute types, mirroring the backend `AttributeInput` whitelist
 * (`App\Catalog\Infrastructure\ApiPlatform\Resource\AttributeInput`).
 *
 * Every surface that lets an operator CREATE an attribute — the standalone
 * `/modeling/attributes/new` page and the create-in-group /
 * create-for-object-type dialogs — consumes this list, so a newly added type
 * (textarea/datetime/color/email/identifier shipped in #1177–#1179) appears in
 * exactly one place instead of drifting between hardcoded copies (the bug this
 * fixes: `new.tsx` was never updated and lagged the dialogs).
 *
 * Intentionally excluded:
 *   - `reference` — system-only (created_by/updated_by); the create endpoint
 *     rejects it, so offering it in a creation UI is a dead option.
 *   - `video` — not implemented in the backend enum yet (own feature ticket).
 */
export const CREATABLE_ATTRIBUTE_TYPES = [
  'text',
  'textarea',
  'identifier',
  'number',
  'select',
  'multiselect',
  'date',
  'datetime',
  'boolean',
  'asset',
  'relation',
  'price',
  'metric',
  'wysiwyg',
  'color',
  'email',
] as const;

export type CreatableAttributeType = (typeof CREATABLE_ATTRIBUTE_TYPES)[number];
