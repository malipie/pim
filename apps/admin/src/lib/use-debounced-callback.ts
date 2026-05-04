import { useCallback, useEffect, useRef } from 'react';

/**
 * Debounce a callback so successive invocations are coalesced into a
 * single call after `delayMs` of inactivity. Useful for inline edit
 * fields that PATCH on blur but should hold off if the user is still
 * typing.
 *
 * The returned callback is stable across renders; the latest `fn` is
 * always used. Pending timers are cleared on unmount so post-unmount
 * sets are no-ops.
 */
export function useDebouncedCallback<TArgs extends unknown[]>(
  fn: (...args: TArgs) => void,
  delayMs: number,
): (...args: TArgs) => void {
  const fnRef = useRef(fn);
  const timerRef = useRef<ReturnType<typeof setTimeout> | null>(null);

  useEffect(() => {
    fnRef.current = fn;
  }, [fn]);

  useEffect(() => {
    return () => {
      if (timerRef.current !== null) {
        clearTimeout(timerRef.current);
      }
    };
  }, []);

  return useCallback(
    (...args: TArgs) => {
      if (timerRef.current !== null) {
        clearTimeout(timerRef.current);
      }
      timerRef.current = setTimeout(() => {
        fnRef.current(...args);
      }, delayMs);
    },
    [delayMs],
  );
}
