<?php
declare(strict_types=1);
namespace Kuko\Tests\Integration;
use Kuko\Db;
use Kuko\SettingsRepo;
use Kuko\MailContent;
use Kuko\Config;
use PHPUnit\Framework\TestCase;

final class MailContentTest extends TestCase
{
    private array $r = [
        'name' => 'Janka', 'package' => 'maxi', 'wished_date' => '2026-06-01',
        'wished_time' => '15:00:00', 'kids_count' => 12,
    ];

    protected function setUp(): void
    {
        Config::reset();
        Config::load(__DIR__ . '/../fixtures/config.test.php');
    }

    protected function tearDown(): void
    {
        MailContent::setSettings(null);
    }

    private function repo(array $rows = []): SettingsRepo
    {
        $db = Db::fromDsn('sqlite::memory:');
        $db->exec("CREATE TABLE settings (setting_key TEXT PRIMARY KEY, value TEXT NOT NULL, updated_at TEXT NOT NULL DEFAULT (datetime('now')))");
        foreach ($rows as $k => $v) {
            $db->execStmt('INSERT INTO settings (setting_key,value) VALUES (?,?)', [$k, $v]);
        }
        return new SettingsRepo($db);
    }

    public function testDefaultsCoverEveryType(): void
    {
        foreach (array_keys(MailContent::TYPES) as $t) {
            $d = MailContent::defaults()[$t] ?? null;
            $this->assertNotNull($d, "missing defaults for $t");
            $this->assertNotSame('', $d['subject']);
            $this->assertNotSame('', $d['intro']);
        }
    }

    public function testSubjectFallsBackToDefaultWithoutDb(): void
    {
        MailContent::setSettings(null);
        $this->assertSame(
            '[KUKO] Nová rezervácia — MAXI',
            MailContent::subject('reservation_admin', $this->r)
        );
    }

    public function testDbOverrideWinsAndTokensSubstituted(): void
    {
        MailContent::setSettings($this->repo([
            'mail.reservation_confirmed.subject' => 'Hotovo {package} pre {name}',
            'mail.reservation_confirmed.intro'   => "Ahoj {name},\n\ntermín {date} o {time}.",
        ]));
        $this->assertSame('Hotovo MAXI pre Janka', MailContent::subject('reservation_confirmed', $this->r));
        $html = MailContent::introHtml('reservation_confirmed', $this->r);
        $this->assertStringContainsString('<p>Ahoj Janka,</p>', $html);
        $this->assertStringContainsString('<p>termín 2026-06-01 o 15:00.</p>', $html);
        $this->assertSame(
            "Ahoj Janka,\n\ntermín 2026-06-01 o 15:00.",
            MailContent::introText('reservation_confirmed', $this->r)
        );
    }

    public function testEmptyDbValueFallsBackToDefault(): void
    {
        MailContent::setSettings($this->repo(['mail.reservation_cancelled.subject' => '']));
        $this->assertSame(
            'Rezervácia zrušená — KUKO detský svet',
            MailContent::subject('reservation_cancelled', $this->r)
        );
    }

    public function testIntroHtmlEscapesUserCopy(): void
    {
        MailContent::setSettings($this->repo([
            'mail.reservation_admin.intro' => 'Pozn: <script>alert(1)</script> & spol.',
        ]));
        $html = MailContent::introHtml('reservation_admin', $this->r);
        $this->assertStringNotContainsString('<script>', $html);
        $this->assertStringContainsString('&lt;script&gt;', $html);
        $this->assertStringContainsString('&amp; spol.', $html);
    }

    public function testSendersAndTemplatesWired(): void
    {
        $root = \dirname(__DIR__, 3);
        $api = file_get_contents($root . '/public/api/reservation.php');
        $this->assertStringContainsString("MailContent::subject('reservation_admin'", $api);
        $this->assertStringContainsString("MailContent::subject('reservation_customer'", $api);
        $admin = file_get_contents($root . '/public/admin/index.php');
        $this->assertStringContainsString('MailContent::subject($template', $admin);
        foreach (['admin', 'customer', 'confirmed', 'cancelled'] as $k) {
            $h = file_get_contents($root . "/private/templates/mail/reservation_$k.html.php");
            $this->assertStringContainsString("MailContent::introHtml('reservation_$k'", $h);
            $t = file_get_contents($root . "/private/templates/mail/reservation_$k.text.php");
            $this->assertStringContainsString("MailContent::introText('reservation_$k'", $t);
        }
    }

    public function testEveryMailHasFullDataAndBrandedFooter(): void
    {
        \Kuko\Content::setDb(null);
        \Kuko\Social::setSettings(null);
        $r = \Kuko\MailContent::sampleRecord();
        $ren = new \Kuko\Renderer(\dirname(__DIR__, 2) . '/templates/mail');
        foreach (['admin', 'customer', 'confirmed', 'cancelled'] as $k) {
            $h = $ren->render("reservation_$k.html", ['r' => $r, 'statusLink' => 'https://kukodetskysvet.sk/x']);
            $t = $ren->render("reservation_$k.text", ['r' => $r, 'statusLink' => 'https://kukodetskysvet.sk/x']);
            foreach ([$h, $t] as $body) {
                $this->assertStringContainsString($r['name'], $body, "$k: name");
                $this->assertStringContainsString($r['phone'], $body, "$k: phone");
                $this->assertStringContainsString($r['email'], $body, "$k: email");
                $this->assertStringContainsString((string) $r['kids_count'], $body, "$k: kids");
                $this->assertStringContainsString('Bratislavská 141, 921 01 Piešťany', $body, "$k: footer address");
                $this->assertStringContainsString('KUKO detský svet', $body, "$k: footer name");
            }
            $this->assertStringContainsString('/assets/img/logo.png', $h, "$k: footer logo");
        }
    }
}
