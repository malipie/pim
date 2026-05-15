import { useTranslation } from 'react-i18next';
import { Link } from 'react-router';

/**
 * EXP-09 (#588) — placeholder full-page export form.
 *
 * Real shared-sections form (column picker + locale/channel toggles +
 * format + scope + filter picker) lands with EXP-12 (#591). The
 * column picker itself lands with EXP-10 (#589).
 *
 * Until then this stub renders a friendly explainer so the route + the
 * tab CTA do not 404. Marathon mode trade-off documented in the
 * companion PR.
 */
export function ExportNewPage(): React.ReactElement {
  const { t } = useTranslation();

  return (
    <div className="space-y-4">
      <header className="space-y-1">
        <h1 className="display text-[28px] font-semibold tracking-tight">
          {t('exports.new.title', { defaultValue: 'Nowy eksport' })}
        </h1>
        <p className="text-sm text-muted-foreground">
          {t('exports.new.subtitle', {
            defaultValue:
              'Pełna forma (column picker + filtry + scope) landuje w EXP-10..EXP-12. Backend (POST /api/products/export) działa już teraz — wywołasz go z modalu na liście produktów po wdrożeniu EXP-11.',
          })}
        </p>
      </header>
      <div className="rounded-md border border-dashed bg-muted/30 p-6 text-sm">
        <p className="font-medium">Backend dostępny:</p>
        <ul className="mt-2 list-disc space-y-1 pl-5 text-muted-foreground">
          <li>
            <code>POST /api/products/export</code> — sync &lt;100 SKU, async &gt;=100 SKU
          </li>
          <li>
            <code>GET /api/exports/sessions</code> — historia (Recent grid w EXP-13)
          </li>
          <li>
            <code>GET /api/exports/profiles</code> — zapisane profile (Saved grid w EXP-14)
          </li>
        </ul>
      </div>
      <Link
        to="/integrations/exports"
        className="inline-flex items-center text-sm text-primary hover:underline"
      >
        ← {t('exports.new.back_to_hub', { defaultValue: 'Wróć do eksportów' })}
      </Link>
    </div>
  );
}

export default ExportNewPage;
