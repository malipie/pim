import { ActivityChart } from './components/ActivityChart';
import { AlertCenter } from './components/AlertCenter';
import { ChannelDistribution } from './components/ChannelDistribution';
import { CompletenessMetrics } from './components/CompletenessMetrics';
import { HeroAgentPanel } from './components/HeroAgentPanel';
import { KpiCards } from './components/KpiCards';
import { RecentAgentActivity } from './components/RecentAgentActivity';
import { SyncsStatusPanel } from './components/SyncsStatusPanel';
import { TopEditedProducts } from './components/TopEditedProducts';

/**
 * Dashboard page — handoff mock (epik UI-03 #356).
 *
 * All blocks are static mocks; the wiring with real backend endpoints lives in
 * Project Plan/UI/Wdrozenie_grafiki/dashboard-do-oprogramowania.md.
 *
 * The component itself is "use-client safe" (no DOM-only APIs at module load).
 */
export function DashboardPage() {
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
