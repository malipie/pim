<?php

declare(strict_types=1);

namespace App\Channel\Presentation\Controller;

use App\Channel\Domain\ChannelPlacementSource;
use App\Channel\Domain\Entity\Channel;
use App\Channel\Domain\Entity\ChannelCategoryNode;
use App\Channel\Domain\Repository\ChannelCategoryNodeRepositoryInterface;
use App\Channel\Domain\Repository\ChannelRepositoryInterface;
use App\Channel\Domain\Repository\ObjectChannelPlacementRepositoryInterface;
use App\Identity\Contracts\Attribute\RequiresPermission;
use App\Shared\Application\TenantContext;
use Doctrine\DBAL\Exception\ForeignKeyConstraintViolationException;
use JsonException;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\Uid\Uuid;

use const JSON_THROW_ON_ERROR;

/**
 * CHC-03 (#1286) — per-channel placement of a product, surfaced inline in the
 * product "Kategorie" tab ("Gdzie trafia na kanałach").
 *
 * Lives in the Channel context (it owns {@see \App\Channel\Domain\Entity\ObjectChannelPlacement});
 * the product is referenced by bare Uuid so there is no cross-BC Catalog import.
 * The master category assignment (`object_categories`) is a different surface
 * and is untouched here.
 */
final class ObjectChannelPlacementController
{
    private const int MAX_LABEL_DEPTH = 50;

    public function __construct(
        private readonly ChannelRepositoryInterface $channels,
        private readonly ChannelCategoryNodeRepositoryInterface $nodes,
        private readonly ObjectChannelPlacementRepositoryInterface $placements,
        private readonly TenantContext $tenantContext,
    ) {
    }

    #[Route('/api/products/{id}/channel-placements', name: 'pim_products_channel_placements_list', methods: ['GET'], format: 'json')]
    #[Route('/api/objects/{id}/channel-placements', name: 'pim_objects_channel_placements_list', methods: ['GET'], format: 'json')]
    #[IsGranted('IS_AUTHENTICATED_FULLY')]
    #[RequiresPermission(module: 'products', action: 'view')]
    public function list(string $id): JsonResponse
    {
        $objectId = $this->uuid($id);
        $tenant = $this->requireTenant();

        $rows = [];
        foreach ($this->channels->findAllForTenant($tenant) as $channel) {
            $placement = $this->placements->findByObjectAndChannel($objectId, $channel->getId());
            $rows[] = [
                'channelId' => $channel->getId()->toRfc4122(),
                'channelCode' => $channel->getCode(),
                'channelLabel' => $channel->getLabel(),
                'placement' => null === $placement ? null : [
                    'nodeId' => $placement->getNodeId()->toRfc4122(),
                    'nodePath' => $this->labelPath($placement->getNode()),
                    'source' => $placement->getSource()->value,
                ],
            ];
        }

        return new JsonResponse(['member' => $rows, 'totalItems' => \count($rows)]);
    }

    #[Route('/api/products/{id}/channel-placements/{channelId}', name: 'pim_products_channel_placements_put', methods: ['PUT'], format: 'json')]
    #[Route('/api/objects/{id}/channel-placements/{channelId}', name: 'pim_objects_channel_placements_put', methods: ['PUT'], format: 'json')]
    #[IsGranted('IS_AUTHENTICATED_FULLY')]
    #[RequiresPermission(module: 'products', action: 'edit')]
    public function put(string $id, string $channelId, Request $request): JsonResponse
    {
        $objectId = $this->uuid($id);
        $channel = $this->requireChannel($channelId);
        $node = $this->requireNodeOfChannel($request, $channel);

        try {
            $placement = $this->placements->upsert($objectId, $channel, $node, ChannelPlacementSource::Manual);
        } catch (ForeignKeyConstraintViolationException) {
            throw new UnprocessableEntityHttpException(\sprintf('Object "%s" does not exist.', $objectId->toRfc4122()));
        }

        return new JsonResponse([
            'channelId' => $channel->getId()->toRfc4122(),
            'channelCode' => $channel->getCode(),
            'channelLabel' => $channel->getLabel(),
            'placement' => [
                'nodeId' => $placement->getNodeId()->toRfc4122(),
                'nodePath' => $this->labelPath($placement->getNode()),
                'source' => $placement->getSource()->value,
            ],
        ]);
    }

    #[Route('/api/products/{id}/channel-placements/{channelId}', name: 'pim_products_channel_placements_delete', methods: ['DELETE'], format: 'json')]
    #[Route('/api/objects/{id}/channel-placements/{channelId}', name: 'pim_objects_channel_placements_delete', methods: ['DELETE'], format: 'json')]
    #[IsGranted('IS_AUTHENTICATED_FULLY')]
    #[RequiresPermission(module: 'products', action: 'edit')]
    public function delete(string $id, string $channelId): Response
    {
        $placement = $this->placements->findByObjectAndChannel($this->uuid($id), $this->uuid($channelId));
        if (null === $placement) {
            throw new NotFoundHttpException('No placement for this product on the given channel.');
        }

        $this->placements->remove($placement);

        return new Response(null, Response::HTTP_NO_CONTENT);
    }

    private function requireChannel(string $channelId): Channel
    {
        $channel = $this->channels->findById($this->uuid($channelId));
        if (null === $channel) {
            throw new NotFoundHttpException(\sprintf('Channel "%s" was not found.', $channelId));
        }

        return $channel;
    }

    private function requireNodeOfChannel(Request $request, Channel $channel): ChannelCategoryNode
    {
        $nodeIdRaw = $this->decodeNodeId($request);
        $node = $this->nodes->findById($this->uuid($nodeIdRaw));
        if (null === $node || !$node->getChannel()->getId()->equals($channel->getId())) {
            throw new UnprocessableEntityHttpException(\sprintf(
                'Node "%s" does not belong to channel "%s".',
                $nodeIdRaw,
                $channel->getCode(),
            ));
        }

        return $node;
    }

    private function decodeNodeId(Request $request): string
    {
        $content = $request->getContent();
        try {
            $data = '' === $content ? [] : json_decode($content, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            throw new BadRequestHttpException('Request body is not valid JSON.');
        }
        if (!\is_array($data) || !isset($data['nodeId']) || !\is_string($data['nodeId']) || '' === $data['nodeId']) {
            throw new UnprocessableEntityHttpException('"nodeId" (string) is required.');
        }

        return $data['nodeId'];
    }

    /**
     * Human-readable breadcrumb from the node's ancestry labels (pl → en → code).
     */
    private function labelPath(ChannelCategoryNode $node): string
    {
        $labels = [];
        $current = $node;
        $guard = 0;
        while (null !== $current && $guard < self::MAX_LABEL_DEPTH) {
            $label = $current->getLabel();
            $labels[] = $label['pl'] ?? $label['en'] ?? (($first = reset($label)) !== false ? $first : $current->getCode());
            $current = $current->getParent();
            ++$guard;
        }

        return implode(' > ', array_reverse($labels));
    }

    private function requireTenant(): \App\Shared\Domain\Tenant
    {
        $tenant = $this->tenantContext->get();
        if (null === $tenant) {
            throw new BadRequestHttpException('No tenant context.');
        }

        return $tenant;
    }

    private function uuid(string $value): Uuid
    {
        if (!Uuid::isValid($value)) {
            throw new UnprocessableEntityHttpException(\sprintf('"%s" is not a valid UUID.', $value));
        }

        return Uuid::fromString($value);
    }
}
