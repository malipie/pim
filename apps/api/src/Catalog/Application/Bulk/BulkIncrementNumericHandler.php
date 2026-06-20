<?php

declare(strict_types=1);

namespace App\Catalog\Application\Bulk;

use App\Catalog\Application\BulkContext;
use App\Catalog\Application\Lock\AttributeLockReader;
use App\Catalog\Application\Reindex\BulkReindexQueueInterface;
use App\Catalog\Domain\Entity\BulkLog;
use App\Catalog\Domain\Entity\BulkSession;
use App\Catalog\Domain\Entity\CatalogObject;
use App\Catalog\Domain\Repository\CatalogObjectRepositoryInterface;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

/**
 * VIEW-13 (#545) — `increment_numeric` bulk action.
 *
 * Applies an arithmetic operator (+ - * / %) to a numeric attribute
 * across N products. Cmd+K killer use case (PRD §3.5): `price *= 1.10`.
 * Skips rows where the current value is not numeric (warning log).
 * Locked attributes (VIEW-33 / PRD §8.3) skip with a warning entry.
 *
 * Division-by-zero raises a per-row error log (no-op) rather than
 * aborting the batch. Shared lifecycle: {@see AbstractBulkHandler}.
 */
final class BulkIncrementNumericHandler extends AbstractBulkHandler
{
    private string $attrCode = '';
    private string $operator = '+';
    private float $operand = 0.0;

    public function __construct(
        CatalogObjectRepositoryInterface $catalogObjects,
        EntityManagerInterface $em,
        BulkContext $bulkContext,
        private readonly AttributeLockReader $lockReader,
        BulkReindexQueueInterface $reindexQueue,
    ) {
        parent::__construct($catalogObjects, $em, $bulkContext, $reindexQueue);
    }

    /**
     * @return array{success: int, skipped: int, error: int}
     */
    public function handle(BulkSession $session, string $attrCode, string $operator, float $operand): array
    {
        if (!\in_array($operator, ['+', '-', '*', '/', '%'], true)) {
            throw new BadRequestHttpException(\sprintf('Unsupported operator "%s".', $operator));
        }

        $this->attrCode = $attrCode;
        $this->operator = $operator;
        $this->operand = $operand;

        return $this->runBatch($session);
    }

    protected function processObject(CatalogObject $object, BulkSession $session, BulkCounters $counters): void
    {
        if ($this->lockReader->isLocked($object, $this->attrCode)) {
            ++$counters->skipped;
            $this->em->persist(new BulkLog(
                $session->getId(),
                $object->getId(),
                null,
                $object->getAttributesIndexed()[$this->attrCode] ?? null,
                $object->getAttributesIndexed()[$this->attrCode] ?? null,
                BulkLog::LEVEL_WARNING,
                'Attribute locked',
            ));

            return;
        }

        $indexed = $object->getAttributesIndexed();
        $oldValue = $indexed[$this->attrCode] ?? null;

        if (!is_numeric($oldValue)) {
            ++$counters->skipped;
            $this->em->persist(new BulkLog(
                $session->getId(),
                $object->getId(),
                null,
                $oldValue,
                $oldValue,
                BulkLog::LEVEL_WARNING,
                'Value is not numeric',
            ));

            return;
        }

        $current = (float) $oldValue;
        $newValue = match ($this->operator) {
            '+' => $current + $this->operand,
            '-' => $current - $this->operand,
            '*' => $current * $this->operand,
            '/' => $this->safeDivide($current, $this->operand),
            '%' => $this->safeModulo($current, $this->operand),
            default => null,
        };

        if (null === $newValue) {
            ++$counters->error;
            $this->em->persist(new BulkLog(
                $session->getId(),
                $object->getId(),
                null,
                $oldValue,
                null,
                BulkLog::LEVEL_ERROR,
                'Division by zero',
            ));

            return;
        }

        $indexed[$this->attrCode] = $newValue;
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
        ++$counters->success;
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
