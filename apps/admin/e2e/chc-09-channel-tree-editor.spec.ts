import { expect, test } from '@playwright/test';

import { loginAsAdmin } from './helpers/auth';

/**
 * CHC-09 (#1302) — manual channel category-tree editor on the "Drzewo kanału"
 * tab: create tree → add sub-nodes → edit (name + external id) → move → delete.
 *
 * `fixme` in CI for the same auth rate-limiter reason as the other channel
 * detail specs (#1209) — runs locally against `pnpm stack:up`.
 *
 * Actions target the root via `.first()` (the root row renders first, so its
 * action buttons are first in DOM order); "move" targets the first non-root.
 */
const CI_BLOCKED = 'Pending storageState rollout: spec exhausts 5/15min auth rate limiter';

test('build, edit, move and delete a channel navigation tree from the UI', async ({ page }) => {
  test.fixme(!!process.env.CI, CI_BLOCKED);
  test.setTimeout(120_000);

  await loginAsAdmin(page);

  const refreshResponse = await page.request.post('/api/auth/refresh');
  expect(refreshResponse.status()).toBe(200);
  const accessToken = ((await refreshResponse.json()) as { token: string }).token;
  const bearer = { Authorization: `Bearer ${accessToken}` };

  // A channel + a clean slate (failed runs skip cleanup).
  const channelsBody = (await (
    await page.request.get('/api/channels', { headers: { ...bearer, accept: 'application/json' } })
  ).json()) as { member?: Array<{ id: string }> } | Array<{ id: string }>;
  const channels = Array.isArray(channelsBody) ? channelsBody : (channelsBody.member ?? []);
  expect(channels.length).toBeGreaterThan(0);
  const channelId = channels[0].id;

  const existing = (await (
    await page.request.get(`/api/channels/${channelId}/navigation-tree`, {
      headers: { ...bearer, accept: 'application/json' },
    })
  ).json()) as Array<{ id: string; parentId: string | null }>;
  for (const root of existing.filter((n) => n.parentId === null)) {
    await page.request.delete(`/api/channels/${channelId}/navigation-tree/nodes/${root.id}`, {
      headers: bearer,
    });
  }

  await page.goto(`/settings/channels/${channelId}`);
  await page.getByRole('button', { name: /Drzewo kanału|Channel tree/i }).click();

  const saveName = async (name: string, external?: string) => {
    const dialog = page.getByRole('dialog');
    await dialog.locator('#chc-node-name').fill(name);
    if (external !== undefined) await dialog.locator('#chc-node-external').fill(external);
    await dialog.getByRole('button', { name: /^(Save|Zapisz)$/ }).click();
  };

  // 1. Create the tree (root).
  await page.getByRole('button', { name: /Utwórz drzewo|Create tree/i }).click();
  await saveName('ROOT09');
  const editor = page.getByTestId('chc-tree-editor');
  await expect(editor).toBeVisible();
  await expect(editor.getByText('ROOT09')).toBeVisible();

  // 2. Edit the root (first edit button) — rename + set external id.
  await editor
    .getByRole('button', { name: /^(Edit|Edytuj)$/ })
    .first()
    .click();
  await saveName('ROOT09X', '999');
  await expect(editor.getByText('ROOT09X')).toBeVisible();
  await expect(editor.getByText('#999')).toBeVisible();

  // 3. Add two sub-nodes under the root (root's add button is first).
  await editor
    .getByRole('button', { name: /Add sub-node|Dodaj podpozycję/i })
    .first()
    .click();
  await saveName('NODEA09');
  await expect(editor.getByText('NODEA09')).toBeVisible();
  await editor
    .getByRole('button', { name: /Add sub-node|Dodaj podpozycję/i })
    .first()
    .click();
  await saveName('NODEB09');
  await expect(editor.getByText('NODEB09')).toBeVisible();

  // 4. Move NODEA09 (first non-root → first move button) under NODEB09.
  await editor
    .getByRole('button', { name: /^(Move|Przenieś)$/ })
    .first()
    .click();
  const moveResponse = page.waitForResponse(
    (r) => /\/nodes\/.+\/move$/.test(r.url()) && r.request().method() === 'PATCH',
  );
  await page
    .getByRole('dialog')
    .getByRole('button', { name: /NODEB09/i })
    .click();
  expect((await moveResponse).status()).toBe(200);

  // 5. Delete the whole tree (root's delete → cascade) and land back on empty state.
  await editor
    .getByRole('button', { name: /^(Delete|Usuń)$/ })
    .first()
    .click();
  const deleteResponse = page.waitForResponse(
    (r) => /\/navigation-tree\/nodes\//.test(r.url()) && r.request().method() === 'DELETE',
  );
  await page
    .getByRole('dialog')
    .getByRole('button', { name: /^(Delete|Usuń)$/ })
    .click();
  expect((await deleteResponse).status()).toBe(204);
  await expect(page.getByRole('button', { name: /Utwórz drzewo|Create tree/i })).toBeVisible();

  // cleanup — ensure no leftover tree.
  const after = (await (
    await page.request.get(`/api/channels/${channelId}/navigation-tree`, {
      headers: { ...bearer, accept: 'application/json' },
    })
  ).json()) as Array<{ id: string; parentId: string | null }>;
  for (const root of after.filter((n) => n.parentId === null)) {
    await page.request.delete(`/api/channels/${channelId}/navigation-tree/nodes/${root.id}`, {
      headers: bearer,
    });
  }
});
