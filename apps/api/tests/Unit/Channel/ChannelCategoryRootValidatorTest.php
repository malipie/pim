<?php

declare(strict_types=1);

namespace App\Tests\Unit\Channel;

use App\Catalog\Domain\Entity\CatalogObject;
use App\Catalog\Domain\Entity\ObjectType;
use App\Catalog\Domain\ObjectKind;
use App\Channel\Domain\Entity\Channel;
use App\Channel\Infrastructure\Doctrine\EventListener\ChannelCategoryRootValidator;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Event\PrePersistEventArgs;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use stdClass;

final class ChannelCategoryRootValidatorTest extends TestCase
{
    private ChannelCategoryRootValidator $validator;
    private EntityManagerInterface $em;

    protected function setUp(): void
    {
        $this->validator = new ChannelCategoryRootValidator();
        $this->em = $this->createStub(EntityManagerInterface::class);
    }

    #[Test]
    public function nullRootIsAllowed(): void
    {
        $channel = new Channel('shop', ['pl' => 'Sklep']);

        $this->validator->prePersist(new PrePersistEventArgs($channel, $this->em));

        self::assertTrue(true);
    }

    #[Test]
    public function categoryRootIsAccepted(): void
    {
        $channel = new Channel('shop', ['pl' => 'Sklep']);
        $type = new ObjectType('category', ObjectKind::Category, ['pl' => 'Kategoria']);
        $root = new CatalogObject($type, 'root');
        $channel->attachCategoryTreeRoot($root);

        $this->validator->prePersist(new PrePersistEventArgs($channel, $this->em));

        self::assertSame($root, $channel->getCategoryTreeRoot());
    }

    #[Test]
    public function productRootThrows(): void
    {
        $channel = new Channel('shop', ['pl' => 'Sklep']);
        $type = new ObjectType('product', ObjectKind::Product, ['pl' => 'Produkt']);
        $root = new CatalogObject($type, 'SKU-1');
        $channel->attachCategoryTreeRoot($root);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('kind=category');
        $this->validator->prePersist(new PrePersistEventArgs($channel, $this->em));
    }

    #[Test]
    public function nonChannelEntityIsIgnored(): void
    {
        $unrelated = new stdClass();

        $this->validator->prePersist(new PrePersistEventArgs($unrelated, $this->em));

        self::assertTrue(true);
    }
}
