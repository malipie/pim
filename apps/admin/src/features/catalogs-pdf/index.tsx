import { useTranslation } from 'react-i18next';

import { ComingSoonPlaceholder } from '@/features/settings/ComingSoonPlaceholder';

export function CatalogsPdfPage() {
  const { t } = useTranslation();

  return (
    <div className="space-y-6">
      <header className="space-y-1">
        <h1 className="display text-[28px] font-semibold tracking-tight">
          {t('catalogs_pdf.page_title')}
        </h1>
      </header>
      <ComingSoonPlaceholder
        titleKey="catalogs_pdf.placeholder_title"
        descriptionKey="catalogs_pdf.placeholder_description"
      />
    </div>
  );
}
