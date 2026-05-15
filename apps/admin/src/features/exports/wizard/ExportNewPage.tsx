import { useState } from 'react';
import { useTranslation } from 'react-i18next';
import { useNavigate } from 'react-router';

import { ExportModal } from './ExportModal';

/**
 * EXP-12 (#591) — Full-page export form.
 *
 * Renders the same {@see ExportModal} from EXP-11 (#590) so submit flow,
 * payload validation, and sections (columns, format/encoding, scope,
 * save-as-profile) stay 1:1 with the modal-from-list path.
 *
 * EXP-20 (#632) — adds a Filter section above the modal. When the user
 * pastes a valid FilterDSL snapshot (PRD §5.5, e.g.
 * `{"attr":"brand","op":"IN","value":["Festo"]}`), the JSON is parsed
 * client-side and forwarded as `filterSnapshot` to the modal. The
 * modal then enables the "Cały filter" radio and rides the snapshot
 * through `POST /api/products/export`, where `SyncExportRunner` compiles
 * it via `FilterDslResolver::toCountSql` into tenant-scoped SQL.
 *
 * Świadome odejście: chip-style filter builder (PRD §13.2) — deferred
 * to a follow-up; the raw textarea is the minimal viable surface for
 * Marcin's snapshot use case and reuses the catalog filter operators
 * verbatim.
 */
export function ExportNewPage(): React.ReactElement {
  const navigate = useNavigate();
  const { t } = useTranslation();

  const [filterJson, setFilterJson] = useState('');
  const [filterSnapshot, setFilterSnapshot] = useState<Record<string, unknown> | null>(null);
  const [filterError, setFilterError] = useState<string | null>(null);

  const onFilterChange = (raw: string) => {
    setFilterJson(raw);
    const trimmed = raw.trim();
    if (trimmed === '') {
      setFilterSnapshot(null);
      setFilterError(null);
      return;
    }
    try {
      const parsed: unknown = JSON.parse(trimmed);
      if (typeof parsed !== 'object' || parsed === null || Array.isArray(parsed)) {
        setFilterSnapshot(null);
        setFilterError(
          t('exports.new.filter_error_object', {
            defaultValue:
              'Filtr musi być obiektem JSON (np. {"attr":"brand","op":"IN","value":["Festo"]}).',
          }),
        );
        return;
      }
      setFilterSnapshot(parsed as Record<string, unknown>);
      setFilterError(null);
    } catch (err) {
      setFilterSnapshot(null);
      setFilterError(
        err instanceof Error
          ? err.message
          : t('exports.new.filter_error_parse', { defaultValue: 'Nieprawidłowy JSON.' }),
      );
    }
  };

  const handleClose = (open: boolean) => {
    if (!open) {
      navigate('/integrations/exports');
    }
  };

  return (
    <div className="space-y-4">
      <p className="text-sm text-muted-foreground">
        {t('exports.new.hint', {
          defaultValue:
            'Pełna forma eksportu — wybierz kolumny, format i zasięg, potem [Eksportuj]. Sync (<100 SKU) zwróci plik bezpośrednio; ≥100 SKU pójdzie do kolejki async (widoczne w Recent exports).',
        })}
      </p>

      <details className="rounded-md border bg-card">
        <summary className="cursor-pointer px-3 py-2 text-sm font-medium">
          {t('exports.new.filter_summary', {
            defaultValue: 'Filtr (opcjonalny — odblokowuje scope „Cały filter")',
          })}
        </summary>
        <div className="space-y-2 border-t p-3">
          <label className="block text-xs text-muted-foreground" htmlFor="export-filter-dsl">
            {t('exports.new.filter_label', {
              defaultValue:
                'Wklej FilterDSL jako JSON (np. {"attr":"brand","op":"IN","value":["Festo"]}). Operator-set z PRD §5.5.',
            })}
          </label>
          <textarea
            id="export-filter-dsl"
            value={filterJson}
            onChange={(e) => onFilterChange(e.target.value)}
            rows={4}
            placeholder='{"attr":"brand","op":"IN","value":["Festo","Bosch"]}'
            className="w-full rounded border border-input bg-background px-2 py-1.5 font-mono text-xs focus:outline-none focus:ring-2 focus:ring-ring"
            aria-invalid={filterError !== null}
            aria-describedby={filterError !== null ? 'filter-error' : undefined}
          />
          {filterError !== null && (
            <p id="filter-error" className="text-xs text-rose-700" role="alert">
              {filterError}
            </p>
          )}
          {filterSnapshot !== null && filterError === null && (
            <p className="text-xs text-emerald-700">
              {t('exports.new.filter_ok', {
                defaultValue: 'Filtr OK — wybierz „Cały filter" w sekcji Co eksportujesz.',
              })}
            </p>
          )}
        </div>
      </details>

      <ExportModal open={true} onOpenChange={handleClose} filterSnapshot={filterSnapshot} />
    </div>
  );
}

export default ExportNewPage;
