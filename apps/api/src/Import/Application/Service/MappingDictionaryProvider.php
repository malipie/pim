<?php

declare(strict_types=1);

namespace App\Import\Application\Service;

interface MappingDictionaryProvider
{
    /**
     * @return array<string, list<string>>
     */
    public function load(): array;

    /**
     * @return array<string, string>
     */
    public function aliasIndex(): array;
}
