import { Plus, Trash2 } from 'lucide-react';
import { useState } from 'react';
import { useTranslation } from 'react-i18next';

import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { jsonFetch } from '@/lib/http';

interface AxisDraft {
  code: string;
  values: string[];
}

interface GenerateVariantsResponse {
  master_id: string;
  created_count: number;
  skipped_count: number;
  created: Array<{ sku: string; axis_values: Record<string, string> }>;
  skipped_existing: string[];
}

/**
 * UI-02.18 (#308) — Variants tab for the product detail page.
 *
 * Slice covers the axes editor + matrix generator, both wired to the
 * UI-02.6 backend (`POST /api/products/{master_id}/generate-variants`).
 *
 * - Local-state axes draft (`color: [red, blue, green]`).
 * - Per-axis add/remove + per-value chip removal.
 * - Generate button posts the cartesian product spec; the response
 *   reports created vs skipped (existing SKU collisions).
 * - SKU template (optional). Default `{master_sku}-{values_joined}`.
 *
 * Per-variant Excel-like grid lands once UI-02.12 is integrated into
 * the variant list view; the matrix generator output already returns
 * the new SKUs so a follow-up just needs to reuse `<ExcelLikeGrid>`
 * over the variants collection.
 */
export function VariantsTab({ masterProductId }: { masterProductId: string }) {
  const { t } = useTranslation();
  const [axes, setAxes] = useState<AxisDraft[]>([{ code: 'color', values: [] }]);
  const [skuTemplate, setSkuTemplate] = useState('');
  const [isPending, setIsPending] = useState(false);
  const [response, setResponse] = useState<GenerateVariantsResponse | null>(null);
  const [error, setError] = useState<string | null>(null);

  const updateAxisCode = (idx: number, code: string): void => {
    setAxes((prev) => prev.map((a, i) => (i === idx ? { ...a, code } : a)));
  };

  const addAxisValue = (idx: number, value: string): void => {
    if (value.trim() === '') return;
    setAxes((prev) =>
      prev.map((a, i) =>
        i === idx && !a.values.includes(value.trim())
          ? { ...a, values: [...a.values, value.trim()] }
          : a,
      ),
    );
  };

  const removeAxisValue = (axisIdx: number, valueIdx: number): void => {
    setAxes((prev) =>
      prev.map((a, i) =>
        i === axisIdx ? { ...a, values: a.values.filter((_, vi) => vi !== valueIdx) } : a,
      ),
    );
  };

  const removeAxis = (idx: number): void => {
    setAxes((prev) => prev.filter((_, i) => i !== idx));
  };

  const addAxis = (): void => {
    setAxes((prev) => [...prev, { code: '', values: [] }]);
  };

  const totalCombinations = axes.reduce((acc, axis) => acc * Math.max(1, axis.values.length), 1);

  const handleGenerate = async (): Promise<void> => {
    setIsPending(true);
    setError(null);
    setResponse(null);
    try {
      const axesPayload: Record<string, string[]> = {};
      for (const axis of axes) {
        if (axis.code.trim() !== '' && axis.values.length > 0) {
          axesPayload[axis.code.trim()] = axis.values;
        }
      }
      const body: Record<string, unknown> = { axes: axesPayload };
      if (skuTemplate.trim() !== '') body.sku_template = skuTemplate.trim();
      const result = await jsonFetch<GenerateVariantsResponse>(
        `/api/products/${masterProductId}/generate-variants`,
        { method: 'POST', body },
      );
      setResponse(result);
    } catch (e) {
      setError(e instanceof Error ? e.message : 'unknown');
    } finally {
      setIsPending(false);
    }
  };

  return (
    <section className="space-y-4 rounded-lg border bg-card p-4">
      <header className="flex items-center justify-between">
        <h2 className="text-lg font-semibold tracking-tight">
          {t('products.variants.title', { defaultValue: 'Variants' })}
        </h2>
        <span className="text-xs text-muted-foreground">
          {t('products.variants.combinations_count', {
            count: totalCombinations,
            defaultValue: '{{count}} combination(s)',
          })}
        </span>
      </header>

      <div className="space-y-3">
        {axes.map((axis, idx) => (
          <AxisRow
            // biome-ignore lint/suspicious/noArrayIndexKey: axis identity is positional in this draft form.
            key={idx}
            axis={axis}
            onCodeChange={(code) => updateAxisCode(idx, code)}
            onAddValue={(value) => addAxisValue(idx, value)}
            onRemoveValue={(vi) => removeAxisValue(idx, vi)}
            onRemove={() => removeAxis(idx)}
          />
        ))}
        <Button type="button" variant="outline" size="sm" onClick={addAxis}>
          <Plus className="size-4" />
          {t('products.variants.add_axis', { defaultValue: 'Add axis' })}
        </Button>
      </div>

      <div className="space-y-2">
        <label htmlFor="variants-sku-template" className="text-sm font-medium">
          {t('products.variants.sku_template_label', {
            defaultValue: 'SKU template (optional)',
          })}
        </label>
        <Input
          id="variants-sku-template"
          value={skuTemplate}
          onChange={(e) => setSkuTemplate(e.target.value)}
          placeholder="{master_sku}-{color}-{size}"
        />
      </div>

      <div className="flex items-center gap-2">
        <Button type="button" disabled={isPending} onClick={() => void handleGenerate()}>
          {isPending
            ? t('products.variants.generating', { defaultValue: 'Generating…' })
            : t('products.variants.generate', { defaultValue: 'Generate variants' })}
        </Button>
      </div>

      {error !== null ? <p className="text-sm text-rose-600">{error}</p> : null}

      {response !== null ? (
        <div className="rounded-md border bg-muted/40 p-3 text-xs">
          <p className="font-medium">
            {t('products.variants.created_summary', {
              created: response.created_count,
              skipped: response.skipped_count,
              defaultValue: '{{created}} created · {{skipped}} skipped (already existed)',
            })}
          </p>
          {response.created.length > 0 ? (
            <ul className="mt-2 max-h-48 space-y-0.5 overflow-y-auto font-mono">
              {response.created.map((v) => (
                <li key={v.sku}>{v.sku}</li>
              ))}
            </ul>
          ) : null}
        </div>
      ) : null}
    </section>
  );
}

function AxisRow({
  axis,
  onCodeChange,
  onAddValue,
  onRemoveValue,
  onRemove,
}: {
  axis: AxisDraft;
  onCodeChange: (code: string) => void;
  onAddValue: (value: string) => void;
  onRemoveValue: (idx: number) => void;
  onRemove: () => void;
}) {
  const { t } = useTranslation();
  const [draft, setDraft] = useState('');

  return (
    <div className="flex items-start gap-2">
      <Input
        value={axis.code}
        onChange={(e) => onCodeChange(e.target.value)}
        placeholder="color"
        className="w-32"
      />
      <div className="flex-1 space-y-1">
        <div className="flex flex-wrap gap-1">
          {axis.values.map((value, vi) => (
            <span
              key={value}
              className="inline-flex items-center gap-1 rounded-full bg-secondary px-2 py-0.5 text-xs"
            >
              <span>{value}</span>
              <button
                type="button"
                onClick={() => onRemoveValue(vi)}
                className="text-secondary-foreground/70 hover:text-secondary-foreground"
                aria-label={t('products.variants.remove_value', {
                  defaultValue: `Remove ${value}`,
                  value,
                })}
              >
                ×
              </button>
            </span>
          ))}
        </div>
        <div className="flex gap-1">
          <Input
            value={draft}
            onChange={(e) => setDraft(e.target.value)}
            onKeyDown={(e) => {
              if (e.key === 'Enter') {
                e.preventDefault();
                onAddValue(draft);
                setDraft('');
              }
            }}
            placeholder={t('products.variants.add_value_placeholder', {
              defaultValue: 'Add value & press Enter',
            })}
          />
        </div>
      </div>
      <Button type="button" variant="ghost" size="sm" onClick={onRemove}>
        <Trash2 className="size-4" />
      </Button>
    </div>
  );
}
