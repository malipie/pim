import { render, screen } from '@testing-library/react';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';

import { ErrorBoundary } from '../ErrorBoundary';

/**
 * AUD-049 (W2-12) — coverage for the top-level error boundary. A child
 * that throws during render must surface the recoverable fallback (role
 * "alert" + Reload action), NOT crash the test renderer / blank the
 * tree (the white-screen failure mode).
 */
function Boom(): never {
  throw new Error('render exploded');
}

describe('ErrorBoundary', () => {
  beforeEach(() => {
    // React logs caught render errors to console.error; silence it so the
    // expected throw does not pollute the test output (and assert nothing
    // about the log itself — that is incidental).
    vi.spyOn(console, 'error').mockImplementation(() => {});
  });

  afterEach(() => {
    vi.restoreAllMocks();
  });

  it('renders children unchanged when nothing throws', () => {
    render(
      <ErrorBoundary>
        <p>healthy content</p>
      </ErrorBoundary>,
    );

    expect(screen.getByText('healthy content')).toBeInTheDocument();
    expect(screen.queryByRole('alert')).not.toBeInTheDocument();
  });

  it('shows the fallback (alert + reload action) when a child throws', () => {
    render(
      <ErrorBoundary>
        <Boom />
      </ErrorBoundary>,
    );

    // Fallback visible — the alert region replaced the crashed subtree.
    expect(screen.getByRole('alert')).toBeInTheDocument();
    // i18n key resolves via vitest.setup (pl translation) — assert the
    // Reload action is present and clickable, not the raw key.
    const reload = screen.getByRole('button', { name: /przeładuj/i });
    expect(reload).toBeInTheDocument();
  });
});
