import { Plus, Trash2 } from 'lucide-react';
import { useEffect, useState } from 'react';
import { useTranslation } from 'react-i18next';

import { Button } from '@/components/ui/button';
import { Combobox } from '@/components/ui/combobox';
import { Input } from '@/components/ui/input';
import { jsonFetch } from '@/lib/http';

interface SchemaAttribute {
  id: string;
  code: string;
  type: string;
  label: { pl?: string; en?: string };
  options?: Array<{ code: string; label?: { pl?: string; en?: string } }>;
}

interface SchemaGroup {
  attributes: SchemaAttribute[];
}

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
 * VIEW-07.3 (#432) — dynamic SKU template default + onGenerated callback.
 *
 * Slice covers the axes editor + matrix generator, both wired to the
 * UI-02.6 backend (`POST /api/products/{master_id}/generate-variants`).
 *
 * - Local-state axes draft (`color: [red, blue, green]`).
 * - Per-axis add/remove + per-value chip removal.
 * - Generate button posts the cartesian product spec; the response
 *   reports created vs skipped (existing SKU collisions).
 * - SKU template (optional). Default computed from current axes:
 *   `{master_sku}-{axis_1}-...-{axis_n}`. Empty input falls back to
 *   the computed default at submit time.
 * - `onGenerated` fires after a successful POST so the host
 *   (`VariantsTabHost`) can re-fetch the variants list.
 */
function buildDefaultSkuTemplate(axes: AxisDraft[]): string {
  const codes = axes.map((a) => a.code.trim()).filter((c) => c !== '');
  if (codes.length === 0) return '{master_sku}';
  return `{master_sku}-${codes.map((c) => `{${c}}`).join('-')}`;
}

export function VariantsTab({
  masterProductId,
  onGenerated,
}: {
  masterProductId: string;
  onGenerated?: () => void;
}) {
  const { t } = useTranslation();
  const [axes, setAxes] = useState<AxisDraft[]>([{ code: 'color', values: [] }]);
  const [attributes, setAttributes] = useState<SchemaAttribute[]>([]);

  useEffect(() => {
    let cancelled = false;
    jsonFetch<{ groups: SchemaGroup[] }>(
      `/api/products/${masterProductId}/effective-attribute-groups`,
    )
      .then((body) => {
        if (cancelled) return;
        const flat = body.groups.flatMap((g) => g.attributes ?? []);
        setAttributes(flat);
      })
      .catch(() => undefined);
    return () => {
      cancelled = true;
    };
  }, [masterProductId]);
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
      const template =
        skuTemplate.trim() !== '' ? skuTemplate.trim() : buildDefaultSkuTemplate(axes);
      body.sku_template = template;
      const result = await jsonFetch<GenerateVariantsResponse>(
        `/api/products/${masterProductId}/generate-variants`,
        { method: 'POST', body },
      );
      setResponse(result);
      onGenerated?.();
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
            attributes={attributes}
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
          placeholder={buildDefaultSkuTemplate(axes)}
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
  attributes,
  onCodeChange,
  onAddValue,
  onRemoveValue,
  onRemove,
}: {
  axis: AxisDraft;
  attributes: SchemaAttribute[];
  onCodeChange: (code: string) => void;
  onAddValue: (value: string) => void;
  onRemoveValue: (idx: number) => void;
  onRemove: () => void;
}) {
  const { t } = useTranslation();
  const [draft, setDraft] = useState('');
  const valuesListId = `axis-values-${axis.code || 'empty'}`;
  const matchingAttribute = attributes.find((a) => a.code === axis.code);
  const suggestedValues = matchingAttribute?.options ?? [];

  // Variants axes only make sense for attributes with predefined
  // values (the generator iterates `select.options` / `multiselect.options`
  // to compute combinations). Restricting the Combobox to these types
  // also drops the noise of system attributes (created_at, updated_by,
  // ...) the previous datalist surfaced. Each option exposes the code
  // as `description` so operators can still see it under the label.
  const axisAttributeOptions = attributes
    .filter((a) => a.type === 'select' || a.type === 'multiselect')
    .map((a) => ({
      value: a.code,
      label: a.label?.pl ?? a.label?.en ?? a.code,
      description: a.code,
    }));

  return (
    <div className="flex items-start gap-2">
      <div className="w-48">
        <Combobox
          options={axisAttributeOptions}
          value={axis.code === '' ? null : axis.code}
          onChange={(next) => onCodeChange(next ?? '')}
          placeholder={t('products.variants.pick_axis_attribute', {
            defaultValue: 'Wybierz atrybut osi',
          })}
          searchPlaceholder={t('products.variants.search_axis_attribute', {
            defaultValue: 'Szukaj atrybutu…',
          })}
          emptyText={t('products.variants.no_select_attributes', {
            defaultValue: 'Brak atrybutów typu select/multiselect',
          })}
        />
      </div>
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
            list={valuesListId}
          />
          <datalist id={valuesListId}>
            {suggestedValues.map((opt) => (
              <option key={opt.code} value={opt.code}>
                {opt.label?.pl ?? opt.label?.en ?? opt.code}
              </option>
            ))}
          </datalist>
        </div>
        {suggestedValues.length > 0 ? (
          <div className="flex flex-wrap gap-1 pt-1">
            {suggestedValues
              .filter((opt) => !axis.values.includes(opt.code))
              .map((opt) => (
                <button
                  key={opt.code}
                  type="button"
                  onClick={() => onAddValue(opt.code)}
                  className="rounded border border-dashed px-1.5 py-0.5 text-[10px] text-muted-foreground hover:border-solid hover:text-foreground"
                >
                  +{opt.code}
                </button>
              ))}
          </div>
        ) : null}
      </div>
      <Button type="button" variant="ghost" size="sm" onClick={onRemove}>
        <Trash2 className="size-4" />
      </Button>
    </div>
  );
}
