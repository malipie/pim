import { Layers, X } from 'lucide-react';
import { useState } from 'react';
import { useTranslation } from 'react-i18next';

import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { toast } from '@/components/ui/toast';
import { jsonFetch } from '@/lib/http';
import { cn } from '@/lib/utils';

/**
 * VIEW-12 (#543) — 3-step bulk action wizard.
 *
 * MVP scope: synchronous `set_attribute` only. Wizard collects:
 *   - Step 1: attribute code + new value (the action target).
 *   - Step 2: locale + channels (placeholder — full scoping in VIEW-13).
 *   - Step 3: preview diff (sample 5 + aggregate counts) → Apply.
 *
 * Pixel-perfect mockup `list-v2-overlays.jsx` l. 151-360. Async path
 * (Messenger + Mercure SSE progress) lands in VIEW-12.1.
 */

interface BulkWizardProps {
  open: boolean;
  selectedIds: string[];
  onClose: () => void;
  onApplied: (result: BulkActionResult) => void;
}

interface BulkActionPreview {
  action: string;
  target_count: number;
  success_count: number;
  skipped_count: number;
  error_count: number;
  sample: Array<{ id: string; sku: string; before: unknown; after: unknown }>;
}

interface BulkActionResult {
  session_id: string;
  action: string;
  target_count: number;
  success_count: number;
  skipped_count: number;
  error_count: number;
  rollback_available_until?: string;
  completed_at?: string;
}

type BulkMode =
  | 'set_attribute'
  | 'clear_attribute'
  | 'append_value'
  | 'remove_value'
  | 'increment_numeric'
  | 'multi_attribute_edit';

const MODE_LABELS: Record<BulkMode, string> = {
  set_attribute: 'Ustaw wartość',
  clear_attribute: 'Wyczyść',
  append_value: 'Dodaj do listy',
  remove_value: 'Usuń z listy',
  increment_numeric: 'Operacja arytm.',
  multi_attribute_edit: 'Multi-atrybut',
};

interface MultiEdit {
  id: string;
  attr: string;
  op: 'set' | 'clear';
  value: string;
}

let multiEditCounter = 0;
const nextEditId = (): string => `edit-${++multiEditCounter}`;

export function BulkWizard({ open, selectedIds, onClose, onApplied }: BulkWizardProps) {
  const { t } = useTranslation();
  const [step, setStep] = useState<1 | 2 | 3>(1);
  const [mode, setMode] = useState<BulkMode>('set_attribute');
  const [attrCode, setAttrCode] = useState('');
  const [newValue, setNewValue] = useState('');
  const [operator, setOperator] = useState<'+' | '-' | '*' | '/' | '%'>('*');
  const [operand, setOperand] = useState('1.10');
  const [edits, setEdits] = useState<MultiEdit[]>([
    { id: nextEditId(), attr: '', op: 'set', value: '' },
  ]);
  const [preview, setPreview] = useState<BulkActionPreview | null>(null);
  const [isLoading, setIsLoading] = useState(false);

  if (!open) return null;

  const buildPayload = (): Record<string, unknown> => {
    if (mode === 'multi_attribute_edit') {
      return {
        edits: edits
          .filter((e) => e.attr.trim() !== '')
          .map((e) => ({
            attr: e.attr.trim(),
            op: e.op,
            ...(e.op === 'set' ? { value: e.value } : {}),
          })),
      };
    }
    if (mode === 'increment_numeric') {
      return { attr: attrCode.trim(), operator, operand: Number(operand) };
    }
    if (mode === 'clear_attribute') {
      return { attr: attrCode.trim() };
    }
    return { attr: attrCode.trim(), value: newValue };
  };

  const canAdvance = (() => {
    if (step !== 1) return true;
    if (mode === 'multi_attribute_edit') {
      return edits.some((e) => e.attr.trim() !== '');
    }
    if (mode === 'clear_attribute') {
      return attrCode.trim() !== '';
    }
    if (mode === 'increment_numeric') {
      return attrCode.trim() !== '' && operand.trim() !== '' && !Number.isNaN(Number(operand));
    }
    return attrCode.trim() !== '' && newValue.trim() !== '';
  })();

  const fetchPreview = async (): Promise<void> => {
    setIsLoading(true);
    try {
      const response = await jsonFetch<BulkActionPreview>('/api/products/bulk-actions/preview', {
        method: 'POST',
        body: {
          action: mode,
          target_ids: selectedIds,
          payload: buildPayload(),
        },
      });
      setPreview(response);
    } catch (e) {
      toast.error(e instanceof Error ? e.message : 'preview failed');
    } finally {
      setIsLoading(false);
    }
  };

  const apply = async (): Promise<void> => {
    setIsLoading(true);
    try {
      const response = await jsonFetch<BulkActionResult>(`/api/products/bulk-actions/${mode}`, {
        method: 'POST',
        body: {
          target_ids: selectedIds,
          payload: buildPayload(),
        },
      });
      onApplied(response);
      toast.success(
        t('products.bulk_wizard.applied_success', {
          count: response.success_count,
          defaultValue: `Zastosowano do ${response.success_count} produktów`,
        }),
      );
      onClose();
    } catch (e) {
      toast.error(e instanceof Error ? e.message : 'apply failed');
    } finally {
      setIsLoading(false);
    }
  };

  const next = async (): Promise<void> => {
    if (step === 1) {
      setStep(2);
    } else if (step === 2) {
      await fetchPreview();
      setStep(3);
    } else if (step === 3) {
      await apply();
    }
  };

  return (
    <div className="fixed inset-0 z-50 bg-zinc-900/30 backdrop-blur-sm grid place-items-center">
      <button
        type="button"
        aria-label="Close backdrop"
        onClick={onClose}
        className="absolute inset-0 cursor-default"
      />
      <div
        className="relative bg-white rounded-3xl shadow-2xl w-[860px] max-w-[94vw] max-h-[88vh] overflow-hidden flex flex-col"
        role="dialog"
        aria-modal="true"
        aria-labelledby="bulk-wizard-title"
      >
        {/* Header */}
        <div className="px-6 h-14 flex items-center gap-3 border-b border-zinc-100">
          <span className="h-8 w-8 rounded-xl bg-zinc-900 text-white grid place-items-center">
            <Layers className="size-4" />
          </span>
          <div className="leading-tight">
            <div id="bulk-wizard-title" className="text-[14.5px] font-semibold tracking-tight">
              {t('products.bulk_wizard.title', { defaultValue: 'Akcja zbiorcza · Ustaw atrybut' })}
            </div>
            <div className="text-[11.5px] text-zinc-500 tabular-nums">
              {selectedIds.length}{' '}
              {t('products.bulk_wizard.target_count_label', {
                defaultValue: 'produktów wybranych',
              })}
            </div>
          </div>
          <div className="ml-auto inline-flex items-center gap-1">
            {(['Wybór akcji', 'Konfiguracja', 'Preview diff'] as const).map((label, i) => {
              const stepNo = (i + 1) as 1 | 2 | 3;
              const active = stepNo === step;
              const done = stepNo < step;
              return (
                <div key={label} className="flex items-center gap-1">
                  {i > 0 && (
                    <span
                      className={cn('h-px w-6', done ? 'bg-emerald-500' : 'bg-zinc-200')}
                      aria-hidden="true"
                    />
                  )}
                  <span
                    className={cn(
                      'inline-flex items-center gap-1.5 h-7 px-2.5 rounded-lg text-[11.5px] font-medium',
                      active
                        ? 'bg-zinc-900 text-white'
                        : done
                          ? 'text-emerald-700 bg-emerald-50'
                          : 'text-zinc-400 bg-zinc-50',
                    )}
                  >
                    <span className="h-4 w-4 grid place-items-center rounded-full text-[10px] font-mono">
                      {done ? '✓' : stepNo}
                    </span>
                    {label}
                  </span>
                </div>
              );
            })}
          </div>
          <button
            type="button"
            onClick={onClose}
            aria-label="Close"
            className="ml-2 h-8 w-8 grid place-items-center rounded-lg text-zinc-400 hover:bg-zinc-100"
          >
            <X className="size-4" />
          </button>
        </div>

        {/* Body */}
        <div className="flex-1 overflow-y-auto p-6">
          {step === 1 && (
            <div className="space-y-4">
              <div>
                <div className="text-[11px] uppercase tracking-wider font-semibold text-zinc-500 mb-2">
                  {t('products.bulk_wizard.mode_label', { defaultValue: 'Tryb operacji' })}
                </div>
                <div className="grid grid-cols-3 gap-2">
                  {(Object.keys(MODE_LABELS) as BulkMode[]).map((m) => (
                    <button
                      key={m}
                      type="button"
                      onClick={() => setMode(m)}
                      className={cn(
                        'h-9 px-3 rounded-lg text-[12px] font-medium border',
                        mode === m
                          ? 'bg-zinc-900 text-white border-zinc-900'
                          : 'bg-white text-zinc-700 border-zinc-200 hover:border-zinc-300',
                      )}
                    >
                      {MODE_LABELS[m]}
                    </button>
                  ))}
                </div>
              </div>
              {mode !== 'multi_attribute_edit' && (
                <div>
                  <label
                    htmlFor="bulk-attr-code"
                    className="text-[11px] uppercase tracking-wider font-semibold text-zinc-500"
                  >
                    {t('products.bulk_wizard.attr_label', { defaultValue: 'Kod atrybutu' })}
                  </label>
                  <Input
                    id="bulk-attr-code"
                    value={attrCode}
                    onChange={(e) => setAttrCode(e.target.value)}
                    placeholder="brand, family, description_en …"
                    className="mt-2"
                  />
                </div>
              )}
              {(mode === 'set_attribute' || mode === 'append_value' || mode === 'remove_value') && (
                <div>
                  <label
                    htmlFor="bulk-attr-value"
                    className="text-[11px] uppercase tracking-wider font-semibold text-zinc-500"
                  >
                    {mode === 'set_attribute'
                      ? t('products.bulk_wizard.value_label', { defaultValue: 'Nowa wartość' })
                      : mode === 'append_value'
                        ? t('products.bulk_wizard.append_value_label', {
                            defaultValue: 'Wartość do dodania',
                          })
                        : t('products.bulk_wizard.remove_value_label', {
                            defaultValue: 'Wartość do usunięcia',
                          })}
                  </label>
                  <Input
                    id="bulk-attr-value"
                    value={newValue}
                    onChange={(e) => setNewValue(e.target.value)}
                    placeholder="np. Festo"
                    className="mt-2"
                  />
                </div>
              )}
              {mode === 'increment_numeric' && (
                <div className="grid grid-cols-2 gap-3">
                  <div>
                    <label
                      htmlFor="bulk-operator"
                      className="text-[11px] uppercase tracking-wider font-semibold text-zinc-500"
                    >
                      Operator
                    </label>
                    <select
                      id="bulk-operator"
                      value={operator}
                      onChange={(e) => setOperator(e.target.value as '+' | '-' | '*' | '/' | '%')}
                      className="mt-2 h-9 w-full rounded-lg border border-zinc-200 px-2 text-[13px] font-mono"
                    >
                      <option value="+">+ dodaj</option>
                      <option value="-">- odejmij</option>
                      <option value="*">* pomnóż</option>
                      <option value="/">/ podziel</option>
                      <option value="%">% modulo</option>
                    </select>
                  </div>
                  <div>
                    <label
                      htmlFor="bulk-operand"
                      className="text-[11px] uppercase tracking-wider font-semibold text-zinc-500"
                    >
                      Wartość
                    </label>
                    <Input
                      id="bulk-operand"
                      value={operand}
                      onChange={(e) => setOperand(e.target.value)}
                      placeholder="np. 1.10 (price *= 1.10)"
                      className="mt-2 font-mono"
                    />
                  </div>
                </div>
              )}
              {mode === 'multi_attribute_edit' && (
                <div className="space-y-3">
                  <div className="text-[11px] uppercase tracking-wider font-semibold text-zinc-500">
                    {t('products.bulk_wizard.multi_label', {
                      defaultValue: 'Lista edycji (attr · op · value)',
                    })}
                  </div>
                  {edits.map((edit) => (
                    <div key={edit.id} className="flex gap-2 items-center">
                      <Input
                        value={edit.attr}
                        onChange={(e) =>
                          setEdits((arr) =>
                            arr.map((row) =>
                              row.id === edit.id ? { ...row, attr: e.target.value } : row,
                            ),
                          )
                        }
                        placeholder="attr code"
                        className="flex-1"
                      />
                      <select
                        value={edit.op}
                        onChange={(e) =>
                          setEdits((arr) =>
                            arr.map((row) =>
                              row.id === edit.id
                                ? { ...row, op: e.target.value as 'set' | 'clear' }
                                : row,
                            ),
                          )
                        }
                        className="h-9 w-24 rounded-lg border border-zinc-200 px-2 text-[13px]"
                      >
                        <option value="set">set</option>
                        <option value="clear">clear</option>
                      </select>
                      {edit.op === 'set' && (
                        <Input
                          value={edit.value}
                          onChange={(e) =>
                            setEdits((arr) =>
                              arr.map((row) =>
                                row.id === edit.id ? { ...row, value: e.target.value } : row,
                              ),
                            )
                          }
                          placeholder="value"
                          className="flex-1"
                        />
                      )}
                      <button
                        type="button"
                        onClick={() => setEdits((arr) => arr.filter((row) => row.id !== edit.id))}
                        className="h-9 w-9 rounded-lg border border-zinc-200 text-zinc-500 hover:bg-zinc-50"
                        aria-label="Remove edit row"
                      >
                        ×
                      </button>
                    </div>
                  ))}
                  <button
                    type="button"
                    onClick={() =>
                      setEdits((arr) => [
                        ...arr,
                        { id: nextEditId(), attr: '', op: 'set', value: '' },
                      ])
                    }
                    className="h-8 px-3 rounded-lg border border-dashed border-zinc-300 text-[12px] font-medium text-zinc-600 hover:bg-zinc-50"
                  >
                    + Dodaj atrybut
                  </button>
                </div>
              )}
            </div>
          )}
          {step === 2 && (
            <div className="rounded-2xl border border-zinc-200 bg-white p-4 text-[12.5px] text-zinc-700">
              {t('products.bulk_wizard.step2_placeholder', {
                defaultValue:
                  'Zakres locale + kanały — w VIEW-13. W MVP set_attribute trafia w global lane.',
              })}
            </div>
          )}
          {step === 3 && preview && (
            <div>
              <div className="grid grid-cols-4 gap-4 mb-5 rounded-2xl bg-zinc-50/70 border border-zinc-100 p-4">
                <Stat n={preview.target_count} label="zaznaczonych" tone="zinc" />
                <Stat n={preview.success_count} label="do zmiany" tone="emerald" />
                <Stat n={preview.skipped_count} label="pominięte" tone="amber" />
                <Stat n={preview.error_count} label="błędy" tone="rose" />
              </div>
              <div className="rounded-2xl border border-zinc-200 overflow-hidden">
                <div
                  className="grid items-center text-[10.5px] uppercase tracking-wider text-zinc-500 font-semibold bg-zinc-50/70 border-b border-zinc-100"
                  style={{ gridTemplateColumns: '120px 1fr 140px 140px' }}
                >
                  <div className="px-3 py-2">SKU</div>
                  <div className="px-3 py-2">ID</div>
                  <div className="px-3 py-2">Przed</div>
                  <div className="px-3 py-2">Po</div>
                </div>
                {preview.sample.map((row) => (
                  <div
                    key={row.id}
                    className="grid items-center text-[12.5px] border-b border-zinc-50 last:border-b-0"
                    style={{ gridTemplateColumns: '120px 1fr 140px 140px' }}
                  >
                    <div className="px-3 py-2 font-mono">{row.sku}</div>
                    <div className="px-3 py-2 font-mono text-zinc-400 text-[11px] truncate">
                      {row.id}
                    </div>
                    <div className="px-3 py-2 text-zinc-500 line-through font-mono text-[11.5px]">
                      {String(row.before ?? '—')}
                    </div>
                    <div className="px-3 py-2 text-emerald-700 font-semibold font-mono text-[11.5px]">
                      {String(row.after ?? '—')}
                    </div>
                  </div>
                ))}
              </div>
            </div>
          )}
        </div>

        {/* Footer */}
        <div className="px-6 h-14 flex items-center gap-3 border-t border-zinc-100 bg-zinc-50/50">
          <span className="text-[11.5px] text-zinc-500">
            {t('products.bulk_wizard.rollback_hint', {
              defaultValue: 'Każda akcja zbiorcza ma 24h soft-rollback.',
            })}
          </span>
          <div className="ml-auto flex items-center gap-2">
            {step > 1 && (
              <Button
                variant="ghost"
                onClick={() => setStep((s) => (s === 3 ? 2 : 1) as 1 | 2)}
                disabled={isLoading}
              >
                {t('app.back', { defaultValue: 'Wstecz' })}
              </Button>
            )}
            <Button variant="ghost" onClick={onClose} disabled={isLoading}>
              {t('app.cancel', { defaultValue: 'Anuluj' })}
            </Button>
            <Button onClick={() => void next()} disabled={!canAdvance || isLoading}>
              {step < 3
                ? t('products.bulk_wizard.next', { defaultValue: 'Dalej →' })
                : t('products.bulk_wizard.apply', { defaultValue: 'Zastosuj' })}
            </Button>
          </div>
        </div>
      </div>
    </div>
  );
}

function Stat({ n, label, tone }: { n: number; label: string; tone: string }) {
  const map: Record<string, string> = {
    zinc: 'text-zinc-900',
    emerald: 'text-emerald-700',
    amber: 'text-amber-700',
    rose: 'text-rose-700',
  };
  return (
    <div>
      <div
        className={cn(
          'font-display text-[28px] font-semibold tracking-tight leading-none tabular-nums',
          map[tone],
        )}
      >
        {n}
      </div>
      <div className="text-[11.5px] text-zinc-500 mt-1">{label}</div>
    </div>
  );
}
