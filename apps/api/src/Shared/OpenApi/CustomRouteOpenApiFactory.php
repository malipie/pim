<?php

declare(strict_types=1);

namespace App\Shared\OpenApi;

use ApiPlatform\OpenApi\Factory\OpenApiFactoryInterface;
use ApiPlatform\OpenApi\Model\Operation;
use ApiPlatform\OpenApi\Model\Parameter;
use ApiPlatform\OpenApi\Model\PathItem;
use ApiPlatform\OpenApi\Model\Paths;
use ApiPlatform\OpenApi\Model\Response;
use ApiPlatform\OpenApi\OpenApi;
use Symfony\Component\Routing\Route;
use Symfony\Component\Routing\RouterInterface;
use Throwable;

/**
 * AUD-043 / AUD-054 (#1600) — surface the custom `#[Route]` controller layer
 * in the exported OpenAPI document.
 *
 * The native API Platform factory only documents resource-backed operations,
 * so the exported spec listed 31 paths while the router exposes ~228 custom
 * `/api/*` routes (auth, MFA, password reset, invitations, bulk-edit, export,
 * import, assets, super-admin tenant ops, RBAC). Integrators rendering the
 * spec (Swagger UI, code generators, audit tooling) had no machine-readable
 * description of that surface at all.
 *
 * This decorator walks the router collection once per export and adds a
 * minimal-but-valid `Operation` for every custom `/api/*` route the inner
 * factory did not already produce. Each synthesised operation carries an
 * `x-pim-source: custom-route` extension so consumers can tell hand-written
 * controllers apart from resource-backed CRUD.
 *
 * Determinism is load-bearing for the `docs/api-spec/v0.json` drift gate:
 * routes are collected into a path-keyed map, the paths are `ksort`-ed, and
 * HTTP methods are emitted in a fixed order before any path is added. A
 * single malformed route can never break the whole export — operation
 * construction is wrapped per route and a failing route is skipped.
 *
 * It composes with {@see \App\Identity\OpenApi\PermissionOpenApiFactory}
 * (decoration_priority 0): this factory runs at priority 10, lifting custom
 * paths into the document first, after which the permission decorator still
 * annotates the resource-backed operations with `x-cortex-permission`.
 *
 * `App\Shared\*` is the lowest architectural layer — this class depends only
 * on the framework router and the API Platform OpenAPI models, never on a
 * bounded context.
 */
final readonly class CustomRouteOpenApiFactory implements OpenApiFactoryInterface
{
    /**
     * Emission order for HTTP methods on a path — keeps the spec diff stable
     * regardless of the order routes are declared in.
     *
     * @var list<string>
     */
    private const array METHOD_ORDER = ['GET', 'POST', 'PUT', 'PATCH', 'DELETE', 'OPTIONS', 'HEAD'];

    /**
     * Path prefixes that are internal plumbing rather than a public surface:
     * test-only routes, the docs UI itself, GraphQL, JSON-LD contexts and the
     * RFC 7807 error documents. They must never leak into the spec.
     *
     * @var list<string>
     */
    private const array EXCLUDED_PREFIXES = [
        '/api/_test/',
        '/api/docs',
        '/api/graphql',
        '/api/contexts',
        '/api/errors',
        '/api/validation_errors',
        '/api/.well-known',
    ];

    public function __construct(
        private OpenApiFactoryInterface $decorated,
        private RouterInterface $router,
    ) {
    }

    public function __invoke(array $context = []): OpenApi
    {
        $openApi = ($this->decorated)($context);
        $paths = $openApi->getPaths();

        $byPath = $this->collectCustomRoutes($paths);
        if ([] === $byPath) {
            return $openApi;
        }

        // Deterministic path order — the v0.json drift gate depends on it.
        ksort($byPath);

        foreach ($byPath as $path => $methods) {
            try {
                $pathItem = $this->buildPathItem($path, $methods);
            } catch (Throwable) {
                // A single malformed route must not break the whole export.
                continue;
            }
            $paths->addPath($path, $pathItem);
        }

        return $openApi;
    }

    /**
     * Walk the router and bucket every documentable custom route by path and
     * upper-cased HTTP method.
     *
     * @return array<string, array<string, Route>> [path][METHOD] => Route
     */
    private function collectCustomRoutes(Paths $existing): array
    {
        $byPath = [];

        foreach ($this->router->getRouteCollection() as $route) {
            try {
                if (!$this->isDocumentableCustomRoute($route, $existing)) {
                    continue;
                }

                $path = $route->getPath();
                foreach ($this->httpMethodsFor($route) as $method) {
                    // First declaration wins for a given (path, method) pair.
                    $byPath[$path][$method] ??= $route;
                }
            } catch (Throwable) {
                continue;
            }
        }

        return $byPath;
    }

    private function isDocumentableCustomRoute(Route $route, Paths $existing): bool
    {
        $path = $route->getPath();

        if (!str_starts_with($path, '/api/')) {
            return false;
        }

        // Skip API Platform–managed routes — the inner factory owns those.
        $defaults = $route->getDefaults();
        if (null !== ($defaults['_api_resource_class'] ?? null)) {
            return false;
        }
        if (\array_key_exists('_api_respond', $defaults) || \array_key_exists('_api_operation_name', $defaults)) {
            return false;
        }

        // Never overwrite a path the inner factory already documented.
        if (null !== $existing->getPath($path)) {
            return false;
        }

        foreach (self::EXCLUDED_PREFIXES as $prefix) {
            if (str_starts_with($path, $prefix)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Resolve the HTTP methods a route answers, in canonical emission order.
     * A route with no explicit methods answers every verb; we document the
     * two that carry a body contract worth describing (GET + POST) and note
     * the lack of a restriction in the operation description.
     *
     * @return list<string>
     */
    private function httpMethodsFor(Route $route): array
    {
        $declared = array_map('strtoupper', $route->getMethods());

        if ([] === $declared) {
            return ['GET', 'POST'];
        }

        $ordered = [];
        foreach (self::METHOD_ORDER as $method) {
            if (\in_array($method, $declared, true)) {
                $ordered[] = $method;
            }
        }

        // Preserve any non-standard verbs deterministically at the tail.
        foreach ($declared as $method) {
            if (!\in_array($method, $ordered, true)) {
                $ordered[] = $method;
            }
        }

        return $ordered;
    }

    /**
     * @param array<string, Route> $methods METHOD => Route
     */
    private function buildPathItem(string $path, array $methods): PathItem
    {
        $tag = $this->tagFor($path);
        $parameters = $this->pathParametersFor($path);
        $pathItem = new PathItem();

        foreach (self::METHOD_ORDER as $method) {
            if (!isset($methods[$method])) {
                continue;
            }
            $operation = $this->buildOperation($methods[$method], $method, $tag, $parameters);
            $pathItem = $this->withOperation($pathItem, $method, $operation);
        }

        return $pathItem;
    }

    /**
     * @param list<Parameter> $parameters
     */
    private function buildOperation(Route $route, string $method, string $tag, array $parameters): Operation
    {
        $routeName = $this->routeName($route);
        $unrestricted = [] === $route->getMethods();

        $summary = $this->humanize($routeName);
        $description = $unrestricted
            ? 'Custom controller route (no HTTP method restriction declared).'
            : 'Custom controller route.';

        return new Operation(
            operationId: $this->operationId($routeName, $method),
            tags: [$tag],
            responses: [
                '200' => new Response(description: 'Success'),
                '401' => new Response(description: 'Unauthorized'),
            ],
            summary: $summary,
            description: $description,
            parameters: $parameters,
            // Match the security scheme the inner factory already declares in
            // components.securitySchemes (JWT bearer); referencing an undefined
            // scheme would dangle. The ApiKey alternative is covered by the
            // document-level `security` requirement.
            security: [['JWT' => []]],
            extensionProperties: ['x-pim-source' => 'custom-route'],
        );
    }

    private function withOperation(PathItem $pathItem, string $method, Operation $operation): PathItem
    {
        return match ($method) {
            'GET' => $pathItem->withGet($operation),
            'POST' => $pathItem->withPost($operation),
            'PUT' => $pathItem->withPut($operation),
            'PATCH' => $pathItem->withPatch($operation),
            'DELETE' => $pathItem->withDelete($operation),
            'OPTIONS' => $pathItem->withOptions($operation),
            'HEAD' => $pathItem->withHead($operation),
            default => $pathItem,
        };
    }

    /**
     * The tag is the second path segment (`/api/<tag>/…`) so the spec groups
     * routes by feature area (auth, admin, assets, exports, …). Falls back to
     * `custom` when there is no second segment.
     */
    private function tagFor(string $path): string
    {
        $segments = array_values(array_filter(explode('/', $path), static fn (string $s): bool => '' !== $s));

        // segments[0] === 'api'; the feature area is the next segment.
        $tag = $segments[1] ?? '';
        if ('' === $tag || str_starts_with($tag, '{')) {
            return 'custom';
        }

        return $tag;
    }

    /**
     * @return list<Parameter>
     */
    private function pathParametersFor(string $path): array
    {
        preg_match_all('/\{(\w+)\}/', $path, $matches);

        $parameters = [];
        foreach ($matches[1] as $name) {
            $parameters[] = new Parameter(
                name: $name,
                in: 'path',
                required: true,
                schema: ['type' => 'string'],
            );
        }

        return $parameters;
    }

    private function routeName(Route $route): string
    {
        $name = $route->getDefault('_route');
        if (\is_string($name) && '' !== $name) {
            return $name;
        }

        // Fallback: derive a stable token from the path when the route was
        // matched without its name in defaults.
        return trim(preg_replace('/[^a-zA-Z0-9]+/', '_', $route->getPath()) ?? '', '_');
    }

    private function operationId(string $routeName, string $method): string
    {
        return strtolower($routeName.'_'.$method);
    }

    private function humanize(string $routeName): string
    {
        $words = preg_replace('/[_-]+/', ' ', $routeName) ?? $routeName;

        return ucfirst(trim($words));
    }
}
