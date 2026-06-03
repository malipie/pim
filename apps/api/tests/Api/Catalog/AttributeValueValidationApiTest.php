<?php

declare(strict_types=1);

namespace App\Tests\Api\Catalog;

use App\Catalog\Domain\ObjectKind;
use PHPUnit\Framework\Attributes\Test;

use const JSON_THROW_ON_ERROR;

/**
 * #1216 — per-type value validation (email/color/identifier) is enforced on
 * the object write path. Previously AttributeValueValidator was never invoked
 * on save, so an email attribute accepted any string ("dfsdfsdf").
 */
final class AttributeValueValidationApiTest extends CatalogApiTestCase
{
    #[Test]
    public function invalidEmailIsRejectedAndValidEmailAccepted(): void
    {
        $client = $this->authenticatedClient();
        $productOt = $this->objectTypeIdFor(ObjectKind::Product);

        // Email attribute attached to the Product ObjectType.
        $attrResponse = $client->request('POST', '/api/attributes', [
            'headers' => ['content-type' => 'application/ld+json', 'accept' => 'application/ld+json'],
            'body' => json_encode([
                'code' => 'email_val_test',
                'type' => 'email',
                'label' => ['pl' => 'Email', 'en' => 'Email'],
            ], JSON_THROW_ON_ERROR),
        ]);
        self::assertResponseStatusCodeSame(201);
        $attrId = $attrResponse->toArray()['id'];
        \assert(\is_string($attrId));

        $client->request('POST', '/api/object_types/'.$productOt.'/attributes/'.$attrId);
        self::assertResponseStatusCodeSame(204);

        // Invalid email → 422 (was silently accepted before #1216).
        $client->request('POST', '/api/products', [
            'headers' => ['content-type' => 'application/ld+json'],
            'body' => json_encode([
                'code' => 'EMAIL-BAD',
                'objectTypeId' => $productOt,
                'attributes' => ['name' => 'Bad', 'email_val_test' => 'dfsdfsdf'],
            ], JSON_THROW_ON_ERROR),
        ]);
        self::assertResponseStatusCodeSame(422);

        // Valid email → 201.
        $client->request('POST', '/api/products', [
            'headers' => ['content-type' => 'application/ld+json'],
            'body' => json_encode([
                'code' => 'EMAIL-OK',
                'objectTypeId' => $productOt,
                'attributes' => ['name' => 'Good', 'email_val_test' => 'a@b.com'],
            ], JSON_THROW_ON_ERROR),
        ]);
        self::assertResponseStatusCodeSame(201);
    }
}
