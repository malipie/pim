import { ActivityChart } from './components/ActivityChart';
import { AlertCenter } from './components/AlertCenter';
import { BackupWidget } from './components/BackupWidget';
import { ChannelDistribution } from './components/ChannelDistribution';
import { CompletenessMetrics } from './components/CompletenessMetrics';
import { HeroAgentPanel } from './components/HeroAgentPanel';
import { KpiCards } from './components/KpiCards';
import { RecentAgentActivity } from './components/RecentAgentActivity';
import { SyncsStatusPanel } from './components/SyncsStatusPanel';
import { TopEditedProducts } from './components/TopEditedProducts';
import { useDashboardCounts } from './use-dashboard-counts';

/**
 * Dashboard v2 (NUI-02 #1421) — row layout per `Dashboard.html`:
 * Hero → KPI → [Activity | Sync] → [Completeness | Channels] →
 * [Alerts | Agent activity] → [Backup | Top edited].
 *
 * KPI entity totals are LIVE (useDashboardCounts); every other block stays a
 * mock with a MockBadge — backend follow-ups in
 * Project Plan/UI/Wdrozenie_grafiki/dashboard-do-oprogramowania.md.
 */
export function DashboardPage() {
  const { data: counts = {}, isPending } = useDashboardCounts();

  return (
    <div className="space-y-6 px-4 py-6 sm:px-6 lg:px-10">
      <HeroAgentPanel />
      <KpiCards counts={counts} isPending={isPending} />
      <div className="grid grid-cols-1 gap-6 lg:grid-cols-3">
        <div className="lg:col-span-2">
          <ActivityChart />
        </div>
        <div>
          <SyncsStatusPanel />
        </div>
      </div>
      <div className="grid grid-cols-1 gap-6 lg:grid-cols-3">
        <div className="lg:col-span-2">
          <CompletenessMetrics />
        </div>
        <div>
          <ChannelDistribution />
        </div>
      </div>
      <div className="grid grid-cols-1 gap-6 lg:grid-cols-2">
        <div>
          <AlertCenter />
        </div>
        <div>
          <RecentAgentActivity />
        </div>
      </div>
      <div className="grid grid-cols-1 gap-6 lg:grid-cols-3">
        <div>
          <BackupWidget />
        </div>
        <div className="lg:col-span-2">
          <TopEditedProducts />
        </div>
      </div>
    </div>
  );
}
