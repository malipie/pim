<?php

declare(strict_types=1);

namespace App\Tests\Api\Catalog;

use App\Catalog\Domain\ObjectKind;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

use const JSON_THROW_ON_ERROR;

/**
 * #1416 — `pim:catalog:backfill-required`: dry-run reports without
 * writing, --apply stamps "Brak danych" with provenance=import on
 * text-like required attributes, non-text gaps are report-only.
 */
final class BackfillRequiredAttributesCommandTest extends CatalogApiTestCase
{
    #[Test]
    public function dryRunReportsAndApplyFillsTextLikeRequiredGaps(): void
    {
        $client = $this->authenticatedClient();
        $productOt = $this->objectTypeIdFor(ObjectKind::Product);

        // Required TEXT attribute (backfillable) + required SELECT
        // attribute (report-only) attached to the product ObjectType.
        $textAttrId = $this->createAttribute($client, 'bf_text', 'text');
        $selectAttrId = $this->createAttribute($client, 'bf_select', 'select');
        foreach ([$textAttrId, $selectAttrId] as $attrId) {
            $client->request('POST', '/api/object_types/'.$productOt.'/attributes/'.$attrId);
            self::assertResponseStatusCodeSame(204);
        }

        // A dirty legacy object (no values for either required attribute)
        // and a clean one (text value present).
        $dirtyResponse = $client->request('POST', '/api/products', [
            'headers' => ['content-type' => 'application/ld+json'],
            'body' => json_encode([
                'code' => 'BF-DIRTY',
                'objectTypeId' => $productOt,
                'attributes' => ['name' => 'Dirty'],
            ], JSON_THROW_ON_ERROR),
        ]);
        self::assertResponseStatusCodeSame(201);
        $dirtyId = $dirtyResponse->toArray()['id'];
        \assert(\is_string($dirtyId));
        $cleanResponse = $client->request('POST', '/api/products', [
            'headers' => ['content-type' => 'application/ld+json'],
            'body' => json_encode([
                'code' => 'BF-CLEAN',
                'objectTypeId' => $productOt,
                'attributes' => ['name' => 'Clean', 'bf_text' => 'already filled'],
            ], JSON_THROW_ON_ERROR),
        ]);
        self::assertResponseStatusCodeSame(201);
        $cleanId = $cleanResponse->toArray()['id'];
        \assert(\is_string($cleanId));

        // Dry-run: reports one pending placeholder + one non-text gap,
        // writes nothing.
        $tester = $this->commandTester();
        $exitCode = $tester->execute(['--tenant' => self::TENANT_CODE]);
        self::assertSame(Command::SUCCESS, $exitCode, $tester->getDisplay());
        $display = $tester->getDisplay();
        self::assertStringContainsString('DRY-RUN', $display);
        self::assertStringContainsString('1 placeholder(s) pending', $display);
        // bf_select is empty on BOTH objects (the "clean" one only filled text).
        self::assertStringContainsString('2 gap(s) reported', $display);
        self::assertStringContainsString('bf_select', $display);
        self::assertSame(
            [],
            $this->attributesIndexedFor($client, $dirtyId, 'bf_text'),
            'Dry-run must not write the placeholder.',
        );

        // Apply: the text gap gets "Brak danych" with provenance=import;
        // the select gap stays untouched (report-only).
        $tester = $this->commandTester();
        $exitCode = $tester->execute(['--tenant' => self::TENANT_CODE, '--apply' => true]);
        self::assertSame(Command::SUCCESS, $exitCode, $tester->getDisplay());
        $display = $tester->getDisplay();
        self::assertStringContainsString('APPLY', $display);
        self::assertStringContainsString('1 placeholder(s) written', $display);

        $entry = $this->attributesIndexedFor($client, $dirtyId, 'bf_text');
        self::assertSame('Brak danych', $entry['value'] ?? null);
        // Provenance lives on the ObjectValue row (attributesIndexed does
        // not denormalize it) — assert straight from the repository.
        self::assertSame('import', $this->provenanceFor($dirtyId, 'bf_text'));
        self::assertSame(
            [],
            $this->attributesIndexedFor($client, $dirtyId, 'bf_select'),
            'Non-text gaps must stay untouched.',
        );

        // The clean object keeps its operator value.
        $clean = $this->attributesIndexedFor($client, $cleanId, 'bf_text');
        self::assertSame('already filled', $clean['value'] ?? null);

        // Idempotency: a second apply finds nothing text-like to fill.
        $tester = $this->commandTester();
        $tester->execute(['--tenant' => self::TENANT_CODE, '--apply' => true]);
        self::assertStringContainsString('0 placeholder(s) written', $tester->getDisplay());
    }

    private function createAttribute(
        \ApiPlatform\Symfony\Bundle\Test\Client $client,
        string $code,
        string $type,
    ): string {
        $response = $client->request('POST', '/api/attributes', [
            'headers' => ['content-type' => 'application/ld+json', 'accept' => 'application/ld+json'],
            'body' => json_encode([
                'code' => $code,
                'type' => $type,
                'label' => ['pl' => $code, 'en' => $code],
                'required' => true,
            ], JSON_THROW_ON_ERROR),
        ]);
        self::assertResponseStatusCodeSame(201);
        $id = $response->toArray()['id'];
        \assert(\is_string($id));

        return $id;
    }

    /**
     * @return array<string, mixed>
     */
    private function attributesIndexedFor(
        \ApiPlatform\Symfony\Bundle\Test\Client $client,
        string $objectId,
        string $attributeCode,
    ): array {
        $response = $client->request('GET', '/api/products/'.$objectId, [
            'headers' => ['accept' => 'application/ld+json'],
        ]);
        self::assertResponseStatusCodeSame(200);
        $row = $response->toArray();
        $indexed = $row['attributesIndexed'] ?? [];
        \assert(\is_array($indexed));
        $entry = $indexed[$attributeCode] ?? [];
        \assert(\is_array($entry));

        $normalized = [];
        foreach ($entry as $key => $value) {
            $normalized[(string) $key] = $value;
        }

        return $normalized;
    }

    private function provenanceFor(string $objectId, string $attributeCode): string
    {
        $em = $this->em();
        $provenance = $em->createQuery(
            'SELECT v.provenance FROM '.\App\Catalog\Domain\Entity\ObjectValue::class.' v'
            .' JOIN v.attribute a WHERE IDENTITY(v.object) = :objectId AND a.code = :code',
        )
            ->setParameter('objectId', $objectId)
            ->setParameter('code', $attributeCode)
            ->getSingleScalarResult();
        \assert(\is_string($provenance));

        return $provenance;
    }

    private function commandTester(): CommandTester
    {
        $application = new Application(self::$kernel ?? self::bootKernel());

        return new CommandTester($application->find('pim:catalog:backfill-required'));
    }
}
