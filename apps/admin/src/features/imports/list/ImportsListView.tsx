import { useList } from '@refinedev/core';
import { Plus } from 'lucide-react';
import * as React from 'react';
import { useTranslation } from 'react-i18next';
import { Link } from 'react-router';

import { Button } from '@/components/ui/button';
import { DataTable, type DataTableColumn } from '@/components/ui/data-table';
import { ImportProfileManager } from '@/features/imports/profiles/ImportProfileManager';

import { type ImportStatus, StatusBadge } from './StatusBadge';

interface ImportSessionRow {
  id: string;
  status: ImportStatus;
  file_name: string;
  total_rows: number | null;
  success_count: number;
  error_count: number;
  started_at: string | null;
  completed_at: string | null;
  rollback_until: string | null;
}

/**
 * IMP-09 (#450) — wizard's Step 0 ("lista importów"). Reads through
 * Refine's `useList` (the standard CatalogObject pattern) so the
 * data provider, auth, and TenantFilter scoping all ride the
 * existing rails. Filters / 3-dot actions / re-run wiring land in
 * IMP-12 + IMP-13 alongside the rollback button.
 */
export function ImportsListView(): React.ReactElement {
  const { t } = useTranslation();

  const { result, query } = useList<ImportSessionRow>({
    resource: 'import-sessions',
    pagination: { pageSize: 50 },
  });

  const isLoading = query.isLoading;
  const rows = result.data ?? [];

  const columns: ReadonlyArray<DataTableColumn<ImportSessionRow>> = [
    {
      id: 'date',
      header: t('imports.list.columns.date', { defaultValue: 'Data' }),
      cell: (row) => formatDate(row.started_at ?? row.completed_at),
      className: 'whitespace-nowrap',
    },
    {
      id: 'file',
      header: t('imports.list.columns.file', { defaultValue: 'Plik' }),
      cell: (row) => <span className="font-mono text-xs">{row.file_name}</span>,
    },
    {
      id: 'status',
      header: t('imports.list.columns.status', { defaultValue: 'Status' }),
      cell: (row) => <StatusBadge status={row.status} />,
    },
    {
      id: 'stats',
      header: t('imports.list.columns.stats', { defaultValue: 'Statystyki' }),
      cell: (row) => `${row.success_count} / ${row.total_rows ?? '?'}`,
      className: 'whitespace-nowrap text-sm',
    },
    {
      id: 'actions',
      header: t('imports.list.columns.actions', { defaultValue: 'Akcje' }),
      cell: (row) => (
        <Button asChild variant="ghost" size="sm">
          <Link to={`/publications/imports/${row.id}`}>
            {t('imports.list.actions.view_report', { defaultValue: 'Zobacz' })}
          </Link>
        </Button>
      ),
      align: 'right',
    },
  ];

  const [profilesOpen, setProfilesOpen] = React.useState(false);

  return (
    <div className="space-y-4">
      <div className="flex items-center justify-between">
        <h2 className="text-lg font-semibold">
          {t('imports.list.title', { defaultValue: 'Importy' })}
        </h2>
        <div className="flex gap-2">
          <Button variant="outline" onClick={() => setProfilesOpen(true)}>
            ⋮ {t('imports.list.profiles', { defaultValue: 'Profile' })}
          </Button>
          <Button asChild>
            <Link to="/publications/imports/new">
              <Plus className="size-4" />
              {t('imports.list.new', { defaultValue: 'Nowy import' })}
            </Link>
          </Button>
        </div>
      </div>

      <ImportProfileManager open={profilesOpen} onOpenChange={setProfilesOpen} />
      {isLoading ? (
        <div className="text-sm text-muted-foreground" aria-busy="true">
          {t('app.loading', { defaultValue: 'Ładowanie…' })}
        </div>
      ) : (
        <DataTable
          data={rows}
          columns={columns}
          rowKey={(row) => row.id}
          emptyState={
            <div className="rounded-md border border-dashed p-8 text-center text-sm text-muted-foreground">
              {t('imports.list.empty', {
                defaultValue: 'Brak importów. Utwórz pierwszy import.',
              })}
            </div>
          }
        />
      )}
    </div>
  );
}

function formatDate(value: string | null): string {
  if (value === null) {
    return '—';
  }
  const parsed = new Date(value);
  if (Number.isNaN(parsed.getTime())) {
    return value;
  }
  return parsed.toLocaleString('pl-PL', {
    dateStyle: 'short',
    timeStyle: 'short',
  });
}
