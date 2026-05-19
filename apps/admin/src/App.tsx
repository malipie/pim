import { Refine } from '@refinedev/core';
import { type ComponentType, type LazyExoticComponent, lazy, Suspense } from 'react';
import { BrowserRouter, Navigate, Route, Routes, useParams } from 'react-router';

import { AuthedRoute } from '@/components/AuthedRoute';
import { ToastProvider } from '@/components/ui/toast';
// HARD-08 — only the login + dashboard pages and the always-mounted
// layout shells stay eager. Every other route is React.lazy()-ed so
// the initial bundle drops from ~2.1 MB to roughly the shell + the
// landing screen. Each route gets its own chunk; revisits hit the
// HTTP cache after the first lazy load.
import { DashboardPage } from '@/features/dashboard/page';
import { LoginPage } from '@/features/identity/auth/login';
import { AppLayout } from '@/layout/AppLayout';
import { SettingsLayout } from '@/layout/SettingsLayout';
import { authProvider } from '@/lib/auth-provider';
import { dataProvider } from '@/lib/data-provider';

/**
 * Lazy-loads a page module that exports the component as a named
 * binding (`export function FooPage`). React.lazy itself only
 * accepts default exports, so we adapt with a tiny mapping step.
 */
function lazyPage<K extends string, T extends Record<K, ComponentType<unknown>>>(
  loader: () => Promise<T>,
  exportName: K,
): LazyExoticComponent<ComponentType<unknown>> {
  return lazy(() => loader().then((m) => ({ default: m[exportName] })));
}

const ApiProfileCreatePage = lazyPage(
  () => import('@/features/api-configurator/api-profiles/create'),
  'ApiProfileCreatePage',
);
const ApiProfileEditPage = lazyPage(
  () => import('@/features/api-configurator/api-profiles/edit'),
  'ApiProfileEditPage',
);
const ApiProfilesListPage = lazyPage(
  () => import('@/features/api-configurator/api-profiles/list'),
  'ApiProfilesListPage',
);
const ApiProfileShowPage = lazyPage(
  () => import('@/features/api-configurator/api-profiles/show'),
  'ApiProfileShowPage',
);
const AssetsListPage = lazyPage(() => import('@/features/asset/assets/list'), 'AssetsListPage');
const AssetShowPage = lazyPage(() => import('@/features/asset/assets/show'), 'AssetShowPage');
const AttributeGroupCreatePage = lazyPage(
  () => import('@/features/catalog/attribute-groups/create'),
  'AttributeGroupCreatePage',
);
const AttributeGroupsListPage = lazyPage(
  () => import('@/features/catalog/attribute-groups/list'),
  'AttributeGroupsListPage',
);
const AttributeGroupShowPage = lazyPage(
  () => import('@/features/catalog/attribute-groups/show'),
  'AttributeGroupShowPage',
);
const AttributesListPage = lazyPage(
  () => import('@/features/catalog/attributes/list'),
  'AttributesListPage',
);
const MigrateAttributeTypePage = lazyPage(
  () => import('@/features/catalog/attributes/migrate-type'),
  'MigrateAttributeTypePage',
);
const AttributeCreatePage = lazyPage(
  () => import('@/features/catalog/attributes/new'),
  'AttributeCreatePage',
);
const AttributeShowPage = lazyPage(
  () => import('@/features/catalog/attributes/show'),
  'AttributeShowPage',
);
const AttributeValuesPage = lazyPage(
  () => import('@/features/catalog/attributes/values'),
  'AttributeValuesPage',
);
const CategoriesTreePage = lazyPage(
  () => import('@/features/catalog/categories/list'),
  'CategoriesTreePage',
);
const CategoryCreatePage = lazyPage(
  () => import('@/features/catalog/categories/new'),
  'CategoryCreatePage',
);
const CategoryShowPage = lazyPage(
  () => import('@/features/catalog/categories/show'),
  'CategoryShowPage',
);
const ModelingLayout = lazyPage(
  () => import('@/features/catalog/modeling/layout'),
  'ModelingLayout',
);
const ObjectTypesListPage = lazyPage(
  () => import('@/features/catalog/object-types/list'),
  'ObjectTypesListPage',
);
const ObjectTypeWizardPage = lazyPage(
  () => import('@/features/catalog/object-types/new'),
  'ObjectTypeWizardPage',
);
const ObjectTypeShowPage = lazyPage(
  () => import('@/features/catalog/object-types/show'),
  'ObjectTypeShowPage',
);
const ObjectListingPlaceholder = lazyPage(
  () => import('@/features/catalog/objects/placeholder'),
  'ObjectListingPlaceholder',
);
const ProductCreatePage = lazyPage(
  () => import('@/features/catalog/products/create'),
  'ProductCreatePage',
);
const ProductListPage = lazyPage(
  () => import('@/features/catalog/products/list'),
  'ProductListPage',
);
const ProductShowPage = lazyPage(
  () => import('@/features/catalog/products/show'),
  'ProductShowPage',
);
const CatalogsPdfPage = lazyPage(() => import('@/features/catalogs-pdf'), 'CatalogsPdfPage');
const ChannelCreatePage = lazyPage(
  () => import('@/features/channel/channels/create'),
  'ChannelCreatePage',
);
const ChannelEditPage = lazyPage(
  () => import('@/features/channel/channels/edit'),
  'ChannelEditPage',
);
const ChannelsListPage = lazyPage(
  () => import('@/features/channel/channels/list'),
  'ChannelsListPage',
);
const ChannelShowPage = lazyPage(
  () => import('@/features/channel/channels/show'),
  'ChannelShowPage',
);
const ImportsLayout = lazyPage(
  () => import('@/features/imports/layout/ImportsLayout'),
  'ImportsLayout',
);
const ImportProfilesView = lazyPage(
  () => import('@/features/imports/profiles/ImportProfilesView'),
  'ImportProfilesView',
);
const ImportScheduleView = lazyPage(
  () => import('@/features/imports/schedule/ImportScheduleView'),
  'ImportScheduleView',
);
const ImportSessionsView = lazyPage(
  () => import('@/features/imports/sessions/ImportSessionsView'),
  'ImportSessionsView',
);
const ImportShowPage = lazyPage(
  () => import('@/features/imports/show/ImportShowPage'),
  'ImportShowPage',
);
const ImportSourcesView = lazyPage(
  () => import('@/features/imports/sources/ImportSourcesView'),
  'ImportSourcesView',
);
const ImportWizardPage = lazyPage(
  () => import('@/features/imports/wizard/ImportWizardPage'),
  'ImportWizardPage',
);
// EXP-09 (#588) — Exports hub MVP. Placeholder views for sessions/profiles
// + new flow; real grids land with EXP-13/EXP-14, column picker EXP-10.
const ExportsLayout = lazyPage(
  () => import('@/features/exports/layout/ExportsLayout'),
  'ExportsLayout',
);
const ExportSessionsView = lazyPage(
  () => import('@/features/exports/sessions/ExportSessionsView'),
  'ExportSessionsView',
);
const ExportProfilesView = lazyPage(
  () => import('@/features/exports/profiles/ExportProfilesView'),
  'ExportProfilesView',
);
const ExportNewPage = lazyPage(
  () => import('@/features/exports/wizard/ExportNewPage'),
  'ExportNewPage',
);
const IntegrationsLayout = lazyPage(
  () => import('@/features/integration-hub/IntegrationsLayout'),
  'IntegrationsLayout',
);
const AiSettingsPage = lazyPage(() => import('@/features/settings/ai'), 'AiSettingsPage');
const LocalesSettingsPage = lazyPage(
  () => import('@/features/settings/locales'),
  'LocalesSettingsPage',
);
const MenuSettingsPage = lazyPage(() => import('@/features/settings/menu'), 'MenuSettingsPage');
const RolesSettingsPage = lazyPage(() => import('@/features/settings/roles'), 'RolesSettingsPage');
const SettingsIndex = lazyPage(() => import('@/features/settings/SettingsIndex'), 'SettingsIndex');
const SecuritySettingsPage = lazyPage(
  () => import('@/features/settings/security'),
  'SecuritySettingsPage',
);
const UsersSettingsPage = lazyPage(() => import('@/features/settings/users'), 'UsersSettingsPage');

/**
 * Suspense fallback shown while a route chunk loads. Discreet — the
 * lazy load typically resolves in 50–150 ms on a warm cache; a full
 * spinner would feel jankier than the brief blank slot.
 */
function RouteFallback() {
  return (
    <div
      className="flex h-full min-h-[200px] items-center justify-center text-sm text-muted-foreground"
      role="status"
      aria-live="polite"
    >
      <span className="size-2 animate-pulse rounded-full bg-muted-foreground/40" />
    </div>
  );
}

function App() {
  return (
    <BrowserRouter>
      <ToastProvider>
        <Refine
          authProvider={authProvider}
          dataProvider={dataProvider}
          resources={[
            {
              name: 'products',
              list: '/products',
              create: '/products/new',
              edit: '/products/:id/edit',
              show: '/products/:id',
            },
            {
              name: 'attributes',
              list: '/modeling/attributes',
              create: '/modeling/attributes/new',
              show: '/modeling/attributes/:id',
            },
            {
              name: 'attribute_groups',
              list: '/modeling/attribute-groups',
              create: '/modeling/attribute-groups/new',
              show: '/modeling/attribute-groups/:id',
            },
            {
              name: 'object_types',
              list: '/modeling/object-types',
              create: '/modeling/object-types/new',
              show: '/modeling/object-types/:id',
            },
            {
              name: 'workspaces',
            },
            {
              name: 'categories',
              list: '/modeling/categories',
              create: '/modeling/categories/new',
              show: '/modeling/categories/:id',
            },
            { name: 'assets', list: '/assets', show: '/assets/:id' },
            {
              name: 'channels',
              list: '/settings/channels',
              create: '/settings/channels/new',
              edit: '/settings/channels/:id/edit',
              show: '/settings/channels/:id',
            },
            { name: 'channel_object_type_mappings' },
            { name: 'locales' },
            { name: 'currencies' },
            {
              name: 'api_profiles',
              list: '/integrations/api-configurator',
              create: '/integrations/api-configurator/create',
              edit: '/integrations/api-configurator/:id/edit',
              show: '/integrations/api-configurator/:id',
            },
            {
              // RBAC-P5-001 (#691) — Settings → Users list. The page lives at
              // /settings/users; Refine resource registration drives the
              // useList wiring + future create/edit routes (#692/#693).
              name: 'users',
              list: '/settings/users',
            },
            {
              // RBAC-P5-005 (#695) — Settings → Roles list. Lists system
              // templates + tenant custom roles with user counts. Create/edit
              // routes land with #696 (custom role builder).
              name: 'roles',
              list: '/settings/roles',
            },
            {
              name: 'import-sessions',
              list: '/integrations/imports/sessions',
              show: '/integrations/imports/:id',
              create: '/integrations/imports/new',
            },
            {
              name: 'import-profiles',
              list: '/integrations/imports/profiles',
            },
            {
              name: 'import-sources',
              list: '/integrations/imports/sources',
            },
            {
              name: 'import-schedules',
              list: '/integrations/imports/schedule',
            },
            // EXP-09 (#588) — Refine resource registrations for the
            // Exports hub. List endpoints land with EXP-13 (sessions
            // grid) and EXP-14 (profiles grid).
            {
              name: 'export-sessions',
              list: '/integrations/exports/sessions',
              show: '/integrations/exports/sessions/:id',
              create: '/integrations/exports/new',
            },
            {
              name: 'export-profiles',
              list: '/integrations/exports/profiles',
            },
          ]}
          options={{
            syncWithLocation: false,
            warnWhenUnsavedChanges: true,
            disableTelemetry: true,
          }}
        >
          <Suspense fallback={<RouteFallback />}>
            <Routes>
              <Route path="/login" element={<LoginPage />} />
              <Route
                element={
                  <AuthedRoute>
                    <AppLayout />
                  </AuthedRoute>
                }
              >
                <Route index element={<Navigate to="/dashboard" replace />} />
                <Route path="/dashboard" element={<DashboardPage />} />
                <Route path="/products" element={<ProductListPage />} />
                <Route path="/products/new" element={<ProductCreatePage />} />
                {/* VIEW-07 (#420): edit page is now inline-edit on /products/:id.
                    Keep the legacy path as a back-compat redirect for bookmarks
                    and Refine resource lookups that still ask for `edit`. */}
                <Route path="/products/:id/edit" element={<RedirectToShow />} />
                <Route path="/products/:id" element={<ProductShowPage />} />
                {/* UI-08.9 (#264) — Modeling shell wraps the 4 sub-tabs under
                  a shared `/modeling/*` route tree. Old top-level paths
                  redirect below for back-compat with bookmarks + the
                  Refine resource registry pointing at the new URLs. */}
                <Route path="/modeling" element={<ModelingLayout />}>
                  <Route index element={<Navigate to="/modeling/object-types" replace />} />
                  <Route path="object-types" element={<ObjectTypesListPage />} />
                  <Route path="object-types/new" element={<ObjectTypeWizardPage />} />
                  <Route path="object-types/:id" element={<ObjectTypeShowPage />} />
                  <Route path="attributes" element={<AttributesListPage />} />
                  <Route path="attributes/new" element={<AttributeCreatePage />} />
                  <Route path="attributes/:id" element={<AttributeShowPage />} />
                  <Route
                    path="attributes/:id/migrate-type"
                    element={<MigrateAttributeTypePage />}
                  />
                  <Route path="attributes/:id/values" element={<AttributeValuesPage />} />
                  <Route path="attribute-groups" element={<AttributeGroupsListPage />} />
                  <Route path="attribute-groups/new" element={<AttributeGroupCreatePage />} />
                  <Route path="attribute-groups/:id" element={<AttributeGroupShowPage />} />
                  <Route path="categories" element={<CategoriesTreePage />} />
                  <Route path="categories/new" element={<CategoryCreatePage />} />
                  <Route path="categories/:id" element={<CategoryShowPage />} />
                </Route>
                <Route
                  path="/attributes"
                  element={<Navigate to="/modeling/attributes" replace />}
                />
                <Route
                  path="/attributes/:id"
                  element={<Navigate to="/modeling/attributes" replace />}
                />
                <Route
                  path="/attribute-groups"
                  element={<Navigate to="/modeling/attribute-groups" replace />}
                />
                <Route
                  path="/object-types"
                  element={<Navigate to="/modeling/object-types" replace />}
                />
                <Route
                  path="/object-types/:id"
                  element={<Navigate to="/modeling/object-types" replace />}
                />
                <Route
                  path="/categories"
                  element={<Navigate to="/modeling/categories" replace />}
                />
                <Route
                  path="/categories/:id"
                  element={<Navigate to="/modeling/categories" replace />}
                />
                <Route path="/assets" element={<AssetsListPage />} />
                <Route path="/assets/:id" element={<AssetShowPage />} />
                {/* VIEW-08 (#427): generic listing for custom ObjectTypes
                    promoted to the main menu. Placeholder until B-2 ships
                    the metadata-driven `<ObjectListingPage />`. */}
                <Route path="/objects/:code" element={<ObjectListingPlaceholder />} />
                <Route path="/catalogs-pdf" element={<CatalogsPdfPage />} />
                <Route path="/settings" element={<SettingsLayout />}>
                  <Route index element={<SettingsIndex />} />
                  <Route path="menu" element={<MenuSettingsPage />} />
                  <Route path="locales" element={<LocalesSettingsPage />} />
                  <Route path="channels" element={<ChannelsListPage />} />
                  <Route path="channels/new" element={<ChannelCreatePage />} />
                  <Route path="channels/:id" element={<ChannelShowPage />} />
                  <Route path="channels/:id/edit" element={<ChannelEditPage />} />
                  <Route path="users" element={<UsersSettingsPage />} />
                  <Route path="roles" element={<RolesSettingsPage />} />
                  <Route path="security" element={<SecuritySettingsPage />} />
                  <Route path="ai" element={<AiSettingsPage />} />
                </Route>
                {/* Top-level Integracje hub — łączy Imports MVP (epik 0.13)
                    + Profile API z VIEW-08 (sub-tab API Configurator). Stare
                    ścieżki /publications/* i /api-profiles/* redirectują niżej. */}
                <Route path="/integrations" element={<IntegrationsLayout />}>
                  <Route index element={<Navigate to="/integrations/imports" replace />} />
                  {/* VIEW-IMP-00 (#493) — Importy hub with 4 tabs. Old flat
                      /integrations/imports keeps working via redirect to the
                      Sessions tab (default). Wizard + show pages live at the
                      same depth so deep-links from emails/reports survive. */}
                  <Route path="imports" element={<ImportsLayout />}>
                    <Route index element={<Navigate to="sessions" replace />} />
                    <Route path="sessions" element={<ImportSessionsView />} />
                    <Route path="profiles" element={<ImportProfilesView />} />
                    <Route path="sources" element={<ImportSourcesView />} />
                    <Route path="schedule" element={<ImportScheduleView />} />
                  </Route>
                  <Route path="imports/new" element={<ImportWizardPage />} />
                  <Route path="imports/:id" element={<ImportShowPage />} />
                  {/* EXP-09 (#588) — Exports hub MVP. Tabs sessions/profiles
                      + standalone /new full-page form. Mirrors imports layout
                      depth so deep-links survive into Faza 1. */}
                  <Route path="exports" element={<ExportsLayout />}>
                    <Route index element={<Navigate to="sessions" replace />} />
                    <Route path="sessions" element={<ExportSessionsView />} />
                    <Route path="profiles" element={<ExportProfilesView />} />
                  </Route>
                  <Route path="exports/new" element={<ExportNewPage />} />
                  <Route path="api-configurator" element={<ApiProfilesListPage />} />
                  <Route path="api-configurator/create" element={<ApiProfileCreatePage />} />
                  <Route path="api-configurator/:id/edit" element={<ApiProfileEditPage />} />
                  <Route path="api-configurator/:id" element={<ApiProfileShowPage />} />
                </Route>
                {/* Back-compat redirects for bookmarks + Refine resource lookups
                    before the consolidation landed. Drop in next epik gdy
                    telemetria pokaże 0 trafień. */}
                <Route
                  path="/publications"
                  element={<Navigate to="/integrations/imports/sessions" replace />}
                />
                <Route
                  path="/publications/imports"
                  element={<Navigate to="/integrations/imports/sessions" replace />}
                />
                <Route
                  path="/publications/imports/new"
                  element={<Navigate to="/integrations/imports/new" replace />}
                />
                <Route path="/publications/imports/:id" element={<RedirectImportShow />} />
                <Route
                  path="/api-profiles"
                  element={<Navigate to="/integrations/api-configurator" replace />}
                />
                <Route
                  path="/api-profiles/create"
                  element={<Navigate to="/integrations/api-configurator/create" replace />}
                />
                <Route path="/api-profiles/:id" element={<RedirectApiProfileShow />} />
                <Route path="/api-profiles/:id/edit" element={<RedirectApiProfileEdit />} />
              </Route>
              <Route path="*" element={<Navigate to="/dashboard" replace />} />
            </Routes>
          </Suspense>
        </Refine>
      </ToastProvider>
    </BrowserRouter>
  );
}

/** VIEW-07 (#420) — back-compat redirect for `/products/:id/edit`. */
function RedirectToShow() {
  const params = useParams<{ id: string }>();
  return <Navigate to={`/products/${params.id ?? ''}`} replace />;
}

/** Publications/Integrations consolidation — back-compat for the old
 *  /publications/imports/:id, /api-profiles/:id, and /api-profiles/:id/edit
 *  bookmarks. */
function RedirectImportShow() {
  const params = useParams<{ id: string }>();
  return <Navigate to={`/integrations/imports/${params.id ?? ''}`} replace />;
}

function RedirectApiProfileShow() {
  const params = useParams<{ id: string }>();
  return <Navigate to={`/integrations/api-configurator/${params.id ?? ''}`} replace />;
}

function RedirectApiProfileEdit() {
  const params = useParams<{ id: string }>();
  return <Navigate to={`/integrations/api-configurator/${params.id ?? ''}/edit`} replace />;
}

export default App;
