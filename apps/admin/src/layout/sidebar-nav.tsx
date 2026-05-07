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
  Tag,
  Workflow,
  Wrench,
} from 'lucide-react';
import { useTranslation } from 'react-i18next';
import { NavLink } from 'react-router';

import { type EffectiveMenuItem, useEffectiveMenu } from '@/lib/use-effective-menu';
import { cn } from '@/lib/utils';

import { UserMenu } from './user-menu';

interface SidebarNavProps {
  onNavigate?: () => void;
}

/**
 * VIEW-08 (#427) — string→LucideIcon lookup. The backend ships icon names
 * (`SystemMenuItemRegistry` for `system` items, `ObjectType.icon` for
 * `object_type` items). Add new icons here as new ObjectTypes/system items
 * arrive — fallback is `Boxes`.
 */
const ICON_MAP: Record<string, LucideIcon> = {
  Boxes,
  Cog,
  FileText,
  Image,
  LayoutDashboard,
  Package,
  Plug2,
  Settings2,
  Tag,
  Workflow,
  Wrench,
};

/**
 * VIEW-08 (#427) fallback — used while the effective-menu query is still
 * loading on a hard reload, and as a graceful degrade if the backend is
 * unreachable. Mirrors the legacy hard-coded sidebar minus Services.
 */
const FALLBACK_ITEMS: EffectiveMenuItem[] = [
  {
    id: 'system:dashboard',
    kind: 'system',
    ref: 'dashboard',
    label: null,
    labelKey: 'nav.dashboard',
    icon: 'LayoutDashboard',
    route: '/dashboard',
    comingSoon: false,
    protected: false,
  },
  {
    id: 'system:catalogs_pdf',
    kind: 'system',
    ref: 'catalogs_pdf',
    label: null,
    labelKey: 'nav.catalogsPdf',
    icon: 'FileText',
    route: '/catalogs-pdf',
    comingSoon: false,
    protected: false,
  },
  {
    id: 'system:multimedia',
    kind: 'system',
    ref: 'multimedia',
    label: null,
    labelKey: 'nav.multimedia',
    icon: 'Image',
    route: '/assets',
    comingSoon: false,
    protected: false,
  },
  {
    id: 'system:workflow',
    kind: 'system',
    ref: 'workflow',
    label: null,
    labelKey: 'nav.workflow',
    icon: 'Workflow',
    route: null,
    comingSoon: true,
    protected: false,
  },
  {
    id: 'system:integrations',
    kind: 'system',
    ref: 'integrations',
    label: null,
    labelKey: 'nav.integrations',
    icon: 'Plug2',
    route: '/integrations',
    comingSoon: false,
    protected: false,
  },
  {
    id: 'system:settings',
    kind: 'system',
    ref: 'settings',
    label: null,
    labelKey: 'nav.settings',
    icon: 'Cog',
    route: '/settings',
    comingSoon: false,
    protected: true,
  },
  {
    id: 'system:modeling',
    kind: 'system',
    ref: 'modeling',
    label: null,
    labelKey: 'nav.modeling',
    icon: 'Settings2',
    route: '/modeling',
    comingSoon: false,
    protected: true,
  },
];

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

  const { data, isError } = useEffectiveMenu();

  // While loading on a fresh reload, show the fallback list — without it
  // the sidebar flashes empty for ~50-200ms on every hard navigation.
  // On error, the fallback also kicks in (graceful degradation).
  const items: EffectiveMenuItem[] = data && !isError ? data.visible : FALLBACK_ITEMS;

  const activeModulesCount = items.filter((item) => !item.comingSoon).length;

  const renderLabel = (item: EffectiveMenuItem) => {
    if (item.label !== null) {
      return item.label;
    }
    if (item.labelKey) {
      return t(item.labelKey);
    }
    return item.ref;
  };

  const renderLeaf = (item: EffectiveMenuItem) => {
    const Icon = ICON_MAP[item.icon] ?? Boxes;
    const labelText = renderLabel(item);

    if (item.comingSoon || item.route === null) {
      return (
        <span key={item.id} className={disabledLeafClass} aria-disabled="true">
          <Icon className="size-4" />
          <span className="flex-1">{labelText}</span>
          <span className="rounded bg-muted px-1.5 py-0.5 text-xs uppercase text-muted-foreground">
            {t('nav.soon')}
          </span>
        </span>
      );
    }

    return (
      <NavLink key={item.id} to={item.route} onClick={onNavigate} className={leafLinkClass}>
        <Icon className="size-4" />
        <span className="flex-1">{labelText}</span>
        {item.kind === 'object_type' && item.objectTypeKind === 'custom' ? (
          <span className="rounded bg-accent-violet/10 px-1.5 py-0.5 text-[9.5px] font-semibold uppercase tracking-wider text-accent-violet">
            {t('nav.custom_tag', { defaultValue: 'CUSTOM' })}
          </span>
        ) : null}
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
        <div className="flex flex-col gap-1">{items.map(renderLeaf)}</div>
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
