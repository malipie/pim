import type { FilterGroup } from '@/lib/filters/filter-dsl';

/** Export entity types — synced with backend ExportEntityType (EXR-04). */
export type ExportEntityType =
  | 'products'
  | 'custom_module'
  | 'module_schema'
  | 'attributes'
  | 'categories';

export type ExportFormat = 'xlsx' | 'csv';

export type ExportTargetScope = 'all' | 'filter' | 'selected';

/** Whole-wizard state (EXR-09); steps 2-4 fill the optional fields. */
export interface WizardState {
  step: number;
  entityType: ExportEntityType;
  /** Required when entityType=custom_module — the chosen custom ObjectType. */
  objectTypeId: string | null;
  profileId: string | null;
  format: ExportFormat;
  filterDsl: FilterGroup | null;
  selectedIds: string[] | null;
  targetScope: ExportTargetScope;
  columns: string[];
  locales: string[] | null;
  channels: string[] | null;
  profileName: string;
  /** Any user-made change — drives cancel/entity-switch confirmations. */
  dirty: boolean;
}

export type WizardAction =
  | { type: 'SET_ENTITY_TYPE'; entityType: ExportEntityType }
  | { type: 'SET_OBJECT_TYPE_ID'; objectTypeId: string | null }
  | { type: 'GO_TO_STEP'; step: number }
  | { type: 'SET_FORMAT'; format: ExportFormat }
  | { type: 'SET_PROFILE'; profileId: string | null }
  | { type: 'SET_FILTER'; filterDsl: FilterGroup | null; targetScope: ExportTargetScope }
  | { type: 'SET_SELECTED_IDS'; selectedIds: string[] | null }
  | { type: 'SET_COLUMNS'; columns: string[] }
  | { type: 'SET_LOCALES'; locales: string[] | null }
  | { type: 'SET_CHANNELS'; channels: string[] | null }
  | { type: 'SET_PROFILE_NAME'; profileName: string };

export const WIZARD_STEP_COUNT = 4;

export const INITIAL_WIZARD_STATE: WizardState = {
  step: 0,
  entityType: 'products',
  objectTypeId: null,
  profileId: null,
  format: 'xlsx',
  filterDsl: null,
  selectedIds: null,
  targetScope: 'all',
  columns: [],
  locales: null,
  channels: null,
  profileName: '',
  dirty: false,
};
