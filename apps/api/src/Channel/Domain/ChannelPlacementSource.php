<?php

declare(strict_types=1);

namespace App\Channel\Domain;

/**
 * Provenance of an {@see Entity\ObjectChannelPlacement}
 * (CHC-02). `manual` placements are operator-set and win over `auto` ones
 * created by node-mapping auto-assignment (CHC-07).
 */
enum ChannelPlacementSource: string
{
    case Manual = 'manual';
    case Auto = 'auto';
}
