<?php

declare(strict_types=1);

namespace App\Tests\Unit\Identity\Infrastructure\Http;

use App\Identity\Domain\Exception\PermissionDeniedException;
use App\Identity\Infrastructure\Http\PermissionDeniedProblemListener;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;

use const JSON_THROW_ON_ERROR;

final class PermissionDeniedProblemListenerTest extends TestCase
{
    #[Test]
    public function emitsRfc7807ProblemDetailsWithPermissionRequired(): void
    {
        $kernel = $this->createStub(HttpKernelInterface::class);
        $event = new ExceptionEvent(
            $kernel,
            new Request(),
            HttpKernelInterface::MAIN_REQUEST,
            new PermissionDeniedException('products.edit'),
        );

        new PermissionDeniedProblemListener()->onException($event);

        $response = $event->getResponse();
        self::assertInstanceOf(JsonResponse::class, $response);
        self::assertSame(403, $response->getStatusCode());
        self::assertSame('application/problem+json', $response->headers->get('content-type'));

        $content = $response->getContent();
        self::assertIsString($content);
        /** @var array<string, mixed> $payload */
        $payload = json_decode($content, true, flags: JSON_THROW_ON_ERROR);
        self::assertSame('https://docs.pim.dev/errors/permission-denied', $payload['type']);
        self::assertSame('Permission denied', $payload['title']);
        self::assertSame(403, $payload['status']);
        self::assertSame('products.edit', $payload['permission_required']);
        self::assertIsString($payload['detail']);
        self::assertStringContainsString('products.edit', $payload['detail']);
    }

    #[Test]
    public function nonPermissionDeniedExceptionsPassThrough(): void
    {
        $kernel = $this->createStub(HttpKernelInterface::class);
        $event = new ExceptionEvent(
            $kernel,
            new Request(),
            HttpKernelInterface::MAIN_REQUEST,
            new RuntimeException('something else'),
        );

        new PermissionDeniedProblemListener()->onException($event);

        self::assertNull($event->getResponse());
    }
}
