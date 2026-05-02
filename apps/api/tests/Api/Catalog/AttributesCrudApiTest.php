<?php

declare(strict_types=1);

namespace App\Tests\Api\Catalog;

use App\Catalog\Application\BuiltInSystemAttributesSeeder;
use App\Catalog\Domain\AttributeType;
use App\Catalog\Domain\Entity\Attribute;
use App\Catalog\Domain\Repository\AttributeRepositoryInterface;
use App\Shared\Application\TenantContext;
use App\Shared\Domain\Tenant;
use PHPUnit\Framework\Attributes\Test;

/**
 * VIEW-02 (#374) — Attribute CRUD ApiResource smoke + invariants.
 *
 * Mirrors AttributeGroupsApiTest (#260) for the new POST/PATCH/DELETE
 * operations. Asserts:
 *   - POST creates a tenant-scoped attribute with proper validation.
 *   - PATCH partial update touches only provided fields.
 *   - DELETE removes a custom attribute; system attributes (`is_system=true`)
 *     are 422 (UI-08.3 immutability).
 *   - Code uniqueness within tenant is enforced (409).
 *   - Code regex (snake_case) is validated (422).
 */
final class AttributesCrudApiTest extends CatalogApiTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $tenant = $this->em()->getRepository(Tenant::class)->findOneBy(['code' => self::TENANT_CODE]);
        \assert($tenant instanceof Tenant);
        // Seeder creates `created_at`/`updated_at`/etc. system attrs we
        // exercise the delete-protection guard against.
        self::getContainer()->get(BuiltInSystemAttributesSeeder::class)->seed($tenant);
    }

    #[Test]
    public function postCreatesAttribute(): void
    {
        $client = $this->authenticatedClient();
        $response = $client->request('POST', '/api/attributes', [
            'json' => [
                'code' => 'warranty_months',
                'label' => ['en' => 'Warranty (months)', 'pl' => 'Gwarancja (msc)'],
                'type' => 'number',
                'localizable' => false,
                'required' => true,
                'validationRules' => ['min' => 0, 'max' => 120],
                'position' => 10,
            ],
        ]);

        self::assertSame(201, $response->getStatusCode());
        $payload = $response->toArray();
        self::assertSame('warranty_months', $payload['code']);
        self::assertSame('number', $payload['type']);
        self::assertTrue($payload['required']);
        self::assertSame(10, $payload['position']);
    }

    #[Test]
    public function postRejectsNonSnakeCaseCode(): void
    {
        $client = $this->authenticatedClient();
        $response = $client->request('POST', '/api/attributes', [
            'json' => [
                'code' => 'WarrantyMonths',
                'label' => ['en' => 'Warranty'],
                'type' => 'number',
            ],
        ]);

        self::assertSame(422, $response->getStatusCode());
    }

    #[Test]
    public function postRejectsDuplicateCodeForSameTenant(): void
    {
        $this->seedAttribute('brand', AttributeType::Text);

        $client = $this->authenticatedClient();
        $response = $client->request('POST', '/api/attributes', [
            'json' => [
                'code' => 'brand',
                'label' => ['en' => 'Brand again'],
                'type' => 'text',
            ],
        ]);

        self::assertSame(409, $response->getStatusCode());
    }

    #[Test]
    public function patchUpdatesProvidedFieldsOnly(): void
    {
        $id = $this->seedAttribute('brand', AttributeType::Text);

        $client = $this->authenticatedClient();
        $response = $client->request('PATCH', '/api/attributes/'.$id->toRfc4122(), [
            'headers' => ['content-type' => 'application/merge-patch+json'],
            'json' => [
                'label' => ['en' => 'Brand renamed', 'pl' => 'Marka'],
                'localizable' => true,
            ],
        ]);

        self::assertSame(200, $response->getStatusCode());
        $payload = $response->toArray();
        self::assertSame(['en' => 'Brand renamed', 'pl' => 'Marka'], $payload['label']);
        self::assertTrue($payload['localizable']);
    }

    #[Test]
    public function deleteRemovesCustomAttribute(): void
    {
        $id = $this->seedAttribute('warranty_months', AttributeType::Number);

        $client = $this->authenticatedClient();
        $response = $client->request('DELETE', '/api/attributes/'.$id->toRfc4122());

        self::assertSame(204, $response->getStatusCode());

        $repo = self::getContainer()->get(AttributeRepositoryInterface::class);
        self::assertNull($repo->findById($id));
    }

    #[Test]
    public function deleteRejectsSystemAttributeWith422(): void
    {
        // BuiltInSystemAttributesSeeder seeds `created_at` as a system
        // attribute — picking it up by code ensures we hit the guard.
        $repo = self::getContainer()->get(AttributeRepositoryInterface::class);
        $tenant = $this->em()->getRepository(Tenant::class)->findOneBy(['code' => self::TENANT_CODE]);
        \assert($tenant instanceof Tenant);
        $system = $repo->findByCode('created_at', $tenant);
        \assert($system instanceof Attribute);

        $client = $this->authenticatedClient();
        $response = $client->request('DELETE', '/api/attributes/'.$system->getId()->toRfc4122());

        self::assertSame(422, $response->getStatusCode());
    }

    private function seedAttribute(string $code, AttributeType $type): \Symfony\Component\Uid\Uuid
    {
        $ctx = self::getContainer()->get(TenantContext::class);
        $tenant = $this->em()->getRepository(Tenant::class)->findOneBy(['code' => self::TENANT_CODE]);
        \assert($tenant instanceof Tenant);
        $ctx->set($tenant);

        $attribute = new Attribute($code, ['en' => $code], $type);
        $repo = self::getContainer()->get(AttributeRepositoryInterface::class);
        $repo->save($attribute);

        return $attribute->getId();
    }
}
