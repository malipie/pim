import { useList } from '@refinedev/core';
import { Eye } from 'lucide-react';
import { useEffect, useState } from 'react';
import { useTranslation } from 'react-i18next';
import { Link } from 'react-router';

import { BuiltInLockBadge } from '@/components/modeling/built-in-lock-badge';
import { Button } from '@/components/ui/button';
import {
  Table,
  TableBody,
  TableCell,
  TableHead,
  TableHeader,
  TableRow,
} from '@/components/ui/table';
import { jsonFetch } from '@/lib/http';

interface AttributeRow {
  id: string;
  code: string;
  label: Record<string, string> | string | null;
  type: string;
  group?: { id: string; code?: string; label?: Record<string, string> | string } | string | null;
  required?: boolean;
  localizable?: boolean;
  scopable?: boolean;
  system?: boolean;
  position?: number;
}

type SystemFilter = 'all' | 'system' | 'business';

const TYPES: ReadonlyArray<string> = [
  'text',
  'textarea',
  'number',
  'boolean',
  'select',
  'multi_select',
  'date',
  'asset',
  'reference',
  'price',
  'measurement',
];

export function AttributesListPage() {
  const { t, i18n } = useTranslation();
  const [typeFilter, setTypeFilter] = useState<string>('');
  const [systemFilter, setSystemFilter] = useState<SystemFilter>('all');
  const [localizableOnly, setLocalizableOnly] = useState(false);
  const [scopableOnly, setScopableOnly] = useState(false);

  const { result, query } = useList<AttributeRow>({
    resource: 'attributes',
    pagination: { mode: 'off' },
  });

  const attributes = result.data;
  const isLoading = query.isLoading;
  const usageCounts = useAttributeUsageCounts(attributes);

  const visible = attributes.filter((row) => {
    if (typeFilter !== '' && row.type !== typeFilter) return false;
    if (systemFilter === 'system' && row.system !== true) return false;
    if (systemFilter === 'business' && row.system === true) return false;
    if (localizableOnly && row.localizable !== true) return false;
    if (scopableOnly && row.scopable !== true) return false;
    return true;
  });

  return (
    <div className="space-y-6">
      <div>
        <h1 className="text-2xl font-semibold tracking-tight">{t('attributes.list_title')}</h1>
        <p className="text-sm text-muted-foreground">{t('attributes.list_subtitle')}</p>
      </div>

      <div className="space-y-2">
        <div className="flex flex-wrap items-center gap-2">
          <span className="text-xs uppercase tracking-wide text-muted-foreground">
            {t('attributes.filter_type')}
          </span>
          <Button
            type="button"
            variant={typeFilter === '' ? 'secondary' : 'ghost'}
            size="sm"
            onClick={() => setTypeFilter('')}
          >
            {t('attributes.filter_all')}
          </Button>
          {TYPES.map((type) => (
            <Button
              key={type}
              type="button"
              variant={typeFilter === type ? 'secondary' : 'ghost'}
              size="sm"
              onClick={() => setTypeFilter(type)}
            >
              {type}
            </Button>
          ))}
        </div>
        <div className="flex flex-wrap items-center gap-2">
          <span className="text-xs uppercase tracking-wide text-muted-foreground">
            {t('modeling.attributes.filter_origin')}
          </span>
          {(['all', 'business', 'system'] as const).map((value) => (
            <Button
              key={value}
              type="button"
              variant={systemFilter === value ? 'secondary' : 'ghost'}
              size="sm"
              onClick={() => setSystemFilter(value)}
            >
              {t(`modeling.attributes.filter_origin_${value}`)}
            </Button>
          ))}
          <span className="ml-2 text-xs uppercase tracking-wide text-muted-foreground">
            {t('modeling.attributes.filter_flags')}
          </span>
          <Button
            type="button"
            variant={localizableOnly ? 'secondary' : 'ghost'}
            size="sm"
            onClick={() => setLocalizableOnly((v) => !v)}
            aria-pressed={localizableOnly}
          >
            {t('attributes.flags.localizable')}
          </Button>
          <Button
            type="button"
            variant={scopableOnly ? 'secondary' : 'ghost'}
            size="sm"
            onClick={() => setScopableOnly((v) => !v)}
            aria-pressed={scopableOnly}
          >
            {t('attributes.flags.scopable')}
          </Button>
        </div>
      </div>

      <div className="rounded-xl border bg-card">
        <Table>
          <TableHeader>
            <TableRow>
              <TableHead className="w-[220px]">{t('attributes.fields.code')}</TableHead>
              <TableHead>{t('attributes.fields.label')}</TableHead>
              <TableHead className="w-[140px]">{t('attributes.fields.type')}</TableHead>
              <TableHead className="w-[160px]">{t('attributes.fields.group')}</TableHead>
              <TableHead className="w-[180px]">{t('attributes.fields.flags')}</TableHead>
              <TableHead className="w-[110px] text-right">
                {t('modeling.attributes.usage_count')}
              </TableHead>
              <TableHead className="w-[80px] text-right">
                <span className="sr-only">{t('attributes.fields.actions')}</span>
              </TableHead>
            </TableRow>
          </TableHeader>
          <TableBody>
            {isLoading ? (
              <TableRow>
                <TableCell colSpan={7} className="py-10 text-center text-muted-foreground">
                  {t('app.loading')}
                </TableCell>
              </TableRow>
            ) : visible.length === 0 ? (
              <TableRow>
                <TableCell colSpan={7} className="py-10 text-center text-muted-foreground">
                  {t('attributes.empty')}
                </TableCell>
              </TableRow>
            ) : (
              visible.map((row) => (
                <TableRow key={row.id}>
                  <TableCell className="font-mono text-xs">
                    <span className="inline-flex items-center gap-2">
                      {row.system ? <BuiltInLockBadge tone="quiet" /> : null}
                      {row.code}
                    </span>
                  </TableCell>
                  <TableCell className="font-medium">
                    {resolveLabel(row.label, i18n.language)}
                  </TableCell>
                  <TableCell>
                    <span className="rounded bg-muted px-2 py-0.5 text-xs uppercase tracking-wide">
                      {row.type}
                    </span>
                  </TableCell>
                  <TableCell className="text-xs text-muted-foreground">
                    {resolveGroupLabel(row.group, i18n.language)}
                  </TableCell>
                  <TableCell className="space-x-1 text-xs text-muted-foreground">
                    {row.required ? <Flag>{t('attributes.flags.required')}</Flag> : null}
                    {row.localizable ? <Flag>{t('attributes.flags.localizable')}</Flag> : null}
                    {row.scopable ? <Flag>{t('attributes.flags.scopable')}</Flag> : null}
                  </TableCell>
                  <TableCell className="text-right font-mono text-sm tabular-nums text-muted-foreground">
                    {usageCounts[row.id] ?? '—'}
                  </TableCell>
                  <TableCell className="text-right">
                    <Button asChild variant="ghost" size="sm">
                      <Link to={`/modeling/attributes/${row.id}`}>
                        <Eye className="size-4" />
                        <span className="sr-only">{t('attributes.actions.view')}</span>
                      </Link>
                    </Button>
                  </TableCell>
                </TableRow>
              ))
            )}
          </TableBody>
        </Table>
      </div>

      <p className="text-xs text-muted-foreground">{t('attributes.write_deferred_note')}</p>
    </div>
  );
}

function Flag({ children }: { children: React.ReactNode }) {
  return (
    <span className="rounded bg-secondary px-1.5 py-0.5 text-[10px] uppercase tracking-wide text-secondary-foreground">
      {children}
    </span>
  );
}

export function resolveLabel(
  value: Record<string, string> | string | null | undefined,
  locale: string,
): string {
  if (typeof value === 'string') return value;
  if (value && typeof value === 'object') {
    const lang = locale.split('-')[0] ?? locale;
    return value[lang] ?? value.en ?? value.pl ?? Object.values(value)[0] ?? '—';
  }
  return '—';
}

function resolveGroupLabel(group: AttributeRow['group'], locale: string): string {
  if (!group) return '—';
  if (typeof group === 'string') return group;
  if (group.label) return resolveLabel(group.label, locale);
  return group.code ?? '—';
}

/**
 * Per-row instance count from the UI-08.7 usage endpoint. Each row gets
 * its own request — `/api/attributes/{id}/usage` is a single-row reader.
 * The widget tolerates 404 / network errors (count stays "—") so the
 * list still renders cleanly when the endpoint is unavailable.
 */
function useAttributeUsageCounts(rows: AttributeRow[]): Record<string, number> {
  const [counts, setCounts] = useState<Record<string, number>>({});

  useEffect(() => {
    let cancelled = false;
    (async () => {
      const next: Record<string, number> = {};
      await Promise.all(
        rows.map(async (row) => {
          try {
            const usage = await jsonFetch<{ instanceCount: number }>(
              `/api/attributes/${row.id}/usage`,
              { accept: 'application/json' },
            );
            next[row.id] = usage.instanceCount;
          } catch {
            // tolerate — row keeps "—"
          }
        }),
      );
      if (!cancelled) setCounts(next);
    })();
    return () => {
      cancelled = true;
    };
  }, [rows]);

  return counts;
}
