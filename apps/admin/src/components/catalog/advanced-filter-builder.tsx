import { Filter } from 'lucide-react';
import { useState } from 'react';
import { useTranslation } from 'react-i18next';

import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Sheet, SheetContent, SheetTitle, SheetTrigger } from '@/components/ui/sheet';

import type { FilterValue } from './product-filter-chips';

/**
 * UI-02.9 (#299) — Advanced filter builder Sheet. Per
 * `Project Plan/UI/epik-02-produkty.md` §4.1 punkt 7.
 *
 * MVP slice ships the most common product filters (brand, status,
 * completeness range) hardcoded; the dynamic per-attribute version
 * lands once the form-schema endpoint (UI-02.5
 * `/effective-attribute-groups`) is wired through.
 */
export function AdvancedFilterBuilder({
  filters,
  onApply,
  onSaveAsView,
}: {
  filters: Record<string, FilterValue>;
  onApply: (next: Record<string, FilterValue>) => void;
  onSaveAsView?: () => void;
}) {
  const { t } = useTranslation();
  const [open, setOpen] = useState(false);
  const [draft, setDraft] = useState<Record<string, FilterValue>>(filters);

  const setBrand = (raw: string): void => {
    setDraft((prev) => {
      const next = { ...prev };
      if (raw.trim() === '') delete next.brand;
      else next.brand = raw.trim();
      return next;
    });
  };

  const setCompletenessRange = (gte: string, lte: string): void => {
    setDraft((prev) => {
      const next = { ...prev };
      const range: { gte?: number; lte?: number } = {};
      if (gte !== '') range.gte = Number.parseInt(gte, 10);
      if (lte !== '') range.lte = Number.parseInt(lte, 10);
      if (range.gte === undefined && range.lte === undefined) {
        delete next.completeness;
      } else {
        next.completeness = range;
      }
      return next;
    });
  };

  const setStatus = (status: string): void => {
    setDraft((prev) => {
      const next = { ...prev };
      if (status === '') delete next.status;
      else next.status = status;
      return next;
    });
  };

  const handleApply = (): void => {
    onApply(draft);
    setOpen(false);
  };

  const handleReset = (): void => {
    setDraft({});
    onApply({});
    setOpen(false);
  };

  const completeness =
    typeof draft.completeness === 'object' && !Array.isArray(draft.completeness)
      ? draft.completeness
      : {};

  return (
    <Sheet open={open} onOpenChange={setOpen}>
      <SheetTrigger asChild>
        <Button variant="outline" size="sm">
          <Filter className="size-4" />
          {t('products.filters.advanced', { defaultValue: 'Advanced' })}
        </Button>
      </SheetTrigger>
      <SheetContent side="right" className="w-[420px] p-6">
        <div className="space-y-1">
          <SheetTitle>
            {t('products.filters.advanced_title', { defaultValue: 'Advanced filters' })}
          </SheetTitle>
          <p className="text-sm text-muted-foreground">
            {t('products.filters.advanced_subtitle', {
              defaultValue: 'Compose a filter set across product attributes.',
            })}
          </p>
        </div>

        <div className="space-y-5 py-4">
          <div className="space-y-2">
            <label htmlFor="filter-brand" className="text-sm font-medium">
              {t('products.fields.brand')}
            </label>
            <Input
              id="filter-brand"
              value={typeof draft.brand === 'string' ? draft.brand : ''}
              onChange={(e) => setBrand(e.target.value)}
              placeholder="Festo"
            />
          </div>

          <div className="space-y-2">
            <span className="text-sm font-medium">
              {t('products.fields.completeness', { defaultValue: 'Completeness' })}
            </span>
            <div className="flex items-center gap-2">
              <Input
                type="number"
                min={0}
                max={100}
                value={completeness.gte ?? ''}
                onChange={(e) =>
                  setCompletenessRange(e.target.value, String(completeness.lte ?? ''))
                }
                placeholder="≥ 0"
              />
              <span className="text-muted-foreground">—</span>
              <Input
                type="number"
                min={0}
                max={100}
                value={completeness.lte ?? ''}
                onChange={(e) =>
                  setCompletenessRange(String(completeness.gte ?? ''), e.target.value)
                }
                placeholder="≤ 100"
              />
            </div>
          </div>

          <div className="space-y-2">
            <span className="text-sm font-medium">
              {t('products.fields.status', { defaultValue: 'Status' })}
            </span>
            <div className="flex flex-wrap gap-2">
              {['', 'draft', 'published', 'archived'].map((status) => (
                <Button
                  key={status || 'any'}
                  type="button"
                  variant={(draft.status ?? '') === status ? 'secondary' : 'outline'}
                  size="sm"
                  onClick={() => setStatus(status)}
                >
                  {status === ''
                    ? t('products.filters.status_any', { defaultValue: 'Any' })
                    : status}
                </Button>
              ))}
            </div>
          </div>
        </div>

        <div className="mt-auto flex flex-row items-center justify-between border-t pt-4">
          <Button variant="ghost" size="sm" onClick={handleReset}>
            {t('products.filters.reset', { defaultValue: 'Reset all' })}
          </Button>
          <div className="flex gap-2">
            {onSaveAsView !== undefined ? (
              <Button variant="outline" size="sm" onClick={onSaveAsView}>
                {t('products.filters.save_as_view', { defaultValue: 'Save as view' })}
              </Button>
            ) : null}
            <Button size="sm" onClick={handleApply}>
              {t('products.filters.apply', { defaultValue: 'Apply' })}
            </Button>
          </div>
        </div>
      </SheetContent>
    </Sheet>
  );
}
