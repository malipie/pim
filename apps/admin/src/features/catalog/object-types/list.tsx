import { useList } from '@refinedev/core';
import {
  Boxes,
  FolderTree,
  Image as ImageIcon,
  Layers,
  Lock,
  Package,
  Plus,
  Tag,
} from 'lucide-react';
import type { ComponentType, SVGProps } from 'react';
import { useEffect, useState } from 'react';
import { useTranslation } from 'react-i18next';
import { CreateCustomObjectTypeDialog } from '@/components/modeling/create-custom-object-type-dialog';
import { ModelingPageHeader } from '@/components/modeling/modeling-page-header';
import { ModelingRow, ModelingSection } from '@/components/modeling/modeling-section';
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
  custom: Package,
};

const SECONDARY_LABEL: Record<string, string> = {
  product: 'Products',
  category: 'Categories',
  asset: 'Assets',
  brand: 'Brands',
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

  const totalInstances = (rows: ObjectTypeRow[]): number =>
    rows.reduce((acc, row) => acc + (instanceCounts[row.id] ?? 0), 0);

  return (
    <div className="space-y-6">
      <ModelingPageHeader
        caption={t('object_types.list_caption', {
          defaultValue: '{{count}} typów obiektów',
          count: types.length,
        })}
        title={t('object_types.list_heading', { defaultValue: 'Object Types' })}
        description={t('object_types.list_description', {
          defaultValue:
            'Każdy ObjectType definiuje czym jest obiekt — Produkt, Usługa, Marka. Built-in typy (🔒) są niezbędne dla integracji i nie mogą być usunięte. Tworzenie własnych typów odblokowuje nowe rodzaje obiektów (np. Subskrypcja, Wydarzenie, Lokalizacja).',
        })}
        ctaLabel={t('object_types.create_custom_action', { defaultValue: '+ Nowy typ' })}
        onCtaClick={() => setCreateOpen(true)}
      />

      {isLoading ? (
        <div className="rounded-2xl border border-line bg-surface p-10 text-center text-muted-foreground soft-shadow">
          {t('app.loading')}
        </div>
      ) : (
        <>
          <ModelingSection
            label={t('object_types.built_in_label', { defaultValue: 'BUILT-IN (SYSTEM)' })}
            tagline={t('object_types.built_in_tagline', {
              defaultValue: 'fundament PIM-u, używane przez integracje',
            })}
            locked
          >
            {builtIn.length === 0 ? (
              <li className="px-5 py-8 text-center text-[13px] text-muted-foreground">
                {t('object_types.empty')}
              </li>
            ) : (
              builtIn.map((row) => (
                <ObjectTypeListRow
                  key={row.id}
                  row={row}
                  language={i18n.language}
                  instanceCount={instanceCounts[row.id]}
                />
              ))
            )}
          </ModelingSection>

          <ModelingSection
            label={t('object_types.custom_label', { defaultValue: 'CUSTOM (YOUR ORGANIZATION)' })}
            tagline={t('object_types.custom_tagline', { defaultValue: 'dodane przez ciebie' })}
            summary={
              <span>
                {t('object_types.custom_summary', {
                  defaultValue: '{{kinds}} typów · {{instances}} instancji',
                  kinds: custom.length,
                  instances: totalInstances(custom).toLocaleString('pl-PL'),
                })}
              </span>
            }
          >
            {custom.length === 0 ? (
              <li className="px-5 py-8 text-center text-[13px] text-muted-foreground">
                {t('object_types.custom_empty', {
                  defaultValue: 'Brak custom ObjectTypes — utwórz pierwszy poniżej.',
                })}
              </li>
            ) : (
              custom.map((row) => (
                <ObjectTypeListRow
                  key={row.id}
                  row={row}
                  language={i18n.language}
                  instanceCount={instanceCounts[row.id]}
                />
              ))
            )}
          </ModelingSection>

          <button
            type="button"
            onClick={() => setCreateOpen(true)}
            className="flex w-full cursor-pointer items-center justify-center gap-2 rounded-2xl border border-dashed border-line bg-surface px-6 py-4 text-[14px] font-medium text-ink-2 transition-colors hover:border-accent-violet/40 hover:bg-accent-violet/5 hover:text-ink"
          >
            <Plus className="size-4" />
            {t('object_types.bottom_cta', {
              defaultValue: '+ Stwórz nowy ObjectType (np. Subskrypcja, Lokalizacja, Wydarzenie)',
            })}
          </button>
        </>
      )}

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

interface ObjectTypeListRowProps {
  row: ObjectTypeRow;
  language: string;
  instanceCount: number | undefined;
}

function ObjectTypeListRow({ row, language, instanceCount }: ObjectTypeListRowProps) {
  const { t } = useTranslation();
  const Icon = KIND_ICONS[row.kind] ?? Layers;
  const isBuiltIn = row.builtIn !== false;
  const accentColor = row.color ?? defaultAccent(row.kind);

  return (
    <ModelingRow
      to={`/modeling/object-types/${row.id}`}
      leading={
        <span
          className="flex size-12 items-center justify-center rounded-xl"
          style={{ backgroundColor: `${accentColor}1a`, color: accentColor }}
        >
          <Icon className="size-5" />
        </span>
      }
      title={resolveLabel(row.label, language)}
      code={row.code}
      badges={
        <>
          {isBuiltIn ? (
            <span className="inline-flex items-center gap-1 rounded-md bg-muted px-1.5 py-0.5 text-[10.5px] font-medium uppercase tracking-wide text-muted-foreground">
              <Lock className="size-2.5" />
              {t('object_types.system_badge', { defaultValue: 'system' })}
            </span>
          ) : null}
          {row.kind === 'product' ? (
            <span className="rounded-md bg-accent-violet/10 px-1.5 py-0.5 text-[10.5px] font-medium uppercase tracking-wide text-accent-violet">
              variants
            </span>
          ) : null}
          {row.kind === 'category' ? (
            <span className="rounded-md bg-accent-emerald/10 px-1.5 py-0.5 text-[10.5px] font-medium uppercase tracking-wide text-accent-emerald">
              hierarchical
            </span>
          ) : null}
        </>
      }
      secondaryLabel={SECONDARY_LABEL[row.kind] ?? row.kind}
      metaPrimary={t('object_types.row_groups_count', {
        defaultValue: '{{count}} grup atrybutów',
        count: row.schemaVersion ?? 3,
      })}
      metaSecondary={
        <span>
          {instanceCount === undefined ? '—' : instanceCount.toLocaleString('pl-PL')}{' '}
          <span className="font-normal text-muted-foreground">
            {t('object_types.row_instances', { defaultValue: 'instancji' })}
          </span>
        </span>
      }
    />
  );
}

function defaultAccent(kind: string): string {
  switch (kind) {
    case 'product':
      return '#3b82f6';
    case 'category':
      return '#10b981';
    case 'asset':
      return '#a855f7';
    case 'brand':
      return '#f59e0b';
    default:
      return '#71717a';
  }
}

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
