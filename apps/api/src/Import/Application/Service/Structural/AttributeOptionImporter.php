<?php

declare(strict_types=1);

namespace App\Import\Application\Service\Structural;

use App\Catalog\Domain\Entity\Attribute;
use App\Catalog\Domain\Entity\AttributeOption;
use App\Catalog\Domain\Repository\AttributeOptionRepositoryInterface;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Upserts the select/multiselect options carried by an attribute import row's
 * `options` JSON cell. Mirrors the create logic of
 * {@see \App\Catalog\Presentation\Controller\AttributeOptionsController} (dedupe
 * by `(attribute, code)`, auto-assign position) but without the HTTP layer.
 *
 * Additive by decision: a re-import adds new options and refreshes labels of
 * existing ones; it never deletes options dropped from the file. Tenant is
 * stamped on prePersist (AttributeOption implements TenantScoped).
 */
final readonly class AttributeOptionImporter
{
    public function __construct(
        private EntityManagerInterface $em,
        private AttributeOptionRepositoryInterface $options,
    ) {
    }

    /**
     * @param list<array{code: string, label: array<string, string>}> $rows
     */
    public function sync(Attribute $attribute, array $rows): void
    {
        if ([] === $rows) {
            return;
        }

        $existing = [];
        $maxPosition = -1;
        foreach ($this->options->findByAttribute($attribute) as $option) {
            $existing[$option->getCode()] = $option;
            $maxPosition = max($maxPosition, $option->getPosition());
        }

        foreach ($rows as $row) {
            $code = $row['code'];
            $label = $row['label'];
            $current = $existing[$code] ?? null;
            if ($current instanceof AttributeOption) {
                if ([] !== $label) {
                    $current->rename($label);
                }

                continue;
            }

            $option = new AttributeOption($attribute, $code, $label, ++$maxPosition);
            $this->em->persist($option);
            $existing[$code] = $option;
        }

        $this->em->flush();
    }
}
