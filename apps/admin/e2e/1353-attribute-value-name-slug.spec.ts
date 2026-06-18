import { expect, test } from '@playwright/test';

import { ADMIN_EMAIL, ADMIN_PASSWORD, apiLogin } from './helpers/auth';

/**
 * #1353 — the quick-add input in the attribute value editor was labelled
 * "code (snake_case)", confusing operators into typing a name. It now
 * asks for a "Nazwa" and derives the immutable snake_case `code`
 * automatically. This spec types a diacritic-rich name and asserts the
 * POST carries a slugified code + the typed name as the label.
 *
 * Marked `fixme` in CI for the shared `storageState` rate-limiter reason.
 */

test('attribute value quick-add takes a name and auto-derives the code', async ({ page }) => {
  test.setTimeout(120_000);

  const loginResponse = await page.request.post('/api/auth/login', {
    data: { email: ADMIN_EMAIL, password: ADMIN_PASSWORD },
    headers: { accept: 'application/json' },
  });
  expect(loginResponse.status()).toBe(200);
  const { token } = (await loginResponse.json()) as { token: string };
  const bearer = { authorization: `Bearer ${token}` };

  await apiLogin(page);

  const code = `zz_slug_${Date.now().toString(36).toLowerCase()}`;
  const created = await page.request.post('/api/attributes', {
    headers: { ...bearer, 'content-type': 'application/ld+json' },
    data: { code, type: 'select', label: { pl: 'Slug spec', en: 'Slug spec' } },
  });
  expect(created.status()).toBe(201);
  const attribute = (await created.json()) as { id: string };

  await page.goto(`/modeling/attributes/${attribute.id}/values`);

  const addButton = page.getByRole('button', { name: /dodaj wartość|add value/i });
  await expect(addButton).toBeVisible({ timeout: 15_000 });
  await addButton.click();

  const draftInput = page.getByPlaceholder(/^nazwa$/i);
  await expect(draftInput).toBeVisible();
  await draftInput.fill('Żarówka LED');

  const postPromise = page.waitForResponse(
    (r) => r.url().includes('/options') && r.request().method() === 'POST',
    { timeout: 15_000 },
  );
  await draftInput.press('Enter');
  const post = await postPromise;
  expect(post.status()).toBe(201);
  const sent = post.request().postDataJSON() as { code: string; label: Record<string, string> };
  expect(sent.code).toBe('zarowka_led');
  expect(sent.label.pl).toBe('Żarówka LED');

  // The new value renders by its human-readable label.
  await expect(page.getByText('Żarówka LED').first()).toBeVisible({ timeout: 15_000 });

  await page.request.delete(`/api/attributes/${attribute.id}`, { headers: bearer });
});
