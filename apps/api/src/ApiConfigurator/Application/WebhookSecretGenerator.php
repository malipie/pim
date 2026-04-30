<?php

declare(strict_types=1);

namespace App\ApiConfigurator\Application;

/**
 * Generates per-profile HMAC secrets used to sign outbound webhook
 * bodies (`X-Pim-Signature: sha256=<hex>`). The secret is rotated by
 * pressing "Regenerate" in the admin — old subscribers must update.
 */
final class WebhookSecretGenerator
{
    /**
     * Returns a 64-char base64url string (48 bytes random → 256-bit
     * HMAC key with margin). URL-safe so admins can paste it into
     * config files without quoting.
     */
    public function generate(): string
    {
        return rtrim(strtr(base64_encode(random_bytes(48)), '+/', '-_'), '=');
    }
}
