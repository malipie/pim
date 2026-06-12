<?php

declare(strict_types=1);

namespace App\Import\Domain\Entity;

use App\Catalog\Domain\Entity\ObjectType;
use App\Import\Domain\Enum\ImportImageSource;
use App\Import\Domain\Enum\ImportMode;
use App\Shared\Application\TenantScoped;
use App\Shared\Domain\AggregateRoot;
use App\Shared\Domain\Tenant;
use DateTimeImmutable;
use LogicException;
use Symfony\Component\Uid\Uuid;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Per-user saved configuration for the import wizard ("smart memory").
 *
 * Editing a profile only affects future imports; historical
 * {@see ImportSession} rows do not pull mapping changes from their
 * profile retroactively (decision: spec §5.8).
 */
class ImportProfile extends AggregateRoot implements TenantScoped
{
    private Uuid $id;

    private ?Tenant $tenant = null;

    private Uuid $userId;

    #[Assert\NotBlank]
    #[Assert\Length(max: 255)]
    private string $name;

    #[Assert\NotBlank]
    #[Assert\Length(max: 64)]
    #[Assert\Regex(pattern: '/^[a-z0-9-]+$/', message: 'Code must contain only lowercase letters, digits, and dashes.')]
    private string $code = '';

    private string $mode = ImportMode::Upsert->value;

    /** Attribute code (type=identifier) used as the row match key; null = objects.code / SKU (ADR-0019 D1). */
    private ?string $matchAttributeCode = null;

    private ObjectType $targetObjectType;

    /**
     * Map of source column header → target attribute id (or "skip").
     * Stored as JSONB so the worker can apply it without a join lookup.
     *
     * @var array<string, string>
     */
    private array $columnMapping = [];

    private ?string $locale = null;

    private ?string $encoding = null;

    private ?string $delimiter = null;

    private string $imageSource = ImportImageSource::None->value;

    private ?string $imageZipNamingConvention = null;

    /**
     * Reserved for IMP-03+. Cross-attribute rules are out of scope MVP
     * (spec §7.5) — kept as JSONB for forward compatibility.
     *
     * @var array<string, mixed>|null
     */
    private ?array $customValidationRules = null;

    private ?DateTimeImmutable $lastUsedAt = null;

    private DateTimeImmutable $createdAt;

    public function __construct(
        Uuid $userId,
        string $name,
        ObjectType $targetObjectType,
        ?Uuid $id = null,
        ?string $code = null,
        ImportMode $mode = ImportMode::Upsert,
    ) {
        $this->id = $id ?? Uuid::v7();
        $this->userId = $userId;
        $this->name = $name;
        $this->code = $code ?? self::slugify($name);
        $this->mode = $mode->value;
        $this->targetObjectType = $targetObjectType;
        $this->createdAt = new DateTimeImmutable();
    }

    /**
     * Stable lower-case slug derived from a free-form name.
     * Public so the API input layer can pre-compute the same value
     * before persistence (lets the FE preview the final `code`).
     */
    public static function slugify(string $value): string
    {
        $lower = mb_strtolower($value);
        $stripped = preg_replace('/[^a-z0-9]+/', '-', $lower) ?? $lower;
        $collapsed = preg_replace('/-+/', '-', $stripped) ?? $stripped;

        return trim($collapsed, '-');
    }

    public function getId(): Uuid
    {
        return $this->id;
    }

    public function getTenant(): ?Tenant
    {
        return $this->tenant;
    }

    /**
     * @internal stamped by TenantAssignmentListener on prePersist
     */
    public function assignTenant(Tenant $tenant): void
    {
        if (null !== $this->tenant) {
            throw new LogicException('Tenant is already assigned and cannot be reassigned.');
        }
        $this->tenant = $tenant;
    }

    public function getUserId(): Uuid
    {
        return $this->userId;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function rename(string $name): void
    {
        $this->name = $name;
    }

    public function getCode(): string
    {
        return $this->code;
    }

    public function setCode(string $code): void
    {
        $this->code = $code;
    }

    public function getMode(): ImportMode
    {
        return ImportMode::from($this->mode);
    }

    public function getMatchAttributeCode(): ?string
    {
        return $this->matchAttributeCode;
    }

    public function setMatchAttributeCode(?string $code): void
    {
        $this->matchAttributeCode = $code;
    }

    public function setMode(ImportMode $mode): void
    {
        $this->mode = $mode->value;
    }

    public function getTargetObjectType(): ObjectType
    {
        return $this->targetObjectType;
    }

    /**
     * @return array<string, string>
     */
    public function getColumnMapping(): array
    {
        return $this->columnMapping;
    }

    /**
     * @param array<string, string> $columnMapping
     */
    public function setColumnMapping(array $columnMapping): void
    {
        $this->columnMapping = $columnMapping;
    }

    public function getLocale(): ?string
    {
        return $this->locale;
    }

    public function setLocale(?string $locale): void
    {
        $this->locale = $locale;
    }

    public function getEncoding(): ?string
    {
        return $this->encoding;
    }

    public function setEncoding(?string $encoding): void
    {
        $this->encoding = $encoding;
    }

    public function getDelimiter(): ?string
    {
        return $this->delimiter;
    }

    public function setDelimiter(?string $delimiter): void
    {
        $this->delimiter = $delimiter;
    }

    public function getImageSource(): ImportImageSource
    {
        return ImportImageSource::from($this->imageSource);
    }

    public function setImageSource(ImportImageSource $source): void
    {
        $this->imageSource = $source->value;
    }

    public function getImageZipNamingConvention(): ?string
    {
        return $this->imageZipNamingConvention;
    }

    public function setImageZipNamingConvention(?string $convention): void
    {
        $this->imageZipNamingConvention = $convention;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function getCustomValidationRules(): ?array
    {
        return $this->customValidationRules;
    }

    /**
     * @param array<string, mixed>|null $rules
     */
    public function setCustomValidationRules(?array $rules): void
    {
        $this->customValidationRules = $rules;
    }

    public function getLastUsedAt(): ?DateTimeImmutable
    {
        return $this->lastUsedAt;
    }

    public function touchLastUsed(): void
    {
        $this->lastUsedAt = new DateTimeImmutable();
    }

    public function getCreatedAt(): DateTimeImmutable
    {
        return $this->createdAt;
    }
}
