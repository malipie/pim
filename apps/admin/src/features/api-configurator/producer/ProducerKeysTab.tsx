import { useList } from '@refinedev/core';
import { useTranslation } from 'react-i18next';

import { ConnStatusPill } from '../components/primitives';
import type { ApiKeyRow } from './types';

/**
 * APIC-P4-06 — Keys tab: the tenant's API keys (prefix, name, scopes, status,
 * last used). Minting + revocation live in their own flows; this is the
 * read-only roster.
 */
export function ProducerKeysTab() {
  const { t } = useTranslation();

  const query = useList<ApiKeyRow>({ resource: 'api_keys', pagination: { mode: 'off' } });
  const keys = query.result.data;

  if (keys.length === 0) {
    return (
      <div className="soft-shadow rounded-2xl border border-dashed border-zinc-300 bg-white p-10 text-center text-[13px] text-zinc-500">
        {t('api_configurator.producer.keys.empty')}
      </div>
    );
  }

  return (
    <div className="soft-shadow overflow-hidden rounded-2xl border border-zinc-200 bg-white">
      <div className="grid grid-cols-[1.4fr_1.4fr_1fr_120px] gap-3 border-b border-zinc-100 bg-zinc-50/40 px-5 py-2.5 text-[10.5px] font-medium uppercase tracking-wider text-zinc-500">
        <div>{t('api_configurator.producer.keys.col.key')}</div>
        <div>{t('api_configurator.producer.keys.col.scopes')}</div>
        <div>{t('api_configurator.producer.keys.col.last_used')}</div>
        <div>{t('api_configurator.producer.keys.col.status')}</div>
      </div>
      <div className="divide-y divide-zinc-50">
        {keys.map((key) => {
          const revoked = key.revokedAt != null;
          return (
            <div
              key={key.id}
              className={`grid grid-cols-[1.4fr_1.4fr_1fr_120px] items-center gap-3 px-5 py-3 ${revoked ? 'opacity-60' : ''}`}
            >
              <div className="min-w-0">
                <div className="truncate text-[12.5px] font-medium text-zinc-900">{key.name}</div>
                <div className="font-mono text-[11px] text-zinc-500">{key.keyPrefix}…</div>
              </div>
              <div className="flex flex-wrap gap-1">
                {(key.scopes ?? []).map((scope) => (
                  <span
                    key={scope}
                    className="rounded bg-zinc-100 px-1.5 py-0.5 font-mono text-[10.5px] text-zinc-700"
                  >
                    {scope}
                  </span>
                ))}
              </div>
              <div className="text-[11.5px] text-zinc-500">
                {key.lastUsedAt != null ? new Date(key.lastUsedAt).toLocaleDateString() : '—'}
              </div>
              <div>
                {revoked ? (
                  <span className="rounded-md bg-rose-50 px-2 py-0.5 text-[11.5px] font-medium text-rose-700">
                    {t('api_configurator.producer.keys.revoked')}
                  </span>
                ) : (
                  <ConnStatusPill status="active" label={t('api_configurator.hub.status.active')} />
                )}
              </div>
            </div>
          );
        })}
      </div>
    </div>
  );
}
