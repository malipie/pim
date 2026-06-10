import { useEffect, useRef } from 'react';
import { useTranslation } from 'react-i18next';

import { ColumnPickerV2 } from '../../components/ColumnPickerV2';
import { useExportColumnCatalog } from '../../components/use-export-column-catalog';
import { useWizard } from '../wizard-store';

/**
 * EXR-11 — wizard step 3 (screen 4): two-pane attribute picker. The
 * selection (and its order = file column order) lives in the wizard
 * store, so Wstecz/Dalej keep it and an entity switch clears it.
 *
 * Round-trip contract (PRD): the natural key (`sku` for products,
 * `code` for custom modules) is locked as the first column. Structural
 * entities default to ALL columns selected (deselect allowed).
 */
export function StepColumns() {
  const { t } = useTranslation();
  const { state, dispatch } = useWizard();

  const structural = state.entityType !== 'product' && state.entityType !== 'custom_module';
  const lockedKey = state.entityType === 'product' ? 'sku' : structural ? undefined : 'code';

  const catalog = useExportColumnCatalog({
    entityType: state.entityType,
    objectTypeId: state.objectTypeId,
  });

  // Defaults on first entry: locked key for row entities, everything for
  // structural ones. Runs once per catalog load while no column is chosen.
  const seededRef = useRef(false);
  useEffect(() => {
    if (seededRef.current || catalog.isLoading || state.columns.length > 0) return;
    seededRef.current = true;
    if (structural) {
      const all = catalog.groups.flatMap((group) => group.columns.map((column) => column.key));
      if (all.length > 0) {
        dispatch({ type: 'SET_COLUMNS', columns: all });
      }
      return;
    }
    if (lockedKey !== undefined) {
      dispatch({ type: 'SET_COLUMNS', columns: [lockedKey] });
    }
  }, [catalog.isLoading, catalog.groups, state.columns.length, structural, lockedKey, dispatch]);

  return (
    <div className="space-y-4">
      {catalog.error !== null && (
        <p className="rounded-xl bg-brick-50 px-4 py-3 text-[13px] text-brick-700">
          {t('exports.picker.catalog_error')}
        </p>
      )}
      <ColumnPickerV2
        groups={catalog.groups}
        value={state.columns}
        onChange={(columns) => dispatch({ type: 'SET_COLUMNS', columns })}
        lockedKey={lockedKey}
        isLoading={catalog.isLoading}
      />
    </div>
  );
}
