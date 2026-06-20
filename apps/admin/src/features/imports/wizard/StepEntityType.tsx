import { Boxes, FolderTree, Layers, Package, Tags } from 'lucide-react';
import { type ReactElement, useEffect } from 'react';
import { useTranslation } from 'react-i18next';

import { Button } from '@/components/ui/button';
import { SelectableCard, SelectableCardGroup } from '@/components/ui-v2/selectable-card';
import {
  type ImportObjectTypeRow,
  useImportEntityObjectTypes,
} from '@/features/imports/hooks/use-import-entity-object-types';
import type { ImportEntityType, useImportWizard } from '@/features/imports/hooks/useImportWizard';
import { cn } from '@/lib/utils';

/** #1678 — tile ids: the three importable entity kinds + two "soon" tiles
 * mirroring the export wizard (import does not yet create schema/attribute
 * definitions from a file). */
type TileId = ImportEntityType | 'module_schema' | 'attributes_groups';

const ENTITY_DEFS: Array<{ id: TileId; icon: typeof Package; comingSoon?: boolean }> = [
  { id: 'product', icon: Package },
  { id: 'custom_module', icon: Boxes },
  { id: 'module_schema', icon: Layers, comingSoon: true },
  { id: 'attributes_groups', icon: Tags, comingSoon: true },
  { id: 'categories', icon: FolderTree },
];

const DEFAULT_TITLES: Record<TileId, string> = {
  product: 'Produkty',
  custom_module: 'Moduły własne',
  module_schema: 'Schemat modułów',
  attributes_groups: 'Atrybuty i grupy',
  categories: 'Kategorie',
};

const DEFAULT_DESCS: Record<TileId, string> = {
  product: 'Importuj główny katalog produktów, warianty, ceny i powiązane multimedia.',
  custom_module:
    'Importuj dane do zdefiniowanych przez użytkownika modułów niestandardowych (np. Producenci, Kolekcje).',
  module_schema: 'Wgranie struktury definicji i relacji modułów — wkrótce.',
  attributes_groups: 'Import słownika atrybutów, ich wartości i podziału na grupy — wkrótce.',
  categories: 'Importuj strukturę drzewa kategorii oraz tłumaczenia nazw.',
};

interface StepEntityTypeProps {
  wizard: ReturnType<typeof useImportWizard>;
}

/**
 * #1678 — import wizard step 1: a 3+2 tile grid mirroring the export wizard's
 * StepEntityType. Product / Moduły własne / Kategorie are importable; Schemat
 * modułów + Atrybuty i grupy are "soon" (the pipeline creates objects per
 * ObjectType, not metadata). Picking a tile maps to the concrete
 * `targetObjectTypeId`; "Moduły własne" reveals a custom-ObjectType select.
 * Reuses the generic SelectableCard/SelectableCardGroup components.
 */
export function StepEntityType({ wizard }: StepEntityTypeProps): ReactElement {
  const { t, i18n } = useTranslation();
  const { state, setField, next } = wizard;
  const lang = i18n.language === 'pl' ? 'pl' : 'en';
  const objectTypes = useImportEntityObjectTypes();

  const rows = objectTypes.data ?? [];
  const productType = rows.find((row) => row.kind === 'product');
  const categoryType = rows.find((row) => row.kind === 'category');
  const customTypes = rows.filter((row) => row.kind === 'custom');
  const customAvailable = customTypes.length > 0;

  // #1678 — object_types load async; if a tile was picked before the query
  // resolved, backfill its targetObjectTypeId once the built-in OT is known
  // (product/categories). custom_module is set explicitly through the dropdown.
  useEffect(() => {
    if (state.targetObjectTypeId !== null) {
      return;
    }
    if (state.entityType === 'product' && productType !== undefined) {
      setField('targetObjectTypeId', productType.id);
    } else if (state.entityType === 'categories' && categoryType !== undefined) {
      setField('targetObjectTypeId', categoryType.id);
    }
  }, [state.entityType, state.targetObjectTypeId, productType, categoryType, setField]);

  const selectEntity = (id: TileId): void => {
    if (id === 'module_schema' || id === 'attributes_groups') {
      return; // "soon" tiles are inert
    }
    if (id !== state.entityType) {
      // Column mapping + auto-suggestions target the previous type's
      // attributes, so they are stale once the data kind changes.
      setField('mapping', {});
      setField('suggestions', []);
    }
    setField('entityType', id);
    if (id === 'product') {
      setField('targetObjectTypeId', productType?.id ?? null);
    } else if (id === 'categories') {
      setField('targetObjectTypeId', categoryType?.id ?? null);
    } else {
      setField('targetObjectTypeId', null); // custom_module → choose below
    }
  };

  const labelOf = (row: ImportObjectTypeRow): string => {
    const labels = row.label ?? {};
    return labels[lang] ?? labels.pl ?? labels.en ?? row.code;
  };

  const canProceed = state.entityType !== null && state.targetObjectTypeId !== null;

  return (
    <div className="space-y-4">
      <div className="rounded-2xl border border-zinc-100 bg-white p-7 shadow-sm">
        <h2 className="text-[16px] font-semibold tracking-tight">
          {t('imports.wizard.step1_title', { defaultValue: 'Krok 1: Wybierz dane do importu' })}
        </h2>
        <p className="mt-1 text-[13px] text-zinc-500">
          {t('imports.wizard.step1_subtitle', {
            defaultValue: 'Wybierz główny typ encji, do którego trafią wiersze pliku.',
          })}
        </p>

        <SelectableCardGroup
          ariaLabel={t('imports.wizard.entity_group_aria', {
            defaultValue: 'Wybór typu danych do importu',
          })}
          className="mt-5 grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-3"
        >
          {ENTITY_DEFS.map(({ id, icon: Icon, comingSoon }) => (
            <SelectableCard
              key={id}
              icon={<Icon className="size-5" aria-hidden />}
              title={t(`imports.entity.${id}`, { defaultValue: DEFAULT_TITLES[id] })}
              description={t(`imports.wizard.entity_desc.${id}`, {
                defaultValue: DEFAULT_DESCS[id],
              })}
              selected={state.entityType === id}
              disabled={
                comingSoon === true ||
                (id === 'custom_module' && !customAvailable && !objectTypes.isLoading)
              }
              onSelect={() => selectEntity(id)}
            />
          ))}
        </SelectableCardGroup>

        {state.entityType === 'custom_module' && (
          <div className="mt-5 rounded-xl border border-zinc-200 bg-zinc-50/60 p-4">
            <label
              htmlFor="import-custom-object-type"
              className="block text-[11px] font-medium uppercase tracking-wider text-zinc-500"
            >
              {t('imports.wizard.custom_object_type_label', {
                defaultValue: 'Wybierz moduł własny',
              })}
            </label>
            <select
              id="import-custom-object-type"
              value={state.targetObjectTypeId ?? ''}
              onChange={(event) => setField('targetObjectTypeId', event.target.value || null)}
              className={cn(
                'mt-2 h-10 w-full max-w-md rounded-xl border bg-white px-3 text-[13px]',
                state.targetObjectTypeId === null ? 'border-rose-300' : 'border-zinc-200',
              )}
            >
              <option value="">
                {t('imports.wizard.custom_object_type_placeholder', {
                  defaultValue: '— wybierz —',
                })}
              </option>
              {customTypes.map((row) => (
                <option key={row.id} value={row.id}>
                  {labelOf(row)}
                </option>
              ))}
            </select>
            {state.targetObjectTypeId === null && (
              <p className="mt-1.5 text-[12px] text-rose-600">
                {t('imports.wizard.custom_object_type_required', {
                  defaultValue: 'Wybierz moduł własny, aby kontynuować',
                })}
              </p>
            )}
          </div>
        )}
      </div>

      <div className="flex justify-end gap-2">
        <Button onClick={() => next()} disabled={!canProceed}>
          {t('imports.wizard.next', { defaultValue: 'Dalej →' })}
        </Button>
      </div>
    </div>
  );
}
