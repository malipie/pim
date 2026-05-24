import { useQuery, useQueryClient } from '@tanstack/react-query';
import { useState } from 'react';
import { useTranslation } from 'react-i18next';

import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Textarea } from '@/components/ui/textarea';
import { toast } from '@/components/ui/toast';
import { HttpError, jsonFetch } from '@/lib/http';

/**
 * MODR-10 (#932) — inline expand/edit panel for a related target object.
 *
 * Shown beneath a `RelationRowItem` when the operator clicks the chevron
 * expand button. Renders the target's form-schema groups + lets the
 * operator save changes back to the target via PATCH; uses the MODR-10
 * `expectedVersion` field on `CatalogObjectPatchInput` to detect stale
 * data and surfaces 409 → toast + auto-refetch.
 *
 * "Edytujesz współdzielony obiekt" warning is always visible — the
 * target object may be referenced from other source objects too.
 */
interface RelationTargetSummary {
  id: string;
  code: string;
  name: string | null;
  objectType: { id: string; code: string; kind: string };
  version: number;
}

interface FormSchemaResponse {
  effectiveGroups: Array<{
    id: string;
    code: string;
    label: Record<string, string>;
    display_mode?: 'tab' | 'stacked';
    attributes: Array<{
      id: string;
      code: string;
      type: string;
      label: Record<string, string>;
      is_system: boolean;
    }>;
  }>;
}

interface TargetObjectResponse {
  id: string;
  code: string;
  attributesIndexed: Record<string, unknown>;
}

export interface RelationInlineEditPanelProps {
  targetId: string;
  onClose: () => void;
  onSaved?: () => void;
}

export function RelationInlineEditPanel({
  targetId,
  onClose,
  onSaved,
}: RelationInlineEditPanelProps) {
  const { t, i18n } = useTranslation();
  const lang = i18n.language === 'pl' ? 'pl' : 'en';
  const queryClient = useQueryClient();

  const summaryQuery = useQuery<RelationTargetSummary[]>({
    queryKey: ['objects', 'summary', targetId],
    queryFn: () =>
      jsonFetch<RelationTargetSummary[]>('/api/objects/summaries', {
        method: 'POST',
        contentType: 'application/json',
        accept: 'application/json',
        body: { ids: [targetId] },
      }),
    staleTime: 0,
  });
  const summary = summaryQuery.data?.[0] ?? null;

  const objectQuery = useQuery<TargetObjectResponse>({
    queryKey: ['objects', targetId, 'detail'],
    queryFn: () =>
      jsonFetch<TargetObjectResponse>(`/api/objects/${targetId}`, {
        accept: 'application/json',
      }),
    staleTime: 0,
  });

  const formSchemaQuery = useQuery<FormSchemaResponse>({
    queryKey: ['objects', targetId, 'form-schema-for-edit'],
    queryFn: () =>
      jsonFetch<FormSchemaResponse>(`/api/objects/${targetId}/form-schema`, {
        accept: 'application/json',
      }),
    staleTime: 0,
  });

  const [dirty, setDirty] = useState<Record<string, unknown>>({});
  const [saving, setSaving] = useState(false);
  const [error, setError] = useState<string | null>(null);

  const objectTypeKind = summary?.objectType.kind ?? 'product';
  const patchPath = sugarPathForKind(objectTypeKind);

  const handleSave = async () => {
    if (summary === null) return;
    setError(null);
    setSaving(true);
    try {
      await jsonFetch(`/api/${patchPath}/${targetId}`, {
        method: 'PATCH',
        contentType: 'application/merge-patch+json',
        accept: 'application/ld+json',
        body: {
          attributes: dirty,
          expectedVersion: summary.version,
        },
      });
      // Invalidate caches so the row's display name + summaries refresh.
      await Promise.all([
        queryClient.invalidateQueries({ queryKey: ['objects', 'summary', targetId] }),
        queryClient.invalidateQueries({ queryKey: ['objects', targetId] }),
        queryClient.invalidateQueries({ queryKey: ['objects', 'summaries'] }),
      ]);
      setDirty({});
      toast.success(
        t('relations.inline_edit.saved', {
          defaultValue: 'Zapisano zmiany na obiekcie powiązanym.',
        }),
      );
      onSaved?.();
    } catch (e) {
      if (e instanceof HttpError && e.status === 409) {
        setError(
          t('relations.inline_edit.stale', {
            defaultValue:
              'Obiekt został w międzyczasie zmieniony przez kogoś innego. Odświeżam — sprawdź wartości i zapisz ponownie.',
          }),
        );
        setDirty({});
        await Promise.all([
          queryClient.invalidateQueries({ queryKey: ['objects', 'summary', targetId] }),
          queryClient.invalidateQueries({ queryKey: ['objects', targetId, 'detail'] }),
        ]);
      } else {
        setError(
          e instanceof HttpError
            ? `HTTP ${e.status}`
            : e instanceof Error
              ? e.message
              : t('relations.inline_edit.failed', {
                  defaultValue: 'Nie udało się zapisać zmian.',
                }),
        );
      }
    } finally {
      setSaving(false);
    }
  };

  const isLoading = summaryQuery.isLoading || objectQuery.isLoading || formSchemaQuery.isLoading;
  if (isLoading) {
    return (
      <div className="mt-2 rounded-xl border border-line bg-zinc-50 p-4 text-xs text-muted-foreground">
        {t('app.loading')}
      </div>
    );
  }
  if (summary === null || objectQuery.data === undefined || formSchemaQuery.data === undefined) {
    return (
      <div className="mt-2 rounded-xl border border-line bg-zinc-50 p-4 text-xs text-muted-foreground">
        {t('relations.inline_edit.target_missing', {
          defaultValue: 'Nie udało się załadować obiektu docelowego.',
        })}
      </div>
    );
  }

  const fieldValue = (code: string): string => {
    const v = code in dirty ? dirty[code] : objectQuery.data.attributesIndexed[code];
    if (v === null || v === undefined) return '';
    if (typeof v === 'string') return v;
    if (typeof v === 'number' || typeof v === 'boolean') return String(v);
    return JSON.stringify(v);
  };

  const setFieldValue = (code: string, next: string) => {
    setDirty((prev) => ({ ...prev, [code]: next }));
  };

  return (
    <div className="mt-2 space-y-3 rounded-xl border border-zinc-200 bg-white p-4">
      <div role="alert" className="rounded-md bg-amber-50 px-3 py-2 text-[12px] text-amber-700">
        {t('relations.inline_edit.shared_warning', {
          defaultValue:
            'Edytujesz współdzielony obiekt — zmiana dotyczy wszystkich powiązań, które go referują.',
        })}
      </div>

      <div className="space-y-4">
        {formSchemaQuery.data.effectiveGroups.map((group) => (
          <section key={group.id}>
            <h4 className="mb-2 text-[12.5px] font-semibold tracking-tight text-zinc-700">
              {group.label[lang] ?? group.code}
            </h4>
            <div className="space-y-2">
              {group.attributes.map((attr) => (
                <div key={attr.code} className="grid grid-cols-[180px_1fr] items-start gap-3">
                  <label
                    htmlFor={`edit-${targetId}-${attr.code}`}
                    className="pt-1.5 text-[12.5px] font-medium text-muted-foreground"
                  >
                    {attr.label[lang] ?? attr.code}
                  </label>
                  {attr.type === 'textarea' ||
                  attr.type === 'richtext' ||
                  attr.type === 'wysiwyg' ? (
                    <Textarea
                      id={`edit-${targetId}-${attr.code}`}
                      rows={3}
                      value={fieldValue(attr.code)}
                      disabled={attr.is_system}
                      onChange={(e) => setFieldValue(attr.code, e.target.value)}
                    />
                  ) : (
                    <Input
                      id={`edit-${targetId}-${attr.code}`}
                      value={fieldValue(attr.code)}
                      disabled={attr.is_system}
                      onChange={(e) => setFieldValue(attr.code, e.target.value)}
                    />
                  )}
                </div>
              ))}
            </div>
          </section>
        ))}
      </div>

      {error !== null ? <p className="text-xs text-destructive">{error}</p> : null}

      <div className="flex justify-end gap-2 border-t border-zinc-100 pt-3">
        <Button type="button" variant="ghost" size="sm" onClick={onClose} disabled={saving}>
          {t('app.cancel')}
        </Button>
        <Button
          type="button"
          size="sm"
          onClick={handleSave}
          disabled={saving || Object.keys(dirty).length === 0}
        >
          {t('relations.inline_edit.save', { defaultValue: 'Zapisz zmiany na obiekcie' })}
        </Button>
      </div>
    </div>
  );
}

function sugarPathForKind(kind: string): string {
  switch (kind) {
    case 'product':
      return 'products';
    case 'category':
      return 'categories';
    case 'asset':
      return 'assets';
    default:
      return 'objects';
  }
}
