<?php

declare(strict_types=1);

namespace App\Tests\Unit\Catalog\Application;

use App\Catalog\Application\HtmlSanitizer;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * AUD-033 / W2-2 (C-4) — server-side wysiwyg HTML sanitisation.
 *
 * Stored-XSS payloads must be neutralised at the source: `<script>`,
 * `on*` event handlers, `<style>`/`<iframe>`, and `javascript:`/`data:`
 * hrefs are stripped, while a conservative rich-text set survives intact
 * so legitimate product copy is not destroyed.
 */
final class HtmlSanitizerTest extends TestCase
{
    private HtmlSanitizer $sanitizer;

    protected function setUp(): void
    {
        $this->sanitizer = new HtmlSanitizer();
    }

    #[Test]
    public function stripsScriptTag(): void
    {
        $out = $this->sanitizer->sanitize('<p>ok</p><script>alert(1)</script>');

        self::assertStringNotContainsStringIgnoringCase('<script', $out);
        self::assertStringNotContainsString('alert(1)', $out);
        self::assertStringContainsString('<p>ok</p>', $out);
    }

    #[Test]
    public function stripsEventHandlerAttribute(): void
    {
        $out = $this->sanitizer->sanitize('<img src=x onerror=alert(1)>');

        self::assertStringNotContainsStringIgnoringCase('onerror', $out);
        self::assertStringNotContainsString('alert(1)', $out);
    }

    #[Test]
    public function stripsStyleAndIframe(): void
    {
        $out = $this->sanitizer->sanitize(
            '<style>body{display:none}</style><iframe src="https://evil.test"></iframe><p>keep</p>',
        );

        self::assertStringNotContainsStringIgnoringCase('<style', $out);
        self::assertStringNotContainsStringIgnoringCase('<iframe', $out);
        self::assertStringContainsString('<p>keep</p>', $out);
    }

    #[Test]
    public function removesJavascriptSchemeHref(): void
    {
        $out = $this->sanitizer->sanitize('<a href="javascript:alert(1)">click</a>');

        self::assertStringNotContainsStringIgnoringCase('javascript:', $out);
        // The link text is preserved even though the dangerous href is dropped.
        self::assertStringContainsString('click', $out);
    }

    #[Test]
    public function removesDataSchemeHref(): void
    {
        $out = $this->sanitizer->sanitize(
            '<a href="data:text/html,<script>alert(1)</script>">x</a>',
        );

        self::assertStringNotContainsStringIgnoringCase('data:text/html', $out);
        self::assertStringNotContainsString('alert(1)', $out);
    }

    #[Test]
    public function preservesSafeRichText(): void
    {
        $out = $this->sanitizer->sanitize('<p><strong>ok</strong></p>');

        self::assertSame('<p><strong>ok</strong></p>', $out);
    }

    #[Test]
    public function preservesHttpsLink(): void
    {
        $out = $this->sanitizer->sanitize('<a href="https://example.com">link</a>');

        self::assertStringContainsString('href="https://example.com"', $out);
        self::assertStringContainsString('link', $out);
    }

    #[Test]
    public function preservesListsAndHeadings(): void
    {
        $html = '<h2>Tytuł</h2><ul><li>jeden</li><li>dwa</li></ul>';
        $out = $this->sanitizer->sanitize($html);

        self::assertStringContainsString('<h2>Tytuł</h2>', $out);
        self::assertStringContainsString('<li>jeden</li>', $out);
        self::assertStringContainsString('<li>dwa</li>', $out);
    }

    #[Test]
    public function emptyStringStaysEmpty(): void
    {
        self::assertSame('', $this->sanitizer->sanitize(''));
    }
}
