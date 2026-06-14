import * as React from 'react';
import { useTranslation } from 'react-i18next';

import { Button } from '@/components/ui/button';
import { MockBadge } from '@/components/ui/mock-badge';
import type { ParsedFilePreview, useImportWizard } from '@/features/imports/hooks/useImportWizard';
import { jsonFetch } from '@/lib/http';
import { cn } from '@/lib/utils';

interface StepDetectProps {
  wizard: ReturnType<typeof useImportWizard>;
}

interface ParsePreviewResponse {
  staged_file_id: string;
  headers: string[];
  sample_rows: Array<Array<string | null>>;
  total_rows: number;
  encoding: string;
  delimiter: string | null;
  sheet_name: string | null;
  had_multiple_sheets: boolean;
}

/**
 * NUI-10 (#1429) — Step 2 „Wykrywanie" (design `Import-nowy.html`
 * StepDetect). WIRE: detection table + 5-row preview straight from
 * `POST /api/import-sessions/parse-preview` (format / encoding /
 * delimiter / header / sheet). MOCK: sheet selection + empty-cell
 * strategy (no backend params). SKIP: decimal / date / line-endings /
 * quote detection — the backend does not detect those, so we do not
 * fabricate results (backlog: Retrofit_v2/importy-do-oprogramowania.md).
 */
export function StepDetect({ wizard }: StepDetectProps): React.ReactElement {
  const { t } = useTranslation();
  const { state, setField, next, back } = wizard;
  const [loading, setLoading] = React.useState(false);
  const [error, setError] = React.useState<string | null>(null);

  const file = state.file;
  const parsed = state.parsed;

  React.useEffect(() => {
    if (file === null || parsed !== null) return;
    let cancelled = false;
    setLoading(true);
    setError(null);

    const form = new FormData();
    form.append('file', file);
    form.append('encoding', state.encoding);
    form.append('delimiter', state.delimiter);

    jsonFetch<ParsePreviewResponse>('/api/import-sessions/parse-preview', {
      method: 'POST',
      body: form,
    })
      .then((data) => {
        if (cancelled) return;
        const snapshot: ParsedFilePreview = {
          headers: data.headers,
          sampleRows: data.sample_rows,
          totalRows: data.total_rows,
          encoding: data.encoding,
          delimiter: data.delimiter,
          sheetName: data.sheet_name,
          hadMultipleSheets: data.had_multiple_sheets,
        };
        setField('parsed', snapshot);
        // IMP2-2.2 — remember the staged id so dry-run + start reuse the
        // already-uploaded bytes instead of re-sending the file.
        setField('stagedFileId', data.staged_file_id);
      })
      .catch((err: unknown) => {
        if (cancelled) return;
        setError(err instanceof Error ? err.message : 'parse-preview failed');
      })
      .finally(() => {
        if (!cancelled) setLoading(false);
      });

    return () => {
      cancelled = true;
    };
  }, [file, parsed, state.encoding, state.delimiter, setField]);

  const fileExt = file?.name.split('.').pop()?.toUpperCase() ?? '—';
  const detected: Array<{ k: string; v: string }> = parsed
    ? [
        { k: t('imports.detect.format', { defaultValue: 'Format' }), v: fileExt },
        {
          k: t('imports.detect.encoding', { defaultValue: 'Kodowanie' }),
          v: parsed.encoding,
        },
        {
          k: t('imports.detect.delimiter', { defaultValue: 'Separator' }),
          v:
            parsed.delimiter ??
            t('imports.detect.delimiter_xlsx', { defaultValue: '— (komórki XLSX)' }),
        },
        {
          k: t('imports.detect.header', { defaultValue: 'Nagłówek' }),
          v: t('imports.detect.header_value', {
            defaultValue: '{{count}} kolumn wykrytych',
            count: parsed.headers.length,
          }),
        },
        ...(parsed.sheetName !== null
          ? [{ k: t('imports.detect.sheet', { defaultValue: 'Arkusz' }), v: parsed.sheetName }]
          : []),
      ]
    : [];

  return (
    <div className="space-y-4">
      <div className="grid grid-cols-1 gap-4 lg:grid-cols-[1.2fr_1fr]">
        <div className="rounded-2xl border border-zinc-100 bg-white p-5 shadow-sm">
          <div className="text-[13.5px] font-semibold">
            {t('imports.detect.title', { defaultValue: 'Wyniki auto-detekcji' })}
          </div>
          <div className="mt-0.5 text-[12px] text-zinc-500">
            {file ? (
              <>
                {t('imports.detect.file_label', { defaultValue: 'Plik' })}{' '}
                <span className="font-mono">{file.name}</span>
              </>
            ) : (
              t('imports.detect.no_file', { defaultValue: 'Wróć do kroku Źródło i wgraj plik.' })
            )}
          </div>

          {loading && (
            <p className="mt-4 text-sm text-zinc-500" aria-busy="true">
              {t('app.loading', { defaultValue: 'Ładowanie…' })}
            </p>
          )}
          {error !== null && (
            <p role="alert" className="mt-4 text-sm text-rose-600">
              {t('imports.detect.parse_error', { defaultValue: 'Nie udało się sparsować pliku' })}:{' '}
              {error}
            </p>
          )}

          {parsed !== null && (
            <div className="mt-4 divide-y divide-zinc-50 overflow-hidden rounded-xl border border-zinc-100">
              {detected.map((d) => (
                <div
                  key={d.k}
                  className="grid grid-cols-[160px_1fr] items-center gap-3 px-4 py-2.5 text-[12.5px]"
                >
                  <div className="text-zinc-500">{d.k}</div>
                  <div className="truncate font-mono text-zinc-800">{d.v}</div>
                </div>
              ))}
            </div>
          )}

          <div className="mt-5 grid grid-cols-1 gap-4 sm:grid-cols-2">
            <div className="relative">
              <div className="mb-2 flex items-center gap-1.5 text-[12px] font-semibold">
                {t('imports.detect.multisheet', { defaultValue: 'Multi-sheet (Excel)' })}
                <MockBadge
                  tooltip={t('imports.detect.multisheet_mock', {
                    defaultValue:
                      'MOCK — backend parsuje pierwszy arkusz; wybór arkusza wymaga parametru (backlog NUI-10)',
                  })}
                />
              </div>
              {parsed?.hadMultipleSheets ? (
                <p className="rounded-lg border border-amber-200 bg-amber-50 px-2.5 py-2 text-[11.5px] text-amber-900">
                  {t('imports.detect.multisheet_warning', {
                    defaultValue: 'Plik ma wiele arkuszy — import przetworzy pierwszy ({{sheet}}).',
                    sheet: parsed.sheetName ?? '#1',
                  })}
                </p>
              ) : (
                <p className="text-[11.5px] text-zinc-500">
                  {t('imports.detect.multisheet_single', { defaultValue: 'Jeden arkusz / CSV.' })}
                </p>
              )}
            </div>

            <div className="relative">
              <div className="mb-2 flex items-center gap-1.5 text-[12px] font-semibold">
                {t('imports.detect.empty_cells', { defaultValue: 'Pusta komórka' })}
                <MockBadge
                  tooltip={t('imports.detect.empty_cells_mock', {
                    defaultValue:
                      'MOCK — strategia pustych komórek wymaga parametru backendu (backlog NUI-10)',
                  })}
                />
              </div>
              <div className="space-y-1">
                {[
                  t('imports.detect.empty_null', { defaultValue: 'NULL (brak wartości)' }),
                  t('imports.detect.empty_string', { defaultValue: 'Pusty string ""' }),
                  t('imports.detect.empty_default', { defaultValue: 'Wartość domyślna z modelu' }),
                ].map((label, i) => (
                  <label
                    key={label}
                    className={cn(
                      'flex cursor-not-allowed items-center gap-2 rounded-lg px-2.5 py-1.5 text-[12px] opacity-60',
                      i === 0 && 'bg-zinc-100',
                    )}
                  >
                    <input
                      type="radio"
                      disabled
                      checked={i === 0}
                      readOnly
                      className="h-3.5 w-3.5"
                    />
                    <span>{label}</span>
                  </label>
                ))}
              </div>
            </div>
          </div>
        </div>

        <div className="rounded-2xl border border-zinc-100 bg-white p-5 shadow-sm">
          <div className="text-[13.5px] font-semibold">
            {t('imports.detect.preview_title', { defaultValue: 'Podgląd pierwszych 5 wierszy' })}
          </div>
          {parsed !== null && (
            <>
              <div className="mt-3 overflow-auto rounded-xl border border-zinc-100">
                <table className="w-full text-[11.5px]">
                  <thead className="bg-zinc-50/60">
                    <tr>
                      {parsed.headers.map((h) => (
                        <th
                          key={h}
                          className="whitespace-nowrap px-2.5 py-2 text-left font-mono font-medium text-zinc-600"
                        >
                          {h}
                        </th>
                      ))}
                    </tr>
                  </thead>
                  <tbody className="font-mono">
                    {parsed.sampleRows.slice(0, 5).map((row, i) => (
                      // biome-ignore lint/suspicious/noArrayIndexKey: sample rows have no stable id
                      <tr key={i} className="even:bg-zinc-50/40">
                        {row.map((c, j) => (
                          // biome-ignore lint/suspicious/noArrayIndexKey: cells have no stable id
                          <td key={j} className="whitespace-nowrap px-2.5 py-1.5 text-zinc-700">
                            {c ?? ''}
                          </td>
                        ))}
                      </tr>
                    ))}
                  </tbody>
                </table>
              </div>
              <div className="mt-3 flex items-center gap-2 rounded-xl border border-emerald-100 bg-emerald-50/60 px-3 py-2 text-[12px] text-emerald-900">
                <span className="text-emerald-700">✓</span>
                {t('imports.detect.ready', {
                  defaultValue:
                    'Wstępna walidacja przeszła — {{count}} wierszy gotowych do mapowania.',
                  count: parsed.totalRows,
                })}
              </div>
            </>
          )}
        </div>
      </div>

      <div className="flex justify-between">
        <Button variant="ghost" onClick={() => back()}>
          ← {t('imports.wizard.back', { defaultValue: 'Wstecz' })}
        </Button>
        <Button onClick={() => next()} disabled={parsed === null}>
          {t('imports.wizard.next', { defaultValue: 'Dalej →' })}
        </Button>
      </div>
    </div>
  );
}
