<?php

declare(strict_types=1);

namespace App\Catalog\Application\Command\UpdateAttribute;

use App\Catalog\Domain\Repository\AttributeRepositoryInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/**
 * VIEW-02 (#374) — partial update for an Attribute.
 *
 * Patch payload contains only the fields the operator changed; null
 * fields are skipped. System attributes (`is_system=true`, e.g.
 * `created_at` / `updated_by`) reject mutating ops on `localizable`,
 * `scopable`, `required` and `validationRules` — only `label` and
 * `help` are translatable on system attributes (mirrors the system
 * AttributeGroup contract from #260 §10.5).
 */
#[AsMessageHandler]
final readonly class UpdateAttributeHandler
{
    public function __construct(
        private AttributeRepositoryInterface $repository,
    ) {
    }

    public function __invoke(UpdateAttributeCommand $command): void
    {
        $attribute = $this->repository->findById($command->id);
        if (null === $attribute) {
            throw new NotFoundHttpException(\sprintf(
                'Attribute "%s" was not found.',
                $command->id->toRfc4122(),
            ));
        }

        if (null !== $command->label) {
            $attribute->rename($command->label);
        }
        if (null !== $command->help) {
            $attribute->updateHelp($command->help);
        }
        if (null !== $command->position) {
            $attribute->reorder($command->position);
        }

        // System attributes: reject any structural changes; the operator
        // can only re-translate label/help. UI surfaces this via the
        // BuiltInLockBadge — calls bypassing the UI hit this guard.
        if ($attribute->isSystem()) {
            $attemptsStructuralChange = null !== $command->localizable
                || null !== $command->scopable
                || null !== $command->required
                || null !== $command->filterable
                || null !== $command->validationRules;
            if ($attemptsStructuralChange) {
                throw new UnprocessableEntityHttpException(\sprintf(
                    'System attribute "%s" — only label and help are editable.',
                    $attribute->getCode(),
                ));
            }

            $this->repository->save($attribute);

            return;
        }

        if (null !== $command->localizable) {
            $attribute->changeLocalizable($command->localizable);
        }
        if (null !== $command->scopable) {
            $attribute->changeScopable($command->scopable);
        }
        if (null !== $command->required) {
            $attribute->changeRequired($command->required);
        }
        if (null !== $command->filterable) {
            $attribute->changeFilterable($command->filterable);
        }
        if (null !== $command->validationRules) {
            $attribute->updateValidationRules($command->validationRules);
        }

        $this->repository->save($attribute);
    }
}
