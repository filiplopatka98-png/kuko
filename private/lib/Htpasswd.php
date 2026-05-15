<?php
declare(strict_types=1);
namespace Kuko;

/**
 * Pure helper to upsert a user into htpasswd-style content.
 *
 * One user per line, bcrypt hashed:  username:$2y$..
 * Used by the admin password reset CLI; Auth reads the same format.
 */
final class Htpasswd
{
    /**
     * Return new file content with $user set to a fresh bcrypt hash of
     * $plainPassword. Existing users keep their order; a matching user is
     * replaced in place, a new user is appended. Always ends with "\n".
     */
    public static function upsert(string $contents, string $user, string $plainPassword): string
    {
        $user = trim($user);
        if ($user === '' || preg_match('/[:\s]/', $user) === 1) {
            throw new \InvalidArgumentException('Invalid username: must be non-empty and contain no colon or whitespace.');
        }
        if ($plainPassword === '') {
            throw new \InvalidArgumentException('Password must not be empty.');
        }

        $hash = password_hash($plainPassword, PASSWORD_BCRYPT);

        $out = [];
        $replaced = false;
        foreach (explode("\n", $contents) as $line) {
            $line = trim($line);
            if ($line === '') continue;
            [$u] = array_pad(explode(':', $line, 2), 2, '');
            if ($u === $user) {
                $out[] = $user . ':' . $hash;
                $replaced = true;
            } else {
                $out[] = $line;
            }
        }
        if (!$replaced) {
            $out[] = $user . ':' . $hash;
        }

        return implode("\n", $out) . "\n";
    }
}
