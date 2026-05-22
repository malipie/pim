import { useGetIdentity } from '@refinedev/core';
import {
  Boxes,
  Cog,
  FileText,
  Image,
  LayoutDashboard,
  type LucideIcon,
  Package,
  Plug2,
  Plus,
  Search,
  Settings2,
  Tag,
  Workflow,
  Wrench,
} from 'lucide-react';
import { useTranslation } from 'react-i18next';
import { NavLink } from 'react-router';

import { MockBadge } from '@/components/ui/mock-badge';
import { Tooltip, TooltipContent, TooltipTrigger } from '@/components/ui/tooltip';
import { isMenuRefVisible, useIdentity } from '@/lib/identity';
import { type EffectiveMenuItem, useEffectiveMenu } from '@/lib/use-effective-menu';
import { cn } from '@/lib/utils';

import { UserMenu } from './user-menu';

interface SidebarNavProps {
  onNavigate?: () => void;
}

interface RefineIdentity {
  id: string;
  name: string;
  email: string;
  roles: string[];
  tenant: { id: string; code: string; name: string } | null;
}

/**
 * VIEW-08 (#427) — string→LucideIcon lookup. Backend ships icon names
 * (`SystemMenuItemRegistry` / `ObjectType.icon`). Fallback: `Boxes`.
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

const baseLeafClass =
  'group relative flex w-full items-center gap-3 rounded-xl px-3 py-2 text-[14px] font-medium transition';

const customLeafClass = cn(
  baseLeafClass,
  'border border-dashed border-violet-300/70 bg-violet-50/50 text-violet-900 hover:bg-violet-100/70',
);

const disabledLeafClass = cn(baseLeafClass, 'cursor-not-allowed text-zinc-400');

const leafLinkClass = ({ isActive }: { isActive: boolean }): string =>
  cn(baseLeafClass, isActive ? 'bg-zinc-900 text-white' : 'text-zinc-700 hover:bg-zinc-100');

export function SidebarNav({ onNavigate }: SidebarNavProps) {
  const { t } = useTranslation();
  const { data, isError } = useEffectiveMenu();
  const { identity } = useIdentity();
  const { data: refineIdentity } = useGetIdentity<RefineIdentity>();

  const rawItems: EffectiveMenuItem[] = data && !isError ? data.visible : FALLBACK_ITEMS;
  const items: EffectiveMenuItem[] = rawItems.filter((item) =>
    isMenuRefVisible(identity, item.ref),
  );

  const renderLabel = (item: EffectiveMenuItem): string => {
    if (item.label !== null) return item.label;
    if (item.labelKey) return t(item.labelKey);
    return item.ref;
  };

  const renderLeaf = (item: EffectiveMenuItem) => {
    const Icon = ICON_MAP[item.icon] ?? Boxes;
    const labelText = renderLabel(item);
    const isCustom = item.kind === 'object_type' && item.objectTypeKind === 'custom';

    if (item.comingSoon || item.route === null) {
      return (
        <span key={item.id} className={disabledLeafClass} aria-disabled="true">
          <Icon className="size-4 text-zinc-400" />
          <span className="flex-1">{labelText}</span>
          <span className="rounded bg-zinc-100 px-1.5 py-0.5 text-[9.5px] font-semibold uppercase tracking-wider text-zinc-500">
            {t('nav.soon')}
          </span>
        </span>
      );
    }

    if (isCustom) {
      return (
        <NavLink
          key={item.id}
          to={item.route}
          onClick={onNavigate}
          className={customLeafClass}
          end={false}
        >
          <Icon className="size-4 text-violet-600" />
          <span className="flex flex-1 items-center gap-1.5">
            {labelText}
            <span className="rounded bg-violet-200/70 px-1.5 py-0.5 text-[9.5px] font-semibold uppercase tracking-wider text-violet-700">
              {t('nav.custom_tag', { defaultValue: 'CUSTOM' })}
            </span>
          </span>
        </NavLink>
      );
    }

    return (
      <NavLink
        key={item.id}
        to={item.route}
        onClick={onNavigate}
        className={leafLinkClass}
        end={false}
      >
        {({ isActive }) => (
          <>
            <Icon className={cn('size-4', isActive ? 'text-white/90' : 'text-zinc-400')} />
            <span className="flex-1">{labelText}</span>
          </>
        )}
      </NavLink>
    );
  };

  const brandSubtitle =
    refineIdentity?.tenant?.name ?? t('app.brand_subtitle_fallback', { defaultValue: 'Workspace' });

  const agentTooltip = t('topbar.agent_mock_tooltip', {
    defaultValue: 'MOCK · Agent layer wymaga oprogramowania (epik 0.7, Faza 2)',
  });

  return (
    <>
      <div className="flex items-center gap-2.5 px-2 pb-3 pt-1">
        <div
          className="grid h-9 w-9 place-items-center rounded-2xl bg-zinc-900 text-white"
          aria-hidden
        >
          <span className="text-[15px] font-bold">P</span>
        </div>
        <div className="min-w-0 leading-tight">
          <div className="truncate text-[15px] font-semibold tracking-tight text-zinc-900">
            {t('app.title')}
          </div>
          <div className="truncate text-[11px] text-zinc-500">{brandSubtitle}</div>
        </div>
      </div>

      <Tooltip>
        <TooltipTrigger asChild>
          <button
            type="button"
            disabled
            className="mt-1 flex w-full cursor-not-allowed items-center gap-2 rounded-2xl bg-white px-3 py-2.5 text-left shadow-[0_1px_2px_rgba(0,0,0,0.04),0_4px_12px_rgba(0,0,0,0.04)] transition focus:outline-none focus-visible:ring-2 focus-visible:ring-ring"
            aria-label={t('topbar.search_agent_placeholder', {
              defaultValue: 'Zapytaj agenta lub szukaj...',
            })}
          >
            <Search className="size-4 shrink-0 text-zinc-400" aria-hidden />
            <span className="flex-1 truncate text-[13px] text-zinc-500">
              {t('topbar.search_agent_placeholder', {
                defaultValue: 'Zapytaj agenta lub szukaj...',
              })}
            </span>
            <kbd className="hidden rounded border border-zinc-200 bg-white px-1.5 py-0.5 font-mono text-[10px] font-medium text-zinc-500 sm:inline">
              ⌘K
            </kbd>
            <MockBadge tooltip={agentTooltip} />
          </button>
        </TooltipTrigger>
        <TooltipContent side="right">{agentTooltip}</TooltipContent>
      </Tooltip>

      <nav className="mt-5 flex-1 overflow-y-auto">
        <div className="px-3 pb-1.5 text-[11px] font-medium uppercase tracking-wider text-zinc-400">
          {t('nav.workspace_label', { defaultValue: 'Workspace' })}
        </div>
        <div className="flex flex-col gap-0.5">{items.map(renderLeaf)}</div>

        <NavLink
          to="/modeling/object-types/new"
          onClick={onNavigate}
          className="mt-3 flex w-full items-center gap-3 rounded-xl border border-dashed border-zinc-200 px-3 py-2 text-[13px] text-zinc-500 transition hover:border-violet-300 hover:bg-violet-50/60 hover:text-violet-700"
        >
          <Plus className="size-4 text-zinc-400" aria-hidden />
          <span className="flex-1 text-left">
            {t('nav.add_custom_module', { defaultValue: 'Dodaj własny moduł' })}
          </span>
        </NavLink>
      </nav>

      <div className="mt-3">
        <UserMenu />
      </div>
    </>
  );
}
