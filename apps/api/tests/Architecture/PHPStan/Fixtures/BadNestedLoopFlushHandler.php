<?php

declare(strict_types=1);

namespace App\Tests\Architecture\PHPStan\Fixtures;

use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/**
 * Red-demonstration: nested loops, no clear anywhere — reported exactly once
 * (on the inner flush line), not once per nesting level.
 */
#[AsMessageHandler]
final class BadNestedLoopFlushHandler
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
                $this->em->flush(); // flagged once
            }
        }
    }
}
