/**
 * `attributes_indexed` is the denormalised JSONB cache that the API exposes
 * as `attributesIndexed` (per-product / per-category / per-asset). Each
 * attribute key maps to an envelope `{ value, locale?, channel?, ... }` so
 * the backend can carry channel/locale overlays alongside the global
 * reading. The shape is canonical — see `AttributesIndexedRebuilder`
 * (apps/api/src/Catalog/Application/AttributesIndexedRebuilder.php) and
 * `ObjectValue::getValue()` for the writer side.
 *
 * The admin reads this map in many places (lists, detail page, variants
 * tab, asset picker, category trees). Treating the envelope as a plain
 * value silently fell back to the row code (SKU) every time, so PATCHes
 * looked like no-ops in the UI even though the backend persisted them.
 *
 * `unwrapAttributesIndexed` lifts `.value` to the top so consumers can
 * read attribute readings with the same `attrs.name` ergonomics they
 * already use. Non-envelope entries pass through unchanged so the helper
 * is safe to apply over data that has already been flattened.
 */
export function unwrapAttributesIndexed(
  raw: Record<string, unknown> | null | undefined,
): Record<string, unknown> {
  if (raw === null || raw === undefined) return {};
  const out: Record<string, unknown> = {};
  for (const [key, entry] of Object.entries(raw)) {
    if (entry !== null && typeof entry === 'object' && !Array.isArray(entry) && 'value' in entry) {
      out[key] = (entry as { value: unknown }).value;
    } else {
      out[key] = entry;
    }
  }
  return out;
}
