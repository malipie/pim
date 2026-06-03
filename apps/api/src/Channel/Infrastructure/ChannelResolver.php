<?php

declare(strict_types=1);

namespace App\Channel\Infrastructure;

use App\Channel\Contracts\ChannelResolverInterface;
use App\Channel\Domain\Repository\ChannelRepositoryInterface;
use App\Shared\Domain\Tenant;
use Symfony\Component\Uid\Uuid;

/**
 * {@see ChannelResolverInterface} backed by the channel repository.
 */
final readonly class ChannelResolver implements ChannelResolverInterface
{
    public function __construct(
        private ChannelRepositoryInterface $channels,
    ) {
    }

    public function resolveId(string $code, Tenant $tenant): ?Uuid
    {
        return $this->channels->findByCode($code, $tenant)?->getId();
    }
}
