import { useOne, useUpdate } from '@refinedev/core';
import { useState } from 'react';
import { useTranslation } from 'react-i18next';
import { useNavigate, useParams } from 'react-router';

import { ProductForm, type ProductFormValues } from './form';

interface Product {
  id: string;
  sku: string;
  name: string;
  description: string | null;
  brand: string | null;
}

export function ProductEditPage() {
  const { t } = useTranslation();
  const navigate = useNavigate();
  const params = useParams<{ id: string }>();
  const productId = params.id ?? '';
  const { result, query } = useOne<Product>({
    resource: 'products',
    id: productId,
    queryOptions: { enabled: productId.length > 0 },
  });
  const isLoading = query.isLoading;
  const { mutateAsync, mutation } = useUpdate();
  const isPending = mutation.isPending;
  const [apiError, setApiError] = useState<string | null>(null);

  if (isLoading || !result) {
    return <p className="text-sm text-muted-foreground">{t('app.loading')}</p>;
  }

  const product = result;

  return (
    <ProductForm
      mode="edit"
      defaultValues={{
        sku: product.sku,
        name: product.name,
        description: product.description ?? '',
        brand: product.brand ?? '',
      }}
      isSubmitting={isPending}
      apiError={apiError}
      onSubmit={async (values: ProductFormValues) => {
        setApiError(null);
        try {
          await mutateAsync({
            resource: 'products',
            id: productId,
            // SKU is immutable on the API (ticket #3 — product:patch group);
            // we still drop it client-side to avoid sending an ignored field.
            values: cleanForPatch(values),
          });
          navigate('/products');
        } catch {
          setApiError(t('products.validation.name_required'));
        }
      }}
    />
  );
}

function cleanForPatch(values: ProductFormValues): Record<string, unknown> {
  const { sku: _sku, ...rest } = values;
  return Object.fromEntries(
    Object.entries(rest).map(([key, value]) => [key, value === '' ? null : value]),
  );
}
