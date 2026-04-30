<?php

declare(strict_types=1);

namespace App\ApiConfigurator\Infrastructure\ApiPlatform\State;

use ApiPlatform\Metadata\DeleteOperationInterface;
use ApiPlatform\Metadata\Operation;
use ApiPlatform\Metadata\Patch;
use ApiPlatform\Metadata\Post;
use ApiPlatform\State\ProcessorInterface;
use App\ApiConfigurator\Application\Command\CreateApiProfile\CreateApiProfileCommand;
use App\ApiConfigurator\Application\Command\DeleteApiProfile\DeleteApiProfileCommand;
use App\ApiConfigurator\Application\Command\UpdateApiProfile\UpdateApiProfileCommand;
use App\ApiConfigurator\Domain\Entity\ApiProfile;
use App\ApiConfigurator\Domain\Enum\OutputFormat;
use App\ApiConfigurator\Domain\Repository\ApiProfileRepositoryInterface;
use App\ApiConfigurator\Infrastructure\ApiPlatform\Resource\ApiProfileInput;
use App\ApiConfigurator\Infrastructure\ApiPlatform\Resource\ApiProfilePatchInput;
use LogicException;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Exception\HandlerFailedException;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\HandledStamp;
use Symfony\Component\Uid\Uuid;

/**
 * @implements ProcessorInterface<ApiProfileInput|ApiProfilePatchInput|ApiProfile, ApiProfile|null>
 */
final readonly class ApiProfileProcessor implements ProcessorInterface
{
    public function __construct(
        private MessageBusInterface $bus,
        private ApiProfileRepositoryInterface $profiles,
    ) {
    }

    /**
     * @param array<string, mixed> $uriVariables
     * @param array<string, mixed> $context
     */
    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): ?ApiProfile
    {
        if ($operation instanceof DeleteOperationInterface) {
            return $this->handleDelete($uriVariables);
        }

        if ($operation instanceof Post) {
            return $this->handlePost($data);
        }

        if ($operation instanceof Patch) {
            return $this->handlePatch($data, $uriVariables);
        }

        throw new LogicException(\sprintf(
            'ApiProfileProcessor cannot handle operation "%s".',
            $operation::class,
        ));
    }

    private function handlePost(mixed $data): ApiProfile
    {
        if (!$data instanceof ApiProfileInput) {
            throw new LogicException('ApiProfileProcessor expects ApiProfileInput on Post.');
        }

        $command = new CreateApiProfileCommand(
            code: $data->code,
            name: $data->name,
            outputFormat: OutputFormat::from($data->outputFormat),
            description: $data->description,
            objectTypeIds: $data->objectTypeIds,
            includedAttributes: $data->includedAttributes,
            filters: $data->filters,
            webhookUrl: $data->webhookUrl,
            webhookEvents: $data->webhookEvents,
            rateLimitPerHour: $data->rateLimitPerHour,
        );

        $envelope = $this->dispatch($command);
        $newId = $this->extractResult($envelope);
        if (!$newId instanceof Uuid) {
            throw new LogicException('CreateApiProfileHandler returned an unexpected result.');
        }

        $created = $this->profiles->findById($newId);
        if (null === $created) {
            throw new LogicException('Newly created ApiProfile could not be re-loaded.');
        }

        return $created;
    }

    /**
     * @param array<string, mixed> $uriVariables
     */
    private function handlePatch(mixed $data, array $uriVariables): ApiProfile
    {
        if (!$data instanceof ApiProfilePatchInput) {
            throw new LogicException('ApiProfileProcessor expects ApiProfilePatchInput on Patch.');
        }

        $id = $this->idFromUriVariables($uriVariables);
        $existing = $this->profiles->findById($id);
        if (null === $existing) {
            throw new NotFoundHttpException(\sprintf('ApiProfile "%s" was not found.', $id->toRfc4122()));
        }

        $command = new UpdateApiProfileCommand(
            id: $id,
            name: $data->name,
            description: $data->description,
            outputFormat: null !== $data->outputFormat ? OutputFormat::from($data->outputFormat) : null,
            objectTypeIds: $data->objectTypeIds,
            includedAttributes: $data->includedAttributes,
            filters: $data->filters,
            webhookUrl: $data->webhookUrl,
            webhookEvents: $data->webhookEvents,
            rateLimitPerHour: $data->rateLimitPerHour,
        );
        $this->dispatch($command);

        $reloaded = $this->profiles->findById($id);
        if (null === $reloaded) {
            throw new LogicException('Updated ApiProfile could not be re-loaded.');
        }

        return $reloaded;
    }

    /**
     * @param array<string, mixed> $uriVariables
     */
    private function handleDelete(array $uriVariables): null
    {
        $id = $this->idFromUriVariables($uriVariables);
        $this->dispatch(new DeleteApiProfileCommand($id));

        return null;
    }

    private function dispatch(object $message): Envelope
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
            throw new LogicException('ApiProfileProcessor requires {id} URI variable.');
        }

        return Uuid::fromString($raw);
    }

    private function extractResult(Envelope $envelope): mixed
    {
        $stamp = $envelope->last(HandledStamp::class);

        return $stamp instanceof HandledStamp ? $stamp->getResult() : null;
    }
}
