<?php

declare(strict_types=1);

namespace App\Tests\Unit\Shared\Infrastructure\Http;

use App\Shared\Infrastructure\Http\RequestBodySizeLimitListener;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;

/**
 * W2-9 / AUD-045 — unit coverage for the JSON request-body size cap.
 *
 * The cap is exercised with an explicitly injected byte limit rather than a
 * global `.env.test` override: a low *global* cap would 413 every other Api
 * test that legitimately ships a body larger than the cap (the first cut set
 * `.env.test` to 1024 and broke four unrelated Api tests). The end-to-end
 * wiring through the kernel is proven by the live smoke recorded on the PR;
 * here we assert the listener's decision logic in isolation.
 */
final class RequestBodySizeLimitListenerTest extends TestCase
{
    private const int CAP = 64;

    #[Test]
    public function oversizedJsonWriteIsRejectedWith413ProblemJson(): void
    {
        $event = $this->event($this->jsonRequest('/api/products/bulk-edit', 'POST', self::CAP + 200));

        new RequestBodySizeLimitListener(self::CAP)->onRequest($event);

        $response = $event->getResponse();
        self::assertNotNull($response);
        self::assertSame(413, $response->getStatusCode());
        self::assertSame('application/problem+json', $response->headers->get('content-type'));
    }

    #[Test]
    public function bodyAtOrBelowCapPassesThrough(): void
    {
        $event = $this->event($this->jsonRequest('/api/products/bulk-edit', 'POST', 8));

        new RequestBodySizeLimitListener(self::CAP)->onRequest($event);

        self::assertNull($event->getResponse());
    }

    #[Test]
    public function multipartUploadIsExempt(): void
    {
        $event = $this->event($this->jsonRequest('/api/assets', 'POST', self::CAP + 500, 'multipart/form-data; boundary=x'));

        new RequestBodySizeLimitListener(self::CAP)->onRequest($event);

        self::assertNull($event->getResponse());
    }

    #[Test]
    public function importSurfaceIsExempt(): void
    {
        $event = $this->event($this->jsonRequest('/api/import-sessions', 'POST', self::CAP + 500));

        new RequestBodySizeLimitListener(self::CAP)->onRequest($event);

        self::assertNull($event->getResponse());
    }

    #[Test]
    public function assetUploadPathIsExempt(): void
    {
        $event = $this->event($this->jsonRequest('/api/assets/upload', 'POST', self::CAP + 500));

        new RequestBodySizeLimitListener(self::CAP)->onRequest($event);

        self::assertNull($event->getResponse());
    }

    #[Test]
    public function nonApiPathIsIgnored(): void
    {
        $event = $this->event($this->jsonRequest('/admin/whatever', 'POST', self::CAP + 500));

        new RequestBodySizeLimitListener(self::CAP)->onRequest($event);

        self::assertNull($event->getResponse());
    }

    #[Test]
    public function bodylessVerbIsIgnored(): void
    {
        // A GET with an oversized body is not a write — never capped.
        $event = $this->event($this->jsonRequest('/api/products', 'GET', self::CAP + 500));

        new RequestBodySizeLimitListener(self::CAP)->onRequest($event);

        self::assertNull($event->getResponse());
    }

    private function event(Request $request): RequestEvent
    {
        return new RequestEvent(
            $this->createStub(HttpKernelInterface::class),
            $request,
            HttpKernelInterface::MAIN_REQUEST,
        );
    }

    private function jsonRequest(string $path, string $method, int $bodyBytes, string $contentType = 'application/json'): Request
    {
        return Request::create(
            $path,
            $method,
            server: ['CONTENT_TYPE' => $contentType],
            content: str_repeat('a', $bodyBytes),
        );
    }
}
