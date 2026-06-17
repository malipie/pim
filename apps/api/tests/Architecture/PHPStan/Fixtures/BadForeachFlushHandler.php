<?php

declare(strict_types=1);

namespace App\Tests\Architecture\PHPStan\Fixtures;

use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/**
 * Red-demonstration: Messenger handler that flush()es inside a foreach with NO
 * clear() — the exact FrankenPHP OOM pattern §3.10 forbids. MUST be flagged.
 */
#[AsMessageHandler]
final class BadForeachFlushHandler
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
            $this->em->flush(); // flagged: flush in loop, no clear
        }
    }
}
