import { Trash2 } from 'lucide-react';

import { Input } from '@/components/ui/input';
import {
  CORE_OPERATORS,
  FILTER_OPERATORS_BY_TYPE,
  type FilterCondition,
  type FilterOperator,
} from '@/lib/filters/filter-dsl';

/**
 * VIEW-09b (#540) — single leaf condition inside the recursive Query
 * mode editor.
 *
 * Identical shape as the grid-mode row in `AdvancedFilterPanel` but
 * rendered inside `<QueryGroupEditor>` instead of the flat list, so
 * the same attribute / operator / value triplet works in both modes.
 */

interface QueryPanelAttr {
  code: string;
  name: string;
  type: string;
}

interface QueryConditionRowProps {
  condition: FilterCondition;
  attrs: ReadonlyArray<QueryPanelAttr>;
  onChange: (next: FilterCondition) => void;
  onRemove: () => void;
}

export function QueryConditionRow({
  condition,
  attrs,
  onChange,
  onRemove,
}: QueryConditionRowProps) {
  const attrMeta = attrs.find((a) => a.code === condition.attr) ?? attrs[0];
  if (!attrMeta) return null;
  const ops = FILTER_OPERATORS_BY_TYPE[attrMeta.type] ?? CORE_OPERATORS;
  const isEmpty =
    condition.op === 'IS EMPTY' ||
    condition.op === 'IS NOT EMPTY' ||
    condition.op === '= TRUE' ||
    condition.op === '= FALSE';

  return (
    <div className="flex items-center gap-2">
      <select
        value={condition.attr}
        onChange={(e) => {
          const nextAttr = attrs.find((a) => a.code === e.target.value) ?? attrMeta;
          const nextOps = FILTER_OPERATORS_BY_TYPE[nextAttr.type] ?? CORE_OPERATORS;
          onChange({ ...condition, attr: e.target.value, op: nextOps[0] ?? '=', value: '' });
        }}
        aria-label="Atrybut"
        className="h-8 px-2 text-[12px] bg-white border border-zinc-200 rounded-lg outline-none focus-visible:ring-2 focus-visible:ring-zinc-900 min-w-[140px]"
      >
        {attrs.map((a) => (
          <option key={a.code} value={a.code}>
            {a.name}
          </option>
        ))}
      </select>

      <select
        value={condition.op}
        onChange={(e) => onChange({ ...condition, op: e.target.value as FilterOperator })}
        aria-label="Operator"
        className="h-8 px-2 text-[12px] bg-white border border-zinc-200 rounded-lg outline-none focus-visible:ring-2 focus-visible:ring-zinc-900 font-mono min-w-[110px]"
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
            typeof condition.value === 'string' || typeof condition.value === 'number'
              ? String(condition.value)
              : Array.isArray(condition.value)
                ? condition.value.join(', ')
                : ''
          }
          onChange={(e) => {
            const raw = e.target.value;
            const next =
              condition.op === 'IN' || condition.op === 'NOT IN'
                ? raw
                    .split(',')
                    .map((s) => s.trim())
                    .filter(Boolean)
                : attrMeta.type === 'number' ||
                    attrMeta.type === 'metric' ||
                    attrMeta.type === 'price'
                  ? raw === ''
                    ? ''
                    : Number(raw)
                  : raw;
            onChange({ ...condition, value: next });
          }}
          placeholder={
            attrMeta.type === 'number' || attrMeta.type === 'metric' ? 'wartość' : 'wpisz wartość'
          }
          className="h-8 flex-1 text-[12px]"
        />
      )}
      {isEmpty && <div className="flex-1" />}

      <button
        type="button"
        onClick={onRemove}
        aria-label="Usuń warunek"
        className="h-8 w-8 grid place-items-center text-zinc-400 hover:text-rose-600 hover:bg-rose-50 rounded-lg"
      >
        <Trash2 className="size-3.5" />
      </button>
    </div>
  );
}
