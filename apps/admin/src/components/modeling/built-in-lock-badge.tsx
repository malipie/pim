import { Lock } from 'lucide-react';
import { useTranslation } from 'react-i18next';

/**
 * UI-08.10 (#265) — 🔒 lock badge for system-managed rows.
 *
 * Used by Object Types / Attributes / Attribute Groups list + detail
 * views to mark built-in entities (`is_built_in=true` / `is_system=true`
 * / `is_system_group=true`) — code is immutable + delete is blocked.
 *
 * Variant `tone` controls the visual treatment: `solid` (filled badge)
 * for table cells where the row needs strong emphasis, `quiet` (icon
 * + tooltip text) for inline use next to a row label.
 */
interface BuiltInLockBadgeProps {
  tone?: 'solid' | 'quiet';
  /** Override the default tooltip text (defaults to `i18n: modeling.built_in_lock.tooltip`). */
  tooltip?: string;
}

export function BuiltInLockBadge({ tone = 'solid', tooltip }: BuiltInLockBadgeProps) {
  const { t } = useTranslation();
  const text = tooltip ?? t('modeling.built_in_lock.tooltip');

  if (tone === 'quiet') {
    return (
      <span className="inline-flex items-center gap-1 text-muted-foreground" title={text}>
        <Lock className="size-3.5" aria-hidden />
        <span className="sr-only">{text}</span>
      </span>
    );
  }

  return (
    <span
      className="inline-flex items-center gap-1 rounded bg-secondary px-2 py-0.5 text-xs font-medium text-secondary-foreground"
      title={text}
    >
      <Lock className="size-3" aria-hidden />
      {t('modeling.built_in_lock.label')}
    </span>
  );
}
