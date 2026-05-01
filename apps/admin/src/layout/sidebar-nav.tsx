import {
  Boxes,
  ChevronDown,
  FolderTree,
  Image,
  KeyRound,
  Layers,
  LayoutList,
  ListTree,
  type LucideIcon,
  Package,
  Radio,
  Settings2,
} from 'lucide-react';
import { useEffect, useState } from 'react';
import { useTranslation } from 'react-i18next';
import { NavLink, useLocation } from 'react-router';

import { cn } from '@/lib/utils';

interface NavLeaf {
  type: 'leaf';
  to: string;
  icon: LucideIcon;
  label: string;
  comingSoon?: boolean;
}

interface NavGroup {
  type: 'group';
  id: string;
  icon: LucideIcon;
  label: string;
  children: NavLeaf[];
}

type NavItem = NavLeaf | NavGroup;

const MODELING_GROUP: NavGroup = {
  type: 'group',
  id: 'modeling',
  icon: Settings2,
  label: 'nav.modeling',
  children: [
    {
      type: 'leaf',
      to: '/modeling/object-types',
      icon: ListTree,
      label: 'nav.modeling_object_types',
    },
    {
      type: 'leaf',
      to: '/modeling/attributes',
      icon: Layers,
      label: 'nav.modeling_attributes',
    },
    {
      type: 'leaf',
      to: '/modeling/attribute-groups',
      icon: LayoutList,
      label: 'nav.modeling_attribute_groups',
    },
    {
      type: 'leaf',
      to: '/modeling/categories',
      icon: FolderTree,
      label: 'nav.modeling_categories',
    },
  ],
};

const NAV_ITEMS: NavItem[] = [
  { type: 'leaf', to: '/products', icon: Package, label: 'nav.products' },
  MODELING_GROUP,
  { type: 'leaf', to: '/assets', icon: Image, label: 'nav.assets' },
  { type: 'leaf', to: '/channels', icon: Radio, label: 'nav.channels' },
  { type: 'leaf', to: '/api-profiles', icon: KeyRound, label: 'nav.api_profiles' },
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

const subLeafLinkClass = ({ isActive }: { isActive: boolean }) =>
  cn(
    'flex items-center gap-2 rounded-md px-3 py-1.5 text-sm transition-colors',
    isActive
      ? 'bg-secondary text-secondary-foreground font-medium'
      : 'text-muted-foreground hover:bg-accent hover:text-accent-foreground',
  );

function isGroupActive(group: NavGroup, pathname: string): boolean {
  return group.children.some((child) => pathname.startsWith(child.to));
}

export function SidebarNav({ onNavigate }: SidebarNavProps) {
  const { t } = useTranslation();
  const { pathname } = useLocation();

  const [openGroups, setOpenGroups] = useState<Record<string, boolean>>(() => {
    const initial: Record<string, boolean> = {};
    for (const item of NAV_ITEMS) {
      if (item.type === 'group') {
        initial[item.id] = isGroupActive(item, pathname);
      }
    }
    return initial;
  });

  useEffect(() => {
    setOpenGroups((prev) => {
      let changed = false;
      const next = { ...prev };
      for (const item of NAV_ITEMS) {
        if (item.type === 'group' && isGroupActive(item, pathname) && !next[item.id]) {
          next[item.id] = true;
          changed = true;
        }
      }
      return changed ? next : prev;
    });
  }, [pathname]);

  const toggleGroup = (id: string) => {
    setOpenGroups((prev) => ({ ...prev, [id]: !prev[id] }));
  };

  return (
    <>
      <div className="flex h-14 items-center gap-2 border-b px-4">
        <Boxes className="size-5 text-primary" />
        <span className="font-semibold tracking-tight">{t('app.title')}</span>
      </div>
      <nav className="flex flex-1 flex-col gap-1 p-3">
        {NAV_ITEMS.map((item) => {
          if (item.type === 'leaf') {
            return (
              <NavLink key={item.to} to={item.to} onClick={onNavigate} className={leafLinkClass}>
                <item.icon className="size-4" />
                <span className="flex-1">{t(item.label)}</span>
                {item.comingSoon ? (
                  <span className="rounded bg-muted px-1.5 py-0.5 text-xs uppercase text-muted-foreground">
                    {t('nav.soon')}
                  </span>
                ) : null}
              </NavLink>
            );
          }

          const isOpen = openGroups[item.id] ?? false;
          const hasActiveChild = isGroupActive(item, pathname);
          const expandLabel = t(isOpen ? 'nav.collapse_modeling' : 'nav.expand_modeling');

          return (
            <div key={item.id} className="flex flex-col">
              <button
                type="button"
                onClick={() => toggleGroup(item.id)}
                aria-expanded={isOpen}
                aria-controls={`nav-group-${item.id}`}
                aria-label={expandLabel}
                className={cn(
                  'flex w-full items-center gap-2 rounded-md px-3 py-2 text-sm font-medium transition-colors',
                  hasActiveChild
                    ? 'text-foreground'
                    : 'text-muted-foreground hover:bg-accent hover:text-accent-foreground',
                )}
              >
                <item.icon className="size-4" />
                <span className="flex-1 text-left">{t(item.label)}</span>
                <ChevronDown
                  className={cn(
                    'size-4 shrink-0 transition-transform duration-150',
                    isOpen ? 'rotate-0' : '-rotate-90',
                  )}
                  aria-hidden="true"
                />
              </button>
              {isOpen ? (
                <div
                  id={`nav-group-${item.id}`}
                  className="ml-4 mt-1 flex flex-col gap-0.5 border-l border-border pl-2"
                >
                  {item.children.map((child) => (
                    <NavLink
                      key={child.to}
                      to={child.to}
                      onClick={onNavigate}
                      className={subLeafLinkClass}
                    >
                      <child.icon className="size-4" />
                      <span className="flex-1">{t(child.label)}</span>
                    </NavLink>
                  ))}
                </div>
              ) : null}
            </div>
          );
        })}
      </nav>
    </>
  );
}
