<?php

declare(strict_types=1);

namespace App\Tests\Architecture\PHPStan\Fixtures;

use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/**
 * Green: nested loops where clear() lives in the OUTER loop subtree (after the
 * inner loop). The clear in an enclosing loop discharges the inner flush — the
 * identity map is detached each outer iteration. MUST NOT be flagged.
 */
#[AsMessageHandler]
final class GoodNestedLoopWithClearHandler
{
    public function __construct(private readonly EntityManagerInterface $em)
    {
    }

    /**
     * @param list<list<object>> $batches
     */
    public function __invoke(array $batches): void
    {
        foreach ($batches as $batch) {
            foreach ($batch as $row) {
                $this->em->persist($row);
                $this->em->flush();
            }
            $this->em->clear();
        }
    }
}
