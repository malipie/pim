import type { TFunction } from 'i18next';

/**
 * UI polish #848 — relative-time helper for the Users list "Last login"
 * column per PRD §5.4 mockup ("2 min ago", "1 hour ago", "yesterday").
 *
 * Stays compact + locale-aware via i18n keys; no external dep (date-fns
 * etc.) because the use site is one column and the math is trivial.
 * Falls back to absolute date for events older than 14 days where
 * relative phrasing stops being useful.
 */
export function relativeTime(t: TFunction, locale: string, iso: string | null): string {
  if (null === iso) return t('settings.users.last_login_never');
  const then = new Date(iso);
  if (Number.isNaN(then.getTime())) return t('settings.users.last_login_never');
  const now = new Date();
  const seconds = Math.floor((now.getTime() - then.getTime()) / 1000);
  if (seconds < 0) return then.toLocaleString(locale);
  if (seconds < 60) return t('settings.users.relative.just_now');
  const minutes = Math.floor(seconds / 60);
  if (minutes < 60) return t('settings.users.relative.minutes_ago', { count: minutes });
  const hours = Math.floor(minutes / 60);
  if (hours < 24) return t('settings.users.relative.hours_ago', { count: hours });
  const days = Math.floor(hours / 24);
  if (days === 1) return t('settings.users.relative.yesterday');
  if (days < 14) return t('settings.users.relative.days_ago', { count: days });
  return then.toLocaleDateString(locale);
}

/**
 * UI polish #848 — "link Nd valid" string per PRD §5.4 mockup, where N
 * is days remaining until invitation expiry. Returns null when the
 * invitation has expired (the backend filters those out, but defensive).
 */
export function invitationValidity(t: TFunction, expiresAtIso: string | null): string | null {
  if (null === expiresAtIso) return null;
  const expires = new Date(expiresAtIso);
  if (Number.isNaN(expires.getTime())) return null;
  const seconds = Math.floor((expires.getTime() - Date.now()) / 1000);
  if (seconds <= 0) return null;
  const days = Math.floor(seconds / 86400);
  const hours = Math.floor(seconds / 3600);
  if (days >= 1) return t('settings.users.invitation_valid_days', { count: days });
  return t('settings.users.invitation_valid_hours', { count: Math.max(1, hours) });
}
