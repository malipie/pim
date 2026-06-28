import { expect, test } from '@playwright/test';

import { loginAsAdmin } from './helpers/auth';

/**
 * APIC-P1-08 (#1768) — consumer connection wizard steps 1–2. Step 1 defines the
 * connection (name → slug code, base URL, auth) and persists a draft; step 2
 * probes it through `POST /api/connections/{id}/test`. The external probe is
 * intercepted so the test never depends on a reachable third-party host — the
 * real backend still creates the draft (AC-3), and the wiring to the test
 * endpoint + result rendering is asserted (AC-2).
 */
test('APIC-P1-08 — connection wizard: define draft then test', async ({ page }) => {
  await loginAsAdmin(page);

  await page.goto('/integrations/api-configurator/connections/new');

  // Step 1 renders with the 4-step stepper.
  await expect(
    page.getByRole('heading', { name: /nowe połączenie|new connection/i }),
  ).toBeVisible();
  await expect(page.getByRole('button', { name: /testuj|test connection/i })).toHaveCount(0);

  // Fill the connection identity — a timestamped name keeps the slug `code`
  // unique across retries (the backend enforces per-tenant uniqueness).
  const name = `E2E Conn ${Date.now()}`;
  await page.getByLabel(/nazwa połączenia|connection name/i).fill(name);
  await page.getByLabel(/^base url$/i).fill('https://api.example.com/v2');

  // Intercept the SSRF-safe probe so step 2 has a deterministic result.
  await page.route('**/connections/*/test', (route) =>
    route.fulfill({
      status: 200,
      contentType: 'application/json',
      body: JSON.stringify({
        ok: true,
        http_status: 200,
        latency_ms: 142,
        size_bytes: 1820,
        content_type: 'application/json',
        sample: '{"id":1,"sku":"A-1"}',
        status: 'active',
        checked_at: '2026-06-28T10:00:00.000+00:00',
      }),
    }),
  );

  // Leaving step 1 persists the draft (real POST) and advances to the test step.
  await page.getByRole('button', { name: /dalej|next/i }).click();

  const testButton = page.getByRole('button', { name: /testuj połączenie|test connection/i });
  await expect(testButton).toBeVisible();

  await testButton.click();
  await expect(page.getByText(/połączenie ok|connection ok/i)).toBeVisible();
  await expect(page.getByText('142 ms')).toBeVisible();
});
