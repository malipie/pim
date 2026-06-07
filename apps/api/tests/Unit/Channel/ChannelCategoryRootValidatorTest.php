<?php

declare(strict_types=1);

namespace App\Tests\Unit\Channel;

use App\Channel\Domain\Entity\Channel;
use App\Channel\Domain\Entity\ChannelCategoryNode;
use App\Channel\Domain\Repository\ChannelCategoryNodeRepositoryInterface;
use App\Channel\Infrastructure\Doctrine\EventListener\ChannelCategoryRootValidator;
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
        $channel = new Channel('shop', 'Sklep');
        $validator = new ChannelCategoryRootValidator(new InMemoryChannelCategoryNodeRepo());

        $validator->prePersist(new PrePersistEventArgs($channel, $this->em));

        self::assertNull($channel->getCategoryTreeRootId());
    }

    #[Test]
    public function rootNodeOfSameChannelIsAccepted(): void
    {
        $channel = new Channel('shop', 'Sklep');
        $root = new ChannelCategoryNode($channel, 'root', ['pl' => 'Root']);
        $channel->attachCategoryTreeRoot($root->getId());

        $repo = new InMemoryChannelCategoryNodeRepo();
        $repo->store($root);
        $validator = new ChannelCategoryRootValidator($repo);

        $validator->prePersist(new PrePersistEventArgs($channel, $this->em));

        self::assertSame($root->getId(), $channel->getCategoryTreeRootId());
    }

    #[Test]
    public function unknownRootThrows(): void
    {
        $channel = new Channel('shop', 'Sklep');
        $channel->attachCategoryTreeRoot(Uuid::v7());

        $validator = new ChannelCategoryRootValidator(new InMemoryChannelCategoryNodeRepo());

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('does not exist');
        $validator->prePersist(new PrePersistEventArgs($channel, $this->em));
    }

    #[Test]
    public function nodeFromAnotherChannelThrows(): void
    {
        $channelA = new Channel('a', 'A');
        $channelB = new Channel('b', 'B');
        $foreignRoot = new ChannelCategoryNode($channelB, 'root', ['pl' => 'Root']);
        $channelA->attachCategoryTreeRoot($foreignRoot->getId());

        $repo = new InMemoryChannelCategoryNodeRepo();
        $repo->store($foreignRoot);
        $validator = new ChannelCategoryRootValidator($repo);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('different channel');
        $validator->prePersist(new PrePersistEventArgs($channelA, $this->em));
    }

    #[Test]
    public function nonRootNodeThrows(): void
    {
        $channel = new Channel('shop', 'Sklep');
        $parent = new ChannelCategoryNode($channel, 'root', ['pl' => 'Root']);
        $child = new ChannelCategoryNode($channel, 'child', ['pl' => 'Child'], $parent);
        $channel->attachCategoryTreeRoot($child->getId());

        $repo = new InMemoryChannelCategoryNodeRepo();
        $repo->store($child);
        $validator = new ChannelCategoryRootValidator($repo);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('must be a tree root');
        $validator->prePersist(new PrePersistEventArgs($channel, $this->em));
    }

    #[Test]
    public function nonChannelEntityIsIgnored(): void
    {
        $unrelated = new stdClass();
        $validator = new ChannelCategoryRootValidator(new InMemoryChannelCategoryNodeRepo());

        $validator->prePersist(new PrePersistEventArgs($unrelated, $this->em));

        self::assertTrue(true);
    }
}

/**
 * Stand-in repository — only `findById` is exercised through the validator.
 */
final class InMemoryChannelCategoryNodeRepo implements ChannelCategoryNodeRepositoryInterface
{
    /** @var array<string, ChannelCategoryNode> */
    private array $nodes = [];

    public function store(ChannelCategoryNode $node): void
    {
        $this->nodes[$node->getId()->toRfc4122()] = $node;
    }

    public function findById(Uuid $id): ?ChannelCategoryNode
    {
        return $this->nodes[$id->toRfc4122()] ?? null;
    }

    public function findRootForChannel(Channel $channel): ?ChannelCategoryNode
    {
        throw new LogicException('not used in this test');
    }

    public function findAllForChannel(Channel $channel): array
    {
        throw new LogicException('not used in this test');
    }

    public function save(ChannelCategoryNode $node): void
    {
        throw new LogicException('not used in this test');
    }

    public function remove(ChannelCategoryNode $node): void
    {
        throw new LogicException('not used in this test');
    }
}
