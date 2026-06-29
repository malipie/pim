import { Search } from 'lucide-react';
import { useMemo, useState } from 'react';
import { useTranslation } from 'react-i18next';

import { type BuilderAttribute, labelText } from './builder-helpers';

/**
 * APIC-P4-07 — the profile builder's attribute picker: searchable checkbox list
 * of the tenant's attribute pool (from the P4-04 builder_options endpoint) with
 * all/none + a selected counter.
 */
export function AttributePicker({
  attributes,
  selected,
  onToggle,
  onSelectAll,
  onSelectNone,
}: {
  attributes: BuilderAttribute[];
  selected: Set<string>;
  onToggle: (code: string) => void;
  onSelectAll: () => void;
  onSelectNone: () => void;
}) {
  const { t } = useTranslation();
  const [q, setQ] = useState('');

  const shown = useMemo(() => {
    const needle = q.trim().toLowerCase();
    if (needle === '') {
      return attributes;
    }
    return attributes.filter(
      (a) =>
        a.code.toLowerCase().includes(needle) ||
        labelText(a.label, a.code).toLowerCase().includes(needle),
    );
  }, [attributes, q]);

  return (
    <section className="soft-shadow rounded-2xl border border-zinc-200 bg-white p-5">
      <div className="mb-3 flex items-center gap-3">
        <div className="text-[11px] font-medium uppercase tracking-wider text-zinc-500">
          {t('api_configurator.builder.attributes.title')}
        </div>
        <span className="rounded bg-zinc-100 px-1.5 py-0.5 text-[10.5px] font-medium tabular-nums text-zinc-600">
          {selected.size} / {attributes.length}
        </span>
        <div className="flex-1" />
        <button
          type="button"
          onClick={onSelectAll}
          className="text-[11.5px] text-zinc-500 hover:text-zinc-900"
        >
          {t('api_configurator.builder.attributes.all')}
        </button>
        <button
          type="button"
          onClick={onSelectNone}
          className="text-[11.5px] text-zinc-500 hover:text-zinc-900"
        >
          {t('api_configurator.builder.attributes.none')}
        </button>
      </div>

      <div className="mb-2 flex items-center gap-2 rounded-xl border border-zinc-200 px-3">
        <Search className="size-4 text-zinc-400" aria-hidden="true" />
        <input
          value={q}
          onChange={(e) => setQ(e.target.value)}
          placeholder={t('api_configurator.builder.attributes.search')}
          aria-label={t('api_configurator.builder.attributes.search')}
          className="h-9 flex-1 bg-transparent text-[13px] outline-none placeholder:text-zinc-400"
        />
      </div>

      <div className="max-h-[55vh] divide-y divide-zinc-50 overflow-y-auto">
        {shown.length === 0 ? (
          <div className="px-1 py-4 text-[12px] text-zinc-500">
            {t('api_configurator.builder.attributes.empty')}
          </div>
        ) : (
          shown.map((a) => (
            <label
              key={a.code}
              className="flex cursor-pointer items-center gap-3 px-1 py-2.5 hover:bg-zinc-50/70"
            >
              <input
                type="checkbox"
                checked={selected.has(a.code)}
                onChange={() => onToggle(a.code)}
                className="size-4 rounded"
              />
              <span className="flex-1 text-[12.5px] text-zinc-800">
                {labelText(a.label, a.code)}
              </span>
              <span className="font-mono text-[10.5px] text-zinc-500">{a.code}</span>
              <span className="rounded bg-zinc-100 px-1.5 py-0.5 text-[10px] font-medium text-zinc-600">
                {a.type}
              </span>
            </label>
          ))
        )}
      </div>
    </section>
  );
}
