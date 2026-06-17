<?php

declare(strict_types=1);

namespace App\Tests\Architecture\PHPStan\Fixtures;

use Doctrine\ORM\EntityManagerInterface;

/**
 * Green: NOT a Messenger handler — a synchronous request-scoped service that
 * flushes inside a small bounded loop. Out of scope by §3.10 wording; clearing
 * the unit of work here would detach entities the caller still holds. MUST NOT
 * be flagged (mirrors ObjectRelationService::replaceForSourceAndAttribute).
 */
final class GoodSyncServiceWithLoopFlush
{
    public function __construct(private readonly EntityManagerInterface $em)
    {
    }

    /**
     * @param list<object> $rows
     */
    public function replace(array $rows): void
    {
        foreach ($rows as $row) {
            $this->em->persist($row);
            $this->em->flush();
        }
    }
}
