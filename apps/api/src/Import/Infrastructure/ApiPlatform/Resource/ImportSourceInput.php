<?php

declare(strict_types=1);

namespace App\Import\Infrastructure\ApiPlatform\Resource;

use Symfony\Component\Validator\Constraints as Assert;

final class ImportSourceInput
{
    #[Assert\NotBlank]
    #[Assert\Length(max: 255)]
    public string $name = '';

    #[Assert\NotBlank]
    #[Assert\Length(max: 64)]
    #[Assert\Regex(pattern: '/^[a-z0-9-]+$/', message: 'Code must contain only lowercase letters, digits, and dashes.')]
    public string $code = '';

    /**
     * One of: `sftp`, `ftp`, `http`, `folder`, `webhook`, `api`, `upload`.
     */
    #[Assert\NotBlank]
    #[Assert\Choice(choices: ['sftp', 'ftp', 'http', 'folder', 'webhook', 'api', 'upload'])]
    public string $type = '';

    public ?string $host = null;

    public ?string $path = null;

    #[Assert\Length(max: 255)]
    public ?string $filePattern = null;

    #[Assert\Length(max: 128)]
    public ?string $authRef = null;

    #[Assert\Range(min: 30, max: 86400)]
    public ?int $pollIntervalSec = null;

    public bool $autotrigger = false;

    /**
     * Optional — UUID of an existing {@see \App\Import\Domain\Entity\ImportProfile}
     * the operator wants to attach to incoming files.
     */
    #[Assert\Uuid]
    public ?string $profileId = null;
}
