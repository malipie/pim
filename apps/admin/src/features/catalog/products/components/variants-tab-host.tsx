import { useEffect, useMemo, useState } from 'react';
import { useTranslation } from 'react-i18next';
import { VariantsTab } from '@/components/catalog/variants-tab';
import type { Provenance } from '@/components/provenance-badge';
import { Button } from '@/components/ui/button';
import { toast } from '@/components/ui/toast';
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
 * VIEW-07 (#420) — Variants tab body.
 * VIEW-07.3 (#432) — generator above list, default-collapsed cards
 * with global expand/collapse toggles, inline edit through a global
 * `Edytuj warianty` toggle (per-variant dirty state, batch save with
 * `Promise.allSettled`), and the per-attribute "Copy to other
 * variants" action that replaces the ProvenanceBadge in the variant
 * RHS slot.
 */
export function VariantsTabHost({ productId }: VariantsTabHostProps) {
  const { t } = useTranslation();
  const [variants, setVariants] = useState<VariantRow[]>([]);
  const [groups, setGroups] = useState<GroupMeta[]>([]);
  const [expanded, setExpanded] = useState<Set<string>>(new Set());
  const [isEditing, setIsEditing] = useState<boolean>(false);
  const [dirtyByVariant, setDirtyByVariant] = useState<Record<string, Record<string, unknown>>>({});
  const [isSaving, setIsSaving] = useState<boolean>(false);
  const [reloadKey, setReloadKey] = useState<number>(0);

  // biome-ignore lint/correctness/useExhaustiveDependencies: reloadKey is the intentional re-fetch trigger after Generate/Save (bumped via setReloadKey).
  useEffect(() => {
    let cancelled = false;
    if (productId === '') return;
    Promise.all([
      jsonFetch<VariantsResponse>(`/api/products?parent_id=${productId}`).then((body) => {
        if (cancelled) return;
        const list = body.member ?? body['hydra:member'] ?? [];
        const arr = Array.isArray(list) ? list : [];
        setVariants(arr);
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
  }, [productId, reloadKey]);

  const variantAttributes = useMemo<AttributeMeta[]>(() => {
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

  const expandAll = (): void => {
    setExpanded(new Set(variants.map((v) => v.id)));
  };

  const collapseAll = (): void => {
    setExpanded(new Set());
  };

  const setVariantField = (variantId: string, code: string, value: unknown): void => {
    setDirtyByVariant((prev) => ({
      ...prev,
      [variantId]: { ...(prev[variantId] ?? {}), [code]: value },
    }));
  };

  const fieldValue = (variantId: string, code: string, base: Record<string, unknown>): unknown => {
    const dirty = dirtyByVariant[variantId];
    if (dirty !== undefined && Object.hasOwn(dirty, code)) return dirty[code];
    return base[code];
  };

  const copyToOthers = (sourceVariantId: string, code: string): void => {
    const sourceVariant = variants.find((v) => v.id === sourceVariantId);
    if (sourceVariant === undefined) return;
    const sourceAttrs = unwrap(sourceVariant.attributesIndexed ?? {});
    const value = fieldValue(sourceVariantId, code, sourceAttrs);
    const others = variants.filter((v) => v.id !== sourceVariantId);
    if (others.length === 0) return;
    setDirtyByVariant((prev) => {
      const next = { ...prev };
      for (const v of others) {
        next[v.id] = { ...(next[v.id] ?? {}), [code]: value };
      }
      return next;
    });
    toast.success(
      t('products.detail.variants.copy_to_others.success', {
        defaultValue: 'Skopiowano do {{count}} wariantów',
        count: others.length,
      }),
    );
  };

  const cancelEdit = (): void => {
    setDirtyByVariant({});
    setIsEditing(false);
  };

  const handleSaveAll = async (): Promise<void> => {
    if (isSaving) return;
    const targets = Object.entries(dirtyByVariant).filter(([, d]) => Object.keys(d).length > 0);
    if (targets.length === 0) {
      setIsEditing(false);
      return;
    }
    setIsSaving(true);
    const results = await Promise.allSettled(
      targets.map(([id, attributes]) =>
        jsonFetch(`/api/products/${id}`, {
          method: 'PATCH',
          contentType: 'application/merge-patch+json',
          body: { attributes },
        }).then(() => id),
      ),
    );
    const failedIds = results
      .map((r, idx) => (r.status === 'rejected' ? targets[idx]?.[0] : null))
      .filter((x): x is string => x !== null && x !== undefined);
    const failedSkus = failedIds
      .map((id) => variants.find((v) => v.id === id)?.code ?? id)
      .join(', ');
    if (failedIds.length === 0) {
      toast.success(
        t('products.detail.variants.save.success', { defaultValue: 'Zapisano warianty' }),
      );
      setDirtyByVariant({});
      setIsEditing(false);
    } else {
      toast.error(
        t('products.detail.variants.save.partial', {
          defaultValue: 'Zapisano {{ok}}/{{total}}. Błędy: {{skus}}',
          ok: targets.length - failedIds.length,
          total: targets.length,
          skus: failedSkus,
        }),
      );
      setDirtyByVariant((prev) => {
        const next: Record<string, Record<string, unknown>> = {};
        for (const id of failedIds) if (prev[id] !== undefined) next[id] = prev[id];
        return next;
      });
    }
    setReloadKey((k) => k + 1);
    setIsSaving(false);
  };

  return (
    <div className="space-y-3">
      <div className="rounded-2xl border border-zinc-100 bg-zinc-50/40 p-1">
        <VariantsTab masterProductId={productId} onGenerated={() => setReloadKey((k) => k + 1)} />
      </div>

      <div>
        <header className="mb-3 flex items-start justify-between gap-3">
          <div>
            <h3 className="text-[14px] font-semibold tracking-tight">
              {t('products.detail.variants.list_title', { defaultValue: 'Lista wariantów' })}
            </h3>
            <p className="num text-[11.5px] text-zinc-500">
              {t('products.detail.variants.count', {
                count: variants.length,
                defaultValue: '{{count}} wariantów',
              })}
            </p>
          </div>
          {variants.length > 0 ? (
            <div className="flex flex-wrap items-center gap-2">
              <Button type="button" variant="ghost" size="sm" onClick={expandAll}>
                {t('products.detail.variants.expand_all', { defaultValue: 'Rozwiń wszystkie' })}
              </Button>
              <Button type="button" variant="ghost" size="sm" onClick={collapseAll}>
                {t('products.detail.variants.collapse_all', { defaultValue: 'Zwiń wszystkie' })}
              </Button>
              <span className="mx-1 h-5 w-px bg-zinc-200" aria-hidden />
              {isEditing ? (
                <>
                  <Button
                    type="button"
                    variant="ghost"
                    size="sm"
                    onClick={cancelEdit}
                    disabled={isSaving}
                  >
                    {t('products.detail.variants.cancel', { defaultValue: 'Anuluj' })}
                  </Button>
                  <Button
                    type="button"
                    size="sm"
                    onClick={() => void handleSaveAll()}
                    disabled={isSaving}
                  >
                    {t('products.detail.variants.save', { defaultValue: 'Zapisz' })}
                  </Button>
                </>
              ) : (
                <Button
                  type="button"
                  size="sm"
                  onClick={() => setIsEditing(true)}
                  aria-pressed={isEditing}
                >
                  {t('products.detail.variants.edit', { defaultValue: 'Edytuj warianty' })}
                </Button>
              )}
            </div>
          ) : null}
        </header>
        {variants.length === 0 ? (
          <p className="rounded-2xl border border-dashed border-zinc-300 bg-white px-5 py-8 text-center text-[13px] text-muted-foreground">
            {t('products.detail.variants.empty_body', {
              defaultValue:
                'Brak wariantów. Wygeneruj je z osi w sekcji powyżej lub dodaj ręcznie.',
            })}
          </p>
        ) : (
          <div className="space-y-3">
            {variants.map((variant) => {
              const baseAttrs = unwrap(variant.attributesIndexed ?? {});
              const filled = countFilled(variantAttributes, baseAttrs, dirtyByVariant[variant.id]);
              return (
                <AttrGroupCard
                  key={variant.id}
                  id={`variant-${variant.id}`}
                  title={variantTitle(variant, baseAttrs)}
                  filledCount={filled}
                  totalCount={variantAttributes.length}
                  expanded={expanded.has(variant.id)}
                  onToggle={() => toggle(variant.id)}
                >
                  {variantAttributes.map((attr) => (
                    <AttrRow
                      key={`${variant.id}-${attr.code}`}
                      attribute={attr}
                      value={fieldValue(variant.id, attr.code, baseAttrs)}
                      provenance={resolveProv(attr, variant.attributesIndexed)}
                      locale={'pl' satisfies ProductLocale}
                      isEditing={isEditing && !attr.is_system}
                      isLocked={attr.is_system}
                      onChange={(next) => setVariantField(variant.id, attr.code, next)}
                      onCopyToOthers={
                        isEditing ? () => copyToOthers(variant.id, attr.code) : undefined
                      }
                    />
                  ))}
                </AttrGroupCard>
              );
            })}
          </div>
        )}
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

function countFilled(
  attrs: AttributeMeta[],
  base: Record<string, unknown>,
  dirty: Record<string, unknown> | undefined,
): number {
  let n = 0;
  for (const a of attrs) {
    const v = dirty !== undefined && Object.hasOwn(dirty, a.code) ? dirty[a.code] : base[a.code];
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
