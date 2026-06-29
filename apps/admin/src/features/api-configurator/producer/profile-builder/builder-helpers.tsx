/**
 * APIC-P4-07 — shared types + small pieces for the profile builder.
 */
export interface ObjectTypeRow {
  id: string;
  code: string;
  label?: Record<string, string> | string | null;
}

export interface BuilderAttribute {
  code: string;
  label: Record<string, string> | string | null;
  type: string;
  groupCode: string | null;
}

export interface ProfileDetail {
  id: string;
  code: string;
  name: string;
  outputFormat: string;
  objectTypeIds?: string[];
  includedAttributes?: string[];
  filters?: Record<string, unknown>;
}

/** Resolve a JSONB `{pl,en}` / string label to display text, falling back to code. */
export function labelText(
  label: Record<string, string> | string | null | undefined,
  fallback: string,
): string {
  if (typeof label === 'string' && label !== '') {
    return label;
  }
  if (label !== null && typeof label === 'object') {
    return label.pl ?? label.en ?? Object.values(label)[0] ?? fallback;
  }
  return fallback;
}

/** Slugify a profile name into a `[a-z0-9-]` code (mirrors the connection wizard). */
export function slugify(name: string): string {
  return name
    .toLowerCase()
    .normalize('NFKD')
    .replace(/[̀-ͯ]/g, '')
    .replace(/[^a-z0-9]+/g, '-')
    .replace(/^-+|-+$/g, '')
    .slice(0, 64);
}
