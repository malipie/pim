<?php

declare(strict_types=1);

namespace App\Tests\Unit\Integration\Generic\Application\Mapping;

use App\Integration\Generic\Application\Mapping\MappingValidator;
use App\Integration\Generic\Domain\Entity\Connection;
use App\Integration\Generic\Domain\Entity\FieldMapping;
use App\Integration\Generic\Domain\Entity\RemoteEndpoint;
use App\Integration\Generic\Domain\Entity\RemoteField;
use App\Integration\Generic\Domain\Enum\MappingDirection;
use App\Integration\Generic\Domain\Enum\RemoteEndpointRole;
use App\Integration\Generic\Domain\Enum\RemoteFieldDataType;
use App\Integration\Generic\Domain\Repository\FieldMappingRepositoryInterface;
use App\Integration\Generic\Domain\Repository\RemoteEndpointRepositoryInterface;
use App\Integration\Generic\Domain\Repository\RemoteFieldRepositoryInterface;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class MappingValidatorTest extends TestCase
{
    private Connection $connection;
    private RemoteEndpoint $endpoint;

    protected function setUp(): void
    {
        $this->connection = new Connection('idosell', 'IdoSell', 'https://api.idosell.com');
        $this->endpoint = new RemoteEndpoint(
            $this->connection,
            RemoteEndpointRole::ReadList,
            'GET',
            '/products',
        );
    }

    #[Test]
    public function inboundWithoutMatchKeyIsAnError(): void
    {
        $mapping = new FieldMapping($this->connection, 'sku', '$.sku', MappingDirection::Inbound);

        $result = $this->validate([$mapping], [$this->field('$.sku', RemoteFieldDataType::String)]);

        self::assertFalse($result->isValid());
        self::assertCount(1, $result->errors);
        self::assertStringContainsString('match key', $result->errors[0]);
    }

    #[Test]
    public function inboundWithMatchKeyIsValid(): void
    {
        $matchKey = new FieldMapping($this->connection, 'sku', '$.sku', MappingDirection::Inbound);
        $matchKey->setMatchKey(true);
        $name = new FieldMapping($this->connection, 'name', '$.name', MappingDirection::Inbound);

        $result = $this->validate(
            [$matchKey, $name],
            [$this->field('$.sku', RemoteFieldDataType::String), $this->field('$.name', RemoteFieldDataType::String)],
        );

        self::assertTrue($result->isValid());
        self::assertSame([], $result->warnings);
    }

    #[Test]
    public function outboundOnlyNeedsNoMatchKey(): void
    {
        $mapping = new FieldMapping($this->connection, 'sku', '$.sku', MappingDirection::Outbound);

        $result = $this->validate([$mapping], [$this->field('$.sku', RemoteFieldDataType::String)]);

        self::assertTrue($result->isValid());
    }

    #[Test]
    public function compositeRemoteFieldIsAWarning(): void
    {
        $matchKey = new FieldMapping($this->connection, 'sku', '$.sku', MappingDirection::Inbound);
        $matchKey->setMatchKey(true);
        $tags = new FieldMapping($this->connection, 'tags', '$.tags', MappingDirection::Inbound);

        $result = $this->validate(
            [$matchKey, $tags],
            [$this->field('$.sku', RemoteFieldDataType::String), $this->field('$.tags', RemoteFieldDataType::Array)],
        );

        self::assertTrue($result->isValid());
        self::assertCount(1, $result->warnings);
        self::assertSame('tags', $result->warnings[0]->pimTarget);
        self::assertStringContainsString('array', $result->warnings[0]->message);
    }

    #[Test]
    public function unknownPathIsAWarning(): void
    {
        $matchKey = new FieldMapping($this->connection, 'sku', '$.sku', MappingDirection::Inbound);
        $matchKey->setMatchKey(true);
        $ghost = new FieldMapping($this->connection, 'mystery', '$.does.not.exist', MappingDirection::Inbound);

        $result = $this->validate([$matchKey, $ghost], [$this->field('$.sku', RemoteFieldDataType::String)]);

        self::assertCount(1, $result->warnings);
        self::assertStringContainsString('not in the discovered schema', $result->warnings[0]->message);
    }

    /**
     * @param list<FieldMapping> $mappings
     * @param list<RemoteField>  $fields
     */
    private function validate(array $mappings, array $fields): \App\Integration\Generic\Application\Mapping\MappingValidationResult
    {
        $mappingRepo = $this->createStub(FieldMappingRepositoryInterface::class);
        $mappingRepo->method('findByConnection')->willReturn($mappings);

        $endpointRepo = $this->createStub(RemoteEndpointRepositoryInterface::class);
        $endpointRepo->method('findByConnection')->willReturn([$this->endpoint]);

        $fieldRepo = $this->createStub(RemoteFieldRepositoryInterface::class);
        $fieldRepo->method('findByEndpoint')->willReturn($fields);

        return new MappingValidator($mappingRepo, $endpointRepo, $fieldRepo)->validate($this->connection);
    }

    private function field(string $path, RemoteFieldDataType $type): RemoteField
    {
        return new RemoteField($this->endpoint, $path, $type);
    }
}
