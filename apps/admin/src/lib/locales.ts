/**
 * VIEW-01 (#372) — single-source-of-truth list of locales the platform
 * recognizes. Mirrors `App\Shared\Domain\LocaleLibrary::CODES` on the BE
 * side; an integration test asserts the two stay in sync.
 *
 * Adding a locale here is a deliberate platform decision (translation
 * completeness, JSONB sizes, fallback chains in LocaleTabsField). Don't
 * expand silently — bring product approval first.
 */
export interface LocaleEntry {
  readonly code: string;
  readonly label: string;
  readonly flag: string;
}

export const LOCALE_LIBRARY: readonly LocaleEntry[] = [
  { code: 'pl', label: 'Polski', flag: '🇵🇱' },
  { code: 'en', label: 'English', flag: '🇬🇧' },
  { code: 'de', label: 'Deutsch', flag: '🇩🇪' },
  { code: 'fr', label: 'Français', flag: '🇫🇷' },
  { code: 'it', label: 'Italiano', flag: '🇮🇹' },
  { code: 'es', label: 'Español', flag: '🇪🇸' },
  { code: 'pt', label: 'Português', flag: '🇵🇹' },
  { code: 'nl', label: 'Nederlands', flag: '🇳🇱' },
  { code: 'cs', label: 'Čeština', flag: '🇨🇿' },
  { code: 'sk', label: 'Slovenčina', flag: '🇸🇰' },
  { code: 'ru', label: 'Русский', flag: '🇷🇺' },
  { code: 'uk', label: 'Українська', flag: '🇺🇦' },
  { code: 'hu', label: 'Magyar', flag: '🇭🇺' },
  { code: 'ro', label: 'Română', flag: '🇷🇴' },
] as const;

export const LOCALE_CODES = LOCALE_LIBRARY.map((l) => l.code) as readonly string[];

export function findLocaleEntry(code: string): LocaleEntry | undefined {
  return LOCALE_LIBRARY.find((l) => l.code === code);
}
