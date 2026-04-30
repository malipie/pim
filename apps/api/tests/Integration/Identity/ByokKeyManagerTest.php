<?php

declare(strict_types=1);

namespace App\Tests\Integration\Identity;

use App\Identity\Application\ByokKeyManager;
use App\Identity\Domain\Repository\TenantAgentConfigRepositoryInterface;
use App\Shared\Application\TenantContext;
use App\Shared\Domain\Tenant;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Zenstruck\Foundry\Test\Factories;
use Zenstruck\Foundry\Test\ResetDatabase;

/**
 * End-to-end coverage for the BYOK lifecycle (#107 / 0.11.12):
 * set → resolve → disable → resolve-after-disable.
 */
final class ByokKeyManagerTest extends KernelTestCase
{
    use Factories;
    use ResetDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        if (!\function_exists('sodium_crypto_aead_aes256gcm_is_available')
            || !sodium_crypto_aead_aes256gcm_is_available()) {
            self::markTestSkipped('AES-256-GCM not available on this host.');
        }
    }

    #[Test]
    public function setKeyEncryptsAndStoresWithDisplayPrefix(): void
    {
        $tenant = $this->seedTenant();
        $manager = $this->byok();

        $config = $manager->setKey($tenant, 'sk-ant-api03-secret-1234567890');

        self::assertSame('sk-ant-a', $config->getKeyPrefix());
        self::assertSame(1, $config->getEncryptionKeyVersion());
        self::assertNotEmpty($config->getAnthropicApiKeyEncrypted());
        // Plaintext must not appear anywhere in the stored ciphertext.
        self::assertStringNotContainsString('secret-1234567890', $config->getAnthropicApiKeyEncrypted());
    }

    #[Test]
    public function resolveKeyReturnsPlaintextForBoundTenant(): void
    {
        $tenant = $this->seedTenant();
        $manager = $this->byok();

        $manager->setKey($tenant, 'sk-ant-resolve-flow-test');
        $resolved = $manager->resolveKey($tenant);

        self::assertSame('sk-ant-resolve-flow-test', $resolved);
    }

    #[Test]
    public function resolveKeyReturnsNullWhenNoConfig(): void
    {
        $tenant = $this->seedTenant();
        $manager = $this->byok();

        self::assertNull($manager->resolveKey($tenant));
    }

    #[Test]
    public function disableHidesTheKeyFromResolver(): void
    {
        $tenant = $this->seedTenant();
        $manager = $this->byok();

        $manager->setKey($tenant, 'sk-ant-disable-flow');
        self::assertNotNull($manager->resolveKey($tenant));

        $manager->disable($tenant);
        self::assertNull($manager->resolveKey($tenant), 'Disabled BYOK must fall through to the platform key.');

        // Re-setting the key re-enables the row in place.
        $manager->setKey($tenant, 'sk-ant-after-reenable');
        self::assertSame('sk-ant-after-reenable', $manager->resolveKey($tenant));
    }

    #[Test]
    public function rotateOverwritesPreviousCiphertext(): void
    {
        $tenant = $this->seedTenant();
        $manager = $this->byok();

        $manager->setKey($tenant, 'sk-ant-original-key');
        $first = $this->repo()->findForTenant($tenant)?->getAnthropicApiKeyEncrypted();
        \assert(\is_string($first));

        $manager->setKey($tenant, 'sk-ant-rotated-key');
        $second = $this->repo()->findForTenant($tenant)?->getAnthropicApiKeyEncrypted();

        self::assertNotSame($first, $second, 'Ciphertext must change on rotation.');
        self::assertSame('sk-ant-rotated-key', $manager->resolveKey($tenant));
    }

    private function seedTenant(): Tenant
    {
        $em = $this->em();
        $tenant = new Tenant('byok-demo', 'BYOK Demo');
        $em->persist($tenant);
        $em->flush();
        self::getContainer()->get(TenantContext::class)->set($tenant);

        return $tenant;
    }

    private function byok(): ByokKeyManager
    {
        $svc = self::getContainer()->get(ByokKeyManager::class);
        self::assertInstanceOf(ByokKeyManager::class, $svc);

        return $svc;
    }

    private function repo(): TenantAgentConfigRepositoryInterface
    {
        $repo = self::getContainer()->get(TenantAgentConfigRepositoryInterface::class);
        self::assertInstanceOf(TenantAgentConfigRepositoryInterface::class, $repo);

        return $repo;
    }

    private function em(): EntityManagerInterface
    {
        $em = self::getContainer()->get('doctrine')->getManager();
        \assert($em instanceof EntityManagerInterface);

        return $em;
    }
}
