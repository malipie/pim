<?php

declare(strict_types=1);

namespace App\Catalog\Application;

use HTMLPurifier;
use HTMLPurifier_Config;

/**
 * AUD-033 / W2-2 (C-4) — server-side HTML sanitisation for `wysiwyg`
 * attribute values, applied on the WRITE path (defense-in-depth).
 *
 * Until now stored-XSS was blocked ONLY by the frontend DOMPurify call in
 * `wysiwyg-editor.tsx` before `dangerouslySetInnerHTML`. Any other consumer
 * of `object_values.value` that forgets to sanitise — a future admin view, a
 * storefront, a partner panel, a report, an e-mail, an HTML export to a sales
 * channel — would fire the payload. This neutralises the HTML at the source,
 * so a `<script>`, an `onerror=` handler, or a `javascript:` / `data:` href
 * never reaches the database verbatim, regardless of who reads it later.
 *
 * The allow-list is a conservative rich-text set (block/inline formatting,
 * lists, links, headings, blockquote, code). HTMLPurifier drops every tag and
 * attribute outside the list — so `<script>`, `<style>`, `<iframe>` and all
 * `on*` event handlers are stripped, and `URI.AllowedSchemes` constrains
 * `<a href>` to http/https/mailto, blocking `javascript:`/`data:` URIs.
 */
final class HtmlSanitizer
{
    /**
     * Conservative rich-text allow-list. `*[id]` is intentionally absent —
     * stable ids on user content are not needed and invite DOM-clobbering.
     */
    private const string ALLOWED_HTML =
        'p,br,strong,b,em,i,u,s,sub,sup,'
        .'ul,ol,li,'
        .'a[href|title|target|rel],'
        .'h1,h2,h3,h4,h5,h6,'
        .'blockquote,pre,code,hr,'
        .'table,thead,tbody,tr,th,td';

    /**
     * Schemes permitted in `<a href>`. `javascript` and `data` are absent by
     * design — they are the wysiwyg stored-XSS vectors flagged by AUD-033.
     */
    private const string ALLOWED_URI_SCHEMES = 'http,https,mailto';

    private ?HTMLPurifier $purifier = null;

    /**
     * Sanitise a wysiwyg HTML string, returning the cleaned markup. A
     * non-string is returned unchanged — the per-type validator
     * ({@see Validation\TypeValidator\WysiwygValidator})
     * is what rejects a non-string wysiwyg value; this method only mutates
     * the HTML it is given.
     */
    public function sanitize(string $html): string
    {
        if ('' === $html) {
            return '';
        }

        return $this->purifier()->purify($html);
    }

    private function purifier(): HTMLPurifier
    {
        if (null !== $this->purifier) {
            return $this->purifier;
        }

        $config = HTMLPurifier_Config::createDefault();
        $config->set('HTML.Allowed', self::ALLOWED_HTML);
        $config->set('URI.AllowedSchemes', array_fill_keys(
            explode(',', self::ALLOWED_URI_SCHEMES),
            true,
        ));
        // Keep external links safe + crawler-neutral without relying on the
        // reader to add it: rel="noopener noreferrer nofollow" on every <a>.
        $config->set('HTML.TargetBlank', false);
        $config->set('Attr.AllowedRel', ['noopener', 'noreferrer', 'nofollow']);
        $config->set('HTML.Nofollow', true);
        // FrankenPHP worker mode + read-only-friendly: no serialiser cache on
        // disk. The definition is cheap to rebuild and the instance is reused
        // for the lifetime of the worker, so the perf hit is negligible.
        $config->set('Cache.DefinitionImpl', null);

        return $this->purifier = new HTMLPurifier($config);
    }
}
