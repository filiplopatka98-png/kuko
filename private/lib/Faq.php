<?php
// private/lib/Faq.php — structured FAQ source of truth.
// Single source for: the public accordion (pages/faq.php), the Google
// FAQPage JSON-LD (head.php) and the admin repeater editor. Stored as a
// JSON list in the `faq.items` setting. Mirrors the site's graceful
// fallback philosophy (Content::get / Seo): never throws, bad/missing
// data degrades to the hardcoded DEFAULT (== the seed values).
declare(strict_types=1);
namespace Kuko;

final class Faq
{
    /**
     * The 6 current Q/A — q = plain text, a = answer HTML (the exact markup
     * shipped today so output is byte-preserved). This is BOTH the in-app
     * default AND the value seed-cms.php writes to the `faq.items` setting,
     * so fallback === seed (keep them identical).
     *
     * @return list<array{q:string,a:string}>
     */
    public static function defaults(): array
    {
        return [
            ['q' => 'Aké sú ceny vstupu do KUKO?',
             'a' => 'Dieťa do 1 roku má vstup <strong>zadarmo</strong>. Dieťa od 1 roku platí <strong>5 € za hodinu</strong>, alebo <strong>15 € na celý deň neobmedzene</strong>.'],
            ['q' => 'Akú oslavu si môžem zarezervovať?',
             'a' => 'Ponúkame 3 balíčky osláv: <strong>KUKO MINI</strong> (do 10 detí, 2 hodiny, 120–150 €), <strong>KUKO MAXI</strong> (do 20 detí, 3 hodiny, 220–260 €) a <strong>Uzavretá spoločnosť</strong> (celé KUKO len pre vás, 4 hodiny, 350 €). <a href="/rezervacia">Rezervovať</a>.'],
            ['q' => 'Aké sú otváracie hodiny?',
             'a' => 'KUKO je otvorený <strong>každý deň</strong>, Pondelok – Nedeľa od 9:00 do 20:00.'],
            ['q' => 'Pre aký vek detí je KUKO vhodný?',
             'a' => 'KUKO je vhodný pre deti od narodenia. Pre najmenších máme bezpečné kojenecké zóny, pre väčšie deti aktívne hracie prvky. Rodičia sú za bezpečnosť svojich detí v priestore zodpovední.'],
            ['q' => 'Kde sa KUKO nachádza?',
             'a' => 'Nájdete nás na <strong>Bratislavskej 141, 921 01 Piešťany</strong>. Pozrite si mapu v sekcii <a href="/#kontakt">Kontakt</a>.'],
            ['q' => 'Ako môžem zrušiť alebo zmeniť rezerváciu?',
             'a' => 'Zmenu alebo zrušenie termínu vybavíme telefonicky na <a href="tel:+421915319934">+421 915 319 934</a> alebo e-mailom na <a href="mailto:info@kuko-detskysvet.sk">info@kuko-detskysvet.sk</a>. Cez web rezerváciu meniť nedá.'],
        ];
    }

    /**
     * Read the `faq.items` setting and decode to a list of {q,a}. Missing
     * key, invalid JSON, or a non-list shape all degrade to defaults().
     * Never throws.
     *
     * @return list<array{q:string,a:string}>
     */
    public static function items(SettingsRepo $s): array
    {
        try {
            $raw = $s->get('faq.items');
        } catch (\Throwable $e) {
            error_log('[Faq] settings read failed: ' . $e->getMessage());
            return self::defaults();
        }
        if ($raw === null || trim($raw) === '') {
            return self::defaults();
        }
        $decoded = json_decode($raw, true);
        if (!is_array($decoded) || !array_is_list($decoded)) {
            return self::defaults();
        }
        $out = [];
        foreach ($decoded as $row) {
            if (!is_array($row)) continue;
            $out[] = [
                'q' => (string) ($row['q'] ?? ''),
                'a' => (string) ($row['a'] ?? ''),
            ];
        }
        return $out === [] ? self::defaults() : array_values($out);
    }

    /**
     * Persist a submitted (already paired & ordered) list of {q,a}.
     * - q: trim + strip_tags (plain text only)
     * - a: HtmlSanitizer::clean(trim(a)) (simple HTML allowed)
     * Rows where BOTH q and a are empty after trimming are dropped, the
     * rest re-indexed, JSON-encoded (unescaped unicode/slashes) and stored.
     *
     * @param array<int|string,mixed> $raw
     */
    public static function save(SettingsRepo $s, array $raw): void
    {
        $clean = [];
        foreach ($raw as $row) {
            if (!is_array($row)) continue;
            $q = trim(strip_tags((string) ($row['q'] ?? '')));
            $a = HtmlSanitizer::clean(trim((string) ($row['a'] ?? '')));
            if ($q === '' && trim($a) === '') continue;
            $clean[] = ['q' => $q, 'a' => $a];
        }
        $json = json_encode(array_values($clean), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $s->set('faq.items', $json === false ? '[]' : $json);
    }

    /**
     * Build the Google FAQPage JSON-LD object string from a list of {q,a}.
     * Answer text is reduced to plain text (tags stripped, entities decoded)
     * so the schema text is clean. Always valid JSON; empty items still
     * yields a valid `mainEntity: []`.
     *
     * @param list<array{q:string,a:string}> $items
     */
    public static function schemaJson(array $items): string
    {
        $mainEntity = [];
        foreach ($items as $it) {
            $q = (string) ($it['q'] ?? '');
            $a = (string) ($it['a'] ?? '');
            $text = trim(html_entity_decode(strip_tags($a), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
            $mainEntity[] = [
                '@type' => 'Question',
                'name'  => $q,
                'acceptedAnswer' => [
                    '@type' => 'Answer',
                    'text'  => $text,
                ],
            ];
        }
        $doc = [
            '@context'   => 'https://schema.org',
            '@type'      => 'FAQPage',
            'mainEntity' => $mainEntity,
        ];
        $json = json_encode($doc, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        return $json === false ? '{"@context":"https://schema.org","@type":"FAQPage","mainEntity":[]}' : $json;
    }
}
