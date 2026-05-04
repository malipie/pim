import {
  Boxes,
  Cog,
  FileText,
  Image,
  LayoutDashboard,
  type LucideIcon,
  Package,
  Plug2,
  Settings2,
  Workflow,
  Wrench,
} from 'lucide-react';
import { useTranslation } from 'react-i18next';
import { NavLink } from 'react-router';

import { cn } from '@/lib/utils';

import { UserMenu } from './user-menu';

interface NavLeaf {
  to?: string;
  icon: LucideIcon;
  label: string;
  comingSoon?: boolean;
  /** MOCK count badge shown after the label (e.g. "12 847"). */
  count?: string;
  /** MOCK kind tag shown next to count (e.g. "CUSTOM"). */
  kindTag?: string;
}

interface NavSection {
  id: string;
  items: NavLeaf[];
}

// MOCK counts per UI-03c handoff — replace with live `useList(pageSize=1)`
// queries when a backend `GET /api/sidebar/counts` endpoint ships.
const NAV_SECTIONS: NavSection[] = [
  {
    id: 'main',
    items: [
      { to: '/dashboard', icon: LayoutDashboard, label: 'nav.dashboard' },
      { to: '/products', icon: Package, label: 'nav.products', count: '12 847' },
      {
        icon: Wrench,
        label: 'nav.services',
        comingSoon: true,
        kindTag: 'CUSTOM',
        count: '84',
      },
      { to: '/catalogs-pdf', icon: FileText, label: 'nav.catalogsPdf' },
      { to: '/assets', icon: Image, label: 'nav.multimedia', count: '8 421' },
      { icon: Workflow, label: 'nav.workflow', comingSoon: true },
      { to: '/api-profiles', icon: Plug2, label: 'nav.integrations' },
      { to: '/settings', icon: Cog, label: 'nav.settings' },
    ],
  },
  {
    id: 'modeling',
    items: [{ to: '/modeling', icon: Settings2, label: 'nav.modeling' }],
  },
];

interface SidebarNavProps {
  onNavigate?: () => void;
}

const leafLinkClass = ({ isActive }: { isActive: boolean }) =>
  cn(
    'group relative flex items-center gap-2 rounded-md px-3 py-2 text-sm font-medium transition-colors',
    isActive
      ? // violet accent border-left + soft accent background per UI-03b handoff
        'bg-accent-violet/10 text-foreground before:absolute before:inset-y-1 before:left-0 before:w-0.5 before:rounded-r before:bg-accent-violet'
      : 'text-muted-foreground hover:bg-accent hover:text-accent-foreground',
  );

const disabledLeafClass = cn(
  'flex items-center gap-2 rounded-md px-3 py-2 text-sm font-medium',
  'cursor-not-allowed text-muted-foreground/60',
);

export function SidebarNav({ onNavigate }: SidebarNavProps) {
  const { t } = useTranslation();

  // Liczba aktywnych modułów = wszystkie wired nav items (wszystkie sekcje, bez comingSoon).
  const activeModulesCount = NAV_SECTIONS.flatMap((section) => section.items).filter(
    (item) => !item.comingSoon,
  ).length;

  const renderTrailing = (item: NavLeaf) => (
    <>
      {item.kindTag ? (
        <span className="rounded bg-accent-violet/10 px-1.5 py-0.5 text-[9.5px] font-semibold uppercase tracking-wider text-accent-violet">
          {item.kindTag}
        </span>
      ) : null}
      {item.count ? (
        <span className="num text-[11px] text-muted-foreground tabular-nums">{item.count}</span>
      ) : null}
    </>
  );

  const renderLeaf = (item: NavLeaf, key: string) => {
    if (item.comingSoon || !item.to) {
      return (
        <span key={key} className={disabledLeafClass} aria-disabled="true">
          <item.icon className="size-4" />
          <span className="flex-1">{t(item.label)}</span>
          {renderTrailing(item)}
          <span className="rounded bg-muted px-1.5 py-0.5 text-xs uppercase text-muted-foreground">
            {t('nav.soon')}
          </span>
        </span>
      );
    }

    return (
      <NavLink key={key} to={item.to} onClick={onNavigate} className={leafLinkClass}>
        <item.icon className="size-4" />
        <span className="flex-1">{t(item.label)}</span>
        {renderTrailing(item)}
      </NavLink>
    );
  };

  return (
    <>
      <div className="flex h-14 items-center gap-2 border-b px-4">
        <Boxes className="size-5 text-accent-violet" />
        <span className="font-semibold tracking-tight">{t('app.title')}</span>
      </div>
      <nav className="flex flex-1 flex-col gap-1 overflow-y-auto p-3">
        {/* Workspace label per UI-03b handoff — separates "where I am" from "what's available". */}
        <div className="px-3 pb-1 pt-1 text-[10px] font-semibold uppercase tracking-wider text-muted-foreground">
          {t('nav.workspace_label', { defaultValue: 'Workspace' })}
        </div>
        {NAV_SECTIONS.map((section, sectionIndex) => (
          <div
            key={section.id}
            className={cn(
              'flex flex-col gap-1',
              sectionIndex > 0 && 'mt-2 border-t border-border pt-2',
            )}
          >
            {section.items.map((item) => renderLeaf(item, item.to ?? item.label))}
          </div>
        ))}
      </nav>
      <div className="border-t p-3">
        <div className="mb-2 px-1 text-[11px] text-muted-foreground">
          {t('nav.active_modules_count', {
            defaultValue: '{{count}} modułów aktywnych',
            count: activeModulesCount,
          })}
        </div>
        <UserMenu />
      </div>
    </>
  );
}
