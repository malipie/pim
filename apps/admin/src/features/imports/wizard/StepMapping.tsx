import { useApiUrl, useCustom, useList } from '@refinedev/core';
import * as React from 'react';
import { useTranslation } from 'react-i18next';

import { Button } from '@/components/ui/button';
import { Combobox, type ComboboxOption } from '@/components/ui/combobox';
import type { ColumnSuggestion, useImportWizard } from '@/features/imports/hooks/useImportWizard';
import { jsonFetch } from '@/lib/http';
import { cn } from '@/lib/utils';

import { ComputedColumnModal } from './ComputedColumnModal';

interface StepMappingProps {
  wizard: ReturnType<typeof useImportWizard>;
}

interface ParsedFileSnapshot {
  headers: string[];
  sampleRows: Array<Array<string | null>>;
  totalRows: number;
}

interface ParsePreviewResponse {
  headers: string[];
  sample_rows: Array<Array<string | null>>;
  total_rows: number;
  encoding: string;
  delimiter: string | null;
  sheet_name: string | null;
  had_multiple_sheets: boolean;
}

interface AutoMapResponse {
  mappings: Array<{
    column_index: number;
    column_header: string;
    suggested_attribute_code: string | null;
    confidence: 'auto' | 'fuzzy' | 'manual' | 'skip';
    sample_values: Array<string | null>;
  }>;
}

interface AttributeOption {
  id: string;
  code: string;
  label?: Record<string, string>;
}

const SKIP_VALUE = '__skip__';
const CATEGORY_VALUE = '__category__';
// IMP2-1.7 — reserved mapping targets mirrored from
// App\Import\Domain\ReservedMappingTarget.
const CATEGORY_APPEND_VALUE = '__category_append__';
const STATUS_VALUE = '__status__';
const ENABLED_VALUE = '__enabled__';

/**
 * Spec §5.3 — column mapping. Streams the uploaded headers + first
 * sample row to the IMP-02 endpoint, renders the mapping table on
 * top of the IMP-08 Combobox, and writes the picks back through
 * `useImportWizard`. Missing attributes are no longer created via a
 * deep-link round-trip (#1737, it dropped the mapping) — the run mints
 * them when "create missing options/attributes" is enabled (#1728).
 */
export function StepMapping({ wizard }: StepMappingProps): React.ReactElement {
  const { t } = useTranslation();
  const { state, setField, patchMapping, next, back } = wizard;
  const [computedOpen, setComputedOpen] = React.useState(false);
  const apiUrl = useApiUrl();

  const file = state.file;
  const targetId = state.targetObjectTypeId;

  // Upload the file to /parse-preview so the backend's PhpSpreadsheet
  // (xlsx) + league-csv (CSV) pipeline produces authoritative headers +
  // sample rows. Doing this in the browser used to work for CSV but the
  // xlsx path sent a single "__xlsx__" sentinel and left Mapping with
  // one fake column — solved by routing both formats through the same
  // server-side parser used by the validate / start-import flows.
  const {
    snapshot: freshParse,
    isLoading: isParsing,
    error: parseError,
  } = useParsedSnapshot(state.parsed !== null ? null : file, state.encoding, state.delimiter);
  // NUI-10 — the Detect step already paid for parse-preview; reuse its
  // snapshot and only re-parse when it is missing (deep-link restore).
  const parsed: ParsedFileSnapshot | null =
    state.parsed !== null
      ? {
          headers: state.parsed.headers,
          sampleRows: state.parsed.sampleRows,
          totalRows: state.parsed.totalRows,
        }
      : freshParse;

  const { result: autoMapResult, query: autoMapQuery } = useCustom<AutoMapResponse>({
    url: `${apiUrl}/import-sessions/auto-map`,
    method: 'post',
    config: {
      payload: {
        column_headers: parsed?.headers ?? [],
        sample_values: parsed?.sampleRows ?? [],
        target_object_type_id: targetId ?? '',
      },
    },
    queryOptions: {
      enabled: parsed !== null && targetId !== null,
    },
  });
  const isLoading = isParsing || autoMapQuery.isLoading;
  const queryError = autoMapQuery.error;
  const suggestions = autoMapResult.data?.mappings ?? [];
  // Cache fetched suggestions on the wizard so re-renders skip the
  // heavy network round-trip.
  if (suggestions.length > 0 && state.suggestions.length === 0) {
    setField('suggestions', suggestions as ColumnSuggestion[]);
    const initialMapping: Record<string, string> = {};
    for (const suggestion of suggestions) {
      if (suggestion.suggested_attribute_code !== null) {
        initialMapping[suggestion.column_header] = suggestion.suggested_attribute_code;
      }
    }
    setField('mapping', { ...state.mapping, ...initialMapping });
  }

  const attributes = useTargetAttributes(targetId);
  const attributeOptions: ComboboxOption[] = attributes.map((attribute) => ({
    value: attribute.code,
    label: pickLabel(attribute.label) ?? attribute.code,
    description: attribute.code,
  }));
  // The reserved "Kategoria" target lands in object_categories junction
  // table — surfaced at the top of the dropdown so operators can map a
  // CSV column to category assignment without it being one of the
  // tenant's regular Attributes.
  const optionsWithSkip: ComboboxOption[] = [
    { value: SKIP_VALUE, label: t('imports.mapping.skip', { defaultValue: 'Pomiń' }) },
    {
      value: CATEGORY_VALUE,
      label: t('imports.mapping.category', {
        defaultValue: 'Kategoria (przypisanie po kodzie)',
      }),
      description: t('imports.mapping.category_hint', {
        defaultValue: 'Linkuje produkt do kategorii o pasującym code',
      }),
    },
    {
      value: CATEGORY_APPEND_VALUE,
      label: t('imports.mapping.category_append', {
        defaultValue: 'Kategoria (dołącz)',
      }),
      description: t('imports.mapping.category_append_hint', {
        defaultValue: 'Dokłada kategorie do istniejących zamiast zastępować',
      }),
    },
    {
      value: STATUS_VALUE,
      label: t('imports.mapping.status', { defaultValue: 'Status' }),
      description: t('imports.mapping.status_hint', {
        defaultValue: 'Ustawia status: draft, published lub archived',
      }),
    },
    {
      value: ENABLED_VALUE,
      label: t('imports.mapping.enabled', { defaultValue: 'Włączony' }),
      description: t('imports.mapping.enabled_hint', {
        defaultValue: 'Ustawia flagę włączenia (true/false)',
      }),
    },
    ...attributeOptions,
  ];

  const autoCount = state.suggestions.filter((row) => row.confidence === 'auto').length;
  const manualCount = state.suggestions.filter((row) => row.confidence === 'manual').length;

  return (
    <div className="space-y-6 rounded-md border bg-card p-6">
      <header className="space-y-1">
        <h2 className="text-lg font-semibold">
          {t('imports.wizard.steps.mapping', { defaultValue: 'Mapping kolumn' })}
        </h2>
        {state.suggestions.length > 0 && (
          <p className="text-sm text-muted-foreground">
            {t('imports.mapping.header', {
              auto: autoCount,
              total: state.suggestions.length,
              manual: manualCount,
              defaultValue:
                'Auto-mapping zakończony: {{auto}}/{{total}} kolumn dopasowanych. Zmapuj {{manual}} ręcznie.',
            })}
          </p>
        )}
      </header>

      {file === null && (
        <p className="text-sm text-muted-foreground">
          {t('imports.errors.upload_failed', {
            defaultValue: 'Nie udało się wczytać pliku — wróć do Step 1.',
          })}
        </p>
      )}

      {parseError !== null && (
        <p role="alert" className="text-sm text-destructive">
          {t('imports.errors.parse_failed', {
            defaultValue: 'Nie udało się sparsować pliku: {{message}}',
            message: parseError.message,
          })}
        </p>
      )}

      {isLoading && (
        <p className="text-sm text-muted-foreground" aria-busy="true">
          {t('app.loading', { defaultValue: 'Ładowanie…' })}
        </p>
      )}

      {!isLoading && queryError !== null && queryError !== undefined && (
        <p role="alert" className="text-sm text-destructive">
          {t('imports.mapping.auto_map_failed', {
            defaultValue:
              'Auto-mapowanie nie powiodło się — sprawdź konsolę DevTools i spróbuj ponownie.',
          })}
        </p>
      )}

      {state.suggestions.length > 0 && (
        <div className="overflow-x-auto rounded-md border">
          <table className="w-full text-sm">
            <thead className="border-b bg-muted/40 text-xs uppercase">
              <tr>
                <th className="px-3 py-2 text-left">
                  {t('imports.mapping.columns.source', { defaultValue: 'Kolumna źródła' })}
                </th>
                <th className="px-3 py-2 text-left">
                  {t('imports.mapping.columns.sample', { defaultValue: 'Sample value' })}
                </th>
                <th className="px-3 py-2 text-left">
                  {t('imports.mapping.columns.mapping', { defaultValue: 'Mapping' })}
                </th>
              </tr>
            </thead>
            <tbody>
              {state.suggestions.map((suggestion) => {
                const currentValue =
                  state.mapping[suggestion.column_header] ??
                  suggestion.suggested_attribute_code ??
                  null;
                return (
                  <tr key={suggestion.column_index} className="border-b last:border-0">
                    <td className="px-3 py-2 font-mono text-xs">{suggestion.column_header}</td>
                    <td className="px-3 py-2 text-xs text-muted-foreground">
                      {(suggestion.sample_values[0] ?? '—').toString()}
                    </td>
                    <td className="px-3 py-2">
                      <div className="flex items-center gap-2">
                        <Combobox
                          options={optionsWithSkip}
                          value={currentValue === 'skip' ? SKIP_VALUE : currentValue}
                          onChange={(picked) =>
                            patchMapping(
                              suggestion.column_header,
                              picked === SKIP_VALUE || picked === null ? 'skip' : picked,
                            )
                          }
                          placeholder={t('imports.mapping.skip', {
                            defaultValue: 'Pomiń',
                          })}
                          allowClear={false}
                          className="min-w-[200px]"
                        />
                        <span
                          className={cn(
                            'rounded-full px-2 py-0.5 text-xs',
                            suggestion.confidence === 'auto'
                              ? 'bg-green-100 text-green-900'
                              : suggestion.confidence === 'fuzzy'
                                ? 'bg-amber-100 text-amber-900'
                                : suggestion.confidence === 'manual'
                                  ? 'bg-muted text-muted-foreground'
                                  : 'bg-muted text-muted-foreground',
                          )}
                        >
                          {t(`imports.mapping.confidence.${suggestion.confidence}`, {
                            defaultValue: suggestion.confidence,
                          })}
                        </span>
                      </div>
                    </td>
                  </tr>
                );
              })}
            </tbody>
          </table>
        </div>
      )}

      <div className="flex flex-wrap items-center justify-between gap-3">
        <div className="flex items-center gap-2">
          <Button variant="outline" size="sm" onClick={() => setComputedOpen(true)}>
            {t('imports.mapping.computed_cta', { defaultValue: 'Nowa kolumna obliczona' })}
          </Button>
        </div>
        <div className="ml-auto flex gap-2">
          <Button variant="ghost" onClick={() => back()}>
            ← {t('imports.wizard.back', { defaultValue: 'Wstecz' })}
          </Button>
          <Button onClick={() => next()} disabled={state.suggestions.length === 0}>
            {t('imports.wizard.next', { defaultValue: 'Dalej →' })}
          </Button>
        </div>
      </div>

      <ComputedColumnModal
        open={computedOpen}
        onClose={() => setComputedOpen(false)}
        headers={parsed?.headers ?? []}
        sampleRow={parsed?.sampleRows[0] ?? []}
      />
    </div>
  );
}

interface ParsedSnapshotResult {
  snapshot: ParsedFileSnapshot | null;
  isLoading: boolean;
  error: Error | null;
}

function useParsedSnapshot(
  file: File | null,
  encoding: string,
  delimiter: string,
): ParsedSnapshotResult {
  const [snapshot, setSnapshot] = React.useState<ParsedFileSnapshot | null>(null);
  const [isLoading, setIsLoading] = React.useState(false);
  const [error, setError] = React.useState<Error | null>(null);

  React.useEffect(() => {
    if (file === null) {
      setSnapshot(null);
      setError(null);
      setIsLoading(false);
      return;
    }

    let cancelled = false;
    setIsLoading(true);
    setError(null);

    const form = new FormData();
    form.append('file', file);
    form.append('encoding', encoding);
    form.append('delimiter', delimiter);

    jsonFetch<ParsePreviewResponse>('/api/import-sessions/parse-preview', {
      method: 'POST',
      body: form,
    })
      .then((data) => {
        if (cancelled) return;
        setSnapshot({
          headers: data.headers,
          sampleRows: data.sample_rows,
          totalRows: data.total_rows,
        });
      })
      .catch((err: unknown) => {
        if (cancelled) return;
        setSnapshot(null);
        setError(err instanceof Error ? err : new Error('parse-preview failed'));
      })
      .finally(() => {
        if (!cancelled) setIsLoading(false);
      });

    return () => {
      cancelled = true;
    };
  }, [file, encoding, delimiter]);

  return { snapshot, isLoading, error };
}

function useTargetAttributes(objectTypeId: string | null): AttributeOption[] {
  // Pulls the attributes attached to the target ObjectType so the
  // combobox surfaces only the codes actually persistable. The list
  // keys off the ObjectType id and is bounded by the pageSize ceiling.
  const enabled = objectTypeId !== null;
  const { result } = useList<AttributeOption>({
    resource: 'attributes',
    pagination: { pageSize: 200 },
    queryOptions: { enabled },
  });
  return result.data ?? [];
}

function pickLabel(label?: Record<string, string>): string | undefined {
  if (label === undefined) {
    return undefined;
  }
  return label.pl ?? label.en ?? Object.values(label)[0];
}
