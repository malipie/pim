<?php

declare(strict_types=1);

namespace App\Catalog\Presentation\Controller;

use App\Catalog\Application\Command\BulkAttachAttributesToGroup\BulkAttachAttributesToGroupCommand;
use App\Catalog\Application\Command\DetachAttributeFromGroup\DetachAttributeFromGroupCommand;
use App\Catalog\Application\Command\ReorderGroupAttributes\ReorderGroupAttributesCommand;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\Messenger\Exception\HandlerFailedException;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\HandledStamp;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\Uid\Uuid;

/**
 * VIEW-03 (#375) — bulk membership operations for the Attribute Group
 * detail view: the "Z biblioteki" attach popup, the per-row trash
 * (detach), and drag-reorder of group members.
 *
 * Routes intentionally live alongside the existing single-junction
 * `AttributeGroupAttributeController` (PATCH ...{attributeId}) so the
 * URL structure self-documents the parent → child relationship and
 * AttributeGroupVoter (`attribute_group:write`) gates writes uniformly.
 */
final class AttributeGroupMembershipController
{
    private const string UUID_REGEX = '[0-9a-fA-F]{8}-[0-9a-fA-F]{4}-[0-9a-fA-F]{4}-[0-9a-fA-F]{4}-[0-9a-fA-F]{12}';

    public function __construct(
        private readonly MessageBusInterface $bus,
    ) {
    }

    /**
     * `POST /api/attribute_groups/{groupId}/attributes/bulk-attach`
     *
     * Body: `{ "attributeCodes": ["code_a", "code_b", ...] }`
     *
     * Returns 200 with `{ attached: [...] }` listing codes that were
     * actually inserted (codes already present are silently skipped to
     * keep the FE multi-select idempotent).
     */
    #[Route(
        '/api/attribute_groups/{groupId}/attributes/bulk-attach',
        name: 'pim_attribute_group_attributes_bulk_attach',
        requirements: ['groupId' => self::UUID_REGEX],
        methods: ['POST'],
    )]
    #[IsGranted('IS_AUTHENTICATED_FULLY')]
    public function bulkAttach(string $groupId, Request $request): JsonResponse
    {
        $body = json_decode($request->getContent(), true);
        if (!\is_array($body)) {
            throw new BadRequestHttpException('Request body must be a JSON object.');
        }

        $codes = $body['attributeCodes'] ?? null;
        if (!\is_array($codes)) {
            throw new BadRequestHttpException('attributeCodes must be an array of strings.');
        }
        $normalized = [];
        foreach ($codes as $code) {
            if (!\is_string($code) || '' === $code) {
                throw new BadRequestHttpException('attributeCodes must contain non-empty strings only.');
            }
            $normalized[] = $code;
        }

        $envelope = $this->dispatch(new BulkAttachAttributesToGroupCommand(
            attributeGroupId: Uuid::fromString($groupId),
            attributeCodes: $normalized,
        ));

        $stamp = $envelope->last(HandledStamp::class);
        $attached = $stamp instanceof HandledStamp ? $stamp->getResult() : [];

        return new JsonResponse(['attached' => \is_array($attached) ? $attached : []]);
    }

    /**
     * `DELETE /api/attribute_groups/{groupId}/attributes/{attributeId}`
     *
     * Removes the junction row only. The Attribute itself stays in the
     * global library. System attributes in the system audit group are
     * 422 (immutable membership).
     */
    #[Route(
        '/api/attribute_groups/{groupId}/attributes/{attributeId}',
        name: 'pim_attribute_group_attributes_detach',
        requirements: ['groupId' => self::UUID_REGEX, 'attributeId' => self::UUID_REGEX],
        methods: ['DELETE'],
    )]
    #[IsGranted('IS_AUTHENTICATED_FULLY')]
    public function detach(string $groupId, string $attributeId): Response
    {
        $this->dispatch(new DetachAttributeFromGroupCommand(
            attributeGroupId: Uuid::fromString($groupId),
            attributeId: Uuid::fromString($attributeId),
        ));

        return new Response(null, 204);
    }

    /**
     * `POST /api/attribute_groups/{groupId}/attributes/reorder`
     *
     * Body: `{ "order": ["code_a", "code_b", "code_c"] }`
     *
     * Payload must be a strict permutation of current membership; the
     * handler raises 422 on mismatched size, duplicates, or unknown
     * codes. Single-row partial reorders go through the per-junction
     * PATCH endpoint instead.
     */
    #[Route(
        '/api/attribute_groups/{groupId}/attributes/reorder',
        name: 'pim_attribute_group_attributes_reorder',
        requirements: ['groupId' => self::UUID_REGEX],
        methods: ['POST'],
    )]
    #[IsGranted('IS_AUTHENTICATED_FULLY')]
    public function reorder(string $groupId, Request $request): Response
    {
        $body = json_decode($request->getContent(), true);
        if (!\is_array($body)) {
            throw new BadRequestHttpException('Request body must be a JSON object.');
        }

        $order = $body['order'] ?? null;
        if (!\is_array($order)) {
            throw new BadRequestHttpException('order must be an array of attribute code strings.');
        }
        $normalized = [];
        foreach ($order as $code) {
            if (!\is_string($code) || '' === $code) {
                throw new BadRequestHttpException('order must contain non-empty strings only.');
            }
            $normalized[] = $code;
        }

        $this->dispatch(new ReorderGroupAttributesCommand(
            attributeGroupId: Uuid::fromString($groupId),
            attributeCodesInOrder: $normalized,
        ));

        return new Response(null, 204);
    }

    private function dispatch(object $message): \Symfony\Component\Messenger\Envelope
    {
        try {
            return $this->bus->dispatch($message);
        } catch (HandlerFailedException $e) {
            $previous = $e->getPrevious();
            if ($previous instanceof HttpException) {
                throw $previous;
            }

            throw $e;
        }
    }
}
