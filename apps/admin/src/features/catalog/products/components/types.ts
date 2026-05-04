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

export interface AttributeMeta {
  id: string;
  code: string;
  type: string;
  label: { pl?: string; en?: string };
  is_system: boolean;
  position: number;
  is_required_in_group: boolean;
  visible_when?: unknown;
}

export interface GroupMeta {
  id: string;
  code: string;
  label: { pl?: string; en?: string };
  position: number;
  attributes: AttributeMeta[];
}

export interface EffectiveAttributeGroups {
  groups: GroupMeta[];
}

export type ProductDetailMode = 'edit' | 'create';
