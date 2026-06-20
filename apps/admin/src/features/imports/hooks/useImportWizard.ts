import * as React from 'react';

export type WizardStepIndex = 0 | 1 | 2 | 3 | 4 | 5 | 6;

/** #1678 — data kind chosen on the first wizard step (tiles). */
export type ImportEntityType = 'product' | 'custom_module' | 'categories';

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
  /** Persist for cross-page round-trips (e.g. "Stwórz nowy atrybut"). */
  persist: () => void;
  /** Restore after returning from /modeling — wipes the saved snapshot. */
  restore: () => void;
}

const STORAGE_KEY = 'pim.imports.wizard';

/**
 * Holds the wizard's cross-step state (spec §4 user flow). The
 * persistence path is the deep-link to /modeling/attributes/new
 * during Step 2: the user creates a custom attribute and lands back
 * on the same wizard, mid-flight. {@link persist} writes the
 * non-File fields to localStorage; {@link restore} pulls them back
 * once the page rehydrates. Files cannot be serialised, so the user
 * re-uploads the source after the round-trip — UX surfaces a hint.
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
      step: Math.min(prev.step + 1, 6) as WizardStepIndex,
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
    if (typeof window !== 'undefined') {
      window.localStorage.removeItem(STORAGE_KEY);
    }
  }, []);

  const persist = React.useCallback(() => {
    if (typeof window === 'undefined') {
      return;
    }
    const persisted = {
      step: state.step,
      entityType: state.entityType,
      locale: state.locale,
      encoding: state.encoding,
      delimiter: state.delimiter,
      imageSource: state.imageSource,
      profileId: state.profileId,
      saveAsProfileName: state.saveAsProfileName,
      targetObjectTypeId: state.targetObjectTypeId,
      mapping: state.mapping,
      stagedFileId: state.stagedFileId,
      doBackup: state.doBackup,
      emailNotification: state.emailNotification,
    };
    window.localStorage.setItem(STORAGE_KEY, JSON.stringify(persisted));
  }, [state]);

  const restore = React.useCallback(() => {
    if (typeof window === 'undefined') {
      return;
    }
    const raw = window.localStorage.getItem(STORAGE_KEY);
    if (raw === null) {
      return;
    }
    try {
      const parsed = JSON.parse(raw) as Partial<WizardState>;
      setState((prev) => ({ ...prev, ...parsed }));
    } catch {
      // Stale snapshot — drop it.
    }
    window.localStorage.removeItem(STORAGE_KEY);
  }, []);

  return { state, setField, patchMapping, next, back, reset, persist, restore };
}
