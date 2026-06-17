<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * AUD-013 / AUD-014 (W1-9) — restore scale indexes on `objects` dropped by the
 * auto-generated regression in Version20260430092112.
 *
 * That migration's up() DROPped three indexes without recreating them in the
 * forward path:
 *   - objects_attributes_indexed_gin (GIN on attributes_indexed)
 *   - objects_path_gist_idx          (GiST on path, partial kind='category')
 *   - objects_path_btree_idx         (btree on path, partial)
 * Doctrine's diff did not understand `USING GIN` / `USING GIST`, treated them
 * as foreign, and the regenerated down() restored them as plain btree.
 *
 * Consequence (confirmed on the live dev DB, 6852 objects):
 *   - `attributes_indexed @> '{...}'` (AttributeFilter / JsonbContainsFunction,
 *     the hybrid-attribute filter that backs `?attribute[brand]=…`) runs as a
 *     Seq Scan + Filter over every tenant row — the product differentiator does
 *     not scale to 50k SKU.
 *   - `path <@ :ancestor` (ltree category-tree queries) can only be evaluated as
 *     a post-scan Filter; no usable index exists even with enable_seqscan=off.
 *
 * Fix: recreate the GIN and GiST indexes in a forward migration.
 *   - GIN uses `jsonb_path_ops`: smaller and faster than the default opclass for
 *     the `@>` containment operator, which is the only operator the codebase
 *     issues against attributes_indexed.
 *   - GiST is partial (kind='category'): only category objects carry a path.
 *
 * Standard (transactional) CREATE INDEX is used rather than CONCURRENTLY: the
 * table is tiny on dev (6852 rows / 5 categories), index builds are instant, and
 * keeping the migration transactional guarantees a clean up/down/up round-trip.
 * CONCURRENTLY would require isTransactional(): false and forfeit atomicity for a
 * benefit that does not apply at this volume.
 */
final class Version20260617210000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Restore scale indexes on objects: GIN(attributes_indexed jsonb_path_ops) + GiST(path) partial kind=category (AUD-013/AUD-014, W1-9).';
    }

    public function up(Schema $schema): void
    {
        // AUD-013: GIN on attributes_indexed for jsonb `@>` containment.
        // jsonb_path_ops is the smaller/faster opclass for the @> operator.
        $this->addSql('CREATE INDEX IF NOT EXISTS objects_attributes_indexed_gin_idx ON objects USING GIN (attributes_indexed jsonb_path_ops)');

        // AUD-014: GiST on ltree path, partial to the category subtree (only
        // category objects have a path), for `path <@ :ancestor` / `path ~ :lquery`.
        $this->addSql("CREATE INDEX IF NOT EXISTS objects_path_gist_idx ON objects USING GIST (path) WHERE (kind)::text = 'category'");
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP INDEX IF EXISTS objects_attributes_indexed_gin_idx');
        $this->addSql('DROP INDEX IF EXISTS objects_path_gist_idx');
    }
}
