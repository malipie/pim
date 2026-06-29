import AxeBuilder from '@axe-core/playwright';
import { expect, type Route, test } from '@playwright/test';

import { loginAsAdmin } from './helpers/auth';

/**
 * APIC-P3-11 (#1789) — the SyncBinding configuration screen. The binding CRUD +
 * run action are mocked (stateful), so the test is deterministic and offline:
 * switch direction (conditional panels), save, and run-now.
 */
test('APIC-P3-11 — sync config: direction panels + save + run-now', async ({ page }) => {
  await loginAsAdmin(page);

  const binding: Record<string, unknown> = {
    id: 'bind-1',
    connectionId: 'conn-1',
    objectTypeId: 'ot-1',
    readEndpointId: null,
    writeEndpointId: null,
    direction: 'inbound',
    schedule: '0 2 * * *',
    conflictPolicy: 'lww',
    matchKeyMapping: 'sku',
    cursor: { field: 'updated_at', type: 'updated_at', state: '2026-05-11T13:58:22Z' },
    isEnabled: true,
    nextRun: '2026-12-01T02:00:00+00:00',
  };
  let patched = false;
  let ran = false;

  await page.route('**/api/sync_bindings**', (route: Route) => {
    const url = route.request().url();
    const method = route.request().method();

    if (method === 'POST' && url.includes('/run')) {
      ran = true;
      return route.fulfill({
        status: 202,
        contentType: 'application/json',
        body: JSON.stringify({ dispatched: true, direction: binding.direction, next_run: null }),
      });
    }

    if (method === 'PATCH') {
      patched = true;
      const body = route.request().postDataJSON() as Record<string, unknown>;
      Object.assign(binding, body);
      return route.fulfill({
        status: 200,
        contentType: 'application/ld+json',
        body: JSON.stringify(binding),
      });
    }

    return route.fulfill({
      status: 200,
      contentType: 'application/ld+json',
      body: JSON.stringify({ member: [binding], totalItems: 1 }),
    });
  });

  await page.route('**/api/remote_endpoints**', (route: Route) =>
    route.fulfill({
      status: 200,
      contentType: 'application/ld+json',
      body: JSON.stringify({
        member: [
          {
            id: 'ep-read',
            connectionId: 'conn-1',
            role: 'read_list',
            httpMethod: 'GET',
            pathTemplate: '/products',
            pagination: { strategy: 'none' },
            recordSelector: '$.results',
          },
          {
            id: 'ep-write',
            connectionId: 'conn-1',
            role: 'write_update',
            httpMethod: 'PUT',
            pathTemplate: '/products/{id}',
            pagination: { strategy: 'none' },
            recordSelector: null,
          },
        ],
        totalItems: 2,
      }),
    }),
  );

  await page.route('**/api/object_types**', (route: Route) =>
    route.fulfill({
      status: 200,
      contentType: 'application/ld+json',
      body: JSON.stringify({ member: [{ id: 'ot-1', code: 'product' }], totalItems: 1 }),
    }),
  );

  await page.goto('/integrations/api-configurator/connections/conn-1/sync');

  await expect(
    page.getByRole('heading', {
      name: /konfiguracja synchronizacji|synchronization configuration/i,
    }),
  ).toBeVisible();

  // Inbound: cursor card present, conflict card absent. Target the conflict
  // card's unique Last-write-wins option (the section title phrase also appears
  // in the page subtitle, so it is not a reliable presence signal).
  await expect(page.getByText(/cursor \(inkrementalny|cursor \(incremental/i)).toBeVisible();
  await expect(page.getByRole('button', { name: /last-write-wins/i })).toHaveCount(0);

  // Switch to bidirectional → the conflict policy card appears.
  await page.getByRole('button', { name: /dwukierunkowy|bidirectional/i }).click();
  await expect(page.getByRole('button', { name: /last-write-wins/i })).toBeVisible();

  // Save the binding.
  await page.getByRole('button', { name: /zapisz wiązanie|save binding/i }).click();
  await expect.poll(() => patched).toBe(true);

  // Run now.
  await page.getByRole('button', { name: /uruchom teraz|run now/i }).click();
  await expect.poll(() => ran).toBe(true);

  const a11y = await new AxeBuilder({ page }).analyze();
  expect(a11y.violations).toEqual([]);
});
