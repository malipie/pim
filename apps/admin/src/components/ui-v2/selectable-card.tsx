import {
  Children,
  cloneElement,
  isValidElement,
  type KeyboardEvent,
  type ReactElement,
  type ReactNode,
  useRef,
} from 'react';
import { useTranslation } from 'react-i18next';

import { Tooltip, TooltipContent, TooltipProvider, TooltipTrigger } from '@/components/ui/tooltip';
import { cn } from '@/lib/utils';

interface SelectableCardProps {
  /** Icon shown in the rounded-xl square (navy when selected). */
  icon?: ReactNode;
  /** Already-translated title. */
  title: string;
  /** Already-translated 13px description. */
  description?: string;
  selected?: boolean;
  /** Disabled card renders a "soon" badge + tooltip and cannot be chosen. */
  disabled?: boolean;
  onSelect?: () => void;
  className?: string;
  /** Managed by SelectableCardGroup — do not set manually. */
  tabIndex?: number;
}

/**
 * Selection tile (wizard step 1 entity / step 2 format). Use inside
 * `<SelectableCardGroup>` which provides radiogroup keyboard semantics.
 */
export function SelectableCard({
  icon,
  title,
  description,
  selected = false,
  disabled = false,
  onSelect,
  className,
  tabIndex,
}: SelectableCardProps) {
  const { t } = useTranslation();
  const card = (
    // biome-ignore lint/a11y/useSemanticElements: rich card content (icon, badges, description) cannot live inside <input type="radio">; button+role=radio matches the Radix RadioGroup pattern
    <button
      type="button"
      role="radio"
      aria-checked={selected}
      aria-disabled={disabled || undefined}
      tabIndex={tabIndex ?? (selected ? 0 : -1)}
      onClick={() => {
        if (!disabled) {
          onSelect?.();
        }
      }}
      className={cn(
        'focus-ring rounded-2xl border bg-surface p-5 text-left transition',
        selected && 'soft-shadow border-zinc-900 ring-1 ring-zinc-900',
        !selected && !disabled && 'border-zinc-200 hover:border-zinc-400',
        disabled && 'cursor-not-allowed border-zinc-200 opacity-60',
        className,
      )}
    >
      {icon && (
        <span
          aria-hidden="true"
          className={cn(
            'grid h-12 w-12 place-items-center rounded-xl transition',
            selected ? 'bg-zinc-900 text-white' : 'bg-zinc-100 text-zinc-600',
          )}
        >
          {icon}
        </span>
      )}
      <span
        className={cn(
          'flex items-center gap-2 text-[15px] font-semibold tracking-tight',
          icon && 'mt-4',
        )}
      >
        {title}
        {selected && (
          <span className="rounded bg-orange-500 px-1.5 py-0.5 text-[9.5px] font-semibold tracking-wider text-zinc-900 uppercase">
            {t('ui_v2.selected')}
          </span>
        )}
        {disabled && (
          <span className="rounded bg-zinc-100 px-1.5 py-0.5 text-[9.5px] font-semibold tracking-wider text-zinc-600 uppercase">
            {t('ui_v2.soon')}
          </span>
        )}
      </span>
      {description && (
        <span className="mt-1.5 block text-[12.5px] leading-relaxed text-zinc-500">
          {description}
        </span>
      )}
    </button>
  );
  if (!disabled) {
    return card;
  }
  return (
    <TooltipProvider>
      <Tooltip>
        <TooltipTrigger asChild>{card}</TooltipTrigger>
        <TooltipContent>{t('ui_v2.soon_tooltip')}</TooltipContent>
      </Tooltip>
    </TooltipProvider>
  );
}

interface SelectableCardGroupProps {
  /** Accessible name of the radiogroup. */
  ariaLabel: string;
  children: ReactNode;
  className?: string;
}

/**
 * Radiogroup wrapper for `<SelectableCard>` tiles: arrow keys move focus
 * and select the focused card (standard radio-group keyboard model).
 */
export function SelectableCardGroup({ ariaLabel, children, className }: SelectableCardGroupProps) {
  const containerRef = useRef<HTMLDivElement>(null);

  const handleKeyDown = (event: KeyboardEvent<HTMLDivElement>) => {
    if (!['ArrowRight', 'ArrowDown', 'ArrowLeft', 'ArrowUp'].includes(event.key)) {
      return;
    }
    const container = containerRef.current;
    if (!container) {
      return;
    }
    const radios = Array.from(
      container.querySelectorAll<HTMLButtonElement>('[role="radio"]:not([aria-disabled="true"])'),
    );
    if (radios.length === 0) {
      return;
    }
    event.preventDefault();
    const forward = event.key === 'ArrowRight' || event.key === 'ArrowDown';
    const currentIndex = radios.indexOf(document.activeElement as HTMLButtonElement);
    const base =
      currentIndex === -1
        ? radios.findIndex((r) => r.getAttribute('aria-checked') === 'true')
        : currentIndex;
    const next = ((base === -1 ? 0 : base) + (forward ? 1 : -1) + radios.length) % radios.length;
    radios[next]?.focus();
    radios[next]?.click();
  };

  // The selected card is the only tab stop; when none is selected the first
  // enabled card takes it so keyboard users can enter the group.
  let firstEnabledAssigned = false;
  const anySelected = Children.toArray(children).some(
    (child) => isValidElement<SelectableCardProps>(child) && child.props.selected,
  );
  const items = Children.map(children, (child) => {
    if (!isValidElement<SelectableCardProps>(child)) {
      return child;
    }
    let tabIndex = -1;
    if (child.props.selected) {
      tabIndex = 0;
    } else if (!anySelected && !child.props.disabled && !firstEnabledAssigned) {
      firstEnabledAssigned = true;
      tabIndex = 0;
    }
    return cloneElement(child as ReactElement<SelectableCardProps>, { tabIndex });
  });

  return (
    <div
      ref={containerRef}
      role="radiogroup"
      aria-label={ariaLabel}
      onKeyDown={handleKeyDown}
      className={className}
    >
      {items}
    </div>
  );
}
