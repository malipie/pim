import { cn } from '@/lib/utils';

export type ImportStage = 'parsing' | 'mapping' | 'validating' | 'writing' | 'done';

interface StageDef {
  id: ImportStage;
  label: string;
  note: string;
}

const STAGES: ReadonlyArray<StageDef> = [
  { id: 'parsing', label: 'Parsing', note: 'csv/xlsx → rows' },
  { id: 'mapping', label: 'Mapping', note: 'cols → atrybuty' },
  { id: 'validating', label: 'Walidacja', note: 'typy + reguły' },
  { id: 'writing', label: 'Zapis', note: 'DB · batch COPY' },
  { id: 'done', label: 'Gotowe', note: 'raport' },
];

type StageState = 'done' | 'active' | 'pending';

function stateFor(currentIdx: number, idx: number): StageState {
  if (idx < currentIdx) {
    return 'done';
  }
  if (idx === currentIdx) {
    return 'active';
  }
  return 'pending';
}

export interface StagePipelineProps {
  stage: ImportStage;
  className?: string;
}

export function StagePipeline({ stage, className }: StagePipelineProps) {
  const currentIdx = STAGES.findIndex((s) => s.id === stage);
  return (
    <div className={cn('flex items-stretch gap-2', className)}>
      {STAGES.map((s, i) => {
        const state = stateFor(currentIdx, i);
        return (
          <div key={s.id} className="flex items-stretch flex-1 min-w-0">
            <div
              className={cn(
                'flex-1 min-w-0 rounded-xl px-3 py-2 border transition',
                state === 'done' && 'bg-emerald-50/60 border-emerald-200/70',
                state === 'active' &&
                  'bg-white border-zinc-900/15 shadow-[0_1px_0_rgba(24,24,27,.04),0_8px_22px_-12px_rgba(24,24,27,.18)]',
                state === 'pending' && 'bg-zinc-50/60 border-zinc-100',
              )}
            >
              <div className="flex items-center gap-1.5">
                <div
                  className={cn(
                    'h-4 w-4 rounded-full grid place-items-center text-[9px]',
                    state === 'done' && 'bg-emerald-500 text-white',
                    state === 'active' && 'bg-zinc-900 text-white',
                    state === 'pending' && 'bg-zinc-200 text-zinc-400',
                  )}
                >
                  {state === 'done' ? (
                    '✓'
                  ) : state === 'active' ? (
                    <span className="h-1.5 w-1.5 rounded-full bg-white pulse-dot" />
                  ) : (
                    i + 1
                  )}
                </div>
                <div
                  className={cn(
                    'text-[12px] font-semibold tracking-tight truncate',
                    state === 'active' && 'text-zinc-900',
                    state === 'done' && 'text-emerald-800',
                    state === 'pending' && 'text-zinc-400',
                  )}
                >
                  {s.label}
                </div>
              </div>
              <div
                className={cn(
                  'text-[10.5px] font-mono mt-0.5 truncate',
                  state === 'pending' ? 'text-zinc-300' : 'text-zinc-500',
                )}
              >
                {s.note}
              </div>
            </div>
            {i < STAGES.length - 1 ? (
              <div className="flex items-center px-1 text-zinc-300" aria-hidden="true">
                <svg
                  width="10"
                  height="10"
                  viewBox="0 0 24 24"
                  fill="none"
                  stroke="currentColor"
                  strokeWidth="2.5"
                  aria-hidden="true"
                  focusable="false"
                >
                  <title>chevron</title>
                  <path d="m9 6 6 6-6 6" />
                </svg>
              </div>
            ) : null}
          </div>
        );
      })}
    </div>
  );
}
