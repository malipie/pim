import { useTranslation } from 'react-i18next';

/**
 * EXP-09 (#588) — placeholder Recent exports view.
 *
 * Real grid (Date | User | Format | Rows | Status | Actions) + Mercure
 * SSE wiring (`exports/{user_id}` topic) lands with EXP-13 (#592). This
 * stub keeps the route reachable so the layout works end-to-end and the
 * sidebar link does not 404.
 */
export function ExportSessionsView(): React.ReactElement {
  const { t } = useTranslation();

  return (
    <div className="rounded-md border border-dashed bg-muted/30 p-8 text-center">
      <h2 className="text-lg font-medium">
        {t('exports.sessions.empty_title', { defaultValue: 'Nie masz jeszcze eksportów' })}
      </h2>
      <p className="mt-2 text-sm text-muted-foreground">
        {t('exports.sessions.empty_subtitle', {
          defaultValue:
            'Recent exports grid + live status (EXP-13) wiąże się po zakończeniu marathonu. Otwórz [Nowy eksport →] aby przetestować flow.',
        })}
      </p>
    </div>
  );
}

export default ExportSessionsView;
