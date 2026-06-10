import { createContext, type ReactNode, useContext, useEffect, useMemo, useState } from 'react';

interface PageActionsContextValue {
  actions: ReactNode;
  setActions: (node: ReactNode) => void;
}

const PageActionsContext = createContext<PageActionsContextValue | null>(null);

/** Mount once in the shell — holds the topbar's per-page action slot. */
export function PageActionsProvider({ children }: { children: ReactNode }) {
  const [actions, setActions] = useState<ReactNode>(null);
  const value = useMemo(() => ({ actions, setActions }), [actions]);
  return <PageActionsContext.Provider value={value}>{children}</PageActionsContext.Provider>;
}

/** Read the currently registered page actions (used by the topbar). */
export function usePageActionsSlot(): ReactNode {
  return useContext(PageActionsContext)?.actions ?? null;
}

/**
 * Register topbar actions for the lifetime of the calling page
 * (EXR-03): `usePageActions(<Button>Nowy eksport</Button>)`.
 * Cleared automatically on unmount.
 */
export function usePageActions(node: ReactNode): void {
  const context = useContext(PageActionsContext);
  const setActions = context?.setActions;
  useEffect(() => {
    if (!setActions) {
      return;
    }
    setActions(node);
    return () => setActions(null);
  }, [node, setActions]);
}
