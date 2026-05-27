import { Loader2 } from 'lucide-react';
import { useTranslation } from 'react-i18next';

/**
 * Issue #1043 — shared loading skeleton for detail pages. Renders an
 * `aria-busy` container with a spinner so screen readers announce the
 * loading state while ProductDetailPage / UniversalDetailPage wait for
 * the GET to settle. Replaces the previous bare `<p>Ładowanie...</p>`.
 */
export function DetailLoadingState() {
  const { t } = useTranslation();
  return (
    <div
      aria-busy="true"
      role="status"
      className="flex h-64 items-center justify-center gap-2 text-sm text-muted-foreground"
    >
      <Loader2 className="size-4 animate-spin" aria-hidden="true" />
      <span>{t('app.loading', { defaultValue: 'Ładowanie…' })}</span>
    </div>
  );
}
