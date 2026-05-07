<?php

declare(strict_types=1);

namespace App\Catalog\Application;

use App\Catalog\Domain\Entity\MenuConfiguration;
use App\Catalog\Domain\Entity\ObjectType;
use App\Catalog\Domain\ObjectKind;
use App\Catalog\Domain\Repository\MenuConfigurationRepositoryInterface;
use App\Catalog\Domain\Repository\ObjectTypeRepositoryInterface;
use App\Catalog\Domain\SystemMenuItemRegistry;
use App\Catalog\Domain\Value\MenuItemRecord;
use App\Shared\Domain\Tenant;
use Doctrine\ORM\EntityManagerInterface;
use InvalidArgumentException;
use LogicException;
use Symfony\Component\Uid\Uuid;

/**
 * VIEW-08 (#427) — owns invariants of `menu_configurations` (per-tenant).
 *
 * Two responsibilities:
 *   1. Lazily seed a fresh `MenuConfiguration` for tenants without one
 *      (delegates to {@see DefaultMenuSeeder}).
 *   2. Validate + atomically replace the items list on PUT.
 *
 * Validation rules ({@see replace}):
 *   - kinds must be `system` or `object_type`
 *   - position must be unique inside the array
 *   - `system` ref must exist in {@see SystemMenuItemRegistry}
 *   - `object_type` ref must be a UUID, exist in this tenant, AND have
 *     `exposeToMainMenu=true`
 *   - `kind=object_type` ref pointing at an Asset is rejected (Asset has
 *     its own `/assets` DAM page)
 *   - protected system items (`settings`, `modeling`) cannot be hidden
 *     (visible=false) — silently coerced or thrown? We throw so the FE
 *     surfaces the error and stops the user
 */
final readonly class MenuConfigurationService
{
    public function __construct(
        private MenuConfigurationRepositoryInterface $repository,
        private ObjectTypeRepositoryInterface $objectTypes,
        private DefaultMenuSeeder $defaultSeeder,
        private EntityManagerInterface $em,
    ) {
    }

    /**
     * Returns the MenuConfiguration for the current tenant, seeding the
     * default layout the first time. Idempotent: subsequent calls return
     * the existing row.
     */
    public function getOrCreate(Tenant $tenant): MenuConfiguration
    {
        $existing = $this->repository->findByTenant($tenant);
        if (null !== $existing) {
            return $this->backfillMissingSystemItems($existing);
        }

        return $this->defaultSeeder->seed($tenant);
    }

    /**
     * Self-heal: gdy SystemMenuItemRegistry rośnie (np. nowy `publications`
     * dorzucany przez epik 0.13 / UI-09), tenanty z config'iem zasiedzonym
     * pod starszą wersję rejestru go nie zobaczą — dopisujemy brakujące
     * system items na końcu listy. Idempotent: jeśli wszystkie są obecne,
     * funkcja zwraca config bez `flush`.
     */
    private function backfillMissingSystemItems(MenuConfiguration $config): MenuConfiguration
    {
        $existingSystemRefs = [];
        $maxPosition = -1;
        foreach ($config->getItems() as $item) {
            if ($item->position > $maxPosition) {
                $maxPosition = $item->position;
            }
            if (MenuItemRecord::KIND_SYSTEM === $item->kind) {
                $existingSystemRefs[] = $item->ref;
            }
        }

        $appended = false;
        $items = $config->getItems();
        foreach (SystemMenuItemRegistry::defaultOrder() as $systemKey) {
            if (\in_array($systemKey, $existingSystemRefs, true)) {
                continue;
            }
            $items[] = new MenuItemRecord(
                MenuItemRecord::KIND_SYSTEM,
                $systemKey,
                ++$maxPosition,
                true,
            );
            $appended = true;
        }

        if (!$appended) {
            return $config;
        }

        $config->replaceItems($items);
        $this->em->flush();

        return $config;
    }

    /**
     * @param list<MenuItemRecord> $items
     */
    public function replace(Tenant $tenant, array $items): MenuConfiguration
    {
        $this->validate($items);

        $config = $this->getOrCreate($tenant);
        $config->replaceItems($items);

        $this->em->flush();

        return $config;
    }

    /**
     * @param list<MenuItemRecord> $items
     */
    private function validate(array $items): void
    {
        $seenPositions = [];
        $seenRefs = [];
        foreach ($items as $item) {
            // Position uniqueness — order is the contract, duplicates would
            // race-condition between sortable handlers.
            if (\in_array($item->position, $seenPositions, true)) {
                throw new InvalidArgumentException(\sprintf(
                    'MenuConfiguration items must have unique positions; "%d" appears twice.',
                    $item->position,
                ));
            }
            $seenPositions[] = $item->position;

            // (kind, ref) uniqueness — same item can't appear twice in the
            // same config (the FE would render two identical sidebar entries).
            $key = $item->kind.':'.$item->ref;
            if (\in_array($key, $seenRefs, true)) {
                throw new InvalidArgumentException(\sprintf(
                    'MenuConfiguration items must be unique; "%s" appears twice.',
                    $key,
                ));
            }
            $seenRefs[] = $key;

            if (MenuItemRecord::KIND_SYSTEM === $item->kind) {
                if (!SystemMenuItemRegistry::exists($item->ref)) {
                    throw new InvalidArgumentException(\sprintf(
                        'System menu item "%s" is not in SystemMenuItemRegistry.',
                        $item->ref,
                    ));
                }
                if (SystemMenuItemRegistry::isProtected($item->ref) && !$item->visible) {
                    throw new LogicException(\sprintf(
                        'System menu item "%s" is protected and cannot be hidden.',
                        $item->ref,
                    ));
                }
                continue;
            }

            // kind = object_type
            $objectType = $this->loadObjectType($item->ref);
            if (null === $objectType) {
                throw new InvalidArgumentException(\sprintf(
                    'ObjectType "%s" referenced in menu configuration does not exist for this tenant.',
                    $item->ref,
                ));
            }
            if (!$objectType->isExposedToMainMenu()) {
                throw new LogicException(\sprintf(
                    'ObjectType "%s" cannot appear in the main menu — set exposeToMainMenu=true first.',
                    $objectType->getCode(),
                ));
            }
            if (ObjectKind::Asset === $objectType->getKind()) {
                throw new LogicException(
                    'Asset ObjectType cannot appear in the main menu — use the dedicated /assets DAM page.',
                );
            }
        }
    }

    private function loadObjectType(string $ref): ?ObjectType
    {
        if (!Uuid::isValid($ref)) {
            return null;
        }

        return $this->objectTypes->findById(Uuid::fromString($ref));
    }
}
