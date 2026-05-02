import { useList } from '@refinedev/core';
import {
  Boxes,
  ChevronRight,
  FolderTree,
  Image as ImageIcon,
  Layers,
  Plus,
  Tag,
} from 'lucide-react';
import type { ComponentType, SVGProps } from 'react';
import { useEffect, useState } from 'react';
import { useTranslation } from 'react-i18next';
import { Link } from 'react-router';

import { BuiltInLockBadge } from '@/components/modeling/built-in-lock-badge';
import { CreateCustomObjectTypeDialog } from '@/components/modeling/create-custom-object-type-dialog';
import { Button } from '@/components/ui/button';
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

const KIND_ICONS: Record<string, ComponentType<SVGProps<SVGSVGElement>>> = {
  product: Boxes,
  category: FolderTree,
  asset: ImageIcon,
  brand: Tag,
  custom: Layers,
};

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
          <div className="flex items-center gap-2">
            <h2 className="text-[15px] font-semibold text-ink">
              {t('object_types.built_in_title')}
            </h2>
            <BuiltInLockBadge />
          </div>
          <span className="text-[12px] text-muted-foreground">{builtIn.length}</span>
        </header>
        {isLoading ? (
          <div className="rounded-2xl border border-line bg-surface p-10 text-center text-muted-foreground soft-shadow">
            {t('app.loading')}
          </div>
        ) : builtIn.length === 0 ? (
          <div className="rounded-2xl border border-line bg-surface p-10 text-center text-muted-foreground soft-shadow">
            {t('object_types.empty')}
          </div>
        ) : (
          <div className="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3">
            {builtIn.map((row) => (
              <ObjectTypeCard
                key={row.id}
                row={row}
                language={i18n.language}
                instanceCount={instanceCounts[row.id]}
              />
            ))}
          </div>
        )}
      </section>

      <section className="space-y-3">
        <header className="flex items-center justify-between">
          <div className="flex items-center gap-2">
            <h2 className="text-[15px] font-semibold text-ink">{t('object_types.custom_title')}</h2>
            <span className="text-[12px] text-muted-foreground">{custom.length}</span>
          </div>
          <Button type="button" size="sm" onClick={() => setCreateOpen(true)}>
            <Plus className="size-4" />
            {t('object_types.create_custom_action', { defaultValue: 'Create custom ObjectType' })}
          </Button>
        </header>
        {custom.length === 0 ? (
          <div className="rounded-2xl border border-dashed border-line bg-surface p-10 text-center text-muted-foreground">
            {t('object_types.custom_empty', {
              defaultValue: 'No custom ObjectTypes yet — create your first one above.',
            })}
          </div>
        ) : (
          <div className="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3">
            {custom.map((row) => (
              <ObjectTypeCard
                key={row.id}
                row={row}
                language={i18n.language}
                instanceCount={instanceCounts[row.id]}
              />
            ))}
          </div>
        )}
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

interface ObjectTypeCardProps {
  row: ObjectTypeRow;
  language: string;
  instanceCount: number | undefined;
}

function ObjectTypeCard({ row, language, instanceCount }: ObjectTypeCardProps) {
  const { t } = useTranslation();
  const Icon = KIND_ICONS[row.kind] ?? Layers;
  const isBuiltIn = row.builtIn !== false;

  return (
    <Link
      to={`/modeling/object-types/${row.id}`}
      className="group relative flex flex-col gap-3 rounded-2xl border border-line bg-surface p-5 soft-shadow transition-all hover:-translate-y-0.5 hover:border-accent-violet/40 hover:shadow-md"
    >
      <div className="flex items-start gap-3">
        <span
          className="flex size-10 shrink-0 items-center justify-center rounded-xl"
          style={{
            backgroundColor: row.color ? `${row.color}1a` : 'var(--surface-2)',
            color: row.color ?? 'var(--ink)',
          }}
        >
          <Icon className="size-5" />
        </span>
        <div className="min-w-0 flex-1">
          <div className="flex items-center gap-2">
            <h3 className="truncate text-[14px] font-semibold text-ink">
              {resolveLabel(row.label, language)}
            </h3>
          </div>
          <code className="mt-0.5 block truncate font-mono text-[11px] text-muted-foreground">
            {row.code}
          </code>
        </div>
        <ChevronRight className="size-4 text-muted-foreground transition-transform group-hover:translate-x-0.5" />
      </div>
      <div className="flex flex-wrap items-center gap-1.5">
        <KindBadge kind={row.kind} />
        <span
          className={
            isBuiltIn
              ? 'inline-flex items-center rounded-md bg-muted px-1.5 py-0.5 text-[10px] font-medium uppercase tracking-wide text-muted-foreground'
              : 'inline-flex items-center rounded-md bg-accent-violet/10 px-1.5 py-0.5 text-[10px] font-medium uppercase tracking-wide text-accent-violet'
          }
        >
          {isBuiltIn ? t('object_types.system_badge', { defaultValue: 'system' }) : 'custom'}
        </span>
        {row.schemaVersion && row.schemaVersion > 1 ? (
          <span className="inline-flex items-center rounded-md bg-muted px-1.5 py-0.5 text-[10px] font-medium text-muted-foreground">
            v{row.schemaVersion}
          </span>
        ) : null}
      </div>
      <div className="flex items-baseline justify-between border-t border-line/70 pt-3 text-[12px] text-muted-foreground">
        <span>{t('object_types.fields.instance_count')}</span>
        <span className="num font-medium text-ink">
          {instanceCount === undefined ? '—' : instanceCount.toLocaleString('pl-PL')}
        </span>
      </div>
    </Link>
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
