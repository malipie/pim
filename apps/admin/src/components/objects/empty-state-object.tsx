import { Boxes, Plus } from 'lucide-react';
import { useTranslation } from 'react-i18next';

import { Button } from '@/components/ui/button';
import type { ListSchemaObjectType } from '@/hooks/use-list-schema';

/**
 * ULV-06 (#988) — generic empty state for the universal `ObjectListView`.
 *
 * Replaces the product-only `EmptyStateProducts` for every ObjectType
 * (built-in product/category/asset/brand or custom). The CTA is muted
 * for now — wizard wiring per ObjectType + permission gate lives in
 * ULV-08 (routing) and ULV-10 (wizard polish).
 */
export function EmptyStateObject({
  objectType,
  onCreate,
}: {
  objectType: ListSchemaObjectType;
  onCreate?: () => void;
}) {
  const { t, i18n } = useTranslation();
  const locale = i18n.language.split('-')[0] ?? 'en';
  const typeLabel = objectType.label[locale] ?? objectType.label.en ?? objectType.code;

  return (
    <div className="mx-auto flex max-w-md flex-col items-center gap-4 rounded-lg border bg-card px-6 py-10 text-center">
      <Boxes className="size-12 text-muted-foreground" />
      <div className="space-y-1">
        <h2 className="text-xl font-semibold">
          {t('object_list.empty.title', {
            defaultValue: 'No "{{type}}" instances yet',
            type: typeLabel,
          })}
        </h2>
        <p className="text-sm text-muted-foreground">
          {t('object_list.empty.subtitle', {
            defaultValue: 'Create the first one to get started.',
          })}
        </p>
      </div>

      {onCreate !== undefined ? (
        <Button onClick={onCreate}>
          <Plus className="size-4" />
          {t('object_list.empty.create', {
            defaultValue: 'Create {{type}}',
            type: typeLabel,
          })}
        </Button>
      ) : null}
    </div>
  );
}
