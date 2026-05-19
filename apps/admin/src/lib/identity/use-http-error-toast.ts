import { useCallback } from 'react';
import { useTranslation } from 'react-i18next';

import { useToast } from '@/components/ui/toast';
import { HttpError } from '@/lib/http';

/**
 * RBAC-P4-007 (#684) + RBAC-P4-008 (#685) — centralised translation
 * from `HttpError` → user-visible toast.
 *
 * Mutations and queries already throw `HttpError` from
 * {@see jsonFetch} so the surface they need to wire is one onError
 * callback that delegates to this helper:
 *
 *   const showHttpError = useHttpErrorToast();
 *   const mutation = useMutation({
 *     ...,
 *     onError: showHttpError,
 *   });
 *
 * Status routing:
 *
 *   - 401 → AuthProvider.onError already logs the user out + redirects
 *           to /login. The toast here is the post-redirect confirmation
 *           ("Session expired"); it stays brief because the user is
 *           about to see the login form anyway.
 *   - 403 → permission denied for the *action* (not the route — that
 *           is PermissionRoute's job, #680). Toast surfaces the deny
 *           with a hint to contact the admin. React Query's automatic
 *           optimistic rollback (when `onMutate` set the optimistic
 *           state) reverses the optimistic write — the toast is the
 *           visible feedback.
 *   - 409 → reserved for conflict-style RBAC denies (last-admin,
 *           owner-uniqueness, system-role-protection — see #668 doc).
 *           Toast text mirrors the backend reason when present.
 *   - other → generic "Something went wrong" fallback so the surface
 *             stays consistent.
 *
 * Non-HttpError exceptions (network failures, JSON parse errors)
 * receive the generic fallback because the SPA cannot distinguish
 * them at the call site.
 */
export function useHttpErrorToast() {
  const toast = useToast();
  const { t } = useTranslation();

  return useCallback(
    (error: unknown) => {
      if (!(error instanceof HttpError)) {
        toast.error(t('http.error.generic', 'Something went wrong. Please try again.'));
        return;
      }

      switch (error.status) {
        case 401:
          toast.info(t('http.error.unauthorized', 'Your session expired. Please sign in again.'));
          return;
        case 403: {
          const detail = readDetail(error);
          toast.error(
            detail ??
              t(
                'http.error.forbidden',
                'You do not have permission to perform this action. Contact your tenant administrator if you believe this is a mistake.',
              ),
          );
          return;
        }
        case 409: {
          const detail = readDetail(error);
          toast.error(detail ?? t('http.error.conflict', 'Operation rejected.'));
          return;
        }
        default:
          toast.error(t('http.error.generic', 'Something went wrong. Please try again.'));
      }
    },
    [t, toast],
  );
}

function readDetail(error: HttpError): string | null {
  if (error.body !== null && typeof error.body === 'object') {
    const body = error.body as Record<string, unknown>;
    if (typeof body.detail === 'string' && body.detail.length > 0) {
      return body.detail;
    }
    if (typeof body.message === 'string' && body.message.length > 0) {
      return body.message;
    }
  }
  return null;
}
