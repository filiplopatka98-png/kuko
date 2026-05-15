<?php
declare(strict_types=1);
namespace Kuko\Tests\Integration;

use Kuko\Db;
use Kuko\ContentBlocksRepo;
use Kuko\SettingsRepo;
use PHPUnit\Framework\TestCase;

final class AdminPagesTest extends TestCase
{
    private string $root;

    protected function setUp(): void
    {
        $this->root = \dirname(__DIR__, 3);
    }

    // ---- structure: pages.php list template ----

    public function testPagesTemplateIteratesModelAndLinksEditor(): void
    {
        $t = file_get_contents($this->root . '/private/templates/admin/pages.php');
        // template iterates $pages model and renders label + editor link per row
        $this->assertStringContainsString('$pages', $t);
        $this->assertMatchesRegularExpression('#foreach\s*\(\s*\$pages#', $t);
        $this->assertStringContainsString("\$cfg['label']", $t);
        $this->assertStringContainsString('/admin/pages/', $t);
        $this->assertStringContainsString('Upraviť', $t);
        $this->assertStringContainsString('Stránky', $t);
        $this->assertSame(0, substr_count($t, '<h1'), 'admin uses h2, not h1');
        $this->assertStringContainsString('<h2', $t);
        $this->assertStringContainsString("require __DIR__ . '/layout.php';", $t);
    }

    public function testAdminPagesModelHasAllLabels(): void
    {
        $src = file_get_contents($this->root . '/public/admin/index.php');
        foreach (['Domov', 'Rezervácia', 'Fotogaléria', 'Časté otázky', 'Ochrana údajov'] as $label) {
            $this->assertStringContainsString($label, $src, "\$adminPages must include $label");
        }
        foreach (['home', 'rezervacia', 'gallery', 'faq', 'privacy'] as $key) {
            $this->assertStringContainsString("'" . $key . "'", $src);
        }
    }

    // ---- structure: page-edit.php combined form ----

    public function testPageEditTemplateHasCombinedForm(): void
    {
        $t = file_get_contents($this->root . '/private/templates/admin/page-edit.php');
        // single combined form posting to the save route
        $this->assertMatchesRegularExpression('#<form[^>]+action="/admin/pages/#', $t);
        $this->assertStringContainsString('method="post"', $t);
        // posts content blocks as arrays + the seo fields + csrf
        $this->assertStringContainsString('blocks[', $t);
        $this->assertStringContainsString('seo_title', $t);
        $this->assertStringContainsString('seo_description', $t);
        $this->assertStringContainsString('name="csrf"', $t);
        // reuses seo.php counter + quill markup
        $this->assertStringContainsString('kukoSeo', $t);
        $this->assertStringContainsString('quill-editor', $t);
        // two sub-tab sections + save
        $this->assertStringContainsString('Obsah', $t);
        $this->assertStringContainsString('SEO', $t);
        $this->assertStringContainsString('Uložiť stránku', $t);
        $this->assertSame(0, substr_count($t, '<h1'));
        $this->assertStringContainsString("require __DIR__ . '/layout.php';", $t);
    }

    // ---- structure: routes registered in index.php ----

    public function testRoutesRegisteredInIndex(): void
    {
        $src = file_get_contents($this->root . '/public/admin/index.php');
        $this->assertMatchesRegularExpression('#->get\(\s*[\'"]/admin/pages[\'"]#', $src, 'GET /admin/pages');
        $this->assertMatchesRegularExpression('#->get\(\s*[\'"]/admin/pages/\{page\}[\'"]#', $src, 'GET /admin/pages/{page}');
        $this->assertMatchesRegularExpression('#->post\(\s*[\'"]/admin/pages/\{page\}/save[\'"]#', $src, 'POST save');
        // legacy GET redirects
        $this->assertMatchesRegularExpression('#->get\(\s*[\'"]/admin/content[\'"]#', $src);
        $this->assertMatchesRegularExpression('#->get\(\s*[\'"]/admin/seo[\'"]#', $src);
        $this->assertStringContainsString("Location: /admin/pages", $src);
        // $adminPages canonical model present with kontakt excluded from home
        $this->assertStringContainsString('$adminPages', $src);
        $this->assertMatchesRegularExpression("#'home'\s*=>.*'prefixes'\s*=>\s*\[[^\]]*'hero'#s", $src);
        $this->assertDoesNotMatchRegularExpression("#'home'\s*=>.*'prefixes'\s*=>\s*\[[^\]]*'kontakt'#s", $src, 'kontakt must NOT be under Domov');
    }

    public function testSeoPagesIncludesGallery(): void
    {
        $src = file_get_contents($this->root . '/public/admin/index.php');
        $this->assertMatchesRegularExpression("#\\\$seoPages\s*=\s*\[[^\]]*'gallery'#", $src);
    }

    public function testSeedListsGallerySeo(): void
    {
        $s = file_get_contents($this->root . '/private/scripts/seed-cms.php');
        $this->assertStringContainsString('seo.gallery.title', $s);
        $this->assertStringContainsString('seo.gallery.description', $s);
    }

    // ---- behavioural: combined save round-trip (the persistence path used by /save) ----

    private function memDb(): Db
    {
        $db = Db::fromDsn('sqlite::memory:');
        $db->exec("CREATE TABLE content_blocks (
            block_key TEXT PRIMARY KEY, label TEXT NOT NULL,
            content_type TEXT NOT NULL DEFAULT 'text', value TEXT NOT NULL,
            updated_at TEXT NOT NULL DEFAULT (datetime('now')), updated_by TEXT)");
        $db->exec("CREATE TABLE settings (setting_key TEXT PRIMARY KEY, value TEXT NOT NULL,
            updated_at TEXT NOT NULL DEFAULT (datetime('now')))");
        $db->execStmt("INSERT INTO content_blocks (block_key,label,content_type,value) VALUES ('hero.title','Hero','text','Old title')");
        $db->execStmt("INSERT INTO content_blocks (block_key,label,content_type,value) VALUES ('faq.intro','FAQ','html','<p>old</p>')");
        return $db;
    }

    public function testCombinedSavePersistsBlocksAndSeo(): void
    {
        $db = $this->memDb();
        $cb = new ContentBlocksRepo($db);
        $settings = new SettingsRepo($db);

        // mirror the /admin/pages/home/save persistence path
        $blocks = [
            'hero.title' => ['value' => 'New title', 'type' => 'text', 'key' => 'hero.title'],
        ];
        foreach ($blocks as $b) {
            $type = in_array($b['type'], ['text', 'html'], true) ? $b['type'] : 'text';
            $cb->set($b['key'], $b['value'], $type, 'tester');
        }
        $settings->set('seo.home.title', 'My SEO Title');
        $settings->set('seo.home.description', 'My SEO Desc');

        $this->assertSame('New title', (new ContentBlocksRepo($db))->get('hero.title'));
        $this->assertSame('My SEO Title', (new SettingsRepo($db))->get('seo.home.title'));
        $this->assertSame('My SEO Desc', (new SettingsRepo($db))->get('seo.home.description'));
    }

    public function testHtmlBlockSanitisedOnSave(): void
    {
        $db = $this->memDb();
        $cb = new ContentBlocksRepo($db);
        $cb->set('faq.intro', '<p>ok</p><script>alert(1)</script>', 'html', 'tester');
        $stored = (new ContentBlocksRepo($db))->get('faq.intro');
        $this->assertStringContainsString('<p>ok</p>', $stored);
        $this->assertStringNotContainsString('<script', $stored);
    }
}
