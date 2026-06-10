import { useQuery } from '@tanstack/react-query';
import { Plus } from 'lucide-react';
import { useMemo } from 'react';
import { useTranslation } from 'react-i18next';
import { Link, Outlet, useLocation, useNavigate } from 'react-router';

import { PillTabs } from '@/components/ui-v2/pill-tabs';
import { usePageActions } from '@/layout/page-actions-context';
import { jsonFetch } from '@/lib/http';

import { useExportSessions } from '../hooks/useExportSessions';

interface ProfilesResponse {
  total: number;
}

/**
 * EXR-08 (#1384) — Exports hub in the v2 design (screen 1): pill tabs
 * (Sesje / Profile Eksportu / Cele / Harmonogram — the last two are D3
 * "soon" placeholders) + the "Nowy eksport" CTA registered into the
 * global topbar via PageActionsContext.
 */
export function ExportsLayout(): React.ReactElement {
  const { t } = useTranslation();
  const { pathname } = useLocation();
  const navigate = useNavigate();

  const sessionsQuery = useExportSessions();
  const profilesQuery = useQuery<ProfilesResponse>({
    queryKey: ['exports', 'profiles', 'count'],
    queryFn: () =>
      jsonFetch<ProfilesResponse>('/api/exports/profiles', { accept: 'application/json' }),
    staleTime: 30_000,
  });

  usePageActions(
    useMemo(
      () => (
        <Link
          to="/integrations/exports/new"
          className="focus-ring inline-flex h-9 items-center gap-1.5 rounded-xl bg-cta px-3.5 text-[13px] font-semibold text-cta-foreground transition hover:bg-accent-hover"
        >
          <Plus className="size-4" aria-hidden />
          {t('exports.new_cta')}
        </Link>
      ),
      [t],
    ),
  );

  const activeId = pathname.startsWith('/integrations/exports/profiles') ? 'profiles' : 'sessions';

  return (
    <div className="space-y-5">
      <PillTabs
        ariaLabel={t('exports.tabs_aria')}
        activeId={activeId}
        onChange={(id) => {
          void navigate(`/integrations/exports/${id}`);
        }}
        items={[
          {
            id: 'sessions',
            label: t('exports.tabs.sessions'),
            count: sessionsQuery.data?.total,
          },
          {
            id: 'profiles',
            label: t('exports.tabs.profiles'),
            count: profilesQuery.data?.total,
          },
          { id: 'targets', label: t('exports.tabs.targets'), disabled: true },
          { id: 'schedule', label: t('exports.tabs.schedule'), disabled: true },
        ]}
      />
      <Outlet />
    </div>
  );
}

export default ExportsLayout;
