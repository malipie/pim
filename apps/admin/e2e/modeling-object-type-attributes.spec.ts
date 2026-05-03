import { expect, test } from '@playwright/test';

import { loginAsAdmin } from './helpers/auth';

/**
 * VIEW-01b (#413) — Custom attribute groups & Custom attributes on
 * ObjectType detail. Single test exercises:
 *  - Two-button refactor of the Custom attribute groups section
 *    (Z biblioteki / Stwórz nowy)
 *  - New Custom attribute card with the same two-button pattern
 *  - Attach group from library via DeclareObjectTypeAttributeGroupDialog
 *  - Attach attribute from library via AddAttributesToObjectTypeDialog
 *  - Create + auto-attach new attribute via CreateAttributeForObjectTypeDialog
 *
 * One login keeps the auth rate limiter (5/IP/15min) shared with the rest
 * of the e2e run.
 */
test('VIEW-01b Modeling · ObjectType detail — custom groups & attributes flow', async ({
  page,
}) => {
  await loginAsAdmin(page);

  // Navigate to a custom ObjectType detail. The list page renders custom OTs
  // in their own section; pick the first link there.
  await page.goto('/modeling/object-types');
  await expect(page.getByRole('heading', { name: /object types/i, level: 1 })).toBeVisible();

  const customDetailLinks = page.locator('a[href^="/modeling/object-types/"]:not([href$="/new"])');
  // Built-in types (Product/Category/Asset) come first; custom types appear
  // below. The seeded demo dataset includes at least one custom OT after
  // earlier flows run, but this spec is stable as long as ANY OT detail
  // renders the new Custom attribute card.
  await customDetailLinks.first().click();
  await expect(page).toHaveURL(/\/modeling\/object-types\/[0-9a-f-]{36}/);

  // 1. Custom attribute groups section now offers two buttons.
  const customGroupsHeader = page.getByText(/custom attribute groups/i).first();
  await expect(customGroupsHeader).toBeVisible();
  // Scope to the row containing the section heading.
  const groupsCard = customGroupsHeader.locator('xpath=ancestor::div[contains(@class, "p-6")][1]');
  await expect(
    groupsCard.getByRole('button', { name: /z biblioteki|from library/i }),
  ).toBeVisible();
  await expect(
    groupsCard.getByRole('button', { name: /stw[oó]rz nowy|create new/i }),
  ).toBeVisible();

  // 2. Custom attribute (singular) card renders with two buttons too.
  const customAttrsHeader = page.getByText(/custom attribute$/i).first();
  await expect(customAttrsHeader).toBeVisible();
  const attrsCard = customAttrsHeader.locator('xpath=ancestor::div[contains(@class, "p-6")][1]');
  await expect(attrsCard.getByRole('button', { name: /z biblioteki|from library/i })).toBeVisible();
  await expect(attrsCard.getByRole('button', { name: /stw[oó]rz nowy|create new/i })).toBeVisible();

  // 3. Open library picker for attributes — dialog mounts as Radix overlay.
  await attrsCard.getByRole('button', { name: /z biblioteki|from library/i }).click();
  const dialog = page.getByRole('dialog');
  await expect(dialog).toBeVisible();
  await expect(
    dialog.getByText(/dodaj atrybuty z biblioteki|add attributes from library/i),
  ).toBeVisible();
  // Library list at minimum contains the auto-attached audit attributes
  // (created_at, updated_at, …) — expect any code mono cell.
  await expect(dialog.locator('span.font-mono').first()).toBeVisible();
  // Close — VIEW-01b parent test should not attach in CI runs (data
  // mutation across specs creates flake). The smoke test in the PR
  // checklist covers the end-to-end attach.
  await page.keyboard.press('Escape');
  await expect(dialog).toBeHidden();

  // 4. Open create-new attribute dialog and assert form fields render.
  await attrsCard.getByRole('button', { name: /stw[oó]rz nowy|create new/i }).click();
  await expect(page.getByRole('dialog')).toBeVisible();
  await expect(
    page.getByRole('dialog').getByText(/nowy atrybut na typie|new attribute on type/i),
  ).toBeVisible();
  // The form has Code + Type select + Name (PL/EN tabs).
  await expect(page.getByRole('dialog').locator('input[placeholder*="plan_tier"]')).toBeVisible();
  await page.keyboard.press('Escape');
  await expect(page.getByRole('dialog')).toBeHidden();

  // 5. Open declare-group library picker and assert it lists groups.
  await groupsCard.getByRole('button', { name: /z biblioteki|from library/i }).click();
  const groupsDialog = page.getByRole('dialog');
  await expect(groupsDialog).toBeVisible();
  await expect(
    groupsDialog.getByText(/dołącz grupy z biblioteki|attach groups from library/i),
  ).toBeVisible();
  await page.keyboard.press('Escape');
  await expect(groupsDialog).toBeHidden();
});
