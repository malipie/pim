<?php

declare(strict_types=1);

namespace App\Tests\Api\Import;

use App\Catalog\Domain\Repository\AttributeRepositoryInterface;
use App\Channel\Domain\Entity\Locale;
use App\Channel\Domain\Entity\TenantLocale;
use App\Shared\Application\TenantContext;
use App\Shared\Domain\Tenant;
use App\Tests\Api\Catalog\CatalogApiTestCase;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\Response;

use const JSON_THROW_ON_ERROR;

/**
 * Structural imports (attribute / attribute-group definitions) operate on
 * bounded-small configuration data, so they always run INLINE — never through
 * the async Messenger worker. Regression guard for the XLSX/large-file path
 * that previously forced async dispatch and 500'd when the Doctrine transport
 * could not be set up.
 */
final class StartStructuralImportApiTest extends CatalogApiTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $tenant = $this->em()->getRepository(Tenant::class)->findOneBy(['code' => self::TENANT_CODE]);
        \assert($tenant instanceof Tenant);
        self::getContainer()->get(TenantContext::class)->set($tenant);
        foreach ([['pl_PL', 'Polski', 'pl'], ['en_US', 'English', 'en']] as [$code, $label, $lang]) {
            $locale = new Locale($code, $label, null, $lang);
            $this->em()->persist($locale);
            $this->em()->persist(new TenantLocale($locale));
        }
        $this->em()->flush();
    }

    #[Test]
    public function largeStructuralImportRunsInlineNotAsync(): void
    {
        // 55 rows — above the legacy 50-row sync threshold. The structural
        // path must still run inline (HTTP 200, terminal status), not return
        // 202 Accepted and hand off to the worker.
        $rows = "code;type;label.en\n";
        for ($i = 1; $i <= 55; ++$i) {
            $rows .= \sprintf("struct_attr_%d;text;Attr %d\n", $i, $i);
        }
        $csvPath = tempnam(sys_get_temp_dir(), 'struct-import-').'.csv';
        file_put_contents($csvPath, $rows);

        try {
            $client = $this->authenticatedClient();
            $client->request('POST', '/api/structural-import-sessions', [
                'extra' => [
                    'parameters' => ['structural_kind' => 'attributes'],
                    'files' => ['file' => new UploadedFile($csvPath, 'attrs.csv', 'text/csv', null, true)],
                ],
            ]);

            $response = $client->getResponse();
            self::assertNotNull($response);
            $content = $response->getContent();
            self::assertSame(
                Response::HTTP_OK,
                $response->getStatusCode(),
                \sprintf('Structural import must run inline (200), got %d: %s', $response->getStatusCode(), substr($content, 0, 400)),
            );
            $body = json_decode($content, true, 512, JSON_THROW_ON_ERROR);
            self::assertIsArray($body);
            self::assertSame('success', $body['status']);
            self::assertSame('attributes', $body['structural_kind']);
            self::assertSame(55, $body['success_count']);

            $tenant = $this->em()->getRepository(Tenant::class)->findOneBy(['code' => self::TENANT_CODE]);
            \assert($tenant instanceof Tenant);
            $attributes = self::getContainer()->get(AttributeRepositoryInterface::class);
            self::assertNotNull($attributes->findByCode('struct_attr_1', $tenant));
            self::assertNotNull($attributes->findByCode('struct_attr_55', $tenant));
        } finally {
            @unlink($csvPath);
        }
    }
}
