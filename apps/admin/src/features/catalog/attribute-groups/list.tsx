import { useList } from '@refinedev/core';
import { Eye, Plus, Trash2 } from 'lucide-react';
import { useEffect, useState } from 'react';
import { useTranslation } from 'react-i18next';
import { Link } from 'react-router';

import { BuiltInLockBadge } from '@/components/modeling/built-in-lock-badge';
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
import { resolveLabel } from '@/features/catalog/attributes/list';
import { HttpError, jsonFetch } from '@/lib/http';

interface AttributeGroupRow {
  id: string;
  code: string;
  label?: Record<string, string> | string | null;
  description?: Record<string, string> | string | null;
  icon?: string | null;
  color?: string | null;
  systemGroup?: boolean;
  autoAttached?: boolean;
  position?: number;
}

export function AttributeGroupsListPage() {
  const { t, i18n } = useTranslation();
  const [search, setSearch] = useState('');
  const [systemFilter, setSystemFilter] = useState<'all' | 'system' | 'business'>('all');
  const [reloadKey, setReloadKey] = useState(0);
  const [error, setError] = useState<string | null>(null);

  const { result, query } = useList<AttributeGroupRow>({
    resource: 'attribute_groups',
    pagination: { mode: 'off' },
    queryOptions: { queryKey: ['attribute_groups', reloadKey] },
  });

  const groups = result.data;
  const isLoading = query.isLoading;
  const usageCounts = useAttributeGroupUsageCounts(groups, reloadKey);

  const visible = groups.filter((row) => {
    if (systemFilter === 'system' && row.systemGroup !== true) return false;
    if (systemFilter === 'business' && row.systemGroup === true) return false;
    if (search === '') return true;
    const needle = search.toLowerCase();
    if (row.code.toLowerCase().includes(needle)) return true;
    const label = resolveLabel(row.label, i18n.language).toLowerCase();
    return label.includes(needle);
  });

  const handleDelete = async (row: AttributeGroupRow) => {
    setError(null);
    if (!window.confirm(t('attribute_groups.delete_confirm', { name: row.code }))) {
      return;
    }
    try {
      await jsonFetch(`/api/attribute_groups/${row.id}`, {
        method: 'DELETE',
        accept: 'application/json',
      });
      setReloadKey((k) => k + 1);
    } catch (err) {
      if (err instanceof HttpError) {
        const detail =
          err.body && typeof err.body === 'object' && 'detail' in err.body
            ? String((err.body as Record<string, unknown>).detail)
            : null;
        setError(detail ?? `HTTP ${err.status}`);
      } else {
        setError(t('attribute_groups.delete_error'));
      }
    }
  };

  return (
    <div className="space-y-6">
      <div className="flex items-start justify-between gap-2">
        <div className="space-y-2">
          <div className="flex items-center gap-2">
            <h1 className="display text-[28px] font-semibold leading-tight text-ink">
              {t('attribute_groups.list_title')}
            </h1>
            <span className="inline-flex items-center gap-1 rounded-full bg-accent-violet/10 px-2.5 py-1 text-[11px] font-medium uppercase tracking-wide text-accent-violet">
              ⭐{' '}
              {t('attribute_groups.first_class_badge', {
                defaultValue: 'first-class entity (ADR-012)',
              })}
            </span>
          </div>
          <p className="max-w-3xl text-[14px] text-ink-2">{t('attribute_groups.list_subtitle')}</p>
        </div>
        <Button asChild>
          <Link to="/modeling/attribute-groups/new">
            <Plus className="size-4" />
            {t('modeling.attribute_groups.create_action')}
          </Link>
        </Button>
      </div>

      <div className="flex flex-wrap items-center gap-2">
        <Input
          aria-label={t('modeling.attribute_groups.search')}
          placeholder={t('modeling.attribute_groups.search_placeholder')}
          value={search}
          onChange={(e) => setSearch(e.target.value)}
          className="w-[260px]"
        />
        {(['all', 'business', 'system'] as const).map((value) => (
          <Button
            key={value}
            type="button"
            variant={systemFilter === value ? 'secondary' : 'ghost'}
            size="sm"
            onClick={() => setSystemFilter(value)}
          >
            {t(`modeling.attribute_groups.filter_${value}`)}
          </Button>
        ))}
      </div>

      {error !== null ? (
        <p className="rounded-md border border-destructive/50 bg-destructive/5 px-3 py-2 text-sm text-destructive">
          {error}
        </p>
      ) : null}

      <div className="rounded-2xl border border-line bg-surface soft-shadow">
        <Table>
          <TableHeader>
            <TableRow>
              <TableHead className="w-[220px]">{t('attribute_groups.fields.code')}</TableHead>
              <TableHead>{t('attribute_groups.fields.label')}</TableHead>
              <TableHead className="w-[120px] text-right">
                {t('modeling.attribute_groups.attributes_count')}
              </TableHead>
              <TableHead className="w-[160px] text-right">
                {t('modeling.where_used.attached_object_types')}
              </TableHead>
              <TableHead className="w-[100px] text-right">
                {t('attribute_groups.fields.position')}
              </TableHead>
              <TableHead className="w-[120px] text-right">
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
                  {t('attribute_groups.empty')}
                </TableCell>
              </TableRow>
            ) : (
              visible
                .slice()
                .sort((a, b) => (a.position ?? 0) - (b.position ?? 0))
                .map((group) => {
                  const usage = usageCounts[group.id];
                  return (
                    <TableRow key={group.id}>
                      <TableCell className="font-mono text-xs">
                        <span className="inline-flex items-center gap-2">
                          {group.color ? (
                            <span
                              aria-hidden
                              className="inline-block size-3 rounded-full border"
                              style={{ backgroundColor: group.color }}
                            />
                          ) : null}
                          {group.systemGroup ? <BuiltInLockBadge tone="quiet" /> : null}
                          {group.code}
                        </span>
                      </TableCell>
                      <TableCell className="font-medium">
                        {resolveLabel(group.label, i18n.language)}
                      </TableCell>
                      <TableCell className="text-right tabular-nums text-muted-foreground">
                        {usage?.attributeCount ?? '—'}
                      </TableCell>
                      <TableCell className="text-right tabular-nums text-muted-foreground">
                        {usage?.attachedObjectTypes ?? '—'}
                      </TableCell>
                      <TableCell className="text-right tabular-nums text-muted-foreground">
                        {group.position ?? '—'}
                      </TableCell>
                      <TableCell className="space-x-1 text-right">
                        <Button asChild variant="ghost" size="sm">
                          <Link to={`/modeling/attribute-groups/${group.id}`}>
                            <Eye className="size-4" />
                            <span className="sr-only">{t('attribute_groups.actions.view')}</span>
                          </Link>
                        </Button>
                        <Button
                          type="button"
                          variant="ghost"
                          size="sm"
                          disabled={
                            group.systemGroup === true ||
                            (usage !== undefined &&
                              (usage.attachedObjectTypes > 0 || usage.attachedCategories > 0))
                          }
                          onClick={() => handleDelete(group)}
                          aria-label={t('attribute_groups.actions.delete')}
                        >
                          <Trash2 className="size-4" />
                        </Button>
                      </TableCell>
                    </TableRow>
                  );
                })
            )}
          </TableBody>
        </Table>
      </div>
    </div>
  );
}

interface UsageCount {
  attributeCount: number;
  attachedObjectTypes: number;
  attachedCategories: number;
}

function useAttributeGroupUsageCounts(
  rows: AttributeGroupRow[],
  reloadKey: number,
): Record<string, UsageCount> {
  const [counts, setCounts] = useState<Record<string, UsageCount>>({});

  useEffect(() => {
    let cancelled = false;
    (async () => {
      const next: Record<string, UsageCount> = {};
      await Promise.all(
        rows.map(async (row) => {
          try {
            const usage = await jsonFetch<{
              directlyAttachedTo: {
                objectTypes: { id: string }[];
                categories: { id: string }[];
              };
              attributeCount: number;
            }>(`/api/attribute_groups/${row.id}/usage`, { accept: 'application/json' });
            next[row.id] = {
              attributeCount: usage.attributeCount,
              attachedObjectTypes: usage.directlyAttachedTo.objectTypes.length,
              attachedCategories: usage.directlyAttachedTo.categories.length,
            };
          } catch {
            // tolerate
          }
        }),
      );
      if (!cancelled) setCounts(next);
    })();
    return () => {
      cancelled = true;
    };
  }, [rows, reloadKey]);
  // ^ reloadKey is intentional — bump triggers a refetch after a list mutation.

  return counts;
}
