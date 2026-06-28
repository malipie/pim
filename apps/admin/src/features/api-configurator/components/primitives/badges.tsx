import { cn } from '@/lib/utils';

/**
 * APIC-P0-05 — shared badges/pills for the Konfigurator API screens, ported
 * pixel-perfect from the `integracje/api-primitives.jsx` prototype. Enum props
 * drive styling; user-facing text that is translatable (connection status,
 * direction) is passed as a `label` prop so the screen owns i18n. Technical
 * tokens (HTTP method, endpoint role, pagination, auth scheme) carry fixed
 * labels — they are protocol terms, not UI copy.
 */

export type AuthType = 'none' | 'api_key' | 'bearer' | 'basic' | 'oauth2_token';

const AUTH_STYLES: Record<AuthType, { label: string; cls: string }> = {
  none: { label: 'Brak auth', cls: 'bg-zinc-100 text-zinc-600' },
  api_key: { label: 'API key', cls: 'bg-sky-50 text-sky-700' },
  bearer: { label: 'Bearer', cls: 'bg-violet-50 text-violet-700' },
  basic: { label: 'Basic', cls: 'bg-amber-50 text-amber-800' },
  oauth2_token: { label: 'OAuth 2.0', cls: 'bg-emerald-50 text-emerald-700' },
};

export function AuthBadge({ type, hint }: { type: AuthType; hint?: string }) {
  const meta = AUTH_STYLES[type];
  return (
    <span
      className={cn(
        'inline-flex items-center gap-1.5 rounded px-1.5 py-0.5 text-[10.5px] font-medium',
        meta.cls,
      )}
    >
      <svg
        width="11"
        height="11"
        viewBox="0 0 24 24"
        fill="none"
        stroke="currentColor"
        strokeWidth="2.2"
        strokeLinecap="round"
        strokeLinejoin="round"
        aria-hidden="true"
      >
        <rect x="4" y="11" width="16" height="10" rx="2" />
        <path d="M8 11V8a4 4 0 1 1 8 0v3" />
      </svg>
      {meta.label}
      {hint !== undefined && hint !== '' ? (
        <span className="hidden font-mono text-[10px] opacity-70 xl:inline">· {hint}</span>
      ) : null}
    </span>
  );
}

export type SyncDirection = 'inbound' | 'outbound' | 'bidirectional';

const DIRECTION_STYLES: Record<SyncDirection, { arrow: string; cls: string }> = {
  inbound: { arrow: '←', cls: 'bg-sky-50 text-sky-700' },
  outbound: { arrow: '→', cls: 'bg-violet-50 text-violet-700' },
  bidirectional: { arrow: '↔', cls: 'bg-emerald-50 text-emerald-700' },
};

export function DirectionBadge({
  dir,
  label,
  size = 'sm',
}: {
  dir: SyncDirection;
  label: string;
  size?: 'sm' | 'md';
}) {
  const meta = DIRECTION_STYLES[dir];
  return (
    <span
      className={cn(
        'inline-flex items-center gap-1 rounded font-medium',
        size === 'sm' ? 'px-1.5 py-0.5 text-[10.5px]' : 'px-2 py-0.5 text-[11.5px]',
        meta.cls,
      )}
    >
      <span className="font-mono text-[12px] leading-none" aria-hidden>
        {meta.arrow}
      </span>
      {label}
    </span>
  );
}

export type ConnectionStatus = 'active' | 'paused' | 'error' | 'draft';

const STATUS_STYLES: Record<ConnectionStatus, { cls: string; dot: string; pulse: boolean }> = {
  active: { cls: 'bg-emerald-50 text-emerald-700', dot: 'bg-emerald-500', pulse: true },
  paused: { cls: 'bg-amber-50 text-amber-800', dot: 'bg-amber-500', pulse: false },
  error: { cls: 'bg-rose-50 text-rose-700', dot: 'bg-rose-500', pulse: true },
  draft: { cls: 'bg-zinc-100 text-zinc-600', dot: 'bg-zinc-400', pulse: false },
};

export function ConnStatusPill({ status, label }: { status: ConnectionStatus; label: string }) {
  const meta = STATUS_STYLES[status];
  return (
    <span
      className={cn(
        'inline-flex items-center gap-1.5 rounded-md px-2 py-0.5 text-[11.5px] font-medium',
        meta.cls,
      )}
    >
      <span
        className={cn('h-1.5 w-1.5 rounded-full', meta.dot, meta.pulse && 'animate-pulse')}
        aria-hidden
      />
      {label}
    </span>
  );
}

export type HttpMethod = 'GET' | 'POST' | 'PUT' | 'PATCH' | 'DELETE';

const METHOD_STYLES: Record<HttpMethod, string> = {
  GET: 'bg-emerald-50 text-emerald-700',
  POST: 'bg-sky-50 text-sky-700',
  PUT: 'bg-amber-50 text-amber-800',
  PATCH: 'bg-violet-50 text-violet-700',
  DELETE: 'bg-rose-50 text-rose-700',
};

export function MethodPill({ method }: { method: HttpMethod }) {
  return (
    <span
      className={cn(
        'inline-flex items-center rounded px-1.5 py-0.5 font-mono text-[10.5px] font-semibold',
        METHOD_STYLES[method],
      )}
    >
      {method}
    </span>
  );
}

export type EndpointRole = 'read_list' | 'read_one' | 'write_create' | 'write_update';

const ROLE_META: Record<EndpointRole, { txt: string; cls: string }> = {
  read_list: { txt: 'read · list', cls: 'bg-sky-50 text-sky-700' },
  read_one: { txt: 'read · one', cls: 'bg-sky-50 text-sky-700' },
  write_create: { txt: 'write · create', cls: 'bg-violet-50 text-violet-700' },
  write_update: { txt: 'write · update', cls: 'bg-violet-50 text-violet-700' },
};

export function RolePill({ value }: { value: EndpointRole }) {
  const meta = ROLE_META[value];
  return (
    <span
      className={cn(
        'inline-flex items-center rounded px-1.5 py-0.5 font-mono text-[10.5px]',
        meta.cls,
      )}
    >
      {meta.txt}
    </span>
  );
}

export type PaginationKind = 'none' | 'offset' | 'page' | 'cursor' | 'link_header';

const PAGINATION_LABELS: Record<PaginationKind, string> = {
  none: 'bez paginacji',
  offset: 'offset',
  page: 'page',
  cursor: 'cursor',
  link_header: 'Link header',
};

export function PaginationPill({ kind }: { kind: PaginationKind }) {
  return (
    <span className="inline-flex items-center rounded bg-zinc-100 px-1.5 py-0.5 font-mono text-[10.5px] text-zinc-600">
      {PAGINATION_LABELS[kind]}
    </span>
  );
}
