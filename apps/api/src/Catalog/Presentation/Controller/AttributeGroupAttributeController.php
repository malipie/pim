<?php

declare(strict_types=1);

namespace App\Catalog\Presentation\Controller;

use App\Catalog\Application\Command\UpdateAttributeGroupAttribute\UpdateAttributeGroupAttributeCommand;
use App\Identity\Domain\Attribute\RequiresPermission;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\Messenger\Exception\HandlerFailedException;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\Uid\Uuid;

/**
 * UI-08.8 (#263) — `PATCH /api/attribute_groups/{groupId}/attributes/{attributeId}`.
 *
 * Update the metadata of a single AttributeGroupAttribute junction row
 * (position, isRequiredInGroup, visibleWhen). The route lives under the
 * parent AttributeGroup so the URL self-documents the relationship and
 * the AttributeGroupVoter ('attribute_group:write') gates writes.
 *
 * Body shape:
 * ```json
 * {
 *   "position": 3,
 *   "isRequiredInGroup": true,
 *   "visibleWhen": {"field": "requires_referral", "operator": "equals", "value": true}
 * }
 * ```
 *
 * `visibleWhen=null` clears the rule; omitting the key leaves it
 * untouched. Invalid rule shape returns 422; cross-group field
 * reference returns 422 ("must reference an attribute code inside the
 * same AttributeGroup").
 */
final class AttributeGroupAttributeController
{
    private const string UUID_REGEX = '[0-9a-fA-F]{8}-[0-9a-fA-F]{4}-[0-9a-fA-F]{4}-[0-9a-fA-F]{4}-[0-9a-fA-F]{12}';

    public function __construct(
        private readonly MessageBusInterface $bus,
    ) {
    }

    #[Route(
        '/api/attribute_groups/{groupId}/attributes/{attributeId}',
        name: 'pim_attribute_group_attributes_update',
        requirements: ['groupId' => self::UUID_REGEX, 'attributeId' => self::UUID_REGEX],
        methods: ['PATCH'],
    )]
    #[IsGranted('IS_AUTHENTICATED_FULLY')]
    #[RequiresPermission(module: 'modeling.attribute_groups', action: 'add_edit')]
    public function patch(string $groupId, string $attributeId, Request $request): JsonResponse
    {
        $body = json_decode($request->getContent(), true);
        if (!\is_array($body)) {
            throw new BadRequestHttpException('Request body must be a JSON object.');
        }

        $position = null;
        if (\array_key_exists('position', $body)) {
            $rawPosition = $body['position'];
            if (!\is_int($rawPosition) || $rawPosition < 0) {
                throw new BadRequestHttpException('position must be a non-negative integer.');
            }
            $position = $rawPosition;
        }

        $isRequired = null;
        if (\array_key_exists('isRequiredInGroup', $body)) {
            $rawRequired = $body['isRequiredInGroup'];
            if (!\is_bool($rawRequired)) {
                throw new BadRequestHttpException('isRequiredInGroup must be a boolean.');
            }
            $isRequired = $rawRequired;
        }

        $visibleWhen = null;
        $clearVisibleWhen = false;
        if (\array_key_exists('visibleWhen', $body)) {
            $rawRule = $body['visibleWhen'];
            if (null === $rawRule) {
                $clearVisibleWhen = true;
            } elseif (\is_array($rawRule)) {
                /** @var array<string, mixed> $rawRule */
                $visibleWhen = $rawRule;
            } else {
                throw new BadRequestHttpException('visibleWhen must be a JSON object or null.');
            }
        }

        $command = new UpdateAttributeGroupAttributeCommand(
            attributeGroupId: Uuid::fromString($groupId),
            attributeId: Uuid::fromString($attributeId),
            position: $position,
            isRequiredInGroup: $isRequired,
            visibleWhen: $visibleWhen,
            clearVisibleWhen: $clearVisibleWhen,
        );

        try {
            $this->bus->dispatch($command);
        } catch (HandlerFailedException $e) {
            $previous = $e->getPrevious();
            if ($previous instanceof HttpException) {
                throw $previous;
            }
            throw $e;
        }

        return new JsonResponse(null, Response::HTTP_NO_CONTENT);
    }
}
