import { useCreate } from '@refinedev/core';
import { useState } from 'react';
import { useTranslation } from 'react-i18next';
import { useNavigate } from 'react-router';

import { ProductForm, type ProductFormValues } from './form';
import { useDefaultObjectType } from './use-default-object-type';

export function ProductCreatePage() {
  const { t } = useTranslation();
  const navigate = useNavigate();
  const { mutateAsync, mutation } = useCreate();
  const isPending = mutation.isPending;
  const [apiError, setApiError] = useState<string | null>(null);
  const { objectTypeId, isLoading, error } = useDefaultObjectType('product');

  if (isLoading) {
    return <p className="text-sm text-muted-foreground">{t('app.loading')}</p>;
  }
  if (error || objectTypeId === null) {
    return (
      <p className="text-sm text-destructive" role="alert">
        {t('products.no_object_type', {
          defaultValue: 'No built-in product ObjectType is available. Run the catalog seeder.',
        })}
      </p>
    );
  }

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
            values: toCatalogObjectInput(values, objectTypeId),
          });
          navigate('/products');
        } catch {
          setApiError(t('products.validation.sku_required'));
        }
      }}
    />
  );
}

function toCatalogObjectInput(
  values: ProductFormValues,
  objectTypeId: string,
): Record<string, unknown> {
  const attributes: Record<string, unknown> = {};
  if (values.name) attributes.name = values.name;
  if (values.brand && values.brand !== '') attributes.brand = values.brand;
  if (values.description && values.description !== '') attributes.description = values.description;

  const body: Record<string, unknown> = {
    code: values.sku,
    objectTypeId,
  };
  if (Object.keys(attributes).length > 0) {
    body.attributes = attributes;
  }
  return body;
}
