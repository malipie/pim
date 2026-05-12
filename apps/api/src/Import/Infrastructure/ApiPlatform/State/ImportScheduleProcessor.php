<?php

declare(strict_types=1);

namespace App\Import\Infrastructure\ApiPlatform\State;

use ApiPlatform\Metadata\DeleteOperationInterface;
use ApiPlatform\Metadata\Operation;
use ApiPlatform\Metadata\Patch;
use ApiPlatform\Metadata\Post;
use ApiPlatform\State\ProcessorInterface;
use App\Identity\Domain\Entity\User;
use App\Import\Application\Service\CronExpressionParser;
use App\Import\Application\Service\ScheduleDispatcherService;
use App\Import\Domain\Entity\ImportProfile;
use App\Import\Domain\Entity\ImportSchedule;
use App\Import\Domain\Entity\ImportSource;
use App\Import\Domain\Enum\SchedulePriority;
use App\Import\Domain\Repository\ImportProfileRepositoryInterface;
use App\Import\Domain\Repository\ImportScheduleRepositoryInterface;
use App\Import\Domain\Repository\ImportSourceRepositoryInterface;
use App\Import\Infrastructure\ApiPlatform\Resource\ImportScheduleInput;
use App\Import\Infrastructure\ApiPlatform\Resource\ImportSchedulePatchInput;
use App\Shared\Application\TenantContext;
use InvalidArgumentException;
use LogicException;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\UnauthorizedHttpException;
use Symfony\Component\Uid\Uuid;

/**
 * @implements ProcessorInterface<ImportScheduleInput|ImportSchedulePatchInput|ImportSchedule, ImportSchedule|null>
 */
final readonly class ImportScheduleProcessor implements ProcessorInterface
{
    public function __construct(
        private ImportScheduleRepositoryInterface $schedules,
        private ImportSourceRepositoryInterface $sources,
        private ImportProfileRepositoryInterface $profiles,
        private CronExpressionParser $parser,
        private ScheduleDispatcherService $dispatcher,
        private Security $security,
        private TenantContext $tenantContext,
    ) {
    }

    /**
     * @param array<string, mixed> $uriVariables
     * @param array<string, mixed> $context
     */
    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): ?ImportSchedule
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
        throw new LogicException(\sprintf('ImportScheduleProcessor cannot handle operation "%s".', $operation::class));
    }

    private function handlePost(mixed $data): ImportSchedule
    {
        if (!$data instanceof ImportScheduleInput) {
            throw new LogicException('ImportScheduleProcessor expects ImportScheduleInput on Post.');
        }
        $user = $this->currentUser();
        $tenant = $user->getTenant();
        $this->tenantContext->set($tenant);

        if (!$this->parser->isValid($data->cron)) {
            throw new BadRequestHttpException(\sprintf('Cron expression "%s" is not valid.', $data->cron));
        }
        if (null !== $this->schedules->findByCode($tenant, $data->code)) {
            throw new ConflictHttpException(\sprintf('Import schedule with code "%s" already exists.', $data->code));
        }

        $schedule = new ImportSchedule(
            userId: $user->getId(),
            name: $data->name,
            code: $data->code,
            cron: $data->cron,
            priority: SchedulePriority::from($data->priority),
        );
        $schedule->setCron($data->cron, $this->parser->describe($data->cron));
        $schedule->setNotifyChannels($data->notifyChannels);
        $schedule->setNotifyConfig($data->notifyConfig);
        if (!$data->enabled) {
            $schedule->disable();
        }
        if (null !== $data->sourceId) {
            $schedule->setSource($this->loadSource($data->sourceId));
        }
        if (null !== $data->profileId) {
            $schedule->setProfile($this->loadProfile($data->profileId, $user));
        }
        $this->schedules->save($schedule);

        // computeNextRun saves again with the populated nextRun column.
        $this->dispatcher->computeNextRun($schedule);

        return $schedule;
    }

    /**
     * @param array<string, mixed> $uriVariables
     */
    private function handlePatch(mixed $data, array $uriVariables): ImportSchedule
    {
        if (!$data instanceof ImportSchedulePatchInput) {
            throw new LogicException('ImportScheduleProcessor expects ImportSchedulePatchInput on Patch.');
        }
        $schedule = $this->loadSchedule($uriVariables);
        $user = $this->currentUser();
        $tenant = $schedule->getTenant() ?? throw new LogicException('schedule tenant');

        if (null !== $data->name) {
            $schedule->rename($data->name);
        }
        if (null !== $data->code && $data->code !== $schedule->getCode()) {
            $existing = $this->schedules->findByCode($tenant, $data->code);
            if (null !== $existing && $existing->getId()->toRfc4122() !== $schedule->getId()->toRfc4122()) {
                throw new ConflictHttpException(\sprintf('Import schedule code "%s" already exists.', $data->code));
            }
            $schedule->setCode($data->code);
        }
        if (null !== $data->cron) {
            if (!$this->parser->isValid($data->cron)) {
                throw new BadRequestHttpException(\sprintf('Cron expression "%s" is not valid.', $data->cron));
            }
            $schedule->setCron($data->cron, $this->parser->describe($data->cron));
        }
        if (null !== $data->priority) {
            $schedule->setPriority(SchedulePriority::from($data->priority));
        }
        if (null !== $data->enabled) {
            $data->enabled ? $schedule->enable() : $schedule->disable();
        }
        if (null !== $data->sourceId) {
            $schedule->setSource($this->loadSource($data->sourceId));
        }
        if (null !== $data->profileId) {
            $schedule->setProfile($this->loadProfile($data->profileId, $user));
        }
        if (null !== $data->notifyChannels) {
            $schedule->setNotifyChannels($data->notifyChannels);
        }
        if (null !== $data->notifyConfig) {
            $schedule->setNotifyConfig($data->notifyConfig);
        }
        $this->schedules->save($schedule);
        $this->dispatcher->computeNextRun($schedule);

        return $schedule;
    }

    /**
     * @param array<string, mixed> $uriVariables
     */
    private function handleDelete(array $uriVariables): null
    {
        $schedule = $this->loadSchedule($uriVariables);
        $this->schedules->remove($schedule);

        return null;
    }

    /**
     * @param array<string, mixed> $uriVariables
     */
    private function loadSchedule(array $uriVariables): ImportSchedule
    {
        $rawId = $uriVariables['id'] ?? null;
        if ($rawId instanceof Uuid) {
            $id = $rawId;
        } elseif (\is_string($rawId) && '' !== $rawId) {
            try {
                $id = Uuid::fromString($rawId);
            } catch (InvalidArgumentException) {
                throw new NotFoundHttpException(\sprintf('Import schedule "%s" was not found.', $rawId));
            }
        } else {
            throw new NotFoundHttpException('Import schedule not found.');
        }
        $schedule = $this->schedules->findById($id);
        if (!$schedule instanceof ImportSchedule) {
            throw new NotFoundHttpException(\sprintf('Import schedule "%s" was not found.', $id->toRfc4122()));
        }

        return $schedule;
    }

    private function loadSource(string $rawId): ImportSource
    {
        try {
            $id = Uuid::fromString($rawId);
        } catch (InvalidArgumentException) {
            throw new NotFoundHttpException(\sprintf('Import source "%s" was not found.', $rawId));
        }
        $source = $this->sources->findById($id);
        if (!$source instanceof ImportSource) {
            throw new NotFoundHttpException(\sprintf('Import source "%s" was not found.', $rawId));
        }

        return $source;
    }

    private function loadProfile(string $rawId, User $user): ImportProfile
    {
        try {
            $id = Uuid::fromString($rawId);
        } catch (InvalidArgumentException) {
            throw new NotFoundHttpException(\sprintf('Import profile "%s" was not found.', $rawId));
        }
        $profile = $this->profiles->findById($id);
        if (!$profile instanceof ImportProfile) {
            throw new NotFoundHttpException(\sprintf('Import profile "%s" was not found.', $rawId));
        }
        if ($profile->getUserId()->toRfc4122() !== $user->getId()->toRfc4122()) {
            throw new NotFoundHttpException(\sprintf('Import profile "%s" was not found.', $rawId));
        }

        return $profile;
    }

    private function currentUser(): User
    {
        $user = $this->security->getUser();
        if (!$user instanceof User) {
            throw new UnauthorizedHttpException('JWT', 'Authenticated user required.');
        }

        return $user;
    }
}
