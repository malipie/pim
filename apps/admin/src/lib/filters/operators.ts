/**
 * VIEW-10 (#538) — operator metadata + i18n labels.
 *
 * Mirror of BE `FilterDslResolver::OPERATORS_BY_TYPE` plus UI-only
 * presentation hooks (i18n keys, value input variant). Centralising
 * here keeps the operator picker, value input, and chip rendering in
 * sync.
 */
import type { FilterOperator } from './filter-dsl';

export type AttributeType =
  | 'text'
  | 'wysiwyg'
  | 'number'
  | 'metric'
  | 'price'
  | 'date'
  | 'datetime'
  | 'select'
  | 'multiselect'
  | 'boolean'
  | 'relation'
  | 'reference'
  | 'asset';

export type ValueInputVariant =
  | 'none'
  | 'text'
  | 'number'
  | 'date'
  | 'select'
  | 'multiselect'
  | 'between-number'
  | 'between-date';

/**
 * Map operator → i18n key (with sensible defaultValue). Components pass
 * the result of `t(opLabelKey(op))` to render localised labels.
 */
export function opLabelKey(op: FilterOperator): { key: string; defaultValue: string } {
  const map: Record<FilterOperator, { key: string; defaultValue: string }> = {
    '=': { key: 'products.advanced_filter.operators.equals', defaultValue: '=' },
    '!=': { key: 'products.advanced_filter.operators.not_equals', defaultValue: '≠' },
    '≠': { key: 'products.advanced_filter.operators.not_equals', defaultValue: '≠' },
    'IS EMPTY': {
      key: 'products.advanced_filter.operators.is_empty',
      defaultValue: 'jest puste',
    },
    'IS NOT EMPTY': {
      key: 'products.advanced_filter.operators.is_not_empty',
      defaultValue: 'jest niepuste',
    },
    '<': { key: 'products.advanced_filter.operators.lt', defaultValue: '<' },
    '>': { key: 'products.advanced_filter.operators.gt', defaultValue: '>' },
    '<=': { key: 'products.advanced_filter.operators.lte', defaultValue: '≤' },
    '>=': { key: 'products.advanced_filter.operators.gte', defaultValue: '≥' },
    IN: { key: 'products.advanced_filter.operators.in', defaultValue: 'IN' },
    'NOT IN': { key: 'products.advanced_filter.operators.not_in', defaultValue: 'NOT IN' },
    'starts with': {
      key: 'products.advanced_filter.operators.starts_with',
      defaultValue: 'zaczyna się od',
    },
    'ends with': {
      key: 'products.advanced_filter.operators.ends_with',
      defaultValue: 'kończy się na',
    },
    contains: {
      key: 'products.advanced_filter.operators.contains',
      defaultValue: 'zawiera',
    },
    'not contains': {
      key: 'products.advanced_filter.operators.not_contains',
      defaultValue: 'nie zawiera',
    },
    between: {
      key: 'products.advanced_filter.operators.between',
      defaultValue: 'pomiędzy',
    },
    after: { key: 'products.advanced_filter.operators.after', defaultValue: 'po' },
    before: { key: 'products.advanced_filter.operators.before', defaultValue: 'przed' },
    '= TRUE': {
      key: 'products.advanced_filter.operators.is_true',
      defaultValue: 'jest prawda',
    },
    '= FALSE': {
      key: 'products.advanced_filter.operators.is_false',
      defaultValue: 'jest fałsz',
    },
  };
  return map[op];
}

/**
 * Choose the value input variant for the given attribute type + operator.
 * `none` means the operator carries its own truth (IS EMPTY etc.) — render
 * no value control.
 */
export function valueInputVariant(type: AttributeType, op: FilterOperator): ValueInputVariant {
  if (op === 'IS EMPTY' || op === 'IS NOT EMPTY' || op === '= TRUE' || op === '= FALSE') {
    return 'none';
  }
  if (op === 'between') {
    return type === 'date' || type === 'datetime' ? 'between-date' : 'between-number';
  }
  if (op === 'IN' || op === 'NOT IN') {
    return 'multiselect';
  }
  switch (type) {
    case 'number':
    case 'metric':
    case 'price':
      return 'number';
    case 'date':
    case 'datetime':
      return 'date';
    case 'select':
    case 'relation':
      return 'select';
    case 'multiselect':
      return 'multiselect';
    default:
      return 'text';
  }
}
