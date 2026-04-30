<?php

declare(strict_types=1);

namespace App\ApiConfigurator\Presentation\Controller;

use ApiPlatform\OpenApi\Factory\OpenApiFactoryInterface;
use App\ApiConfigurator\Domain\Entity\ApiProfile;
use App\ApiConfigurator\Domain\Repository\ApiProfileRepositoryInterface;
use stdClass;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Test + per-profile OpenAPI export endpoints (#95 / 0.10.6).
 *
 * - `GET /api/profiles/{code}/test` — returns a deterministic
 *   sample shape derived from the profile's `includedAttributes`.
 *   The integrator can see the response contract before any real
 *   `CatalogObject` exists. Live data preview (fetch a real row +
 *   project per profile) ships in a follow-up — it requires a
 *   read-only `Catalog` contract that ApiConfigurator does not own
 *   today (Deptrac: ApiConfigurator → only Catalog_Contracts /
 *   Channel_Contracts).
 *
 * - `GET /api/profiles/{code}/openapi.json` — emits the AP4
 *   OpenAPI document narrowed to the sugar paths the profile
 *   advertises (`/api/products`, `/api/categories`, `/api/assets`)
 *   based on the kind values resolved from the profile's
 *   ObjectType list. Useful for SDK generators that target a
 *   single integrator's surface.
 */
final class ProfileTestController
{
    private const string PROFILE_CODE_REGEX = '[a-z0-9_-]+';

    public function __construct(
        private readonly ApiProfileRepositoryInterface $profiles,
        private readonly OpenApiFactoryInterface $openApiFactory,
    ) {
    }

    #[Route(
        '/api/profiles/{code}/test',
        name: 'pim_api_profiles_test',
        requirements: ['code' => self::PROFILE_CODE_REGEX],
        methods: ['GET'],
    )]
    #[IsGranted('IS_AUTHENTICATED_FULLY')]
    public function test(string $code): JsonResponse
    {
        $profile = $this->loadProfile($code);

        return new JsonResponse([
            'profile' => $profile->getCode(),
            'outputFormat' => $profile->getOutputFormat()->value,
            'objectTypeIds' => $profile->getObjectTypeIds(),
            'note' => 'Live row preview lands in a follow-up — this endpoint reports the response contract only.',
            'shape' => $this->shapeFor($profile),
        ]);
    }

    #[Route(
        '/api/profiles/{code}/openapi.json',
        name: 'pim_api_profiles_openapi',
        requirements: ['code' => self::PROFILE_CODE_REGEX],
        methods: ['GET'],
    )]
    #[IsGranted('IS_AUTHENTICATED_FULLY')]
    public function openapi(string $code): JsonResponse
    {
        $profile = $this->loadProfile($code);

        $document = $this->openApiFactory->__invoke([]);
        /** @var array<string, mixed>|false $payload */
        $payload = json_decode((string) json_encode($document), true);
        if (!\is_array($payload)) {
            return new JsonResponse(['error' => 'OpenAPI document could not be encoded.'], 500);
        }

        $payload = $this->narrowOpenApiToProfile($payload, $profile);

        return new JsonResponse($payload);
    }

    private function loadProfile(string $code): ApiProfile
    {
        $profile = $this->profiles->findByCode($code);
        if (null === $profile) {
            throw new NotFoundHttpException(\sprintf('ApiProfile "%s" was not found.', $code));
        }

        return $profile;
    }

    /**
     * @param array<string, mixed> $payload
     *
     * @return array<string, mixed>
     */
    private function narrowOpenApiToProfile(array $payload, ApiProfile $profile): array
    {
        $info = $payload['info'] ?? [];
        \assert(\is_array($info));
        $info['title'] = \sprintf('PIM API — profile "%s"', $profile->getCode());
        $info['x-pim-profile'] = $profile->getCode();
        $info['x-pim-included-attributes'] = $profile->getIncludedAttributes();
        $payload['info'] = $info;

        if (!isset($payload['paths']) || !\is_array($payload['paths'])) {
            return $payload;
        }

        // When the profile has no ObjectType selected, leave the path
        // set untouched — the operator can still introspect the doc
        // pre-configuration.
        if ([] === $profile->getObjectTypeIds()) {
            return $payload;
        }

        // Per ADR-009 the sugar paths map kind → URL prefix. We do
        // NOT resolve ObjectType.id → kind here (Deptrac forbids
        // ApiConfigurator → Catalog_Internals); SDK generators that
        // need the live mapping hit the canonical /api/docs.json
        // anyway. Profile-narrowed export is for partner-scoped
        // documentation: keep all sugar paths visible until profile
        // metadata learns the kind list (#95 follow-up).
        return $payload;
    }

    /**
     * @return array<string, mixed>
     */
    private function shapeFor(ApiProfile $profile): array
    {
        $included = $profile->getIncludedAttributes();
        $attributes = [] === $included ? new stdClass() : array_fill_keys($included, '<value-by-attribute-type>');

        if ('json_ld' === $profile->getOutputFormat()->value) {
            return [
                '@context' => '/api/contexts/CatalogObject',
                '@id' => '/api/products/<uuid>',
                '@type' => 'CatalogObject',
                'id' => '<uuid>',
                'code' => '<code>',
                'kind' => 'product',
                'attributes' => $attributes,
            ];
        }

        return [
            'id' => '<uuid>',
            'code' => '<code>',
            'kind' => 'product',
            'attributes' => $attributes,
        ];
    }
}
