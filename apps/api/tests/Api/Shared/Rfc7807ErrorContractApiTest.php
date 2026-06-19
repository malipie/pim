<?php

declare(strict_types=1);

namespace App\Tests\Api\Shared;

use App\Tests\Api\Catalog\CatalogApiTestCase;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Component\HttpFoundation\Response;

use const JSON_THROW_ON_ERROR;

/**
 * W2-7 / AUD-042 — single RFC 7807 error contract for custom controllers.
 *
 * Before this ticket the ~157 custom `#[Route]` controllers (which throw
 * `BadRequestHttpException`/`ConflictHttpException`/… instead of flowing
 * through an API Platform resource) returned Symfony's FlattenException
 * shape: `type` pointing at RFC 2616, plus `class` (the exception FQCN —
 * an information leak) and, in debug, a full `trace`. Without an `Accept`
 * header they returned an HTML error page (routing-structure leak).
 *
 * API Platform native routes already emit clean RFC 7807
 * (`application/problem+json`, `type`/`title`/`status`/`detail`,
 * validation errors carrying `violations`). This suite pins both halves
 * of the contract:
 *   - custom routes are normalised to RFC 7807 with NO `class`/`trace`;
 *   - API Platform routes keep their RFC 7807 shape untouched;
 *   - API Platform validation keeps `violations`.
 *
 * Assertions check status + key presence/absence (not full detail
 * strings) so they hold in both debug and non-debug environments
 * (lessons: error `detail` differs debug vs prod).
 */
final class Rfc7807ErrorContractApiTest extends CatalogApiTestCase
{
    private const string PROBLEM_JSON = 'application/problem+json';

    /**
     * Custom controller throwing `BadRequestHttpException('code is required.')`.
     * Must be RFC 7807 `application/problem+json`, NOT the FlattenException
     * shape with `class`/`trace`.
     */
    #[Test]
    public function customControllerErrorIsRfc7807WithoutClassOrTrace(): void
    {
        $client = $this->authenticatedClient();

        $response = $client->request('POST', '/api/object_types', [
            'headers' => [
                'content-type' => 'application/json',
                'accept' => 'application/json',
            ],
            'body' => json_encode([], JSON_THROW_ON_ERROR),
        ]);

        self::assertResponseStatusCodeSame(Response::HTTP_BAD_REQUEST);

        $contentType = $response->getHeaders(false)['content-type'][0] ?? '';
        self::assertStringContainsString(
            self::PROBLEM_JSON,
            $contentType,
            'Custom controller error must be served as application/problem+json.',
        );

        $payload = $response->toArray(false);

        // RFC 7807 members present and correctly typed.
        self::assertArrayHasKey('type', $payload);
        self::assertArrayHasKey('title', $payload);
        self::assertArrayHasKey('status', $payload);
        self::assertArrayHasKey('detail', $payload);
        self::assertSame(Response::HTTP_BAD_REQUEST, $payload['status']);

        // `type` must NOT be the RFC 2616 sentinel the old FlattenException used.
        self::assertIsString($payload['type']);
        self::assertStringNotContainsString(
            'rfc2616',
            $payload['type'],
            'RFC 7807 `type` must reference an error document, not RFC 2616.',
        );

        // Information-leak fields must be gone.
        self::assertArrayNotHasKey('class', $payload, 'Exception FQCN must not leak.');
        self::assertArrayNotHasKey('trace', $payload, 'Stack trace must not leak.');
    }

    /**
     * A custom controller error WITHOUT an `Accept` header must still return
     * JSON problem details for `/api/*`, never an HTML error page.
     */
    #[Test]
    public function customControllerErrorWithoutAcceptHeaderStaysJson(): void
    {
        $client = $this->authenticatedClient();

        $response = $client->request('POST', '/api/object_types', [
            'headers' => ['content-type' => 'application/json'],
            'body' => json_encode([], JSON_THROW_ON_ERROR),
        ]);

        self::assertResponseStatusCodeSame(Response::HTTP_BAD_REQUEST);

        $contentType = $response->getHeaders(false)['content-type'][0] ?? '';
        self::assertStringContainsString(
            self::PROBLEM_JSON,
            $contentType,
            'A /api/* error must not fall back to an HTML error page.',
        );
        self::assertStringNotContainsString('text/html', $contentType);

        $payload = $response->toArray(false);
        self::assertArrayNotHasKey('class', $payload);
        self::assertArrayNotHasKey('trace', $payload);
    }

    /**
     * API Platform native route (Get item on a missing id) must keep its
     * own clean RFC 7807 response — the listener must not intercept it.
     */
    #[Test]
    public function apiPlatformRouteErrorStaysRfc7807(): void
    {
        $client = $this->authenticatedClient();

        $response = $client->request('GET', '/api/object_types/99999999-0000-0000-0000-000000000000', [
            'headers' => ['accept' => 'application/ld+json'],
        ]);

        self::assertResponseStatusCodeSame(Response::HTTP_NOT_FOUND);

        $payload = $response->toArray(false);
        self::assertArrayHasKey('type', $payload);
        self::assertArrayHasKey('title', $payload);
        self::assertArrayHasKey('status', $payload);
        self::assertSame(Response::HTTP_NOT_FOUND, $payload['status']);
        // API Platform's Hydra error keeps its JSON-LD context.
        self::assertArrayHasKey('@context', $payload, 'API Platform RFC 7807 (Hydra) must be untouched.');
        // API Platform never emits `class`; guard against the listener
        // accidentally re-adding it.
        self::assertArrayNotHasKey('class', $payload);
    }

    /**
     * API Platform validation error (422) must keep its `violations` array —
     * the listener must not flatten it into a plain problem document.
     */
    #[Test]
    public function apiPlatformValidationErrorKeepsViolations(): void
    {
        $client = $this->authenticatedClient();

        $response = $client->request('POST', '/api/objects', [
            'headers' => [
                'content-type' => 'application/ld+json',
                'accept' => 'application/ld+json',
            ],
            'body' => json_encode([], JSON_THROW_ON_ERROR),
        ]);

        self::assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);

        $payload = $response->toArray(false);
        self::assertSame(Response::HTTP_UNPROCESSABLE_ENTITY, $payload['status']);
        self::assertArrayHasKey('violations', $payload, 'Validation 422 must keep the violations array.');
        self::assertNotEmpty($payload['violations']);
    }
}
