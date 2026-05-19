<?php
declare(strict_types=1);
namespace Kuko\Tests\Integration;
use Kuko\Db;
use Kuko\SettingsRepo;
use Kuko\Seo;
use PHPUnit\Framework\TestCase;

final class SeoImageTest extends TestCase
{
    private function repoWith(array $rows): SettingsRepo
    {
        $db = Db::fromDsn('sqlite::memory:');
        $db->exec("CREATE TABLE settings (setting_key TEXT PRIMARY KEY, value TEXT NOT NULL, updated_at TEXT NOT NULL DEFAULT (datetime('now')))");
        foreach ($rows as $k => $v) {
            $db->execStmt('INSERT INTO settings (setting_key,value) VALUES (?,?)', [$k, $v]);
        }
        return new SettingsRepo($db);
    }

    protected function tearDown(): void
    {
        Seo::setSettings(null);
    }

    public function testResolveReturnsEmptyImageByDefault(): void
    {
        Seo::setSettings($this->repoWith([]));
        $r = Seo::resolve('home', 'T', 'D', false, null);
        $this->assertArrayHasKey('image', $r);
        $this->assertSame('', $r['image']);
    }

    public function testResolveReturnsPerPageImageFromDb(): void
    {
        Seo::setSettings($this->repoWith(['seo.home.image' => '/assets/img/seo/og-home-1.jpg']));
        $r = Seo::resolve('home', 'T', 'D', false, null);
        $this->assertSame('/assets/img/seo/og-home-1.jpg', $r['image']);
    }

    public function testHeadAppliesPerPageImageOverride(): void
    {
        $head = file_get_contents(\dirname(__DIR__, 2) . '/templates/head.php');
        $this->assertStringContainsString("\$seo['image']", $head);
        $this->assertStringContainsString('$ogImageUrl', $head);
    }

    public function testAdminPageEditHasUploadAndGooglePreview(): void
    {
        $tpl = file_get_contents(\dirname(__DIR__, 2) . '/templates/admin/page-edit.php');
        $this->assertStringContainsString('multipart/form-data', $tpl);
        $this->assertStringContainsString('name="seo_image"', $tpl);
        $this->assertStringContainsString('admin-seo-preview', $tpl);
        $css = file_get_contents(\dirname(__DIR__, 3) . '/public/assets/css/admin.css');
        $this->assertStringContainsString('.admin-seo-preview__title', $css);
        $this->assertMatchesRegularExpression('/\.admin-seo-preview__title\s*\{[^}]*#1a0dab/i', $css);
    }
}
