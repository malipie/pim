<?php

declare(strict_types=1);

namespace App\Tests\Api\Catalog;

use App\Catalog\Domain\ObjectKind;
use PHPUnit\Framework\Attributes\Test;

use const JSON_THROW_ON_ERROR;

/**
 * #1350 — the global Attribute.isRequired flag is enforced on the object
 * write path: explicitly emptying a required attribute is rejected 422.
 * Codes absent from the payload are untouched (partial PATCH / imports of
 * legacy dirty records stay writable); the admin form enforces the
 * full-state rule client-side.
 */
final class RequiredAttributeValidationApiTest extends CatalogApiTestCase
{
    #[Test]
    public function emptyingARequiredAttributeIsRejected(): void
    {
        $client = $this->authenticatedClient();
        $productOt = $this->objectTypeIdFor(ObjectKind::Product);

        // Required text attribute attached to the Product ObjectType.
        $attrResponse = $client->request('POST', '/api/attributes', [
            'headers' => ['content-type' => 'application/ld+json', 'accept' => 'application/ld+json'],
            'body' => json_encode([
                'code' => 'req_val_test',
                'type' => 'text',
                'label' => ['pl' => 'Wymagany', 'en' => 'Required'],
                'required' => true,
            ], JSON_THROW_ON_ERROR),
        ]);
        self::assertResponseStatusCodeSame(201);
        $attrId = $attrResponse->toArray()['id'];
        \assert(\is_string($attrId));

        $client->request('POST', '/api/object_types/'.$productOt.'/attributes/'.$attrId);
        self::assertResponseStatusCodeSame(204);

        // Creating WITH a value → 201.
        $createResponse = $client->request('POST', '/api/products', [
            'headers' => ['content-type' => 'application/ld+json'],
            'body' => json_encode([
                'code' => 'REQ-OK',
                'objectTypeId' => $productOt,
                'attributes' => ['name' => 'Req ok', 'req_val_test' => 'filled'],
            ], JSON_THROW_ON_ERROR),
        ]);
        self::assertResponseStatusCodeSame(201);
        $productId = $createResponse->toArray()['id'];
        \assert(\is_string($productId));

        // Explicitly emptying the required attribute → 422 with the code
        // in the Problem Details `detail`.
        $client->request('PATCH', '/api/products/'.$productId, [
            'headers' => [
                'content-type' => 'application/merge-patch+json',
                'accept' => 'application/json',
            ],
            'body' => json_encode([
                'attributes' => ['req_val_test' => ''],
            ], JSON_THROW_ON_ERROR),
        ]);
        self::assertResponseStatusCodeSame(422);

        // Null is just as empty.
        $client->request('PATCH', '/api/products/'.$productId, [
            'headers' => [
                'content-type' => 'application/merge-patch+json',
                'accept' => 'application/json',
            ],
            'body' => json_encode([
                'attributes' => ['req_val_test' => null],
            ], JSON_THROW_ON_ERROR),
        ]);
        self::assertResponseStatusCodeSame(422);

        // A partial PATCH that does NOT touch the required code stays valid
        // (legacy dirty records are enforced on their next full-form save,
        // not on unrelated writes).
        $client->request('PATCH', '/api/products/'.$productId, [
            'headers' => [
                'content-type' => 'application/merge-patch+json',
                'accept' => 'application/json',
            ],
            'body' => json_encode([
                'attributes' => ['name' => 'Renamed'],
            ], JSON_THROW_ON_ERROR),
        ]);
        self::assertResponseStatusCodeSame(200);

        // Overwriting with a fresh value works.
        $client->request('PATCH', '/api/products/'.$productId, [
            'headers' => [
                'content-type' => 'application/merge-patch+json',
                'accept' => 'application/json',
            ],
            'body' => json_encode([
                'attributes' => ['req_val_test' => 'refilled'],
            ], JSON_THROW_ON_ERROR),
        ]);
        self::assertResponseStatusCodeSame(200);
    }

    #[Test]
    public function effectiveGroupsShipTheRequiredFlag(): void
    {
        $client = $this->authenticatedClient();
        $productOt = $this->objectTypeIdFor(ObjectKind::Product);

        $attrResponse = $client->request('POST', '/api/attributes', [
            'headers' => ['content-type' => 'application/ld+json', 'accept' => 'application/ld+json'],
            'body' => json_encode([
                'code' => 'req_flag_test',
                'type' => 'text',
                'label' => ['pl' => 'Flaga', 'en' => 'Flag'],
                'required' => true,
            ], JSON_THROW_ON_ERROR),
        ]);
        self::assertResponseStatusCodeSame(201);
        $attrId = $attrResponse->toArray()['id'];
        \assert(\is_string($attrId));

        $client->request('POST', '/api/object_types/'.$productOt.'/attributes/'.$attrId);
        self::assertResponseStatusCodeSame(204);

        $createResponse = $client->request('POST', '/api/products', [
            'headers' => ['content-type' => 'application/ld+json'],
            'body' => json_encode([
                'code' => 'REQ-FLAG',
                'objectTypeId' => $productOt,
                'attributes' => ['name' => 'Flag carrier', 'req_flag_test' => 'x'],
            ], JSON_THROW_ON_ERROR),
        ]);
        self::assertResponseStatusCodeSame(201);
        $productId = $createResponse->toArray()['id'];
        \assert(\is_string($productId));

        $groupsResponse = $client->request(
            'GET',
            '/api/objects/'.$productId.'/effective-attribute-groups',
            ['headers' => ['accept' => 'application/json']],
        );
        self::assertResponseStatusCodeSame(200);
        $groups = $groupsResponse->toArray()['groups'];
        \assert(\is_array($groups));

        $flag = null;
        foreach ($groups as $group) {
            \assert(\is_array($group) && \is_array($group['attributes']));
            foreach ($group['attributes'] as $attribute) {
                \assert(\is_array($attribute));
                if ('req_flag_test' === $attribute['code']) {
                    $flag = $attribute['is_required'];
                }
            }
        }
        self::assertTrue($flag, 'effective-attribute-groups must serialize is_required=true');
    }
}
