import { useEffect, useMemo, useState } from 'react';
import { useTranslation } from 'react-i18next';
import { VariantsTab } from '@/components/catalog/variants-tab';
import type { Provenance } from '@/components/provenance-badge';
import { jsonFetch } from '@/lib/http';

import { AttrGroupCard } from './attr-group-card';
import { AttrRow } from './attr-row';
import type { AttributeMeta, GroupMeta, ProductLocale } from './types';

interface VariantRow {
  id: string;
  code: string;
  enabled?: boolean;
  attributesIndexed?: Record<string, unknown>;
}

interface VariantsResponse {
  member?: VariantRow[];
  'hydra:member'?: VariantRow[];
}

export interface VariantsTabHostProps {
  productId: string;
}

/**
 * VIEW-07 (#420) — Variants tab body. Two stacked sections per the
 * operator's spec: (1) per-variant collapsible cards in the same
 * AttrGroupCard style as the Atrybuty tab, (2) the existing axes
 * editor + matrix generator from UI-02.18 kept below the list so the
 * cartesian-product flow remains discoverable.
 */
export function VariantsTabHost({ productId }: VariantsTabHostProps) {
  const { t } = useTranslation();
  const [variants, setVariants] = useState<VariantRow[]>([]);
  const [groups, setGroups] = useState<GroupMeta[]>([]);
  const [expanded, setExpanded] = useState<Set<string>>(new Set());

  useEffect(() => {
    let cancelled = false;
    if (productId === '') return;
    Promise.all([
      jsonFetch<VariantsResponse>(`/api/products?parent_id=${productId}`).then((body) => {
        if (cancelled) return;
        const list = body.member ?? body['hydra:member'] ?? [];
        const arr = Array.isArray(list) ? list : [];
        setVariants(arr);
        setExpanded(new Set(arr.map((v) => v.id)));
      }),
      jsonFetch<{ groups: GroupMeta[] }>(
        `/api/products/${productId}/effective-attribute-groups`,
      ).then((body) => {
        if (!cancelled) setGroups(body.groups);
      }),
    ]).catch(() => undefined);
    return () => {
      cancelled = true;
    };
  }, [productId]);

  const variantAttributes = useMemo<AttributeMeta[]>(() => {
    // Variant cards show the master's attributes, but only the editable
    // ones — system fields (created_at, etc.) collapse to noise here.
    return groups.flatMap((g) => g.attributes.filter((a) => !a.is_system));
  }, [groups]);

  const toggle = (id: string): void => {
    setExpanded((prev) => {
      const next = new Set(prev);
      if (next.has(id)) next.delete(id);
      else next.add(id);
      return next;
    });
  };

  return (
    <div className="space-y-3">
      <div>
        <header className="mb-3">
          <h3 className="text-[14px] font-semibold tracking-tight">
            {t('products.detail.variants.list_title', { defaultValue: 'Lista wariantów' })}
          </h3>
          <p className="num text-[11.5px] text-zinc-500">
            {t('products.detail.variants.count', {
              count: variants.length,
              defaultValue: '{{count}} wariantów',
            })}
          </p>
        </header>
        {variants.length === 0 ? (
          <p className="rounded-2xl border border-dashed border-zinc-300 bg-white px-5 py-8 text-center text-[13px] text-muted-foreground">
            {t('products.detail.variants.empty_body', {
              defaultValue:
                'Brak wariantów. Wygeneruj je z osi w sekcji poniżej lub dodaj ręcznie.',
            })}
          </p>
        ) : (
          <div className="space-y-3">
            {variants.map((variant) => {
              const attrs = unwrap(variant.attributesIndexed ?? {});
              const filled = countFilled(variantAttributes, attrs);
              return (
                <AttrGroupCard
                  key={variant.id}
                  id={`variant-${variant.id}`}
                  title={variantTitle(variant, attrs)}
                  filledCount={filled}
                  totalCount={variantAttributes.length}
                  expanded={expanded.has(variant.id)}
                  onToggle={() => toggle(variant.id)}
                >
                  {variantAttributes.map((attr) => (
                    <AttrRow
                      key={`${variant.id}-${attr.code}`}
                      attribute={attr}
                      value={attrs[attr.code]}
                      provenance={resolveProv(attr, variant.attributesIndexed)}
                      locale={'pl' satisfies ProductLocale}
                      isEditing={false}
                      isLocked
                      onChange={() => undefined}
                    />
                  ))}
                </AttrGroupCard>
              );
            })}
          </div>
        )}
      </div>

      <div className="rounded-2xl border border-zinc-100 bg-zinc-50/40 p-1">
        <VariantsTab masterProductId={productId} />
      </div>
    </div>
  );
}

function unwrap(raw: Record<string, unknown>): Record<string, unknown> {
  const out: Record<string, unknown> = {};
  for (const [k, v] of Object.entries(raw)) {
    if (v !== null && typeof v === 'object' && !Array.isArray(v) && 'value' in v) {
      out[k] = (v as { value: unknown }).value;
    } else {
      out[k] = v;
    }
  }
  return out;
}

function countFilled(attrs: AttributeMeta[], values: Record<string, unknown>): number {
  let n = 0;
  for (const a of attrs) {
    const v = values[a.code];
    if (v === undefined || v === null) continue;
    if (typeof v === 'string' && v.trim() === '') continue;
    n += 1;
  }
  return n;
}

function variantTitle(variant: VariantRow, attrs: Record<string, unknown>): string {
  const name = typeof attrs.name === 'string' ? attrs.name : '';
  if (name !== '') return `${variant.code} · ${name}`;
  return variant.code;
}

function resolveProv(
  attr: AttributeMeta,
  indexed: Record<string, unknown> | undefined,
): Provenance {
  const meta = (indexed as Record<string, { provenance?: Provenance }> | undefined)?.[attr.code];
  if (meta && typeof meta === 'object' && typeof meta.provenance === 'string') {
    return meta.provenance;
  }
  return 'manual';
}
