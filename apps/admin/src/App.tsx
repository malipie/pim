import { Refine } from '@refinedev/core';
import { BrowserRouter, Navigate, Route, Routes, useParams } from 'react-router';

import { AuthedRoute } from '@/components/AuthedRoute';
import { ToastProvider } from '@/components/ui/toast';
import { ApiProfileCreatePage } from '@/features/api-configurator/api-profiles/create';
import { ApiProfileEditPage } from '@/features/api-configurator/api-profiles/edit';
import { ApiProfilesListPage } from '@/features/api-configurator/api-profiles/list';
import { ApiProfileShowPage } from '@/features/api-configurator/api-profiles/show';
import { AssetsListPage } from '@/features/asset/assets/list';
import { AssetShowPage } from '@/features/asset/assets/show';
import { AttributeGroupCreatePage } from '@/features/catalog/attribute-groups/create';
import { AttributeGroupsListPage } from '@/features/catalog/attribute-groups/list';
import { AttributeGroupShowPage } from '@/features/catalog/attribute-groups/show';
import { AttributesListPage } from '@/features/catalog/attributes/list';
import { MigrateAttributeTypePage } from '@/features/catalog/attributes/migrate-type';
import { AttributeCreatePage } from '@/features/catalog/attributes/new';
import { AttributeShowPage } from '@/features/catalog/attributes/show';
import { AttributeValuesPage } from '@/features/catalog/attributes/values';
import { CategoriesTreePage } from '@/features/catalog/categories/list';
import { CategoryCreatePage } from '@/features/catalog/categories/new';
import { CategoryShowPage } from '@/features/catalog/categories/show';
import { ModelingLayout } from '@/features/catalog/modeling/layout';
import { ObjectTypesListPage } from '@/features/catalog/object-types/list';
import { ObjectTypeWizardPage } from '@/features/catalog/object-types/new';
import { ObjectTypeShowPage } from '@/features/catalog/object-types/show';
import { ObjectListingPlaceholder } from '@/features/catalog/objects/placeholder';
import { ProductCreatePage } from '@/features/catalog/products/create';
import { ProductListPage } from '@/features/catalog/products/list';
import { ProductShowPage } from '@/features/catalog/products/show';
import { CatalogsPdfPage } from '@/features/catalogs-pdf';
import { ChannelCreatePage } from '@/features/channel/channels/create';
import { ChannelEditPage } from '@/features/channel/channels/edit';
import { ChannelsListPage } from '@/features/channel/channels/list';
import { ChannelShowPage } from '@/features/channel/channels/show';
import { DashboardPage } from '@/features/dashboard/page';
import { LoginPage } from '@/features/identity/auth/login';
import { ImportsLayout } from '@/features/imports/layout/ImportsLayout';
import { ImportsListView } from '@/features/imports/list/ImportsListView';
import { ImportProfilesPlaceholder } from '@/features/imports/profiles/ImportProfilesPlaceholder';
import { ImportSchedulePlaceholder } from '@/features/imports/schedule/ImportSchedulePlaceholder';
import { ImportShowPage } from '@/features/imports/show/ImportShowPage';
import { ImportSourcesPlaceholder } from '@/features/imports/sources/ImportSourcesPlaceholder';
import { ImportWizardPage } from '@/features/imports/wizard/ImportWizardPage';
import { IntegrationsLayout } from '@/features/integration-hub/IntegrationsLayout';
import { AiSettingsPage } from '@/features/settings/ai';
import { LocalesSettingsPage } from '@/features/settings/locales';
import { MenuSettingsPage } from '@/features/settings/menu';
import { RolesSettingsPage } from '@/features/settings/roles';
import { SettingsIndex } from '@/features/settings/SettingsIndex';
import { SecuritySettingsPage } from '@/features/settings/security';
import { UsersSettingsPage } from '@/features/settings/users';
import { AppLayout } from '@/layout/AppLayout';
import { SettingsLayout } from '@/layout/SettingsLayout';
import { authProvider } from '@/lib/auth-provider';
import { dataProvider } from '@/lib/data-provider';

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
              name: 'import-sessions',
              list: '/integrations/imports/sessions',
              show: '/integrations/imports/:id',
              create: '/integrations/imports/new',
            },
            {
              name: 'import-profiles',
              list: '/integrations/imports/profiles',
            },
          ]}
          options={{
            syncWithLocation: false,
            warnWhenUnsavedChanges: true,
            disableTelemetry: true,
          }}
        >
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
                <Route path="attributes/:id/migrate-type" element={<MigrateAttributeTypePage />} />
                <Route path="attributes/:id/values" element={<AttributeValuesPage />} />
                <Route path="attribute-groups" element={<AttributeGroupsListPage />} />
                <Route path="attribute-groups/new" element={<AttributeGroupCreatePage />} />
                <Route path="attribute-groups/:id" element={<AttributeGroupShowPage />} />
                <Route path="categories" element={<CategoriesTreePage />} />
                <Route path="categories/new" element={<CategoryCreatePage />} />
                <Route path="categories/:id" element={<CategoryShowPage />} />
              </Route>
              <Route path="/attributes" element={<Navigate to="/modeling/attributes" replace />} />
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
              <Route path="/categories" element={<Navigate to="/modeling/categories" replace />} />
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
                  <Route path="sessions" element={<ImportsListView />} />
                  <Route path="profiles" element={<ImportProfilesPlaceholder />} />
                  <Route path="sources" element={<ImportSourcesPlaceholder />} />
                  <Route path="schedule" element={<ImportSchedulePlaceholder />} />
                </Route>
                <Route path="imports/new" element={<ImportWizardPage />} />
                <Route path="imports/:id" element={<ImportShowPage />} />
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
