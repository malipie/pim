<?php

declare(strict_types=1);

namespace App\Catalog\Application;

use App\Catalog\Domain\Entity\SmartFilterPreset;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Uid\Uuid;

/**
 * VIEW-09 (#535) — seeds the five system-shipped Smart Filter Presets.
 *
 * The migration {@see \DoctrineMigrations\Version20260513120000} inlines
 * these rows on production-style boot. Tests use `doctrine:schema:create`
 * (faster than running 41 migrations) so the seed has to be replayed
 * explicitly — that lives here so production and test code converge on
 * the same definitions.
 *
 * Built-in presets are tenant-less (`tenant_id IS NULL`) and immutable;
 * the upsert logic skips them when they already exist.
 */
final class BuiltInSmartFilterPresetsSeeder
{
    /**
     * @return list<array{id: string, slug: string, name: array{pl: string, en: string}, icon: string, query: array<string, mixed>, sort_order: int}>
     */
    public static function definitions(): array
    {
        return [
            [
                'id' => '019089d0-0000-7000-8000-000000000001',
                'slug' => 'inconsistent-translations',
                'name' => ['pl' => 'Niespójne tłumaczenia', 'en' => 'Inconsistent translations'],
                'icon' => '🌐',
                'query' => [
                    'operator' => 'AND',
                    'conditions' => [
                        ['attr' => 'description.pl', 'op' => 'IS NOT EMPTY'],
                        ['attr' => 'description.en', 'op' => 'IS EMPTY'],
                    ],
                ],
                'sort_order' => 10,
            ],
            [
                'id' => '019089d0-0000-7000-8000-000000000002',
                'slug' => 'missing-images',
                'name' => ['pl' => 'Brakujące zdjęcia', 'en' => 'Missing images'],
                'icon' => '📷',
                'query' => ['attr' => 'main_image', 'op' => 'IS EMPTY'],
                'sort_order' => 20,
            ],
            [
                'id' => '019089d0-0000-7000-8000-000000000003',
                'slug' => 'weak-seo',
                'name' => ['pl' => 'Niepełne SEO', 'en' => 'Weak SEO'],
                'icon' => '🔍',
                'query' => [
                    'operator' => 'AND',
                    'conditions' => [
                        ['attr' => 'description', 'op' => 'IS NOT EMPTY'],
                        ['attr' => 'meta_description', 'op' => 'IS EMPTY'],
                    ],
                ],
                'sort_order' => 30,
            ],
            [
                'id' => '019089d0-0000-7000-8000-000000000004',
                'slug' => 'red-low-completeness',
                'name' => ['pl' => 'Czerwone (<50%)', 'en' => 'Red (<50%)'],
                'icon' => '🔴',
                'query' => ['attr' => 'completeness_pct', 'op' => '<', 'value' => 50],
                'sort_order' => 40,
            ],
            [
                'id' => '019089d0-0000-7000-8000-000000000005',
                'slug' => 'no-category',
                'name' => ['pl' => 'Bez kategorii', 'en' => 'No category'],
                'icon' => '📂',
                'query' => ['attr' => 'category', 'op' => 'IS EMPTY'],
                'sort_order' => 50,
            ],
        ];
    }

    public function __construct(
        private readonly EntityManagerInterface $em,
    ) {
    }

    /**
     * Idempotent seed: skips slugs that already exist (lookup keyed on
     * `is_built_in=true` + slug).
     */
    public function seed(): void
    {
        $repo = $this->em->getRepository(SmartFilterPreset::class);

        foreach (self::definitions() as $def) {
            $existing = $repo->findOneBy(['slug' => $def['slug'], 'isBuiltIn' => true]);
            if (null !== $existing) {
                continue;
            }

            $preset = new SmartFilterPreset(
                slug: $def['slug'],
                name: $def['name'],
                icon: $def['icon'],
                query: $def['query'],
                userId: null,
                isBuiltIn: true,
                sortOrder: $def['sort_order'],
                id: Uuid::fromString($def['id']),
                // UP-05 (#1020): built-in presets target the legacy product
                // list. Scoping them to `products` keeps them out of
                // `/objects/{custom_kind}` views.
                resource: 'products',
            );

            $this->em->persist($preset);
        }

        $this->em->flush();
    }
}
