<?php
declare(strict_types=1);
namespace Kuko;

/**
 * Dynamic /llms.txt — Markdown brief for LLM crawlers (ChatGPT, Perplexity,
 * Claude, Gemini AI Overviews). Convention: https://llmstxt.org/
 *
 * Data sources are the SAME ones the homepage renders from, so edits in
 * /admin/contact, /admin/pages (cennik blocks), and /admin/packages take
 * effect on next request. The site never breaks: every field has a
 * seed-identical fallback (matches `private/scripts/seed-cms.php` and
 * `sections/oslavy.php` $defaults — keep all three in sync).
 */
final class LlmsTxt
{
    /** Per-package defaults — byte-identical with seed-cms.php + oslavy.php. */
    private const PACKAGE_DEFAULTS = [
        'mini' => [
            'name'            => 'Oslava KUKO MINI',
            'description'     => 'Bázový balíček pre menšie oslavy s priateľmi. Zahŕňa prenájom časti herne na 2 hodiny.',
            'price_text'      => '120 – 150 € / balíček',
            'kids_count_text' => 'do 10',
            'duration_text'   => '2 hodiny',
        ],
        'maxi' => [
            'name'            => 'Oslava KUKO MAXI',
            'description'     => 'Pre väčšie deti a väčšie skupiny. Plne vybavená oslava s programom.',
            'price_text'      => '220 – 260 € / balíček',
            'kids_count_text' => 'do 20',
            'duration_text'   => '3 hodiny',
        ],
        'closed' => [
            'name'            => 'Uzavretá spoločnosť',
            'description'     => 'Doprajte svojmu dieťaťu oslavu, na ktorú bude ešte dlho spomínať. Pri uzavretej spoločnosti máte celé KUKO len pre seba — v pokojnej a príjemnej atmosfére. Deti si môžu naplno užiť všetky herné prvky a spoločné chvíle s kamarátmi, zatiaľ čo rodičia si vychutnajú oslavu bez stresu a zbytočného zhonu. Počas celej oslavy je vám k dispozícii aj náš personál, ktorý sa postará o pohodlie a hladký priebeh.',
            'price_text'      => '350 € / balíček',
            'kids_count_text' => 'neobmedzene',
            'duration_text'   => '4 hodiny',
        ],
    ];

    /**
     * Render the llms.txt body. Pass a Db handle when available so packages
     * come from the live `packages` table; otherwise the seed-identical
     * defaults above are used (site never 500s on a DB outage).
     */
    public static function render(?Db $db = null): string
    {
        $base = rtrim((string) Config::get('app.url', 'https://kuko-detskysvet.sk'), '/');

        // Contact + cennik — live values via Content (seed-identical fallbacks).
        $address = Content::get('kontakt.address', 'Bratislavská 141, 921 01 Piešťany');
        $phone   = Content::get('kontakt.phone',   '+421 915 319 934');
        $email   = Content::get('kontakt.email',   'info@kuko-detskysvet.sk');
        $hours   = Content::get('kontakt.hours',   'Pondelok – Nedeľa: 9:00 – 20:00');
        $tagline = Content::get('hero.tagline',    'Bezpečné a hravé miesto pre vaše deti v Piešťanoch');

        $cennikRows = [
            [Content::get('cennik.item1.label', 'Dieťa do 1 roku'),               Content::get('cennik.item1.price', 'ZADARMO')],
            [Content::get('cennik.item2.label', 'Dieťa od 1 roku'),               Content::get('cennik.item2.price', '5,00 € / hod')],
            [Content::get('cennik.item3.label', 'Dieťa od 1 roku neobmedzene'),   Content::get('cennik.item3.price', '15,00 €')],
        ];

        // Packages — same source as homepage; defaults match oslavy.php $defaults.
        $packages = self::packageList($db);

        $out  = "# KUKO detský svet\n\n";
        $out .= "> Interiérové detské ihrisko spojené s kaviarňou v Piešťanoch. "
              . "$tagline. Organizujeme aj detské oslavy na mieru.\n\n";

        $out .= "## Kontakt\n";
        $out .= "- Adresa: $address\n";
        $out .= "- Telefón: $phone\n";
        $out .= "- E-mail: $email\n";
        $out .= "- Otváracie hodiny: $hours\n";
        $out .= "- Web: $base/\n\n";

        $out .= "## Cenník vstupu\n";
        foreach ($cennikRows as [$label, $price]) {
            $out .= "- $label: $price\n";
        }
        $out .= "\n";

        $out .= "## Balíčky osláv\n";
        foreach ($packages as $p) {
            $out .= "### {$p['name']}\n";
            $out .= "- Cena: {$p['price_text']}\n";
            $out .= "- Počet detí: {$p['kids_count_text']}\n";
            $out .= "- Trvanie: {$p['duration_text']}\n";
            $out .= "- {$p['description']}\n";
            $out .= "- Rezervácia: $base/rezervacia?balicek={$p['code']}\n\n";
        }

        $out .= "## Rezervácia\n";
        $out .= "- Online formulár: $base/rezervacia\n";
        $out .= "- Alebo telefonicky/e-mailom (kontakty vyššie).\n\n";

        $out .= "## Stránky\n";
        $out .= "- [Domov]($base/)\n";
        $out .= "- [O nás]($base/#o-nas)\n";
        $out .= "- [Detské oslavy]($base/#oslavy)\n";
        $out .= "- [Cenník]($base/#cennik)\n";
        $out .= "- [Fotogaléria]($base/galeria)\n";
        $out .= "- [Časté otázky]($base/faq)\n";
        $out .= "- [Kontakt]($base/#kontakt)\n";
        $out .= "- [Ochrana osobných údajov]($base/ochrana-udajov)\n";
        $out .= "- [Zásady cookies]($base/zasady-cookies)\n";

        return $out;
    }

    /**
     * Resolve the active package list from DB; fall back to defaults per-field.
     * @return array<int,array{code:string,name:string,description:string,price_text:string,kids_count_text:string,duration_text:string}>
     */
    private static function packageList(?Db $db): array
    {
        $rows = [];
        if ($db !== null) {
            try {
                $rows = (new PackagesRepo($db))->listActive();
            } catch (\Throwable $e) {
                error_log('[LlmsTxt] packages load failed: ' . $e->getMessage());
                $rows = [];
            }
        }

        $byCode = [];
        foreach ($rows as $r) {
            $byCode[(string) ($r['code'] ?? '')] = $r;
        }

        $out = [];
        $order = $rows !== [] ? array_keys($byCode) : array_keys(self::PACKAGE_DEFAULTS);
        foreach ($order as $code) {
            $def = self::PACKAGE_DEFAULTS[$code] ?? null;
            $row = $byCode[$code] ?? null;
            if ($def === null && $row === null) continue;
            $pick = static function (string $key) use ($row, $def): string {
                $v = $row[$key] ?? null;
                if (is_string($v) && $v !== '') return $v;
                return (string) ($def[$key] ?? '');
            };
            $out[] = [
                'code'            => (string) $code,
                'name'            => $pick('name'),
                'description'     => $pick('description'),
                'price_text'      => $pick('price_text'),
                'kids_count_text' => $pick('kids_count_text'),
                'duration_text'   => $pick('duration_text'),
            ];
        }
        return $out;
    }
}
