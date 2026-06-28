import AxeBuilder from '@axe-core/playwright';
import { expect, type Route, test } from '@playwright/test';

import { loginAsAdmin } from './helpers/auth';

/**
 * APIC-P2-06 (#1775) — wizard steps 3–4: the endpoint descriptor builder and
 * schema discovery. The descriptor + discover APIs are mocked (the live probe
 * would need a reachable third-party host), so the test is deterministic and
 * offline: add an endpoint, fetch a sample, accept the discovered fields.
 */
test('APIC-P2-06 — wizard endpoint builder + schema discovery', async ({ page }) => {
  await loginAsAdmin(page);

  const endpoints: Array<Record<string, unknown>> = [];

  // Connection draft (step 1 → 2 persists it).
  await page.route('**/api/connections', (route: Route) => {
    if (route.request().method() === 'POST') {
      return route.fulfill({
        status: 201,
        contentType: 'application/ld+json',
        body: JSON.stringify({ id: 'conn-1', code: 'idosell', name: 'IdoSell', status: 'draft' }),
      });
    }
    return route.fallback();
  });

  // RemoteEndpoint collection (GET) + create (POST).
  await page.route('**/api/remote_endpoints*', (route: Route) => {
    const method = route.request().method();
    if (method === 'POST') {
      const body = route.request().postDataJSON() as Record<string, unknown>;
      const created = {
        id: `ep-${endpoints.length + 1}`,
        connectionId: 'conn-1',
        role: body.role ?? 'read_list',
        httpMethod: body.httpMethod ?? 'GET',
        pathTemplate: body.pathTemplate ?? '/',
        pagination: body.pagination ?? { strategy: 'none' },
        recordSelector: body.recordSelector ?? null,
      };
      endpoints.push(created);
      return route.fulfill({
        status: 201,
        contentType: 'application/ld+json',
        body: JSON.stringify(created),
      });
    }
    return route.fulfill({
      status: 200,
      contentType: 'application/ld+json',
      body: JSON.stringify({ member: endpoints, totalItems: endpoints.length }),
    });
  });

  // Schema discovery.
  await page.route('**/api/connections/*/discover', (route: Route) =>
    route.fulfill({
      status: 200,
      contentType: 'application/json',
      body: JSON.stringify({
        fields: [
          { path: '$.sku', dataType: 'string', sampleValue: 'A-1' },
          { path: '$.price.amount', dataType: 'integer', sampleValue: '1999' },
        ],
        sampleRecord: { sku: 'A-1', price: { amount: 1999 } },
        sampledRecords: 2,
      }),
    }),
  );

  // Accepted RemoteFields.
  await page.route('**/api/remote_fields', (route: Route) =>
    route.fulfill({
      status: 201,
      contentType: 'application/ld+json',
      body: JSON.stringify({ id: 'field-1' }),
    }),
  );

  await page.goto('/integrations/api-configurator/connections/new');

  // Step 1 → persist draft → step 2 (test).
  await page.getByLabel(/nazwa połączenia|connection name/i).fill('IdoSell');
  await page.getByLabel(/^base url$/i).fill('https://api.idosell.com');
  await page.getByRole('button', { name: /dalej|next/i }).click();
  await expect(
    page.getByRole('button', { name: /testuj połączenie|test connection/i }),
  ).toBeVisible();

  // Step 2 → step 3 (endpoints).
  await page.getByRole('button', { name: /dalej|next/i }).click();
  await expect(page.getByRole('button', { name: /dodaj endpoint|add endpoint/i })).toBeVisible();

  // Add a read_list endpoint and see it land in the table.
  await page.getByLabel(/^ścieżka$|^path$/i).fill('/products');
  await page.getByRole('button', { name: /dodaj endpoint|add endpoint/i }).click();
  await expect(page.getByText('/products')).toBeVisible();

  // Step 3 → step 4 (schema discovery).
  await page.getByRole('button', { name: /dalej|next/i }).click();
  await page.getByRole('button', { name: /pobierz próbkę|fetch sample/i }).click();

  // Discovered fields render; accept them.
  await expect(page.getByText('$.sku')).toBeVisible();
  await expect(page.getByText('$.price.amount')).toBeVisible();
  await page.getByRole('button', { name: /zapisz pola|save fields/i }).click();
  await expect(page.getByText(/pola zapisane|fields saved/i)).toBeVisible();

  const a11y = await new AxeBuilder({ page }).analyze();
  expect(a11y.violations).toEqual([]);
});
