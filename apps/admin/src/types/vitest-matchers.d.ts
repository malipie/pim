/**
 * Vitest matcher augmentations for component tests:
 * - jest-dom matchers (toBeInTheDocument, toHaveAttribute, ...)
 * - jest-axe `toHaveNoViolations` (typed for vitest's Assertion, since
 *   @types/jest-axe only augments the jest namespace)
 */
import '@testing-library/jest-dom/vitest';

declare module 'vitest' {
  interface Assertion<T> {
    toHaveNoViolations(): T;
  }
}
