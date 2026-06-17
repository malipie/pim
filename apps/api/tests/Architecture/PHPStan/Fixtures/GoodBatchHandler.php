<?php

declare(strict_types=1);

namespace App\Tests\Architecture\PHPStan\Fixtures;

use App\Shared\Application\AbstractBatchHandler;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/**
 * Green: subclass of AbstractBatchHandler — exempt. The base class guarantees
 * clear() via flushAndClear(), so even a raw in-loop flush is tolerated. MUST
 * NOT be flagged (mirrors ImportRunHandler / RebuildAttributesIndexedHandler).
 */
#[AsMessageHandler]
final class GoodBatchHandler extends AbstractBatchHandler
{
    /**
     * @param list<object> $rows
     */
    public function __invoke(array $rows): void
    {
        $processed = 0;
        foreach ($rows as $row) {
            $this->entityManager->persist($row);
            if ($this->shouldFlush(++$processed)) {
                $this->flushAndClear();
            }
            $this->entityManager->flush(); // tolerated — AbstractBatchHandler exempt
        }
    }
}
