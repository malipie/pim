import { useQuery, useQueryClient } from '@tanstack/react-query';
import { useTranslation } from 'react-i18next';

import { jsonFetch } from '@/lib/http';
import {
  RelationGroupCard,
  type RelationGroupPayload,
  type RelationsResponse,
} from './relations-tab';

/**
 * MODRC-05 (#1084) — inline editor used by {@link AttrRow} whenever a
 * relation-type attribute is placed in a `display_mode='stacked'` group
 * (or any non-tab context). Re-uses the existing {@link RelationGroupCard}
 * editor that powers the dedicated Relations tab, so the operator gets
 * the same picker + grid + advanced metadata UI regardless of the
 * group's display mode. The reverse panel stays in the Relations tab —
 * inline is forward-only.
 *
 * Data: shares the same `['objects', productId, 'relations']` cache key
 * as the tab, so the inline editor and the tab stay in sync.
 */
export interface RelationInlineEditorProps {
  productId: string;
  attributeId: string;
  attributeCode: string;
}

export function RelationInlineEditor({
  productId,
  attributeId,
  attributeCode,
}: RelationInlineEditorProps) {
  const { t, i18n } = useTranslation();
  const queryClient = useQueryClient();
  const locale = i18n.language === 'pl' ? 'pl' : 'en';

  const query = useQuery<RelationsResponse>({
    queryKey: ['objects', productId, 'relations'],
    queryFn: () =>
      jsonFetch<RelationsResponse>(`/api/objects/${productId}/relations`, {
        accept: 'application/json',
      }),
    staleTime: 5_000,
  });

  if (query.isLoading) {
    return <p className="text-xs text-muted-foreground">{t('app.loading')}</p>;
  }
  if (query.isError || !query.data) {
    return (
      <p className="text-xs text-destructive">
        {t('relations.fetch_error', { defaultValue: 'Nie udało się pobrać powiązań.' })}
      </p>
    );
  }

  const group: RelationGroupPayload | undefined = query.data.relationAttributes.find(
    (g) => g.attribute.id === attributeId || g.attribute.code === attributeCode,
  );

  if (!group) {
    return (
      <p className="text-xs text-muted-foreground">
        {t('relations.inline_no_attribute', {
          defaultValue:
            'Atrybut nie jest jeszcze przypięty do ObjectType — przypisanie idzie przez Modelowanie.',
        })}
      </p>
    );
  }

  return (
    <RelationGroupCard
      productId={productId}
      group={group}
      locale={locale}
      onChange={() => {
        void queryClient.invalidateQueries({
          queryKey: ['objects', productId, 'relations'],
        });
        void queryClient.invalidateQueries({
          queryKey: ['objects', productId, 'relations', 'reverse'],
        });
      }}
    />
  );
}
