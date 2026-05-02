import { useQuery } from '@tanstack/react-query';
import { useTranslation } from 'react-i18next';

import { Card, CardContent } from '@/components/ui/card';
import { jsonFetch } from '@/lib/http';

interface AuditEntry {
  id: string;
  action: string;
  actorId: string | null;
  actorName: string | null;
  occurredAt: string;
  diff: Record<string, unknown>;
}

interface AuditTrailCompactProps {
  resource: 'object_types';
  id: string;
  limit?: number;
}

/**
 * VIEW-01 (#372) — last-N audit entries for the modeling Detail view's
 * "Historia zmian" card. Adapter over `GET /api/<resource>/{id}/audit_log`.
 */
export function AuditTrailCompact({ resource, id, limit = 5 }: AuditTrailCompactProps) {
  const { t, i18n } = useTranslation();
  const url = `/api/${resource}/${id}/audit_log?limit=${limit}`;

  const { data, isLoading } = useQuery<{ entries: AuditEntry[] }>({
    queryKey: [resource, id, 'audit_log', limit],
    queryFn: () => jsonFetch<{ entries: AuditEntry[] }>(url, { accept: 'application/json' }),
    staleTime: 30_000,
  });

  return (
    <Card>
      <CardContent className="space-y-3 p-6">
        <div className="text-[11px] font-medium uppercase tracking-wider text-zinc-500">
          {t('object_types.audit_trail_title', {
            defaultValue: 'Historia zmian (5 ostatnich)',
          })}
        </div>
        {isLoading ? (
          <p className="text-[12px] text-muted-foreground">{t('app.loading')}</p>
        ) : !data || data.entries.length === 0 ? (
          <p className="text-[12px] text-muted-foreground">
            {t('object_types.audit_trail_empty', { defaultValue: 'Brak zmian.' })}
          </p>
        ) : (
          <ul className="space-y-2">
            {data.entries.map((entry) => (
              <li key={entry.id} className="flex items-start justify-between gap-3 text-[12.5px]">
                <div className="min-w-0">
                  <div className="font-medium text-zinc-900">
                    {entry.actorName ??
                      t('object_types.audit_actor_system', { defaultValue: 'system' })}
                  </div>
                  <div className="text-zinc-500">
                    {entry.action} ·{' '}
                    {entry.occurredAt
                      ? new Date(entry.occurredAt).toLocaleString(i18n.language)
                      : '—'}
                  </div>
                </div>
              </li>
            ))}
          </ul>
        )}
      </CardContent>
    </Card>
  );
}
