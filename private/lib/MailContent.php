<?php
declare(strict_types=1);
namespace Kuko;

/**
 * Editable e-mail subject + intro text, with DB override and hardcoded
 * fallback. Mirrors the Seo helper's resilience contract: a settings-layer
 * fault (missing table, no DB) degrades to defaults — sending mail must
 * never crash because the admin copy could not be read.
 */
final class MailContent
{
    private static ?SettingsRepo $settings = null;
    private static bool $triedConfig = false;

    /** Editable mail types → admin label. */
    public const TYPES = [
        'reservation_admin'     => 'Notifikácia pre prevádzku — nová rezervácia',
        'reservation_customer'  => 'Zákazníkovi — rezervácia prijatá',
        'reservation_confirmed' => 'Zákazníkovi — rezervácia potvrdená',
        'reservation_cancelled' => 'Zákazníkovi — rezervácia zrušená',
    ];

    public static function setSettings(?SettingsRepo $s): void
    {
        self::$settings = $s;
        self::$triedConfig = false;
    }

    private static function settings(): ?SettingsRepo
    {
        if (self::$settings !== null) return self::$settings;
        if (self::$triedConfig) return null;
        self::$triedConfig = true;
        try {
            self::$settings = new SettingsRepo(Db::fromConfig());
        } catch (\Throwable $e) {
            error_log('[MailContent] settings DB unavailable: ' . $e->getMessage());
            return null;
        }
        return self::$settings;
    }

    private static function settingValue(string $key): ?string
    {
        try {
            return self::settings()?->get($key);
        } catch (\Throwable $e) {
            error_log('[MailContent] settings read failed for "' . $key . '": ' . $e->getMessage());
            return null;
        }
    }

    /** @return array<string,array{subject:string,intro:string}> */
    public static function defaults(): array
    {
        return [
            'reservation_admin' => [
                'subject' => '[KUKO] Nová rezervácia — {package}',
                'intro'   => 'Prišla nová požiadavka na balíček {package}.',
            ],
            'reservation_customer' => [
                'subject' => 'Potvrdenie prijatia rezervácie — KUKO detský svet',
                'intro'   => "prijali sme vašu požiadavku na rezerváciu balíčka {package} dňa {date} o {time} pre {kids} detí.\n\nOzveme sa vám do 24 hodín na telefón alebo e-mail uvedený v rezervácii.",
            ],
            'reservation_confirmed' => [
                'subject' => 'Rezervácia potvrdená — KUKO detský svet',
                'intro'   => "tešíme sa Vás oznámiť, že Vaša rezervácia balíčka {package} dňa {date} o {time} pre {kids} detí je potvrdená.\n\nTešíme sa na Vás!",
            ],
            'reservation_cancelled' => [
                'subject' => 'Rezervácia zrušená — KUKO detský svet',
                'intro'   => 'Vaša rezervácia balíčka {package} dňa {date} o {time} bola zrušená.',
            ],
        ];
    }

    /**
     * Resolve subject + intro for a type: non-empty DB value wins, else default.
     * @return array{subject:string,intro:string}
     */
    public static function resolve(string $type): array
    {
        $d = self::defaults()[$type] ?? ['subject' => '', 'intro' => ''];
        $s = self::settingValue("mail.$type.subject");
        $i = self::settingValue("mail.$type.intro");
        return [
            'subject' => ($s !== null && $s !== '') ? $s : $d['subject'],
            'intro'   => ($i !== null && $i !== '') ? $i : $d['intro'],
        ];
    }

    /** Placeholder → value map derived from a reservation record. */
    public static function tokens(array $r): array
    {
        return [
            '{name}'    => (string) ($r['name'] ?? ''),
            '{package}' => strtoupper((string) ($r['package'] ?? '')),
            '{date}'    => (string) ($r['wished_date'] ?? ''),
            '{time}'    => substr((string) ($r['wished_time'] ?? ''), 0, 5),
            '{kids}'    => (string) (int) ($r['kids_count'] ?? 0),
        ];
    }

    public static function subject(string $type, array $r): string
    {
        return strtr(self::resolve($type)['subject'], self::tokens($r));
    }

    /** Plain-text intro with placeholders substituted (for *.text.php mails). */
    public static function introText(string $type, array $r): string
    {
        return strtr(self::resolve($type)['intro'], self::tokens($r));
    }

    /**
     * HTML intro: blank lines split paragraphs, single newlines become <br>,
     * everything escaped. Admin copy is plain text — never trusted as markup.
     */
    public static function introHtml(string $type, array $r): string
    {
        $blocks = preg_split('/\n{2,}/', trim(self::introText($type, $r))) ?: [];
        $out = '';
        foreach ($blocks as $b) {
            $b = trim($b);
            if ($b === '') continue;
            $out .= '<p>' . nl2br(htmlspecialchars($b, ENT_QUOTES | ENT_HTML5, 'UTF-8')) . '</p>' . "\n";
        }
        return $out;
    }

    /** Representative record used to render the admin live preview. */
    public static function sampleRecord(): array
    {
        return [
            'name'             => 'Janka Nováková',
            'package'          => 'maxi',
            'wished_date'      => date('Y-m-d', strtotime('+10 days')),
            'wished_time'      => '15:00:00',
            'kids_count'       => 12,
            'phone'            => '+421 900 123 456',
            'email'            => 'janka@example.sk',
            'note'             => 'Téma: jednorožce, torta bez orechov.',
            'cancelled_reason' => 'Žiaľ, v daný termín je už obsadené.',
            'view_token'       => 'ukazka-token',
        ];
    }
}
