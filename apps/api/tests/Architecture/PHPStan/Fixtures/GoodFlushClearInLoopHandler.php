<?php

declare(strict_types=1);

namespace App\Tests\Architecture\PHPStan\Fixtures;

use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/**
 * Green: Messenger handler that pairs flush() with clear() inside the loop —
 * the sanctioned manual pattern. MUST NOT be flagged.
 */
#[AsMessageHandler]
final class GoodFlushClearInLoopHandler
{
    public function __construct(private readonly EntityManagerInterface $em)
    {
    }

    /**
     * @param list<object> $rows
     */
    public function __invoke(array $rows): void
    {
        $i = 0;
        foreach ($rows as $row) {
            $this->em->persist($row);
            if (0 === ++$i % 200) {
                $this->em->flush();
                $this->em->clear();
            }
        }
        $this->em->flush();
    }
}
