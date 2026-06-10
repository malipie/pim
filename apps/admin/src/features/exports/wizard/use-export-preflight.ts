import { useEffect, useRef, useState } from 'react';

import type { FilterDsl } from '@/lib/filters/filter-dsl';
import { jsonFetch } from '@/lib/http';

import type { ExportEntityType, ExportTargetScope, PreflightResult } from './types';

interface PreflightInput {
  entityType: ExportEntityType;
  objectTypeId: string | null;
  targetScope: ExportTargetScope;
  filterDsl: FilterDsl | null;
  selectedIds: string[] | null;
  /** Skip probing (e.g. custom_module without a chosen ObjectType). */
  enabled?: boolean;
}

interface PreflightState {
  result: PreflightResult | null;
  isLoading: boolean;
  error: boolean;
}

const DEBOUNCE_MS = 500;

/**
 * EXR-10 — debounced POST /api/exports/preflight (EXR-07) on every
 * configuration change. Stale responses are discarded by sequence id.
 */
export function useExportPreflight(input: PreflightInput): PreflightState {
  const [state, setState] = useState<PreflightState>({
    result: null,
    isLoading: false,
    error: false,
  });
  const seqRef = useRef(0);

  const { entityType, objectTypeId, targetScope, filterDsl, selectedIds, enabled = true } = input;
  const filterKey = JSON.stringify(filterDsl);
  const selectedKey = JSON.stringify(selectedIds);

  // biome-ignore lint/correctness/useExhaustiveDependencies: filterDsl/selectedIds tracked via their JSON keys
  useEffect(() => {
    if (!enabled) {
      setState({ result: null, isLoading: false, error: false });
      return;
    }
    const seq = ++seqRef.current;
    setState((prev) => ({ ...prev, isLoading: true, error: false }));
    const timer = setTimeout(() => {
      const payload: Record<string, unknown> = {
        entity_type: entityType,
        target_scope: targetScope,
      };
      if (objectTypeId !== null) payload.object_type_id = objectTypeId;
      if (targetScope === 'filter' && filterDsl !== null) payload.filter = filterDsl;
      if (targetScope === 'selected') payload.selected_object_ids = selectedIds ?? [];

      jsonFetch<PreflightResult>('/api/exports/preflight', {
        method: 'POST',
        accept: 'application/json',
        body: payload,
      })
        .then((result) => {
          if (seqRef.current === seq) {
            setState({ result, isLoading: false, error: false });
          }
        })
        .catch(() => {
          if (seqRef.current === seq) {
            setState({ result: null, isLoading: false, error: true });
          }
        });
    }, DEBOUNCE_MS);
    return () => clearTimeout(timer);
  }, [entityType, objectTypeId, targetScope, filterKey, selectedKey, enabled]);

  return state;
}
