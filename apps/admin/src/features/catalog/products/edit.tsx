import { useOne, useUpdate } from '@refinedev/core';
import { useState } from 'react';
import { useTranslation } from 'react-i18next';
import { useNavigate, useParams } from 'react-router';

import { ProductForm, type ProductFormValues } from './form';

interface CatalogObject {
  id: string;
  code: string;
  enabled?: boolean;
  status?: string;
  attributesIndexed?: Record<string, unknown>;
}

export function ProductEditPage() {
  const { t } = useTranslation();
  const navigate = useNavigate();
  const params = useParams<{ id: string }>();
  const productId = params.id ?? '';
  const { result, query } = useOne<CatalogObject>({
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
  const attrs = (product.attributesIndexed ?? {}) as Record<string, unknown>;
  const name = typeof attrs.name === 'string' ? attrs.name : '';
  const brand = typeof attrs.brand === 'string' ? attrs.brand : '';
  const description = typeof attrs.description === 'string' ? attrs.description : '';

  return (
    <ProductForm
      mode="edit"
      defaultValues={{
        sku: product.code,
        name,
        description,
        brand,
      }}
      isSubmitting={isPending}
      apiError={apiError}
      onSubmit={async (values: ProductFormValues) => {
        setApiError(null);
        try {
          await mutateAsync({
            resource: 'products',
            id: productId,
            values: toPatchInput(values),
          });
          navigate('/products');
        } catch {
          setApiError(t('products.validation.name_required'));
        }
      }}
    />
  );
}

function toPatchInput(values: ProductFormValues): Record<string, unknown> {
  // SKU is immutable on PATCH (per #3 / object:patch group); we ship the
  // editable fields under `attributes` and let the merge-patch processor
  // upsert ObjectValue rows server-side (#45).
  const attributes: Record<string, unknown> = {
    name: values.name,
  };
  attributes.brand = values.brand && values.brand !== '' ? values.brand : null;
  attributes.description =
    values.description && values.description !== '' ? values.description : null;
  return { attributes };
}
