import { Refine } from '@refinedev/core';
import { BrowserRouter, Navigate, Route, Routes } from 'react-router';

import { AuthedRoute } from '@/components/AuthedRoute';
import { ComingSoon } from '@/features/_shared/coming-soon';
import { AttributeGroupsListPage } from '@/features/catalog/attribute-groups/list';
import { AttributesListPage } from '@/features/catalog/attributes/list';
import { AttributeShowPage } from '@/features/catalog/attributes/show';
import { CategoriesTreePage } from '@/features/catalog/categories/list';
import { CategoryShowPage } from '@/features/catalog/categories/show';
import { ObjectTypesListPage } from '@/features/catalog/object-types/list';
import { ObjectTypeShowPage } from '@/features/catalog/object-types/show';
import { ProductCreatePage } from '@/features/catalog/products/create';
import { ProductEditPage } from '@/features/catalog/products/edit';
import { ProductListPage } from '@/features/catalog/products/list';
import { ProductShowPage } from '@/features/catalog/products/show';
import { ChannelsListPage } from '@/features/channel/channels/list';
import { ChannelShowPage } from '@/features/channel/channels/show';
import { LoginPage } from '@/features/identity/auth/login';
import { AppLayout } from '@/layout/AppLayout';
import { authProvider } from '@/lib/auth-provider';
import { dataProvider } from '@/lib/data-provider';

function App() {
  return (
    <BrowserRouter>
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
          { name: 'attributes', list: '/attributes', show: '/attributes/:id' },
          { name: 'attribute_groups', list: '/attribute-groups' },
          { name: 'object_types', list: '/object-types', show: '/object-types/:id' },
          { name: 'categories', list: '/categories', show: '/categories/:id' },
          { name: 'assets', list: '/assets' },
          { name: 'channels', list: '/channels', show: '/channels/:id' },
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
            <Route index element={<Navigate to="/products" replace />} />
            <Route path="/products" element={<ProductListPage />} />
            <Route path="/products/new" element={<ProductCreatePage />} />
            <Route path="/products/:id/edit" element={<ProductEditPage />} />
            <Route path="/products/:id" element={<ProductShowPage />} />
            <Route path="/attributes" element={<AttributesListPage />} />
            <Route path="/attributes/:id" element={<AttributeShowPage />} />
            <Route path="/attribute-groups" element={<AttributeGroupsListPage />} />
            <Route path="/object-types" element={<ObjectTypesListPage />} />
            <Route path="/object-types/:id" element={<ObjectTypeShowPage />} />
            <Route path="/categories" element={<CategoriesTreePage />} />
            <Route path="/categories/:id" element={<CategoryShowPage />} />
            <Route
              path="/assets"
              element={<ComingSoon resource="assets" epic="0.3 / 0.6" issue={37} />}
            />
            <Route path="/channels" element={<ChannelsListPage />} />
            <Route path="/channels/:id" element={<ChannelShowPage />} />
          </Route>
          <Route path="*" element={<Navigate to="/products" replace />} />
        </Routes>
      </Refine>
    </BrowserRouter>
  );
}

export default App;
