import * as React from 'react';

export type WizardStepIndex = 0 | 1 | 2 | 3 | 4 | 5 | 6;

/** #1678 — data kind chosen on the first wizard step (tiles). */
export type ImportEntityType =
  | 'product'
  | 'custom_module'
  | 'categories'
  | 'attributes'
  | 'attribute_groups';

/**
 * Structural kinds import configuration entities (attribute / attribute-group
 * definitions), not CatalogObjects. They carry no target ObjectType and run a
 * simplified 4-step flow (Dane → Źródło → Wykrywanie → Start) — column mapping,
 * match rules and dry-run are meaningless for a fixed-schema config import.
 */
export function isStructuralImportKind(kind: ImportEntityType | null): boolean {
  return kind === 'attributes' || kind === 'attribute_groups';
}

/** Last step index per flow: structural = 4 steps (0-3), catalog = 7 (0-6). */
export function lastStepFor(kind: ImportEntityType | null): WizardStepIndex {
  return isStructuralImportKind(kind) ? 3 : 6;
}

/** Authoritative parse-preview snapshot captured on the Detect step (NUI-10). */
export interface ParsedFilePreview {
  headers: string[];
  sampleRows: Array<Array<string | null>>;
  totalRows: number;
  encoding: string;
  delimiter: string | null;
  sheetName: string | null;
  hadMultipleSheets: boolean;
}

export interface ColumnSuggestion {
  column_index: number;
  column_header: string;
  suggested_attribute_code: string | null;
  confidence: 'auto' | 'fuzzy' | 'manual' | 'skip';
  sample_values: Array<string | null>;
}

export interface ValidationFinding {
  rowNumber: number;
  sku: string | null;
  errorType: string;
  level: 'info' | 'warning' | 'error';
  message: string;
  columnName: string | null;
  columnValue: string | null;
}

export interface WizardState {
  step: WizardStepIndex;
  /** #1678 — data kind picked on step 1 (tiles); null until a tile is chosen. */
  entityType: ImportEntityType | null;
  file: File | null;
  zipFile: File | null;
  locale: string | null;
  encoding: string;
  delimiter: string;
  imageSource: 'http' | 'zip' | 'none';
  profileId: string | null;
  saveAsProfileName: string | null;
  targetObjectTypeId: string | null;
  /** Header → attribute_code (or "skip"). */
  mapping: Record<string, string>;
  suggestions: ColumnSuggestion[];
  /** Set by the Detect step; Mapping reuses it instead of re-parsing. Not persisted (derived from the File). */
  parsed: ParsedFilePreview | null;
  /**
   * IMP2-2.2 — id of the file staged once at parse-preview; the dry-run +
   * start steps send this instead of re-uploading the bytes. Reset to null
   * whenever a new file is chosen.
   */
  stagedFileId: string | null;
  validation: {
    totalRows: number;
    successCount: number;
    errorCount: number;
    topErrors: ValidationFinding[];
  } | null;
  doBackup: boolean;
  emailNotification: boolean;
  /** IMP2-1.3 (#1465) — write strategy sent to POST /api/import-sessions. */
  mode: 'CREATE' | 'UPDATE' | 'UPSERT';
  /**
   * #1718 — when true, the run mints missing select/multiselect options
   * instead of failing rows with unknown values. Opt-in (data governance).
   */
  createMissingOptions: boolean;
}

const INITIAL_STATE: WizardState = {
  step: 0,
  entityType: null,
  file: null,
  zipFile: null,
  locale: null,
  encoding: 'auto',
  delimiter: 'auto',
  imageSource: 'none',
  profileId: null,
  saveAsProfileName: null,
  targetObjectTypeId: null,
  mapping: {},
  mode: 'UPSERT',
  createMissingOptions: false,
  suggestions: [],
  parsed: null,
  stagedFileId: null,
  validation: null,
  doBackup: false,
  emailNotification: true,
};

interface WizardController {
  state: WizardState;
  setField: <K extends keyof WizardState>(field: K, value: WizardState[K]) => void;
  patchMapping: (header: string, attributeCode: string) => void;
  next: () => void;
  back: () => void;
  reset: () => void;
}

/**
 * Holds the wizard's cross-step state (spec §4 user flow). State lives only
 * for the lifetime of the wizard mount — there is deliberately no localStorage
 * round-trip: the uploaded File cannot be serialised, so a deep-link away and
 * back used to drop the mapping (#1737).
 */
export function useImportWizard(): WizardController {
  const [state, setState] = React.useState<WizardState>(INITIAL_STATE);

  const setField = React.useCallback(
    <K extends keyof WizardState>(field: K, value: WizardState[K]) => {
      setState((prev) => ({ ...prev, [field]: value }));
    },
    [],
  );

  const patchMapping = React.useCallback((header: string, attributeCode: string) => {
    setState((prev) => ({
      ...prev,
      mapping: { ...prev.mapping, [header]: attributeCode },
    }));
  }, []);

  const next = React.useCallback(() => {
    setState((prev) => ({
      ...prev,
      step: Math.min(prev.step + 1, lastStepFor(prev.entityType)) as WizardStepIndex,
    }));
  }, []);

  const back = React.useCallback(() => {
    setState((prev) => ({
      ...prev,
      step: Math.max(prev.step - 1, 0) as WizardStepIndex,
    }));
  }, []);

  const reset = React.useCallback(() => {
    setState(INITIAL_STATE);
  }, []);

  return { state, setField, patchMapping, next, back, reset };
}
