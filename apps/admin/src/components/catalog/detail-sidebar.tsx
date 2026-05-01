import { History, Image, Link2, Radio, RefreshCw, Sparkles } from 'lucide-react';
import { useEffect, useState } from 'react';
import { useTranslation } from 'react-i18next';

import { jsonFetch } from '@/lib/http';

interface AuditEntry {
  type: string | null;
  user: string | null;
  diffs: unknown;
  created_at: string | null;
}

interface ChannelStatus {
  product_id: string;
  aggregate: string;
  channels: Array<{ code: string; status: string; last_sync_at: string | null }>;
}

/**
 * UI-02.16 (#306) — right-side context column on the product detail
 * page (4 sections per `Project Plan/UI/epik-02-produkty.md` §5.2).
 *
 * Slice scope:
 * - Images section is a stub (needs the DAM endpoint from epik UI-05).
 * - Related products is a stub (Faza 1+ epik 09).
 * - History reads UI-02.5 audit-log (last 5).
 * - Channels reads UI-02.5 channels-status (aggregate + per-channel
 *   list, currently empty until Faza 1 publish writes the rows).
 */
export function DetailSidebar({ productId }: { productId: string }) {
  const { t } = useTranslation();
  const [audit, setAudit] = useState<AuditEntry[]>([]);
  const [channels, setChannels] = useState<ChannelStatus | null>(null);

  useEffect(() => {
    let cancelled = false;
    Promise.all([
      jsonFetch<{ entries: AuditEntry[] }>(`/api/products/${productId}/audit-log?limit=5`)
        .then((b) => {
          if (!cancelled) setAudit(b.entries);
        })
        .catch(() => undefined),
      jsonFetch<ChannelStatus>(`/api/products/${productId}/channels-status`)
        .then((b) => {
          if (!cancelled) setChannels(b);
        })
        .catch(() => undefined),
    ]);
    return () => {
      cancelled = true;
    };
  }, [productId]);

  return (
    <aside className="flex flex-col gap-5 text-sm">
      <Section icon={Image} title={t('products.detail.sidebar.images', { defaultValue: 'Images' })}>
        <p className="text-xs text-muted-foreground">
          {t('products.detail.sidebar.images_stub', {
            defaultValue: 'DAM widget lands with epik UI-05 (Multimedia).',
          })}
        </p>
      </Section>

      <Section
        icon={Link2}
        title={t('products.detail.sidebar.related', { defaultValue: 'Related products' })}
      >
        <p className="text-xs text-muted-foreground">
          {t('products.detail.sidebar.related_stub', {
            defaultValue: 'Related products picker lands in Faza 1 epik 09.',
          })}
        </p>
      </Section>

      <Section
        icon={History}
        title={t('products.detail.sidebar.history', { defaultValue: 'Recent changes' })}
      >
        {audit.length === 0 ? (
          <p className="text-xs text-muted-foreground">
            {t('products.detail.sidebar.history_empty', {
              defaultValue: 'No audit entries yet.',
            })}
          </p>
        ) : (
          <ul className="space-y-2 text-xs">
            {audit.map((entry) => (
              <li
                key={`${entry.created_at ?? ''}-${entry.type ?? ''}`}
                className="border-l-2 border-muted pl-2"
              >
                <div className="font-medium">{entry.type}</div>
                <div className="text-muted-foreground">
                  {entry.user ?? t('products.audit.system_user', { defaultValue: 'system' })} ·{' '}
                  {entry.created_at}
                </div>
              </li>
            ))}
          </ul>
        )}
      </Section>

      <Section
        icon={Radio}
        title={t('products.detail.sidebar.channels', { defaultValue: 'Channels' })}
      >
        {channels === null ? (
          <p className="text-xs text-muted-foreground">{t('app.loading')}</p>
        ) : channels.channels.length === 0 ? (
          <p className="text-xs text-muted-foreground">
            {t('products.detail.sidebar.channels_empty', {
              defaultValue: 'No channel sync rows yet (Faza 1 publish flow populates).',
            })}
          </p>
        ) : (
          <ul className="space-y-1 text-xs">
            {channels.channels.map((ch) => (
              <li key={ch.code} className="flex items-center justify-between">
                <span className="font-medium">{ch.code}</span>
                <span className="text-muted-foreground">{ch.status}</span>
              </li>
            ))}
          </ul>
        )}
        {/* MOCK: Force sync — wymaga POST /api/integrations/{id}/sync (#TBD).
            Patrz Project Plan/UI/Wdrozenie_grafiki/produkty-do-oprogramowania.md. */}
        <button
          type="button"
          disabled
          aria-disabled="true"
          className="mt-2 inline-flex cursor-not-allowed items-center gap-1.5 rounded-md border border-line px-2 py-1 text-[11px] text-muted-foreground"
          title={t('products.detail.sidebar.force_sync_disabled', {
            defaultValue: 'Mock — wymaga POST /api/integrations/{id}/sync',
          })}
        >
          <RefreshCw className="size-3" />
          {t('products.detail.sidebar.force_sync', { defaultValue: 'Wymuś synchronizację' })}
        </button>
      </Section>

      {/*
        MOCK: Agent suggestions card (handoff right rail) — wymaga warstwy
        agenta (Faza 2) per CLAUDE.md PIM. Trzy stub'y akcji: tłumaczenie EN,
        kod HS, rekomendacje akcesoriów. Patrz Project Plan/UI/Wdrozenie_grafiki/
        produkty-do-oprogramowania.md § "Agent (Faza 2)".
      */}
      <Section
        icon={Sparkles}
        title={t('products.detail.sidebar.agent', { defaultValue: 'Sugestie agenta' })}
      >
        <ul className="space-y-2 text-xs">
          {[
            t('products.detail.sidebar.agent_translate', {
              defaultValue: 'Wygeneruj opis EN dla tego produktu',
            }),
            t('products.detail.sidebar.agent_hs_code', {
              defaultValue: 'Uzupełnij kod HS na podstawie kategorii',
            }),
            t('products.detail.sidebar.agent_accessories', {
              defaultValue: 'Zaproponuj akcesoria z istniejącego katalogu',
            }),
          ].map((suggestion) => (
            <li
              key={suggestion}
              className="flex items-center justify-between gap-2 rounded-md bg-accent-violet/5 px-2 py-1.5 text-ink-2"
            >
              <span className="flex-1">{suggestion}</span>
              <span className="rounded bg-accent-violet/10 px-1.5 py-0.5 text-[9px] font-medium uppercase tracking-wide text-accent-violet">
                Faza 2
              </span>
            </li>
          ))}
        </ul>
      </Section>
    </aside>
  );
}

function Section({
  icon: Icon,
  title,
  children,
}: {
  icon: typeof Image;
  title: string;
  children: React.ReactNode;
}) {
  return (
    <div className="space-y-2">
      <div className="flex items-center gap-2 text-xs uppercase tracking-wide text-muted-foreground">
        <Icon className="size-4" />
        <span>{title}</span>
      </div>
      <div className="rounded-2xl border border-line bg-surface px-3 py-2 soft-shadow">
        {children}
      </div>
    </div>
  );
}
