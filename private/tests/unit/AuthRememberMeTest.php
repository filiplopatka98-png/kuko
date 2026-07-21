<?php
declare(strict_types=1);
namespace Kuko\Tests\Unit;

use Kuko\Auth;
use Kuko\Config;
use PHPUnit\Framework\TestCase;

/**
 * Remember-me cookie hardening:
 *  - Ú5: the signature binds the user's current password fingerprint, so a
 *    password change invalidates every previously issued cookie.
 *  - Ú6: an empty auth.secret fails closed (a cookie is never trusted), while
 *    session-based login is unaffected (it never reaches this validator).
 */
final class AuthRememberMeTest extends TestCase
{
    private string $user;
    private string $hash;

    protected function setUp(): void
    {
        $file = \dirname(__DIR__, 3) . '/config/.htpasswd';
        if (!is_file($file)) {
            $this->markTestSkipped('config/.htpasswd not present in this checkout');
        }
        $lines = file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [];
        [$u, $h] = array_pad(explode(':', trim($lines[0] ?? ''), 2), 2, '');
        if ($u === '' || $h === '') {
            $this->markTestSkipped('config/.htpasswd has no usable entry');
        }
        $this->user = $u;
        $this->hash = $h;
    }

    protected function tearDown(): void
    {
        Config::reset();
    }

    private function loadConfig(string $secret): void
    {
        Config::reset();
        $tmp = tempnam(sys_get_temp_dir(), 'cfg');
        file_put_contents($tmp, "<?php return ['auth' => ['secret' => " . var_export($secret, true) . "]];");
        Config::load($tmp);
        @unlink($tmp);
    }

    /** Reproduce Auth::sign() for a given fingerprint without touching privates. */
    private function signWithFingerprint(string $secret, string $user, int $iat, string $fp): string
    {
        return hash_hmac('sha256', 'admin|' . $user . '|' . $iat . '|' . $fp, $secret);
    }

    private function currentFingerprint(): string
    {
        return substr(sha1($this->hash), 0, 16);
    }

    public function testValidCookiePasses(): void
    {
        $this->loadConfig('a-real-secret');
        $iat = time();
        $sig = $this->signWithFingerprint('a-real-secret', $this->user, $iat, $this->currentFingerprint());
        $cookie = $this->user . '|' . $iat . '|' . $sig;
        $this->assertSame($this->user, Auth::rememberCookieValid($cookie, $iat));
    }

    public function testPasswordChangeInvalidatesOldCookie(): void
    {
        // A cookie signed against a stale password hash (different fingerprint)
        // must no longer verify against the current hash.
        $this->loadConfig('a-real-secret');
        $iat = time();
        $staleFp = substr(sha1('$2y$05$' . str_repeat('x', 53)), 0, 16);
        $this->assertNotSame($this->currentFingerprint(), $staleFp);
        $sig = $this->signWithFingerprint('a-real-secret', $this->user, $iat, $staleFp);
        $cookie = $this->user . '|' . $iat . '|' . $sig;
        $this->assertNull(Auth::rememberCookieValid($cookie, $iat));
    }

    public function testEmptySecretFailsClosed(): void
    {
        // Even a cookie whose signature was computed with the same empty key is
        // rejected — the validator refuses to trust an HMAC keyed on ''.
        $this->loadConfig('');
        $iat = time();
        $sig = $this->signWithFingerprint('', $this->user, $iat, $this->currentFingerprint());
        $cookie = $this->user . '|' . $iat . '|' . $sig;
        $this->assertNull(Auth::rememberCookieValid($cookie, $iat));
    }

    public function testTamperedSignatureRejected(): void
    {
        $this->loadConfig('a-real-secret');
        $iat = time();
        $cookie = $this->user . '|' . $iat . '|' . str_repeat('0', 64);
        $this->assertNull(Auth::rememberCookieValid($cookie, $iat));
    }

    public function testExpiredCookieRejected(): void
    {
        $this->loadConfig('a-real-secret');
        $iat = time() - (31 * 86400); // older than the 30-day TTL
        $sig = $this->signWithFingerprint('a-real-secret', $this->user, $iat, $this->currentFingerprint());
        $cookie = $this->user . '|' . $iat . '|' . $sig;
        $this->assertNull(Auth::rememberCookieValid($cookie, time()));
    }

    public function testUnknownUserRejected(): void
    {
        $this->loadConfig('a-real-secret');
        $iat = time();
        $ghost = 'definitely-not-a-real-admin';
        $fp = substr(sha1(''), 0, 16); // unknown user → empty hash fingerprint
        $sig = $this->signWithFingerprint('a-real-secret', $ghost, $iat, $fp);
        $cookie = $ghost . '|' . $iat . '|' . $sig;
        $this->assertNull(Auth::rememberCookieValid($cookie, $iat));
    }

    public function testMalformedCookieRejected(): void
    {
        $this->loadConfig('a-real-secret');
        $this->assertNull(Auth::rememberCookieValid('', time()));
        $this->assertNull(Auth::rememberCookieValid('garbage', time()));
        $this->assertNull(Auth::rememberCookieValid($this->user . '|notadigit|sig', time()));
    }
}
