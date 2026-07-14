<?php
declare(strict_types=1);
namespace Kuko;

/**
 * Content-Security-Policy with a per-request nonce.
 *
 * The nonce lets us drop 'unsafe-inline' from script-src: every inline <script>
 * we emit carries nonce="<Csp::nonce()>", and the CSP header advertises the same
 * value, so only our own scripts run — an injected <script> without the nonce is
 * blocked. style-src keeps 'unsafe-inline' (inline style="" attrs are pervasive
 * and cannot execute JS, so the XSS risk there is negligible).
 *
 * Third-party scripts are still allowed via explicit host allowlists (reCAPTCHA,
 * unpkg/Leaflet) since they are loaded as external <script src> from those hosts.
 */
final class Csp
{
    private static ?string $nonce = null;

    public static function nonce(): string
    {
        if (self::$nonce === null) {
            self::$nonce = bin2hex(random_bytes(16));
        }
        return self::$nonce;
    }

    /** Reset — test-only seam. */
    public static function reset(): void
    {
        self::$nonce = null;
    }

    /** Build the policy string for a context ('public' | 'admin'). */
    public static function policy(string $context): string
    {
        $n = "'nonce-" . self::nonce() . "'";
        if ($context === 'admin') {
            return implode('; ', [
                "default-src 'self'",
                "base-uri 'self'",
                "img-src 'self' data:",
                "script-src 'self' $n",
                "style-src 'self' 'unsafe-inline'",
                "font-src 'self' data:",
                "connect-src 'self'",
                "frame-src 'self'",
                "form-action 'self'",
                "frame-ancestors 'self'",
            ]);
        }
        return implode('; ', [
            "default-src 'self'",
            "base-uri 'self'",
            "img-src 'self' data: https://*.tile.openstreetmap.org",
            "script-src 'self' $n https://www.google.com/recaptcha/ https://www.gstatic.com/recaptcha/ https://unpkg.com",
            "frame-src https://www.google.com/recaptcha/",
            "style-src 'self' 'unsafe-inline' https://unpkg.com",
            "font-src 'self' data:",
            "connect-src 'self' https://www.google.com/recaptcha/",
            "form-action 'self'",
        ]);
    }

    /** Emit the header (no-op if headers already sent). */
    public static function send(string $context): void
    {
        if (!headers_sent()) {
            header('Content-Security-Policy: ' . self::policy($context));
        }
    }
}
