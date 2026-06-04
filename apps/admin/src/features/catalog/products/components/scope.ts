import type { CompletenessMap } from './types';

/**
 * #1150 / #1155 — build the `?locale=&channel=` scope query string for
 * locale/channel-aware object reads + writes. A null channel = "all
 * channels" (global), so it is omitted.
 */
export function scopeQuery(locale: string, channel: string | null): string {
  const params = new URLSearchParams({ locale });
  if (channel !== null) {
    params.set('channel', channel);
  }
  return `?${params.toString()}`;
}

/**
 * #1152 — resolve the completeness % for the active scope: a per-channel
 * value wins (the operator is looking at "readiness for this channel"),
 * then a per-locale value, else the global baseline. `scope` is the label
 * the value came from (channel/locale code) or null when it fell back to
 * global — the card only shows the chip when a scoped value was used.
 */
export function scopedCompleteness(
  completeness: CompletenessMap | null | undefined,
  locale: string,
  channel: string | null,
  fallbackPct: number,
): { pct: number; scope: string | null } {
  if (channel !== null) {
    const perChannel = completeness?.per_channel?.[channel];
    if (typeof perChannel === 'number') {
      return { pct: perChannel, scope: channel };
    }
  }
  const perLocale = completeness?.per_locale?.[locale];
  if (typeof perLocale === 'number') {
    return { pct: perLocale, scope: locale };
  }
  return { pct: completeness?.global ?? fallbackPct, scope: null };
}
