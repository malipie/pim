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

interface ChannelRow {
  code: string;
  label?: Record<string, string>;
}

export interface ExportColumnCatalog {
  groups: ColumnGroup[];
  enabledLocales: string[];
  enabledChannels: string[];
  /** #1267 — codes of scopable attributes; their bare `code` column is the
   *  global (channel=null) value, gated by the "Wszystkie" channel option. */
  scopableCodes: string[];
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
  const [channels, setChannels] = useState<ChannelRow[]>([]);
  const [isLoading, setIsLoading] = useState(true);
  const [error, setError] = useState<Error | null>(null);

  useEffect(() => {
    let cancelled = false;
    setIsLoading(true);
    setError(null);

    void (async () => {
      // Promise.allSettled — partial failures are OK. Attribute catalog
      // is the critical one (the rest are nice-to-have for grouping +
      // locale fan-out). The hook only flips `error` if attributes
      // themselves cannot load; groups + workspace silently fall back to
      // their defaults so the modal still ships a useful picker.
      const [attrsResult, groupsResult, workspaceResult, channelsResult] = await Promise.allSettled(
        [
          jsonFetch<AttributeRow[] | { member?: AttributeRow[] }>(
            '/api/attributes?itemsPerPage=500',
          ),
          jsonFetch<AttributeGroupRow[] | { member?: AttributeGroupRow[] }>(
            '/api/attribute_groups?itemsPerPage=100',
          ),
          jsonFetch<WorkspaceRow>('/api/workspaces/current'),
          jsonFetch<ChannelRow[] | { member?: ChannelRow[] }>('/api/channels?itemsPerPage=50'),
        ],
      );

      if (cancelled) return;

      if (attrsResult.status === 'fulfilled') {
        const value = attrsResult.value;
        const list = Array.isArray(value) ? value : (value.member ?? []);
        setAttrs(list);
      } else {
        // eslint-disable-next-line no-console
        console.warn('export-column-catalog: attributes fetch failed', attrsResult.reason);
        setAttrs([]);
        setError(
          attrsResult.reason instanceof Error
            ? attrsResult.reason
            : new Error(String(attrsResult.reason)),
        );
      }

      if (groupsResult.status === 'fulfilled') {
        const value = groupsResult.value;
        const list = Array.isArray(value) ? value : (value.member ?? []);
        setAttrGroups(list);
      } else {
        // eslint-disable-next-line no-console
        console.warn(
          'export-column-catalog: attribute_groups fetch failed (degrading to "Inne" bucket)',
          groupsResult.reason,
        );
        setAttrGroups([]);
      }

      if (workspaceResult.status === 'fulfilled') {
        setWorkspace(workspaceResult.value);
      } else {
        // eslint-disable-next-line no-console
        console.warn(
          'export-column-catalog: workspace fetch failed (using default locales pl/en)',
          workspaceResult.reason,
        );
        setWorkspace(null);
      }

      if (channelsResult.status === 'fulfilled') {
        const value = channelsResult.value;
        const list = Array.isArray(value) ? value : (value.member ?? []);
        setChannels(list);
      } else {
        // eslint-disable-next-line no-console
        console.warn(
          'export-column-catalog: channels fetch failed (scopable fan-out disabled)',
          channelsResult.reason,
        );
        setChannels([]);
      }

      setIsLoading(false);
    })();

    return () => {
      cancelled = true;
    };
  }, []);

  const enabledLocales = workspace?.enabledLocales ?? ['pl', 'en'];
  const enabledChannels = channels.map((c) => c.code);
  const scopableCodes = attrs.filter((a) => a.scopable === true).map((a) => a.code);

  const groups: ColumnGroup[] = [
    ...BUILT_IN_COLUMN_GROUPS,
    ...buildAttributeGroups(attrs, attrGroups, enabledLocales, enabledChannels),
  ];

  return { groups, enabledLocales, enabledChannels, scopableCodes, isLoading, error };
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
  enabledChannels: string[] = [],
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
      columns: bucket.flatMap((attr) => attrToOptions(attr, enabledLocales, enabledChannels)),
    });
  }

  const orphans = byBucket.get('__none__') ?? [];
  if (orphans.length > 0) {
    result.push({
      id: 'attr-group-other',
      labelKey: 'exports.column_picker.group_other',
      defaultLabel: 'Inne atrybuty',
      columns: orphans.flatMap((attr) => attrToOptions(attr, enabledLocales, enabledChannels)),
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
  enabledChannels: string[] = [],
): Array<{ key: string; labelKey: string; defaultLabel: string }> {
  const baseLabel = localizedLabel(attr.label, 'pl') ?? attr.code;
  const isLoc = attr.localizable === true && enabledLocales.length > 0;
  const isScop = attr.scopable === true && enabledChannels.length > 0;

  const bare = {
    key: attr.code,
    labelKey: `exports.columns.${attr.code}`,
    defaultLabel: baseLabel,
  };
  const localeCols = enabledLocales.map((locale) => ({
    key: `${attr.code}.${locale}`,
    labelKey: `exports.columns.${attr.code}.${locale}`,
    defaultLabel: `${baseLabel} [${locale}]`,
  }));
  const channelCols = enabledChannels.map((channel) => ({
    key: `${attr.code}.${channel}`,
    labelKey: `exports.columns.${attr.code}.${channel}`,
    defaultLabel: `${baseLabel} [${channel}]`,
  }));

  // #1271 — an attribute that is BOTH localizable and scopable carries a
  // global value, per-locale values, AND per-channel overrides. Emit the
  // bare `code` (global) + per-locale + per-channel so none of the three
  // scopes is dropped. Previously the localizable branch won and only the
  // (often empty) per-locale columns shipped, hiding the global + channel
  // values entirely.
  if (isLoc && isScop) {
    return [bare, ...localeCols, ...channelCols];
  }
  // Pure localizable: the primary locale === the global value, so only the
  // per-locale columns ship (no redundant bare column).
  if (isLoc) {
    return localeCols;
  }
  // #1245 / #1267 — pure scopable: bare global (gated by "Wszystkie") +
  // per-channel overrides.
  if (isScop) {
    return [bare, ...channelCols];
  }
  return [bare];
}

function localizedLabel(label: Record<string, string> | undefined, locale: string): string | null {
  if (label === undefined) return null;
  const value = label[locale];
  if (typeof value === 'string' && value.length > 0) return value;
  const fallback = Object.values(label).find((v) => typeof v === 'string' && v.length > 0);
  return fallback ?? null;
}
