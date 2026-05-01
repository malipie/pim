import { useOne } from '@refinedev/core';
import { ArrowLeft, Loader2, Wand2 } from 'lucide-react';
import { useCallback, useState } from 'react';
import { useTranslation } from 'react-i18next';
import { Link, useNavigate, useParams } from 'react-router';

import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { HttpError, jsonFetch } from '@/lib/http';

import { resolveLabel } from './list';

/**
 * UI-08.12 (#267) — `<MigrateTypePage>` (route `/modeling/attributes/:id/migrate-type`).
 *
 * Wires the UI-08.6 backend (`POST /api/attributes/{id}/migrate-type`)
 * into a 3-section flow:
 *
 *   1. Target type picker.
 *   2. Mapping plan editor — empty by default; "Suggest mappings" button
 *      fires a dry-run with empty mapping plan and renders the
 *      planner's view (rowCount / distinctValues / unmapped values
 *      with counts) so the operator can craft a plan informed by the
 *      real corpus.
 *   3. Apply controls — backupSnapshot, force, dryRun checkboxes +
 *      Cancel / Dry-run / Apply buttons.
 *
 * Modeled as a page (not a dialog/modal) for two reasons:
 *   - we don't have a Radix Dialog primitive yet, and Sheet's right-
 *     drawer affordance is too cramped for the mapping table;
 *   - back-button + deep-linkable URL is a better UX for a destructive
 *     workflow than a modal that vanishes on accidental click-outside.
 */

interface AttributeDetail {
  id: string;
  code: string;
  label?: Record<string, string> | string | null;
  type: string;
  system?: boolean;
}

interface MappingRow {
  from: string;
  to: string;
  count: number;
}

interface UnmappedRow {
  value: string;
  count: number;
}

interface MigrationAnalysis {
  compatibility: 'safe' | 'requires_force' | 'blocked';
  rowCount: number;
  distinctValues: number;
  mappings: MappingRow[];
  unmapped: UnmappedRow[];
  forceRequired: boolean;
  blockedReason: string | null;
}

interface MigrationResponse {
  dryRun: boolean;
  analysis: MigrationAnalysis;
}

const TARGET_TYPES = ['text', 'number', 'select', 'multiselect', 'date', 'boolean'] as const;

type TargetType = (typeof TARGET_TYPES)[number];

interface MappingDraft {
  from: string;
  to: string;
}

export function MigrateAttributeTypePage() {
  const { t, i18n } = useTranslation();
  const params = useParams<{ id: string }>();
  const navigate = useNavigate();
  const id = params.id ?? '';

  const { result, query } = useOne<AttributeDetail>({
    resource: 'attributes',
    id,
    queryOptions: { enabled: id.length > 0 },
  });

  const [targetType, setTargetType] = useState<TargetType>('select');
  const [mappings, setMappings] = useState<MappingDraft[]>([]);
  const [unmappedAction, setUnmappedAction] = useState<'null' | 'skip'>('null');
  const [backupSnapshot, setBackupSnapshot] = useState(true);
  const [force, setForce] = useState(false);
  const [analysis, setAnalysis] = useState<MigrationAnalysis | null>(null);
  const [submitting, setSubmitting] = useState<'idle' | 'dryrun' | 'apply'>('idle');
  const [error, setError] = useState<string | null>(null);

  const submit = useCallback(
    async (dryRun: boolean) => {
      setError(null);
      setSubmitting(dryRun ? 'dryrun' : 'apply');
      try {
        const body = {
          targetType,
          mappingPlan: mappings.filter((row) => row.from !== '' && row.to !== ''),
          unmappedAction,
          force,
          dryRun,
          backupSnapshot,
        };
        const response = await jsonFetch<MigrationResponse>(`/api/attributes/${id}/migrate-type`, {
          method: 'POST',
          contentType: 'application/json',
          accept: 'application/json',
          body,
        });
        setAnalysis(response.analysis);
        if (!dryRun) {
          // Apply succeeded → bounce back to the attribute detail page.
          navigate(`/modeling/attributes/${id}`, { replace: true });
        }
      } catch (err) {
        if (err instanceof HttpError) {
          const detail =
            err.body && typeof err.body === 'object' && 'detail' in err.body
              ? String((err.body as Record<string, unknown>).detail)
              : null;
          setError(detail ?? `HTTP ${err.status}`);
        } else {
          setError(t('modeling.attributes.migration.error_generic'));
        }
      } finally {
        setSubmitting('idle');
      }
    },
    [id, targetType, mappings, unmappedAction, force, backupSnapshot, navigate, t],
  );

  /**
   * Dry-run with the *current* mapping plan but only to surface the
   * unmapped list. We populate the editor's draft mappings from the
   * unmapped values so the user can fill targets row by row.
   */
  const suggestMappings = useCallback(async () => {
    setError(null);
    setSubmitting('dryrun');
    try {
      const response = await jsonFetch<MigrationResponse>(`/api/attributes/${id}/migrate-type`, {
        method: 'POST',
        contentType: 'application/json',
        accept: 'application/json',
        body: { targetType, mappingPlan: [], unmappedAction: 'null', dryRun: true },
      });
      setAnalysis(response.analysis);
      const drafts: MappingDraft[] = response.analysis.unmapped.map((row) => ({
        from: row.value,
        to: '',
      }));
      setMappings(drafts);
    } catch (err) {
      if (err instanceof HttpError) {
        const detail =
          err.body && typeof err.body === 'object' && 'detail' in err.body
            ? String((err.body as Record<string, unknown>).detail)
            : null;
        setError(detail ?? `HTTP ${err.status}`);
      } else {
        setError(t('modeling.attributes.migration.error_generic'));
      }
    } finally {
      setSubmitting('idle');
    }
  }, [id, targetType, t]);

  if (query.isLoading || !result) {
    return <p className="text-sm text-muted-foreground">{t('app.loading')}</p>;
  }

  const attribute = result;
  const label = resolveLabel(attribute.label, i18n.language);

  if (attribute.system === true) {
    return (
      <div className="space-y-3">
        <Button asChild variant="ghost" size="sm" className="-ml-3">
          <Link to={`/modeling/attributes/${attribute.id}`}>
            <ArrowLeft className="size-4" />
            {t('attributes.back')}
          </Link>
        </Button>
        <h1 className="text-2xl font-semibold tracking-tight">
          {t('modeling.attributes.migration.title', { name: label })}
        </h1>
        <p className="rounded-md border border-amber-300 bg-amber-50 px-3 py-2 text-sm text-amber-900">
          {t('modeling.attributes.system_immutable_note')}
        </p>
      </div>
    );
  }

  return (
    <div className="space-y-6">
      <div className="space-y-1">
        <Button asChild variant="ghost" size="sm" className="-ml-3">
          <Link to={`/modeling/attributes/${attribute.id}`}>
            <ArrowLeft className="size-4" />
            {t('attributes.back')}
          </Link>
        </Button>
        <h1 className="text-2xl font-semibold tracking-tight">
          {t('modeling.attributes.migration.title', { name: label })}
        </h1>
        <p className="text-sm text-muted-foreground">
          {t('modeling.attributes.migration.description', {
            from: attribute.type,
          })}
        </p>
      </div>

      <Card>
        <CardContent className="space-y-4 pt-6">
          <div className="grid gap-2">
            <Label htmlFor="target-type">{t('modeling.attributes.migration.target_type')}</Label>
            <select
              id="target-type"
              className="h-9 rounded-md border border-input bg-background px-3 text-sm shadow-sm"
              value={targetType}
              onChange={(e) => setTargetType(e.target.value as TargetType)}
            >
              {TARGET_TYPES.filter((type) => type !== attribute.type).map((type) => (
                <option key={type} value={type}>
                  {type}
                </option>
              ))}
            </select>
          </div>

          <div className="flex flex-wrap items-center gap-3">
            <Button type="button" variant="outline" size="sm" onClick={suggestMappings}>
              <Wand2 className="size-4" />
              {t('modeling.attributes.migration.suggest')}
            </Button>
            {analysis ? (
              <span className="text-xs text-muted-foreground">
                {t('modeling.attributes.migration.analysis_summary', {
                  rows: analysis.rowCount,
                  distinct: analysis.distinctValues,
                  unmapped: analysis.unmapped.length,
                })}
              </span>
            ) : null}
          </div>

          {analysis && analysis.compatibility === 'blocked' ? (
            <p className="rounded-md border border-destructive/50 bg-destructive/5 px-3 py-2 text-sm text-destructive">
              {analysis.blockedReason ?? t('modeling.attributes.migration.blocked')}
            </p>
          ) : null}

          {analysis?.forceRequired ? (
            <p className="rounded-md border border-amber-300 bg-amber-50 px-3 py-2 text-sm text-amber-900">
              {t('modeling.attributes.migration.force_required')}
            </p>
          ) : null}
        </CardContent>
      </Card>

      <Card>
        <CardContent className="space-y-3 pt-6">
          <h2 className="text-sm font-semibold">
            {t('modeling.attributes.migration.mapping_plan')}
          </h2>
          {mappings.length === 0 ? (
            <p className="text-sm text-muted-foreground">
              {t('modeling.attributes.migration.mapping_empty')}
            </p>
          ) : (
            <div className="grid gap-2">
              {mappings.map((row, idx) => (
                // biome-ignore lint/suspicious/noArrayIndexKey: mapping rows are keyed by source value (idx tiebreak only); rows never reorder during edit.
                <div key={`${row.from}-${idx}`} className="flex items-center gap-2 text-sm">
                  <span className="flex-1 rounded bg-muted px-2 py-1 font-mono text-xs">
                    {row.from}
                  </span>
                  <span className="text-muted-foreground">→</span>
                  <Input
                    aria-label={t('modeling.attributes.migration.mapping_target_label', {
                      from: row.from,
                    })}
                    className="flex-1"
                    value={row.to}
                    onChange={(e) =>
                      setMappings((rows) =>
                        rows.map((r, i) => (i === idx ? { ...r, to: e.target.value } : r)),
                      )
                    }
                    placeholder={t('modeling.attributes.migration.mapping_target_placeholder')}
                  />
                </div>
              ))}
            </div>
          )}
        </CardContent>
      </Card>

      <Card>
        <CardContent className="space-y-3 pt-6">
          <h2 className="text-sm font-semibold">{t('modeling.attributes.migration.controls')}</h2>

          <div className="grid gap-2">
            <Label htmlFor="unmapped-action">
              {t('modeling.attributes.migration.unmapped_action')}
            </Label>
            <select
              id="unmapped-action"
              className="h-9 rounded-md border border-input bg-background px-3 text-sm shadow-sm"
              value={unmappedAction}
              onChange={(e) => setUnmappedAction(e.target.value as 'null' | 'skip')}
            >
              <option value="null">{t('modeling.attributes.migration.unmapped_null')}</option>
              <option value="skip">{t('modeling.attributes.migration.unmapped_skip')}</option>
            </select>
          </div>

          <label className="flex items-center gap-2 text-sm">
            <input
              type="checkbox"
              checked={backupSnapshot}
              onChange={(e) => setBackupSnapshot(e.target.checked)}
            />
            {t('modeling.attributes.migration.backup_snapshot')}
          </label>

          <label className="flex items-center gap-2 text-sm">
            <input type="checkbox" checked={force} onChange={(e) => setForce(e.target.checked)} />
            {t('modeling.attributes.migration.force')}
          </label>
        </CardContent>
      </Card>

      {error !== null ? (
        <p className="rounded-md border border-destructive/50 bg-destructive/5 px-3 py-2 text-sm text-destructive">
          {error}
        </p>
      ) : null}

      <div className="flex flex-wrap items-center gap-2">
        <Button asChild variant="ghost">
          <Link to={`/modeling/attributes/${attribute.id}`}>
            {t('modeling.attributes.migration.cancel')}
          </Link>
        </Button>
        <Button
          type="button"
          variant="outline"
          disabled={submitting !== 'idle'}
          onClick={() => submit(true)}
        >
          {submitting === 'dryrun' ? <Loader2 className="size-4 animate-spin" /> : null}
          {t('modeling.attributes.migration.dry_run')}
        </Button>
        <Button type="button" disabled={submitting !== 'idle'} onClick={() => submit(false)}>
          {submitting === 'apply' ? <Loader2 className="size-4 animate-spin" /> : null}
          {t('modeling.attributes.migration.apply')}
        </Button>
      </div>
    </div>
  );
}
