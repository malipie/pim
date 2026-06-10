import { describe, expect, it } from 'vitest';

import { INITIAL_WIZARD_STATE, type WizardState } from '../types';
import { hasDownstreamConfig, wizardReducer } from '../wizard-store';

const CONFIGURED: WizardState = {
  ...INITIAL_WIZARD_STATE,
  step: 2,
  format: 'csv',
  columns: ['sku', 'name'],
  filterDsl: { operator: 'AND', conditions: [] },
  targetScope: 'filter',
  dirty: true,
};

describe('wizardReducer', () => {
  it('resets steps 2-4 when the entity type changes', () => {
    const next = wizardReducer(CONFIGURED, {
      type: 'SET_ENTITY_TYPE',
      entityType: 'categories',
    });
    expect(next.entityType).toBe('categories');
    expect(next.columns).toEqual([]);
    expect(next.filterDsl).toBeNull();
    expect(next.targetScope).toBe('all');
    expect(next.step).toBe(2);
    expect(next.dirty).toBe(true);
  });

  it('is a no-op when re-selecting the same entity', () => {
    const next = wizardReducer(CONFIGURED, {
      type: 'SET_ENTITY_TYPE',
      entityType: 'product',
    });
    expect(next).toBe(CONFIGURED);
  });

  it('clamps GO_TO_STEP into 0..3', () => {
    expect(wizardReducer(CONFIGURED, { type: 'GO_TO_STEP', step: 9 }).step).toBe(3);
    expect(wizardReducer(CONFIGURED, { type: 'GO_TO_STEP', step: -2 }).step).toBe(0);
  });
});

describe('hasDownstreamConfig', () => {
  it('detects configured later steps', () => {
    expect(hasDownstreamConfig(INITIAL_WIZARD_STATE)).toBe(false);
    expect(hasDownstreamConfig(CONFIGURED)).toBe(true);
  });
});
