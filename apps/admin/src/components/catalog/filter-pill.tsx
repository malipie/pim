import { ChevronDown } from 'lucide-react';
import { useTranslation } from 'react-i18next';

import {
  DropdownMenu,
  DropdownMenuContent,
  DropdownMenuItem,
  DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { cn } from '@/lib/utils';

export interface FilterPillOption {
  value: string;
  label: string;
}

interface FilterPillProps {
  label: string;
  value: string | null | undefined;
  options: ReadonlyArray<FilterPillOption>;
  onChange: (next: string | null) => void;
  /** Custom label for the "all" entry; defaults to t('products.toolbar.filter_all'). */
  allLabel?: string;
  /** Optional aria-label override (defaults to `label`). */
  ariaLabel?: string;
}

/**
 * VIEW-05 (#411) — pixel-perfect filter pill matching the prototype
 * (mockup `produkty/list-view.jsx` lines 143–173). When `value` is
 * `null`/`undefined` the pill renders white with the muted label; once a
 * value is picked the pill flips to dark with the chosen value visible
 * inline. Selecting the "all" entry clears the filter.
 */
export function FilterPill({
  label,
  value,
  options,
  onChange,
  allLabel,
  ariaLabel,
}: FilterPillProps) {
  const { t } = useTranslation();
  const resolvedAllLabel =
    allLabel ?? t('products.toolbar.filter_all', { defaultValue: 'wszystkie' });
  const isActive = value !== null && value !== undefined && value !== '';
  const activeLabel = isActive
    ? (options.find((opt) => opt.value === value)?.label ?? value)
    : null;

  return (
    <DropdownMenu>
      <DropdownMenuTrigger asChild>
        <button
          type="button"
          aria-label={ariaLabel ?? label}
          className={cn(
            'h-11 px-3.5 rounded-2xl shadow-sm text-[13px] font-medium inline-flex items-center gap-2 focus:outline-none focus-visible:ring-2 focus-visible:ring-zinc-900 transition',
            isActive ? 'bg-zinc-900 text-white' : 'bg-white text-zinc-600 hover:bg-zinc-50',
          )}
        >
          <span className={isActive ? 'text-white/70' : 'text-zinc-400'}>{label}</span>
          {isActive && activeLabel !== null ? <span>{activeLabel}</span> : null}
          <ChevronDown
            className={cn('size-3.5', isActive ? 'text-white/60' : 'text-zinc-400')}
            aria-hidden="true"
          />
        </button>
      </DropdownMenuTrigger>
      <DropdownMenuContent align="start" className="min-w-[180px]">
        <DropdownMenuItem
          onSelect={() => {
            onChange(null);
          }}
          className={cn(!isActive && 'font-semibold')}
        >
          {resolvedAllLabel}
        </DropdownMenuItem>
        {options.map((opt) => (
          <DropdownMenuItem
            key={opt.value}
            onSelect={() => {
              onChange(opt.value);
            }}
            className={cn(value === opt.value && 'font-semibold')}
          >
            {opt.label}
          </DropdownMenuItem>
        ))}
      </DropdownMenuContent>
    </DropdownMenu>
  );
}
