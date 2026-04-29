<?php

declare(strict_types=1);

namespace App\Tests\Unit\Catalog;

use App\Catalog\Domain\Entity\CatalogObject;
use App\Catalog\Domain\Entity\ObjectType;
use App\Catalog\Domain\ObjectKind;
use App\Catalog\Infrastructure\Doctrine\EventListener\CategoryPathValidator;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Event\PrePersistEventArgs;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use stdClass;

final class CategoryPathValidatorTest extends TestCase
{
    private CategoryPathValidator $validator;
    private EntityManagerInterface $em;

    protected function setUp(): void
    {
        $this->validator = new CategoryPathValidator();
        $this->em = $this->createStub(EntityManagerInterface::class);
    }

    #[Test]
    public function nullPathPassesForAnyKind(): void
    {
        foreach (ObjectKind::cases() as $kind) {
            $object = $this->makeObject($kind, code: 'X', path: null);
            $this->validator->prePersist(new PrePersistEventArgs($object, $this->em));
        }

        // No exception — the listener stays out of the way for null paths.
        self::assertTrue(true);
    }

    #[Test]
    public function productWithPathThrows(): void
    {
        $object = $this->makeObject(ObjectKind::Product, 'SKU-1', 'root.men');

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('path is only valid for kind=category');
        $this->validator->prePersist(new PrePersistEventArgs($object, $this->em));
    }

    #[Test]
    public function assetWithPathThrows(): void
    {
        $object = $this->makeObject(ObjectKind::Asset, 'IMG-1', 'media.images');

        $this->expectException(InvalidArgumentException::class);
        $this->validator->prePersist(new PrePersistEventArgs($object, $this->em));
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function validLtreePaths(): iterable
    {
        yield 'single label' => ['root'];
        yield 'two labels' => ['root.men'];
        yield 'three labels' => ['root.men.shoes'];
        yield 'underscores' => ['root_node.child_one'];
        yield 'digits after first letter' => ['root.men2.shoes3'];
        yield 'leading underscore' => ['_internal.docs'];
    }

    #[Test]
    #[DataProvider('validLtreePaths')]
    public function categoryWithValidPathPasses(string $path): void
    {
        $object = $this->makeObject(ObjectKind::Category, 'cat', $path);

        $this->validator->prePersist(new PrePersistEventArgs($object, $this->em));

        self::assertSame($path, $object->getPath());
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function invalidLtreePaths(): iterable
    {
        yield 'leading digit' => ['1root.men'];
        yield 'spaces' => ['root.men shoes'];
        yield 'hyphens' => ['root.men-shoes'];
        yield 'empty label' => ['root..men'];
        yield 'trailing dot' => ['root.men.'];
    }

    #[Test]
    #[DataProvider('invalidLtreePaths')]
    public function categoryWithMalformedPathThrows(string $path): void
    {
        $object = $this->makeObject(ObjectKind::Category, 'cat', $path);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid ltree path format');
        $this->validator->prePersist(new PrePersistEventArgs($object, $this->em));
    }

    #[Test]
    public function nonCatalogObjectIsIgnored(): void
    {
        $unrelated = new stdClass();

        $this->validator->prePersist(new PrePersistEventArgs($unrelated, $this->em));

        // No exception, no side-effect.
        self::assertTrue(true);
    }

    private function makeObject(ObjectKind $kind, string $code, ?string $path): CatalogObject
    {
        $type = new ObjectType($kind->value, $kind, ['pl' => 'X']);
        $object = new CatalogObject($type, $code);
        $object->attachToPath($path);

        return $object;
    }
}
