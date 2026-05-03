import {
  Boxes,
  Cog,
  FolderTree,
  Image,
  LayoutDashboard,
  type LucideIcon,
  Package,
  Settings2,
  Share2,
  Tag,
  Workflow,
  Wrench,
} from 'lucide-react';
import { useTranslation } from 'react-i18next';
import { NavLink } from 'react-router';

import { type SidebarObjectType, useObjectTypesMenu } from '@/lib/use-object-types-menu';
import { cn } from '@/lib/utils';

import { UserMenu } from './user-menu';

interface NavLeaf {
  to?: string;
  icon: LucideIcon;
  label: string;
  /** Pre-resolved label for dynamic items (skips i18n keys). */
  resolvedLabel?: string;
  comingSoon?: boolean;
  /** MOCK count badge shown after the label (e.g. "12 847"). */
  count?: string;
  /** Kind tag shown next to count — e.g. CUSTOM for tenant-defined types. */
  kindTag?: string;
}

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

/**
 * VIEW-01c (#414) — pick a sensible icon + sugar-path route per ObjectKind.
 * Built-in kinds get their dedicated icon + the legacy `/products`,
 * `/categories`, `/assets`, `/brands` URL. Custom kinds fall back to a
 * generic Wrench icon and the `/object-types/{code}` instance-list route.
 */
function leafForObjectType(
  row: SidebarObjectType,
  language: string,
): { to: string; icon: LucideIcon; label: string } {
  const label = row.label[language] ?? row.label.pl ?? row.label.en ?? row.code;
  switch (row.kind) {
    case 'product':
      return { to: '/products', icon: Package, label };
    case 'category':
      return { to: '/categories', icon: FolderTree, label };
    case 'asset':
      return { to: '/assets', icon: Image, label };
    case 'brand':
      return { to: '/brands', icon: Tag, label };
    default:
      return { to: `/object-types/${row.code}`, icon: Wrench, label };
  }
}

export function SidebarNav({ onNavigate }: SidebarNavProps) {
  const { t, i18n } = useTranslation();
  const { data: objectTypesMenu = [] } = useObjectTypesMenu();

  const dynamicItems: NavLeaf[] = objectTypesMenu.map((row) => {
    const leaf = leafForObjectType(row, i18n.language);
    return {
      to: leaf.to,
      icon: leaf.icon,
      // Resolved at fetch time — no i18n key.
      label: row.code,
      resolvedLabel: leaf.label,
      kindTag: row.builtIn ? undefined : 'CUSTOM',
    };
  });

  // Static skeleton — surrounding the dynamic ObjectType slot are pieces
  // that are not ObjectType-driven (Dashboard, channels/publications,
  // workflow, API profiles / Settings).
  const mainBefore: NavLeaf[] = [
    { to: '/dashboard', icon: LayoutDashboard, label: 'nav.dashboard' },
  ];
  const mainAfter: NavLeaf[] = [
    { to: '/channels', icon: Share2, label: 'nav.publications' },
    { icon: Workflow, label: 'nav.workflow', comingSoon: true },
    { to: '/api-profiles', icon: Cog, label: 'nav.settings' },
  ];
  const modeling: NavLeaf[] = [{ to: '/modeling', icon: Settings2, label: 'nav.modeling' }];

  const allActiveItems = [...mainBefore, ...dynamicItems, ...mainAfter, ...modeling];
  const activeModulesCount = allActiveItems.filter(
    (item) => !item.comingSoon && item.to !== undefined,
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
    const labelText = item.resolvedLabel ?? t(item.label);
    if (item.comingSoon || !item.to) {
      return (
        <span key={key} className={disabledLeafClass} aria-disabled="true">
          <item.icon className="size-4" />
          <span className="flex-1">{labelText}</span>
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
        <span className="flex-1">{labelText}</span>
        {renderTrailing(item)}
      </NavLink>
    );
  };

  const mainItems = [...mainBefore, ...dynamicItems, ...mainAfter];

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
        <div className="flex flex-col gap-1">
          {mainItems.map((item, idx) => renderLeaf(item, `${item.to ?? item.label}-${idx}`))}
        </div>
        <div className="mt-2 flex flex-col gap-1 border-t border-border pt-2">
          {modeling.map((item) => renderLeaf(item, item.to ?? item.label))}
        </div>
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
