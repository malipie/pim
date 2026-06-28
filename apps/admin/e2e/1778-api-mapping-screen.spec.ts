import AxeBuilder from '@axe-core/playwright';
import { expect, type Route, test } from '@playwright/test';

import { loginAsAdmin } from './helpers/auth';

/**
 * APIC-P2-09 (#1778) — the 1:1 field mapping screen. The mapping + descriptor +
 * validate APIs are mocked (stateful), so the test is deterministic and offline:
 * add a mapping, toggle its match key, and check the discovered-field pools.
 */
test('APIC-P2-09 — field mapper: add mapping + toggle match key', async ({ page }) => {
  await loginAsAdmin(page);

  const mappings: Array<Record<string, unknown>> = [];

  await page.route('**/api/field_mappings**', (route: Route) => {
    const url = route.request().url();
    const method = route.request().method();
    const match = url.match(/\/field_mappings\/([^/?]+)/);

    if (method === 'POST' && match === null) {
      const body = route.request().postDataJSON() as Record<string, unknown>;
      const created = {
        id: `map-${mappings.length + 1}`,
        connectionId: 'conn-1',
        pimTarget: body.pimTarget ?? 'sku',
        remoteFieldPath: body.remoteFieldPath ?? '$.sku',
        direction: body.direction ?? 'inbound',
        isMatchKey: body.isMatchKey ?? false,
        version: 1,
      };
      mappings.push(created);
      return route.fulfill({
        status: 201,
        contentType: 'application/ld+json',
        body: JSON.stringify(created),
      });
    }

    if (method === 'PATCH' && match !== null) {
      const id = match[1];
      const body = route.request().postDataJSON() as Record<string, unknown>;
      const row = mappings.find((m) => m.id === id);
      if (row !== undefined) {
        Object.assign(row, body, { version: (row.version as number) + 1 });
      }
      return route.fulfill({
        status: 200,
        contentType: 'application/ld+json',
        body: JSON.stringify(row ?? {}),
      });
    }

    return route.fulfill({
      status: 200,
      contentType: 'application/ld+json',
      body: JSON.stringify({ member: mappings, totalItems: mappings.length }),
    });
  });

  await page.route('**/api/remote_endpoints**', (route: Route) =>
    route.fulfill({
      status: 200,
      contentType: 'application/ld+json',
      body: JSON.stringify({
        member: [
          {
            id: 'ep-1',
            connectionId: 'conn-1',
            role: 'read_list',
            httpMethod: 'GET',
            pathTemplate: '/products',
            pagination: { strategy: 'none' },
            recordSelector: '$.results',
          },
        ],
        totalItems: 1,
      }),
    }),
  );

  await page.route('**/api/remote_fields**', (route: Route) =>
    route.fulfill({
      status: 200,
      contentType: 'application/ld+json',
      body: JSON.stringify({
        member: [
          { id: 'rf-1', path: '$.sku', dataType: 'string' },
          { id: 'rf-2', path: '$.name', dataType: 'string' },
        ],
        totalItems: 2,
      }),
    }),
  );

  await page.route('**/api/connections/*/mappings/validate', (route: Route) =>
    route.fulfill({
      status: 200,
      contentType: 'application/json',
      body: JSON.stringify({ valid: true, errors: [], warnings: [] }),
    }),
  );

  await page.goto('/integrations/api-configurator/connections/conn-1/mapping');

  await expect(page.getByRole('heading', { name: /mapowanie pól|field mapping/i })).toBeVisible();

  // Add a mapping.
  await page.getByLabel(/atrybut pim|pim attribute/i).fill('sku');
  await page.getByLabel(/pole zewnętrzne|external field/i).fill('$.sku');
  await page.getByRole('button', { name: /dodaj mapowanie 1:1|add 1:1 mapping/i }).click();

  await expect(page.getByText('sku', { exact: true })).toBeVisible();
  await expect(page.getByText('$.sku', { exact: true })).toBeVisible();

  // Toggle the match key → the badge appears after the optimistic refetch.
  await page.getByRole('button', { name: /^key$/i }).click();
  await expect(page.getByText(/match key/i)).toBeVisible();

  const a11y = await new AxeBuilder({ page }).analyze();
  expect(a11y.violations).toEqual([]);
});
