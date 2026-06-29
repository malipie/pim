import type { SyncDirection } from '../../components/primitives';
import type { RemoteEndpointRow } from '../wizard/steps/StepEndpoints';

/**
 * APIC-P3-11 — presentational pieces of the sync-config screen, extracted from
 * SyncConfigScreen so the page stays a thin composition (ADR-0021 readability
 * guard).
 */

export function EndpointSelect({
  value,
  onChange,
  endpoints,
  placeholder,
  ariaLabel,
}: {
  value: string;
  onChange: (next: string) => void;
  endpoints: RemoteEndpointRow[];
  placeholder: string;
  ariaLabel: string;
}) {
  return (
    <select
      value={value}
      onChange={(e) => onChange(e.target.value)}
      aria-label={ariaLabel}
      className="focus-ring h-10 w-full rounded-xl border border-zinc-200 bg-white px-3 text-[13px]"
    >
      <option value="">{placeholder}</option>
      {endpoints.map((ep) => (
        <option key={ep.id} value={ep.id}>
          {ep.httpMethod} {ep.pathTemplate} · {ep.role}
        </option>
      ))}
    </select>
  );
}

export function ReadonlyValue({ value, mono = false }: { value: string; mono?: boolean }) {
  return (
    <div
      className={`flex h-10 items-center truncate rounded-xl border border-zinc-200 bg-zinc-50/60 px-3 text-[12px] text-zinc-700 ${mono ? 'font-mono' : ''}`}
    >
      {value}
    </div>
  );
}

export function DirDiagram({ dir, apiLabel }: { dir: SyncDirection; apiLabel: string }) {
  const meta = {
    inbound: { top: '←', bottom: null as string | null, label: 'API → PIM' },
    outbound: { top: '→', bottom: null as string | null, label: 'PIM → API' },
    bidirectional: { top: '→', bottom: '←', label: 'PIM ↔ API' },
  }[dir];

  return (
    <div className="rounded-2xl border border-zinc-100 bg-zinc-50/70 p-4">
      <div className="flex items-center justify-between gap-2">
        <DiagramNode label="PIM" sub="hub" dark />
        <div className="flex flex-1 flex-col items-center gap-1">
          <div className="flex w-full items-center gap-1">
            <div className="h-px flex-1 bg-zinc-300" />
            <span className="font-mono text-[16px] leading-none text-zinc-700">{meta.top}</span>
            <div className="h-px flex-1 bg-zinc-300" />
          </div>
          {meta.bottom !== null ? (
            <div className="flex w-full items-center gap-1">
              <div className="h-px flex-1 bg-zinc-300" />
              <span className="font-mono text-[16px] leading-none text-zinc-700">
                {meta.bottom}
              </span>
              <div className="h-px flex-1 bg-zinc-300" />
            </div>
          ) : null}
        </div>
        <DiagramNode label={apiLabel} sub="spoke" />
      </div>
      <div className="mt-3 text-center font-mono text-[11px] text-zinc-400">{meta.label}</div>
    </div>
  );
}

function DiagramNode({ label, sub, dark = false }: { label: string; sub: string; dark?: boolean }) {
  return (
    <div
      className={`grid h-14 w-16 shrink-0 place-items-center rounded-xl ${dark ? 'bg-zinc-900 text-white' : 'border border-zinc-200 bg-white text-zinc-700'}`}
    >
      <div className="text-[13px] font-bold">{label}</div>
      <div className={`font-mono text-[9px] ${dark ? 'text-white/50' : 'text-zinc-400'}`}>
        {sub}
      </div>
    </div>
  );
}
