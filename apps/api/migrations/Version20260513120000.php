<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * VIEW-09 (#535) — `smart_filter_presets` table for rule-based filter
 * presets surfaced in the products list smart filter row. System-shipped
 * built-ins (`tenant_id IS NULL`, `user_id IS NULL`, `is_built_in=true`)
 * are immutable per tenant; user-defined presets are owner-scoped.
 *
 * Five built-in presets seeded inline so the smart filter row works on
 * a clean tenant without app-bootstrap fixtures. Slugs are stable: tools
 * and tests reference them by slug, not UUID.
 */
final class Version20260513120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'VIEW-09 smart_filter_presets table + 5 built-in seed (#535).';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            CREATE TABLE smart_filter_presets (
              id UUID NOT NULL,
              tenant_id UUID NULL,
              user_id UUID NULL,
              slug VARCHAR(64) NOT NULL,
              name JSONB NOT NULL,
              icon VARCHAR(64) NOT NULL,
              query JSONB NOT NULL,
              is_built_in BOOLEAN NOT NULL DEFAULT false,
              sort_order INTEGER NOT NULL DEFAULT 0,
              created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
              updated_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
              PRIMARY KEY (id)
            )
            SQL);

        // Unique slug per (tenant_id, user_id) — system-shipped (both NULL)
        // collides on slug across the whole table thanks to COALESCE.
        $this->addSql(<<<'SQL'
            CREATE UNIQUE INDEX smart_filter_presets_slug_uniq
              ON smart_filter_presets (
                COALESCE(tenant_id, '00000000-0000-0000-0000-000000000000'::uuid),
                COALESCE(user_id,   '00000000-0000-0000-0000-000000000000'::uuid),
                slug
              )
            SQL);

        $this->addSql('CREATE INDEX smart_filter_presets_tenant_idx ON smart_filter_presets (tenant_id) WHERE tenant_id IS NOT NULL');
        $this->addSql('CREATE INDEX smart_filter_presets_user_idx ON smart_filter_presets (user_id) WHERE user_id IS NOT NULL');
        $this->addSql('CREATE INDEX smart_filter_presets_builtin_idx ON smart_filter_presets (is_built_in) WHERE is_built_in = true');

        // Seed 5 built-in presets (PRD §8.2 rule-based, marketing-honest).
        $now = '2026-05-13 00:00:00';
        $seed = [
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

        foreach ($seed as $row) {
            $this->addSql(
                'INSERT INTO smart_filter_presets (id, tenant_id, user_id, slug, name, icon, query, is_built_in, sort_order, created_at, updated_at) '
                . 'VALUES (:id, NULL, NULL, :slug, CAST(:name AS JSONB), :icon, CAST(:query AS JSONB), true, :sort_order, :now, :now)',
                [
                    'id' => $row['id'],
                    'slug' => $row['slug'],
                    'name' => json_encode($row['name'], JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR),
                    'icon' => $row['icon'],
                    'query' => json_encode($row['query'], JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR),
                    'sort_order' => $row['sort_order'],
                    'now' => $now,
                ]
            );
        }
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP INDEX IF EXISTS smart_filter_presets_builtin_idx');
        $this->addSql('DROP INDEX IF EXISTS smart_filter_presets_user_idx');
        $this->addSql('DROP INDEX IF EXISTS smart_filter_presets_tenant_idx');
        $this->addSql('DROP INDEX IF EXISTS smart_filter_presets_slug_uniq');
        $this->addSql('DROP TABLE IF EXISTS smart_filter_presets');
    }
}
