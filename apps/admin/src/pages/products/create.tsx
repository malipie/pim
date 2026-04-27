import { useCreate } from '@refinedev/core';
import { useState } from 'react';
import { useTranslation } from 'react-i18next';
import { useNavigate } from 'react-router';

import { ProductForm, type ProductFormValues } from './form';

export function ProductCreatePage() {
  const { t } = useTranslation();
  const navigate = useNavigate();
  const { mutateAsync, mutation } = useCreate();
  const isPending = mutation.isPending;
  const [apiError, setApiError] = useState<string | null>(null);

  return (
    <ProductForm
      mode="create"
      isSubmitting={isPending}
      apiError={apiError}
      onSubmit={async (values: ProductFormValues) => {
        setApiError(null);
        try {
          await mutateAsync({
            resource: 'products',
            values: cleanEmpty(values),
          });
          navigate('/products');
        } catch {
          setApiError(t('products.validation.sku_required'));
        }
      }}
    />
  );
}

function cleanEmpty(values: ProductFormValues): Record<string, unknown> {
  return Object.fromEntries(
    Object.entries(values).filter(([, value]) => value !== '' && value !== undefined),
  );
}
