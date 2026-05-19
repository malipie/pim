/**
 * RBAC-P4-009 (#686) — frontend contract for the response shape PRD §3.5
 * spells out, mirroring the backend value object
 * {@see \App\Identity\Application\Serializer\RestrictedField} from #675.
 *
 * Backend may send a raw value (legacy / non-RBAC fields) or the
 * `{value, editable, reason?}` envelope:
 *
 *   - Edit       → `{value: …, editable: true}`            — input.
 *   - View       → `{value: …, editable: false, reason: 'view_only'}`
 *                                                          — text node.
 *   - Restricted → key absent entirely (the field is hidden by the
 *                  serializer; frontend never sees it).
 *
 * The renderer mode is decided once per field via {@link decideFieldMode}
 * so the form components do not need to repeat the discriminator
 * inline. PRD §3.5 anticipates the response shape — the frontend may
 * still see a raw value during the Phase 6 retrofit window while the
 * backend serializer wiring lands per endpoint.
 */
export interface RestrictedFieldEnvelope<T = unknown> {
  value: T;
  editable: boolean;
  reason?: string;
}

export type RestrictedFieldValue<T = unknown> = RestrictedFieldEnvelope<T> | T | undefined;

export type FieldRenderMode = 'input' | 'text' | 'hidden';

export function isRestrictedFieldEnvelope<T>(
  candidate: unknown,
): candidate is RestrictedFieldEnvelope<T> {
  if (typeof candidate !== 'object' || candidate === null) {
    return false;
  }
  const record = candidate as Record<string, unknown>;
  return 'value' in record && typeof record.editable === 'boolean';
}

/**
 * Resolves the renderer mode + raw value from a wire payload. Callers
 * pass the JSON value verbatim — no upstream type narrowing required.
 *
 *   - envelope `{editable: true}`  → `'input'`,
 *   - envelope `{editable: false}` → `'text'`,
 *   - raw value (legacy non-RBAC) → `'input'` if defined,
 *   - undefined / missing key     → `'hidden'` (field omitted).
 */
export function decideFieldMode<T>(payload: RestrictedFieldValue<T>): {
  mode: FieldRenderMode;
  value: T | undefined;
  reason?: string;
} {
  if (payload === undefined) {
    return { mode: 'hidden', value: undefined };
  }
  if (isRestrictedFieldEnvelope<T>(payload)) {
    return {
      mode: payload.editable ? 'input' : 'text',
      value: payload.value,
      reason: payload.reason,
    };
  }
  // Legacy raw value during the Phase 6 retrofit window — treat as input.
  return { mode: 'input', value: payload };
}
