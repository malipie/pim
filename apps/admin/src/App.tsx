import { Refine } from '@refinedev/core';
import { BrowserRouter, Navigate, Route, Routes } from 'react-router';

import { AuthedRoute } from '@/components/AuthedRoute';
import { AppLayout } from '@/layout/AppLayout';
import { authProvider } from '@/lib/auth-provider';
import { dataProvider } from '@/lib/data-provider';
import { LoginPage } from '@/pages/login';
import { ProductCreatePage } from '@/pages/products/create';
import { ProductEditPage } from '@/pages/products/edit';
import { ProductListPage } from '@/pages/products/list';

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
            <Route index element={<Navigate to="/products" replace />} />
            <Route path="/products" element={<ProductListPage />} />
            <Route path="/products/new" element={<ProductCreatePage />} />
            <Route path="/products/:id/edit" element={<ProductEditPage />} />
          </Route>
          <Route path="*" element={<Navigate to="/products" replace />} />
        </Routes>
      </Refine>
    </BrowserRouter>
  );
}

export default App;
