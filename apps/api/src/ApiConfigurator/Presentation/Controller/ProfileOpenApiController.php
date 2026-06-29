<?php

declare(strict_types=1);

namespace App\ApiConfigurator\Presentation\Controller;

use ApiPlatform\OpenApi\Factory\OpenApiFactoryInterface;
use App\ApiConfigurator\Domain\Entity\ApiProfile;
use App\ApiConfigurator\Domain\Repository\ApiProfileRepositoryInterface;
use App\Identity\Contracts\Attribute\RequiresPermission;
use InvalidArgumentException;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\Uid\Uuid;

/**
 * APIC-P4-03 (ADR-0020/0022) — per-profile OpenAPI export.
 *
 * `GET /api/docs/profile/{id}.jsonopenapi` returns the canonical OpenAPI
 * document narrowed to one integrator profile: only the catalog *data* paths a
 * profile exposes (products / categories / assets / objects — auth, admin,
 * import/export, configurator + settings surfaces are dropped), tagged with the
 * profile's scope (`x-pim-object-type-ids` / `x-pim-included-attributes` /
 * `x-pim-filters`) so an SDK generator targets a single partner's surface.
 *
 * The profile is resolved tenant-scoped (Postgres RLS) — a cross-tenant or
 * unknown id is a 404. Lives under `/api/docs/` (API Platform owns
 * `/api/api_profiles/...`); the `api_profile.read` permission gates it.
 *
 * Per-ObjectType path narrowing (id → kind → exact sugar path) is a follow-up:
 * it needs a Catalog Contracts kind-resolution seam ApiConfigurator does not own
 * (Deptrac: Catalog_Contracts only). Until then the scope is advertised via the
 * `x-pim-*` metadata while all catalog data paths stay visible.
 */
final class ProfileOpenApiController
{
    /** Catalog data-resource path prefixes a profile can expose (ADR-009 sugar paths). */
    private const array DATA_PREFIXES = ['/api/products', '/api/categories', '/api/assets', '/api/objects'];

    public function __construct(
        private readonly ApiProfileRepositoryInterface $profiles,
        private readonly OpenApiFactoryInterface $openApiFactory,
    ) {
    }

    #[Route(
        '/api/docs/profile/{id}.jsonopenapi',
        name: 'pim_api_profile_openapi',
        requirements: ['id' => '[0-9a-fA-F]{8}-[0-9a-fA-F]{4}-[0-9a-fA-F]{4}-[0-9a-fA-F]{4}-[0-9a-fA-F]{12}'],
        methods: ['GET'],
    )]
    #[IsGranted('IS_AUTHENTICATED_FULLY')]
    #[RequiresPermission(module: 'api_profile', action: 'read')]
    public function __invoke(string $id): JsonResponse
    {
        try {
            $profileId = Uuid::fromString($id);
        } catch (InvalidArgumentException) {
            throw new NotFoundHttpException(\sprintf('ApiProfile "%s" was not found.', $id));
        }

        $profile = $this->profiles->findById($profileId);
        if (!$profile instanceof ApiProfile) {
            throw new NotFoundHttpException(\sprintf('ApiProfile "%s" was not found.', $id));
        }

        /** @var array<string, mixed>|false $payload */
        $payload = json_decode((string) json_encode($this->openApiFactory->__invoke([])), true);
        if (!\is_array($payload)) {
            return new JsonResponse(['error' => 'OpenAPI document could not be encoded.'], 500);
        }

        return new JsonResponse($this->narrow($payload, $profile));
    }

    /**
     * @param array<string, mixed> $payload
     *
     * @return array<string, mixed>
     */
    private function narrow(array $payload, ApiProfile $profile): array
    {
        $info = \is_array($payload['info'] ?? null) ? $payload['info'] : [];
        $info['title'] = \sprintf('PIM API — profile "%s"', $profile->getCode());
        $info['x-pim-profile'] = $profile->getCode();
        $info['x-pim-object-type-ids'] = $profile->getObjectTypeIds();
        $info['x-pim-included-attributes'] = $profile->getIncludedAttributes();
        $info['x-pim-filters'] = $profile->getFilters();
        $payload['info'] = $info;

        if (\is_array($payload['paths'] ?? null)) {
            $payload['paths'] = $this->keepDataPaths($payload['paths']);
        }

        return $payload;
    }

    /**
     * @param array<mixed, mixed> $paths
     *
     * @return array<string, mixed>
     */
    private function keepDataPaths(array $paths): array
    {
        $kept = [];
        foreach ($paths as $path => $item) {
            if (!\is_string($path)) {
                continue;
            }
            foreach (self::DATA_PREFIXES as $prefix) {
                if ($path === $prefix || str_starts_with($path, $prefix.'/') || str_starts_with($path, $prefix.'.')) {
                    $kept[$path] = $item;
                    break;
                }
            }
        }

        return $kept;
    }
}
