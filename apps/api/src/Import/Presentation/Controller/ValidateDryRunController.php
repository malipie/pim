<?php

declare(strict_types=1);

namespace App\Import\Presentation\Controller;

use App\Catalog\Domain\Entity\ObjectType;
use App\Catalog\Domain\Repository\ObjectTypeRepositoryInterface;
use App\Identity\Contracts\Attribute\RequiresPermission;
use App\Import\Application\Service\ImportValidationService;
use App\Import\Domain\Enum\FileEncoding;
use App\Import\Domain\Exception\InvalidImportFileException;
use App\Import\Domain\ValueObject\ValidationError;
use App\Import\Domain\ValueObject\ValidationResult;
use InvalidArgumentException;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Uid\Uuid;

/**
 * IMP-03 (#444) wizard Step 3 — uploads the file again as multipart,
 * runs the validation pass without committing anything, and returns
 * `{success_count, error_count, errors[]}` for the preview UI.
 *
 * No `import_session_id` is created here — the dry run is stateless
 * by design (spec §5.4: "wynik dry-run"). Step 4 confirm posts the
 * mapping again to the persisting endpoint that lands in IMP-04.
 */
final class ValidateDryRunController
{
    private const int MAX_TOP_ERRORS = 10;

    public function __construct(
        private readonly ImportValidationService $validator,
        private readonly ObjectTypeRepositoryInterface $objectTypes,
    ) {
    }

    #[Route(
        path: '/api/import-sessions/validate-dry-run',
        name: 'imports_validate_dry_run',
        methods: ['POST'],
    )]
    #[RequiresPermission(module: 'imports', action: 'run')]
    public function __invoke(Request $request): JsonResponse
    {
        $file = $request->files->get('file');
        if (!$file instanceof UploadedFile) {
            throw new BadRequestHttpException('"file" multipart field is required.');
        }

        $rawTargetId = (string) $request->request->get('target_object_type_id', '');
        if ('' === $rawTargetId) {
            throw new BadRequestHttpException('"target_object_type_id" field is required.');
        }
        try {
            $targetId = Uuid::fromString($rawTargetId);
        } catch (InvalidArgumentException) {
            throw new BadRequestHttpException(\sprintf('Invalid target_object_type_id "%s".', $rawTargetId));
        }

        $objectType = $this->objectTypes->findById($targetId);
        if (!$objectType instanceof ObjectType) {
            throw new NotFoundHttpException(\sprintf('ObjectType "%s" was not found.', $rawTargetId));
        }

        $rawMapping = (string) $request->request->get('mapping', '{}');
        $decoded = json_decode($rawMapping, true);
        if (!\is_array($decoded)) {
            throw new BadRequestHttpException('"mapping" must be a JSON object {column_header: attribute_code}.');
        }

        $columnMapping = [];
        foreach ($decoded as $key => $value) {
            $columnMapping[(string) $key] = \is_string($value) ? $value : 'skip';
        }

        $encoding = $this->parseEncoding($request->request->get('encoding'));
        $delimiter = $this->parseDelimiter($request->request->get('delimiter'));

        // ImportRowReader dispatches by extension via pathinfo(), but
        // FrankenPHP / PHP-FPM write multipart uploads to /tmp/phpXXXX
        // with no extension. Copy to a temp path that preserves the
        // client's original extension before validating (same dance as
        // ParsePreviewController) — without this the wizard's Step 3
        // browser path 400s with "Unsupported import file extension".
        $extension = strtolower($file->getClientOriginalExtension());
        if ('' === $extension) {
            throw new BadRequestHttpException('Uploaded file must have an extension (.csv or .xlsx).');
        }
        $tempPath = tempnam(sys_get_temp_dir(), 'pim_dry_run_');
        if (false === $tempPath) {
            throw new BadRequestHttpException('Failed to allocate a temp file for validation.');
        }
        $finalPath = $tempPath.'.'.$extension;
        if (!@rename($tempPath, $finalPath)) {
            @unlink($tempPath);
            throw new BadRequestHttpException('Failed to prepare a temp file for validation.');
        }
        if (!@copy($file->getPathname(), $finalPath)) {
            @unlink($finalPath);
            throw new BadRequestHttpException('Failed to copy uploaded file for validation.');
        }

        try {
            $result = $this->validator->validate(
                absolutePath: $finalPath,
                columnMapping: $columnMapping,
                target: $objectType,
                encodingOverride: $encoding,
                delimiterOverride: $delimiter,
            );
        } catch (InvalidImportFileException $exception) {
            throw new BadRequestHttpException($exception->getMessage(), $exception);
        } finally {
            @unlink($finalPath);
        }

        return new JsonResponse($this->serialise($result), Response::HTTP_OK);
    }

    /**
     * @return array<string, mixed>
     */
    private function serialise(ValidationResult $result): array
    {
        return [
            'total_rows' => $result->totalRows,
            'success_count' => $result->successCount,
            'error_count' => $result->errorCount,
            'top_errors' => array_map(
                static fn (ValidationError $error): array => [
                    'row_number' => $error->rowNumber,
                    'sku' => $error->sku,
                    'error_type' => $error->errorType->value,
                    'level' => $error->level->value,
                    'message' => $error->message,
                    'column_name' => $error->columnName,
                    'column_value' => $error->columnValue,
                ],
                \array_slice($result->errors, 0, self::MAX_TOP_ERRORS),
            ),
            'all_errors' => array_map(
                static fn (ValidationError $error): array => [
                    'row_number' => $error->rowNumber,
                    'sku' => $error->sku,
                    'error_type' => $error->errorType->value,
                    'level' => $error->level->value,
                    'message' => $error->message,
                    'column_name' => $error->columnName,
                    'column_value' => $error->columnValue,
                ],
                $result->errors,
            ),
        ];
    }

    private function parseEncoding(mixed $raw): ?FileEncoding
    {
        if (!\is_string($raw) || '' === $raw || 'auto' === $raw) {
            return null;
        }

        return FileEncoding::tryFrom($raw);
    }

    private function parseDelimiter(mixed $raw): ?string
    {
        if (!\is_string($raw) || '' === $raw || 'auto' === $raw) {
            return null;
        }
        if ('tab' === $raw) {
            return "\t";
        }

        return $raw;
    }
}
