<?php

declare(strict_types=1);

namespace App\Tests\Api\Catalog;

use App\Catalog\Domain\ObjectKind;
use App\Catalog\Domain\Repository\ObjectTypeRepositoryInterface;
use App\Shared\Domain\Tenant;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Component\HttpFoundation\Response;

/**
 * fix(catalog) #949 — POST /api/attributes with type=relation honours
 * `relationCardinality` + `relationTargetObjectTypeIds` from the create
 * flows (`/modeling/attributes/new`, `CreateAttributeInGroupDialog`,
 * `CreateAttributeForObjectTypeDialog`). Regression cover for the bug
 * where the FE wasn't sending these fields and the backend validator
 * always 422'd.
 */
final class AttributeRelationCreateApiTest extends CatalogApiTestCase
{
    #[Test]
    public function createWithRelationConfigSucceeds(): void
    {
        $productId = $this->productObjectTypeId();
        $client = $this->authenticatedClient();

        $client->request('POST', '/api/attributes', [
            'json' => [
                'code' => 'relation_ok',
                'type' => 'relation',
                'label' => ['pl' => 'Powiązany', 'en' => 'Related'],
                'relationCardinality' => 'many',
                'relationTargetObjectTypeIds' => [$productId],
                'relationAdvanced' => false,
            ],
            'headers' => ['content-type' => 'application/ld+json'],
        ]);
        self::assertResponseStatusCodeSame(Response::HTTP_CREATED);
    }

    #[Test]
    public function createRelationWithoutCardinalityReturns422(): void
    {
        $productId = $this->productObjectTypeId();
        $client = $this->authenticatedClient();

        $client->request('POST', '/api/attributes', [
            'json' => [
                'code' => 'relation_missing_card',
                'type' => 'relation',
                'label' => ['pl' => 'Brak'],
                'relationTargetObjectTypeIds' => [$productId],
            ],
            'headers' => ['content-type' => 'application/ld+json'],
        ]);
        self::assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);
    }

    #[Test]
    public function createRelationWithEmptyTargetsIsAllowedAtDataLayer(): void
    {
        // RelationAttributeConfigValidator allows an empty target list at
        // the persistence layer — the UI gates it (submit guard). Test
        // documents the contract so a future tightening doesn't surprise
        // the FE.
        $client = $this->authenticatedClient();

        $client->request('POST', '/api/attributes', [
            'json' => [
                'code' => 'relation_empty_targets',
                'type' => 'relation',
                'label' => ['pl' => 'Brak'],
                'relationCardinality' => 'many',
                'relationTargetObjectTypeIds' => [],
            ],
            'headers' => ['content-type' => 'application/ld+json'],
        ]);
        self::assertResponseStatusCodeSame(Response::HTTP_CREATED);
    }

    #[Test]
    public function createNonRelationWithStaleRelationFieldsRejects422(): void
    {
        // AC-6 — guards the backend contract documented in
        // `RelationAttributeConfigValidator::validateAndNormalise`: a
        // non-relation type with non-default relation fields → 422.
        // Frontend MUST strip these fields client-side when the operator
        // toggles type away from `relation` (the create flows added in
        // this PR do exactly that).
        $client = $this->authenticatedClient();

        $client->request('POST', '/api/attributes', [
            'json' => [
                'code' => 'text_after_relation',
                'type' => 'text',
                'label' => ['pl' => 'Tekstowy'],
                'relationCardinality' => 'many',
                'relationTargetObjectTypeIds' => [$this->productObjectTypeId()],
            ],
            'headers' => ['content-type' => 'application/ld+json'],
        ]);
        self::assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);
    }

    private function productObjectTypeId(): string
    {
        $tenant = $this->em()->getRepository(Tenant::class)->findOneBy(['code' => self::TENANT_CODE]);
        \assert($tenant instanceof Tenant);
        $type = self::getContainer()->get(ObjectTypeRepositoryInterface::class)
            ->findBuiltInByKind(ObjectKind::Product, $tenant);
        \assert(null !== $type);

        return $type->getId()->toRfc4122();
    }
}
