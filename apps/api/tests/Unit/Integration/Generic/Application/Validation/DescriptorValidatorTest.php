<?php

declare(strict_types=1);

namespace App\Tests\Unit\Integration\Generic\Application\Validation;

use App\Integration\Generic\Application\Validation\DescriptorValidator;
use App\Integration\Generic\Domain\Exception\InvalidDescriptorException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class DescriptorValidatorTest extends TestCase
{
    #[Test]
    #[DataProvider('validBaseUrls')]
    public function acceptsAbsoluteHttpUrls(string $baseUrl): void
    {
        $this->expectNotToPerformAssertions();
        new DescriptorValidator()->assertValidBaseUrl($baseUrl);
    }

    #[Test]
    #[DataProvider('invalidBaseUrls')]
    public function rejectsNonHttpOrSchemelessBaseUrls(string $baseUrl): void
    {
        $this->expectException(InvalidDescriptorException::class);
        new DescriptorValidator()->assertValidBaseUrl($baseUrl);
    }

    #[Test]
    #[DataProvider('validPaths')]
    public function acceptsRelativePathTemplates(string $path): void
    {
        $this->expectNotToPerformAssertions();
        new DescriptorValidator()->assertValidPathTemplate($path);
    }

    #[Test]
    #[DataProvider('invalidPaths')]
    public function rejectsPathTemplatesThatCanOverrideTheHost(string $path): void
    {
        $this->expectException(InvalidDescriptorException::class);
        new DescriptorValidator()->assertValidPathTemplate($path);
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function validBaseUrls(): iterable
    {
        yield 'https' => ['https://api.idosell.com'];
        yield 'http with port + path' => ['http://api.example.com:8080/v2'];
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function invalidBaseUrls(): iterable
    {
        yield 'empty' => [''];
        yield 'file scheme' => ['file:///etc/passwd'];
        yield 'gopher scheme' => ['gopher://evil/x'];
        yield 'schemeless host' => ['api.example.com/v2'];
        yield 'scheme without host' => ['https:///v2'];
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function validPaths(): iterable
    {
        yield 'leading slash' => ['/products/{id}'];
        yield 'relative segment' => ['products'];
        yield 'query template' => ['/products?since={cursor}'];
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function invalidPaths(): iterable
    {
        yield 'embedded scheme/host' => ['https://169.254.169.254/latest'];
        yield 'protocol-relative' => ['//evil.example.com/x'];
        yield 'backslash' => ['\\\\evil\\share'];
    }
}
