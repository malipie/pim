import { useMemo, useState } from 'react';

import {
  conditionsToDsl,
  dslToFlatConditions,
  type FilterCondition,
  type FilterDsl,
  isFilterGroup,
} from './filter-dsl';

export interface FilterDslState {
  conditions: FilterCondition[];
  setConditions: (conditions: FilterCondition[]) => void;
  matchOperator: 'AND' | 'OR';
  setMatchOperator: (operator: 'AND' | 'OR') => void;
  /** Composed DSL (single condition stays unwrapped) — `null` when empty. */
  dsl: FilterDsl | null;
  clear: () => void;
}

/**
 * EXR-10 — shared state holder for `AdvancedFilterPanel` (Single Source
 * of Truth for filtering): the universal list page and the export
 * wizard hold the same conditions/operator pair and derive the composed
 * FilterDSL from one place. The panel itself stays fully props-driven.
 */
export function useFilterDslState(initial?: FilterDsl | null): FilterDslState {
  const [conditions, setConditions] = useState<FilterCondition[]>(
    () => dslToFlatConditions(initial ?? null) ?? [],
  );
  const [matchOperator, setMatchOperator] = useState<'AND' | 'OR'>(
    initial && isFilterGroup(initial) ? initial.operator : 'AND',
  );

  const dsl = useMemo(
    () => conditionsToDsl(conditions, matchOperator),
    [conditions, matchOperator],
  );

  return {
    conditions,
    setConditions,
    matchOperator,
    setMatchOperator,
    dsl,
    clear: () => setConditions([]),
  };
}
