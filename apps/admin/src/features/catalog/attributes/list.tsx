import { useList } from '@refinedev/core';
import { Eye } from 'lucide-react';
import { useState } from 'react';
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

interface AttributeRow {
  id: string;
  code: string;
  label: Record<string, string> | string | null;
  type: string;
  group?: { id: string; code?: string; label?: Record<string, string> | string } | string | null;
  required?: boolean;
  localizable?: boolean;
  scopable?: boolean;
  position?: number;
}

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

  const { result, query } = useList<AttributeRow>({
    resource: 'attributes',
    pagination: { mode: 'off' },
  });

  const attributes = result.data;
  const isLoading = query.isLoading;

  const visible =
    typeFilter === '' ? attributes : attributes.filter((row) => row.type === typeFilter);

  return (
    <div className="space-y-6">
      <div>
        <h1 className="text-2xl font-semibold tracking-tight">{t('attributes.list_title')}</h1>
        <p className="text-sm text-muted-foreground">{t('attributes.list_subtitle')}</p>
      </div>

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

      <div className="rounded-xl border bg-card">
        <Table>
          <TableHeader>
            <TableRow>
              <TableHead className="w-[200px]">{t('attributes.fields.code')}</TableHead>
              <TableHead>{t('attributes.fields.label')}</TableHead>
              <TableHead className="w-[140px]">{t('attributes.fields.type')}</TableHead>
              <TableHead className="w-[160px]">{t('attributes.fields.group')}</TableHead>
              <TableHead className="w-[180px]">{t('attributes.fields.flags')}</TableHead>
              <TableHead className="w-[80px] text-right">
                <span className="sr-only">{t('attributes.fields.actions')}</span>
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
            ) : visible.length === 0 ? (
              <TableRow>
                <TableCell colSpan={6} className="py-10 text-center text-muted-foreground">
                  {t('attributes.empty')}
                </TableCell>
              </TableRow>
            ) : (
              visible.map((row) => (
                <TableRow key={row.id}>
                  <TableCell className="font-mono text-xs">{row.code}</TableCell>
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
                  <TableCell className="text-right">
                    <Button asChild variant="ghost" size="sm">
                      <Link to={`/attributes/${row.id}`}>
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
