<?php

declare(strict_types=1);

namespace App\Tests\Api\Import;

use App\Shared\Domain\Tenant;
use App\Tests\Api\Catalog\CatalogApiTestCase;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Component\HttpFoundation\File\UploadedFile;

use const JSON_THROW_ON_ERROR;

/**
 * Covers POST /api/import-sessions/parse-preview, the wizard's Step 1
 * → Step 2 transition. The controller is the only place that exposes
 * FileParserService over HTTP — without this, the admin used a
 * CSV-only in-browser parser and emitted a "__xlsx__" sentinel for
 * Excel uploads. The regression case is the xlsx round-trip below.
 */
final class ParsePreviewApiTest extends CatalogApiTestCase
{
    #[Test]
    public function csvUploadReturnsHeadersSampleRowsAndDetectedDelimiter(): void
    {
        $csvPath = $this->writeCsv();

        try {
            $client = $this->authenticatedClient();
            $client->request('POST', '/api/import-sessions/parse-preview', [
                'extra' => [
                    'parameters' => [
                        'encoding' => 'auto',
                        'delimiter' => 'auto',
                    ],
                    'files' => [
                        'file' => new UploadedFile($csvPath, 'sample.csv', 'text/csv', null, true),
                    ],
                ],
            ]);

            self::assertResponseIsSuccessful();
            $body = $this->decodeJson($client->getResponse()?->getContent());

            self::assertSame(['sku', 'name', 'price'], $body['headers']);
            $sampleRows = $body['sample_rows'];
            self::assertIsArray($sampleRows);
            self::assertCount(2, $sampleRows);
            $firstRow = $sampleRows[0];
            self::assertIsArray($firstRow);
            self::assertSame('ABC-001', $firstRow[0]);
            self::assertSame(2, $body['total_rows']);
            self::assertSame(';', $body['delimiter']);
            self::assertSame('utf-8', $body['encoding']);
            self::assertNull($body['sheet_name']);
            self::assertFalse($body['had_multiple_sheets']);
        } finally {
            @unlink($csvPath);
        }
    }

    #[Test]
    public function parsePreviewRejectsTooManyRowsWith422(): void
    {
        // IMP2-2.7 (#1483) — the wizard preview enforces the per-tenant row limit
        // (here 1) so the user sees a clear 422 before mapping/start.
        $em = $this->em();
        $tenant = $em->getRepository(Tenant::class)->findOneBy(['code' => self::TENANT_CODE]);
        \assert($tenant instanceof Tenant);
        $tenant->setImportMaxRows(1);
        $em->flush();

        $csvPath = $this->writeCsv(); // 2 data rows > limit 1
        try {
            $client = $this->authenticatedClient();
            $client->request('POST', '/api/import-sessions/parse-preview', [
                'extra' => [
                    'parameters' => [],
                    'files' => ['file' => new UploadedFile($csvPath, 'sample.csv', 'text/csv', null, true)],
                ],
            ]);
            self::assertResponseStatusCodeSame(422);
        } finally {
            @unlink($csvPath);
        }
    }

    #[Test]
    public function xlsxUploadReturnsRealHeadersInsteadOfSentinel(): void
    {
        $xlsxPath = $this->writeXlsx();

        try {
            $client = $this->authenticatedClient();
            $client->request('POST', '/api/import-sessions/parse-preview', [
                'extra' => [
                    'parameters' => [],
                    'files' => [
                        'file' => new UploadedFile(
                            $xlsxPath,
                            'sample.xlsx',
                            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                            null,
                            true,
                        ),
                    ],
                ],
            ]);

            self::assertResponseIsSuccessful();
            $body = $this->decodeJson($client->getResponse()?->getContent());

            self::assertSame(['sku', 'name', 'price'], $body['headers']);
            $sampleRows = $body['sample_rows'];
            self::assertIsArray($sampleRows);
            self::assertCount(2, $sampleRows);
            $firstRow = $sampleRows[0];
            self::assertIsArray($firstRow);
            self::assertSame('XLSX-1', $firstRow[0]);
            self::assertSame(2, $body['total_rows']);
            self::assertNull($body['delimiter']);
            self::assertNotNull($body['sheet_name']);
        } finally {
            @unlink($xlsxPath);
        }
    }

    #[Test]
    public function rejectsRequestWithoutFile(): void
    {
        $client = $this->authenticatedClient();
        $client->request('POST', '/api/import-sessions/parse-preview', [
            'extra' => [
                'parameters' => [],
                'files' => [],
            ],
        ]);

        self::assertResponseStatusCodeSame(400);
    }

    /**
     * @return array<mixed>
     */
    private function decodeJson(?string $raw): array
    {
        self::assertNotNull($raw);
        $decoded = json_decode($raw, true, 512, JSON_THROW_ON_ERROR);
        self::assertIsArray($decoded);

        return $decoded;
    }

    private function writeCsv(): string
    {
        $contents = "sku;name;price\nABC-001;Wkręt M6;9.99\nXYZ-002;Śruba;14.50\n";
        $path = tempnam(sys_get_temp_dir(), 'pim-parse-preview-');
        \assert(false !== $path);
        $renamed = $path.'.csv';
        rename($path, $renamed);
        file_put_contents($renamed, $contents);

        return $renamed;
    }

    private function writeXlsx(): string
    {
        $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Products');
        $sheet->fromArray([
            ['sku', 'name', 'price'],
            ['XLSX-1', 'Pneumatic valve', 199.0],
            ['XLSX-2', 'Sensor', 249.5],
        ], null, 'A1');

        $path = tempnam(sys_get_temp_dir(), 'pim-parse-preview-');
        \assert(false !== $path);
        $renamed = $path.'.xlsx';
        rename($path, $renamed);

        $writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet);
        $writer->save($renamed);

        return $renamed;
    }
}
