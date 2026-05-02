/**
 * MockBadge — shared "this surface is mocked, backend missing" indicator for UI-03b.
 *
 * Three variants cover all current use cases:
 *
 *   <MockBadge variant="inline" />                       // pill obok tekstu w przycisku
 *   <MockBadge variant="corner" tooltip="..." />         // absolute na rogu karty
 *   <MockBadge variant="overlay" tooltip="...">          // pełen overlay na disabled section
 *     <DisabledThing />
 *   </MockBadge>
 *
 * Always wrap the page tree in <TooltipProvider> once at app root for tooltips to work
 * (see AppLayout.tsx).
 */

import type { ReactNode } from 'react';
import { useTranslation } from 'react-i18next';

import { Tooltip, TooltipContent, TooltipTrigger } from '@/components/ui/tooltip';
import { cn } from '@/lib/utils';

type MockVariant = 'inline' | 'corner' | 'overlay';

interface MockBadgeProps {
  variant?: MockVariant;
  /** Tekst tooltipa. Default: t('mock.requires_implementation'). */
  tooltip?: string;
  /** Numer ticketu BE/FE odblokowującego ten element (np. "#TBD" lub "#412"). */
  ticket?: string;
  /** Treść owinięta przez overlay variant (ignorowane dla inline/corner). */
  children?: ReactNode;
  className?: string;
}

const baseBadgeClass = cn(
  'inline-flex items-center gap-1 rounded-full border border-amber-200',
  'bg-amber-50 px-2 py-0.5 text-[10px] font-semibold uppercase tracking-wide text-amber-900',
);

export function MockBadge({
  variant = 'inline',
  tooltip,
  ticket,
  children,
  className,
}: MockBadgeProps) {
  const { t } = useTranslation();
  const tooltipText =
    tooltip ?? t('mock.requires_implementation', { defaultValue: 'MOCK · Wymaga oprogramowania' });
  const labelText = t('mock.label', { defaultValue: 'MOCK' });

  const tooltipContent = (
    <span className="block">
      {tooltipText}
      {ticket ? <span className="ml-1 opacity-70">{ticket}</span> : null}
    </span>
  );

  // Visible "MOCK" text is the accessible name of the badge; full description
  // is provided by Radix Tooltip via aria-describedby on the trigger.
  if (variant === 'inline') {
    return (
      <Tooltip>
        <TooltipTrigger asChild>
          <span className={cn(baseBadgeClass, className)}>{labelText}</span>
        </TooltipTrigger>
        <TooltipContent>{tooltipContent}</TooltipContent>
      </Tooltip>
    );
  }

  if (variant === 'corner') {
    return (
      <Tooltip>
        <TooltipTrigger asChild>
          <span className={cn(baseBadgeClass, 'absolute right-2 top-2 z-10 shadow-sm', className)}>
            {labelText}
          </span>
        </TooltipTrigger>
        <TooltipContent>{tooltipContent}</TooltipContent>
      </Tooltip>
    );
  }

  // overlay — pointer-events-none on the wrapper means the tooltip is
  // attached to the inner badge span, which is interactive (pointer-events-auto).
  return (
    <div className={cn('relative', className)}>
      {children}
      <div
        className={cn(
          'pointer-events-none absolute inset-0 z-10 flex items-start justify-end p-2',
          'rounded-[inherit] bg-amber-50/30 ring-1 ring-inset ring-amber-200',
        )}
      >
        <Tooltip>
          <TooltipTrigger asChild>
            <span className={cn(baseBadgeClass, 'pointer-events-auto shadow-sm')}>{labelText}</span>
          </TooltipTrigger>
          <TooltipContent>{tooltipContent}</TooltipContent>
        </Tooltip>
      </div>
    </div>
  );
}
