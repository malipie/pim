/**
 * EXR-14 — shared column-catalog types + built-in groups. Extracted from
 * the retired ColumnPicker (EXP-10) so the catalog hook and the v2
 * picker (EXR-11) keep the contract after the legacy component removal.
 */
export interface ColumnGroup {
  id: string;
  labelKey: string;
  defaultLabel: string;
  columns: ColumnOption[];
}

export interface ColumnOption {
  key: string;
  labelKey: string;
  defaultLabel: string;
}

export const BUILT_IN_COLUMN_GROUPS: readonly ColumnGroup[] = [
  {
    id: 'identity',
    labelKey: 'exports.column_picker.group_identity',
    defaultLabel: 'Identyfikacja',
    columns: [
      { key: 'sku', labelKey: 'exports.columns.sku', defaultLabel: 'SKU' },
      { key: 'parent_sku', labelKey: 'exports.columns.parent_sku', defaultLabel: 'SKU rodzica' },
      { key: 'category', labelKey: 'exports.columns.category', defaultLabel: 'Kategorie' },
    ],
  },
  {
    id: 'lifecycle',
    labelKey: 'exports.column_picker.group_lifecycle',
    defaultLabel: 'Stan',
    columns: [
      { key: 'status', labelKey: 'exports.columns.status', defaultLabel: 'Status' },
      { key: 'enabled', labelKey: 'exports.columns.enabled', defaultLabel: 'Włączony' },
      {
        key: 'completeness_pct',
        labelKey: 'exports.columns.completeness_pct',
        defaultLabel: 'Kompletność (%)',
      },
      { key: 'created_at', labelKey: 'exports.columns.created_at', defaultLabel: 'Utworzono' },
      { key: 'updated_at', labelKey: 'exports.columns.updated_at', defaultLabel: 'Zmodyfikowano' },
    ],
  },
] as const;
