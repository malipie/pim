<?php

declare(strict_types=1);

namespace App\Catalog\Domain;

/**
 * VIEW-08 (#427) — registry of built-in system menu items.
 *
 * System items are the parts of the admin sidebar that are not domain
 * objects (Pulpit, Multimedia, Workflow, Integracje, Ustawienia,
 * Modelowanie). They live in code (not in the database) because they
 * are wired to specific FE routes and components — they exist iff the
 * admin is built with the matching pages. `MenuConfiguration.items`
 * stores only the *which-where* (position + visible) keyed by these
 * registry keys.
 *
 * `protected: true` items cannot be hidden through the UI. The two
 * obvious candidates are `settings` and `modeling` — without them the
 * operator cannot get back to this very page or open ObjectType detail,
 * so we lock them visible by service-level invariant rather than relying
 * on the FE to enforce.
 *
 * `comingSoon: true` items render as disabled `<span>` in the sidebar
 * with a "Wkrótce" badge — the route is null so a NavLink would 404.
 *
 * `route: null` is reserved for coming-soon entries; everything else
 * routes to a real admin page that exists today.
 */
final class SystemMenuItemRegistry
{
    public const string KIND = 'system';

    /**
     * @return array<string, array{
     *     route: ?string,
     *     icon: string,
     *     labelKey: string,
     *     comingSoon: bool,
     *     protected: bool
     * }>
     */
    public static function items(): array
    {
        return [
            'dashboard' => [
                'route' => '/dashboard',
                'icon' => 'LayoutDashboard',
                'labelKey' => 'nav.dashboard',
                'comingSoon' => false,
                'protected' => false,
            ],
            'catalogs_pdf' => [
                'route' => '/catalogs-pdf',
                'icon' => 'FileText',
                'labelKey' => 'nav.catalogsPdf',
                'comingSoon' => false,
                'protected' => false,
            ],
            'multimedia' => [
                'route' => '/assets',
                'icon' => 'Image',
                'labelKey' => 'nav.multimedia',
                'comingSoon' => false,
                'protected' => false,
            ],
            'workflow' => [
                'route' => null,
                'icon' => 'Workflow',
                'labelKey' => 'nav.workflow',
                'comingSoon' => true,
                'protected' => false,
            ],
            // Epik 04 Publikacje (UI-09 / 0.13). Dziś prowadzi prosto do
            // sub-tab Imports — Exports / API Configurator / Webhooks dochodzą
            // wraz z epikami 0.8-0.10. Osobno od `integrations` dlatego, że
            // ten ostatni nadal kieruje na samodzielny moduł Profile API
            // (pozostałość po VIEW-08); konsolidacja w późniejszym ticket'cie.
            'publications' => [
                'route' => '/publications',
                'icon' => 'Send',
                'labelKey' => 'nav.publications',
                'comingSoon' => false,
                'protected' => false,
            ],
            'integrations' => [
                'route' => '/api-profiles',
                'icon' => 'Plug2',
                'labelKey' => 'nav.integrations',
                'comingSoon' => false,
                'protected' => false,
            ],
            'settings' => [
                'route' => '/settings',
                'icon' => 'Cog',
                'labelKey' => 'nav.settings',
                'comingSoon' => false,
                'protected' => true,
            ],
            'modeling' => [
                'route' => '/modeling',
                'icon' => 'Settings2',
                'labelKey' => 'nav.modeling',
                'comingSoon' => false,
                'protected' => true,
            ],
        ];
    }

    public static function exists(string $key): bool
    {
        return \array_key_exists($key, self::items());
    }

    public static function isProtected(string $key): bool
    {
        return self::items()[$key]['protected'] ?? false;
    }

    /**
     * @return array{route: ?string, icon: string, labelKey: string, comingSoon: bool, protected: bool}|null
     */
    public static function get(string $key): ?array
    {
        return self::items()[$key] ?? null;
    }

    /**
     * Default seed order replicating the legacy hard-coded sidebar — minus
     * `services` (operator adds it manually as a custom ObjectType later
     * per VIEW-08 ticket body).
     *
     * Built-in `Product` is interleaved at position 1 by `DefaultMenuSeeder`
     * because it is an `object_type` ref, not a system one — this list only
     * yields the system ordering.
     *
     * @return list<string>
     */
    public static function defaultOrder(): array
    {
        return [
            'dashboard',
            // <- product object_type slot here (handled by DefaultMenuSeeder)
            'catalogs_pdf',
            'multimedia',
            'workflow',
            'publications',
            'integrations',
            'settings',
            'modeling',
        ];
    }
}
