<?php

declare(strict_types=1);

namespace App\Integration\Generic\Infrastructure\ApiPlatform\State;

use ApiPlatform\Metadata\DeleteOperationInterface;
use ApiPlatform\Metadata\Operation;
use ApiPlatform\Metadata\Patch;
use ApiPlatform\Metadata\Post;
use ApiPlatform\State\ProcessorInterface;
use App\Integration\Generic\Application\ConnectionCredentialsCipher;
use App\Integration\Generic\Application\Validation\DescriptorValidator;
use App\Integration\Generic\Domain\Entity\Connection;
use App\Integration\Generic\Domain\Enum\AuthType;
use App\Integration\Generic\Domain\Enum\ConnectionStatus;
use App\Integration\Generic\Domain\Exception\InvalidDescriptorException;
use App\Integration\Generic\Domain\Repository\ConnectionRepositoryInterface;
use App\Integration\Generic\Infrastructure\ApiPlatform\Resource\ConnectionInput;
use App\Integration\Generic\Infrastructure\ApiPlatform\Resource\ConnectionPatchInput;
use App\Shared\Application\TenantContext;
use App\Shared\Domain\Tenant;
use LogicException;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;
use Symfony\Component\Uid\Uuid;

/**
 * Write path for the `Connection` resource (APIC-P1-06). Builds/updates the
 * tenant-stamped aggregate from the input DTO, encrypts credentials via the
 * BYOK cipher (APIC-P1-02) and validates the base URL via the SSRF descriptor
 * wall (APIC-P1-04). The tenant is stamped on persist by the listener; the
 * serializer never exposes the ciphertext columns.
 *
 * @implements ProcessorInterface<ConnectionInput|ConnectionPatchInput|Connection, Connection|null>
 */
final readonly class ConnectionProcessor implements ProcessorInterface
{
    public function __construct(
        private ConnectionRepositoryInterface $connections,
        private ConnectionCredentialsCipher $cipher,
        private DescriptorValidator $descriptors,
        private TenantContext $tenantContext,
    ) {
    }

    /**
     * @param array<string, mixed> $uriVariables
     * @param array<string, mixed> $context
     */
    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): ?Connection
    {
        if ($operation instanceof DeleteOperationInterface) {
            $this->connections->remove($this->require($uriVariables));

            return null;
        }

        if ($operation instanceof Post) {
            return $this->handlePost($data);
        }

        if ($operation instanceof Patch) {
            return $this->handlePatch($data, $uriVariables);
        }

        throw new LogicException(\sprintf('ConnectionProcessor cannot handle operation "%s".', $operation::class));
    }

    private function handlePost(mixed $data): Connection
    {
        if (!$data instanceof ConnectionInput) {
            throw new LogicException('ConnectionProcessor expects ConnectionInput on Post.');
        }

        $tenant = $this->requireTenant();
        if (null !== $this->connections->findByCode($tenant, $data->code)) {
            throw new ConflictHttpException(\sprintf('Connection with code "%s" already exists for this tenant.', $data->code));
        }

        $this->validateBaseUrl($data->baseUrl);

        $connection = new Connection($data->code, $data->name, $data->baseUrl, AuthType::from($data->authType));
        $connection->setDefaultHeaders($data->defaultHeaders);
        $connection->setRateLimitHint($data->rateLimitHint);
        $this->cipher->apply($connection, $data->credentials);

        $this->connections->save($connection);

        return $connection;
    }

    /**
     * @param array<string, mixed> $uriVariables
     */
    private function handlePatch(mixed $data, array $uriVariables): Connection
    {
        if (!$data instanceof ConnectionPatchInput) {
            throw new LogicException('ConnectionProcessor expects ConnectionPatchInput on Patch.');
        }

        $connection = $this->require($uriVariables);

        if (null !== $data->name) {
            $connection->setName($data->name);
        }
        if (null !== $data->baseUrl) {
            $this->validateBaseUrl($data->baseUrl);
            $connection->setBaseUrl($data->baseUrl);
        }
        if (null !== $data->authType) {
            $connection->setAuthType(AuthType::from($data->authType));
        }
        if (null !== $data->status) {
            $connection->setStatus(ConnectionStatus::from($data->status));
        }
        if (null !== $data->defaultHeaders) {
            $connection->setDefaultHeaders($data->defaultHeaders);
        }
        if (null !== $data->rateLimitHint) {
            $connection->setRateLimitHint($data->rateLimitHint);
        }
        if (null !== $data->credentials) {
            $this->cipher->apply($connection, $data->credentials);
        }

        $this->connections->save($connection);

        return $connection;
    }

    private function validateBaseUrl(string $baseUrl): void
    {
        try {
            $this->descriptors->assertValidBaseUrl($baseUrl);
        } catch (InvalidDescriptorException $exception) {
            throw new UnprocessableEntityHttpException($exception->getMessage(), $exception);
        }
    }

    /**
     * @param array<string, mixed> $uriVariables
     */
    private function require(array $uriVariables): Connection
    {
        $connection = $this->connections->findById($this->idFromUriVariables($uriVariables));
        if (!$connection instanceof Connection) {
            throw new NotFoundHttpException('Connection was not found.');
        }

        return $connection;
    }

    private function requireTenant(): Tenant
    {
        $tenant = $this->tenantContext->get();
        if (null === $tenant) {
            throw new LogicException('ConnectionProcessor requires an active tenant.');
        }

        return $tenant;
    }

    /**
     * @param array<string, mixed> $uriVariables
     */
    private function idFromUriVariables(array $uriVariables): Uuid
    {
        $raw = $uriVariables['id'] ?? null;
        if ($raw instanceof Uuid) {
            return $raw;
        }
        if (!\is_string($raw) || '' === $raw) {
            throw new LogicException('ConnectionProcessor requires the {id} URI variable.');
        }

        return Uuid::fromString($raw);
    }
}
