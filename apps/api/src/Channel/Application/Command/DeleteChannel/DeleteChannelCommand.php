<?php

declare(strict_types=1);

namespace App\Channel\Application\Command\DeleteChannel;

use Symfony\Component\Uid\Uuid;

final readonly class DeleteChannelCommand
{
    public function __construct(public Uuid $id)
    {
    }
}
