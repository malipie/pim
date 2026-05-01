import { useList } from '@refinedev/core';
import { Eye, Plus } from 'lucide-react';
import { useEffect, useState } from 'react';
import { useTranslation } from 'react-i18next';
import { Link } from 'react-router';

import { BuiltInLockBadge } from '@/components/modeling/built-in-lock-badge';
import { CreateCustomObjectTypeDialog } from '@/components/modeling/create-custom-object-type-dialog';
import { Button } from '@/components/ui/button';
import {
  Table,
  TableBody,
  TableCell,
  TableHead,
  TableHeader,
  TableRow,
} from '@/components/ui/table';
import { resolveLabel } from '@/features/catalog/attributes/list';
import { jsonFetch } from '@/lib/http';

interface ObjectTypeRow {
  id: string;
  code: string;
  kind: string;
  label?: Record<string, string> | string | null;
  builtIn?: boolean;
  codeImmutable?: boolean;
  deletable?: boolean;
  icon?: string | null;
  color?: string | null;
  schemaVersion?: number;
}

export function ObjectTypesListPage() {
  const { t, i18n } = useTranslation();
  const { result, query } = useList<ObjectTypeRow>({
    resource: 'object_types',
    pagination: { mode: 'off' },
  });

  const types = result.data;
  const isLoading = query.isLoading;
  const instanceCounts = useObjectTypeInstanceCounts(types);
  const [createOpen, setCreateOpen] = useState(false);

  const builtIn = types.filter((row) => row.builtIn !== false);
  const custom = types.filter((row) => row.builtIn === false);

  return (
    <div className="space-y-8">
      <div className="space-y-2">
        <h1 className="display text-[28px] font-semibold leading-tight text-ink">
          {t('object_types.list_title')}
        </h1>
        <p className="max-w-3xl text-[14px] text-ink-2">{t('object_types.list_subtitle')}</p>
      </div>

      <section className="space-y-3">
        <header className="flex items-center justify-between">
          <h2 className="text-[15px] font-semibold text-ink">{t('object_types.built_in_title')}</h2>
          <BuiltInLockBadge />
        </header>
        <div className="rounded-2xl border border-line bg-surface soft-shadow">
          <Table>
            <TableHeader>
              <TableRow>
                <TableHead className="w-[180px]">{t('object_types.fields.code')}</TableHead>
                <TableHead>{t('object_types.fields.label')}</TableHead>
                <TableHead className="w-[120px]">{t('object_types.fields.kind')}</TableHead>
                <TableHead className="w-[120px] text-right">
                  {t('object_types.fields.instance_count')}
                </TableHead>
                <TableHead className="w-[120px]">
                  {t('object_types.fields.schema_version')}
                </TableHead>
                <TableHead className="w-[80px] text-right">
                  <span className="sr-only">{t('object_types.fields.actions')}</span>
                </TableHead>
              </TableRow>
            </TableHeader>
            <TableBody>
              {isLoading ? (
                <TableRow>
                  <TableCell colSpan={6} className="py-10 text-center text-muted-foreground">
                    {t('app.loading')}
                  </TableCell>
                </TableRow>
              ) : builtIn.length === 0 ? (
                <TableRow>
                  <TableCell colSpan={6} className="py-10 text-center text-muted-foreground">
                    {t('object_types.empty')}
                  </TableCell>
                </TableRow>
              ) : (
                builtIn.map((row) => (
                  <TableRow key={row.id}>
                    <TableCell className="font-mono text-xs">
                      <span className="inline-flex items-center gap-2">
                        <ColorSwatch color={row.color} />
                        {row.code}
                      </span>
                    </TableCell>
                    <TableCell className="font-medium">
                      {resolveLabel(row.label, i18n.language)}
                    </TableCell>
                    <TableCell>
                      <KindBadge kind={row.kind} />
                    </TableCell>
                    <TableCell className="text-right font-mono text-sm tabular-nums">
                      {instanceCounts[row.id] ?? '—'}
                    </TableCell>
                    <TableCell className="text-muted-foreground tabular-nums">
                      {row.schemaVersion ?? 1}
                    </TableCell>
                    <TableCell className="text-right">
                      <Button asChild variant="ghost" size="sm">
                        <Link to={`/modeling/object-types/${row.id}`}>
                          <Eye className="size-4" />
                          <span className="sr-only">{t('object_types.actions.view')}</span>
                        </Link>
                      </Button>
                    </TableCell>
                  </TableRow>
                ))
              )}
            </TableBody>
          </Table>
        </div>
      </section>

      <section className="space-y-3">
        <header className="flex items-center justify-between">
          <h2 className="text-[15px] font-semibold text-ink">{t('object_types.custom_title')}</h2>
          <Button type="button" size="sm" onClick={() => setCreateOpen(true)}>
            <Plus className="size-4" />
            {t('object_types.create_custom_action', { defaultValue: 'Create custom ObjectType' })}
          </Button>
        </header>
        <div className="rounded-2xl border border-line bg-surface soft-shadow">
          <Table>
            <TableHeader>
              <TableRow>
                <TableHead className="w-[180px]">{t('object_types.fields.code')}</TableHead>
                <TableHead>{t('object_types.fields.label')}</TableHead>
                <TableHead className="w-[120px]">{t('object_types.fields.kind')}</TableHead>
                <TableHead className="w-[120px] text-right">
                  {t('object_types.fields.instance_count')}
                </TableHead>
                <TableHead className="w-[120px]">
                  {t('object_types.fields.schema_version')}
                </TableHead>
                <TableHead className="w-[80px] text-right">
                  <span className="sr-only">{t('object_types.fields.actions')}</span>
                </TableHead>
              </TableRow>
            </TableHeader>
            <TableBody>
              {custom.length === 0 ? (
                <TableRow>
                  <TableCell colSpan={6} className="py-10 text-center text-muted-foreground">
                    {t('object_types.custom_empty', {
                      defaultValue: 'No custom ObjectTypes yet — create your first one above.',
                    })}
                  </TableCell>
                </TableRow>
              ) : (
                custom.map((row) => (
                  <TableRow key={row.id}>
                    <TableCell className="font-mono text-xs">
                      <span className="inline-flex items-center gap-2">
                        <ColorSwatch color={row.color} />
                        {row.code}
                      </span>
                    </TableCell>
                    <TableCell className="font-medium">
                      {resolveLabel(row.label, i18n.language)}
                    </TableCell>
                    <TableCell>
                      <KindBadge kind={row.kind} />
                    </TableCell>
                    <TableCell className="text-right font-mono text-sm tabular-nums">
                      {instanceCounts[row.id] ?? '—'}
                    </TableCell>
                    <TableCell className="text-muted-foreground tabular-nums">
                      {row.schemaVersion ?? 1}
                    </TableCell>
                    <TableCell className="text-right">
                      <Button asChild variant="ghost" size="sm">
                        <Link to={`/modeling/object-types/${row.id}`}>
                          <Eye className="size-4" />
                          <span className="sr-only">{t('object_types.actions.view')}</span>
                        </Link>
                      </Button>
                    </TableCell>
                  </TableRow>
                ))
              )}
            </TableBody>
          </Table>
        </div>
      </section>

      {createOpen ? (
        <CreateCustomObjectTypeDialog
          onClose={() => setCreateOpen(false)}
          onCreated={() => {
            void query.refetch();
          }}
        />
      ) : null}
    </div>
  );
}

function KindBadge({ kind }: { kind: string }) {
  const tone =
    kind === 'product'
      ? 'bg-accent-blue/10 text-accent-blue'
      : kind === 'category'
        ? 'bg-accent-emerald/10 text-accent-emerald'
        : kind === 'asset'
          ? 'bg-accent-violet/10 text-accent-violet'
          : kind === 'brand'
            ? 'bg-accent-amber/10 text-accent-amber'
            : 'bg-muted text-muted-foreground';
  return (
    <span
      className={`rounded-md px-2 py-0.5 text-[11px] font-medium uppercase tracking-wide ${tone}`}
    >
      {kind}
    </span>
  );
}

function ColorSwatch({ color }: { color?: string | null }) {
  if (!color) return null;
  return (
    <span
      aria-hidden
      className="inline-block size-3 rounded-full border"
      style={{ backgroundColor: color }}
    />
  );
}

/**
 * Fetch `/api/object_types/{id}/usage.instanceCount` for every row.
 * Stored in a parallel map so the row render stays decoupled — the
 * count cell shows "—" until the per-id call resolves. Each call is
 * its own request (UI-08.7 ships only single-row endpoints; a batch
 * endpoint is a follow-up for huge tenants).
 */
function useObjectTypeInstanceCounts(types: ObjectTypeRow[]): Record<string, number> {
  const [counts, setCounts] = useState<Record<string, number>>({});

  useEffect(() => {
    let cancelled = false;
    (async () => {
      const next: Record<string, number> = {};
      await Promise.all(
        types.map(async (row) => {
          try {
            const usage = await jsonFetch<{ instanceCount: number }>(
              `/api/object_types/${row.id}/usage`,
              { accept: 'application/json' },
            );
            next[row.id] = usage.instanceCount;
          } catch {
            // tolerate 404 / network — leave row's count blank.
          }
        }),
      );
      if (!cancelled) setCounts(next);
    })();
    return () => {
      cancelled = true;
    };
  }, [types]);

  return counts;
}
