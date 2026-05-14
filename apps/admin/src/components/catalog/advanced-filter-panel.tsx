import { Link2, Plus, Trash2, X } from 'lucide-react';
import { useTranslation } from 'react-i18next';

import { QueryGroupEditor } from '@/components/catalog/query-group-editor';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import {
  CORE_OPERATORS,
  FILTER_OPERATORS_BY_TYPE,
  type FilterCondition,
  type FilterOperator,
} from '@/lib/filters/filter-dsl';
import { cn } from '@/lib/utils';

/**
 * VIEW-09 (#535) — push-down sticky-collapsible advanced filter panel.
 * Replaces Sheet-based `AdvancedFilterBuilder` (UI-02.9 / #299).
 *
 * Grid mode only in VIEW-09 (Query mode tab disabled — lands in VIEW-09b).
 * Full operator set per attribute type → VIEW-10 (currently hardcoded
 * CORE_OPERATORS as fallback).
 *
 * Pixel-perfect Tailwind mirror of mockup `list-v2-overlays.jsx` l. 40-146.
 */

type PanelAttr = {
  code: string;
  name: string;
  type: keyof typeof FILTER_OPERATORS_BY_TYPE;
  star?: boolean;
};

const FIRST_PANEL_ATTR: PanelAttr = {
  code: 'brand',
  name: 'Marka',
  type: 'relation',
  star: true,
};

const PANEL_ATTRS: ReadonlyArray<PanelAttr> = [
  FIRST_PANEL_ATTR,
  { code: 'family', name: 'Rodzina', type: 'relation', star: true },
  { code: 'category', name: 'Kategoria', type: 'relation', star: true },
  { code: 'completeness_pct', name: 'Completeness %', type: 'number', star: true },
  { code: 'enabled', name: 'Aktywny', type: 'boolean', star: true },
  { code: 'price', name: 'Cena bazowa', type: 'metric', star: true },
  { code: 'stock', name: 'Stan magazynowy', type: 'number' },
  { code: 'main_image', name: 'Główne zdjęcie', type: 'asset' },
  { code: 'description.pl', name: 'Opis · PL', type: 'text' },
  { code: 'description.en', name: 'Opis · EN', type: 'text' },
  { code: 'meta_description', name: 'Meta description', type: 'text' },
  { code: 'tags', name: 'Tagi', type: 'multiselect' },
];

interface AdvancedFilterPanelProps {
  open: boolean;
  conditions: FilterCondition[];
  setConditions: (conditions: FilterCondition[]) => void;
  matchOperator: 'AND' | 'OR';
  setMatchOperator: (op: 'AND' | 'OR') => void;
  onApply: () => void;
  onClose: () => void;
  onClear: () => void;
  onSaveAsView?: () => void;
  onSaveAsPreset?: () => void;
  resultCount?: number;
  /**
   * VIEW-09b (#540) — Query mode editor state. When `mode='query'`,
   * `queryDsl` is the recursive AND/OR tree edited inside
   * `<QueryGroupEditor>`. When `mode='grid'`, `conditions` (flat list)
   * is the source of truth.
   */
  mode?: 'grid' | 'query';
  setMode?: (mode: 'grid' | 'query') => void;
  queryDsl?: import('@/lib/filters/filter-dsl').FilterGroup | null;
  setQueryDsl?: (dsl: import('@/lib/filters/filter-dsl').FilterGroup) => void;
}

export function AdvancedFilterPanel({
  open,
  conditions,
  setConditions,
  matchOperator,
  setMatchOperator,
  onApply,
  onClose,
  onClear,
  onSaveAsView,
  onSaveAsPreset,
  resultCount,
  mode = 'grid',
  setMode,
  queryDsl,
  setQueryDsl,
}: AdvancedFilterPanelProps) {
  const { t } = useTranslation();
  if (!open) return null;

  const updateCondition = (idx: number, patch: Partial<FilterCondition>): void => {
    setConditions(conditions.map((c, i) => (i === idx ? { ...c, ...patch } : c)));
  };
  const removeCondition = (idx: number): void => {
    setConditions(conditions.filter((_, i) => i !== idx));
  };
  const addCondition = (): void => {
    const defaultAttr = PANEL_ATTRS[0];
    if (!defaultAttr) return;
    setConditions([...conditions, { attr: defaultAttr.code, op: '=', value: '' }]);
  };

  return (
    <section
      aria-label={t('products.advanced_filter.title', { defaultValue: 'Filtr zaawansowany' })}
      className="rounded-3xl bg-white shadow-md border border-zinc-100 overflow-hidden"
    >
      {/* Header */}
      <div className="px-5 h-12 flex items-center gap-3 border-b border-zinc-100">
        <span className="text-[11px] uppercase tracking-wider font-semibold text-zinc-500">
          {t('products.advanced_filter.title', { defaultValue: 'Filtr zaawansowany' })}
        </span>
        {/* Mode toggle — VIEW-09b unlocked Query tab */}
        <div role="tablist" className="h-7 rounded-xl bg-zinc-100 inline-flex items-center p-0.5">
          <button
            type="button"
            role="tab"
            aria-selected={mode === 'grid'}
            onClick={() => setMode?.('grid')}
            className={cn(
              'h-6 px-2.5 rounded-lg text-[11.5px] font-medium',
              mode === 'grid'
                ? 'bg-white text-zinc-900 shadow-sm'
                : 'text-zinc-500 hover:text-zinc-900',
            )}
          >
            {t('products.advanced_filter.mode_grid', { defaultValue: 'Grid' })}
          </button>
          <button
            type="button"
            role="tab"
            aria-selected={mode === 'query'}
            onClick={() => setMode?.('query')}
            className={cn(
              'h-6 px-2.5 rounded-lg text-[11.5px] font-medium inline-flex items-center gap-1',
              mode === 'query'
                ? 'bg-white text-zinc-900 shadow-sm'
                : 'text-zinc-500 hover:text-zinc-900',
            )}
          >
            {t('products.advanced_filter.mode_query', { defaultValue: 'Query' })}
            <span className="text-[9.5px] uppercase tracking-wider px-1 py-px rounded bg-amber-100 text-amber-700">
              {t('products.advanced_filter.mode_query_power_label', { defaultValue: 'power' })}
            </span>
          </button>
        </div>
        <div className="ml-auto flex items-center gap-2">
          <span className="text-[11.5px] text-zinc-400 tabular-nums">
            {t('products.advanced_filter.condition_count', {
              count: conditions.length,
              defaultValue: `${conditions.length} warunków`,
            })}
          </span>
          <button
            type="button"
            onClick={onClear}
            className="text-[12px] text-zinc-500 hover:text-zinc-900 px-2 h-7 rounded-lg hover:bg-zinc-100"
          >
            {t('products.advanced_filter.clear', { defaultValue: 'Wyczyść' })}
          </button>
          <button
            type="button"
            aria-label={t('app.close', { defaultValue: 'Close' })}
            onClick={onClose}
            className="h-7 w-7 grid place-items-center rounded-lg text-zinc-400 hover:bg-zinc-100"
          >
            <X className="size-4" />
          </button>
        </div>
      </div>

      {/* Body — query mode (VIEW-09b) OR grid mode (VIEW-09) */}
      {mode === 'query' && queryDsl && setQueryDsl ? (
        <div className="p-5">
          <QueryGroupEditor group={queryDsl} attrs={PANEL_ATTRS} onChange={setQueryDsl} />
        </div>
      ) : null}
      {mode !== 'query' && (
        <div className="p-5">
          <div className="space-y-2">
            {conditions.map((cond, idx) => {
              const attrMeta = PANEL_ATTRS.find((a) => a.code === cond.attr) ?? FIRST_PANEL_ATTR;
              const ops = FILTER_OPERATORS_BY_TYPE[attrMeta.type] ?? CORE_OPERATORS;
              const isEmpty = cond.op === 'IS EMPTY' || cond.op === 'IS NOT EMPTY';

              return (
                // Index-based key is acceptable here: the editor mutates
                // conditions in-place by index, so a stable identity would
                // require a synthetic id field on every condition. The
                // surrounding controls already key off the index too.
                // biome-ignore lint/suspicious/noArrayIndexKey: see comment
                <div key={`cond-${idx}`} className="flex items-center gap-2">
                  {idx === 0 ? (
                    <span className="text-[11px] uppercase tracking-wider font-semibold text-zinc-400 w-12">
                      {t('products.advanced_filter.where_label', { defaultValue: 'Gdzie' })}
                    </span>
                  ) : (
                    <select
                      value={matchOperator}
                      onChange={(e) => setMatchOperator(e.target.value as 'AND' | 'OR')}
                      aria-label="Conjunction"
                      className="h-9 w-12 text-[11px] uppercase tracking-wider font-semibold text-zinc-500 bg-zinc-50 rounded-lg px-1 outline-none focus-visible:ring-2 focus-visible:ring-zinc-900 border-0"
                    >
                      <option value="AND">AND</option>
                      <option value="OR">OR</option>
                    </select>
                  )}

                  <select
                    value={cond.attr}
                    onChange={(e) => {
                      const nextAttr =
                        PANEL_ATTRS.find((a) => a.code === e.target.value) ?? FIRST_PANEL_ATTR;
                      const nextOps = FILTER_OPERATORS_BY_TYPE[nextAttr.type] ?? CORE_OPERATORS;
                      updateCondition(idx, {
                        attr: e.target.value,
                        op: nextOps[0] ?? '=',
                        value: '',
                      });
                    }}
                    aria-label="Atrybut"
                    className="h-9 px-2.5 text-[12.5px] bg-white border border-zinc-200 rounded-lg outline-none focus-visible:ring-2 focus-visible:ring-zinc-900 min-w-[160px]"
                  >
                    <optgroup
                      label={t('products.attribute_groups.favorites', { defaultValue: 'Ulubione' })}
                    >
                      {PANEL_ATTRS.filter((a) => a.star).map((a) => (
                        <option key={a.code} value={a.code}>
                          {a.name}
                        </option>
                      ))}
                    </optgroup>
                    <optgroup
                      label={t('products.attribute_groups.all', {
                        defaultValue: 'Wszystkie atrybuty',
                      })}
                    >
                      {PANEL_ATTRS.filter((a) => !a.star).map((a) => (
                        <option key={a.code} value={a.code}>
                          {a.name}
                        </option>
                      ))}
                    </optgroup>
                  </select>

                  <span className="text-[10px] font-mono px-1.5 py-0.5 rounded bg-zinc-100 text-zinc-500">
                    {attrMeta.type}
                  </span>

                  <select
                    value={cond.op}
                    onChange={(e) => updateCondition(idx, { op: e.target.value as FilterOperator })}
                    aria-label="Operator"
                    className="h-9 px-2.5 text-[12.5px] bg-white border border-zinc-200 rounded-lg outline-none focus-visible:ring-2 focus-visible:ring-zinc-900 font-mono min-w-[120px]"
                  >
                    {ops.map((o) => (
                      <option key={o} value={o}>
                        {o}
                      </option>
                    ))}
                  </select>

                  {!isEmpty && (
                    <Input
                      value={
                        typeof cond.value === 'string' || typeof cond.value === 'number'
                          ? String(cond.value)
                          : Array.isArray(cond.value)
                            ? cond.value.join(', ')
                            : ''
                      }
                      onChange={(e) => {
                        const raw = e.target.value;
                        const next =
                          cond.op === 'IN' || cond.op === 'NOT IN'
                            ? raw
                                .split(',')
                                .map((s) => s.trim())
                                .filter(Boolean)
                            : attrMeta.type === 'number' || attrMeta.type === 'metric'
                              ? raw === ''
                                ? ''
                                : Number(raw)
                              : raw;
                        updateCondition(idx, { value: next });
                      }}
                      placeholder={
                        attrMeta.type === 'number' || attrMeta.type === 'metric'
                          ? 'wartość'
                          : 'wpisz wartość'
                      }
                      className="h-9 flex-1 text-[12.5px]"
                    />
                  )}
                  {isEmpty && <div className="flex-1" />}

                  <button
                    type="button"
                    onClick={() => removeCondition(idx)}
                    aria-label="Usuń warunek"
                    className="h-9 w-9 grid place-items-center text-zinc-400 hover:text-rose-600 hover:bg-rose-50 rounded-lg"
                  >
                    <Trash2 className="size-4" />
                  </button>
                </div>
              );
            })}
          </div>

          <button
            type="button"
            onClick={addCondition}
            className="mt-3 text-[12.5px] text-zinc-500 hover:text-zinc-900 inline-flex items-center gap-1.5 h-8 px-2.5 rounded-lg hover:bg-zinc-100 focus:outline-none focus-visible:ring-2 focus-visible:ring-zinc-900"
          >
            <Plus className="size-3.5" />
            {t('products.advanced_filter.add_condition', { defaultValue: 'Dodaj warunek' })}
          </button>
        </div>
      )}

      {/* Footer */}
      <div className="px-5 h-12 flex items-center gap-3 border-t border-zinc-100 bg-zinc-50/50">
        <div className="inline-flex items-center gap-2 text-[11.5px] text-zinc-500">
          <span>{t('products.advanced_filter.match_label', { defaultValue: 'Dopasuj:' })}</span>
          <div className="h-6 rounded-lg bg-white border border-zinc-200 inline-flex items-center p-0.5">
            <button
              type="button"
              onClick={() => setMatchOperator('AND')}
              className={cn(
                'h-5 px-2 rounded-md text-[11px]',
                matchOperator === 'AND' ? 'bg-zinc-900 text-white font-medium' : 'text-zinc-500',
              )}
            >
              {t('products.advanced_filter.match_all', { defaultValue: 'Wszystkie (AND)' })}
            </button>
            <button
              type="button"
              onClick={() => setMatchOperator('OR')}
              className={cn(
                'h-5 px-2 rounded-md text-[11px]',
                matchOperator === 'OR' ? 'bg-zinc-900 text-white font-medium' : 'text-zinc-500',
              )}
            >
              {t('products.advanced_filter.match_any', { defaultValue: 'Dowolne (OR)' })}
            </button>
          </div>
        </div>

        {conditions.length > 0 && (
          <span className="text-[11.5px] text-zinc-400 inline-flex items-center gap-1.5">
            <Link2 className="size-3.5" />
            <span>
              {t('products.advanced_filter.url_updated', { defaultValue: 'URL zaktualizowany' })}
              {resultCount !== undefined && (
                <span className="ml-1 tabular-nums">— {resultCount} wyników</span>
              )}
            </span>
          </span>
        )}

        <div className="ml-auto flex items-center gap-2">
          {onSaveAsView && (
            <Button
              variant="ghost"
              type="button"
              onClick={onSaveAsView}
              className="h-9 text-[12.5px]"
            >
              {t('products.advanced_filter.save_as_view', {
                defaultValue: 'Zapisz jako Saved View',
              })}
            </Button>
          )}
          {onSaveAsPreset && (
            <Button
              variant="ghost"
              type="button"
              onClick={onSaveAsPreset}
              disabled={conditions.length === 0}
              className="h-9 text-[12.5px]"
            >
              {t('products.advanced_filter.save_as_preset', {
                defaultValue: 'Zapisz jako Smart Preset',
              })}
            </Button>
          )}
          <Button
            type="button"
            onClick={onApply}
            disabled={conditions.length === 0}
            className="h-9 px-4 rounded-xl bg-zinc-900 text-white text-[12.5px] font-medium hover:bg-zinc-800"
          >
            {t('products.advanced_filter.apply', { defaultValue: 'Zastosuj filtr' })}
          </Button>
        </div>
      </div>
    </section>
  );
}
