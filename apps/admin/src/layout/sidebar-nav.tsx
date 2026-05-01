import {
  Boxes,
  Cog,
  Image,
  LayoutDashboard,
  type LucideIcon,
  Package,
  Settings2,
  Share2,
  Workflow,
  Wrench,
} from 'lucide-react';
import { useTranslation } from 'react-i18next';
import { NavLink } from 'react-router';

import { cn } from '@/lib/utils';

interface NavLeaf {
  to?: string;
  icon: LucideIcon;
  label: string;
  comingSoon?: boolean;
}

interface NavSection {
  id: string;
  items: NavLeaf[];
}

const NAV_SECTIONS: NavSection[] = [
  {
    id: 'main',
    items: [
      { icon: LayoutDashboard, label: 'nav.dashboard', comingSoon: true },
      { to: '/products', icon: Package, label: 'nav.products' },
      { icon: Wrench, label: 'nav.services', comingSoon: true },
      { to: '/channels', icon: Share2, label: 'nav.publications' },
      { to: '/assets', icon: Image, label: 'nav.multimedia' },
      { icon: Workflow, label: 'nav.workflow', comingSoon: true },
      { to: '/api-profiles', icon: Cog, label: 'nav.settings' },
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
    'flex items-center gap-2 rounded-md px-3 py-2 text-sm font-medium transition-colors',
    isActive
      ? 'bg-secondary text-secondary-foreground'
      : 'text-muted-foreground hover:bg-accent hover:text-accent-foreground',
  );

const disabledLeafClass = cn(
  'flex items-center gap-2 rounded-md px-3 py-2 text-sm font-medium',
  'cursor-not-allowed text-muted-foreground/60',
);

export function SidebarNav({ onNavigate }: SidebarNavProps) {
  const { t } = useTranslation();

  const renderLeaf = (item: NavLeaf, key: string) => {
    if (item.comingSoon || !item.to) {
      return (
        <span key={key} className={disabledLeafClass} aria-disabled="true">
          <item.icon className="size-4" />
          <span className="flex-1">{t(item.label)}</span>
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
      </NavLink>
    );
  };

  return (
    <>
      <div className="flex h-14 items-center gap-2 border-b px-4">
        <Boxes className="size-5 text-primary" />
        <span className="font-semibold tracking-tight">{t('app.title')}</span>
      </div>
      <nav className="flex flex-1 flex-col gap-1 p-3">
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
    </>
  );
}
