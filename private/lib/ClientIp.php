<?php
declare(strict_types=1);
namespace Kuko;

/**
 * Resolve the real client IP for rate-limiting and audit hashing.
 *
 * By default we trust ONLY REMOTE_ADDR (spoof-proof). Behind a reverse proxy
 * that rewrites REMOTE_ADDR to its own address (e.g. some WebSupport setups),
 * set security.trust_proxy = true so the first hop of X-Forwarded-For is used
 * instead. Never trust the header unless the deployment is actually behind a
 * proxy you control — it is attacker-controlled otherwise.
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
        // Left-most entry is the original client (proxy appends its own hops).
        $first = trim(explode(',', $header)[0]);
        return filter_var($first, FILTER_VALIDATE_IP) !== false ? $first : $remote;
    }
}
