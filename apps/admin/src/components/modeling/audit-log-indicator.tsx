import { History } from 'lucide-react';
import { useTranslation } from 'react-i18next';

import { MockBadge } from '@/components/ui/mock-badge';

/**
 * UI-03b placeholder for the per-entity audit log button. The endpoint
 * (`GET /api/{resource}/{id}/audit-log`) is not implemented yet — this
 * surfaces the missing capability with a disabled button + MockBadge so
 * the operator can see the intent in the UI.
 */
export function AuditLogIndicator() {
  const { t } = useTranslation();
  const tooltip = t('modeling.audit_log.mock_tooltip', {
    defaultValue: 'MOCK · Audit log wymaga endpointu BE',
  });

  return (
    <span className="inline-flex items-center gap-1.5">
      <button
        type="button"
        disabled
        aria-disabled="true"
        className="inline-flex cursor-not-allowed items-center gap-1.5 rounded-md border border-line px-2 py-1 text-[12px] text-muted-foreground"
      >
        <History className="size-3.5" />
        {t('modeling.audit_log.label', { defaultValue: 'Audit log' })}
      </button>
      <MockBadge tooltip={tooltip} />
    </span>
  );
}
