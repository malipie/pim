import { Plug } from 'lucide-react';
import { useTranslation } from 'react-i18next';

export function ImportSourcesPlaceholder() {
  const { t } = useTranslation();
  return (
    <div className="flex flex-col items-center justify-center gap-3 rounded-2xl border border-dashed border-zinc-200 bg-zinc-50/60 px-6 py-16 text-center">
      <Plug className="h-8 w-8 text-zinc-400" aria-hidden="true" />
      <div className="space-y-1">
        <h2 className="text-base font-semibold text-zinc-900">
          {t('imports.placeholder.coming_soon')}
        </h2>
        <p className="max-w-md text-sm text-muted-foreground">
          {t('imports.placeholder.sources_subtitle')}
        </p>
      </div>
    </div>
  );
}
