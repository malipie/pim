<?php

declare(strict_types=1);

namespace App\Catalog\Application\Bulk;

use App\Catalog\Application\BulkContext;
use App\Catalog\Application\Lock\AttributeLockReader;
use App\Catalog\Domain\Entity\BulkLog;
use App\Catalog\Domain\Entity\BulkSession;
use App\Catalog\Domain\Entity\CatalogObject;
use App\Catalog\Domain\Repository\CatalogObjectRepositoryInterface;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\Uid\Uuid;
use Throwable;

/**
 * VIEW-13 (#545) — `increment_numeric` bulk action.
 *
 * Applies an arithmetic operator (+ - * / %) to a numeric attribute
 * across N products. Cmd+K killer use case (PRD §3.5): `price *= 1.10`.
 * Skips rows where the current value is not numeric (warning log).
 * Locked attributes (VIEW-33 / PRD §8.3) skip with a warning entry.
 *
 * Division-by-zero raises a per-row error log (no-op) rather than
 * aborting the batch.
 */
final class BulkIncrementNumericHandler
{
    public const int CHUNK_SIZE = 200;

    public function __construct(
        private readonly CatalogObjectRepositoryInterface $catalogObjects,
        private readonly EntityManagerInterface $em,
        private readonly BulkContext $bulkContext,
        private readonly AttributeLockReader $lockReader,
    ) {
    }

    /**
     * @return array{success: int, skipped: int, error: int}
     */
    public function handle(BulkSession $session, string $attrCode, string $operator, float $operand): array
    {
        if (!\in_array($operator, ['+', '-', '*', '/', '%'], true)) {
            throw new BadRequestHttpException(\sprintf('Unsupported operator "%s".', $operator));
        }

        $this->bulkContext->setBulk(true);
        try {
            $success = 0;
            $skipped = 0;
            $errors = 0;
            $chunkCounter = 0;

            foreach ($session->getTargetObjectIds() as $targetId) {
                try {
                    $object = $this->catalogObjects->findById(Uuid::fromString($targetId));
                    if (!$object instanceof CatalogObject) {
                        ++$errors;
                        ++$chunkCounter;
                        continue;
                    }

                    if ($this->lockReader->isLocked($object, $attrCode)) {
                        ++$skipped;
                        $this->em->persist(new BulkLog(
                            $session->getId(),
                            $object->getId(),
                            null,
                            $object->getAttributesIndexed()[$attrCode] ?? null,
                            $object->getAttributesIndexed()[$attrCode] ?? null,
                            BulkLog::LEVEL_WARNING,
                            'Attribute locked',
                        ));
                        ++$chunkCounter;
                        if ($chunkCounter >= self::CHUNK_SIZE) {
                            $this->em->flush();
                            $this->em->clear();
                            $chunkCounter = 0;
                        }
                        continue;
                    }

                    $indexed = $object->getAttributesIndexed();
                    $oldValue = $indexed[$attrCode] ?? null;

                    if (!is_numeric($oldValue)) {
                        ++$skipped;
                        $this->em->persist(new BulkLog(
                            $session->getId(),
                            $object->getId(),
                            null,
                            $oldValue,
                            $oldValue,
                            BulkLog::LEVEL_WARNING,
                            'Value is not numeric',
                        ));
                        ++$chunkCounter;
                        continue;
                    }

                    $current = (float) $oldValue;
                    $newValue = match ($operator) {
                        '+' => $current + $operand,
                        '-' => $current - $operand,
                        '*' => $current * $operand,
                        '/' => $this->safeDivide($current, $operand),
                        '%' => $this->safeModulo($current, $operand),
                    };

                    if (null === $newValue) {
                        ++$errors;
                        $this->em->persist(new BulkLog(
                            $session->getId(),
                            $object->getId(),
                            null,
                            $oldValue,
                            null,
                            BulkLog::LEVEL_ERROR,
                            'Division by zero',
                        ));
                    } else {
                        $indexed[$attrCode] = $newValue;
                        $object->updateAttributeIndex($indexed);
                        $object->markTouchedByBulkSession($session->getId());
                        $this->em->persist(new BulkLog(
                            $session->getId(),
                            $object->getId(),
                            null,
                            $oldValue,
                            $newValue,
                            BulkLog::LEVEL_INFO,
                            null,
                        ));
                        ++$success;
                    }
                } catch (Throwable $e) {
                    ++$errors;
                    $this->em->persist(new BulkLog(
                        $session->getId(),
                        Uuid::fromString($targetId),
                        null,
                        null,
                        null,
                        BulkLog::LEVEL_ERROR,
                        $e->getMessage(),
                    ));
                }

                ++$chunkCounter;
                if ($chunkCounter >= self::CHUNK_SIZE) {
                    $this->em->flush();
                    $this->em->clear();
                    $chunkCounter = 0;
                }
            }

            if ($chunkCounter > 0) {
                $this->em->flush();
            }

            $session->complete($success, $skipped, $errors);
            $this->em->persist($session);
            $this->em->flush();

            return ['success' => $success, 'skipped' => $skipped, 'error' => $errors];
        } finally {
            $this->bulkContext->setBulk(false);
        }
    }

    private function safeDivide(float $a, float $b): ?float
    {
        if (0.0 === $b) {
            return null;
        }

        return $a / $b;
    }

    private function safeModulo(float $a, float $b): ?float
    {
        if (0.0 === $b) {
            return null;
        }

        return fmod($a, $b);
    }
}
