import { getAccessToken } from '@/lib/http';

/**
 * Downloads a JWT-guarded file endpoint via fetch + blob anchor.
 *
 * window.open() / a plain <a href> drop the Authorization header, so
 * #[RequiresPermission]-guarded endpoints respond 401 in a new tab.
 * Throws on non-2xx so callers can surface a toast.
 */
export async function downloadWithAuth(path: string, fallbackFilename: string): Promise<void> {
  const token = getAccessToken();
  const headers: Record<string, string> = { accept: '*/*' };
  if (token !== null) {
    headers.authorization = `Bearer ${token}`;
  }
  const response = await fetch(path, {
    method: 'GET',
    headers,
    credentials: 'same-origin',
  });
  if (!response.ok) {
    throw new Error(`Download failed: HTTP ${response.status}`);
  }
  const blob = await response.blob();
  const filename = parseFilename(response.headers.get('content-disposition')) ?? fallbackFilename;
  const url = URL.createObjectURL(blob);
  const anchor = document.createElement('a');
  anchor.href = url;
  anchor.download = filename;
  document.body.appendChild(anchor);
  anchor.click();
  document.body.removeChild(anchor);
  setTimeout(() => URL.revokeObjectURL(url), 1000);
}

function parseFilename(header: string | null): string | null {
  if (header === null) return null;
  const match = /filename\*?=(?:UTF-8'')?"?([^";]+)"?/i.exec(header);
  return match?.[1] !== undefined ? decodeURIComponent(match[1]) : null;
}
