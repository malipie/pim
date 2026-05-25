/*
 * UP-08 (#1029) — universal full-page create wizard for any ObjectType.
 *
 * Operator decision: "Dodawanie - ma być pełen widok jak przy produkcie
 *   - nie żaden modal." This is a dedicated route (not a Dialog) that
 *   gives custom kinds the same full-page create experience that
 *   `/products/new` offers for products.
 *
 * Scope decisions:
 *   - POSTs to `/api/objects` (poly-kind endpoint; existing since
 *     ULV-02). On success, navigates to `/objects/:slug/:id` — the
 *     UP-07 detail page picks up from there for full inline editing.
 *   - Form is minimal in MVP: code + initial attribute values for any
 *     attribute declared by the ObjectType (via `effective-attribute-groups
 *     /preview` against the OT with an empty categoryIds payload). Full
 *     parity with /products/new (category-driven attribute overlay
 *     during create, validation rules per field) lands in follow-up
 *     once UP-07 categories editing is universal too.
 */
import { useQuery } from '@tanstack/react-query';
import { ArrowLeft, Save } from 'lucide-react';
import { useState } from 'react';
import { useTranslation } from 'react-i18next';
import { Link, useNavigate } from 'react-router';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { toast } from '@/components/ui/toast';
import { AttrGroupCard } from '@/features/catalog/products/components/attr-group-card';
import { AttrRow } from '@/features/catalog/products/components/attr-row';
import type { GroupMeta, ProductLocale } from '@/features/catalog/products/components/types';
import { jsonFetch } from '@/lib/http';

export interface UniversalCreatePageProps {
  objectTypeId: string;
  objectTypeCode: string;
  objectTypeLabel: string;
  /** Where the cancel button navigates (typically `/objects/:slug`). */
  backHref: string;
  /** Builder for the detail route after successful create. */
  detailPathFor: (id: string) => string;
}

const GROUP_ICONS: Record<string, string> = {
  identification: '🔑',
  identyfikacja: '🔑',
  marketing: '✨',
  technical: '⚙',
  technicals: '⚙',
  specyfikacje: '⚙',
  logistics: '📦',
  logistyka: '📦',
  pricing: '💰',
  cennik: '💰',
};

export function UniversalCreatePage({
  objectTypeId,
  objectTypeCode,
  objectTypeLabel,
  backHref,
  detailPathFor,
}: UniversalCreatePageProps) {
  const { t, i18n } = useTranslation();
  const navigate = useNavigate();
  const lang = i18n.language === 'pl' ? 'pl' : 'en';
  const [code, setCode] = useState('');
  const [dirtyFields, setDirtyFields] = useState<Record<string, unknown>>({});
  const [expandedGroups, setExpandedGroups] = useState<Set<string>>(new Set());
  const [isSaving, setIsSaving] = useState(false);
  const [locale] = useState<ProductLocale>('pl');

  // UP-08 — fetch the ObjectType's effective groups without an object
  // context (no categories selected). Uses the `/preview` POST endpoint
  // with an empty categoryIds payload so the response shape mirrors
  // the detail page; category-driven overlay during create is a
  // follow-up.
  const groupsQuery = useQuery<{ groups: GroupMeta[] }>({
    queryKey: ['object-type', objectTypeId, 'effective-attribute-groups', 'preview', 'empty'],
    enabled: objectTypeId !== '',
    staleTime: 5 * 60 * 1000,
    queryFn: () =>
      jsonFetch<{ groups: GroupMeta[] }>(
        `/api/object_types/${objectTypeId}/effective-attribute-groups/preview`,
        {
          method: 'POST',
          contentType: 'application/json',
          accept: 'application/json',
          body: { categoryIds: [] },
        },
      ),
  });
  const groups = groupsQuery.data?.groups ?? [];
  const stackedGroups = groups.filter((g) => (g.display_mode ?? 'tab') === 'stacked');

  const toggleGroup = (groupId: string): void => {
    setExpandedGroups((prev) => {
      const next = new Set(prev);
      if (next.has(groupId)) next.delete(groupId);
      else next.add(groupId);
      return next;
    });
  };

  const setFieldValue = (codeKey: string, value: unknown): void => {
    setDirtyFields((prev) => ({ ...prev, [codeKey]: value }));
  };

  const handleCreate = async (): Promise<void> => {
    if (isSaving) return;
    const trimmedCode = code.trim();
    if (trimmedCode === '') {
      toast.error(
        t('object_create.validation.code_required', { defaultValue: 'Kod jest wymagany' }),
      );
      return;
    }
    setIsSaving(true);
    try {
      const attributes = stripAttributes(dirtyFields);
      const body: Record<string, unknown> = {
        objectTypeId,
        code: trimmedCode,
      };
      if (Object.keys(attributes).length > 0) body.attributes = attributes;
      const created = await jsonFetch<{ id: string }>('/api/objects', {
        method: 'POST',
        contentType: 'application/ld+json',
        body,
      });
      toast.success(
        t('object_create.success', {
          defaultValue: 'Utworzono {{code}}',
          code: trimmedCode,
        }),
      );
      navigate(detailPathFor(created.id));
    } catch {
      toast.error(t('object_create.failed', { defaultValue: 'Nie udało się utworzyć obiektu' }));
    } finally {
      setIsSaving(false);
    }
  };

  return (
    <div className="-mx-6 -mt-6 min-h-[calc(100vh-3rem)] bg-zinc-50">
      <header className="glass-strong sticky top-0 z-20 border-b border-zinc-100">
        <div className="px-7 pb-3 pt-5">
          <div className="flex items-center gap-3">
            <Button
              asChild
              variant="ghost"
              size="icon"
              className="soft-shadow size-9 rounded-xl bg-white"
            >
              <Link to={backHref} aria-label={t('object_create.back', { defaultValue: 'Powrót' })}>
                <ArrowLeft className="size-4" />
              </Link>
            </Button>
            <div className="text-[12px] text-zinc-500">
              <span>{objectTypeLabel}</span>
              <span className="mx-1.5 text-zinc-300">/</span>
              <span className="font-medium text-zinc-900">
                {t('object_create.new', { defaultValue: 'Nowy' })}
              </span>
            </div>
            <div className="ml-auto flex items-center gap-2">
              <Button
                type="button"
                variant="ghost"
                size="sm"
                onClick={() => navigate(backHref)}
                disabled={isSaving}
                className="h-9 rounded-xl px-3 text-[12.5px] text-zinc-600"
              >
                {t('object_create.actions.cancel', { defaultValue: 'Anuluj' })}
              </Button>
              <Button
                type="button"
                onClick={() => void handleCreate()}
                disabled={isSaving || code.trim() === ''}
                className="h-9 rounded-xl bg-zinc-900 px-4 text-[12.5px] font-medium text-white hover:bg-zinc-800"
              >
                <Save className="size-4" />
                {t('object_create.actions.create', { defaultValue: 'Utwórz' })}
              </Button>
            </div>
          </div>

          <div className="mt-4 flex items-start gap-5">
            <div
              className="soft-shadow grid size-[72px] shrink-0 place-items-center rounded-2xl bg-white text-[34px]"
              aria-hidden
            >
              ▣
            </div>
            <div className="min-w-0 flex-1 space-y-2">
              <Input
                autoFocus
                placeholder={t('object_create.placeholder.code', {
                  defaultValue: 'Kod (np. CAR-001)',
                })}
                value={code}
                onChange={(event) => setCode(event.target.value)}
                className="font-display h-10 rounded-lg border-zinc-200 bg-white text-[20px] font-semibold tracking-tight"
              />
              <p className="text-[11.5px] text-muted-foreground">
                {t('object_create.code_hint', {
                  defaultValue:
                    'Kod musi być unikalny w obrębie tego typu obiektu (np. SKU dla produktu).',
                })}
              </p>
            </div>
          </div>
        </div>
      </header>

      <div className="grid grid-cols-1 gap-5 px-7 py-6 lg:grid-cols-[minmax(0,1fr)_320px]">
        <div className="min-w-0 space-y-3">
          {groupsQuery.isLoading ? (
            <p className="text-sm text-muted-foreground">{t('app.loading')}</p>
          ) : stackedGroups.length === 0 ? (
            <div className="border-line bg-surface rounded-2xl border border-dashed p-6 text-center">
              <p className="text-ink text-[13px] font-medium">
                {t('object_create.no_attributes', {
                  defaultValue: 'Ten typ obiektu nie ma jeszcze atrybutów.',
                })}
              </p>
              <p className="mt-1 text-[11.5px] text-muted-foreground">
                {t('object_create.no_attributes_hint', {
                  defaultValue:
                    'Dodaj atrybuty w modelowaniu (Modelowanie → Typy obiektów → {{label}}).',
                  label: objectTypeLabel,
                })}
              </p>
            </div>
          ) : (
            stackedGroups.map((group) => (
              <AttrGroupCard
                key={group.id}
                id={group.id}
                title={group.label[lang] ?? group.code}
                icon={GROUP_ICONS[group.code]}
                filledCount={countFilled(group, dirtyFields)}
                totalCount={group.attributes.length}
                expanded={expandedGroups.has(group.id) || expandedGroups.size === 0}
                onToggle={() => toggleGroup(group.id)}
              >
                {group.attributes.map((attr) => (
                  <AttrRow
                    key={attr.id}
                    attribute={attr}
                    value={dirtyFields[attr.code]}
                    provenance="manual"
                    locale={locale}
                    isEditing={true}
                    isLocked={attr.is_system}
                    onChange={(next) => setFieldValue(attr.code, next)}
                  />
                ))}
              </AttrGroupCard>
            ))
          )}
        </div>

        <aside
          className="space-y-3"
          aria-label={t('object_create.sidebar.aria', { defaultValue: 'Panel boczny' })}
        >
          <div className="rounded-2xl border border-zinc-200 bg-white p-4">
            <h3 className="text-[13px] font-semibold text-zinc-900">
              {t('object_create.sidebar.type', { defaultValue: 'Typ obiektu' })}
            </h3>
            <p className="mt-1 text-[12px] text-zinc-600">{objectTypeLabel}</p>
            <p className="mt-3 text-[11px] text-zinc-400">
              <span className="font-mono">{objectTypeCode}</span>
            </p>
          </div>
        </aside>
      </div>
    </div>
  );
}

function stripAttributes(dirty: Record<string, unknown>): Record<string, unknown> {
  const out: Record<string, unknown> = {};
  for (const [k, v] of Object.entries(dirty)) {
    if (k === 'sku' || k === 'code') continue;
    out[k] = v;
  }
  return out;
}

function countFilled(group: GroupMeta, dirty: Record<string, unknown>): number {
  let filled = 0;
  for (const attr of group.attributes) {
    const value = dirty[attr.code];
    if (value === undefined || value === null) continue;
    if (typeof value === 'string' && value.trim() === '') continue;
    filled += 1;
  }
  return filled;
}
