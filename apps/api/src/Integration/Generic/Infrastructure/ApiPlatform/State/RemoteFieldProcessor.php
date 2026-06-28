<?php

declare(strict_types=1);

namespace App\Integration\Generic\Infrastructure\ApiPlatform\State;

use ApiPlatform\Metadata\DeleteOperationInterface;
use ApiPlatform\Metadata\Operation;
use ApiPlatform\Metadata\Patch;
use ApiPlatform\Metadata\Post;
use ApiPlatform\State\ProcessorInterface;
use App\Integration\Generic\Domain\Entity\RemoteEndpoint;
use App\Integration\Generic\Domain\Entity\RemoteField;
use App\Integration\Generic\Domain\Enum\RemoteFieldDataType;
use App\Integration\Generic\Domain\Repository\RemoteEndpointRepositoryInterface;
use App\Integration\Generic\Domain\Repository\RemoteFieldRepositoryInterface;
use App\Integration\Generic\Infrastructure\ApiPlatform\Resource\RemoteFieldInput;
use App\Integration\Generic\Infrastructure\ApiPlatform\Resource\RemoteFieldPatchInput;
use InvalidArgumentException;
use LogicException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Uid\Uuid;

/**
 * Write path for the `RemoteField` resource (APIC-P2-05). Resolves the parent
 * endpoint tenant-scoped (a cross-tenant or missing id is a 404) and persists.
 * The tenant is stamped on persist by the listener.
 *
 * @implements ProcessorInterface<RemoteFieldInput|RemoteFieldPatchInput|RemoteField, RemoteField|null>
 */
final readonly class RemoteFieldProcessor implements ProcessorInterface
{
    public function __construct(
        private RemoteEndpointRepositoryInterface $endpoints,
        private RemoteFieldRepositoryInterface $fields,
    ) {
    }

    /**
     * @param array<string, mixed> $uriVariables
     * @param array<string, mixed> $context
     */
    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): ?RemoteField
    {
        if ($operation instanceof DeleteOperationInterface) {
            $this->fields->remove($this->require($uriVariables));

            return null;
        }

        if ($operation instanceof Post) {
            return $this->handlePost($data);
        }

        if ($operation instanceof Patch) {
            return $this->handlePatch($data, $uriVariables);
        }

        throw new LogicException(\sprintf('RemoteFieldProcessor cannot handle operation "%s".', $operation::class));
    }

    private function handlePost(mixed $data): RemoteField
    {
        if (!$data instanceof RemoteFieldInput) {
            throw new LogicException('RemoteFieldProcessor expects RemoteFieldInput on Post.');
        }

        $endpoint = $this->requireEndpoint($data->endpoint);

        $field = new RemoteField($endpoint, $data->path, RemoteFieldDataType::from($data->dataType));
        $field->setLabel($data->label);
        $field->setSampleValue($data->sampleValue);

        $this->fields->save($field);

        return $field;
    }

    /**
     * @param array<string, mixed> $uriVariables
     */
    private function handlePatch(mixed $data, array $uriVariables): RemoteField
    {
        if (!$data instanceof RemoteFieldPatchInput) {
            throw new LogicException('RemoteFieldProcessor expects RemoteFieldPatchInput on Patch.');
        }

        $field = $this->require($uriVariables);

        if (null !== $data->path) {
            $field->setPath($data->path);
        }
        if (null !== $data->label) {
            $field->setLabel($data->label);
        }
        if (null !== $data->dataType) {
            $field->setDataType(RemoteFieldDataType::from($data->dataType));
        }
        if (null !== $data->sampleValue) {
            $field->setSampleValue($data->sampleValue);
        }

        $this->fields->save($field);

        return $field;
    }

    private function requireEndpoint(string $id): RemoteEndpoint
    {
        $endpoint = $this->endpoints->findById($this->parseId($id, 'endpoint'));
        if (!$endpoint instanceof RemoteEndpoint) {
            throw new NotFoundHttpException('RemoteEndpoint was not found.');
        }

        return $endpoint;
    }

    /**
     * @param array<string, mixed> $uriVariables
     */
    private function require(array $uriVariables): RemoteField
    {
        $raw = $uriVariables['id'] ?? null;
        $id = $raw instanceof Uuid ? $raw : $this->parseId(\is_string($raw) ? $raw : '', 'id');

        $field = $this->fields->findById($id);
        if (!$field instanceof RemoteField) {
            throw new NotFoundHttpException('RemoteField was not found.');
        }

        return $field;
    }

    private function parseId(string $raw, string $field): Uuid
    {
        if ('' === $raw) {
            throw new NotFoundHttpException(\sprintf('Missing %s identifier.', $field));
        }

        try {
            return Uuid::fromString($raw);
        } catch (InvalidArgumentException) {
            throw new NotFoundHttpException(\sprintf('Invalid %s identifier.', $field));
        }
    }
}
