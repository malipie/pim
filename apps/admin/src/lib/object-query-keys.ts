/**
 * #1348/#1351 unification — single query-key family for one catalog
 * object of ANY kind (product, category, asset, custom). Every reader
 * and every invalidation MUST go through this factory so cache busting
 * keeps working when components are reused across /products/{id} and
 * /objects/{slug}/{id}. Prefix-invalidating `objectKeys.all(id)` hits
 * every scoped sub-key below.
 */
export const objectKeys = {
  all: (id: string) => ['object', id] as const,
  scoped: (id: string, locale: string, channel: string | null) =>
    ['object', id, locale, channel] as const,
  categories: (id: string) => ['object', id, 'categories'] as const,
  effectiveGroups: (id: string) => ['object', id, 'effective-attribute-groups'] as const,
  scopeStatus: (id: string, locale: string, channel: string | null) =>
    ['object', id, 'scope-status', locale, channel] as const,
  schemaDrift: (id: string) => ['object', id, 'schema-drift'] as const,
  channelPlacements: (id: string) => ['object', id, 'channel-placements'] as const,
};
