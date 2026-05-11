import { Layers } from 'lucide-react';
import * as React from 'react';
import { useTranslation } from 'react-i18next';

import { Button } from '@/components/ui/button';

import { ImportProfileManager } from './ImportProfileManager';

/**
 * VIEW-IMP-00 placeholder for the dedicated Profiles tab.
 *
 * Until VIEW-IMP-02 ships the full grid/list view, we expose the
 * existing `ImportProfileManager` Sheet via a button so users still
 * have a way to manage profiles. The empty-state banner signals that
 * the proper view is coming.
 */
export function ImportProfilesPlaceholder() {
  const { t } = useTranslation();
  const [open, setOpen] = React.useState(false);
  return (
    <div className="flex flex-col items-center justify-center gap-3 rounded-2xl border border-dashed border-zinc-200 bg-zinc-50/60 px-6 py-16 text-center">
      <Layers className="h-8 w-8 text-zinc-400" aria-hidden="true" />
      <div className="space-y-1">
        <h2 className="text-base font-semibold text-zinc-900">
          {t('imports.placeholder.coming_soon')}
        </h2>
        <p className="max-w-md text-sm text-muted-foreground">
          {t('imports.placeholder.profiles_subtitle')}
        </p>
      </div>
      <Button variant="outline" size="sm" onClick={() => setOpen(true)}>
        {t('imports.placeholder.profiles_open_manager')}
      </Button>
      <ImportProfileManager open={open} onOpenChange={setOpen} />
    </div>
  );
}
