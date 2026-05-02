import { useEffect, useState } from 'react';

import { ActivityChart } from './components/ActivityChart';
import { AlertCenter } from './components/AlertCenter';
import { ChannelDistribution } from './components/ChannelDistribution';
import { CompletenessMetrics } from './components/CompletenessMetrics';
import { HeroAgentPanel } from './components/HeroAgentPanel';
import { KpiCards } from './components/KpiCards';
import { RecentAgentActivity } from './components/RecentAgentActivity';
import { SyncsStatusPanel } from './components/SyncsStatusPanel';
import { ChartSkeleton } from './components/skeletons/ChartSkeleton';
import { KpiSkeleton } from './components/skeletons/KpiSkeleton';
import { ListSkeleton } from './components/skeletons/ListSkeleton';
import { TopEditedProducts } from './components/TopEditedProducts';

/**
 * Dashboard page — handoff mock (epik UI-03 #356, polished in UI-03b #364).
 *
 * All blocks are static mocks; the wiring with real backend endpoints lives in
 * Project Plan/UI/Wdrozenie_grafiki/dashboard-do-oprogramowania.md.
 *
 * The 300 ms setTimeout simulates a network delay so the skeleton loaders are
 * visible during smoke testing — once TanStack Query is wired to live endpoints
 * this state will be driven by `useQuery({ ... }).isPending`.
 */
const SKELETON_DELAY_MS = 300;

export function DashboardPage() {
  const [loading, setLoading] = useState(true);

  useEffect(() => {
    const timer = window.setTimeout(() => setLoading(false), SKELETON_DELAY_MS);
    return () => window.clearTimeout(timer);
  }, []);

  if (loading) {
    return (
      <div className="space-y-6 px-4 py-6 sm:px-6 lg:px-10">
        <ChartSkeleton className="h-[120px]" />
        <KpiSkeleton />
        <div className="grid grid-cols-1 gap-6 lg:grid-cols-3">
          <div className="space-y-6 lg:col-span-2">
            <ChartSkeleton />
            <ListSkeleton rows={6} />
            <ChartSkeleton />
          </div>
          <div className="space-y-6">
            <ListSkeleton rows={4} />
            <ChartSkeleton />
            <ListSkeleton rows={5} />
            <ListSkeleton rows={5} />
          </div>
        </div>
      </div>
    );
  }

  return (
    <div className="space-y-6 px-4 py-6 sm:px-6 lg:px-10">
      <HeroAgentPanel />
      <KpiCards />
      <div className="grid grid-cols-1 gap-6 lg:grid-cols-3">
        <div className="space-y-6 lg:col-span-2">
          <ActivityChart />
          <TopEditedProducts />
          <ChannelDistribution />
        </div>
        <div className="space-y-6">
          <SyncsStatusPanel />
          <CompletenessMetrics />
          <AlertCenter />
          <RecentAgentActivity />
        </div>
      </div>
    </div>
  );
}
