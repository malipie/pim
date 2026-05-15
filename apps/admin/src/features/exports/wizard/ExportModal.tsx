import { useApiUrl, useCustomMutation } from '@refinedev/core';
import { useState } from 'react';
import { useTranslation } from 'react-i18next';

import {
  Dialog,
  DialogContent,
  DialogFooter,
  DialogHeader,
  DialogTitle,
} from '@/components/ui/dialog';

import { BUILT_IN_COLUMN_GROUPS, ColumnPicker } from '../components/ColumnPicker';

type ExportFormat = 'xlsx' | 'csv';
type ExportEncoding = 'utf8_bom' | 'windows_1250';
type TargetScope = 'selected' | 'filter' | 'all';

interface ExportModalProps {
  open: boolean;
  onOpenChange: (open: boolean) => void;
  /** SKU IDs preselected from the catalog list. Triggers `target_scope=selected`. */
  selectedObjectIds?: readonly string[];
}

interface ExportSubmitResult {
  id?: string;
  status?: string;
  target_count?: number;
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

  const { mutate: submit } = useCustomMutation<ExportSubmitResult>();

  const onSubmit = () => {
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

    submit(
      {
        url: `${apiUrl}/products/export`,
        method: 'post',
        values: payload,
      },
      {
        onSuccess: (response) => {
          // Async path (HTTP 202) → toast + close. Sync path returns
          // binary; Refine custom mutation cannot stream the
          // download, so the FE encodes the same payload as a form
          // POST through window.open for sync-scale exports.
          if (response.data?.status === 'pending') {
            // Async dispatched — Recent grid will pick it up via
            // 5s polling (EXP-13).
            onOpenChange(false);
            window.location.href = '/integrations/exports/sessions';
            return;
          }
          // For sync exports, fall back to a form-POST trick that
          // lets the browser handle the binary stream directly.
          submitFormPost(apiUrl, payload);
          onOpenChange(false);
        },
        onError: (mutationError) => {
          setError(
            mutationError instanceof Error
              ? mutationError.message
              : t('exports.modal.error_generic', { defaultValue: 'Eksport nie powiódł się.' }),
          );
        },
        onSettled: () => setSubmitting(false),
      },
    );
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
            </h3>
            <ColumnPicker
              available={BUILT_IN_COLUMN_GROUPS}
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
 * Sync exports come back as a binary `application/vnd.openxmlformats-...`
 * stream. Refine's `useCustomMutation` does not surface raw blobs in a
 * way the browser will download — we re-POST through an invisible
 * `<form>` so the browser handles the Content-Disposition flow natively.
 */
function submitFormPost(apiUrl: string, payload: Record<string, unknown>): void {
  const form = document.createElement('form');
  form.method = 'POST';
  form.action = `${apiUrl}/products/export`;
  form.target = '_self';
  const input = document.createElement('input');
  input.type = 'hidden';
  input.name = '__payload';
  input.value = JSON.stringify(payload);
  form.appendChild(input);
  document.body.appendChild(form);
  form.submit();
  document.body.removeChild(form);
}

export default ExportModal;
