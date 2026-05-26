import { useTranslation } from 'react-i18next';
import { useParams, useSearchParams } from 'react-router';

import { UniversalDetailPage } from '@/components/objects/universal-detail-page';
import { useListSchema } from '@/hooks/use-list-schema';

import { ProductDetailPage } from './components/product-detail-page';
import { useDefaultObjectType } from './use-default-object-type';

/**
 * UX-09 — `/products/:id` adds an opt-in `?universal=1` path that
 * renders the new `UniversalDetailPage` so operators can preview the
 * cutover during the dual-maintenance window. The legacy
 * `ProductDetailPage` stays as the default render because four power
 * features have not been migrated yet (RelationsTab, full variants
 * editor, SyncStatusCard + AgentSuggestionsCard, Duplicate / Preview
 * buttons). When their follow-up tickets land the default flips and
 * the conditional gets removed.
 */
export function ProductShowPage() {
  const params = useParams<{ id: string }>();
  const [searchParams] = useSearchParams();
  const productId = params.id ?? '';
  const useUniversal = searchParams.get('universal') === '1';

  if (useUniversal) {
    return <ProductDetailUniversal productId={productId} />;
  }

  return <ProductDetailPage mode="edit" productId={productId} />;
}

function ProductDetailUniversal({ productId }: { productId: string }) {
  const { t, i18n } = useTranslation();
  const lang = i18n.language.split('-')[0] ?? 'en';
  const { objectTypeId, isLoading } = useDefaultObjectType('product');
  const schemaQuery = useListSchema(objectTypeId ?? undefined);

  if (isLoading || (objectTypeId && schemaQuery.isLoading)) {
    return (
      <div
        aria-busy="true"
        className="flex h-64 items-center justify-center text-sm text-muted-foreground"
      >
        {t('app.loading')}
      </div>
    );
  }

  if (!objectTypeId || schemaQuery.isError || !schemaQuery.data) {
    return (
      <div className="rounded border border-destructive bg-destructive/5 p-6 text-sm text-destructive">
        {t('object_detail.errors.schema_fetch', {
          defaultValue: 'Could not load product schema. Try ?legacy=1 to view the legacy page.',
        })}
      </div>
    );
  }

  const labels = schemaQuery.data.objectType.label ?? {};
  const typeLabel = labels[lang] ?? labels.en ?? labels.pl ?? 'Product';

  return (
    <UniversalDetailPage
      objectId={productId}
      objectTypeCode="product"
      objectTypeLabel={typeLabel}
      backHref="/products"
      isCategorizable={schemaQuery.data.objectType.is_categorizable}
      hasMultimedia={schemaQuery.data.objectType.has_multimedia}
      hasVariants={schemaQuery.data.objectType.has_variants}
    />
  );
}
