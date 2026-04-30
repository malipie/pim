import {
  Boxes,
  FolderTree,
  Image,
  Layers,
  LayoutList,
  ListTree,
  type LucideIcon,
  Package,
  Radio,
} from 'lucide-react';
import { useTranslation } from 'react-i18next';
import { NavLink } from 'react-router';

import { cn } from '@/lib/utils';

interface NavItem {
  to: string;
  icon: LucideIcon;
  label: string;
  comingSoon?: boolean;
}

// Sidebar entries land here as the corresponding admin resources arrive in
// epic 0.6 (#54-#62). The `comingSoon` flag drives a muted visual state — the
// route still works and renders <ComingSoon /> with a link to the tracking
// issue, so users get a deterministic "not yet" rather than a 404.
const NAV_ITEMS: NavItem[] = [
  { to: '/products', icon: Package, label: 'nav.products' },
  { to: '/attributes', icon: Layers, label: 'nav.attributes' },
  { to: '/attribute-groups', icon: LayoutList, label: 'nav.attribute_groups' },
  { to: '/object-types', icon: ListTree, label: 'nav.object_types' },
  { to: '/categories', icon: FolderTree, label: 'nav.categories', comingSoon: true },
  { to: '/assets', icon: Image, label: 'nav.assets', comingSoon: true },
  { to: '/channels', icon: Radio, label: 'nav.channels', comingSoon: true },
];

interface SidebarNavProps {
  onNavigate?: () => void;
}

export function SidebarNav({ onNavigate }: SidebarNavProps) {
  const { t } = useTranslation();

  return (
    <>
      <div className="flex h-14 items-center gap-2 border-b px-4">
        <Boxes className="size-5 text-primary" />
        <span className="font-semibold tracking-tight">{t('app.title')}</span>
      </div>
      <nav className="flex flex-1 flex-col gap-1 p-3">
        {NAV_ITEMS.map(({ to, icon: Icon, label, comingSoon }) => (
          <NavLink
            key={to}
            to={to}
            onClick={onNavigate}
            className={({ isActive }) =>
              cn(
                'flex items-center gap-2 rounded-md px-3 py-2 text-sm font-medium transition-colors',
                isActive
                  ? 'bg-secondary text-secondary-foreground'
                  : 'text-muted-foreground hover:bg-accent hover:text-accent-foreground',
              )
            }
          >
            <Icon className="size-4" />
            <span className="flex-1">{t(label)}</span>
            {comingSoon ? (
              <span className="rounded bg-muted px-1.5 py-0.5 text-xs uppercase text-muted-foreground">
                {t('nav.soon')}
              </span>
            ) : null}
          </NavLink>
        ))}
      </nav>
    </>
  );
}
