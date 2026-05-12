import { Check, X } from 'lucide-react';
import * as React from 'react';

import { cn } from '@/lib/utils';

export interface MultiSelectOption {
  value: string;
  label: string;
  color?: string | null;
  deprecated?: boolean;
}

interface MultiSelectProps {
  options: MultiSelectOption[];
  value: string[];
  onChange: (next: string[]) => void;
  placeholder?: string;
  searchPlaceholder?: string;
  emptyText?: string;
  disabled?: boolean;
  className?: string;
}

/**
 * Lightweight popover multi-select. Renders selected entries as removable
 * chips inside the trigger and a search-filtered checkbox list in the
 * popover. Used by the product detail page for `multiselect` attributes
 * (Tagi etc.) where operators were previously typing option codes by hand.
 *
 * Stays headless of `cmdk` / `@radix-ui/react-popover` to mirror
 * `Combobox` and avoid extra deps.
 */
export function MultiSelect({
  options,
  value,
  onChange,
  placeholder = 'Wybierz…',
  searchPlaceholder = 'Szukaj…',
  emptyText = 'Brak wyników',
  disabled,
  className,
}: MultiSelectProps): React.ReactElement {
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

  const selectedSet = React.useMemo(() => new Set(value), [value]);
  const selected = options.filter((option) => selectedSet.has(option.value));

  const toggle = (optionValue: string): void => {
    if (selectedSet.has(optionValue)) {
      onChange(value.filter((v) => v !== optionValue));
    } else {
      onChange([...value, optionValue]);
    }
  };

  const removeChip = (optionValue: string, event: React.MouseEvent): void => {
    event.stopPropagation();
    onChange(value.filter((v) => v !== optionValue));
  };

  // The trigger has to host real <button> chips for "remove" actions, so
  // it cannot itself be a <button> (no nested interactives). Use a div
  // with role=button + keyboard handlers so a11y is preserved.
  const handleTriggerKey = (event: React.KeyboardEvent<HTMLDivElement>): void => {
    if (disabled) return;
    if (event.key === 'Enter' || event.key === ' ') {
      event.preventDefault();
      setOpen((prev) => !prev);
    } else if (event.key === 'Escape' && open) {
      event.preventDefault();
      setOpen(false);
    }
  };

  return (
    <div ref={wrapperRef} className={cn('relative inline-block w-full', className)}>
      {/* biome-ignore lint/a11y/useSemanticElements: chips inside contain real <button>s for "remove", so the trigger cannot itself be a <button>. */}
      <div
        role="button"
        tabIndex={disabled ? -1 : 0}
        onClick={() => !disabled && setOpen((prev) => !prev)}
        onKeyDown={handleTriggerKey}
        aria-haspopup="listbox"
        aria-expanded={open}
        aria-disabled={disabled}
        className={cn(
          'flex min-h-9 w-full flex-wrap items-center gap-1 rounded-md border border-input bg-background px-2 py-1.5 text-sm shadow-sm transition-colors',
          'hover:bg-accent/40',
          'focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-ring',
          disabled && 'cursor-not-allowed opacity-50',
        )}
      >
        {selected.length === 0 ? (
          <span className="px-1 text-muted-foreground">{placeholder}</span>
        ) : (
          selected.map((option) => (
            <span
              key={option.value}
              className="inline-flex items-center gap-1 rounded-full bg-muted px-2 py-0.5 text-xs"
            >
              {option.color ? (
                <span
                  aria-hidden
                  className="size-2 rounded-full"
                  style={{ backgroundColor: option.color }}
                />
              ) : null}
              <span>{option.label}</span>
              {!disabled && (
                <button
                  type="button"
                  tabIndex={-1}
                  aria-label={`Usuń ${option.label}`}
                  onClick={(event) => removeChip(option.value, event)}
                  className="ml-0.5 inline-flex size-3.5 cursor-pointer items-center justify-center rounded-full text-muted-foreground hover:bg-foreground/10 hover:text-foreground"
                >
                  <X className="size-3" />
                </button>
              )}
            </span>
          ))
        )}
        <span aria-hidden className="ml-auto text-muted-foreground">
          ▾
        </span>
      </div>

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
            {filtered.length === 0 ? (
              <div className="px-3 py-2 text-sm text-muted-foreground">{emptyText}</div>
            ) : (
              filtered.map((option) => {
                const isSelected = selectedSet.has(option.value);
                return (
                  <button
                    key={option.value}
                    type="button"
                    onClick={() => toggle(option.value)}
                    className={cn(
                      'flex w-full items-center gap-2 px-3 py-1.5 text-left text-sm hover:bg-accent',
                      option.deprecated && 'text-muted-foreground line-through',
                    )}
                    aria-pressed={isSelected}
                  >
                    <span
                      className={cn(
                        'flex size-4 items-center justify-center rounded border',
                        isSelected
                          ? 'border-primary bg-primary text-primary-foreground'
                          : 'border-input',
                      )}
                      aria-hidden
                    >
                      {isSelected ? <Check className="size-3" /> : null}
                    </span>
                    {option.color ? (
                      <span
                        aria-hidden
                        className="size-2.5 rounded-full"
                        style={{ backgroundColor: option.color }}
                      />
                    ) : null}
                    <span className="flex-1">{option.label}</span>
                  </button>
                );
              })
            )}
          </div>
        </div>
      )}
    </div>
  );
}
