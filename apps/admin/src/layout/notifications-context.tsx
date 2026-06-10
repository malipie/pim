import { createContext, type ReactNode, useCallback, useContext, useMemo, useState } from 'react';

/**
 * EXR-15 — simple client-side in-app inbox. Exports are the first
 * writer; the shape is module-agnostic so future modules (imports,
 * sync) can push entries through the same context. Deliberately NO
 * backend persistence in MVP — reload clears the inbox.
 */
export interface InboxNotification {
  id: string;
  level: 'success' | 'warning' | 'error';
  /** Already-translated title. */
  title: string;
  /** Already-translated secondary line. */
  body?: string;
  /** In-app link target (e.g. session detail). */
  href?: string;
  receivedAt: number;
}

interface NotificationsInboxValue {
  entries: InboxNotification[];
  unread: number;
  add: (entry: Omit<InboxNotification, 'receivedAt'>) => void;
  markAllRead: () => void;
}

const MAX_ENTRIES = 20;

const NotificationsInboxContext = createContext<NotificationsInboxValue | null>(null);

export function NotificationsInboxProvider({ children }: { children: ReactNode }) {
  const [entries, setEntries] = useState<InboxNotification[]>([]);
  const [unread, setUnread] = useState(0);

  const add = useCallback((entry: Omit<InboxNotification, 'receivedAt'>) => {
    setEntries((previous) => {
      if (previous.some((existing) => existing.id === entry.id)) {
        return previous;
      }
      return [{ ...entry, receivedAt: Date.now() }, ...previous].slice(0, MAX_ENTRIES);
    });
    setUnread((count) => count + 1);
  }, []);

  const markAllRead = useCallback(() => setUnread(0), []);

  const value = useMemo(
    () => ({ entries, unread, add, markAllRead }),
    [entries, unread, add, markAllRead],
  );

  return (
    <NotificationsInboxContext.Provider value={value}>
      {children}
    </NotificationsInboxContext.Provider>
  );
}

export function useNotificationsInbox(): NotificationsInboxValue {
  const context = useContext(NotificationsInboxContext);
  if (!context) {
    throw new Error('useNotificationsInbox must be used inside <NotificationsInboxProvider>');
  }
  return context;
}

/** Null-safe variant for components that may render outside the shell. */
export function useNotificationsInboxOptional(): NotificationsInboxValue | null {
  return useContext(NotificationsInboxContext);
}
