<?php
declare(strict_types=1);
namespace Kuko;

final class CalendarLink
{
    /**
     * Build a "Add to Google Calendar" URL for a reservation.
     * Returns '' if the reservation date/time cannot be parsed.
     */
    public static function google(array $r, int $durationMin, string $tz): string
    {
        $startStr = trim((string) ($r['wished_date'] ?? '') . ' ' . (string) ($r['wished_time'] ?? ''));
        // DateTimeImmutable('') silently means "now" — reject an empty date.
        if ($startStr === '') return '';
        try {
            $start = new \DateTimeImmutable($startStr, new \DateTimeZone($tz));
        } catch (\Throwable) {
            return '';
        }
        $end = $start->modify('+' . max(1, $durationMin) . ' minutes');
        $utc = static fn(\DateTimeImmutable $d): string =>
            $d->setTimezone(new \DateTimeZone('UTC'))->format('Ymd\THis\Z');

        $pkg = strtoupper((string) ($r['package'] ?? ''));
        $title = trim($pkg . ' — ' . (string) ($r['name'] ?? ''), ' —');
        $details = sprintf(
            "Balíček: %s\nPočet detí: %d\nTelefón: %s\nE-mail: %s\nPoznámka: %s",
            $pkg,
            (int) ($r['kids_count'] ?? 0),
            (string) ($r['phone'] ?? ''),
            (string) ($r['email'] ?? ''),
            str_replace(["\r", "\n"], ['', ' / '], (string) ($r['note'] ?? '—'))
        );

        return 'https://calendar.google.com/calendar/render?' . http_build_query([
            'action'   => 'TEMPLATE',
            'text'     => $title,
            'dates'    => $utc($start) . '/' . $utc($end),
            'details'  => $details,
            'location' => 'Bratislavská 141, 921 01 Piešťany',
        ], '', '&', PHP_QUERY_RFC3986);
    }
}
