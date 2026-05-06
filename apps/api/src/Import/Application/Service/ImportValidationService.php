<?php

declare(strict_types=1);

namespace App\Import\Application\Service;

use App\Catalog\Domain\AttributeType;
use App\Catalog\Domain\Entity\Attribute;
use App\Catalog\Domain\Entity\ObjectType;
use App\Catalog\Domain\ObjectKind;
use App\Catalog\Domain\Repository\AttributeRepositoryInterface;
use App\Catalog\Domain\Repository\CatalogObjectRepositoryInterface;
use App\Import\Domain\Enum\FileEncoding;
use App\Import\Domain\Enum\ImportErrorType;
use App\Import\Domain\Enum\ImportLogLevel;
use App\Import\Domain\Exception\InvalidImportFileException;
use App\Import\Domain\ValueObject\ValidationError;
use App\Import\Domain\ValueObject\ValidationResult;
use App\Shared\Application\TenantContext;
use App\Shared\Domain\Tenant;
use Generator;
use League\Csv\Reader;
use LogicException;
use PhpOffice\PhpSpreadsheet\IOFactory;

use const PATHINFO_EXTENSION;

/**
 * Iterates the uploaded file row-by-row and produces per-row validation
 * findings without persisting anything. Powers the wizard's Step 3
 * preview ("247 OK, 33 errors") and the post-async report flow in IMP-05.
 *
 * Required + unique fields per spec §7.5 — hardcoded to `sku` + `name`
 * (required) and `sku` + `ean` (unique-in-file). DB-side uniqueness on
 * SKU is checked against {@see CatalogObjectRepositoryInterface} scoped
 * to the active tenant. Custom validation rules + cross-attribute
 * checks are out of MVP scope (spec §7.5).
 */
final readonly class ImportValidationService
{
    /** @var list<string> */
    private const array REQUIRED_ATTRIBUTE_CODES = ['sku', 'name'];

    private const string SKU_ATTRIBUTE_CODE = 'sku';

    private const int SAMPLE_LIMIT = 100;

    public function __construct(
        private AttributeRepositoryInterface $attributes,
        private CatalogObjectRepositoryInterface $catalogObjects,
        private TenantContext $tenantContext,
        private EncodingDetector $encodingDetector,
        private DelimiterDetector $delimiterDetector,
    ) {
    }

    /**
     * @param array<string, string> $columnMapping header → attribute_code (or "skip")
     */
    public function validate(
        string $absolutePath,
        array $columnMapping,
        ObjectType $target,
        ?FileEncoding $encodingOverride = null,
        ?string $delimiterOverride = null,
    ): ValidationResult {
        $tenant = $this->tenantContext->get();
        if (!$tenant instanceof Tenant) {
            throw new LogicException('Tenant context must be set for import validation.');
        }

        $extension = strtolower(pathinfo($absolutePath, PATHINFO_EXTENSION));
        if (!\in_array($extension, ['xlsx', 'csv'], true)) {
            throw InvalidImportFileException::unsupportedExtension($extension);
        }

        $attributesByCode = $this->loadAttributesByCode($tenant, $columnMapping);
        $errors = [];
        $successCount = 0;
        $errorCount = 0;
        $totalRows = 0;
        $skuSeenInFile = [];

        $rows = 'xlsx' === $extension
            ? $this->iterateXlsx($absolutePath)
            : $this->iterateCsv($absolutePath, $encodingOverride, $delimiterOverride);

        foreach ($rows as $rowNumber => $cells) {
            ++$totalRows;
            $rowErrors = $this->validateRow(
                rowNumber: $rowNumber,
                cells: $cells,
                columnMapping: $columnMapping,
                attributesByCode: $attributesByCode,
                target: $target,
                tenant: $tenant,
                skuSeenInFile: $skuSeenInFile,
            );

            if ([] === $rowErrors) {
                ++$successCount;
            } else {
                ++$errorCount;
                foreach ($rowErrors as $error) {
                    if (\count($errors) < self::SAMPLE_LIMIT) {
                        $errors[] = $error;
                    }
                }
            }
        }

        return new ValidationResult(
            totalRows: $totalRows,
            successCount: $successCount,
            errorCount: $errorCount,
            errors: $errors,
        );
    }

    /**
     * @param array<string, string|null> $cells            column_header → value
     * @param array<string, string>      $columnMapping
     * @param array<string, Attribute>   $attributesByCode
     * @param array<string, int>         $skuSeenInFile    mutable; tracks duplicates within the file
     *
     * @return list<ValidationError>
     */
    private function validateRow(
        int $rowNumber,
        array $cells,
        array $columnMapping,
        array $attributesByCode,
        ObjectType $target,
        Tenant $tenant,
        array &$skuSeenInFile,
    ): array {
        $errors = [];

        // Materialise attribute_code → value for the current row.
        $valueByAttribute = [];
        foreach ($columnMapping as $columnHeader => $attributeCode) {
            if ('skip' === $attributeCode || '' === $attributeCode) {
                continue;
            }
            $valueByAttribute[$attributeCode] = $cells[$columnHeader] ?? null;
        }

        $sku = $valueByAttribute[self::SKU_ATTRIBUTE_CODE] ?? null;

        foreach (self::REQUIRED_ATTRIBUTE_CODES as $requiredCode) {
            $value = $valueByAttribute[$requiredCode] ?? null;
            if (null === $value || '' === $value) {
                $errors[] = new ValidationError(
                    rowNumber: $rowNumber,
                    sku: $sku,
                    errorType: ImportErrorType::MissingRequired,
                    level: ImportLogLevel::Error,
                    message: \sprintf('Missing required attribute "%s".', $requiredCode),
                    columnName: $requiredCode,
                    columnValue: $value,
                );
            }
        }

        if (null !== $sku && '' !== $sku) {
            if (isset($skuSeenInFile[$sku])) {
                $errors[] = new ValidationError(
                    rowNumber: $rowNumber,
                    sku: $sku,
                    errorType: ImportErrorType::DuplicateSkuInFile,
                    level: ImportLogLevel::Warning,
                    message: \sprintf('SKU "%s" already appears in the file at row %d.', $sku, $skuSeenInFile[$sku]),
                    columnName: 'sku',
                    columnValue: $sku,
                );
            } else {
                $skuSeenInFile[$sku] = $rowNumber;
                if (null !== $this->catalogObjects->findByCode($sku, ObjectKind::Product, $tenant)) {
                    $errors[] = new ValidationError(
                        rowNumber: $rowNumber,
                        sku: $sku,
                        errorType: ImportErrorType::DuplicateSkuInDb,
                        level: ImportLogLevel::Warning,
                        message: \sprintf('SKU "%s" already exists in the catalog.', $sku),
                        columnName: 'sku',
                        columnValue: $sku,
                    );
                }
            }
        }

        foreach ($valueByAttribute as $attributeCode => $value) {
            if (null === $value || '' === $value) {
                continue;
            }
            $attribute = $attributesByCode[$attributeCode] ?? null;
            if (!$attribute instanceof Attribute) {
                continue;
            }
            $typeError = $this->validateScalarType($attribute, $value);
            if (null !== $typeError) {
                $errors[] = new ValidationError(
                    rowNumber: $rowNumber,
                    sku: $sku,
                    errorType: ImportErrorType::InvalidType,
                    level: ImportLogLevel::Error,
                    message: $typeError,
                    columnName: $attributeCode,
                    columnValue: $value,
                );
            }
        }

        return $errors;
    }

    private function validateScalarType(Attribute $attribute, string $value): ?string
    {
        return match ($attribute->getType()) {
            AttributeType::Number, AttributeType::Price, AttributeType::Metric => is_numeric(str_replace(',', '.', $value))
                    ? null
                    : \sprintf('"%s" is not a valid number for "%s".', $value, $attribute->getCode()),
            AttributeType::Date => false === strtotime($value)
                ? \sprintf('"%s" is not a valid date for "%s".', $value, $attribute->getCode())
                : null,
            AttributeType::Boolean => \in_array(strtolower($value), ['0', '1', 'true', 'false', 'yes', 'no', 'tak', 'nie'], true)
                ? null
                : \sprintf('"%s" is not a valid boolean for "%s".', $value, $attribute->getCode()),
            default => null,
        };
    }

    /**
     * @param array<string, string> $columnMapping
     *
     * @return array<string, Attribute>
     */
    private function loadAttributesByCode(Tenant $tenant, array $columnMapping): array
    {
        $codes = [];
        foreach ($columnMapping as $attributeCode) {
            if ('skip' !== $attributeCode && '' !== $attributeCode) {
                $codes[$attributeCode] = true;
            }
        }
        if ([] === $codes) {
            return [];
        }

        $attributes = [];
        foreach (array_keys($codes) as $code) {
            $attribute = $this->attributes->findByCode($code, $tenant);
            if ($attribute instanceof Attribute) {
                $attributes[$attribute->getCode()] = $attribute;
            }
        }

        return $attributes;
    }

    /**
     * @return Generator<int, array<string, string|null>>
     */
    private function iterateXlsx(string $absolutePath): Generator
    {
        $reader = IOFactory::createReaderForFile($absolutePath);
        $reader->setReadDataOnly(true);
        $spreadsheet = $reader->load($absolutePath);
        $sheet = $spreadsheet->getSheet(0);

        $headers = [];
        $rowNumber = 0;
        foreach ($sheet->getRowIterator() as $row) {
            ++$rowNumber;
            $cells = [];
            foreach ($row->getCellIterator() as $cell) {
                $value = $cell->getValue();
                $cells[] = null === $value ? null : (string) (\is_scalar($value) ? $value : '');
            }
            if (1 === $rowNumber) {
                $headers = array_map(static fn (?string $v): string => $v ?? '', $cells);
                continue;
            }
            $assoc = [];
            foreach ($headers as $index => $header) {
                $assoc[$header] = $cells[$index] ?? null;
            }
            yield $rowNumber - 1 => $assoc;
        }
    }

    /**
     * @return Generator<int, array<string, string|null>>
     */
    private function iterateCsv(string $absolutePath, ?FileEncoding $encodingOverride, ?string $delimiterOverride): Generator
    {
        $bytes = file_get_contents($absolutePath);
        if (false === $bytes) {
            throw InvalidImportFileException::unreadable($absolutePath);
        }

        $encoding = $encodingOverride ?? $this->encodingDetector->detect($bytes);
        $body = $this->encodingDetector->stripBom($bytes);
        if (FileEncoding::Utf8 !== $encoding && FileEncoding::Utf8Bom !== $encoding) {
            $converted = @iconv($encoding->iconvName(), 'UTF-8//TRANSLIT', $body);
            if (false === $converted) {
                throw InvalidImportFileException::corruptedEncoding($encoding->value);
            }
            $body = $converted;
        }

        $delimiter = $delimiterOverride ?? $this->delimiterDetector->detect(substr($body, 0, 4096));
        $reader = Reader::fromString($body);
        $reader->setDelimiter($delimiter);
        $reader->setHeaderOffset(0);

        $rowNumber = 1;
        foreach ($reader->getRecords() as $record) {
            ++$rowNumber;
            $assoc = [];
            foreach ($record as $key => $value) {
                if (null === $value || '' === $value) {
                    $assoc[(string) $key] = null;
                } else {
                    $assoc[(string) $key] = \is_scalar($value) ? (string) $value : '';
                }
            }
            yield $rowNumber - 1 => $assoc;
        }
    }
}
