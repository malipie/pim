import { Plug } from 'lucide-react';
import { useTranslation } from 'react-i18next';

import { cn } from '@/lib/utils';

import {
  AuthBadge,
  type AuthType,
  type ConnectionStatus,
  ConnStatusPill,
} from '../components/primitives';

export interface ConnectionRow {
  id: string;
  code: string;
  name: string;
  baseUrl: string;
  authType: AuthType;
  rateLimitHint: number | null;
  status: ConnectionStatus;
  lastHealthCheckAt: string | null;
  createdAt: string;
  updatedAt: string;
}

/**
 * APIC-P1-07 — a connection card on the consumer hub. Shows the Connection's own
 * fields (name, base URL, status, auth, last health check, rate hint). The
 * sync-derived fields from the prototype (direction, schedule, mapping
 * coverage, cursor, last sync) arrive with SyncBinding/SyncRun in M3.
 */
export function ConnectionCard({ connection }: { connection: ConnectionRow }) {
  const { t, i18n } = useTranslation();

  const checkedAt =
    connection.lastHealthCheckAt !== null
      ? new Date(connection.lastHealthCheckAt).toLocaleString(i18n.language)
      : t('api_configurator.hub.never_checked');

  const iconCls =
    connection.status === 'error'
      ? 'bg-rose-50 text-rose-600'
      : connection.status === 'paused'
        ? 'bg-zinc-100 text-zinc-400'
        : 'bg-zinc-900 text-white';

  return (
    <div className="soft-shadow rounded-2xl border border-zinc-200 bg-white p-5">
      <div className="flex items-start gap-3">
        <div className={cn('grid h-11 w-11 shrink-0 place-items-center rounded-xl', iconCls)}>
          <Plug className="size-5" aria-hidden />
        </div>
        <div className="min-w-0 flex-1">
          <div className="flex items-center gap-2">
            <span className="truncate text-[15px] font-semibold tracking-tight">
              {connection.name}
            </span>
            <span className="font-mono text-[11px] text-zinc-400">{connection.code}</span>
          </div>
          <div className="mt-1 truncate font-mono text-[11.5px] text-zinc-500">
            {connection.baseUrl}
          </div>
        </div>
        <ConnStatusPill
          status={connection.status}
          label={t(`api_configurator.hub.status.${connection.status}`)}
        />
      </div>

      <div className="mt-4 grid grid-cols-2 gap-x-5 gap-y-3 text-[12px]">
        <div>
          <div className="text-[10px] uppercase tracking-wider text-zinc-400">
            {t('api_configurator.hub.auth')}
          </div>
          <div className="mt-1">
            <AuthBadge type={connection.authType} />
          </div>
        </div>
        <div>
          <div className="text-[10px] uppercase tracking-wider text-zinc-400">
            {t('api_configurator.hub.last_check')}
          </div>
          <div className="mt-1 text-zinc-700">{checkedAt}</div>
        </div>
      </div>
    </div>
  );
}
