<?php

declare(strict_types=1);

namespace App\Tests\Api\Import;

use App\Catalog\Domain\AttributeType;
use App\Catalog\Domain\Entity\Attribute;
use App\Catalog\Domain\Entity\AttributeOption;
use App\Catalog\Domain\Entity\CatalogObject;
use App\Catalog\Domain\Entity\ObjectCategory;
use App\Catalog\Domain\Entity\ObjectType;
use App\Catalog\Domain\Entity\ObjectTypeAttribute;
use App\Catalog\Domain\Entity\ObjectValue;
use App\Catalog\Domain\ObjectKind;
use App\Catalog\Domain\Provenance;
use App\Export\Application\Sync\SyncExportRunner;
use App\Export\Domain\Entity\ExportSession;
use App\Export\Domain\Enum\ExportEntityType;
use App\Export\Domain\Enum\ExportFormat;
use App\Export\Domain\Enum\ExportSource;
use App\Export\Domain\Enum\ExportTargetScope;
use App\Shared\Application\TenantContext;
use App\Shared\Domain\Tenant;
use App\Tests\Api\Catalog\CatalogApiTestCase;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\Uid\Uuid;

use const JSON_THROW_ON_ERROR;

/**
 * IMP2-1.5 (#1467, ADR-0019) — GOLDEN ROUND-TRIP v0: the REAL exporter
 * produces a CSV which the REAL import engine consumes in UPSERT mode;
 * the test asserts envelope equality in the database afterwards.
 *
 * This replaces the dry-run-only ImportRoundTripApiTest (#1130) which
 * never ran the exporter and never persisted — format drift between
 * ValueSerializer and the import parsers is now guarded by CI.
 *
 * v0 matrix: every value-carrying AttributeType (global + one non-primary-locale (en; primary-locale columns normalise to the global row like the admin path — #1146 covered in IMP2-1.6)
 * column), single category assignment, plus the modified-cell scenario
 * (export → edit → re-import updates exactly one value). IMP2-1.6 adds the
 * channel-only and combined locale+channel scopes (gr_chan); variants,
 * relations and multi-categories extend this in IMP2-1.10 after their
 * engine tickets (1.7–1.8) land.
 *
 * Normalisation rules v1 (ADR-0019): numeric string ≡ number for
 * number/price.amount/metric.value; everything else compares strictly.
 */
final class GoldenRoundTripApiTest extends CatalogApiTestCase
{
    /**
     * type => [seeded envelope, csv column key].
     *
     * @var array<string, array{0: AttributeType, 1: array<string, mixed>}>
     */
    private const array MATRIX = [
        'gr_text' => [AttributeType::Text, ['value' => 'Stalowy uchwyt M8']],
        'gr_textarea' => [AttributeType::Textarea, ['value' => "Linia 1\nLinia 2"]],
        'gr_wysiwyg' => [AttributeType::Wysiwyg, ['value' => '<p>Opis <b>HTML</b></p>']],
        'gr_number' => [AttributeType::Number, ['value' => 12.5]],
        'gr_date' => [AttributeType::Date, ['value' => '2026-03-15']],
        'gr_datetime' => [AttributeType::Datetime, ['value' => '2026-03-15T10:30:00+00:00']],
        'gr_boolean' => [AttributeType::Boolean, ['value' => true]],
        'gr_color' => [AttributeType::Color, ['value' => '#ff8800']],
        'gr_email' => [AttributeType::Email, ['value' => 'kontakt@example.com']],
        'gr_identifier' => [AttributeType::Identifier, ['value' => '5901234123457']],
        'gr_select' => [AttributeType::Select, ['option_code' => 'red']],
        'gr_multiselect' => [AttributeType::Multiselect, ['option_codes' => ['new', 'sale']]],
        'gr_price' => [AttributeType::Price, ['amount' => 249.99, 'currency' => 'PLN']],
        'gr_metric' => [AttributeType::Metric, ['value' => 0.75, 'unit' => 'kg']],
    ];

    #[Test]
    public function exportedCsvReimportsWithEnvelopeEquality(): void
    {
        $client = $this->authenticatedClient();
        $tenant = $this->tenant();
        self::getContainer()->get(TenantContext::class)->set($tenant);
        $em = $this->em();

        $objectType = $em->getRepository(ObjectType::class)
            ->find(Uuid::fromString($this->objectTypeIdFor(ObjectKind::Product)));
        \assert($objectType instanceof ObjectType);

        // ── Seed: attributes (one per type), options, category, product ──
        /** @var array<string, Attribute> $attributes */
        $attributes = [];
        foreach (self::MATRIX as $code => [$type]) {
            $attribute = new Attribute($code, ['en' => $code], $type);
            $em->persist($attribute);
            $em->persist(new ObjectTypeAttribute($objectType, $attribute));
            $attributes[$code] = $attribute;
        }
        $name = new Attribute('gr_name', ['en' => 'Name'], AttributeType::Text);
        $name->changeLocalizable(true);
        $em->persist($name);
        $em->persist(new ObjectTypeAttribute($objectType, $name));
        $attributes['gr_name'] = $name;

        // IMP2-1.6 (#1469): a localizable + scopable attribute drives the
        // channel and locale+channel round-trip rows of the matrix.
        $chan = new Attribute('gr_chan', ['en' => 'Channel-scoped'], AttributeType::Text);
        $chan->changeLocalizable(true);
        $chan->changeScopable(true);
        $em->persist($chan);
        $em->persist(new ObjectTypeAttribute($objectType, $chan));
        $attributes['gr_chan'] = $chan;

        $em->persist(new AttributeOption($attributes['gr_select'], 'red', ['en' => 'Red'], 1));
        $em->persist(new AttributeOption($attributes['gr_multiselect'], 'new', ['en' => 'New'], 1));
        $em->persist(new AttributeOption($attributes['gr_multiselect'], 'sale', ['en' => 'Sale'], 2));

        // Active tenant locale 'en_US' (language 'en') — the column grammar
        // (IMP2-1.6) validates header suffixes against this registry; the
        // schema-built test DB ships no locale seeds.
        $enLocale = new \App\Channel\Domain\Entity\Locale('en_US', 'English (US)', null, 'en');
        $em->persist($enLocale);
        $em->persist(new \App\Channel\Domain\Entity\TenantLocale($enLocale));

        // Channel 'shopify' — the grammar resolves `gr_chan.shopify` /
        // `gr_chan.en.shopify` headers against it (tenant stamped by the
        // listener from the TenantContext set above).
        $shopify = new \App\Channel\Domain\Entity\Channel('shopify', 'Shopify');
        $em->persist($shopify);
        $shopifyId = $shopify->getId();

        $categoryType = $em->getRepository(ObjectType::class)
            ->find(Uuid::fromString($this->objectTypeIdFor(ObjectKind::Category)));
        \assert($categoryType instanceof ObjectType);
        $category = new CatalogObject($categoryType, 'gr-cat');
        $category2 = new CatalogObject($categoryType, 'gr-cat2');
        $em->persist($category);
        $em->persist($category2);

        $product = new CatalogObject($objectType, 'GR-001');
        // IMP2-1.7: non-default status/enabled exercise the column round-trip.
        $product->transitionTo('published');
        $product->changeEnabled(false);
        $em->persist($product);
        foreach (self::MATRIX as $code => [, $envelope]) {
            $em->persist(new ObjectValue($product, $attributes[$code], $envelope, Provenance::Manual));
        }
        $em->persist(new ObjectValue($product, $name, ['value' => 'Uchwyt globalny'], Provenance::Manual));
        $em->persist(new ObjectValue($product, $name, ['value' => 'Uchwyt PL'], Provenance::Manual, null, 'en'));
        // gr_chan: global, channel-only, and locale+channel scopes (IMP2-1.6).
        $em->persist(new ObjectValue($product, $chan, ['value' => 'Chan global'], Provenance::Manual));
        $em->persist(new ObjectValue($product, $chan, ['value' => 'Chan shopify'], Provenance::Manual, $shopifyId));
        $em->persist(new ObjectValue($product, $chan, ['value' => 'Chan EN shopify'], Provenance::Manual, $shopifyId, 'en'));
        $em->persist(new ObjectCategory(product: $product, category: $category, isPrimary: true, position: 0));
        $em->persist(new ObjectCategory(product: $product, category: $category2, isPrimary: false, position: 1));
        $em->flush();
        $productId = $product->getId();

        $expected = $this->envelopesOf($productId);
        self::assertNotSame([], $expected);

        // ── Act 1: real exporter → CSV ──
        $columns = array_merge(
            ['sku', 'category'],
            array_keys(self::MATRIX),
            ['gr_name', 'gr_name.en', 'gr_chan', 'gr_chan.shopify', 'gr_chan.en.shopify', 'status', 'enabled'],
        );
        $csv = $this->runProductExport($tenant, $columns, [$productId], ['shopify']);
        self::assertStringContainsString('GR-001', $csv);
        self::assertStringContainsString('249.99 PLN', $csv, 'price serialises as "amount CUR"');
        self::assertStringContainsString('Chan EN shopify', $csv, 'combined locale+channel cell present in export');

        // ── Act 2: real import (UPSERT) of that very file ──
        $mapping = ['sku' => 'sku', 'category' => '__category__'];
        foreach (array_keys(self::MATRIX) as $code) {
            $mapping[$code] = $code;
        }
        $mapping['gr_name'] = 'gr_name';
        $mapping['gr_name.en'] = 'gr_name';
        $mapping['gr_chan'] = 'gr_chan';
        $mapping['gr_chan.shopify'] = 'gr_chan';
        $mapping['gr_chan.en.shopify'] = 'gr_chan';
        $mapping['status'] = '__status__';
        $mapping['enabled'] = '__enabled__';

        $body = $this->postImport($client, $csv, $objectType->getId()->toRfc4122(), $mapping);
        $sessionId = $body['id'];
        \assert(\is_string($sessionId));
        self::assertSame([], $this->reportRows($client, $sessionId), 'no findings expected');
        self::assertSame('success', $body['status']);
        self::assertSame(1, $body['success_count']);
        self::assertSame(1, $body['updated_count'], 'UPSERT must update, not duplicate');
        self::assertSame(0, $body['error_count']);

        // Same object — no duplicate row was created.
        $em->clear();
        $count = $em->getConnection()->fetchOne(
            "SELECT count(*) FROM objects WHERE code = 'GR-001'",
        );
        self::assertSame(1, (int) (\is_scalar($count) ? $count : 0));

        // ── Assert: envelope equality after the round-trip ──
        $actual = $this->envelopesOf($productId);
        foreach ($expected as $key => $envelope) {
            self::assertArrayHasKey($key, $actual, \sprintf('value "%s" lost in round-trip', $key));
            self::assertEnvelopeEquals($envelope, $actual[$key], $key);
        }

        // ── Assert: IMP2-1.7 — categories (replace, primary + order) and
        // status/enabled round-trip 1:1. ──
        $catCodes = $em->getConnection()->fetchFirstColumn(
            <<<'SQL'
                SELECT c.code FROM object_categories oc
                JOIN objects c ON c.id = oc.category_id
                JOIN objects o ON o.id = oc.object_id
                WHERE o.code = 'GR-001' ORDER BY oc.position
                SQL,
        );
        self::assertSame(['gr-cat', 'gr-cat2'], $catCodes, 'both categories survive the round-trip, in order');
        $primaryCode = $em->getConnection()->fetchOne(
            <<<'SQL'
                SELECT c.code FROM object_categories oc
                JOIN objects c ON c.id = oc.category_id
                JOIN objects o ON o.id = oc.object_id
                WHERE o.code = 'GR-001' AND oc.is_primary = true
                SQL,
        );
        self::assertSame('gr-cat', $primaryCode, 'first category stays primary');

        $reloaded = $em->find(CatalogObject::class, $productId);
        \assert($reloaded instanceof CatalogObject);
        self::assertSame('published', $reloaded->getStatus());
        self::assertFalse($reloaded->isEnabled());

        // ── Act 3: edit one cell → re-import updates exactly that value ──
        $edited = str_replace('Uchwyt globalny', 'Uchwyt v2', $csv);
        self::assertNotSame($csv, $edited);
        $body2 = $this->postImport($client, $edited, $objectType->getId()->toRfc4122(), $mapping);
        self::assertSame(1, $body2['updated_count']);

        $after = $this->envelopesOf($productId);
        self::assertSame('Uchwyt v2', $after['gr_name||']['value'] ?? null);
        unset($expected['gr_name||'], $after['gr_name||']);
        foreach ($expected as $key => $envelope) {
            self::assertEnvelopeEquals($envelope, $after[$key], $key.' (po edycji innej komórki)');
        }
    }

    /**
     * @param array<string, mixed> $expected
     * @param array<string, mixed> $actual
     */
    private static function assertEnvelopeEquals(array $expected, array $actual, string $context): void
    {
        ksort($expected);
        ksort($actual);
        // ADR-0019 normalisation v1: numeric string ≡ number.
        $normalise = static function (array $envelope): array {
            foreach (['value', 'amount'] as $key) {
                if (isset($envelope[$key]) && \is_string($envelope[$key]) && is_numeric($envelope[$key])) {
                    $envelope[$key] = (float) $envelope[$key];
                }
                if (isset($envelope[$key]) && (\is_int($envelope[$key]) || \is_float($envelope[$key]))) {
                    $envelope[$key] = (float) $envelope[$key];
                }
            }

            return $envelope;
        };
        self::assertSame($normalise($expected), $normalise($actual), \sprintf('envelope drift for "%s"', $context));
    }

    /**
     * @return array<string, array<string, mixed>> "code|locale|channelId" => envelope
     */
    private function envelopesOf(Uuid $productId): array
    {
        $rows = $this->em()->getConnection()->fetchAllAssociative(
            <<<'SQL'
                SELECT a.code, ov.locale, ov.channel_id, ov.value
                FROM object_values ov
                JOIN attributes a ON a.id = ov.attribute_id
                WHERE ov.object_id = :id AND a.code LIKE 'gr_%'
                ORDER BY a.code, ov.locale NULLS FIRST, ov.channel_id NULLS FIRST
                SQL,
            ['id' => $productId->toRfc4122()],
        );

        $map = [];
        foreach ($rows as $row) {
            $raw = $row['value'];
            \assert(\is_string($raw));
            /** @var array<string, mixed> $envelope */
            $envelope = json_decode($raw, true, 512, JSON_THROW_ON_ERROR);
            $code = $row['code'];
            $locale = $row['locale'];
            $channelId = $row['channel_id'];
            \assert(\is_string($code));
            $map[$code.'|'.(\is_string($locale) ? $locale : '').'|'.(\is_string($channelId) ? $channelId : '')] = $envelope;
        }

        return $map;
    }

    /**
     * @param list<string> $columns
     * @param list<Uuid>   $ids
     * @param list<string> $channels
     */
    private function runProductExport(Tenant $tenant, array $columns, array $ids, array $channels = []): string
    {
        $session = new ExportSession(
            userId: Uuid::v7(),
            source: ExportSource::CentralTab,
            format: ExportFormat::Csv,
            targetScope: ExportTargetScope::Selected,
            selectedColumns: $columns,
            entityType: ExportEntityType::Product,
            selectedObjectIds: array_map(static fn (Uuid $id): string => $id->toRfc4122(), $ids),
            channels: $channels,
        );
        $session->assignTenant($tenant);

        $runner = self::getContainer()->get(SyncExportRunner::class);
        $path = tempnam(sys_get_temp_dir(), 'golden-').'.csv';
        try {
            $runner->runToFile($session, $path);

            return (string) file_get_contents($path);
        } finally {
            @unlink($path);
        }
    }

    /**
     * @param array<string, string> $mapping
     *
     * @return array<string, mixed>
     */
    private function postImport(
        \ApiPlatform\Symfony\Bundle\Test\Client $client,
        string $csv,
        string $objectTypeId,
        array $mapping,
    ): array {
        $path = tempnam(sys_get_temp_dir(), 'golden-import-').'.csv';
        file_put_contents($path, $csv);

        try {
            $client->request('POST', '/api/import-sessions', [
                'extra' => [
                    'parameters' => [
                        'target_object_type_id' => $objectTypeId,
                        'mapping' => json_encode($mapping, JSON_THROW_ON_ERROR),
                        'mode' => 'UPSERT',
                    ],
                    'files' => [
                        'file' => new UploadedFile($path, 'golden.csv', 'text/csv', null, true),
                    ],
                ],
            ]);
            self::assertResponseIsSuccessful();
            $response = $client->getResponse();
            \assert(null !== $response);
            $content = $response->getContent();
            /** @var array<string, mixed> $body */
            $body = json_decode($content, true, 512, JSON_THROW_ON_ERROR);

            return $body;
        } finally {
            @unlink($path);
        }
    }

    /** @return list<string> */
    private function reportRows(\ApiPlatform\Symfony\Bundle\Test\Client $client, string $sessionId): array
    {
        $client->request('GET', '/api/import-sessions/'.$sessionId.'/report.csv');
        $response = $client->getResponse();
        \assert(null !== $response);
        $content = $response->getContent();
        $lines = array_filter(explode("\n", trim($content)), static fn (string $line): bool => '' !== $line);

        return \array_slice($lines, 1);
    }

    private function tenant(): Tenant
    {
        $tenant = $this->em()->getRepository(Tenant::class)->findOneBy(['code' => self::TENANT_CODE]);
        \assert($tenant instanceof Tenant);

        return $tenant;
    }
}
