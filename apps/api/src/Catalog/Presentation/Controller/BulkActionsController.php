<?php

declare(strict_types=1);

namespace App\Catalog\Presentation\Controller;

use App\Catalog\Application\Bulk\BulkSetAttributeHandler;
use App\Catalog\Domain\Entity\BulkSession;
use App\Catalog\Domain\Entity\CatalogObject;
use App\Catalog\Domain\Repository\CatalogObjectRepositoryInterface;
use App\Shared\Application\TenantContext;
use App\Shared\Application\UserIdentityAware;
use DateTimeInterface;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\Uid\Uuid;
use Throwable;

/**
 * VIEW-12 (#543) — bulk actions: preview + apply.
 *
 * MVP scope: synchronous `set_attribute` action only. Async via
 * Symfony Messenger + Mercure SSE progress lands in VIEW-12.1.
 * `delete` / `duplicate` / `publish` / category ops follow in
 * VIEW-13..VIEW-16.
 *
 * Preview returns sample 5 + aggregate counts so the wizard's Step 3
 * can render the diff without persisting anything.
 */
final class BulkActionsController
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly TenantContext $tenantContext,
        private readonly Security $security,
        private readonly CatalogObjectRepositoryInterface $catalogObjects,
        private readonly BulkSetAttributeHandler $setAttributeHandler,
    ) {
    }

    #[Route('/api/products/bulk-actions/preview', name: 'pim_bulk_actions_preview', methods: ['POST'])]
    #[IsGranted('IS_AUTHENTICATED_FULLY')]
    public function preview(Request $request): JsonResponse
    {
        /** @var array<string, mixed> $body */
        $body = json_decode($request->getContent(), true) ?? [];

        $action = $this->requireString($body, 'action');
        $targetIds = $this->requireIdList($body, 'target_ids');
        $payload = $this->requireArray($body, 'payload');

        // Hard limit 10k IDs per request (matches VIEW-11 selection cap).
        if (\count($targetIds) > 10_000) {
            throw new BadRequestHttpException('target_ids exceeds 10000 hard cap.');
        }

        $sample = [];
        $skipped = 0;
        $errors = 0;

        if ('set_attribute' === $action) {
            $attrCode = $payload['attr'] ?? null;
            $newValue = $payload['value'] ?? null;
            if (!\is_string($attrCode) || '' === $attrCode) {
                throw new BadRequestHttpException('payload.attr is required.');
            }
            $sampleIds = \array_slice($targetIds, 0, 5);
            foreach ($sampleIds as $id) {
                try {
                    $object = $this->catalogObjects->findById(Uuid::fromString($id));
                    if (!$object instanceof CatalogObject) {
                        ++$errors;
                        continue;
                    }
                    $oldValue = $object->getAttributesIndexed()[$attrCode] ?? null;
                    $sample[] = [
                        'id' => $object->getId()->toRfc4122(),
                        'sku' => $object->getCode(),
                        'before' => $oldValue,
                        'after' => $newValue,
                    ];
                } catch (Throwable) {
                    ++$errors;
                }
            }

            return new JsonResponse([
                'action' => $action,
                'target_count' => \count($targetIds),
                'success_count' => \count($targetIds) - $skipped - $errors,
                'skipped_count' => $skipped,
                'error_count' => $errors,
                'sample' => $sample,
            ]);
        }

        throw new BadRequestHttpException(\sprintf('Unsupported bulk action "%s" in MVP.', $action));
    }

    #[Route('/api/products/bulk-actions/{actionType}', name: 'pim_bulk_actions_apply', methods: ['POST'])]
    #[IsGranted('IS_AUTHENTICATED_FULLY')]
    public function apply(string $actionType, Request $request): JsonResponse
    {
        $tenant = $this->tenantContext->get();
        if (null === $tenant) {
            throw new BadRequestHttpException('No tenant context.');
        }
        $user = $this->security->getUser();
        $userId = $user instanceof UserIdentityAware ? $user->getId() : null;

        /** @var array<string, mixed> $body */
        $body = json_decode($request->getContent(), true) ?? [];

        $targetIds = $this->requireIdList($body, 'target_ids');
        $payload = $this->requireArray($body, 'payload');

        if (\count($targetIds) > 10_000) {
            throw new BadRequestHttpException('target_ids exceeds 10000 hard cap.');
        }

        $session = new BulkSession(
            actionType: $actionType,
            targetObjectIds: $targetIds,
            actionPayload: $payload,
            userId: $userId,
        );
        $this->em->persist($session);
        $this->em->flush();

        $result = match ($actionType) {
            'set_attribute' => $this->setAttributeHandler->handle(
                $session,
                \is_string($payload['attr'] ?? null) ? $payload['attr'] : '',
                $payload['value'] ?? null,
            ),
            default => throw new NotFoundHttpException(\sprintf('Bulk action "%s" not implemented.', $actionType)),
        };

        return new JsonResponse([
            'session_id' => $session->getId()->toRfc4122(),
            'action' => $actionType,
            'target_count' => $session->getTargetCount(),
            'success_count' => $result['success'],
            'skipped_count' => $result['skipped'],
            'error_count' => $result['error'],
            'rollback_available_until' => $session->getRollbackAvailableUntil()?->format(DateTimeInterface::ATOM),
            'completed_at' => $session->getCompletedAt()?->format(DateTimeInterface::ATOM),
        ]);
    }

    /**
     * @param array<string, mixed> $body
     */
    private function requireString(array $body, string $key): string
    {
        $value = $body[$key] ?? null;
        if (!\is_string($value) || '' === trim($value)) {
            throw new BadRequestHttpException(\sprintf('%s must be a non-empty string.', $key));
        }

        return trim($value);
    }

    /**
     * @param array<string, mixed> $body
     *
     * @return list<string>
     */
    private function requireIdList(array $body, string $key): array
    {
        $raw = $body[$key] ?? null;
        if (!\is_array($raw) || [] === $raw) {
            throw new BadRequestHttpException(\sprintf('%s must be a non-empty array.', $key));
        }
        $out = [];
        foreach ($raw as $id) {
            if (\is_string($id) && '' !== $id) {
                $out[] = $id;
            }
        }

        return $out;
    }

    /**
     * @param array<string, mixed> $body
     *
     * @return array<string, mixed>
     */
    private function requireArray(array $body, string $key): array
    {
        $raw = $body[$key] ?? null;
        if (!\is_array($raw)) {
            throw new BadRequestHttpException(\sprintf('%s must be an object.', $key));
        }
        /** @var array<string, mixed> $typed */
        $typed = $raw;

        return $typed;
    }
}
