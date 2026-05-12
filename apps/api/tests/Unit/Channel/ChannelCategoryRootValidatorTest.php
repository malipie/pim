<?php

declare(strict_types=1);

namespace App\Tests\Unit\Channel;

use App\Catalog\Application\Query\GetObjectSummary\GetObjectSummaryHandler;
use App\Catalog\Domain\Entity\CatalogObject;
use App\Catalog\Domain\Entity\ObjectType;
use App\Catalog\Domain\ObjectKind;
use App\Catalog\Domain\Repository\CatalogObjectRepositoryInterface;
use App\Channel\Domain\Entity\Channel;
use App\Channel\Infrastructure\Doctrine\EventListener\ChannelCategoryRootValidator;
use App\Shared\Domain\Tenant;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Event\PrePersistEventArgs;
use InvalidArgumentException;
use LogicException;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\Stub;
use PHPUnit\Framework\TestCase;
use stdClass;
use Symfony\Component\Uid\Uuid;

final class ChannelCategoryRootValidatorTest extends TestCase
{
    private EntityManagerInterface&Stub $em;

    protected function setUp(): void
    {
        $this->em = $this->createStub(EntityManagerInterface::class);
    }

    #[Test]
    public function nullRootIsAllowed(): void
    {
        $channel = new Channel('shop', ['pl' => 'Sklep']);
        $validator = new ChannelCategoryRootValidator($this->summaryHandler());

        $validator->prePersist(new PrePersistEventArgs($channel, $this->em));

        self::assertNull($channel->getCategoryTreeRootId());
    }

    #[Test]
    public function categoryRootIsAccepted(): void
    {
        $tenant = new Tenant('demo', 'Demo');
        $type = new ObjectType('category', ObjectKind::Category, ['pl' => 'Kategoria']);
        $type->assignTenant($tenant);
        $root = new CatalogObject($type, 'root');
        $root->assignTenant($tenant);

        $channel = new Channel('shop', ['pl' => 'Sklep']);
        $channel->attachCategoryTreeRoot($root->getId());

        $validator = new ChannelCategoryRootValidator($this->summaryHandler($root));

        $validator->prePersist(new PrePersistEventArgs($channel, $this->em));

        self::assertSame($root->getId(), $channel->getCategoryTreeRootId());
    }

    #[Test]
    public function productRootThrows(): void
    {
        $tenant = new Tenant('demo', 'Demo');
        $type = new ObjectType('product', ObjectKind::Product, ['pl' => 'Produkt']);
        $type->assignTenant($tenant);
        $root = new CatalogObject($type, 'SKU-1');
        $root->assignTenant($tenant);

        $channel = new Channel('shop', ['pl' => 'Sklep']);
        $channel->attachCategoryTreeRoot($root->getId());

        $validator = new ChannelCategoryRootValidator($this->summaryHandler($root));

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('kind=category');
        $validator->prePersist(new PrePersistEventArgs($channel, $this->em));
    }

    #[Test]
    public function unknownRootThrows(): void
    {
        $channel = new Channel('shop', ['pl' => 'Sklep']);
        $channel->attachCategoryTreeRoot(Uuid::v7());

        $validator = new ChannelCategoryRootValidator($this->summaryHandler());

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('does not exist');
        $validator->prePersist(new PrePersistEventArgs($channel, $this->em));
    }

    #[Test]
    public function nonChannelEntityIsIgnored(): void
    {
        $unrelated = new stdClass();
        $validator = new ChannelCategoryRootValidator($this->summaryHandler());

        $validator->prePersist(new PrePersistEventArgs($unrelated, $this->em));

        self::assertTrue(true);
    }

    private function summaryHandler(?CatalogObject $object = null): GetObjectSummaryHandler
    {
        $repo = new InMemoryCatalogObjectRepoForValidator();
        if (null !== $object) {
            $repo->store($object);
        }

        return new GetObjectSummaryHandler($repo);
    }
}

/**
 * Stand-in repository — only `findById` is exercised through the validator.
 */
final class InMemoryCatalogObjectRepoForValidator implements CatalogObjectRepositoryInterface
{
    /** @var array<string, CatalogObject> */
    private array $objects = [];

    public function store(CatalogObject $object): void
    {
        $this->objects[$object->getId()->toRfc4122()] = $object;
    }

    public function findById(Uuid $id): ?CatalogObject
    {
        return $this->objects[$id->toRfc4122()] ?? null;
    }

    public function findByIds(array $idsRfc4122): array
    {
        throw new LogicException('not used in this test');
    }

    public function findByCode(string $code, ObjectKind $kind, Tenant $tenant): ?CatalogObject
    {
        throw new LogicException('not used in this test');
    }

    public function findByKind(ObjectKind $kind, Tenant $tenant): array
    {
        throw new LogicException('not used in this test');
    }

    public function save(CatalogObject $object): void
    {
        throw new LogicException('not used in this test');
    }

    public function remove(CatalogObject $object): void
    {
        throw new LogicException('not used in this test');
    }
}
