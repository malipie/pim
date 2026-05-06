import { getAccessToken, refreshAccessToken } from './http';

/**
 * DAM upload helper (#438).
 *
 * `fetch()` does not surface progress events for an outgoing body — the
 * Streams API is only widely available on download — so we drop down to
 * `XMLHttpRequest` here. The dropzone needs accurate per-file progress
 * to render the upload chip; without it the UI freezes at "uploading…"
 * for multi-megabyte JPEGs.
 *
 * The helper mirrors `jsonFetch`'s 401-then-refresh-then-retry guard so
 * a token that just expired does not lose the upload (the access JWT
 * lives in module-scoped memory in `http.ts`; we read the latest value
 * for every send to pick up refreshed tokens).
 */

export const ACCEPTED_MIME_TYPES = [
  'image/jpeg',
  'image/png',
  'image/webp',
  'image/gif',
  'image/svg+xml',
  'image/avif',
  'application/pdf',
] as const;

export type AcceptedMimeType = (typeof ACCEPTED_MIME_TYPES)[number];

export const MAX_IMAGE_BYTES = 25 * 1024 * 1024;
export const MAX_PDF_BYTES = 50 * 1024 * 1024;

export function isAcceptedMimeType(mime: string): mime is AcceptedMimeType {
  return (ACCEPTED_MIME_TYPES as readonly string[]).includes(mime);
}

export function maxBytesFor(mime: string): number {
  return mime === 'application/pdf' ? MAX_PDF_BYTES : MAX_IMAGE_BYTES;
}

export interface UploadAssetResult {
  id: string;
  code: string;
  originalFilename: string;
  mimeType: string;
  size: number;
  width: number | null;
  height: number | null;
  pageCount: number | null;
  tags: string[];
  thumbnailsStatus: 'pending' | 'ready' | 'failed';
  storagePath: string;
}

export interface DuplicateAssetError {
  kind: 'duplicate';
  existingAssetId: string;
  existingCode: string;
  message: string;
}

export interface ValidationAssetError {
  kind: 'invalid' | 'unsupported_mime' | 'too_large' | 'forbidden' | 'server_error';
  status: number;
  message: string;
}

export type UploadAssetError = DuplicateAssetError | ValidationAssetError;

export interface UploadAssetOptions {
  file: File;
  code?: string;
  tags?: string[];
  /**
   * Logical folder for the upload. `product-<UUID>` is recognised by
   * the backend, which auto-links the new Asset to the product.
   */
  folderCode?: string;
  onProgress?: (loadedPercent: number) => void;
  signal?: AbortSignal;
}

export async function uploadAsset(options: UploadAssetOptions): Promise<UploadAssetResult> {
  try {
    return await sendOnce(options, false);
  } catch (error) {
    if (error instanceof UploadAuthExpiredError) {
      try {
        await refreshAccessToken();
      } catch {
        throw makeError(401, { detail: 'Authentication expired.' });
      }
      return sendOnce(options, true);
    }
    throw error;
  }
}

class UploadAuthExpiredError extends Error {}

function sendOnce(
  options: UploadAssetOptions,
  retryAfterRefresh: boolean,
): Promise<UploadAssetResult> {
  return new Promise<UploadAssetResult>((resolve, reject) => {
    const xhr = new XMLHttpRequest();
    xhr.open('POST', '/api/assets/upload');
    xhr.responseType = 'json';

    const token = getAccessToken();
    if (token) {
      xhr.setRequestHeader('authorization', `Bearer ${token}`);
    }
    xhr.setRequestHeader('accept', 'application/json');

    if (options.signal) {
      const abort = () => xhr.abort();
      if (options.signal.aborted) {
        abort();
      } else {
        options.signal.addEventListener('abort', abort, { once: true });
      }
    }

    if (options.onProgress) {
      xhr.upload.addEventListener('progress', (event) => {
        if (event.lengthComputable && event.total > 0) {
          options.onProgress?.(Math.round((event.loaded / event.total) * 100));
        }
      });
    }

    xhr.addEventListener('load', () => {
      const status = xhr.status;
      const body = xhr.response as Record<string, unknown> | null;

      if (status >= 200 && status < 300 && body) {
        resolve(body as unknown as UploadAssetResult);
        return;
      }

      if (status === 401 && !retryAfterRefresh) {
        reject(new UploadAuthExpiredError());
        return;
      }

      if (status === 409 && body && typeof body.existingAssetId === 'string') {
        reject({
          kind: 'duplicate',
          existingAssetId: body.existingAssetId,
          existingCode: typeof body.existingCode === 'string' ? body.existingCode : '',
          message:
            typeof body.detail === 'string'
              ? body.detail
              : 'Asset with this content already exists.',
        } satisfies DuplicateAssetError);
        return;
      }

      reject(makeError(status, body));
    });

    xhr.addEventListener('error', () => {
      reject(makeError(xhr.status || 0, { detail: 'Network error.' }));
    });

    xhr.addEventListener('abort', () => {
      reject(makeError(0, { detail: 'Upload aborted.' }));
    });

    const form = new FormData();
    form.append('file', options.file, options.file.name);
    if (options.code) {
      form.append('code', options.code);
    }
    if (options.tags) {
      for (const tag of options.tags) {
        form.append('tags[]', tag);
      }
    }
    if (options.folderCode) {
      form.append('folderCode', options.folderCode);
    }

    xhr.send(form);
  });
}

function makeError(status: number, body: unknown): ValidationAssetError {
  const detail = (body as { detail?: unknown } | null)?.detail;
  const message = typeof detail === 'string' ? detail : `HTTP ${status}`;

  if (status === 415) return { kind: 'unsupported_mime', status, message };
  if (status === 413) return { kind: 'too_large', status, message };
  if (status === 403) return { kind: 'forbidden', status, message };
  if (status >= 400 && status < 500) return { kind: 'invalid', status, message };
  return { kind: 'server_error', status, message };
}
