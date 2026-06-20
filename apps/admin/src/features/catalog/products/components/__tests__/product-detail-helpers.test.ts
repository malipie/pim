import { describe, expect, it } from 'vitest';

import { isAttributeRequired } from '../product-detail-helpers';
import type { AttributeMeta } from '../types';

function attr(overrides: Partial<AttributeMeta>): AttributeMeta {
  return {
    id: 'a1',
    code: 'demo',
    type: 'text',
    label: { pl: 'Demo', en: 'Demo' },
    is_system: false,
    position: 0,
    is_required_in_group: false,
    ...overrides,
  };
}

/**
 * #1673 — the save guard (collectRequiredViolations) and the attr-row asterisk
 * share this single predicate, so the two can never drift apart again. Before
 * #1673 only the global `is_required` flag blocked saving; a field required
 * only within its group showed the asterisk but saved while empty.
 */
describe('isAttributeRequired', () => {
  it('is required when globally is_required', () => {
    expect(isAttributeRequired(attr({ is_required: true }))).toBe(true);
  });

  it('is required when required within its group (the #1673 case)', () => {
    expect(isAttributeRequired(attr({ is_required: false, is_required_in_group: true }))).toBe(
      true,
    );
  });

  it('is required when both flags are set', () => {
    expect(isAttributeRequired(attr({ is_required: true, is_required_in_group: true }))).toBe(true);
  });

  it('is not required when neither flag is set', () => {
    expect(isAttributeRequired(attr({ is_required: false, is_required_in_group: false }))).toBe(
      false,
    );
  });

  it('is not required when is_required is absent and the group flag is false', () => {
    expect(isAttributeRequired(attr({ is_required_in_group: false }))).toBe(false);
  });

  it('is never required for booleans, even with both flags (unchecked = the value false)', () => {
    expect(
      isAttributeRequired(attr({ type: 'boolean', is_required: true, is_required_in_group: true })),
    ).toBe(false);
  });
});
