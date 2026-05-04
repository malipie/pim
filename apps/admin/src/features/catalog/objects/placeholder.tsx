import { Construction } from 'lucide-react';
import { useTranslation } from 'react-i18next';
import { Link, useParams } from 'react-router';

import { Button } from '@/components/ui/button';

/**
 * VIEW-08 (#427) — placeholder for object_type listings reachable via
 * `/objects/:code` after operator promotes a custom ObjectType to the
 * main menu via `/settings/menu`. The full generic listing ships in the
 * follow-up B-2 ticket (`<ObjectListingPage />` driven by ObjectType
 * `listing_config`); until then we render a Construction card so the
 * sidebar entry doesn't 404 → fallback to /dashboard.
 */
export function ObjectListingPlaceholder() {
  const { t } = useTranslation();
  const params = useParams<{ code: string }>();
  const code = params.code ?? '';

  return (
    <div className="flex min-h-[420px] flex-col items-center justify-center gap-4 rounded-lg border border-dashed bg-background p-8 text-center">
      <div className="flex size-12 items-center justify-center rounded-full bg-accent-violet/10 text-accent-violet">
        <Construction className="size-6" />
      </div>
      <div className="space-y-1">
        <h2 className="display text-xl font-semibold tracking-tight">
          {t('objects.placeholder.title', {
            defaultValue: 'Widok „{{code}}" w przygotowaniu',
            code,
          })}
        </h2>
        <p className="max-w-md text-sm text-muted-foreground">
          {t('objects.placeholder.description', {
            defaultValue:
              'Generyczna strona listingu dla custom ObjectType jest planowana w follow-upie VIEW-08 B-2. Na razie pozycję możesz ukryć z menu przez Ustawienia → Menu.',
          })}
        </p>
      </div>
      <div className="flex gap-2">
        <Button asChild variant="outline" size="sm">
          <Link to="/settings/menu">
            {t('objects.placeholder.manage_menu', {
              defaultValue: 'Zarządzaj menu',
            })}
          </Link>
        </Button>
        <Button asChild variant="ghost" size="sm">
          <Link to="/dashboard">
            {t('settings.placeholder.back', { defaultValue: 'Wróć do pulpitu' })}
          </Link>
        </Button>
      </div>
    </div>
  );
}
