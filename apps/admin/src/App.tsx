import { Refine } from '@refinedev/core';
import { BrowserRouter, Navigate, Route, Routes } from 'react-router';

import { AuthedRoute } from '@/components/AuthedRoute';
import { ComingSoon } from '@/features/_shared/coming-soon';
import { ProductCreatePage } from '@/features/catalog/products/create';
import { ProductEditPage } from '@/features/catalog/products/edit';
import { ProductListPage } from '@/features/catalog/products/list';
import { ProductShowPage } from '@/features/catalog/products/show';
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
          { name: 'attributes', list: '/attributes' },
          { name: 'object-types', list: '/object-types' },
          { name: 'categories', list: '/categories' },
          { name: 'assets', list: '/assets' },
          { name: 'channels', list: '/channels' },
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
            <Route
              path="/attributes"
              element={<ComingSoon resource="attributes" epic="0.3 / 0.6" issue={31} />}
            />
            <Route
              path="/object-types"
              element={<ComingSoon resource="object_types" epic="0.3 / 0.6" issue={32} />}
            />
            <Route
              path="/categories"
              element={<ComingSoon resource="categories" epic="0.3 / 0.6" issue={33} />}
            />
            <Route
              path="/assets"
              element={<ComingSoon resource="assets" epic="0.3 / 0.6" issue={37} />}
            />
            <Route
              path="/channels"
              element={<ComingSoon resource="channels" epic="0.3 / 0.6" issue={36} />}
            />
          </Route>
          <Route path="*" element={<Navigate to="/products" replace />} />
        </Routes>
      </Refine>
    </BrowserRouter>
  );
}

export default App;
