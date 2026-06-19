<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure\Http;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * W2-9 / AUD-045 — application-level cap on JSON request bodies.
 *
 * The defence-in-depth chain against an oversized-body DoS ends at:
 *   Caddy (request body 150 MB) → php.ini `post_max_size` (110 MB) → the app.
 * Before this listener the *only* application-level size limit lived on the
 * import path (`Tenant::importMaxFileSize`, enforced per upload in
 * StartImportController / ParsePreviewController). Every other JSON write
 * endpoint — most dangerously POST `/api/products/bulk-edit`, which
 * `json_decode`s the whole body up front — accepted any body up to
 * `post_max_size`. A 109 MB JSON document decodes into a PHP structure many
 * times larger than the 256 MB FrankenPHP worker `memory_limit`, OOM-killing
 * the long-lived worker process (a single 240 KB probe on bulk-edit is fully
 * decoded before validation — the vector scales linearly).
 *
 * This subscriber rejects an over-limit JSON `/api/*` write with a 413
 * `application/problem+json` BEFORE the controller can decode it. It runs on
 * `kernel.request` at priority 16 — after Symfony's RouterListener (priority
 * 32) so route attributes are resolved, and well before the controller is
 * invoked. Calling `setResponse()` on the RequestEvent short-circuits the
 * kernel, so the controller never sees the request and the 413 is returned
 * verbatim (it does NOT pass through {@see Rfc7807ExceptionListener}, which
 * is why the problem document is built self-contained here).
 *
 * Deliberately scoped to JSON writes only:
 *   - method ∈ {POST, PUT, PATCH} (bodyless verbs carry no payload to cap);
 *   - `Content-Type` is a JSON media type (`application/json`,
 *     `application/ld+json`, `application/merge-patch+json`);
 *   - path starts with `/api/`.
 *
 * Exempt (returns early, never caps):
 *   - multipart/form-data — legitimate large file uploads (DAM, import
 *     wizard) ship multipart bodies and carry their OWN per-tenant
 *     file-size limit;
 *   - the import surface (`/api/import-*` — sessions, schedules, sources,
 *     profiles) and `/api/assets/upload`, whose uploads are bounded by
 *     `Tenant::importMaxFileSize` / the per-MIME asset cap respectively.
 */
final class RequestBodySizeLimitListener implements EventSubscriberInterface
{
    /**
     * JSON media types this guard caps. A `Content-Type` is matched by prefix
     * so charset parameters (e.g. `application/json; charset=utf-8`) still hit.
     *
     * @var list<string>
     */
    private const array JSON_CONTENT_TYPES = [
        'application/json',
        'application/ld+json',
        'application/merge-patch+json',
    ];

    /**
     * @param int $maxBytes maximum allowed JSON request body in bytes;
     *                      bound from `%env(int:API_MAX_JSON_BODY_BYTES)%`
     */
    public function __construct(private readonly int $maxBytes)
    {
    }

    public static function getSubscribedEvents(): array
    {
        // Priority 16: after RouterListener (32) so `_route`/route attributes
        // are populated, but before the controller runs — the body is capped
        // before any controller can json_decode it.
        return [KernelEvents::REQUEST => ['onRequest', 16]];
    }

    public function onRequest(RequestEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $request = $event->getRequest();

        if (!$this->shouldEnforce($request)) {
            return;
        }

        // `getContent()` is already buffered by PHP (php://input is read into
        // the Request once), so strlen() is cheap and — unlike trusting
        // Content-Length — cannot be spoofed by a lying header. We still take
        // the max of the two so a chunked/streamed body that under-reports via
        // getContent() can't slip past on a large advertised Content-Length.
        $size = max(
            \strlen($request->getContent()),
            (int) $request->headers->get('Content-Length', '0'),
        );

        if ($size <= $this->maxBytes) {
            return;
        }

        // Self-contained RFC 7807 document: setResponse() short-circuits the
        // kernel, so this never flows through Rfc7807ExceptionListener — the
        // problem+json contract is guaranteed here regardless.
        $event->setResponse(new JsonResponse(
            data: [
                'type' => '/errors/413',
                'title' => 'Payload Too Large',
                'status' => Response::HTTP_REQUEST_ENTITY_TOO_LARGE,
                'detail' => \sprintf('Request JSON body exceeds the %d-byte limit.', $this->maxBytes),
            ],
            status: Response::HTTP_REQUEST_ENTITY_TOO_LARGE,
            headers: ['content-type' => 'application/problem+json'],
        ));
    }

    /**
     * True only for JSON-bodied `/api/*` writes that are not on an
     * upload-exempt path.
     */
    private function shouldEnforce(Request $request): bool
    {
        if (!\in_array($request->getMethod(), [Request::METHOD_POST, Request::METHOD_PUT, Request::METHOD_PATCH], true)) {
            return false;
        }

        $path = $request->getPathInfo();
        if (!str_starts_with($path, '/api/')) {
            return false;
        }

        // Upload surfaces carry their own per-tenant / per-MIME size limits and
        // legitimately ship large bodies. `/api/import-` covers import-sessions,
        // import-schedules, import-sources and import-profiles.
        if (str_starts_with($path, '/api/import-') || str_starts_with($path, '/api/assets/upload')) {
            return false;
        }

        $contentType = $request->headers->get('Content-Type', '');

        // multipart uploads are never JSON — exempt regardless of path.
        if (str_starts_with($contentType, 'multipart/form-data')) {
            return false;
        }

        foreach (self::JSON_CONTENT_TYPES as $jsonType) {
            if (str_starts_with($contentType, $jsonType)) {
                return true;
            }
        }

        return false;
    }
}
