import { CheckCircle2, Info, X, XCircle } from 'lucide-react';
import {
  createContext,
  type ReactNode,
  useCallback,
  useContext,
  useEffect,
  useMemo,
  useRef,
  useState,
} from 'react';
import { createPortal } from 'react-dom';

import { cn } from '@/lib/utils';

type ToastLevel = 'info' | 'error' | 'success';

interface ToastEntry {
  id: number;
  level: ToastLevel;
  text: string;
}

interface ToastApi {
  info: (text: string) => void;
  error: (text: string) => void;
  success: (text: string) => void;
}

const ToastContext = createContext<ToastApi | null>(null);
const MAX_VISIBLE = 3;
const AUTO_DISMISS_MS = 4000;

let externalApi: ToastApi | null = null;

export const toast: ToastApi = {
  info: (text) => externalApi?.info(text),
  error: (text) => externalApi?.error(text),
  success: (text) => externalApi?.success(text),
};

export function ToastProvider({ children }: { children: ReactNode }) {
  const [entries, setEntries] = useState<ToastEntry[]>([]);
  const counterRef = useRef(0);

  const dismiss = useCallback((id: number): void => {
    setEntries((prev) => prev.filter((entry) => entry.id !== id));
  }, []);

  const push = useCallback(
    (level: ToastLevel, text: string): void => {
      counterRef.current += 1;
      const id = counterRef.current;
      setEntries((prev) => {
        const next = [...prev, { id, level, text }];
        if (next.length > MAX_VISIBLE) next.splice(0, next.length - MAX_VISIBLE);
        return next;
      });
      window.setTimeout(() => {
        dismiss(id);
      }, AUTO_DISMISS_MS);
    },
    [dismiss],
  );

  const api = useMemo<ToastApi>(
    () => ({
      info: (text) => {
        push('info', text);
      },
      error: (text) => {
        push('error', text);
      },
      success: (text) => {
        push('success', text);
      },
    }),
    [push],
  );

  useEffect(() => {
    externalApi = api;
    return () => {
      if (externalApi === api) externalApi = null;
    };
  }, [api]);

  return (
    <ToastContext.Provider value={api}>
      {children}
      {typeof document !== 'undefined'
        ? createPortal(<ToastViewport entries={entries} onDismiss={dismiss} />, document.body)
        : null}
    </ToastContext.Provider>
  );
}

export function useToast(): ToastApi {
  const ctx = useContext(ToastContext);
  if (ctx === null) throw new Error('useToast must be used inside <ToastProvider>');
  return ctx;
}

interface ToastViewportProps {
  entries: ToastEntry[];
  onDismiss: (id: number) => void;
}

function ToastViewport({ entries, onDismiss }: ToastViewportProps) {
  return (
    <section
      aria-label="Notifications"
      className="pointer-events-none fixed bottom-6 right-6 z-[60] flex flex-col gap-2"
    >
      {entries.map((entry) => (
        <ToastCard key={entry.id} entry={entry} onDismiss={onDismiss} />
      ))}
    </section>
  );
}

interface ToastCardProps {
  entry: ToastEntry;
  onDismiss: (id: number) => void;
}

function ToastCard({ entry, onDismiss }: ToastCardProps) {
  const Icon = entry.level === 'error' ? XCircle : entry.level === 'success' ? CheckCircle2 : Info;
  const accent =
    entry.level === 'error'
      ? 'text-rose-500'
      : entry.level === 'success'
        ? 'text-emerald-500'
        : 'text-violet-500';
  return (
    <div
      role={entry.level === 'error' ? 'alert' : 'status'}
      aria-live={entry.level === 'error' ? 'assertive' : 'polite'}
      className={cn(
        'pointer-events-auto flex max-w-sm items-start gap-3 rounded-2xl border border-zinc-200 bg-white px-4 py-3 shadow-lg',
        'animate-in fade-in slide-in-from-bottom-2 duration-200',
      )}
    >
      <Icon className={cn('mt-0.5 size-4 shrink-0', accent)} aria-hidden="true" />
      <p className="flex-1 text-[13px] leading-snug text-zinc-700">{entry.text}</p>
      <button
        type="button"
        onClick={() => {
          onDismiss(entry.id);
        }}
        className="rounded p-0.5 text-zinc-400 transition hover:text-zinc-700 focus:outline-none focus-visible:ring-2 focus-visible:ring-zinc-900"
        aria-label="Dismiss"
      >
        <X className="size-3.5" aria-hidden="true" />
      </button>
    </div>
  );
}
