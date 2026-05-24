/**
 * Shared types for the VIEW-07 product detail/create page.
 *
 * Backend contract reference:
 *   GET /api/products/{id} → CatalogObject (kind=product)
 *   GET /api/products/{id}/effective-attribute-groups → { groups: [...] }
 */

export type ProductLocale = 'pl' | 'en' | 'de' | 'cs';

export const PRODUCT_LOCALES: readonly ProductLocale[] = ['pl', 'en', 'de', 'cs'] as const;

export type ProductChannel = 'shopify' | 'baselinker' | 'allegro';

export const PRODUCT_CHANNELS: readonly ProductChannel[] = [
  'shopify',
  'baselinker',
  'allegro',
] as const;

export interface CatalogObjectDto {
  id: string;
  code: string;
  enabled?: boolean;
  status?: string;
  kind?: string;
  completeness?: { pct?: number; missing?: string[] } | null;
  completenessPct?: number;
  syncStatusAggregate?: 'gray' | 'green' | 'yellow' | 'red' | string;
  attributesIndexed?: Record<string, unknown>;
  createdAt?: string;
  updatedAt?: string;
  objectType?: { id?: string; code?: string; name?: { pl?: string; en?: string } } | null;
}

/**
 * One choice for a `select` / `multiselect` Attribute. Backend ships this
 * shape via `effective-attribute-groups` so the detail page can render a
 * proper picker instead of a free-text input — see
 * `App\Catalog\Presentation\Controller\ProductReadEndpointsController::serializeAttribute`.
 */
export interface AttributeOptionMeta {
  code: string;
  label: { pl?: string; en?: string };
  color?: string | null;
  is_default?: boolean;
  is_deprecated?: boolean;
}

export interface AttributeMeta {
  id: string;
  code: string;
  type: string;
  label: { pl?: string; en?: string };
  is_system: boolean;
  position: number;
  is_required_in_group: boolean;
  visible_when?: unknown;
  /**
   * Populated by the backend only for `select` / `multiselect` types
   * (`AttributeType::usesOptions()`). Other types omit the field
   * entirely; treat `undefined` as "no predefined values, use a free
   * input".
   */
  options?: AttributeOptionMeta[];
}

export interface GroupMeta {
  id: string;
  code: string;
  label: { pl?: string; en?: string };
  position: number;
  /**
   * MODR-01 (#923) — `tab` renders the group as its own tab on the
   * product detail page; `stacked` renders it as an inline section under
   * the default "Attributes" tab. Falls back to `tab` when the backend
   * predates the column.
   */
  display_mode?: 'tab' | 'stacked';
  attributes: AttributeMeta[];
}

export interface EffectiveAttributeGroups {
  groups: GroupMeta[];
}

export type ProductDetailMode = 'edit' | 'create';

/**
 * Sentinel UUID returned by `GET /api/products/{id}/effective-attribute-groups`
 * for the synthetic "default" bucket holding ObjectType-attached attributes
 * that are not declared in any AttributeGroup. The frontend matches this id
 * verbatim to (a) skip the bucket in the "Effective model" sidebar listing,
 * (b) render its body the same way every other group renders.
 *
 * Backend constant lives in
 * `App\Catalog\Presentation\Controller\ProductReadEndpointsController::SYNTHETIC_DEFAULT_GROUP_ID`.
 */
export const SYNTHETIC_DEFAULT_GROUP_ID = '00000000-0000-0000-0000-000000000000';
