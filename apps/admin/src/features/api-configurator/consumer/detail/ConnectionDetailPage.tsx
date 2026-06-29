import { useApiUrl, useCustomMutation, useList, useOne } from '@refinedev/core';
import { ArrowLeft, Play, Wifi } from 'lucide-react';
import { useTranslation } from 'react-i18next';
import { useNavigate, useParams, useSearchParams } from 'react-router';

import { Button } from '@/components/ui/button';

import {
  AuthBadge,
  type AuthType,
  type ConnectionStatus,
  ConnStatusPill,
  DirectionBadge,
} from '../../components/primitives';
import { MappingScreen } from '../mapping/MappingScreen';
import { SyncConfigScreen } from '../sync/SyncConfigScreen';
import { DetailEndpoints } from './DetailEndpoints';
import { DetailHistory } from './DetailHistory';
import { DetailOverview } from './DetailOverview';
import { DETAIL_TABS, type DetailTab, type SyncBindingRow, toDetailTab } from './types';

interface ConnectionRow {
  id: string;
  code: string;
  name: string;
  baseUrl: string;
  authType: AuthType;
  rateLimitHint: number | null;
  status: ConnectionStatus;
}

const HUB = '/integrations/api-configurator/connections';

/**
 * APIC-P3-12 — the full-screen connection detail with five deep-linkable tabs
 * (`?tab=`): Overview, Endpoints, Mapping (embeds P2-09), Sync (embeds P3-11),
 * History (P4-01). The header carries identity + status/direction/auth and the
 * Test (P1-05) + Sync-now (P3-10 run) actions.
 */
export function ConnectionDetailPage() {
  const { t } = useTranslation();
  const navigate = useNavigate();
  const apiUrl = useApiUrl();
  const { id: connectionId = '' } = useParams();
  const [searchParams, setSearchParams] = useSearchParams();
  const tab = toDetailTab(searchParams.get('tab'));

  const connectionQuery = useOne<ConnectionRow>({
    resource: 'connections',
    id: connectionId,
    queryOptions: { enabled: connectionId !== '' },
  });
  const bindingsQuery = useList<SyncBindingRow>({
    resource: 'sync_bindings',
    filters: [{ field: 'connection', operator: 'eq', value: connectionId }],
    pagination: { mode: 'off' },
    queryOptions: { enabled: connectionId !== '' },
  });

  const connection = connectionQuery.result ?? null;
  const binding = bindingsQuery.result.data[0] ?? null;

  const { mutate: test } = useCustomMutation();
  const { mutate: runNow } = useCustomMutation();

  function selectTab(next: DetailTab): void {
    setSearchParams(next === 'overview' ? {} : { tab: next });
  }

  function triggerTest(): void {
    if (connectionId === '') {
      return;
    }
    test({ url: `${apiUrl}/connections/${connectionId}/test`, method: 'post', values: {} });
  }

  function triggerSync(): void {
    if (binding === null) {
      return;
    }
    runNow({ url: `${apiUrl}/sync_bindings/${binding.id}/run`, method: 'post', values: {} });
  }

  return (
    <div className="space-y-5">
      <div className="flex items-center gap-3">
        <Button
          variant="outline"
          size="icon"
          onClick={() => navigate(HUB)}
          aria-label={t('api_configurator.detail.back')}
        >
          <ArrowLeft className="size-4" aria-hidden="true" />
        </Button>
        <div className="min-w-0 flex-1">
          <h1 className="font-display text-[22px] font-semibold tracking-tight">
            {connection?.name ?? t('api_configurator.detail.title')}
          </h1>
          <p className="truncate font-mono text-[12px] text-zinc-500">
            {connection?.baseUrl ?? ''}
          </p>
        </div>
        <Button type="button" variant="outline" onClick={triggerTest}>
          <Wifi className="mr-1 size-4" aria-hidden="true" />
          {t('api_configurator.detail.test')}
        </Button>
        <Button type="button" onClick={triggerSync} disabled={binding === null}>
          <Play className="mr-1 size-4" aria-hidden="true" />
          {t('api_configurator.detail.sync_now')}
        </Button>
      </div>

      {connection !== null ? (
        <div className="flex flex-wrap items-center gap-2.5">
          <ConnStatusPill
            status={connection.status}
            label={t(`api_configurator.hub.status.${connection.status}`)}
          />
          {binding !== null ? (
            <DirectionBadge dir={binding.direction} label={binding.direction} />
          ) : null}
          <AuthBadge type={connection.authType} />
        </div>
      ) : null}

      <div
        role="tablist"
        aria-label={t('api_configurator.detail.tabs_label')}
        className="flex flex-wrap gap-1 border-b border-zinc-200"
      >
        {DETAIL_TABS.map((id) => {
          const active = tab === id;
          return (
            <button
              key={id}
              type="button"
              role="tab"
              aria-selected={active}
              onClick={() => selectTab(id)}
              className={`-mb-px border-b-2 px-3 py-2 text-[13px] font-medium transition ${active ? 'border-zinc-900 text-zinc-900' : 'border-transparent text-zinc-500 hover:text-zinc-800'}`}
            >
              {t(`api_configurator.detail.tab.${id}`)}
            </button>
          );
        })}
      </div>

      <div>
        {tab === 'overview' ? (
          <DetailOverview
            connectionId={connectionId}
            binding={binding}
            rateLimitHint={connection?.rateLimitHint ?? null}
            onOpenTab={selectTab}
          />
        ) : null}
        {tab === 'endpoints' ? <DetailEndpoints connectionId={connectionId} /> : null}
        {tab === 'mapping' ? <MappingScreen embedded /> : null}
        {tab === 'sync' ? <SyncConfigScreen embedded /> : null}
        {tab === 'history' ? <DetailHistory connectionId={connectionId} /> : null}
      </div>
    </div>
  );
}
