import { useTranslation } from 'react-i18next';
import { NavLink, Outlet, useLocation } from 'react-router';

/**
 * UI-08.9 (#264) — Modeling layout shell.
 *
 * Renders a 4-tab top bar (Object Types / Attributes / Attribute Groups /
 * Categories) that drives the `/modeling/*` route tree. Active tab is
 * derived from the current pathname so a deep-link (e.g.
 * `/modeling/attributes/{id}`) still highlights the parent tab.
 *
 * Tab buttons are `<NavLink>` components — keyboard navigation, focus
 * outlines, and back/forward history come for free. The visual styling
 * matches the segmented tablist used by the API Profile form
 * (`features/api-configurator/api-profiles/form.tsx`); `role="tablist"`
 * + `aria-selected` keep it screen-reader friendly without a Radix
 * `Tabs` primitive (no shadcn Tabs component is installed yet, and a
 * NavLink-driven tablist is the right primitive when each tab maps to
 * its own URL).
 */

const TABS = [
  { value: 'object-types', to: '/modeling/object-types', label: 'modeling.tabs.object_types' },
  { value: 'attributes', to: '/modeling/attributes', label: 'modeling.tabs.attributes' },
  {
    value: 'attribute-groups',
    to: '/modeling/attribute-groups',
    label: 'modeling.tabs.attribute_groups',
  },
  { value: 'categories', to: '/modeling/categories', label: 'modeling.tabs.categories' },
] as const;

export function ModelingLayout() {
  const { t } = useTranslation();
  const { pathname } = useLocation();

  const activeTab = TABS.find((tab) => pathname.startsWith(tab.to))?.value ?? TABS[0].value;

  return (
    <div className="space-y-6">
      <header className="space-y-1">
        <h1 className="text-2xl font-semibold tracking-tight">{t('modeling.title')}</h1>
        <p className="text-sm text-muted-foreground">{t('modeling.description')}</p>
      </header>
      <div
        className="flex gap-1 border-b"
        role="tablist"
        aria-label={t('modeling.tabs.aria_label')}
      >
        {TABS.map((tab) => {
          const isActive = activeTab === tab.value;
          return (
            <NavLink
              key={tab.value}
              to={tab.to}
              role="tab"
              aria-selected={isActive}
              aria-controls={`modeling-panel-${tab.value}`}
              className={
                isActive
                  ? 'border-primary text-foreground -mb-px border-b-2 px-4 py-2 text-sm font-medium'
                  : 'text-muted-foreground hover:text-foreground -mb-px border-b-2 border-transparent px-4 py-2 text-sm'
              }
            >
              {t(tab.label)}
            </NavLink>
          );
        })}
      </div>
      <div role="tabpanel" id={`modeling-panel-${activeTab}`}>
        <Outlet />
      </div>
    </div>
  );
}
