import { ArrowLeft } from 'lucide-react';
import { useTranslation } from 'react-i18next';
import { Link } from 'react-router';

import { CreateProductWizard } from '@/components/catalog/create-product-wizard';
import { Button } from '@/components/ui/button';

import { useDefaultObjectType } from './use-default-object-type';

export function ProductCreatePage() {
  const { t } = useTranslation();
  const { objectTypeId, isLoading, error } = useDefaultObjectType('product');

  return (
    <div className="space-y-6">
      <div className="space-y-1">
        <Button asChild variant="ghost" size="sm" className="-ml-3">
          <Link to="/products">
            <ArrowLeft className="size-4" />
            {t('products.back')}
          </Link>
        </Button>
        <h1 className="text-2xl font-semibold tracking-tight">
          {t('products.create.page_title', { defaultValue: 'Create product' })}
        </h1>
        <p className="text-sm text-muted-foreground">
          {t('products.create.page_subtitle', {
            defaultValue: '3 steps: pick family → required attributes → confirm.',
          })}
        </p>
      </div>

      {isLoading ? (
        <p className="text-sm text-muted-foreground">{t('app.loading')}</p>
      ) : error !== null || objectTypeId === null ? (
        <p className="text-sm text-rose-600" role="alert">
          {t('products.no_object_type', {
            defaultValue: 'No built-in product ObjectType is available. Run the catalog seeder.',
          })}
        </p>
      ) : (
        <CreateProductWizard objectTypeId={objectTypeId} />
      )}
    </div>
  );
}
