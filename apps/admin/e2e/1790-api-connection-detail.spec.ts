import AxeBuilder from '@axe-core/playwright';
import { expect, type Route, test } from '@playwright/test';

import { loginAsAdmin } from './helpers/auth';

/**
 * APIC-P3-12 (#1790) — the connection detail with five deep-linkable tabs. All
 * resources are mocked, so the test is deterministic and offline: switch tabs
 * (overview → endpoints → history) and drill into a run's records.
 */
test('APIC-P3-12 — connection detail: tab navigation + history drill-down', async ({ page }) => {
  await loginAsAdmin(page);

  const connection = {
    id: 'conn-1',
    code: 'idosell',
    name: 'IdoSell EU',
    baseUrl: 'https://api.idosell.example',
    authType: 'api_key',
    rateLimitHint: 40,
    status: 'active',
  };
  const binding = {
    id: 'bind-1',
    connectionId: 'conn-1',
    direction: 'inbound',
    schedule: '0 2 * * *',
    conflictPolicy: 'lww',
    matchKeyMapping: 'sku',
    cursor: { field: 'updated_at', type: 'updated_at', state: 'x' },
    isEnabled: true,
    nextRun: '2026-12-01T02:00:00+00:00',
  };
  const run = {
    id: 'run-1',
    bindingId: 'bind-1',
    direction: 'inbound',
    startedAt: '2026-06-20T10:00:00+00:00',
    finishedAt: '2026-06-20T10:01:00+00:00',
    status: 'success',
    createdCount: 3,
    updatedCount: 2,
    skippedCount: 0,
    failedCount: 0,
    cursorAfter: { state: 'y' },
  };

  await page.route('**/api/connections/conn-1**', (r: Route) => {
    if (r.request().url().includes('/test')) {
      return r.fulfill({ status: 200, contentType: 'application/json', body: '{"ok":true}' });
    }
    return r.fulfill({
      status: 200,
      contentType: 'application/ld+json',
      body: JSON.stringify(connection),
    });
  });
  await page.route('**/api/sync_bindings**', (r: Route) => {
    if (r.request().url().includes('/run')) {
      return r.fulfill({
        status: 202,
        contentType: 'application/json',
        body: '{"dispatched":true}',
      });
    }
    return r.fulfill({
      status: 200,
      contentType: 'application/ld+json',
      body: JSON.stringify({ member: [binding], totalItems: 1 }),
    });
  });
  await page.route('**/api/sync_runs**', (r: Route) =>
    r.fulfill({
      status: 200,
      contentType: 'application/ld+json',
      body: JSON.stringify({ member: [run], totalItems: 1 }),
    }),
  );
  await page.route('**/api/sync_run_logs**', (r: Route) =>
    r.fulfill({
      status: 200,
      contentType: 'application/ld+json',
      body: JSON.stringify({
        member: [
          {
            id: 'log-1',
            runId: 'run-1',
            matchKey: 'SKU-1',
            action: 'created',
            message: null,
            createdAt: run.startedAt,
          },
        ],
        totalItems: 1,
      }),
    }),
  );
  await page.route('**/api/remote_endpoints**', (r: Route) =>
    r.fulfill({
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
  await page.route('**/api/field_mappings**', (r: Route) =>
    r.fulfill({
      status: 200,
      contentType: 'application/ld+json',
      body: '{"member":[],"totalItems":0}',
    }),
  );
  await page.route('**/api/object_types**', (r: Route) =>
    r.fulfill({
      status: 200,
      contentType: 'application/ld+json',
      body: '{"member":[{"id":"ot-1","code":"product"}],"totalItems":1}',
    }),
  );

  await page.goto('/integrations/api-configurator/connections/conn-1');

  // Header identity.
  await expect(page.getByRole('heading', { name: 'IdoSell EU' })).toBeVisible();

  // Overview (default tab): recent-runs section.
  await expect(page.getByText(/ostatnie synchronizacje|recent syncs/i)).toBeVisible();

  // Endpoints tab.
  await page.getByRole('tab', { name: /endpointy|endpoints/i }).click();
  await expect(page.getByText('/products', { exact: true })).toBeVisible();

  // History tab → drill into the run.
  await page.getByRole('tab', { name: /historia|history/i }).click();
  const runRow = page.getByRole('button', { name: /success|sukces/i }).first();
  await runRow.click();
  await expect(page.getByText('SKU-1', { exact: true })).toBeVisible();

  const a11y = await new AxeBuilder({ page }).analyze();
  expect(a11y.violations).toEqual([]);
});
