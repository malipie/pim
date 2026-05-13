/**
 * VIEW-09 (#535) — Filter DSL shared between Smart Filter Presets,
 * Advanced Filter Panel, and URL serializer.
 *
 * Flat (single-level) form:
 *   { attr: 'brand', op: 'IN', value: ['Festo', 'Bosch'] }
 *
 * Grouped form (one level of grouping in VIEW-09; nested AND/OR/NOT
 * lands in VIEW-09b without schema changes):
 *   { operator: 'AND', conditions: [
 *     { attr: 'description.pl', op: 'IS NOT EMPTY' },
 *     { attr: 'description.en', op: 'IS EMPTY' },
 *   ]}
 *
 * Backend resolver mirrors this exactly — see FilterDslResolver.php.
 * URL serializer compresses single-level conditions into shareable
 * params (see `url-serializer.ts`); query mode falls back to a
 * base64 blob landing in VIEW-09b.
 */

export type FilterOperator =
  | '='
  | '!='
  | '≠'
  | 'IS EMPTY'
  | 'IS NOT EMPTY'
  | '<'
  | '>'
  | '<='
  | '>='
  | 'IN'
  | 'NOT IN';

export type FilterConditionValue = string | number | boolean | Array<string | number> | null;

export interface FilterCondition {
  attr: string;
  op: FilterOperator;
  value?: FilterConditionValue;
}

export interface FilterGroup {
  operator: 'AND' | 'OR';
  conditions: Array<FilterCondition | FilterGroup>;
}

export type FilterDsl = FilterCondition | FilterGroup;

/**
 * Pełna lista operatorów per typ atrybutu w VIEW-10. VIEW-09 hardcoded
 * obsługuje 6 wspólnych operatorów ({@link CORE_OPERATORS}).
 */
export const CORE_OPERATORS: readonly FilterOperator[] = [
  '=',
  '≠',
  'IN',
  'NOT IN',
  'IS EMPTY',
  'IS NOT EMPTY',
] as const;

export const FILTER_OPERATORS_BY_TYPE: Record<string, readonly FilterOperator[]> = {
  text: ['=', '≠', 'IS EMPTY', 'IS NOT EMPTY'],
  number: ['=', '≠', '<', '>', '<=', '>=', 'IS EMPTY', 'IS NOT EMPTY'],
  metric: ['=', '≠', '<', '>', '<=', '>=', 'IS EMPTY', 'IS NOT EMPTY'],
  select: ['=', '≠', 'IN', 'NOT IN', 'IS EMPTY', 'IS NOT EMPTY'],
  multiselect: ['IS EMPTY', 'IS NOT EMPTY'],
  boolean: ['='],
  relation: ['=', '≠', 'IN', 'NOT IN', 'IS EMPTY', 'IS NOT EMPTY'],
  asset: ['IS EMPTY', 'IS NOT EMPTY'],
};

/**
 * True when DSL is a grouped form (operator + conditions).
 */
export function isFilterGroup(dsl: FilterDsl): dsl is FilterGroup {
  return 'operator' in dsl && 'conditions' in dsl;
}

/**
 * Convert a flat condition list to a top-level AND group, or unwrap a
 * single condition. Used by the Advanced filter panel grid mode where
 * the editor manages a flat array, but the API/saved preset expects a
 * group.
 */
export function conditionsToDsl(
  conditions: FilterCondition[],
  operator: 'AND' | 'OR' = 'AND',
): FilterDsl | null {
  const first = conditions[0];
  if (first === undefined) return null;
  if (conditions.length === 1) return first;
  return { operator, conditions };
}

/**
 * Inverse of {@link conditionsToDsl}: flatten the top-level group into a
 * flat condition list when possible (drops grouping). Returns `null`
 * when DSL contains nested groups — the grid mode editor cannot
 * represent them (VIEW-09b query mode handles those).
 */
export function dslToFlatConditions(dsl: FilterDsl | null): FilterCondition[] | null {
  if (!dsl) return [];
  if (!isFilterGroup(dsl)) return [dsl];

  const flat: FilterCondition[] = [];
  for (const cond of dsl.conditions) {
    if (isFilterGroup(cond)) return null; // nested → query mode required
    flat.push(cond);
  }
  return flat;
}
