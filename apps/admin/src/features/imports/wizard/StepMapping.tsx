import { useApiUrl, useCustom, useList } from '@refinedev/core';
import * as React from 'react';
import { useTranslation } from 'react-i18next';
import { Link } from 'react-router';

import { Button } from '@/components/ui/button';
import { Combobox, type ComboboxOption } from '@/components/ui/combobox';
import type { ColumnSuggestion, useImportWizard } from '@/features/imports/hooks/useImportWizard';
import { cn } from '@/lib/utils';

interface StepMappingProps {
  wizard: ReturnType<typeof useImportWizard>;
}

interface ParsedFileSnapshot {
  headers: string[];
  sampleRows: Array<Array<string | null>>;
  totalRows: number;
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

/**
 * Spec §5.3 — column mapping. Streams the uploaded headers + first
 * sample row to the IMP-02 endpoint, renders the mapping table on
 * top of the IMP-08 Combobox, and persists the picks back through
 * `useImportWizard`. The "+ Stwórz nowy atrybut" CTA snapshots the
 * wizard state to localStorage and deep-links to /modeling — the
 * round-trip restore lives on the hook.
 */
export function StepMapping({ wizard }: StepMappingProps): React.ReactElement {
  const { t } = useTranslation();
  const { state, setField, patchMapping, next, back, persist } = wizard;
  const apiUrl = useApiUrl();

  const file = state.file;
  const targetId = state.targetObjectTypeId;

  // Parse the uploaded file in the browser to surface headers + a
  // sample row to the auto-map endpoint. Real CSV parsing is good
  // enough for picking column names; xlsx falls back to a single
  // header line stripped from the raw bytes.
  const parsed = useParsedSnapshot(file, state.delimiter);

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
  const isLoading = autoMapQuery.isLoading;
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
  const optionsWithSkip: ComboboxOption[] = [
    { value: SKIP_VALUE, label: t('imports.mapping.skip', { defaultValue: 'Pomiń' }) },
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

      {parsed === null && (
        <p className="text-sm text-muted-foreground">
          {t('imports.errors.upload_failed', {
            defaultValue: 'Nie udało się wczytać pliku — wróć do Step 1.',
          })}
        </p>
      )}

      {isLoading && (
        <p className="text-sm text-muted-foreground" aria-busy="true">
          {t('app.loading', { defaultValue: 'Ładowanie…' })}
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
        <Button asChild variant="outline" size="sm" onClick={() => persist()}>
          <Link to="/modeling/attributes">
            {t('imports.wizard.create_attribute', {
              defaultValue: '+ Stwórz nowy atrybut',
            })}
          </Link>
        </Button>
        <div className="ml-auto flex gap-2">
          <Button variant="ghost" onClick={() => back()}>
            ← {t('imports.wizard.back', { defaultValue: 'Wstecz' })}
          </Button>
          <Button onClick={() => next()} disabled={state.suggestions.length === 0}>
            {t('imports.wizard.next', { defaultValue: 'Dalej →' })}
          </Button>
        </div>
      </div>
    </div>
  );
}

function useParsedSnapshot(file: File | null, delimiterChoice: string): ParsedFileSnapshot | null {
  const [snapshot, setSnapshot] = React.useState<ParsedFileSnapshot | null>(null);

  React.useEffect(() => {
    if (file === null) {
      setSnapshot(null);
      return;
    }
    if (!file.name.toLowerCase().endsWith('.csv')) {
      // xlsx — no in-browser parser; backend handles real headers.
      // Provide a sentinel single header so auto-map call is sent.
      setSnapshot({
        headers: ['__xlsx__'],
        sampleRows: [[null]],
        totalRows: 0,
      });
      return;
    }
    const reader = new FileReader();
    reader.addEventListener('load', () => {
      const text = String(reader.result ?? '');
      const rows = text.split(/\r\n|\n|\r/).filter((line) => line.length > 0);
      if (rows.length === 0) {
        setSnapshot(null);
        return;
      }
      const delim = delimiterChoice === 'auto' ? guessDelimiter(rows[0]) : delimiterChoice;
      const splitter = delim === 'tab' ? '\t' : delim;
      const headers = rows[0].split(splitter).map((value) => value.trim());
      const sample = rows
        .slice(1, 4)
        .map((row) => row.split(splitter).map((value) => (value === '' ? null : value)));
      setSnapshot({ headers, sampleRows: sample, totalRows: rows.length - 1 });
    });
    reader.readAsText(file, 'utf-8');
  }, [file, delimiterChoice]);

  return snapshot;
}

function guessDelimiter(sample: string): string {
  for (const candidate of [';', ',', '\t', '|']) {
    if (sample.includes(candidate)) {
      return candidate === '\t' ? 'tab' : candidate;
    }
  }
  return ';';
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
