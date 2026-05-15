import { useEffect, useState } from 'react';

import { jsonFetch } from '@/lib/http';

import type { ColumnGroup } from './ColumnPicker';
import { BUILT_IN_COLUMN_GROUPS } from './ColumnPicker';

interface AttributeRow {
  id: string;
  code: string;
  label: Record<string, string>;
  type: string;
  position?: number;
  localizable?: boolean;
  scopable?: boolean;
  system?: boolean;
  groupId?: string | null;
  group?: { id?: string; code?: string } | null;
}

interface AttributeGroupRow {
  id: string;
  code: string;
  label: Record<string, string>;
  position?: number;
}

interface WorkspaceRow {
  enabledLocales: string[];
  primaryLocale: string;
}

export interface ExportColumnCatalog {
  groups: ColumnGroup[];
  enabledLocales: string[];
  isLoading: boolean;
  error: Error | null;
}

/**
 * Fetch + assemble the full column catalog used by the ExportModal
 * picker (EXP-11) + ExportNewPage form (EXP-12).
 *
 * Layout (PRD §13.1):
 *   - Built-ins first (sku, parent_sku, category, status, …) so the
 *     muscle-memory keys land at the top of the picker.
 *   - One section per AttributeGroup, sorted by group position.
 *   - "Inne" catch-all for attributes that belong to no group.
 *   - For `localizable=true` attributes the picker exposes one row per
 *     enabled locale (key `description.pl`, `description.en`, …) so the
 *     export contract matches the ExportBuilder column resolver from
 *     EXP-03 (which splits on `.` for locale narrowing). Non-localizable
 *     attributes ship a single key (their code).
 *
 * Scopable attributes (`scopable=true`) ship a single key in MVP —
 * per-channel variants land when the modal grows a channel selector
 * (PRD §6.1 Faza 1 candidate). The picker's contract stays stable.
 */
export function useExportColumnCatalog(): ExportColumnCatalog {
  const [attrs, setAttrs] = useState<AttributeRow[]>([]);
  const [attrGroups, setAttrGroups] = useState<AttributeGroupRow[]>([]);
  const [workspace, setWorkspace] = useState<WorkspaceRow | null>(null);
  const [isLoading, setIsLoading] = useState(true);
  const [error, setError] = useState<Error | null>(null);

  useEffect(() => {
    let cancelled = false;
    setIsLoading(true);
    setError(null);

    void (async () => {
      try {
        const [attrsResponse, groupsResponse, workspaceResponse] = await Promise.all([
          jsonFetch<AttributeRow[] | { member?: AttributeRow[] }>(
            '/api/attributes?itemsPerPage=500',
          ),
          jsonFetch<AttributeGroupRow[] | { member?: AttributeGroupRow[] }>(
            '/api/attribute_groups?itemsPerPage=100',
          ),
          jsonFetch<WorkspaceRow>('/api/workspaces/current'),
        ]);

        if (cancelled) return;

        const attrsList = Array.isArray(attrsResponse)
          ? attrsResponse
          : (attrsResponse.member ?? []);
        const groupsList = Array.isArray(groupsResponse)
          ? groupsResponse
          : (groupsResponse.member ?? []);

        setAttrs(attrsList);
        setAttrGroups(groupsList);
        setWorkspace(workspaceResponse);
      } catch (err) {
        if (!cancelled) {
          setError(err instanceof Error ? err : new Error(String(err)));
        }
      } finally {
        if (!cancelled) {
          setIsLoading(false);
        }
      }
    })();

    return () => {
      cancelled = true;
    };
  }, []);

  const enabledLocales = workspace?.enabledLocales ?? ['pl', 'en'];

  const groups: ColumnGroup[] = [
    ...BUILT_IN_COLUMN_GROUPS,
    ...buildAttributeGroups(attrs, attrGroups, enabledLocales),
  ];

  return { groups, enabledLocales, isLoading, error };
}

/**
 * Bucket attributes by their AttributeGroup (or "Inne" for orphans),
 * sort groups by position, and inside each group sort attributes by
 * (position, code). Localizable attributes fan out into one entry per
 * enabled locale.
 */
function buildAttributeGroups(
  attrs: AttributeRow[],
  groups: AttributeGroupRow[],
  enabledLocales: string[],
): ColumnGroup[] {
  const groupsByCode = new Map<string, AttributeGroupRow>();
  for (const g of groups) groupsByCode.set(g.code, g);

  const byBucket = new Map<string, AttributeRow[]>();
  for (const attr of attrs) {
    const bucket = resolveBucketCode(attr, groupsByCode);
    const existing = byBucket.get(bucket);
    if (existing === undefined) {
      byBucket.set(bucket, [attr]);
    } else {
      existing.push(attr);
    }
  }

  const result: ColumnGroup[] = [];
  const sortedGroups = [...groups].sort(
    (a, b) => (a.position ?? 0) - (b.position ?? 0) || a.code.localeCompare(b.code),
  );

  for (const group of sortedGroups) {
    const bucket = byBucket.get(group.code);
    if (bucket === undefined || bucket.length === 0) continue;
    result.push({
      id: `attr-group-${group.code}`,
      labelKey: `exports.column_picker.group_attr_${group.code}`,
      defaultLabel: localizedLabel(group.label, 'pl') ?? group.code,
      columns: bucket.flatMap((attr) => attrToOptions(attr, enabledLocales)),
    });
  }

  const orphans = byBucket.get('__none__') ?? [];
  if (orphans.length > 0) {
    result.push({
      id: 'attr-group-other',
      labelKey: 'exports.column_picker.group_other',
      defaultLabel: 'Inne atrybuty',
      columns: orphans.flatMap((attr) => attrToOptions(attr, enabledLocales)),
    });
  }

  return result;
}

function resolveBucketCode(
  attr: AttributeRow,
  groupsByCode: Map<string, AttributeGroupRow>,
): string {
  const code = attr.group?.code;
  if (typeof code === 'string' && groupsByCode.has(code)) return code;
  return '__none__';
}

function attrToOptions(
  attr: AttributeRow,
  enabledLocales: string[],
): Array<{ key: string; labelKey: string; defaultLabel: string }> {
  const baseLabel = localizedLabel(attr.label, 'pl') ?? attr.code;
  if (attr.localizable === true && enabledLocales.length > 0) {
    return enabledLocales.map((locale) => ({
      key: `${attr.code}.${locale}`,
      labelKey: `exports.columns.${attr.code}.${locale}`,
      defaultLabel: `${baseLabel} [${locale}]`,
    }));
  }
  return [
    {
      key: attr.code,
      labelKey: `exports.columns.${attr.code}`,
      defaultLabel: baseLabel,
    },
  ];
}

function localizedLabel(label: Record<string, string> | undefined, locale: string): string | null {
  if (label === undefined) return null;
  const value = label[locale];
  if (typeof value === 'string' && value.length > 0) return value;
  const fallback = Object.values(label).find((v) => typeof v === 'string' && v.length > 0);
  return fallback ?? null;
}
