import { ArrowLeft, Layers } from 'lucide-react';
import { useTranslation } from 'react-i18next';
import { Link, useParams } from 'react-router';

import { Button } from '@/components/ui/button';

/**
 * UI-03.2 placeholder for AttributeValuesView (epik UI-03 #357).
 *
 * The handoff design specifies a full-page editor for select/multi-select
 * attribute values: locale tabs, color swatches, default/deprecated
 * toggles, drag-reorder. None of that is implementable yet — there is no
 * backend (`GET/POST/PATCH/DELETE /api/attributes/{id}/values`) and the
 * `attribute_values` entity itself is in the backlog. This page renders
 * as a banner so the click flow from the list view (violet "Wartości"
 * badge → here) exists end-to-end.
 *
 * MOCK: full AttributeValuesView — wymaga endpointów /values + entity
 * attribute_values (patrz Project Plan/UI/Wdrozenie_grafiki/modelowanie-do-oprogramowania.md).
 */
export function AttributeValuesPage() {
  const { t } = useTranslation();
  const { id = '' } = useParams<{ id: string }>();

  return (
    <div className="space-y-6 px-4 py-6 sm:px-6 lg:px-10">
      <div>
        <Button asChild variant="ghost" size="sm">
          <Link to={`/modeling/attributes/${id}`}>
            <ArrowLeft className="size-4" />
            {t('attribute_values.back', { defaultValue: 'Wróć do atrybutu' })}
          </Link>
        </Button>
      </div>

      <div className="rounded-3xl border border-accent-violet/30 bg-accent-violet/5 p-8 soft-shadow">
        <div className="mb-3 inline-flex items-center gap-1.5 rounded-full bg-accent-violet/10 px-2.5 py-1 text-[11px] font-medium uppercase tracking-wide text-accent-violet">
          <Layers className="size-3.5" />
          {t('attribute_values.mock_badge', { defaultValue: 'Wymaga oprogramowania' })}
        </div>
        <h1 className="display text-[28px] font-semibold leading-tight text-ink">
          {t('attribute_values.title', { defaultValue: 'Wartości atrybutu' })}
        </h1>
        <p className="mt-2 max-w-3xl text-[14px] text-ink-2">
          {t('attribute_values.mock_body', {
            defaultValue:
              'Pełna edycja wartości select / multi-select (etykiety per locale, kolory, default, deprecated, kolejność) wymaga endpointów /api/attributes/{id}/values. Tracking w Project Plan/UI/Wdrozenie_grafiki/modelowanie-do-oprogramowania.md.',
          })}
        </p>
      </div>
    </div>
  );
}
