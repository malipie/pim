<?php

declare(strict_types=1);

namespace App\Tests\Architecture\PHPStan\Fixtures;

use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/**
 * Green: Messenger handler that accumulates then flushes ONCE after the loop —
 * the flush is not a descendant of the loop node, so it is correct. MUST NOT be
 * flagged (mirrors CreateAttributeHandler / ReorderGroupAttributesHandler).
 */
#[AsMessageHandler]
final class GoodFlushAfterLoopHandler
{
    public function __construct(private readonly EntityManagerInterface $em)
    {
    }

    /**
     * @param list<object> $rows
     */
    public function __invoke(array $rows): void
    {
        foreach ($rows as $row) {
            $this->em->persist($row);
        }
        $this->em->flush();
    }
}
