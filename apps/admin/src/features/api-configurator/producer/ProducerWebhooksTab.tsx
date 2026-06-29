import { useList } from '@refinedev/core';
import { useMemo } from 'react';
import { useTranslation } from 'react-i18next';

import { SecurityNote } from '../components/primitives';
import { type RunStatus, RunStatusDot } from '../consumer/detail/RunStatus';
import type { ApiProfileRow, WebhookDeliveryRow } from './types';

const DELIVERY_STATUS: Record<string, RunStatus> = {
  delivered: 'success',
  pending: 'running',
  failed: 'failed',
};

/**
 * APIC-P4-06 — Webhooks tab: per-profile webhook configuration (URL + events)
 * with the latest delivery outcome from the P4-05 history (read via the P4-06
 * /api/webhook_deliveries API). The HMAC secret is never returned by the API,
 * so it is shown as configured/not, not its value.
 */
export function ProducerWebhooksTab() {
  const { t } = useTranslation();

  const profilesQuery = useList<ApiProfileRow>({
    resource: 'api_profiles',
    pagination: { mode: 'off' },
  });
  const deliveriesQuery = useList<WebhookDeliveryRow>({
    resource: 'webhook_deliveries',
    pagination: { mode: 'off' },
  });

  const hooked = profilesQuery.result.data.filter((p) => (p.webhookUrl ?? '') !== '');

  // Latest delivery per profile (the list is createdAt DESC, so first wins).
  const latestByProfile = useMemo(() => {
    const map = new Map<string, WebhookDeliveryRow>();
    for (const d of deliveriesQuery.result.data) {
      if (!map.has(d.profileId)) {
        map.set(d.profileId, d);
      }
    }
    return map;
  }, [deliveriesQuery.result.data]);

  if (hooked.length === 0) {
    return (
      <div className="soft-shadow rounded-2xl border border-dashed border-zinc-300 bg-white p-10 text-center text-[13px] text-zinc-500">
        {t('api_configurator.producer.webhooks.empty')}
      </div>
    );
  }

  return (
    <div className="space-y-3">
      {hooked.map((profile) => {
        const last = latestByProfile.get(profile.id);
        const lastStatus = last !== undefined ? (DELIVERY_STATUS[last.status] ?? 'running') : null;
        return (
          <section
            key={profile.id}
            className="soft-shadow rounded-2xl border border-zinc-200 bg-white p-5"
          >
            <div className="flex items-start justify-between gap-3">
              <div className="min-w-0">
                <div className="font-display text-[14px] font-semibold tracking-tight">
                  {profile.name}
                </div>
                <div className="truncate font-mono text-[11.5px] text-zinc-500">
                  {profile.webhookUrl}
                </div>
              </div>
              {lastStatus !== null ? (
                <span className="inline-flex items-center gap-1.5 text-[11.5px] font-medium text-zinc-600">
                  <RunStatusDot status={lastStatus} />
                  {t(`api_configurator.detail.run_status.${lastStatus}`)}
                </span>
              ) : (
                <span className="text-[11.5px] text-zinc-400">
                  {t('api_configurator.producer.webhooks.no_delivery')}
                </span>
              )}
            </div>
            <div className="mt-2 flex flex-wrap gap-1">
              {(profile.webhookEvents ?? []).map((event) => (
                <span
                  key={event}
                  className="rounded bg-zinc-100 px-1.5 py-0.5 font-mono text-[11px] text-zinc-700"
                >
                  {event}
                </span>
              ))}
            </div>
          </section>
        );
      })}
      <SecurityNote tone="zinc">{t('api_configurator.producer.webhooks.secret_note')}</SecurityNote>
    </div>
  );
}
