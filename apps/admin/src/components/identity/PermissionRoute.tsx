import { ArrowLeft, LogOut, ShieldOff } from 'lucide-react';
import { type ReactNode, useState } from 'react';
import { useTranslation } from 'react-i18next';
import { useNavigate } from 'react-router';

import { Button } from '@/components/ui/button';
import { authProvider } from '@/lib/auth-provider';
import { useCanI, useCanIAll, useCanIAny, useIdentity } from '@/lib/identity';

/**
 * RBAC-P4-003 (#680) — route-level permission boundary.
 *
 * Pairs with the existing `AuthedRoute` (auth-only) to add a permission
 * check on top:
 *
 *   <Route element={<PermissionRoute code="settings.users.manage" />}>
 *     <Route path="settings/users" element={<UsersListPage />} />
 *   </Route>
 *
 * Renders the {@link Forbidden403Page} fallback when the check fails so
 * navigation does not look broken; the URL stays put and the user can
 * choose to navigate away.
 *
 * Implementation note — uses `Outlet` indirectly through `children`;
 * callers wire it as a layout route. The component is a leaf wrapper,
 * not a router primitive, so callers compose it with `react-router`'s
 * `<Route element={...}>` pattern that renders the matched child.
 */
interface BaseProps {
  fallback?: ReactNode;
  children: ReactNode;
}

interface SingleProps extends BaseProps {
  code: string;
  anyOf?: never;
  allOf?: never;
}

interface AnyProps extends BaseProps {
  code?: never;
  anyOf: readonly string[];
  allOf?: never;
}

interface AllProps extends BaseProps {
  code?: never;
  anyOf?: never;
  allOf: readonly string[];
}

export type PermissionRouteProps = SingleProps | AnyProps | AllProps;

export function PermissionRoute(props: PermissionRouteProps) {
  const { children, fallback = <Forbidden403Page /> } = props;
  const { isLoading } = useIdentity();
  const singleAllowed = useCanI(props.code ?? '');
  const anyAllowed = useCanIAny(props.anyOf ?? []);
  const allAllowed = useCanIAll(props.allOf ?? []);

  if (isLoading) {
    return <RouteLoadingShim />;
  }

  let allowed: boolean;
  if (props.code !== undefined) {
    allowed = singleAllowed;
  } else if (props.anyOf !== undefined) {
    allowed = anyAllowed;
  } else if (props.allOf !== undefined) {
    allowed = allAllowed;
  } else {
    allowed = false;
  }

  return allowed ? children : fallback;
}

function RouteLoadingShim() {
  const { t } = useTranslation();

  return <p className="p-6 text-sm text-muted-foreground">{t('app.loading')}</p>;
}

/**
 * RBAC-P5-018 (#708) — polished 403 page rendered when a route's
 * permission check fails. Kept inside the same module so it can be the
 * default fallback without forcing callers to import a separate page.
 *
 * Polish over the #680 substrate:
 *   - centred card with lock/shield icon,
 *   - back + logout action buttons (back uses router history, logout
 *     hits the auth-provider so cookies / refresh tokens get wiped),
 *   - optional `permissionCode` prop surfaces the required code in an
 *     `<details>` toggle so the operator can copy-paste it into a
 *     support ticket without inspecting React DevTools,
 *   - optional `detailMessage` forwards a backend Problem Details
 *     `detail` string when the 403 came from an API call rather than a
 *     local route guard.
 */
export interface Forbidden403PageProps {
  permissionCode?: string;
  detailMessage?: string;
}

export function Forbidden403Page({ permissionCode, detailMessage }: Forbidden403PageProps = {}) {
  const { t } = useTranslation();
  const navigate = useNavigate();
  const [signingOut, setSigningOut] = useState(false);

  const goBack = () => {
    if (window.history.length > 1) {
      navigate(-1);
      return;
    }
    navigate('/dashboard', { replace: true });
  };

  const signOut = async () => {
    setSigningOut(true);
    try {
      await authProvider.logout?.({});
    } finally {
      navigate('/login', { replace: true });
    }
  };

  return (
    <div className="flex min-h-[60vh] items-center justify-center p-8">
      <section
        aria-labelledby="forbidden-403-title"
        className="w-full max-w-md rounded-2xl border bg-background p-8 text-center shadow-sm"
      >
        <div
          className="mx-auto mb-4 grid size-16 place-items-center rounded-full bg-rose-100 text-rose-700"
          aria-hidden="true"
        >
          <ShieldOff className="size-8" />
        </div>
        <h1 id="forbidden-403-title" className="display text-2xl font-semibold tracking-tight">
          {t('rbac.forbidden.title')}
        </h1>
        <p className="mt-2 text-sm text-muted-foreground">
          {detailMessage ?? t('rbac.forbidden.body')}
        </p>
        {permissionCode ? (
          <details className="mt-4 rounded-md border border-dashed bg-muted/40 p-3 text-left text-xs text-muted-foreground">
            <summary className="cursor-pointer select-none font-medium">
              {t('rbac.forbidden.debug_details')}
            </summary>
            <p className="mt-2">
              {t('rbac.forbidden.permission_required_label')}:{' '}
              <code className="rounded bg-muted px-1.5 py-0.5 font-mono text-[11px] text-foreground">
                {permissionCode}
              </code>
            </p>
          </details>
        ) : null}
        <div className="mt-6 flex justify-center gap-2">
          <Button variant="outline" size="sm" onClick={goBack} className="gap-1.5">
            <ArrowLeft className="size-4" aria-hidden="true" />
            {t('rbac.forbidden.back')}
          </Button>
          <Button
            variant="ghost"
            size="sm"
            onClick={signOut}
            disabled={signingOut}
            className="gap-1.5"
          >
            <LogOut className="size-4" aria-hidden="true" />
            {t('rbac.forbidden.logout')}
          </Button>
        </div>
      </section>
    </div>
  );
}
