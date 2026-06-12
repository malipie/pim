import { useQuery } from '@tanstack/react-query';
import { Plus } from 'lucide-react';
import { useMemo } from 'react';
import { useTranslation } from 'react-i18next';
import { Link, Outlet, useLocation, useNavigate } from 'react-router';

import { PillTabs } from '@/components/ui-v2/pill-tabs';
import { usePageActions } from '@/layout/page-actions-context';
import { jsonFetch } from '@/lib/http';

interface LdCollection {
  totalItems?: number;
  'hydra:totalItems'?: number;
}

const TAB_IDS = ['sessions', 'profiles', 'sources', 'schedule'] as const;
type TabId = (typeof TAB_IDS)[number];

const COUNT_ENDPOINTS: Record<TabId, string> = {
  sessions: '/api/import-sessions',
  profiles: '/api/import-profiles',
  sources: '/api/import-sources',
  schedule: '/api/import-schedules',
};

function useTabCounts() {
  return useQuery({
    queryKey: ['imports', 'tab-counts'],
    staleTime: 30_000,
    refetchOnWindowFocus: false,
    queryFn: async (): Promise<Partial<Record<TabId, number>>> => {
      const entries = await Promise.all(
        TAB_IDS.map(async (id) => {
          try {
            const data = await jsonFetch<LdCollection>(COUNT_ENDPOINTS[id], {
              accept: 'application/ld+json',
              query: { itemsPerPage: 1 },
            });
            return [id, data.totalItems ?? data['hydra:totalItems'] ?? undefined] as const;
          } catch {
            return [id, undefined] as const;
          }
        }),
      );
      const counts: Partial<Record<TabId, number>> = {};
      for (const [id, value] of entries) {
        if (value !== undefined) counts[id] = value;
      }
      return counts;
    },
  });
}

/**
 * NUI-09 (#1428) — Imports hub in the v2 shell (exact EXR-08 pattern):
 * pill tabs with live counts + the "Nowy import" CTA registered into the
 * global topbar via PageActionsContext. The legacy IntegrationsLayout
 * wrapper (header + second tab row) is retired.
 */
export function ImportsLayout() {
  const { t } = useTranslation();
  const { pathname } = useLocation();
  const navigate = useNavigate();
  const counts = useTabCounts().data ?? {};

  usePageActions(
    useMemo(
      () => (
        <Link
          to="/integrations/imports/new"
          className="focus-ring inline-flex h-9 items-center gap-1.5 rounded-xl bg-cta px-3.5 text-[13px] font-semibold text-cta-foreground transition hover:bg-accent-hover"
        >
          <Plus className="size-4" aria-hidden />
          {t('imports.sessions.new_import')}
        </Link>
      ),
      [t],
    ),
  );

  const activeId: TabId =
    TAB_IDS.find((id) => pathname.startsWith(`/integrations/imports/${id}`)) ?? 'sessions';

  return (
    <div className="space-y-5">
      <PillTabs
        ariaLabel={t('imports.tabs.aria_label')}
        activeId={activeId}
        onChange={(id) => {
          void navigate(`/integrations/imports/${id}`);
        }}
        items={TAB_IDS.map((id) => ({
          id,
          label: t(`imports.tabs.${id}`),
          count: counts[id],
        }))}
      />
      <Outlet />
    </div>
  );
}
