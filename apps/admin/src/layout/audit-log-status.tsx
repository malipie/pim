import { useTranslation } from 'react-i18next';

import { MockBadge } from '@/components/ui/mock-badge';
import { Tooltip, TooltipContent, TooltipTrigger } from '@/components/ui/tooltip';

/**
 * UI-03c — extended audit log status pill in the topbar (right side).
 *
 * Replaces the icon-only indicator from #363 with the handoff-style pill:
 * green dot + "Audit log: aktywny · ostatnia zmiana 14 min temu". Both
 * the status and the timestamp are MOCK until the BE endpoint
 * `GET /api/audit-log?limit=1` ships.
 */
export function AuditLogStatus() {
  const { t } = useTranslation();
  const tooltip = t('topbar.audit_log_tooltip', {
    defaultValue: 'MOCK · Audit log wymaga endpointu BE',
  });

  return (
    <Tooltip>
      <TooltipTrigger asChild>
        <span className="inline-flex items-center gap-1.5 rounded-md border border-line bg-surface px-2 py-1 text-[11px] text-muted-foreground">
          <span className="size-1.5 rounded-full bg-accent-emerald" aria-hidden />
          <span className="font-medium">
            {t('topbar.audit_log_label', { defaultValue: 'Audit log: aktywny' })}
          </span>
          <span aria-hidden>·</span>
          <span>
            {t('topbar.audit_log_last_change', {
              defaultValue: 'ostatnia zmiana 14 min temu',
            })}
          </span>
          <MockBadge tooltip={tooltip} />
        </span>
      </TooltipTrigger>
      <TooltipContent>{tooltip}</TooltipContent>
    </Tooltip>
  );
}
