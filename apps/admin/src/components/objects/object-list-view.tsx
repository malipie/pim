import { useState } from 'react';
import { useTranslation } from 'react-i18next';

import { EmptyStateObject } from '@/components/objects/empty-state-object';
import { Button } from '@/components/ui/button';
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

export function ObjectListView({ objectTypeId, onCreate }: ObjectListViewProps) {
  const { t, i18n } = useTranslation();
  const locale = i18n.language.split('-')[0] ?? 'en';
  const schemaQuery = useListSchema(objectTypeId);
  const [cursorAfter, setCursorAfter] = useState<string | undefined>(undefined);
  const listQuery = useObjectList({
    objectTypeId,
    itemsPerPage: 30,
    cursorAfter,
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

  if (totalItems === 0 && !listQuery.isLoading) {
    return (
      <div className="space-y-4">
        <header className="flex items-center justify-between">
          <h1 className="text-2xl font-semibold">{typeLabel}</h1>
        </header>
        <EmptyStateObject objectType={objectType} onCreate={onCreate} />
      </div>
    );
  }

  return (
    <div className="space-y-4">
      <header className="flex items-center justify-between">
        <div>
          <h1 className="text-2xl font-semibold">{typeLabel}</h1>
          <p className="text-sm text-muted-foreground">
            {t('object_list.header_count', {
              defaultValue: '{{count}} items',
              count: totalItems,
            })}
          </p>
        </div>
        {onCreate !== undefined ? (
          <Button onClick={onCreate}>
            {t('object_list.cta_create', { defaultValue: 'Create' })}
          </Button>
        ) : null}
      </header>

      <Table>
        <TableHeader>
          <TableRow>
            {columns.map((c) => (
              <TableHead key={c.key}>{c.label[locale] ?? c.label.en ?? c.key}</TableHead>
            ))}
          </TableRow>
        </TableHeader>
        <TableBody>
          {listQuery.isLoading ? (
            <TableRow>
              <TableCell colSpan={columns.length}>
                {t('object_list.loading', { defaultValue: 'Loading…' })}
              </TableCell>
            </TableRow>
          ) : (
            items.map((item) => (
              <TableRow key={item.id}>
                {columns.map((c) => (
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
