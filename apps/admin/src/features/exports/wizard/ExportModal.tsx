import { useApiUrl } from '@refinedev/core';
import { useState } from 'react';
import { useTranslation } from 'react-i18next';

import {
  Dialog,
  DialogContent,
  DialogFooter,
  DialogHeader,
  DialogTitle,
} from '@/components/ui/dialog';
import { getAccessToken } from '@/lib/http';

import { ColumnPicker } from '../components/ColumnPicker';
import { useExportColumnCatalog } from '../components/use-export-column-catalog';

type ExportFormat = 'xlsx' | 'csv';
type ExportEncoding = 'utf8_bom' | 'windows_1250';
type TargetScope = 'selected' | 'filter' | 'all';

interface ExportModalProps {
  open: boolean;
  onOpenChange: (open: boolean) => void;
  /** SKU IDs preselected from the catalog list. Triggers `target_scope=selected`. */
  selectedObjectIds?: readonly string[];
}

/**
 * EXP-11 (#590) — Modal kontekstowy z listy produktów.
 *
 * Four sections (PRD §13.1):
 *   1. Kolumny — embedded ColumnPicker (EXP-10).
 *   2. Format + encoding (CSV only) — radio + radio.
 *   3. Co eksportujesz — Zaznaczone (N) / Cały filter / Wszystkie produkty.
 *   4. Locale + channel toggles (placeholder w MVP — pełne UX z
 *      EXP-10 picker locale/channel sub-selectors landuje gdy
 *      tenant locales/channels API jest fetched).
 *
 * Submit POSTs to `/api/products/export`:
 *   - `target_count < 100` → BinaryFileResponse z bytes → browser
 *     download (file Content-Disposition).
 *   - `target_count >= 100` → 202 Accepted, toast + close modal.
 *
 * Świadome odejścia:
 *   - BulkActionsToolbar integration — operator otwiera modal przez
 *     prop control (parent component decides when). Toolbar wiring
 *     follow-up: dodać "Eksport" button do `apps/admin/src/features/catalog/products/list/*`
 *     który wywołuje `setOpen(true)`. Modal ships standalone gotowy
 *     na to plug-in.
 *   - Locale + channel toggles — placeholder section. Pełne UX
 *     wymaga `useCustom(/api/tenant/locales)` + per-attribute
 *     scope dropdown. Faza 1 candidate.
 *   - "Save as profile" checkbox — wymaga EXP-07 profile create
 *     dispatch przy submit (już dostępny w API). Sekcja zarezerwowana
 *     jako placeholder; pełna integracja w follow-up gdy modal
 *     edit-mode (EXP-14 Edit action) wymaga symmetric save.
 */
export function ExportModal({
  open,
  onOpenChange,
  selectedObjectIds = [],
}: ExportModalProps): React.ReactElement {
  const { t } = useTranslation();
  const apiUrl = useApiUrl();

  const [columns, setColumns] = useState<string[]>(['sku', 'parent_sku', 'status']);
  const [format, setFormat] = useState<ExportFormat>('xlsx');
  const [encoding, setEncoding] = useState<ExportEncoding>('utf8_bom');
  const initialScope: TargetScope = selectedObjectIds.length > 0 ? 'selected' : 'all';
  const [targetScope, setTargetScope] = useState<TargetScope>(initialScope);
  const [submitting, setSubmitting] = useState(false);
  const [error, setError] = useState<string | null>(null);

  const columnCatalog = useExportColumnCatalog();

  const onSubmit = async () => {
    if (columns.length === 0) {
      setError(
        t('exports.modal.error_no_columns', { defaultValue: 'Wybierz co najmniej jedną kolumnę.' }),
      );
      return;
    }
    setSubmitting(true);
    setError(null);

    const payload: Record<string, unknown> = {
      format,
      target_scope: targetScope,
      selected_columns: columns,
      include_variants: true,
    };
    if (format === 'csv') {
      payload['encoding'] = encoding;
    }
    if (targetScope === 'selected') {
      payload['selected_object_ids'] = selectedObjectIds;
    }

    try {
      // Raw fetch — sync response is XLSX/CSV binary (NOT JSON), so
      // Refine's useCustomMutation / jsonFetch reject it as
      // "HTTP 200" via the 2026-05-13 white-screen guard. We branch
      // on status: 202 → JSON redirect; 200 → blob download.
      const token = getAccessToken();
      const headers: Record<string, string> = {
        'content-type': 'application/json',
        accept:
          'application/json, application/vnd.openxmlformats-officedocument.spreadsheetml.sheet, text/csv',
      };
      if (token !== null) {
        headers['authorization'] = `Bearer ${token}`;
      }
      const response = await fetch(`${apiUrl}/products/export`, {
        method: 'POST',
        headers,
        credentials: 'same-origin',
        body: JSON.stringify(payload),
      });

      if (response.status === 202) {
        // Async path — Recent grid will pick it up via polling (EXP-13).
        onOpenChange(false);
        window.location.href = '/integrations/exports/sessions';
        return;
      }

      if (!response.ok) {
        const text = await response.text();
        let detail = `HTTP ${response.status}`;
        try {
          const parsed = JSON.parse(text) as { detail?: string; message?: string };
          detail = parsed.detail ?? parsed.message ?? detail;
        } catch {
          // Non-JSON error body — keep the status code only.
        }
        setError(detail);
        return;
      }

      // Sync path — read body as a blob and trigger a download via a
      // temp anchor. browsers handle Content-Disposition + content-type
      // when the bytes arrive as a Blob URL.
      const blob = await response.blob();
      const filename =
        parseFilename(response.headers.get('content-disposition')) ??
        `pim-export-${new Date().toISOString().replace(/[:.]/g, '-')}.${format}`;
      const url = URL.createObjectURL(blob);
      const anchor = document.createElement('a');
      anchor.href = url;
      anchor.download = filename;
      document.body.appendChild(anchor);
      anchor.click();
      document.body.removeChild(anchor);
      // Revoke after a tick so Safari has time to start the download.
      setTimeout(() => URL.revokeObjectURL(url), 1000);
      onOpenChange(false);
    } catch (err) {
      setError(
        err instanceof Error
          ? err.message
          : t('exports.modal.error_generic', { defaultValue: 'Eksport nie powiódł się.' }),
      );
    } finally {
      setSubmitting(false);
    }
  };

  return (
    <Dialog open={open} onOpenChange={onOpenChange}>
      <DialogContent className="max-w-4xl">
        <DialogHeader>
          <DialogTitle>
            {t('exports.modal.title', { defaultValue: 'Eksportuj produkty' })}
          </DialogTitle>
        </DialogHeader>
        <div className="space-y-6">
          {/* Section 1 — Kolumny */}
          <section>
            <h3 className="mb-2 text-sm font-medium">
              {t('exports.modal.section_columns', { defaultValue: 'Kolumny' })}
              {columnCatalog.isLoading ? (
                <span className="ml-2 text-xs font-normal text-muted-foreground">
                  {t('exports.modal.columns_loading', { defaultValue: '(ładuję atrybuty…)' })}
                </span>
              ) : null}
              {columnCatalog.error !== null ? (
                <span className="ml-2 text-xs font-normal text-rose-600">
                  {t('exports.modal.columns_error', {
                    defaultValue:
                      '(nie udało się załadować atrybutów — pokazuję tylko wbudowane kolumny)',
                  })}
                </span>
              ) : null}
            </h3>
            <ColumnPicker
              available={columnCatalog.groups}
              selected={columns}
              onChange={setColumns}
            />
          </section>

          {/* Section 2 — Format + encoding */}
          <section className="space-y-2">
            <h3 className="text-sm font-medium">
              {t('exports.modal.section_format', { defaultValue: 'Format' })}
            </h3>
            <div className="flex gap-3 text-sm">
              <label className="flex items-center gap-2">
                <input
                  type="radio"
                  name="format"
                  className="size-4"
                  checked={format === 'xlsx'}
                  onChange={() => setFormat('xlsx')}
                />
                XLSX
              </label>
              <label className="flex items-center gap-2">
                <input
                  type="radio"
                  name="format"
                  className="size-4"
                  checked={format === 'csv'}
                  onChange={() => setFormat('csv')}
                />
                CSV
              </label>
            </div>
            {format === 'csv' && (
              <div className="flex gap-3 text-sm pl-6 mt-2">
                <label className="flex items-center gap-2">
                  <input
                    type="radio"
                    name="encoding"
                    className="size-4"
                    checked={encoding === 'utf8_bom'}
                    onChange={() => setEncoding('utf8_bom')}
                  />
                  UTF-8 BOM
                </label>
                <label className="flex items-center gap-2">
                  <input
                    type="radio"
                    name="encoding"
                    className="size-4"
                    checked={encoding === 'windows_1250'}
                    onChange={() => setEncoding('windows_1250')}
                  />
                  Windows-1250
                </label>
              </div>
            )}
          </section>

          {/* Section 3 — Target scope */}
          <section className="space-y-2">
            <h3 className="text-sm font-medium">
              {t('exports.modal.section_scope', { defaultValue: 'Co eksportujesz' })}
            </h3>
            <div className="flex flex-col gap-1 text-sm">
              <label className="flex items-center gap-2">
                <input
                  type="radio"
                  name="scope"
                  className="size-4"
                  checked={targetScope === 'selected'}
                  onChange={() => setTargetScope('selected')}
                  disabled={selectedObjectIds.length === 0}
                />
                {t('exports.modal.scope_selected', {
                  count: selectedObjectIds.length,
                  defaultValue: `Zaznaczone (${selectedObjectIds.length})`,
                })}
              </label>
              <label className="flex items-center gap-2">
                <input
                  type="radio"
                  name="scope"
                  className="size-4"
                  checked={targetScope === 'all'}
                  onChange={() => setTargetScope('all')}
                />
                {t('exports.modal.scope_all', { defaultValue: 'Wszystkie produkty' })}
              </label>
            </div>
          </section>
        </div>

        {error !== null && (
          <p className="text-sm text-rose-700" role="alert">
            {error}
          </p>
        )}

        <DialogFooter>
          <button
            type="button"
            onClick={() => onOpenChange(false)}
            className="rounded border border-input bg-background px-3 py-1.5 text-sm hover:bg-muted"
          >
            {t('exports.modal.cancel', { defaultValue: 'Anuluj' })}
          </button>
          <button
            type="button"
            onClick={onSubmit}
            disabled={submitting || columns.length === 0}
            className="rounded bg-primary px-3 py-1.5 text-sm font-medium text-primary-foreground hover:bg-primary/90 disabled:opacity-50"
          >
            {submitting
              ? t('exports.modal.submitting', { defaultValue: 'Eksportowanie…' })
              : t('exports.modal.submit', { defaultValue: 'Eksportuj' })}
          </button>
        </DialogFooter>
      </DialogContent>
    </Dialog>
  );
}

/**
 * Pull the filename out of a `Content-Disposition` header so the
 * triggered anchor download lands on disk with the same name the
 * backend chose (sync controller computes `pim-export-<timestamp>.<ext>`).
 * Returns null if the header is missing or unparseable — caller falls
 * back to a client-generated name.
 */
function parseFilename(header: string | null): string | null {
  if (header === null) return null;
  const match = /filename\*?=(?:UTF-8'')?"?([^";]+)"?/i.exec(header);
  return match?.[1] !== undefined ? decodeURIComponent(match[1]) : null;
}

export default ExportModal;
