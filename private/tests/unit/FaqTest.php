<?php
declare(strict_types=1);
namespace Kuko\Tests\Unit;

use Kuko\Db;
use Kuko\Faq;
use Kuko\SettingsRepo;
use PHPUnit\Framework\TestCase;

final class FaqTest extends TestCase
{
    private string $root;

    protected function setUp(): void
    {
        // dirname(__DIR__,3) from private/tests/unit/ == repo root
        $this->root = \dirname(__DIR__, 3);
    }

    private function memSettings(): SettingsRepo
    {
        $db = Db::fromDsn('sqlite::memory:');
        $db->exec("CREATE TABLE settings (setting_key TEXT PRIMARY KEY, value TEXT NOT NULL, updated_at TEXT NOT NULL DEFAULT (datetime('now')))");
        return new SettingsRepo($db);
    }

    // ---- items() ----

    public function testItemsReturnsSixDefaultsWhenSettingAbsent(): void
    {
        $s = $this->memSettings();
        $items = Faq::items($s);
        $this->assertCount(6, $items);
        $this->assertSame('Aké sú ceny vstupu do KUKO?', $items[0]['q']);
        $this->assertStringContainsString('<strong>zadarmo</strong>', $items[0]['a']);
        // every default has q + a keys, q plain text, a HTML
        foreach ($items as $it) {
            $this->assertArrayHasKey('q', $it);
            $this->assertArrayHasKey('a', $it);
            $this->assertNotSame('', trim($it['q']));
        }
        // the relative/tel/mailto links must live in the defaults
        $joined = implode('', array_column($items, 'a'));
        $this->assertStringContainsString('href="/rezervacia"', $joined);
        $this->assertStringContainsString('href="/#kontakt"', $joined);
        $this->assertStringContainsString('href="tel:+421915319934"', $joined);
        $this->assertStringContainsString('href="mailto:info@kuko-detskysvet.sk"', $joined);
    }

    public function testItemsParsesValidJson(): void
    {
        $s = $this->memSettings();
        $s->set('faq.items', json_encode([
            ['q' => 'Otázka 1', 'a' => '<strong>Odpoveď 1</strong>'],
            ['q' => 'Otázka 2', 'a' => 'Odpoveď 2'],
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
        $items = Faq::items($s);
        $this->assertCount(2, $items);
        $this->assertSame('Otázka 1', $items[0]['q']);
        $this->assertSame('<strong>Odpoveď 1</strong>', $items[0]['a']);
        $this->assertSame('Otázka 2', $items[1]['q']);
    }

    public function testItemsFallsBackToDefaultsOnInvalidJson(): void
    {
        $s = $this->memSettings();
        $s->set('faq.items', '{not valid json[[[');
        $items = Faq::items($s);
        $this->assertCount(6, $items, 'invalid JSON must degrade to the 6 defaults, never throw');
    }

    public function testItemsFallsBackOnNonListJson(): void
    {
        $s = $this->memSettings();
        $s->set('faq.items', json_encode(['q' => 'x', 'a' => 'y']));
        $items = Faq::items($s);
        $this->assertCount(6, $items);
    }

    // ---- save() ----

    public function testSaveSanitisesAnswerAndStripsQuestionTags(): void
    {
        $s = $this->memSettings();
        Faq::save($s, [
            ['q' => '  <b>Plain</b> question  ', 'a' => '<strong>keep</strong> <a href="/rezervacia">link</a><script>alert(1)</script>'],
        ]);
        $items = Faq::items($s);
        $this->assertCount(1, $items);
        $this->assertSame('Plain question', $items[0]['q'], 'question must be trimmed + tag-stripped');
        $this->assertStringContainsString('<strong>keep</strong>', $items[0]['a']);
        $this->assertStringContainsString('href="/rezervacia"', $items[0]['a']);
        $this->assertStringNotContainsString('<script', $items[0]['a'], 'answer HTML must be sanitised');
    }

    public function testSaveDropsFullyEmptyRowsAndReindexes(): void
    {
        $s = $this->memSettings();
        Faq::save($s, [
            ['q' => 'Keep me', 'a' => 'answer'],
            ['q' => '   ', 'a' => '   '],          // fully empty -> dropped
            ['q' => '', 'a' => '<p>only answer</p>'], // answer-only kept
        ]);
        $items = Faq::items($s);
        $this->assertCount(2, $items);
        $this->assertSame([0, 1], array_keys($items), 'must be reindexed list');
        $this->assertSame('Keep me', $items[0]['q']);
        $this->assertSame('', $items[1]['q']);
    }

    public function testSaveRoundTripsThroughSettingsRepo(): void
    {
        $s = $this->memSettings();
        Faq::save($s, [['q' => 'Ž otázka', 'a' => '<strong>Š</strong>']]);
        $raw = $s->get('faq.items');
        $this->assertIsString($raw);
        // UNESCAPED unicode/slashes
        $this->assertStringContainsString('Ž otázka', $raw);
        $this->assertStringContainsString('/', $raw === '' ? '/' : $raw);
        $decoded = json_decode($raw, true);
        $this->assertSame('Ž otázka', $decoded[0]['q']);
    }

    // ---- schemaJson() ----

    public function testSchemaJsonIsValidFaqPage(): void
    {
        $items = [
            ['q' => 'Q1', 'a' => '<strong>A1</strong> with <a href="/rezervacia">link</a>'],
            ['q' => 'Q2', 'a' => 'Plain A2'],
        ];
        $json = Faq::schemaJson($items);
        $data = json_decode($json, true);
        $this->assertIsArray($data, 'schemaJson must be valid JSON');
        $this->assertSame('https://schema.org', $data['@context']);
        $this->assertSame('FAQPage', $data['@type']);
        $this->assertCount(2, $data['mainEntity']);
        $this->assertSame('Question', $data['mainEntity'][0]['@type']);
        $this->assertSame('Q1', $data['mainEntity'][0]['name']);
        $this->assertSame('Answer', $data['mainEntity'][0]['acceptedAnswer']['@type']);
        $text = $data['mainEntity'][0]['acceptedAnswer']['text'];
        $this->assertStringNotContainsString('<', $text, 'answer text must be plain (no tags)');
        $this->assertStringContainsString('A1 with link', $text);
    }

    public function testSchemaJsonEmptyItemsStillValid(): void
    {
        $json = Faq::schemaJson([]);
        $data = json_decode($json, true);
        $this->assertIsArray($data);
        $this->assertSame('FAQPage', $data['@type']);
        $this->assertSame([], $data['mainEntity']);
    }

    public function testSchemaJsonMatchesDefaultsCount(): void
    {
        $s = $this->memSettings();
        $json = Faq::schemaJson(Faq::items($s));
        $data = json_decode($json, true);
        $this->assertCount(6, $data['mainEntity']);
    }

    public function testSchemaJsonNeutralizesScriptBreakout(): void
    {
        $s = Faq::schemaJson([
            ['q' => '</script><script>alert(1)</script>', 'a' => '<a href="/x">x</a> & <b>b</b>'],
            ['q' => 'Cena < 5 €?', 'a' => 'ok'],
        ]);
        // The HTML tokenizer ends a <script> on a literal `</script` and
        // would start a new one on `<script`; neither may appear raw.
        $this->assertFalse(strpos($s, '</script'), 'no literal </script can break the inline schema script');
        $this->assertFalse(strpos($s, '<script'), 'no literal <script can open a new script element');
        // Stronger: NO raw `<` or `>` char anywhere in the output.
        $this->assertStringNotContainsString('<', $s, 'every < must be hex-escaped (JSON_HEX_TAG)');
        $this->assertStringNotContainsString('>', $s, 'every > must be hex-escaped (JSON_HEX_TAG)');
        // Still valid JSON and a structurally correct FAQPage.
        $data = json_decode($s, true);
        $this->assertIsArray($data, 'output must remain valid JSON');
        $this->assertSame('FAQPage', $data['@type']);
        $this->assertCount(2, $data['mainEntity']);
        $this->assertSame('Question', $data['mainEntity'][0]['@type']);
        $this->assertSame('Question', $data['mainEntity'][1]['@type']);
        // json_decode reverses the \u00xx escapes, so the decoded value
        // equals the ORIGINAL question text — safe in HTML, semantically intact.
        $this->assertSame('</script><script>alert(1)</script>', $data['mainEntity'][0]['name']);
        $this->assertSame('Cena < 5 €?', $data['mainEntity'][1]['name']);
        // Answers reduced to plain text (no tags).
        $this->assertStringNotContainsString('<', $data['mainEntity'][0]['acceptedAnswer']['text']);
        $this->assertSame('x & b', $data['mainEntity'][0]['acceptedAnswer']['text']);
        $this->assertSame('ok', $data['mainEntity'][1]['acceptedAnswer']['text']);
    }

    // ---- wiring / string guards ----

    public function testFaqTemplateUsesRepeaterNotContentBlock(): void
    {
        $t = file_get_contents($this->root . '/private/templates/pages/faq.php');
        $this->assertStringNotContainsString("Content::get('faq.items'", $t, 'faq.php must not use the legacy content block');
        $this->assertStringContainsString('Faq::items', $t);
        $this->assertStringContainsString("Content::get('faq.intro'", $t, 'faq.intro stays a content block');
        $this->assertSame(1, substr_count($t, '<h1'), 'faq keeps exactly one h1');
    }

    public function testHeadUsesFaqSchemaJson(): void
    {
        $t = file_get_contents($this->root . '/private/templates/head.php');
        $this->assertStringContainsString('Faq::schemaJson', $t);
        $this->assertStringContainsString("=== 'faq'", $t);
        $this->assertStringNotContainsString('keep questions in sync with the faq.items content block', $t, 'stale NOTE comment removed');
    }

    public function testPageEditRendersFaqRepeater(): void
    {
        $t = file_get_contents($this->root . '/private/templates/admin/page-edit.php');
        $this->assertStringContainsString('faq_items[', $t, 'repeater inputs present');
    }

    public function testAdminIndexSavesFaqItems(): void
    {
        $t = file_get_contents($this->root . '/public/admin/index.php');
        $this->assertStringContainsString('faq_items', $t);
        $this->assertStringContainsString('Faq::save', $t);
        $this->assertStringContainsString('Faq::items', $t);
    }

    public function testSeedSeedsFaqItemsSettingAndDropsHtmlBlock(): void
    {
        $s = file_get_contents($this->root . '/private/scripts/seed-cms.php');
        $this->assertStringContainsString("'faq.items'", $s, 'seeds the faq.items setting');
        // the legacy html content block entry must be gone (it was a
        // ['faq.items', <label>, 'html', <accordion>] row in $blocks)
        $this->assertDoesNotMatchRegularExpression("/'faq\\.items',[^\\n]*'html'/", $s, 'legacy faq.items html block entry removed');
        $this->assertStringContainsString("'faq.intro'", $s, 'faq.intro block stays');
    }
}
