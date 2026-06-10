import { act, renderHook } from '@testing-library/react';
import { describe, expect, it } from 'vitest';

import type { FilterCondition, FilterGroup } from '../filter-dsl';
import { useFilterDslState } from '../use-filter-dsl-state';

const COND_A: FilterCondition = { attr: 'brand', op: '=', value: 'Festo' };
const COND_B: FilterCondition = { attr: 'enabled', op: '=', value: true };

describe('useFilterDslState', () => {
  it('starts empty and composes DSL as conditions are added', () => {
    const { result } = renderHook(() => useFilterDslState());
    expect(result.current.dsl).toBeNull();

    act(() => result.current.setConditions([COND_A]));
    expect(result.current.dsl).toEqual(COND_A);

    act(() => result.current.setConditions([COND_A, COND_B]));
    expect(result.current.dsl).toEqual({ operator: 'AND', conditions: [COND_A, COND_B] });
  });

  it('hydrates from an initial group (operator preserved)', () => {
    const initial: FilterGroup = { operator: 'OR', conditions: [COND_A, COND_B] };
    const { result } = renderHook(() => useFilterDslState(initial));
    expect(result.current.matchOperator).toBe('OR');
    expect(result.current.conditions).toEqual([COND_A, COND_B]);
  });

  it('clear() empties the composed DSL', () => {
    const { result } = renderHook(() => useFilterDslState(COND_A));
    act(() => result.current.clear());
    expect(result.current.dsl).toBeNull();
  });
});
