import { useTranslation } from 'react-i18next';
import { useNavigate } from 'react-router';

import { ExportModal } from './ExportModal';

/**
 * EXP-12 (#591) — Full-page export form.
 *
 * MVP renders the same {@see ExportModal} from EXP-11 (#590) but with
 * `open=true` and a redirect on close — so power users who reach
 * `/integrations/exports/new` directly (Marcin's snapshot use case
 * from PRD §3.5) get the full picker + scope sections without going
 * through the catalog list page.
 *
 * Why reuse the modal instead of a parallel page-level form:
 *   - Submit flow + payload validation are identical.
 *   - Sections (columns, format/encoding, scope) match 1:1.
 *   - Single source of truth for FE export controls keeps the
 *     contract tight as the modal evolves (e.g. when locale toggles
 *     and "save as profile" wire in).
 *
 * Świadome odejścia (PRD §13.1 + §14):
 *   - Chip filter picker — backend zwraca 501 dla target_scope=filter
 *     w MVP sync runnera. Once the FilterDslResolver lands in the
 *     async path, this page grows a chip-style filter section above
 *     the modal — same component, just gated on tenant-side feature
 *     flag.
 *   - No back-to-hub breadcrumb in MVP — Esc/cancel on the modal
 *     returns to `/integrations/exports` which IS the hub.
 */
export function ExportNewPage(): React.ReactElement {
  const navigate = useNavigate();
  const { t } = useTranslation();

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
      <ExportModal open={true} onOpenChange={handleClose} />
    </div>
  );
}

export default ExportNewPage;
