import { Component, type ErrorInfo, type ReactNode } from 'react';
import { useTranslation } from 'react-i18next';

import { Button } from '@/components/ui/button';

interface Props {
  children: ReactNode;
}

interface State {
  hasError: boolean;
}

/**
 * AUD-049 (W2-12) — top-level React error boundary.
 *
 * Before this, an uncaught render error anywhere in the route tree
 * unmounted the whole app and left `#root` blank (the white-screen
 * incident 2026-05-13 originating in `http.ts:141`). React only catches
 * render-phase errors via the class lifecycle hooks below — there is no
 * hook equivalent — so this stays a class component while the fallback
 * itself is a function component so it can use `useTranslation()`.
 *
 * Scope: catches synchronous render/lifecycle errors in the subtree it
 * wraps. Async rejections (fetch, promises) are NOT render errors and
 * never reach an error boundary — the global `unhandledrejection`
 * handler in `main.tsx` covers that side.
 */
export class ErrorBoundary extends Component<Props, State> {
  constructor(props: Props) {
    super(props);
    this.state = { hasError: false };
  }

  static getDerivedStateFromError(): State {
    // Flip to the fallback on the next render after a child throws.
    return { hasError: true };
  }

  componentDidCatch(error: Error, info: ErrorInfo): void {
    // Surface the error for diagnostics. console.error is intentional —
    // it is the white-screen breadcrumb the operator looks for in
    // DevTools, and any external telemetry sink hooks in here later.
    console.error('[ErrorBoundary] Uncaught render error:', error, info.componentStack);
  }

  render(): ReactNode {
    if (this.state.hasError) {
      return <ErrorFallback />;
    }
    return this.props.children;
  }
}

/**
 * Render fallback shown after a caught error. A full reload is the only
 * reliable recovery for a corrupted render tree (boundary state cannot
 * un-break a child whose module-level state is bad), so the single
 * action is `window.location.reload()`.
 */
function ErrorFallback() {
  const { t } = useTranslation();

  return (
    <div
      role="alert"
      className="flex min-h-[60vh] flex-col items-center justify-center gap-4 p-6 text-center"
    >
      <div className="max-w-md space-y-2">
        <h1 className="text-lg font-semibold text-foreground">
          {t('error_boundary.title', { defaultValue: 'Something went wrong' })}
        </h1>
        <p className="text-sm text-muted-foreground">
          {t('error_boundary.description', {
            defaultValue:
              'The page hit an unexpected error and could not be displayed. Reloading usually fixes it.',
          })}
        </p>
      </div>
      <Button type="button" onClick={() => window.location.reload()}>
        {t('error_boundary.reload', { defaultValue: 'Reload' })}
      </Button>
    </div>
  );
}
