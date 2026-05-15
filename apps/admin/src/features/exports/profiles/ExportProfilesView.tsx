import { useTranslation } from 'react-i18next';

/**
 * EXP-09 (#588) — placeholder Saved profiles view.
 *
 * Real grid (Name | Created | Last run | Run count | Actions) + Run-now
 * dispatch lands with EXP-14 (#593). Stub keeps the route reachable.
 */
export function ExportProfilesView(): React.ReactElement {
  const { t } = useTranslation();

  return (
    <div className="rounded-md border border-dashed bg-muted/30 p-8 text-center">
      <h2 className="text-lg font-medium">
        {t('exports.profiles.empty_title', { defaultValue: 'Brak zapisanych profili eksportu' })}
      </h2>
      <p className="mt-2 text-sm text-muted-foreground">
        {t('exports.profiles.empty_subtitle', {
          defaultValue:
            'Saved profiles CRUD (EXP-14) landuje wkrótce. Profile zapisujesz checkboxem w modalu Eksport (EXP-11).',
        })}
      </p>
    </div>
  );
}

export default ExportProfilesView;
