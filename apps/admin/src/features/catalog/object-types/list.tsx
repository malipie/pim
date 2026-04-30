import { useList } from '@refinedev/core';
import { Eye, Lock } from 'lucide-react';
import { useTranslation } from 'react-i18next';
import { Link } from 'react-router';

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

interface ObjectTypeRow {
  id: string;
  code: string;
  kind: string;
  label?: Record<string, string> | string | null;
  builtIn?: boolean;
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

  const builtIn = types.filter((row) => row.builtIn !== false);
  const custom = types.filter((row) => row.builtIn === false);

  return (
    <div className="space-y-8">
      <div>
        <h1 className="text-2xl font-semibold tracking-tight">{t('object_types.list_title')}</h1>
        <p className="text-sm text-muted-foreground">{t('object_types.list_subtitle')}</p>
      </div>

      <section className="space-y-3">
        <header className="flex items-center justify-between">
          <h2 className="text-sm font-medium">{t('object_types.built_in_title')}</h2>
          <span className="rounded bg-secondary px-2 py-0.5 text-xs text-secondary-foreground">
            <Lock className="inline size-3" /> {t('object_types.locked')}
          </span>
        </header>
        <div className="rounded-xl border bg-card">
          <Table>
            <TableHeader>
              <TableRow>
                <TableHead className="w-[180px]">{t('object_types.fields.code')}</TableHead>
                <TableHead>{t('object_types.fields.label')}</TableHead>
                <TableHead className="w-[120px]">{t('object_types.fields.kind')}</TableHead>
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
                  <TableCell colSpan={5} className="py-10 text-center text-muted-foreground">
                    {t('app.loading')}
                  </TableCell>
                </TableRow>
              ) : builtIn.length === 0 ? (
                <TableRow>
                  <TableCell colSpan={5} className="py-10 text-center text-muted-foreground">
                    {t('object_types.empty')}
                  </TableCell>
                </TableRow>
              ) : (
                builtIn.map((row) => (
                  <TableRow key={row.id}>
                    <TableCell className="font-mono text-xs">{row.code}</TableCell>
                    <TableCell className="font-medium">
                      {resolveLabel(row.label, i18n.language)}
                    </TableCell>
                    <TableCell>
                      <KindBadge kind={row.kind} />
                    </TableCell>
                    <TableCell className="text-muted-foreground tabular-nums">
                      {row.schemaVersion ?? 1}
                    </TableCell>
                    <TableCell className="text-right">
                      <Button asChild variant="ghost" size="sm">
                        <Link to={`/object-types/${row.id}`}>
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
          <h2 className="text-sm font-medium">{t('object_types.custom_title')}</h2>
          <span className="rounded bg-amber-100 px-2 py-0.5 text-xs font-medium text-amber-900">
            {t('object_types.phase_2_badge')}
          </span>
        </header>
        <div className="rounded-xl border border-dashed bg-muted/30 px-4 py-6 text-sm text-muted-foreground">
          <p className="mb-2">{t('object_types.custom_disabled_explanation')}</p>
          <Button type="button" variant="outline" size="sm" disabled>
            {t('object_types.create_custom_disabled')}
          </Button>
          {custom.length > 0 ? (
            <p className="mt-3 text-xs">
              {t('object_types.custom_present_note', { count: custom.length })}
            </p>
          ) : null}
        </div>
      </section>
    </div>
  );
}

function KindBadge({ kind }: { kind: string }) {
  const tone =
    kind === 'product'
      ? 'bg-blue-100 text-blue-900'
      : kind === 'category'
        ? 'bg-emerald-100 text-emerald-900'
        : kind === 'asset'
          ? 'bg-purple-100 text-purple-900'
          : 'bg-muted text-muted-foreground';
  return (
    <span className={`rounded px-2 py-0.5 text-xs font-medium uppercase tracking-wide ${tone}`}>
      {kind}
    </span>
  );
}
