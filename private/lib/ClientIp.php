<?php
declare(strict_types=1);
namespace Kuko;

/**
 * Resolve the real client IP for rate-limiting and audit hashing.
 *
 * By default we trust ONLY REMOTE_ADDR (spoof-proof). Behind a reverse proxy
 * that rewrites REMOTE_ADDR to its own address (e.g. some WebSupport setups),
 * set security.trust_proxy = true so the RIGHT-MOST hop of X-Forwarded-For is
 * used instead. Never trust the header unless the deployment is actually
 * behind a single proxy you control — it is attacker-controlled otherwise.
 */
final class ClientIp
{
    public static function get(): string
    {
        $remote = (string) ($_SERVER['REMOTE_ADDR'] ?? '0.0.0.0');
        if (!Config::get('security.trust_proxy', false)) {
            return $remote;
        }
        $header = (string) ($_SERVER['HTTP_X_FORWARDED_FOR'] ?? '');
        if ($header === '') {
            return $remote;
        }
        // The trusted proxy APPENDS the address it saw the connection from, so
        // the RIGHT-MOST entry is the real client as seen by our proxy. Any
        // left-hand entries are client-supplied and therefore spoofable, so we
        // ignore them entirely.
        $parts = explode(',', $header);
        $last = trim((string) end($parts));
        return filter_var($last, FILTER_VALIDATE_IP) !== false ? $last : $remote;
    }
}
