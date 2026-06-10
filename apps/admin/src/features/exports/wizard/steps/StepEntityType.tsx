import { useQuery } from '@tanstack/react-query';
import { Boxes, FolderTree, Layers, Package, Tags } from 'lucide-react';
import { useTranslation } from 'react-i18next';

import { SelectableCard, SelectableCardGroup } from '@/components/ui-v2/selectable-card';
import { jsonFetch } from '@/lib/http';
import { cn } from '@/lib/utils';

import type { ExportEntityType } from '../types';
import { hasDownstreamConfig, useWizard } from '../wizard-store';

interface ObjectTypeRow {
  id: string;
  code: string;
  kind?: string;
  builtIn?: boolean;
  label?: Record<string, string>;
}

interface ObjectTypesResponse {
  member?: ObjectTypeRow[];
  'hydra:member'?: ObjectTypeRow[];
}

const ENTITY_DEFS: Array<{ id: ExportEntityType; icon: typeof Package }> = [
  { id: 'products', icon: Package },
  { id: 'custom_module', icon: Boxes },
  { id: 'module_schema', icon: Layers },
  { id: 'attributes', icon: Tags },
  { id: 'categories', icon: FolderTree },
];

/** Custom (non-built-in) ObjectTypes for the custom_module second row. */
function useCustomObjectTypes() {
  return useQuery({
    queryKey: ['object-types', 'custom'],
    staleTime: 60_000,
    queryFn: async (): Promise<ObjectTypeRow[]> => {
      const response = await jsonFetch<ObjectTypesResponse>('/api/object_types');
      const rows = response.member ?? response['hydra:member'] ?? [];
      return rows.filter((row) => row.kind === 'custom' || row.builtIn === false);
    },
  });
}

/**
 * EXR-09 — wizard step 1 (screen 2): 3+2 grid of entity tiles. Picking
 * "Moduły własne" reveals an ObjectType select (custom OTs only); the
 * tile is disabled with a tooltip when the tenant has none. Switching
 * entity after configuring later steps asks for confirmation and resets
 * steps 2-4 (reducer handles the reset).
 */
export function StepEntityType() {
  const { t, i18n } = useTranslation();
  const { state, dispatch } = useWizard();
  const customTypes = useCustomObjectTypes();

  const customAvailable = (customTypes.data?.length ?? 0) > 0;

  const selectEntity = (entityType: ExportEntityType) => {
    if (entityType === state.entityType) return;
    if (hasDownstreamConfig(state) && !window.confirm(t('exports.wizard.entity_switch_confirm'))) {
      return;
    }
    dispatch({ type: 'SET_ENTITY_TYPE', entityType });
  };

  const labelOf = (row: ObjectTypeRow): string => {
    const labels = row.label ?? {};
    return labels[i18n.language] ?? labels.pl ?? labels.en ?? row.code;
  };

  return (
    <div className="rounded-2xl border border-zinc-200 bg-surface p-7 shadow-card">
      <h2 className="text-[16px] font-semibold tracking-tight text-ink">
        {t('exports.wizard.step1_title')}
      </h2>
      <p className="mt-1 text-[13px] text-zinc-500">{t('exports.wizard.step1_subtitle')}</p>

      <SelectableCardGroup
        ariaLabel={t('exports.wizard.entity_group_aria')}
        className="mt-5 grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-3"
      >
        {ENTITY_DEFS.map(({ id, icon: Icon }) => (
          <SelectableCard
            key={id}
            icon={<Icon className="size-5" aria-hidden />}
            title={t(`exports.entity.${id}`)}
            description={t(`exports.wizard.entity_desc.${id}`)}
            selected={state.entityType === id}
            disabled={id === 'custom_module' && !customAvailable && !customTypes.isLoading}
            onSelect={() => selectEntity(id)}
          />
        ))}
      </SelectableCardGroup>

      {state.entityType === 'custom_module' && (
        <div className="mt-5 rounded-xl border border-zinc-200 bg-surface-muted p-4">
          <label
            htmlFor="wizard-custom-object-type"
            className="block text-[11px] font-medium tracking-wider text-zinc-400 uppercase"
          >
            {t('exports.wizard.custom_object_type_label')}
          </label>
          <select
            id="wizard-custom-object-type"
            value={state.objectTypeId ?? ''}
            onChange={(event) => {
              dispatch({ type: 'SET_OBJECT_TYPE_ID', objectTypeId: event.target.value || null });
            }}
            className={cn(
              'focus-ring mt-2 h-10 w-full max-w-md rounded-xl border bg-surface px-3 text-[13px]',
              state.objectTypeId === null ? 'border-brick-300' : 'border-zinc-200',
            )}
          >
            <option value="">{t('exports.wizard.custom_object_type_placeholder')}</option>
            {(customTypes.data ?? []).map((row) => (
              <option key={row.id} value={row.id}>
                {labelOf(row)}
              </option>
            ))}
          </select>
          {state.objectTypeId === null && (
            <p className="mt-1.5 text-[12px] text-brick-600">
              {t('exports.wizard.custom_object_type_required')}
            </p>
          )}
        </div>
      )}
    </div>
  );
}
