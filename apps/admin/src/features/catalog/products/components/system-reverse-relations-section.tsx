import { ArrowDownLeft, Lock } from 'lucide-react';
import { useTranslation } from 'react-i18next';
import { Link } from 'react-router';

import { cn } from '@/lib/utils';

/**
 * MODRC-03 (#1082) — system section listing objects that point at the
 * current one through any relation attribute. After Option Y the
 * legacy "Powiązania" AttributeGroup is gone, so reverse links no
 * longer have a user-managed home — this is the single auto-generated
 * landing pad. Visually marked as `system` + zinc-tinted styling so the
 * operator can tell it apart from user-defined groups. Read-only:
 * edits live on the source side.
 *
 * Data shape mirrors the existing `GET /api/objects/{id}/relations/reverse`
 * response (MOD-07).
 */
export interface ReverseGroupRow {
  sourceObjectType: { id: string; code: string; kind: string };
  attribute: { id: string; code: string; label: Record<string, string> | null };
  sources: Array<{ id: string; code: string; relationId: string; position: number }>;
}

export interface SystemReverseRelationsSectionProps {
  groups: ReverseGroupRow[];
  className?: string;
}

export function SystemReverseRelationsSection({
  groups,
  className,
}: SystemReverseRelationsSectionProps) {
  const { t } = useTranslation();

  if (groups.length === 0) return null;

  return (
    <section
      aria-label={t('relations.reverse_section_aria', {
        defaultValue: 'Powiązania zwrotne (sekcja systemowa, read-only)',
      })}
      className={cn('rounded-2xl border border-zinc-200 bg-zinc-50/60 p-5 soft-shadow', className)}
    >
      <header className="flex items-center gap-2">
        <ArrowDownLeft className="size-4 text-zinc-500" aria-hidden />
        <h3 className="text-sm font-semibold text-zinc-800">
          {t('relations.reverse_title', { defaultValue: 'Powiązania zwrotne' })}
        </h3>
        <span
          className="inline-flex items-center gap-1 rounded bg-zinc-200/70 px-1.5 py-0.5 text-[10px] font-semibold uppercase tracking-wider text-zinc-600"
          title={t('relations.reverse_system_badge_tooltip', {
            defaultValue: 'Sekcja generowana automatycznie — edycja po stronie obiektu źródłowego.',
          })}
        >
          <Lock className="size-2.5" aria-hidden />
          {t('relations.reverse_system_badge_label', { defaultValue: 'system' })}
        </span>
      </header>
      <p className="mt-1 text-xs text-zinc-500">
        {t('relations.reverse_desc', {
          defaultValue: 'Obiekty, które wskazują na ten produkt przez swoje atrybuty relacji.',
        })}
      </p>
      <div className="mt-4 space-y-3">
        {groups.map((group) => (
          <div
            key={`${group.sourceObjectType.id}:${group.attribute.id}`}
            className="rounded-xl border border-zinc-200 bg-white p-3"
          >
            <div className="text-xs font-medium">
              <span className="text-foreground">{group.attribute.code}</span>
              <span className="ml-2 text-muted-foreground">
                ({group.sourceObjectType.code} / {group.sourceObjectType.kind})
              </span>
            </div>
            <ul className="mt-2 flex flex-wrap gap-1.5">
              {group.sources.map((src) => (
                <li key={src.relationId}>
                  <Link
                    to={resolveSourceHref(group.sourceObjectType.kind, src.id)}
                    className="inline-block rounded-md bg-zinc-100 px-2 py-1 font-mono text-xs text-zinc-800 transition hover:bg-zinc-200 hover:text-zinc-900"
                    title={t('relations.reverse_open_source_tooltip', {
                      defaultValue: 'Otwórz obiekt źródłowy',
                    })}
                  >
                    {src.code}
                  </Link>
                </li>
              ))}
            </ul>
          </div>
        ))}
      </div>
    </section>
  );
}

function resolveSourceHref(kind: string, id: string): string {
  if (kind === 'product') return `/products/${id}`;
  return `/objects/${id}`;
}
