import { useList } from '@refinedev/core';
import { useTranslation } from 'react-i18next';
import { useNavigate } from 'react-router';

import { type ConnectionStatus, ConnStatusPill } from '../components/primitives';
import type { ApiProfileRow } from './types';

const BASE = '/integrations/api-configurator';

const KNOWN_STATUSES: ConnectionStatus[] = ['active', 'paused', 'error', 'draft'];

/**
 * APIC-P4-06 — Profiles tab: the tenant's API profiles as cards (scope counts +
 * status), each opening the profile detail/builder.
 */
export function ProducerProfilesTab() {
  const { t } = useTranslation();
  const navigate = useNavigate();

  const query = useList<ApiProfileRow>({ resource: 'api_profiles', pagination: { mode: 'off' } });
  const profiles = query.result.data;

  return (
    <div className="space-y-3">
      <div className="flex justify-end">
        <button
          type="button"
          onClick={() => navigate(`${BASE}/profiles/new`)}
          className="h-9 rounded-xl bg-zinc-900 px-4 text-[13px] font-semibold text-white transition hover:bg-zinc-800"
        >
          {t('api_configurator.producer.profiles.new')}
        </button>
      </div>

      {profiles.length === 0 ? (
        <div className="soft-shadow rounded-2xl border border-dashed border-zinc-300 bg-white p-10 text-center text-[13px] text-zinc-500">
          {t('api_configurator.producer.profiles.empty')}
        </div>
      ) : (
        <div className="grid grid-cols-1 gap-3 md:grid-cols-2 xl:grid-cols-3">
          {profiles.map((profile) => {
            const status = (KNOWN_STATUSES as string[]).includes(profile.status ?? '')
              ? (profile.status as ConnectionStatus)
              : 'draft';
            return (
              <button
                key={profile.id}
                type="button"
                onClick={() => navigate(`${BASE}/profiles/${profile.id}/edit`)}
                className="soft-shadow rounded-2xl border border-zinc-200 bg-white p-5 text-left transition hover:border-zinc-300"
              >
                <div className="flex items-start justify-between gap-2">
                  <div className="min-w-0">
                    <div className="truncate font-display text-[15px] font-semibold tracking-tight">
                      {profile.name}
                    </div>
                    <div className="font-mono text-[11.5px] text-zinc-500">{profile.code}</div>
                  </div>
                  <ConnStatusPill
                    status={status}
                    label={t(`api_configurator.hub.status.${status}`)}
                  />
                </div>
                <div className="mt-3 flex gap-4 text-[11.5px] text-zinc-600">
                  <span>
                    {t('api_configurator.producer.profiles.object_types', {
                      count: profile.objectTypeIds?.length ?? 0,
                    })}
                  </span>
                  <span>
                    {t('api_configurator.producer.profiles.attributes', {
                      count: profile.includedAttributes?.length ?? 0,
                    })}
                  </span>
                </div>
              </button>
            );
          })}
        </div>
      )}
    </div>
  );
}
