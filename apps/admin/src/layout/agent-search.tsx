import { Search } from 'lucide-react';
import { useTranslation } from 'react-i18next';

import { MockBadge } from '@/components/ui/mock-badge';
import { Tooltip, TooltipContent, TooltipTrigger } from '@/components/ui/tooltip';

/**
 * Topbar agent search placeholder for UI-03b. Real ⌘K agent integration
 * lives in epic 0.7 (Faza 2). This component renders a disabled input
 * with a MOCK badge and tooltip linking back to the gating epic.
 */
export function AgentSearch() {
  const { t } = useTranslation();
  const placeholder = t('topbar.search_agent_placeholder', {
    defaultValue: 'Zapytaj agenta lub szukaj...',
  });
  const tooltipText = t('topbar.agent_mock_tooltip', {
    defaultValue: 'MOCK · Agent layer wymaga oprogramowania (epik 0.7, Faza 2)',
  });

  return (
    <Tooltip>
      <TooltipTrigger asChild>
        <div className="relative w-full max-w-md">
          <Search
            className="pointer-events-none absolute left-2.5 top-1/2 size-3.5 -translate-y-1/2 text-muted-foreground"
            aria-hidden
          />
          <input
            type="search"
            disabled
            aria-label={placeholder}
            placeholder={placeholder}
            className="h-9 w-full cursor-not-allowed rounded-md border bg-muted/40 pl-8 pr-20 text-sm text-muted-foreground placeholder:text-muted-foreground/70 focus:outline-none"
          />
          <span className="pointer-events-none absolute right-2 top-1/2 flex -translate-y-1/2 items-center gap-1.5">
            <kbd className="rounded border bg-background px-1.5 py-0.5 text-[10px] font-medium text-muted-foreground">
              ⌘K
            </kbd>
            <MockBadge tooltip={tooltipText} />
          </span>
        </div>
      </TooltipTrigger>
      <TooltipContent>{tooltipText}</TooltipContent>
    </Tooltip>
  );
}
