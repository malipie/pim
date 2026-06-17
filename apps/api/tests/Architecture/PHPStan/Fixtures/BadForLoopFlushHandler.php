<?php

declare(strict_types=1);

namespace App\Tests\Architecture\PHPStan\Fixtures;

use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/**
 * Red-demonstration: `for` loop variant (not just foreach) — same offence,
 * also flagged.
 */
#[AsMessageHandler]
final class BadForLoopFlushHandler
{
    public function __construct(private readonly EntityManagerInterface $em)
    {
    }

    /**
     * @param list<object> $rows
     */
    public function __invoke(array $rows): void
    {
        for ($i = 0, $n = \count($rows); $i < $n; ++$i) {
            $this->em->persist($rows[$i]);
            $this->em->flush(); // flagged
        }
    }
}
