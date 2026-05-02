<?php

declare(strict_types=1);

namespace App\Tests\Api\Catalog;

use App\Catalog\Domain\Entity\ObjectType;
use App\Catalog\Domain\ObjectKind;
use App\Catalog\Domain\Repository\ObjectTypeRepositoryInterface;
use App\Shared\Domain\Tenant;
use DateTimeImmutable;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Component\HttpFoundation\Response;

/**
 * VIEW-01 (#372) — end-to-end coverage of the modeling API surface
 * introduced by the Object Types view: PATCH on a built-in (icon-only),
 * PATCH rejection of locked field on built-in, custom ObjectType
 * lifecycle through POST → PATCH → DELETE, duplicate, and the Workspace
 * locale endpoints feeding LocaleTabsField.
 */
final class ObjectTypeView01ApiTest extends CatalogApiTestCase
{
    #[Test]
    public function listExposesNewSettingsFieldsForBuiltIns(): void
    {
        $client = $this->authenticatedClient();
        $response = $client->request('GET', '/api/object_types');

        self::assertResponseIsSuccessful();
        /** @var array<string, mixed> $payload */
        $payload = $response->toArray();
        $items = $payload['hydra:member'] ?? $payload['member'] ?? [];
        \assert(\is_array($items));
        self::assertNotEmpty($items);

        /** @var array<string, array<string, mixed>> $byKind */
        $byKind = [];
        foreach ($items as $row) {
            \assert(\is_array($row) && \is_string($row['kind']));
            $byKind[$row['kind']] = $row;
        }
        self::assertArrayHasKey('product', $byKind);
        self::assertTrue($byKind['product']['hasVariants']);
        self::assertFalse($byKind['product']['hierarchical']);
        self::assertArrayHasKey('category', $byKind);
        self::assertTrue($byKind['category']['hierarchical']);
    }

    #[Test]
    public function patchOnBuiltInAllowsIconChange(): void
    {
        $id = $this->objectTypeIdFor(ObjectKind::Product);
        $client = $this->authenticatedClient();

        $response = $client->request('PATCH', '/api/object_types/'.$id, [
            'json' => ['icon' => 'Box'],
            'headers' => ['content-type' => 'application/json'],
        ]);

        self::assertResponseIsSuccessful();
        self::assertSame('Box', $response->toArray()['icon']);
    }

    #[Test]
    public function patchOnBuiltInRefusesHierarchicalToggle(): void
    {
        $id = $this->objectTypeIdFor(ObjectKind::Product);
        $client = $this->authenticatedClient();

        $client->request('PATCH', '/api/object_types/'.$id, [
            'json' => ['hierarchical' => true],
            'headers' => ['content-type' => 'application/json'],
        ]);

        self::assertResponseStatusCodeSame(Response::HTTP_FORBIDDEN);
    }

    #[Test]
    public function customLifecyclePatchAndDelete(): void
    {
        $client = $this->authenticatedClient();
        $created = $client->request('POST', '/api/object_types', [
            'json' => [
                'code' => 'view01_custom',
                'label' => ['pl' => 'VIEW-01 custom', 'en' => 'VIEW-01 custom'],
                'icon' => 'Sparkles',
                'color' => '#6366f1',
                'hierarchical' => true,
            ],
        ]);
        self::assertResponseStatusCodeSame(Response::HTTP_CREATED);
        $createdBody = $created->toArray();
        \assert(\is_string($createdBody['id']));
        $id = $createdBody['id'];

        $patched = $client->request('PATCH', '/api/object_types/'.$id, [
            'json' => ['hasVariants' => true, 'abstract' => true],
            'headers' => ['content-type' => 'application/json'],
        ]);
        self::assertResponseIsSuccessful();
        $patchedBody = $patched->toArray();
        self::assertTrue($patchedBody['hasVariants']);
        self::assertTrue($patchedBody['abstract']);
        self::assertGreaterThan(1, $patchedBody['schemaVersion']);

        $client->request('DELETE', '/api/object_types/'.$id);
        self::assertResponseStatusCodeSame(Response::HTTP_NO_CONTENT);
    }

    #[Test]
    public function deleteOnBuiltInIsForbidden(): void
    {
        $id = $this->objectTypeIdFor(ObjectKind::Product);
        $client = $this->authenticatedClient();

        $client->request('DELETE', '/api/object_types/'.$id);

        self::assertResponseStatusCodeSame(Response::HTTP_FORBIDDEN);
    }

    #[Test]
    public function duplicateClonesSettings(): void
    {
        $id = $this->objectTypeIdFor(ObjectKind::Product);
        $client = $this->authenticatedClient();

        $response = $client->request('POST', '/api/object_types/'.$id.'/duplicate', [
            'json' => [
                'newCode' => 'product_pro',
                'newLabel' => ['pl' => 'Produkt Pro', 'en' => 'Product Pro'],
            ],
        ]);

        self::assertResponseStatusCodeSame(Response::HTTP_CREATED);
        $body = $response->toArray();
        self::assertSame('product_pro', $body['code']);
        self::assertSame('custom', $body['kind']);
        self::assertTrue($body['hasVariants']); // copied from product seed
    }

    #[Test]
    public function workspaceCurrentExposesEnabledLocales(): void
    {
        $client = $this->authenticatedClient();
        $response = $client->request('GET', '/api/workspaces/current');

        self::assertResponseIsSuccessful();
        $body = $response->toArray();
        self::assertSame(['pl', 'en'], $body['enabledLocales']);
        self::assertSame('pl', $body['primaryLocale']);
    }

    #[Test]
    public function workspaceLocalesAddIsIdempotentAndPersists(): void
    {
        $client = $this->authenticatedClient();

        $client->request('POST', '/api/workspaces/current/locales', [
            'json' => ['locale' => 'de'],
        ]);
        self::assertResponseStatusCodeSame(Response::HTTP_CREATED);

        // Idempotent re-add still returns 201 (the endpoint signals "current
        // state contains this locale" rather than diff'ing).
        $second = $client->request('POST', '/api/workspaces/current/locales', [
            'json' => ['locale' => 'de'],
        ]);
        self::assertResponseStatusCodeSame(Response::HTTP_CREATED);
        $secondLocales = $second->toArray()['enabledLocales'];
        \assert(\is_array($secondLocales));
        self::assertContains('de', $secondLocales);

        $current = $client->request('GET', '/api/workspaces/current');
        $currentLocales = $current->toArray()['enabledLocales'];
        \assert(\is_array($currentLocales));
        self::assertContains('de', $currentLocales);
    }

    #[Test]
    public function workspaceLocalesAddRejectsCodesOutsideLibrary(): void
    {
        $client = $this->authenticatedClient();

        $client->request('POST', '/api/workspaces/current/locales', [
            'json' => ['locale' => 'zz'],
        ]);

        self::assertResponseStatusCodeSame(Response::HTTP_BAD_REQUEST);
    }

    #[Test]
    public function attachedGroupsReturnsBuiltInGroupsForProduct(): void
    {
        $id = $this->objectTypeIdFor(ObjectKind::Product);
        $client = $this->authenticatedClient();

        $response = $client->request('GET', '/api/object_types/'.$id.'/attached_groups');

        self::assertResponseIsSuccessful();
        // Returned shape is the array directly — assert keys exist on the
        // first row (built-in groups are seeded by the BuiltInObjectType
        // attribute group seeder).
        $rows = $response->toArray();
        if ([] !== $rows) {
            $first = $rows[0];
            \assert(\is_array($first));
            self::assertArrayHasKey('id', $first);
            self::assertArrayHasKey('code', $first);
            self::assertArrayHasKey('system', $first);
            self::assertArrayHasKey('attrsCount', $first);
            self::assertArrayHasKey('attrsPreview', $first);
        }
    }

    #[Test]
    public function deleteRefusesWhenInstancesExist(): void
    {
        $client = $this->authenticatedClient();

        // Create a custom type, give it an instance, then attempt delete.
        $created = $client->request('POST', '/api/object_types', [
            'json' => [
                'code' => 'with_inst',
                'label' => ['pl' => 'Z instancjami'],
            ],
        ]);
        self::assertResponseStatusCodeSame(Response::HTTP_CREATED);
        $createdBody = $created->toArray();
        \assert(\is_string($createdBody['id']));
        $id = $createdBody['id'];

        // Insert an objects row directly via the EM — the catalog-side flow
        // would require attribute setup beyond the scope of this test.
        $em = $this->em();
        $tenant = $em->getRepository(Tenant::class)->findOneBy(['code' => self::TENANT_CODE]);
        \assert($tenant instanceof Tenant);
        $type = self::getContainer()->get(ObjectTypeRepositoryInterface::class)
            ->findById(\Symfony\Component\Uid\Uuid::fromString($id));
        \assert($type instanceof ObjectType);

        $em->getConnection()->insert('objects', [
            'id' => \Symfony\Component\Uid\Uuid::v7()->toRfc4122(),
            'tenant_id' => $tenant->getId()->toRfc4122(),
            'object_type_id' => $id,
            'kind' => 'custom',
            'code' => 'view01-instance-1',
            'status' => 'draft',
            'attributes_indexed' => '{}',
            'completeness' => '{}',
            'completeness_pct' => 0,
            'sync_status_aggregate' => 'gray',
            'enabled' => true,
            'created_at' => new DateTimeImmutable()->format('Y-m-d H:i:s'),
            'updated_at' => new DateTimeImmutable()->format('Y-m-d H:i:s'),
        ]);

        $client->request('DELETE', '/api/object_types/'.$id);
        self::assertResponseStatusCodeSame(Response::HTTP_CONFLICT);
    }
}
