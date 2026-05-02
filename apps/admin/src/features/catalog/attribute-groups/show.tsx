import { useOne } from '@refinedev/core';
import { ArrowDown, ArrowLeft, ArrowUp, Save, X } from 'lucide-react';
import { useCallback, useEffect, useState } from 'react';
import { useTranslation } from 'react-i18next';
import { Link, useParams } from 'react-router';

import { AuditLogIndicator } from '@/components/modeling/audit-log-indicator';
import { BuiltInLockBadge } from '@/components/modeling/built-in-lock-badge';
import { WhereUsedList } from '@/components/modeling/where-used-list';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
  Table,
  TableBody,
  TableCell,
  TableHead,
  TableHeader,
  TableRow,
} from '@/components/ui/table';
import { resolveLabel } from '@/features/catalog/attributes/list';
import { jsonFetch } from '@/lib/http';

interface AttributeGroupDetail {
  id: string;
  code: string;
  label?: Record<string, string> | string | null;
  description?: Record<string, string> | string | null;
  icon?: string | null;
  color?: string | null;
  systemGroup?: boolean;
  autoAttached?: boolean;
  position?: number;
}

interface MemberRow {
  attribute: {
    id: string;
    code: string;
    type: string;
    label?: Record<string, string> | string | null;
    is_system: boolean;
  };
  position: number;
  is_required_in_group: boolean;
  visible_when: { field: string; operator: string; value: unknown } | null;
}

interface MembersResponse {
  attributeGroupId: string;
  members: MemberRow[];
}

export function AttributeGroupShowPage() {
  const { t, i18n } = useTranslation();
  const params = useParams<{ id: string }>();
  const id = params.id ?? '';

  const { result, query } = useOne<AttributeGroupDetail>({
    resource: 'attribute_groups',
    id,
    queryOptions: { enabled: id.length > 0 },
  });

  const [members, setMembers] = useState<MemberRow[]>([]);
  const [editingId, setEditingId] = useState<string | null>(null);
  const [draftRule, setDraftRule] = useState<{ field: string; value: string }>({
    field: '',
    value: '',
  });

  const reload = useCallback(async () => {
    if (id === '') return;
    try {
      const data = await jsonFetch<MembersResponse>(`/api/attribute_groups/${id}/attributes`, {
        accept: 'application/json',
      });
      setMembers(data.members);
    } catch {
      setMembers([]);
    }
  }, [id]);

  useEffect(() => {
    reload();
  }, [reload]);

  const patchJunction = useCallback(
    async (attributeId: string, body: Record<string, unknown>) => {
      await jsonFetch(`/api/attribute_groups/${id}/attributes/${attributeId}`, {
        method: 'PATCH',
        contentType: 'application/json',
        accept: 'application/json',
        body,
      });
      await reload();
    },
    [id, reload],
  );

  const reorder = useCallback(
    async (idx: number, delta: -1 | 1) => {
      const target = idx + delta;
      if (target < 0 || target >= members.length) return;
      const a = members[idx];
      const b = members[target];
      if (!a || !b) return;
      // Swap positions in two PATCH calls. Sequence matters because
      // UI-08.4 cache invalidator nukes per-ObjectType tag entries on
      // each junction mutation; we accept the brief window where both
      // rows could share the same position (no UNIQUE constraint).
      await patchJunction(a.attribute.id, { position: b.position });
      await patchJunction(b.attribute.id, { position: a.position });
    },
    [members, patchJunction],
  );

  if (query.isLoading || !result) {
    return <p className="text-sm text-muted-foreground">{t('app.loading')}</p>;
  }

  const group = result;
  const label = resolveLabel(group.label, i18n.language);
  const description = resolveLabel(group.description, i18n.language);

  return (
    <div className="space-y-6">
      <div className="space-y-1">
        <div className="flex items-center justify-between">
          <Button asChild variant="ghost" size="sm" className="-ml-3">
            <Link to="/modeling/attribute-groups">
              <ArrowLeft className="size-4" />
              {t('attribute_groups.back')}
            </Link>
          </Button>
          <AuditLogIndicator />
        </div>
        <div className="flex flex-wrap items-center gap-2">
          {group.color ? (
            <span
              aria-hidden
              className="inline-block size-4 rounded-full border"
              style={{ backgroundColor: group.color }}
            />
          ) : null}
          <h1 className="text-2xl font-semibold tracking-tight">{label}</h1>
          {group.systemGroup ? <BuiltInLockBadge /> : null}
          {group.autoAttached ? (
            <span className="rounded bg-blue-100 px-2 py-0.5 text-xs text-blue-900">
              {t('modeling.attribute_groups.auto_attached')}
            </span>
          ) : null}
        </div>
        <p className="font-mono text-xs text-muted-foreground">{group.code}</p>
        {description !== '—' ? (
          <p className="text-sm text-muted-foreground">{description}</p>
        ) : null}
      </div>

      <div className="grid gap-6 lg:grid-cols-[1fr_320px]">
        <Card>
          <CardContent className="space-y-3 pt-6">
            <h2 className="text-sm font-semibold">
              {t('modeling.attribute_groups.members_title')}
            </h2>
            <Table>
              <TableHeader>
                <TableRow>
                  <TableHead className="w-[80px] text-center">
                    {t('modeling.attribute_groups.fields.position')}
                  </TableHead>
                  <TableHead>{t('attributes.fields.code')}</TableHead>
                  <TableHead className="w-[120px]">{t('attributes.fields.type')}</TableHead>
                  <TableHead className="w-[100px]">{t('attributes.fields.required')}</TableHead>
                  <TableHead>{t('modeling.visible_when.column')}</TableHead>
                  <TableHead className="w-[100px] text-right">
                    <span className="sr-only">{t('attributes.fields.actions')}</span>
                  </TableHead>
                </TableRow>
              </TableHeader>
              <TableBody>
                {members.length === 0 ? (
                  <TableRow>
                    <TableCell colSpan={6} className="py-6 text-center text-muted-foreground">
                      {t('modeling.attribute_groups.members_empty')}
                    </TableCell>
                  </TableRow>
                ) : (
                  members.map((row, idx) => (
                    <TableRow key={row.attribute.id}>
                      <TableCell className="text-center text-xs tabular-nums text-muted-foreground">
                        {row.position}
                      </TableCell>
                      <TableCell className="font-mono text-xs">
                        <span className="inline-flex items-center gap-1">
                          {row.attribute.is_system ? <BuiltInLockBadge tone="quiet" /> : null}
                          {row.attribute.code}
                        </span>
                      </TableCell>
                      <TableCell>
                        <span className="rounded bg-muted px-2 py-0.5 text-xs uppercase tracking-wide">
                          {row.attribute.type}
                        </span>
                      </TableCell>
                      <TableCell>{row.is_required_in_group ? '✓' : '—'}</TableCell>
                      <TableCell className="text-xs">
                        {editingId === row.attribute.id ? (
                          <VisibleWhenInlineEditor
                            initial={row.visible_when}
                            availableFields={members
                              .filter((m) => m.attribute.id !== row.attribute.id)
                              .map((m) => m.attribute.code)
                              .concat(['created_at', 'updated_at', 'created_by', 'updated_by'])}
                            draftField={draftRule.field}
                            draftValue={draftRule.value}
                            onChange={(field, value) => setDraftRule({ field, value })}
                            onCancel={() => {
                              setEditingId(null);
                              setDraftRule({ field: '', value: '' });
                            }}
                            onSave={async () => {
                              await patchJunction(row.attribute.id, {
                                visibleWhen:
                                  draftRule.field === ''
                                    ? null
                                    : {
                                        field: draftRule.field,
                                        operator: 'equals',
                                        value: draftRule.value,
                                      },
                              });
                              setEditingId(null);
                              setDraftRule({ field: '', value: '' });
                            }}
                          />
                        ) : row.visible_when ? (
                          <button
                            type="button"
                            className="text-left font-mono text-xs underline-offset-2 hover:underline"
                            onClick={() => {
                              setEditingId(row.attribute.id);
                              setDraftRule({
                                field: row.visible_when?.field ?? '',
                                value: String(row.visible_when?.value ?? ''),
                              });
                            }}
                          >
                            {row.visible_when.field} = {String(row.visible_when.value)}
                          </button>
                        ) : (
                          <button
                            type="button"
                            className="text-xs text-muted-foreground hover:text-foreground"
                            onClick={() => {
                              setEditingId(row.attribute.id);
                              setDraftRule({ field: '', value: '' });
                            }}
                          >
                            {t('modeling.visible_when.add')}
                          </button>
                        )}
                      </TableCell>
                      <TableCell className="text-right">
                        <Button
                          type="button"
                          variant="ghost"
                          size="sm"
                          aria-label={t('modeling.attribute_groups.move_up')}
                          disabled={idx === 0}
                          onClick={() => reorder(idx, -1)}
                        >
                          <ArrowUp className="size-4" />
                        </Button>
                        <Button
                          type="button"
                          variant="ghost"
                          size="sm"
                          aria-label={t('modeling.attribute_groups.move_down')}
                          disabled={idx === members.length - 1}
                          onClick={() => reorder(idx, 1)}
                        >
                          <ArrowDown className="size-4" />
                        </Button>
                      </TableCell>
                    </TableRow>
                  ))
                )}
              </TableBody>
            </Table>
          </CardContent>
        </Card>
        <WhereUsedList resource="attribute_groups" id={group.id} />
      </div>

      <p className="text-xs text-muted-foreground">
        {t('modeling.attribute_groups.attach_deferred_note')}
      </p>
    </div>
  );
}

function VisibleWhenInlineEditor({
  initial,
  availableFields,
  draftField,
  draftValue,
  onChange,
  onCancel,
  onSave,
}: {
  initial: { field: string; operator: string; value: unknown } | null;
  availableFields: string[];
  draftField: string;
  draftValue: string;
  onChange: (field: string, value: string) => void;
  onCancel: () => void;
  onSave: () => Promise<void>;
}) {
  const { t } = useTranslation();
  const fieldOptions = Array.from(new Set(availableFields)).sort();
  const isClearMode = initial !== null && draftField === '';

  return (
    <div className="flex flex-wrap items-center gap-2">
      <Label className="sr-only" htmlFor="vw-field">
        {t('modeling.visible_when.field')}
      </Label>
      <select
        id="vw-field"
        className="h-8 rounded-md border border-input bg-background px-2 text-xs"
        value={draftField}
        onChange={(e) => onChange(e.target.value, draftValue)}
      >
        <option value="">{t('modeling.visible_when.none')}</option>
        {fieldOptions.map((field) => (
          <option key={field} value={field}>
            {field}
          </option>
        ))}
      </select>
      <span className="text-xs text-muted-foreground">=</span>
      <Input
        aria-label={t('modeling.visible_when.value')}
        className="h-8 w-[140px] text-xs"
        value={draftValue}
        onChange={(e) => onChange(draftField, e.target.value)}
        placeholder={t('modeling.visible_when.value_placeholder')}
      />
      <Button type="button" variant="ghost" size="sm" onClick={onCancel}>
        <X className="size-3.5" />
        <span className="sr-only">{t('app.cancel')}</span>
      </Button>
      <Button type="button" variant="secondary" size="sm" onClick={onSave}>
        <Save className="size-3.5" />
        {isClearMode ? t('modeling.visible_when.clear') : t('app.save')}
      </Button>
    </div>
  );
}
