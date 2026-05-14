import { FolderPlus, Plus, X } from 'lucide-react';
import { useTranslation } from 'react-i18next';

import { QueryConditionRow } from '@/components/catalog/query-condition-row';
import {
  type FilterCondition,
  type FilterDsl,
  type FilterGroup,
  isFilterGroup,
} from '@/lib/filters/filter-dsl';
import { cn } from '@/lib/utils';

/**
 * VIEW-09b (#540) — recursive AND/OR group editor.
 *
 * Hierarchy invariants (PRD §5.3 + §13.2):
 *   - root node is always a group (even single condition → group of 1);
 *   - max depth 3 — the `+ Dodaj grupę` button hides past that;
 *   - removing the last child of a non-root group prunes the group;
 *   - toggle AND ↔ OR rebinds the entire group, no value loss.
 *
 * Renders identically in the BE Meilisearch filter expression after a
 * round-trip through `FilterDslResolver::toMeilisearchFilter()`.
 */

type PanelAttr = {
  code: string;
  name: string;
  type: string;
};

interface QueryGroupEditorProps {
  group: FilterGroup;
  attrs: ReadonlyArray<PanelAttr>;
  onChange: (next: FilterGroup) => void;
  onRemove?: () => void;
  depth?: number;
  maxDepth?: number;
}

export function QueryGroupEditor({
  group,
  attrs,
  onChange,
  onRemove,
  depth = 0,
  maxDepth = 3,
}: QueryGroupEditorProps) {
  const { t } = useTranslation();
  const isRoot = depth === 0;
  const canNestDeeper = depth < maxDepth;

  const toggleOperator = (): void => {
    onChange({ ...group, operator: group.operator === 'AND' ? 'OR' : 'AND' });
  };

  const updateChild = (index: number, next: FilterDsl): void => {
    const conditions = [...group.conditions];
    conditions[index] = next;
    onChange({ ...group, conditions });
  };

  const removeChild = (index: number): void => {
    const conditions = group.conditions.filter((_, i) => i !== index);
    onChange({ ...group, conditions });
  };

  const addCondition = (): void => {
    const defaultAttr = attrs[0];
    if (!defaultAttr) return;
    const newCondition: FilterCondition = {
      attr: defaultAttr.code,
      op: '=',
      value: '',
    };
    onChange({ ...group, conditions: [...group.conditions, newCondition] });
  };

  const addGroup = (): void => {
    const defaultAttr = attrs[0];
    if (!defaultAttr) return;
    const seed: FilterCondition = { attr: defaultAttr.code, op: '=', value: '' };
    const newGroup: FilterGroup = { operator: 'OR', conditions: [seed] };
    onChange({ ...group, conditions: [...group.conditions, newGroup] });
  };

  const bgByDepth =
    depth === 0 ? 'bg-zinc-50/70' : depth === 1 ? 'bg-zinc-100/70' : 'bg-zinc-200/60';

  return (
    <section
      aria-label={t('products.advanced_filter.query_mode.group_label', {
        defaultValue: `Grupa ${group.operator}`,
        operator: group.operator,
      })}
      className={cn('rounded-2xl border border-zinc-200 p-3', bgByDepth)}
    >
      <div className="flex items-center gap-2 mb-2">
        <span className="text-[10.5px] uppercase tracking-wider font-semibold text-zinc-500">
          {t('products.advanced_filter.query_mode.group_label', {
            defaultValue: `Grupa ${group.operator}`,
            operator: group.operator,
          })}
        </span>
        <button
          type="button"
          onClick={toggleOperator}
          aria-pressed={group.operator === 'AND'}
          className="h-6 px-2 rounded-md bg-white border border-zinc-200 text-[11px] font-mono font-semibold text-zinc-700 hover:bg-zinc-100"
        >
          {group.operator}
        </button>
        {!isRoot && onRemove && (
          <button
            type="button"
            onClick={onRemove}
            aria-label={t('products.advanced_filter.query_mode.remove_group', {
              defaultValue: 'Usuń grupę',
            })}
            className="ml-auto h-6 w-6 grid place-items-center text-zinc-400 hover:text-rose-600 hover:bg-rose-50 rounded"
          >
            <X className="size-3.5" />
          </button>
        )}
      </div>

      <div className="space-y-2">
        {group.conditions.map((child, idx) => {
          const childKey = `child-${idx}`;
          if (isFilterGroup(child)) {
            return (
              <QueryGroupEditor
                key={childKey}
                group={child}
                attrs={attrs}
                onChange={(next) => updateChild(idx, next)}
                onRemove={() => removeChild(idx)}
                depth={depth + 1}
                maxDepth={maxDepth}
              />
            );
          }
          return (
            <QueryConditionRow
              key={childKey}
              condition={child}
              attrs={attrs}
              onChange={(next) => updateChild(idx, next)}
              onRemove={() => removeChild(idx)}
            />
          );
        })}
      </div>

      <div className="mt-3 flex flex-wrap gap-2">
        <button
          type="button"
          onClick={addCondition}
          className="text-[12px] text-zinc-500 hover:text-zinc-900 inline-flex items-center gap-1.5 h-7 px-2 rounded-lg hover:bg-zinc-100"
        >
          <Plus className="size-3.5" />
          {t('products.advanced_filter.query_mode.add_condition', {
            defaultValue: 'Dodaj warunek',
          })}
        </button>
        {canNestDeeper && (
          <button
            type="button"
            onClick={addGroup}
            className="text-[12px] text-zinc-500 hover:text-zinc-900 inline-flex items-center gap-1.5 h-7 px-2 rounded-lg hover:bg-zinc-100"
          >
            <FolderPlus className="size-3.5" />
            {t('products.advanced_filter.query_mode.add_group', { defaultValue: 'Dodaj grupę' })}
          </button>
        )}
        {!canNestDeeper && (
          <span className="text-[10.5px] text-zinc-400">
            {t('products.advanced_filter.query_mode.depth_limit_reached', {
              defaultValue: 'Limit zagnieżdżenia 3 poziomy',
            })}
          </span>
        )}
      </div>
    </section>
  );
}
