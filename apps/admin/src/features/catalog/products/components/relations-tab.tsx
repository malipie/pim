import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { ArrowDownLeft, Link2, Plus, Search, Trash2, X } from 'lucide-react';
import { type FormEvent, useEffect, useMemo, useState } from 'react';
import { useTranslation } from 'react-i18next';

import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogDescription, DialogTitle } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { jsonFetch } from '@/lib/http';
import { cn } from '@/lib/utils';

/**
 * ADR-014 / MOD-12 (#904) — „Powiązania" tab on the product detail page.
 *
 * Backed by:
 *  - `GET /api/objects/{id}/relations`        — current links per attribute (MOD-06)
 *  - `PUT /api/objects/{id}/relations/{code}` — atomic replace (MOD-06)
 *  - `DELETE /api/objects/{id}/relations/{code}/{targetId}` (MOD-06)
 *  - `GET /api/objects/{id}/relations/reverse` (MOD-07)
 *
 * Render modes per attribute:
 *  - `cardinality=one`  → single picker (one button → modal → set one target)
 *  - `cardinality=many` → grid (list of linked objects + add/remove)
 *  - `advanced=true`    → grid with per-row metadata inputs
 *                         (text / number / boolean, validated by MOD-08)
 *
 * The "Powiązania zwrotne" section under the active links lists every
 * source object referencing the current product through one of its
 * relation attributes. Read-only; edits live on the source side.
 *
 * Object picker (`ObjectPickerDialog`) is intentionally lightweight: a
 * search-as-you-type field + a flat list of candidate objects scoped to
 * the attribute's `relation_target_object_type_ids` allowlist. Refining
 * the picker (favourites, recent, multi-select queue) is queued for the
 * design-pass follow-up.
 */
interface RelationsTabProps {
  productId: string;
}

interface RelationAttribute {
  id: string;
  code: string;
  label: Record<string, string> | null;
  cardinality: 'one' | 'many' | null;
  advanced: boolean;
}

interface RelationRow {
  id: string;
  targetObjectId: string;
  position: number;
  metadata: Record<string, unknown> | unknown[];
}

interface RelationGroupPayload {
  attribute: RelationAttribute;
  relations: RelationRow[];
}

interface RelationsResponse {
  sourceObjectId: string;
  relationAttributes: RelationGroupPayload[];
}

interface ReverseGroupPayload {
  sourceObjectType: { id: string; code: string; kind: string };
  attribute: { id: string; code: string; label: Record<string, string> | null };
  sources: Array<{ id: string; code: string; relationId: string; position: number }>;
}

interface ReverseResponse {
  targetObjectId: string;
  reverseRelations: ReverseGroupPayload[];
}

interface ObjectsListResponse {
  'hydra:member'?: Array<{ id: string; code: string; objectType?: { id: string } | null }>;
  member?: Array<{ id: string; code: string; objectType?: { id: string } | null }>;
}

interface AttributeFull extends RelationAttribute {
  relationTargetObjectTypeIds?: string[];
  validationRules?: Record<string, unknown> | null;
}

function labelText(label: Record<string, string> | null | undefined, locale: string): string {
  if (!label) return '';
  return label[locale] ?? label.pl ?? label.en ?? Object.values(label)[0] ?? '';
}

export function RelationsTab({ productId }: RelationsTabProps) {
  const { t, i18n } = useTranslation();
  const queryClient = useQueryClient();
  const locale = i18n.language === 'pl' ? 'pl' : 'en';

  const relationsQuery = useQuery<RelationsResponse>({
    queryKey: ['objects', productId, 'relations'],
    queryFn: () =>
      jsonFetch<RelationsResponse>(`/api/objects/${productId}/relations`, {
        accept: 'application/json',
      }),
    staleTime: 5_000,
  });

  const reverseQuery = useQuery<ReverseResponse>({
    queryKey: ['objects', productId, 'relations', 'reverse'],
    queryFn: () =>
      jsonFetch<ReverseResponse>(`/api/objects/${productId}/relations/reverse`, {
        accept: 'application/json',
      }),
    staleTime: 5_000,
  });

  const invalidate = () => {
    void queryClient.invalidateQueries({ queryKey: ['objects', productId, 'relations'] });
    void queryClient.invalidateQueries({
      queryKey: ['objects', productId, 'relations', 'reverse'],
    });
  };

  if (relationsQuery.isLoading) {
    return <p className="text-sm text-muted-foreground">{t('app.loading')}</p>;
  }
  if (relationsQuery.isError || !relationsQuery.data) {
    return (
      <p className="text-sm text-destructive">
        {t('relations.fetch_error', { defaultValue: 'Nie udało się pobrać powiązań.' })}
      </p>
    );
  }

  const groups = relationsQuery.data.relationAttributes;
  const reverseGroups = reverseQuery.data?.reverseRelations ?? [];

  if (groups.length === 0 && reverseGroups.length === 0) {
    return (
      <div className="rounded-2xl border border-dashed border-line bg-surface p-8 text-center">
        <Link2 className="mx-auto size-6 text-muted-foreground" />
        <h3 className="mt-2 text-sm font-semibold">
          {t('relations.empty_title', { defaultValue: 'Brak atrybutów typu relacja' })}
        </h3>
        <p className="mt-1 text-xs text-muted-foreground">
          {t('relations.empty_desc', {
            defaultValue:
              'Skonfiguruj atrybuty relation dla tego ObjectType w zakładce Modelowanie (MOD-13), żeby zacząć budować powiązania.',
          })}
        </p>
      </div>
    );
  }

  return (
    <div className="space-y-6">
      {groups.map((group) => (
        <RelationGroupCard
          key={group.attribute.id}
          productId={productId}
          group={group}
          locale={locale}
          onChange={invalidate}
        />
      ))}

      {reverseGroups.length > 0 ? (
        <div className="rounded-2xl border border-line bg-surface p-5 soft-shadow">
          <div className="flex items-center gap-2">
            <ArrowDownLeft className="size-4 text-muted-foreground" />
            <h3 className="text-sm font-semibold">
              {t('relations.reverse_title', { defaultValue: 'Powiązania zwrotne (read-only)' })}
            </h3>
          </div>
          <p className="mt-1 text-xs text-muted-foreground">
            {t('relations.reverse_desc', {
              defaultValue: 'Obiekty, które wskazują na ten produkt przez swoje atrybuty relacji.',
            })}
          </p>
          <div className="mt-4 space-y-3">
            {reverseGroups.map((group) => (
              <div
                key={`${group.sourceObjectType.id}:${group.attribute.id}`}
                className="rounded-xl border border-line bg-background p-3"
              >
                <div className="text-xs font-medium">
                  <span className="text-foreground">{group.attribute.code}</span>
                  <span className="ml-2 text-muted-foreground">
                    ({group.sourceObjectType.code} / {group.sourceObjectType.kind})
                  </span>
                </div>
                <ul className="mt-2 flex flex-wrap gap-1.5">
                  {group.sources.map((src) => (
                    <li
                      key={src.relationId}
                      className="rounded-md bg-zinc-100 px-2 py-1 text-xs font-mono"
                    >
                      {src.code}
                    </li>
                  ))}
                </ul>
              </div>
            ))}
          </div>
        </div>
      ) : null}
    </div>
  );
}

function RelationGroupCard({
  productId,
  group,
  locale,
  onChange,
}: {
  productId: string;
  group: RelationGroupPayload;
  locale: string;
  onChange: () => void;
}) {
  const { t } = useTranslation();
  const [pickerOpen, setPickerOpen] = useState(false);
  const [error, setError] = useState<string | null>(null);
  const attribute = group.attribute as AttributeFull;
  const cardinality = attribute.cardinality ?? 'many';

  // The list endpoint returns attribute id only; fetching the full
  // payload gives us `relationTargetObjectTypeIds` for the picker filter
  // and `validation_rules.advanced_fields` for the metadata editor.
  const attributeQuery = useQuery<AttributeFull>({
    queryKey: ['attributes', attribute.id],
    queryFn: () =>
      jsonFetch<AttributeFull>(`/api/attributes/${attribute.id}`, {
        accept: 'application/json',
      }),
    staleTime: 60_000,
  });

  const writeMutation = useMutation({
    mutationFn: async (targets: Array<{ id: string; metadata?: Record<string, unknown> }>) => {
      const body: { targets: Array<{ id: string; metadata?: Record<string, unknown> }> } = {
        targets,
      };
      await jsonFetch(`/api/objects/${productId}/relations/${attribute.code}`, {
        method: 'PUT',
        body,
      });
    },
    onError: (e: unknown) => {
      setError(e instanceof Error ? e.message : 'Nieznany błąd');
    },
    onSuccess: () => {
      setError(null);
      onChange();
    },
  });

  const deleteMutation = useMutation({
    mutationFn: async (targetId: string) => {
      await jsonFetch(`/api/objects/${productId}/relations/${attribute.code}/${targetId}`, {
        method: 'DELETE',
      });
    },
    onError: (e: unknown) => {
      setError(e instanceof Error ? e.message : 'Nieznany błąd');
    },
    onSuccess: () => {
      setError(null);
      onChange();
    },
  });

  const targetIds = attributeQuery.data?.relationTargetObjectTypeIds ?? [];

  // MODR-08 (#930) — batch-fetch a summary (code + name + ObjectType code)
  // for every currently linked target so the preview cards render in a
  // single round trip instead of N parallel GET /api/objects/{id} calls.
  const linkedTargetIds = useMemo(
    () =>
      Array.from(new Set(group.relations.map((r) => r.targetObjectId)))
        .sort()
        .filter((v) => v.length > 0),
    [group.relations],
  );
  const summariesQuery = useQuery<RelationTargetSummary[]>({
    queryKey: ['objects', 'summaries', linkedTargetIds],
    queryFn: () =>
      jsonFetch<RelationTargetSummary[]>('/api/objects/summaries', {
        method: 'POST',
        contentType: 'application/json',
        accept: 'application/json',
        body: { ids: linkedTargetIds },
      }),
    enabled: linkedTargetIds.length > 0,
    staleTime: 30_000,
  });
  const summariesById = useMemo(() => {
    const map = new Map<string, RelationTargetSummary>();
    for (const s of summariesQuery.data ?? []) map.set(s.id, s);
    return map;
  }, [summariesQuery.data]);

  const handleAdd = (newTargetId: string) => {
    const existing = group.relations.map((r) => ({
      id: r.targetObjectId,
      metadata: normaliseMetadata(r.metadata),
    }));
    if (cardinality === 'one') {
      writeMutation.mutate([{ id: newTargetId }]);
    } else {
      writeMutation.mutate([...existing, { id: newTargetId }]);
    }
    setPickerOpen(false);
  };

  const handleMetadataChange = (targetId: string, nextMetadata: Record<string, unknown>) => {
    const updated = group.relations.map((r) => ({
      id: r.targetObjectId,
      metadata: r.targetObjectId === targetId ? nextMetadata : normaliseMetadata(r.metadata),
    }));
    writeMutation.mutate(updated);
  };

  return (
    <div className="rounded-2xl border border-line bg-surface p-5 soft-shadow">
      <div className="flex flex-wrap items-center justify-between gap-3">
        <div>
          <h3 className="text-sm font-semibold">
            {labelText(attribute.label, locale) || attribute.code}
          </h3>
          <p className="mt-0.5 text-xs text-muted-foreground">
            {attribute.code} · cardinality={cardinality}
            {attribute.advanced ? ' · advanced' : ''}
          </p>
        </div>
        <Button
          type="button"
          variant="outline"
          size="sm"
          onClick={() => setPickerOpen(true)}
          disabled={cardinality === 'one' && group.relations.length > 0}
        >
          <Plus className="size-4" />
          {cardinality === 'one'
            ? t('relations.set_target', { defaultValue: 'Wybierz powiązanie' })
            : t('relations.add_target', { defaultValue: 'Dodaj powiązanie' })}
        </Button>
      </div>

      {error !== null ? <p className="mt-3 text-xs text-destructive">{error}</p> : null}

      <div className="mt-4 space-y-2">
        {group.relations.length === 0 ? (
          <p className="text-xs text-muted-foreground">
            {t('relations.no_links_yet', { defaultValue: 'Brak powiązań.' })}
          </p>
        ) : (
          group.relations.map((row) => (
            <RelationRowItem
              key={row.id}
              row={row}
              advanced={attribute.advanced}
              advancedFields={extractAdvancedFields(attributeQuery.data?.validationRules)}
              summary={summariesById.get(row.targetObjectId)}
              onRemove={() => deleteMutation.mutate(row.targetObjectId)}
              onMetadataChange={(next) => handleMetadataChange(row.targetObjectId, next)}
              disabled={writeMutation.isPending || deleteMutation.isPending}
            />
          ))
        )}
      </div>

      {pickerOpen ? (
        <ObjectPickerDialog
          allowedObjectTypeIds={targetIds}
          excludedObjectIds={group.relations.map((r) => r.targetObjectId).concat(productId)}
          onPick={handleAdd}
          onClose={() => setPickerOpen(false)}
        />
      ) : null}
    </div>
  );
}

interface RelationTargetSummary {
  id: string;
  code: string;
  name: string | null;
  objectType: { id: string; code: string; kind: string };
}

function RelationRowItem({
  row,
  advanced,
  advancedFields,
  summary,
  onRemove,
  onMetadataChange,
  disabled,
}: {
  row: RelationRow;
  advanced: boolean;
  advancedFields: Array<{
    code: string;
    type: 'text' | 'number' | 'boolean';
    label: Record<string, string>;
    required: boolean;
  }>;
  /**
   * MODR-08 (#930) — pre-fetched summary from the batch endpoint
   * (`POST /api/objects/summaries`). When absent, the card falls back
   * to the target UUID's first 8 characters.
   */
  summary?: RelationTargetSummary;
  onRemove: () => void;
  onMetadataChange: (next: Record<string, unknown>) => void;
  disabled: boolean;
}) {
  const { t, i18n } = useTranslation();
  const locale = i18n.language === 'pl' ? 'pl' : 'en';

  const targetMetadata = normaliseMetadata(row.metadata);
  const displayCode = summary?.code ?? row.targetObjectId.slice(0, 8);
  const displayName = summary?.name ?? null;
  const objectTypeCode = summary?.objectType.code ?? null;

  return (
    <div className="rounded-xl border border-line bg-background p-3">
      <div className="flex items-center justify-between gap-2">
        <div className="min-w-0">
          {displayName !== null ? (
            <div className="truncate text-sm font-semibold tracking-tight">{displayName}</div>
          ) : null}
          <div className="flex items-center gap-2 text-[11px] text-muted-foreground">
            <span className="font-mono">{displayCode}</span>
            {objectTypeCode ? (
              <span className="rounded bg-muted px-1.5 py-0.5 font-mono uppercase tracking-wide">
                {objectTypeCode}
              </span>
            ) : null}
          </div>
        </div>
        <Button
          type="button"
          variant="ghost"
          size="sm"
          onClick={onRemove}
          disabled={disabled}
          aria-label={t('relations.remove_link', { defaultValue: 'Usuń powiązanie' })}
        >
          <Trash2 className="size-4" />
        </Button>
      </div>

      {advanced && advancedFields.length > 0 ? (
        <div className="mt-3 grid grid-cols-1 gap-2 sm:grid-cols-2">
          {advancedFields.map((field) => {
            const currentValue = targetMetadata[field.code];
            return (
              <div key={field.code} className="flex flex-col gap-1">
                <span className="text-[11px] font-medium text-muted-foreground">
                  {labelText(field.label, locale) || field.code}
                  {field.required ? <span className="ml-1 text-destructive">*</span> : null}
                </span>
                {field.type === 'boolean' ? (
                  <input
                    aria-label={labelText(field.label, locale) || field.code}
                    type="checkbox"
                    checked={Boolean(currentValue)}
                    disabled={disabled}
                    onChange={(e) =>
                      onMetadataChange({ ...targetMetadata, [field.code]: e.target.checked })
                    }
                  />
                ) : (
                  <Input
                    aria-label={labelText(field.label, locale) || field.code}
                    type={field.type === 'number' ? 'number' : 'text'}
                    value={
                      currentValue === undefined || currentValue === null
                        ? ''
                        : String(currentValue)
                    }
                    disabled={disabled}
                    onChange={(e) => {
                      const raw = e.target.value;
                      const next =
                        field.type === 'number' ? (raw === '' ? undefined : Number(raw)) : raw;
                      onMetadataChange({ ...targetMetadata, [field.code]: next });
                    }}
                  />
                )}
              </div>
            );
          })}
        </div>
      ) : null}
    </div>
  );
}

function ObjectPickerDialog({
  allowedObjectTypeIds,
  excludedObjectIds,
  onPick,
  onClose,
}: {
  allowedObjectTypeIds: string[];
  excludedObjectIds: string[];
  onPick: (id: string) => void;
  onClose: () => void;
}) {
  const { t } = useTranslation();
  const [query, setQuery] = useState('');
  const [debounced, setDebounced] = useState('');

  useEffect(() => {
    const timer = setTimeout(() => setDebounced(query), 200);
    return () => clearTimeout(timer);
  }, [query]);

  const candidatesQuery = useQuery<ObjectsListResponse>({
    queryKey: ['objects', 'picker', debounced, allowedObjectTypeIds.join(',')],
    queryFn: () => {
      const params = new URLSearchParams();
      if (debounced.length > 0) params.set('code', debounced);
      params.set('itemsPerPage', '50');
      return jsonFetch<ObjectsListResponse>(`/api/objects?${params.toString()}`, {
        accept: 'application/json',
      });
    },
  });

  const items = useMemo(() => {
    const raw = candidatesQuery.data?.['hydra:member'] ?? candidatesQuery.data?.member ?? [];
    return raw.filter((item) => {
      if (excludedObjectIds.includes(item.id)) return false;
      if (allowedObjectTypeIds.length === 0) return true;
      const otId = item.objectType?.id;
      return Boolean(otId && allowedObjectTypeIds.includes(otId));
    });
  }, [candidatesQuery.data, allowedObjectTypeIds, excludedObjectIds]);

  const handleSubmit = (e: FormEvent) => {
    e.preventDefault();
  };

  return (
    <Dialog open onOpenChange={onClose}>
      <DialogContent className="max-h-[80vh] max-w-2xl overflow-hidden">
        <DialogTitle>{t('relations.picker_title', { defaultValue: 'Wybierz obiekt' })}</DialogTitle>
        <DialogDescription>
          {t('relations.picker_desc', {
            defaultValue: 'Wpisz kod obiektu — wyszukiwanie zawęża listę kandydatów.',
          })}
        </DialogDescription>
        <form onSubmit={handleSubmit} className="flex items-center gap-2">
          <Search className="size-4 text-muted-foreground" />
          <Input
            autoFocus
            placeholder={t('relations.picker_search', { defaultValue: 'Szukaj po kodzie…' })}
            value={query}
            onChange={(e) => setQuery(e.target.value)}
          />
        </form>
        <ul className="mt-3 max-h-[55vh] space-y-1 overflow-y-auto">
          {candidatesQuery.isLoading ? (
            <li className="text-xs text-muted-foreground">{t('app.loading')}</li>
          ) : null}
          {items.length === 0 && !candidatesQuery.isLoading ? (
            <li className="text-xs text-muted-foreground">
              {t('relations.picker_no_matches', { defaultValue: 'Brak dopasowań.' })}
            </li>
          ) : null}
          {items.map((item) => (
            <li key={item.id}>
              <button
                type="button"
                onClick={() => onPick(item.id)}
                className={cn(
                  'flex w-full items-center justify-between rounded-md border border-transparent px-3 py-2 text-left',
                  'hover:border-zinc-200 hover:bg-zinc-50',
                )}
              >
                <span className="font-mono text-sm">{item.code}</span>
                <Plus className="size-4 text-muted-foreground" />
              </button>
            </li>
          ))}
        </ul>
        <div className="mt-2 flex justify-end">
          <Button type="button" variant="ghost" size="sm" onClick={onClose}>
            <X className="size-4" />
            {t('app.cancel')}
          </Button>
        </div>
      </DialogContent>
    </Dialog>
  );
}

function normaliseMetadata(raw: unknown): Record<string, unknown> {
  if (raw && typeof raw === 'object' && !Array.isArray(raw)) {
    return raw as Record<string, unknown>;
  }
  return {};
}

function extractAdvancedFields(rules: Record<string, unknown> | null | undefined): Array<{
  code: string;
  type: 'text' | 'number' | 'boolean';
  label: Record<string, string>;
  required: boolean;
}> {
  if (!rules) return [];
  const raw = rules.advanced_fields;
  if (!Array.isArray(raw)) return [];
  const out: Array<{
    code: string;
    type: 'text' | 'number' | 'boolean';
    label: Record<string, string>;
    required: boolean;
  }> = [];
  for (const entry of raw) {
    if (typeof entry !== 'object' || entry === null) continue;
    const e = entry as Record<string, unknown>;
    const code = typeof e.code === 'string' ? e.code : '';
    const type = e.type === 'text' || e.type === 'number' || e.type === 'boolean' ? e.type : 'text';
    const label =
      typeof e.label === 'object' && e.label !== null ? (e.label as Record<string, string>) : {};
    const required = Boolean(e.required);
    if (code !== '') out.push({ code, type, label, required });
  }
  return out;
}
