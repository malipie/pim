import { expect, test } from '@playwright/test';

import { loginAsAdmin } from './helpers/auth';

/**
 * #1214 — a real-world PDF (>2 MB) must upload. PHP's default
 * upload_max_filesize=2M / post_max_size=8M truncated the multipart body
 * before UploadAssetController ran, surfacing as a broken "HTTP 200" in the
 * uploader. php.ini now raises the limits to match the advertised 50 MB PDF
 * cap (apply with `docker compose build api`).
 *
 * Generates a ~3 MB PDF in-memory (no committed fixture) and posts it to the
 * upload endpoint — should return 201, not the old truncated response.
 *
 * `fixme` in CI for the same auth rate-limiter reason as the other UI specs.
 */
const CI_BLOCKED = 'Pending storageState rollout: spec exhausts 5/15min auth rate limiter';

test('a >2MB PDF uploads successfully (PHP upload limits raised)', async ({ page }) => {
  test.fixme(!!process.env.CI, CI_BLOCKED);
  test.setTimeout(60_000);

  await loginAsAdmin(page);
  const refreshResponse = await page.request.post('/api/auth/refresh');
  expect(refreshResponse.status()).toBe(200);
  const accessToken = ((await refreshResponse.json()) as { token: string }).token;

  // ~3 MB PDF: minimal header + a large comment payload. Well over PHP's old
  // 2 MB default, well under the 50 MB PDF cap.
  const filler = Buffer.alloc(3 * 1024 * 1024, 0x41);
  const pdf = Buffer.concat([
    Buffer.from('%PDF-1.4\n1 0 obj<</Type/Catalog/Pages 2 0 R>>endobj\n%'),
    filler,
    Buffer.from('\ntrailer<</Root 1 0 R/Size 2>>\n%%EOF\n'),
  ]);

  const response = await page.request.post('/api/assets/upload', {
    headers: { Authorization: `Bearer ${accessToken}`, accept: 'application/json' },
    multipart: {
      file: { name: 'smoke-large.pdf', mimeType: 'application/pdf', buffer: pdf },
    },
  });

  expect(response.status(), await response.text()).toBe(201);
  const body = (await response.json()) as { id?: string; mimeType?: string };
  expect(body.id).toBeTruthy();
  expect(body.mimeType).toBe('application/pdf');
});
