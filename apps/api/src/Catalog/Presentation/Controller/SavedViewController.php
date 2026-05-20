<?php

declare(strict_types=1);

namespace App\Catalog\Presentation\Controller;

use App\Catalog\Domain\Entity\SavedView;
use App\Identity\Domain\Attribute\RequiresPermission;
use App\Shared\Application\TenantContext;
use DateTimeInterface;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\Uid\Uuid;

/**
 * UI-02.7 (#297) — `SavedView` CRUD endpoints (tenant-shared in MVP).
 *
 * - `GET /api/saved-views?resource=products` — list tenant views.
 * - `POST /api/saved-views` — create. Auto-slug from name with
 *   collision suffix.
 * - `PATCH /api/saved-views/{id}` — partial update.
 * - `DELETE /api/saved-views/{id}` — delete a tenant view.
 *
 * Default flag enforcement: when a view is saved with `is_default=true`,
 * any other view of the same `(tenant, resource)` is cleared first so
 * only one default exists per resource.
 *
 * **MVP scope reduction:** per-user owner scoping (the `user_id`
 * column on the entity) is wired through the schema but NOT enforced
 * at the controller layer in this slice. Owner-only writes + system
 * view immutability + Faza 1 ADR-013 permissions land together in
 * the follow-up that introduces the cross-bundle `CurrentUserProvider`
 * contract (Catalog cannot depend on the Identity entity directly per
 * Deptrac rules).
 */
final class SavedViewController
{
    private const string UUID_REGEX = '[0-9a-fA-F]{8}-[0-9a-fA-F]{4}-[0-9a-fA-F]{4}-[0-9a-fA-F]{4}-[0-9a-fA-F]{12}';

    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly TenantContext $tenantContext,
    ) {
    }

    #[Route('/api/saved-views', name: 'pim_saved_views_list', methods: ['GET'])]
    #[IsGranted('IS_AUTHENTICATED_FULLY')]
    #[RequiresPermission(module: 'products', action: 'view')]
    public function list(Request $request): JsonResponse
    {
        $tenant = $this->tenantContext->get();
        if (null === $tenant) {
            throw new BadRequestHttpException('No tenant context.');
        }

        $resource = $request->query->getString('resource', 'products');

        /** @var list<SavedView> $views */
        $views = $this->em->getRepository(SavedView::class)
            ->createQueryBuilder('v')
            ->where('v.tenant = :tenant')
            ->andWhere('v.resource = :resource')
            ->setParameter('tenant', $tenant)
            ->setParameter('resource', $resource)
            ->orderBy('v.isDefault', 'DESC')
            ->addOrderBy('v.name', 'ASC')
            ->getQuery()
            ->getResult();

        return new JsonResponse([
            'views' => array_map(fn (SavedView $v): array => $this->serialize($v), $views),
        ]);
    }

    #[Route('/api/saved-views', name: 'pim_saved_views_create', methods: ['POST'])]
    #[IsGranted('IS_AUTHENTICATED_FULLY')]
    #[RequiresPermission(module: 'products', action: 'view')]
    public function create(Request $request): JsonResponse
    {
        $tenant = $this->tenantContext->get();
        if (null === $tenant) {
            throw new BadRequestHttpException('No tenant context.');
        }

        /** @var array<string, mixed> $body */
        $body = json_decode($request->getContent(), true) ?? [];

        $name = $body['name'] ?? null;
        if (!\is_string($name) || '' === trim($name)) {
            throw new BadRequestHttpException('name is required.');
        }
        $resource = $body['resource'] ?? 'products';
        if (!\is_string($resource) || '' === $resource) {
            $resource = 'products';
        }
        $config = $body['config'] ?? [];
        if (!\is_array($config)) {
            throw new BadRequestHttpException('config must be an object.');
        }
        /** @var array<string, mixed> $config */
        $isDefault = (bool) ($body['is_default'] ?? false);
        $description = $body['description'] ?? null;

        $slug = $this->generateUniqueSlug($name, $tenant->getId());

        $view = new SavedView(
            slug: $slug,
            name: trim($name),
            resource: $resource,
            config: $config,
        );
        if (\is_string($description)) {
            $view->changeDescription($description);
        }
        if ($isDefault) {
            $this->clearOtherDefaults($tenant->getId(), $resource);
            $view->markDefault(true);
        }

        $this->em->persist($view);
        $this->em->flush();

        return new JsonResponse($this->serialize($view), Response::HTTP_CREATED);
    }

    #[Route('/api/saved-views/{id}', name: 'pim_saved_views_patch', requirements: ['id' => self::UUID_REGEX], methods: ['PATCH'])]
    #[IsGranted('IS_AUTHENTICATED_FULLY')]
    #[RequiresPermission(module: 'products', action: 'view')]
    public function patch(string $id, Request $request): JsonResponse
    {
        $view = $this->mustFind($id);

        /** @var array<string, mixed> $body */
        $body = json_decode($request->getContent(), true) ?? [];

        if (\array_key_exists('name', $body) && \is_string($body['name'])) {
            $view->rename(trim($body['name']));
        }
        if (\array_key_exists('description', $body)) {
            $view->changeDescription(\is_string($body['description']) ? $body['description'] : null);
        }
        if (\array_key_exists('config', $body) && \is_array($body['config'])) {
            /** @var array<string, mixed> $cfg */
            $cfg = $body['config'];
            $view->updateConfig($cfg);
        }
        if (\array_key_exists('is_default', $body)) {
            $isDefault = (bool) $body['is_default'];
            if ($isDefault) {
                $tenant = $view->getTenant();
                if (null !== $tenant) {
                    $this->clearOtherDefaults($tenant->getId(), $view->getResource(), exceptId: $view->getId());
                }
            }
            $view->markDefault($isDefault);
        }

        $this->em->flush();

        return new JsonResponse($this->serialize($view));
    }

    #[Route('/api/saved-views/{id}', name: 'pim_saved_views_delete', requirements: ['id' => self::UUID_REGEX], methods: ['DELETE'])]
    #[IsGranted('IS_AUTHENTICATED_FULLY')]
    #[RequiresPermission(module: 'products', action: 'view')]
    public function delete(string $id): Response
    {
        $view = $this->mustFind($id);
        $this->em->remove($view);
        $this->em->flush();

        return new Response('', Response::HTTP_NO_CONTENT);
    }

    private function generateUniqueSlug(string $name, Uuid $tenantId): string
    {
        $base = $this->slugify($name);
        if ('' === $base) {
            $base = 'view';
        }
        $candidate = $base;
        $counter = 1;
        while (null !== $this->em->getRepository(SavedView::class)->findOneBy([
            'tenant' => $tenantId,
            'slug' => $candidate,
        ])) {
            ++$counter;
            $candidate = $base.'-'.$counter;
            if ($counter > 9999) {
                throw new BadRequestHttpException(\sprintf('Cannot allocate a slug for "%s".', $name));
            }
        }

        return $candidate;
    }

    private function slugify(string $name): string
    {
        $lower = strtolower($name);
        $ascii = preg_replace('/[^a-z0-9]+/u', '-', $lower) ?? '';

        return trim($ascii, '-');
    }

    private function clearOtherDefaults(Uuid $tenantId, string $resource, ?Uuid $exceptId = null): void
    {
        $qb = $this->em->getRepository(SavedView::class)->createQueryBuilder('v')
            ->update()
            ->set('v.isDefault', ':false')
            ->where('v.tenant = :tenant')
            ->andWhere('v.resource = :resource')
            ->andWhere('v.isDefault = :true')
            ->setParameter('false', false)
            ->setParameter('true', true)
            ->setParameter('tenant', $tenantId)
            ->setParameter('resource', $resource);

        if (null !== $exceptId) {
            $qb->andWhere('v.id != :except')->setParameter('except', $exceptId);
        }

        $qb->getQuery()->execute();
    }

    private function mustFind(string $id): SavedView
    {
        $view = $this->em->getRepository(SavedView::class)->find(Uuid::fromString($id));
        if (!$view instanceof SavedView) {
            throw new NotFoundHttpException(\sprintf('Saved view %s not found.', $id));
        }

        return $view;
    }

    /**
     * @return array<string, mixed>
     */
    private function serialize(SavedView $view): array
    {
        return [
            'id' => $view->getId()->toRfc4122(),
            'slug' => $view->getSlug(),
            'name' => $view->getName(),
            'description' => $view->getDescription(),
            'resource' => $view->getResource(),
            'config' => $view->getConfig(),
            'is_default' => $view->isDefault(),
            'is_system' => $view->isSystem(),
            'created_at' => $view->getCreatedAt()->format(DateTimeInterface::ATOM),
            'updated_at' => $view->getUpdatedAt()->format(DateTimeInterface::ATOM),
        ];
    }
}
