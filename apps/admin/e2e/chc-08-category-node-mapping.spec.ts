import { expect, test } from '@playwright/test';

import { loginAsAdmin } from './helpers/auth';

/**
 * CHC-08 (#1291) — channel category mapping split-view: map a master category
 * to a channel navigation node from the "Kategorie kanału" tab, which drives
 * CHC-07 auto-assignment.
 *
 * `fixme` in CI for the same auth rate-limiter reason as the other product/
 * channel detail specs (#1209) — runs locally against `pnpm stack:up`.
 */
const CI_BLOCKED = 'Pending storageState rollout: spec exhausts 5/15min auth rate limiter';

test('map a master category to a channel node from the channel mapping tab', async ({ page }) => {
  test.fixme(!!process.env.CI, CI_BLOCKED);
  test.setTimeout(120_000);

  await loginAsAdmin(page);

  const refreshResponse = await page.request.post('/api/auth/refresh');
  expect(refreshResponse.status()).toBe(200);
  const accessToken = ((await refreshResponse.json()) as { token: string }).token;
  const bearer = { Authorization: `Bearer ${accessToken}` };
  const json = { ...bearer, 'content-type': 'application/json', accept: 'application/json' };

  // 1. A channel.
  const channelsBody = (await (
    await page.request.get('/api/channels', { headers: { ...bearer, accept: 'application/json' } })
  ).json()) as { member?: Array<{ id: string }> } | Array<{ id: string }>;
  const channels = Array.isArray(channelsBody) ? channelsBody : (channelsBody.member ?? []);
  expect(channels.length).toBeGreaterThan(0);
  const channelId = channels[0].id;

  // 2. A navigation tree (root + one node) on that channel.
  const rootResp = await page.request.post(`/api/channels/${channelId}/navigation-tree`, {
    headers: json,
    data: { label: { pl: 'Korzeń CHC08' } },
  });
  expect([201, 409]).toContain(rootResp.status());
  const tree = (await (
    await page.request.get(`/api/channels/${channelId}/navigation-tree`, {
      headers: { ...bearer, accept: 'application/json' },
    })
  ).json()) as Array<{ id: string; parentId: string | null }>;
  const rootId = tree.find((n) => n.parentId === null)?.id;
  if (rootId === undefined) throw new Error('Navigation root missing after creation.');

  await page.request.post(`/api/channels/${channelId}/navigation-tree/nodes`, {
    headers: json,
    data: { parentId: rootId, code: 'chc08_node', label: { pl: 'CHC08 Węzeł' } },
  });

  // Start from a clean mapping slate so assertions are deterministic.
  await page.request.delete(`/api/channels/${channelId}/node-mappings`, { headers: bearer });

  // 3. Open the channel "Kategorie kanału" tab.
  await page.goto(`/settings/channels/${channelId}`);
  await page.getByRole('button', { name: /Kategorie kanału/i }).click();

  await expect(page.getByTestId('chc-master-list')).toBeVisible();
  await expect(page.getByTestId('chc-channel-tree')).toBeVisible();
  await expect(page.getByText(/CHC08 Węzeł/i)).toBeVisible();

  // 4. Map the first master category to the node via the dialog.
  await page.locator('[data-testid^="chc-map-"]').first().click();
  const dialog = page.getByRole('dialog');
  await expect(dialog).toBeVisible();

  await dialog.getByRole('button', { name: /wybierz węzły kanału/i }).click();
  await dialog.getByRole('button', { name: /CHC08 Węzeł/i }).click();

  const putResponse = page.waitForResponse(
    (r) => /\/node-mappings\//.test(r.url()) && r.request().method() === 'PUT',
  );
  await dialog.getByRole('button', { name: /^Zapisz$/ }).click();
  expect((await putResponse).status()).toBe(200);

  // 5. The mapped-count summary surfaces on a master row.
  await expect(page.getByText(/zmapowano do/i).first()).toBeVisible();

  // cleanup — clear the mappings and the navigation tree we created.
  await page.request.delete(`/api/channels/${channelId}/node-mappings`, { headers: bearer });
  await page.request.delete(`/api/channels/${channelId}/navigation-tree/nodes/${rootId}`, {
    headers: bearer,
  });
});
