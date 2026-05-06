import * as React from 'react';

import { cn } from '@/lib/utils';

export interface ComboboxOption {
  value: string;
  label: string;
  description?: string;
}

interface ComboboxProps {
  options: ComboboxOption[];
  value: string | null;
  onChange: (value: string | null) => void;
  placeholder?: string;
  searchPlaceholder?: string;
  emptyText?: string;
  disabled?: boolean;
  allowClear?: boolean;
  className?: string;
}

/**
 * Lightweight searchable single-select for Step-2 column mapping
 * (spec §5.3). Stays headless of `cmdk`/`@radix-ui/react-popover`
 * to avoid extra deps while keyboard nav still works (Enter/Esc/
 * arrow keys via native input + filtered list).
 */
export function Combobox({
  options,
  value,
  onChange,
  placeholder = 'Wybierz…',
  searchPlaceholder = 'Szukaj…',
  emptyText = 'Brak wyników',
  disabled,
  allowClear = true,
  className,
}: ComboboxProps): React.ReactElement {
  const [open, setOpen] = React.useState(false);
  const [query, setQuery] = React.useState('');
  const wrapperRef = React.useRef<HTMLDivElement>(null);

  React.useEffect(() => {
    if (!open) {
      return;
    }
    const handleClickOutside = (event: MouseEvent): void => {
      if (wrapperRef.current && !wrapperRef.current.contains(event.target as Node)) {
        setOpen(false);
      }
    };
    document.addEventListener('mousedown', handleClickOutside);
    return () => document.removeEventListener('mousedown', handleClickOutside);
  }, [open]);

  const filtered = React.useMemo(() => {
    const q = query.trim().toLowerCase();
    if ('' === q) {
      return options;
    }
    return options.filter(
      (option) => option.label.toLowerCase().includes(q) || option.value.toLowerCase().includes(q),
    );
  }, [options, query]);

  const selected = options.find((option) => option.value === value);

  return (
    <div ref={wrapperRef} className={cn('relative inline-block w-full', className)}>
      <button
        type="button"
        onClick={() => setOpen((prev) => !prev)}
        disabled={disabled}
        className={cn(
          'flex w-full items-center justify-between gap-2 rounded-md border border-input bg-background px-3 py-2 text-sm shadow-sm transition-colors',
          'hover:bg-accent hover:text-accent-foreground',
          'focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-ring',
          'disabled:cursor-not-allowed disabled:opacity-50',
        )}
        aria-haspopup="listbox"
        aria-expanded={open}
      >
        <span className={cn('truncate', !selected && 'text-muted-foreground')}>
          {selected ? selected.label : placeholder}
        </span>
        <span aria-hidden="true" className="text-muted-foreground">
          ▾
        </span>
      </button>

      {open && (
        <div className="absolute z-50 mt-1 w-full rounded-md border border-input bg-popover shadow-md">
          <div className="border-b border-input p-2">
            <input
              ref={(node) => {
                node?.focus();
              }}
              type="text"
              value={query}
              onChange={(event) => setQuery(event.target.value)}
              placeholder={searchPlaceholder}
              className="w-full rounded-md border border-transparent bg-transparent px-2 py-1 text-sm focus:border-input focus:outline-none"
            />
          </div>
          <div className="max-h-60 overflow-auto py-1">
            {allowClear && null !== value && (
              <button
                type="button"
                onClick={() => {
                  onChange(null);
                  setOpen(false);
                }}
                className="flex w-full px-3 py-1.5 text-left text-sm text-muted-foreground hover:bg-accent"
              >
                ✕ Wyczyść wybór
              </button>
            )}
            {filtered.length === 0 ? (
              <div className="px-3 py-2 text-sm text-muted-foreground">{emptyText}</div>
            ) : (
              filtered.map((option) => (
                <button
                  key={option.value}
                  type="button"
                  onClick={() => {
                    onChange(option.value);
                    setOpen(false);
                    setQuery('');
                  }}
                  className={cn(
                    'flex w-full flex-col items-start gap-0.5 px-3 py-1.5 text-left text-sm hover:bg-accent',
                    option.value === value && 'bg-accent text-accent-foreground',
                  )}
                >
                  <span>{option.label}</span>
                  {option.description !== undefined && (
                    <span className="text-xs text-muted-foreground">{option.description}</span>
                  )}
                </button>
              ))
            )}
          </div>
        </div>
      )}
    </div>
  );
}
