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
  /**
   * Pre-parsed FilterDSL snapshot to pass through `target_scope=filter`.
   * When provided, the "Cały filter" radio becomes enabled and the
   * snapshot is forwarded to the export endpoint. Set by `ExportNewPage`
   * after parsing the user's JSON input.
   */
  filterSnapshot?: Record<string, unknown> | null;
}

/**
 * EXP-11 (#590) — Modal kontekstowy z listy produktów.
 *
 * Sections (PRD §13.1):
 *   1. Kolumny — embedded ColumnPicker (EXP-10).
 *   2. Format + encoding (CSV only) — radio + radio.
 *   3. Co eksportujesz — Zaznaczone (N) / Cały filter / Wszystkie produkty.
 *   4. Zapisz jako profil (EXP-18 #630) — opcjonalny checkbox + name.
 *      Profil zapisywany PRZED eksportem; 409 (duplikat nazwy)
 *      blokuje eksport, więc user może poprawić nazwę bez orphan
 *      pobrania.
 *
 * Submit POSTs to `/api/products/export`:
 *   - `target_count < 100` → BinaryFileResponse z bytes → browser
 *     download (file Content-Disposition).
 *   - `target_count >= 100` → 202 Accepted, toast + close modal.
 *
 * Świadome odejścia (Faza 1):
 *   - Locale + channel sub-toggles — wymaga `/api/tenant/locales`
 *     + per-attribute scope dropdown. Single-locale tenant MVP
 *     nie używa.
 *
 * Filter scope:
 *   - Modal-from-list (no `filterSnapshot` prop): "Cały filter" radio
 *     is disabled with a hint to use the full-page form.
 *   - Full-page form (`ExportNewPage` passes `filterSnapshot`): radio
 *     is enabled, and the snapshot rides through the export payload.
 *   - Backend SQL resolution lives in `SyncExportRunner::resolveFilter`
 *     (EXP-20 #632) via `FilterDslResolver::toCountSql`.
 */
export function ExportModal({
  open,
  onOpenChange,
  selectedObjectIds = [],
  filterSnapshot = null,
}: ExportModalProps): React.ReactElement {
  const { t } = useTranslation();
  const apiUrl = useApiUrl();

  const [columns, setColumns] = useState<string[]>(['sku', 'parent_sku', 'status']);
  const [format, setFormat] = useState<ExportFormat>('xlsx');
  const [encoding, setEncoding] = useState<ExportEncoding>('utf8_bom');
  const initialScope: TargetScope =
    selectedObjectIds.length > 0 ? 'selected' : filterSnapshot !== null ? 'filter' : 'all';
  const [targetScope, setTargetScope] = useState<TargetScope>(initialScope);
  const [submitting, setSubmitting] = useState(false);
  const [error, setError] = useState<string | null>(null);
  const [saveAsProfile, setSaveAsProfile] = useState(false);
  const [profileName, setProfileName] = useState('');
  const [profileError, setProfileError] = useState<string | null>(null);

  const columnCatalog = useExportColumnCatalog();

  const onSubmit = async () => {
    if (columns.length === 0) {
      setError(
        t('exports.modal.error_no_columns', { defaultValue: 'Wybierz co najmniej jedną kolumnę.' }),
      );
      return;
    }
    const trimmedProfileName = profileName.trim();
    if (saveAsProfile) {
      if (trimmedProfileName.length < 1 || trimmedProfileName.length > 255) {
        setProfileError(
          t('exports.modal.profile_error_name_length', {
            defaultValue: 'Nazwa profilu musi mieć 1-255 znaków.',
          }),
        );
        return;
      }
    }
    setSubmitting(true);
    setError(null);
    setProfileError(null);

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
    if (targetScope === 'filter' && filterSnapshot !== null) {
      payload['filter_snapshot'] = filterSnapshot;
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

      // Save profile FIRST when requested. 409 (duplicate name) blocks
      // the export so the user can correct the name without an orphan
      // download. selected_object_ids are stripped — running the profile
      // later shouldn't carry ad-hoc selections from this submit.
      if (saveAsProfile) {
        const profileConfig: Record<string, unknown> = {
          selected_columns: columns,
          format,
          target_scope: targetScope,
          include_variants: true,
        };
        if (format === 'csv') {
          profileConfig['encoding'] = encoding;
        }
        if (targetScope === 'filter' && filterSnapshot !== null) {
          profileConfig['filter_snapshot'] = filterSnapshot;
        }
        const profileResponse = await fetch(`${apiUrl}/exports/profiles`, {
          method: 'POST',
          headers: { ...headers, accept: 'application/json' },
          credentials: 'same-origin',
          body: JSON.stringify({ name: trimmedProfileName, config: profileConfig }),
        });
        if (!profileResponse.ok) {
          if (profileResponse.status === 409) {
            setProfileError(
              t('exports.modal.profile_error_duplicate', {
                defaultValue: 'Profil o tej nazwie już istnieje.',
              }),
            );
          } else {
            const text = await profileResponse.text();
            let detail = `HTTP ${profileResponse.status}`;
            try {
              const parsed = JSON.parse(text) as { detail?: string; message?: string };
              detail = parsed.detail ?? parsed.message ?? detail;
            } catch {
              // Non-JSON body — keep status code only.
            }
            setProfileError(detail);
          }
          setSubmitting(false);
          return;
        }
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
                  checked={targetScope === 'filter'}
                  onChange={() => setTargetScope('filter')}
                  disabled={filterSnapshot === null}
                  title={
                    filterSnapshot === null
                      ? t('exports.modal.scope_filter_disabled', {
                          defaultValue:
                            'Filtr dostępny tylko z formularza /integrations/exports/new.',
                        })
                      : undefined
                  }
                />
                {t('exports.modal.scope_filter', { defaultValue: 'Cały filter' })}
                {filterSnapshot === null && (
                  <span className="ml-1 text-xs text-muted-foreground">
                    {t('exports.modal.scope_filter_hint', {
                      defaultValue: '(użyj /integrations/exports/new)',
                    })}
                  </span>
                )}
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

          {/* Section 4 — Save as profile */}
          <section className="space-y-2">
            <label className="flex items-center gap-2 text-sm font-medium">
              <input
                type="checkbox"
                className="size-4"
                checked={saveAsProfile}
                onChange={(e) => {
                  setSaveAsProfile(e.target.checked);
                  if (!e.target.checked) {
                    setProfileError(null);
                  }
                }}
              />
              {t('exports.modal.save_as_profile', { defaultValue: 'Zapisz jako profil' })}
            </label>
            {saveAsProfile && (
              <div className="space-y-1 pl-6">
                <input
                  type="text"
                  value={profileName}
                  onChange={(e) => {
                    setProfileName(e.target.value);
                    if (profileError !== null) setProfileError(null);
                  }}
                  maxLength={255}
                  placeholder={t('exports.modal.profile_name_placeholder', {
                    defaultValue: 'Nazwa profilu (1-255 znaków)',
                  })}
                  className="w-full rounded border border-input bg-background px-2 py-1.5 text-sm focus:outline-none focus:ring-2 focus:ring-ring"
                  aria-invalid={profileError !== null}
                  aria-describedby={profileError !== null ? 'profile-error' : undefined}
                />
                {profileError !== null && (
                  <p id="profile-error" className="text-xs text-rose-700" role="alert">
                    {profileError}
                  </p>
                )}
                <p className="text-xs text-muted-foreground">
                  {t('exports.modal.profile_hint', {
                    defaultValue:
                      'Profil zapisuje wybrane kolumny, format i scope. Uruchomisz go ponownie z zakładki Profile.',
                  })}
                </p>
              </div>
            )}
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
