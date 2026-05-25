import { Plus, Search, X } from 'lucide-react';
import { useState } from 'react';
import { useTranslation } from 'react-i18next';

import { EmptyStateObject } from '@/components/objects/empty-state-object';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import {
  Table,
  TableBody,
  TableCell,
  TableHead,
  TableHeader,
  TableRow,
} from '@/components/ui/table';
import { type ListSchemaColumn, useListSchema } from '@/hooks/use-list-schema';
import { type ObjectListItem, useObjectList } from '@/hooks/use-object-list';
import { HttpError } from '@/lib/http';
import { useDebouncedCallback } from '@/lib/use-debounced-callback';

/**
 * ULV-06 (#988) — universal list view component for any ObjectType.
 *
 * Props:
 *   - `objectTypeId` — UUID of the ObjectType to list. Drives the
 *     list-schema fetch (columns, capability flags) and the
 *     /api/objects?objectType= query.
 *   - `onCreate` (optional) — handler for the empty state CTA. ULV-08
 *     (routing) wires it to the per-ObjectType create wizard.
 *
 * MVP scope (per ULV epic prioritisation):
 *   - System columns + attribute columns (sorted by list_position) render
 *     from the list-schema response — ULV-04b already strips restricted
 *     attributes server-side.
 *   - Cursor pagination via AP4 `id[lt]` / `id[gt]` advertised by the
 *     ObjectsCollection IriTemplate.
 *   - Search (`?q=`), AdvancedFilterBuilder, ExcelLikeGrid, SavedViewsRail,
 *     bulk action toolbar, conditional features per capability flag —
 *     wired iteratively in ULV-07, ULV-08, ULV-09.
 *
 * Polish-language strings via i18next; English fallback uses
 * `defaultValue` per project convention (see `EmptyStateProducts`).
 */
export interface ObjectListViewProps {
  objectTypeId: string;
  onCreate?: () => void;
}

function readHiddenColumns(lsKey: string): Set<string> {
  if (typeof window === 'undefined') {
    return new Set();
  }
  try {
    const raw = window.localStorage.getItem(lsKey);
    if (raw === null) {
      return new Set();
    }
    const parsed = JSON.parse(raw);
    if (!Array.isArray(parsed)) {
      return new Set();
    }
    return new Set(parsed.filter((v): v is string => typeof v === 'string'));
  } catch {
    return new Set();
  }
}

const STATUS_OPTIONS = ['', 'published', 'draft', 'archived'] as const;
type StatusOption = (typeof STATUS_OPTIONS)[number];

export function ObjectListView({ objectTypeId, onCreate }: ObjectListViewProps) {
  const { t, i18n } = useTranslation();
  const locale = i18n.language.split('-')[0] ?? 'en';
  const schemaQuery = useListSchema(objectTypeId);
  const [cursorAfter, setCursorAfter] = useState<string | undefined>(undefined);
  // #1012 — search + status filter. Search debounces 300ms before
  // firing /api/objects?sku= (substring on code via existing SkuFilter);
  // status is a plain enum dropdown matching the StatusFilter values.
  const [searchInput, setSearchInput] = useState('');
  const [appliedSearch, setAppliedSearch] = useState('');
  const [statusFilter, setStatusFilter] = useState<StatusOption>('');
  const applySearch = useDebouncedCallback((value: string) => {
    setAppliedSearch(value.trim());
    setCursorAfter(undefined);
  }, 300);

  const listQuery = useObjectList({
    objectTypeId,
    itemsPerPage: 30,
    cursorAfter,
    query: appliedSearch.length > 0 ? appliedSearch : undefined,
    status: statusFilter.length > 0 ? statusFilter : undefined,
  });

  if (schemaQuery.isError) {
    const message =
      schemaQuery.error instanceof HttpError && schemaQuery.error.status === 404
        ? t('object_list.errors.not_found', {
            defaultValue: 'ObjectType not found in your tenant.',
          })
        : t('object_list.errors.schema_fetch', {
            defaultValue: 'Could not load list schema.',
          });

    return (
      <div className="rounded border border-destructive bg-destructive/5 p-6 text-sm text-destructive">
        {message}
      </div>
    );
  }

  if (!schemaQuery.data) {
    return (
      <div
        className="flex h-64 items-center justify-center text-sm text-muted-foreground"
        aria-busy="true"
      >
        {t('object_list.loading_schema', { defaultValue: 'Loading schema…' })}
      </div>
    );
  }

  const { objectType, columns } = schemaQuery.data;
  const typeLabel = objectType.label[locale] ?? objectType.label.en ?? objectType.code;
  const items = listQuery.data?.member ?? [];
  const totalItems = listQuery.data?.totalItems ?? 0;
  const next = listQuery.data?.view?.next;
  // ULV-07 (#989) — column visibility persists per (user, objectTypeId)
  // in `localStorage`. The full Saved-Views override layer (per-view
  // column overrides shared across the org) stays deferred to a
  // follow-up; this slice ships per-user local persistence so column
  // ordering / hiding survives reloads without backend round trips.
  const lsKey = `pim.objectList.columns.${objectType.id}`;
  const hiddenKeys = readHiddenColumns(lsKey);
  const visibleColumns = columns.filter((c) => !hiddenKeys.has(c.key));
  const systemColumns = columns.filter((c) => c.system).length;
  const attributeColumns = columns.length - systemColumns;

  const renderCell = (column: ListSchemaColumn, item: ObjectListItem) => {
    if (column.system) {
      switch (column.key) {
        case 'code':
          return <span className="font-mono text-xs">{item.code}</span>;
        case 'status':
          return (
            <span
              className={
                item.status === 'published'
                  ? 'rounded bg-emerald-100 px-2 py-0.5 text-xs text-emerald-900'
                  : 'rounded bg-muted px-2 py-0.5 text-xs text-muted-foreground'
              }
            >
              {item.status}
            </span>
          );
        case 'completeness':
          return (
            <span className="text-xs">
              {typeof item.completenessPct === 'number' ? `${item.completenessPct}%` : '—'}
            </span>
          );
        case 'updatedAt':
          return (
            <span className="text-xs text-muted-foreground">
              {item.updatedAt ? new Date(item.updatedAt).toLocaleString(locale) : '—'}
            </span>
          );
        default:
          return null;
      }
    }

    const raw = item.attributesIndexed?.[column.key];
    if (raw === undefined || raw === null) {
      return <span className="text-muted-foreground">—</span>;
    }
    if (typeof raw === 'string' || typeof raw === 'number' || typeof raw === 'boolean') {
      return <span>{String(raw)}</span>;
    }
    if (
      typeof raw === 'object' &&
      raw !== null &&
      'value' in raw &&
      (typeof raw.value === 'string' ||
        typeof raw.value === 'number' ||
        typeof raw.value === 'boolean')
    ) {
      return <span>{String(raw.value)}</span>;
    }

    return (
      <span className="text-xs text-muted-foreground">{JSON.stringify(raw).slice(0, 40)}</span>
    );
  };

  const noResultsForFilters =
    totalItems === 0 &&
    !listQuery.isLoading &&
    (appliedSearch.length > 0 || statusFilter.length > 0);
  const trulyEmpty = totalItems === 0 && !listQuery.isLoading && !noResultsForFilters;

  if (trulyEmpty) {
    return (
      <div className="space-y-4">
        <header className="flex items-center justify-between">
          <h1 className="text-2xl font-semibold">{typeLabel}</h1>
          {onCreate !== undefined ? (
            <Button onClick={onCreate}>
              <Plus className="size-4" />
              {t('object_list.cta_create', { defaultValue: 'Create' })}
            </Button>
          ) : null}
        </header>
        <EmptyStateObject objectType={objectType} onCreate={onCreate} />
      </div>
    );
  }

  return (
    <div className="space-y-4">
      <header className="flex items-center justify-between">
        <div className="space-y-1">
          <div className="flex items-center gap-2">
            <h1 className="text-2xl font-semibold">{typeLabel}</h1>
            {/* ULV-09 (#991) — capability badges: variants + categorizable
                surface on the header so operators see at a glance whether
                this ObjectType opts into the conditional features (variant
                tree, category sidebar). Each badge is muted; the actual
                feature wiring rides in dedicated tickets (variant column
                expander + category filter sidebar) which the ULV-09
                minimum-viable slice keeps deferred so the badge ships now. */}
            {objectType.has_variants ? (
              <span className="rounded bg-violet-100 px-2 py-0.5 text-xs text-violet-900">
                {t('object_list.capability.variants', { defaultValue: 'Variants' })}
              </span>
            ) : null}
            {objectType.is_categorizable ? (
              <span className="rounded bg-sky-100 px-2 py-0.5 text-xs text-sky-900">
                {t('object_list.capability.categorizable', { defaultValue: 'Categorized' })}
              </span>
            ) : null}
          </div>
          <p className="text-sm text-muted-foreground">
            {t('object_list.header_count', {
              defaultValue: '{{count}} items',
              count: totalItems,
            })}
            {' · '}
            {t('object_list.header_columns', {
              defaultValue: '{{system}} system + {{attr}} attribute columns',
              system: systemColumns,
              attr: attributeColumns,
            })}
          </p>
        </div>
        {onCreate !== undefined ? (
          <Button onClick={onCreate}>
            <Plus className="size-4" />
            {t('object_list.cta_create', { defaultValue: 'Create' })}
          </Button>
        ) : null}
      </header>

      {/* #1012 — search + status filter toolbar. Search debounces 300ms
          then forwards as ?sku= (substring on CatalogObject.code); status
          forwards as ?status=. AdvancedFilterBuilder + SavedViewsRail
          stay deferred to ULV-07 follow-up parity work. */}
      <div className="flex items-center gap-3">
        <div className="relative flex-1 max-w-md">
          <Search className="absolute left-2.5 top-1/2 size-4 -translate-y-1/2 text-muted-foreground" />
          <Input
            value={searchInput}
            placeholder={t('object_list.search_placeholder', {
              defaultValue: 'Search by code…',
            })}
            onChange={(e) => {
              const next = e.target.value;
              setSearchInput(next);
              applySearch(next);
            }}
            className="pl-8 pr-8"
            aria-label={t('object_list.search_aria', { defaultValue: 'Search objects by code' })}
          />
          {searchInput.length > 0 ? (
            <button
              type="button"
              onClick={() => {
                setSearchInput('');
                setAppliedSearch('');
                setCursorAfter(undefined);
              }}
              className="absolute right-2 top-1/2 grid -translate-y-1/2 place-items-center rounded p-1 text-muted-foreground hover:bg-muted"
              aria-label={t('object_list.search_clear', { defaultValue: 'Clear search' })}
            >
              <X className="size-3.5" />
            </button>
          ) : null}
        </div>
        <select
          value={statusFilter}
          onChange={(e) => {
            setStatusFilter(e.target.value as StatusOption);
            setCursorAfter(undefined);
          }}
          className="h-9 rounded-md border bg-background px-2 text-sm"
          aria-label={t('object_list.status_filter_aria', { defaultValue: 'Filter by status' })}
        >
          <option value="">
            {t('object_list.status_filter_all', { defaultValue: 'All statuses' })}
          </option>
          <option value="published">
            {t('object_list.status.published', { defaultValue: 'Published' })}
          </option>
          <option value="draft">{t('object_list.status.draft', { defaultValue: 'Draft' })}</option>
          <option value="archived">
            {t('object_list.status.archived', { defaultValue: 'Archived' })}
          </option>
        </select>
      </div>

      {noResultsForFilters ? (
        <div className="rounded border bg-muted/30 p-6 text-center text-sm text-muted-foreground">
          {t('object_list.no_results', {
            defaultValue: 'No objects match the current search / filter.',
          })}
        </div>
      ) : null}

      <Table>
        <TableHeader>
          <TableRow>
            {visibleColumns.map((c) => (
              <TableHead key={c.key}>{c.label[locale] ?? c.label.en ?? c.key}</TableHead>
            ))}
          </TableRow>
        </TableHeader>
        <TableBody>
          {listQuery.isLoading ? (
            <TableRow>
              <TableCell colSpan={visibleColumns.length}>
                {t('object_list.loading', { defaultValue: 'Loading…' })}
              </TableCell>
            </TableRow>
          ) : (
            items.map((item) => (
              <TableRow key={item.id}>
                {visibleColumns.map((c) => (
                  <TableCell key={c.key}>{renderCell(c, item)}</TableCell>
                ))}
              </TableRow>
            ))
          )}
        </TableBody>
      </Table>

      {next !== undefined ? (
        <div className="flex justify-end">
          <Button
            variant="outline"
            onClick={() => {
              const lastId = items[items.length - 1]?.id;
              if (lastId !== undefined) {
                setCursorAfter(lastId);
              }
            }}
          >
            {t('object_list.next_page', { defaultValue: 'Next page' })}
          </Button>
        </div>
      ) : null}
    </div>
  );
}
