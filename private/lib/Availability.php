<?php
declare(strict_types=1);
namespace Kuko;

final class AvailabilityResult
{
    /**
     * @param string[] $slots HH:MM start times
     */
    public function __construct(
        public readonly array $slots,
        public readonly ?string $reason,
        public readonly int $durationMin,
    ) {}
}

final class Availability
{
    public function __construct(
        private Db $db,
        private SettingsRepo $settings,
        private PackagesRepo $packages,
        private OpeningHoursRepo $hours,
        private BlockedPeriodsRepo $blocked,
        private \DateTimeImmutable $now,
    ) {}

    /**
     * Compute available start-time slots for a given date and package code.
     */
    public function forDate(string $date, string $packageCode): AvailabilityResult
    {
        $pkg = $this->packages->find($packageCode);
        if ($pkg === null) {
            return new AvailabilityResult([], 'unknown_package', 0);
        }
        $duration = (int) $pkg['duration_min'];

        $dateObj = \DateTimeImmutable::createFromFormat('!Y-m-d', $date);
        if (!$dateObj || $dateObj->format('Y-m-d') !== $date) {
            return new AvailabilityResult([], 'bad_date', $duration);
        }

        // Lead time
        $leadHours = $this->settings->getInt('lead_hours', 24);
        $earliest = $this->now->modify("+{$leadHours} hours");
        if ($dateObj->setTime(23, 59, 59) < $earliest) {
            return new AvailabilityResult([], 'before_lead', $duration);
        }

        // Horizon
        $horizonDays = $this->settings->getInt('horizon_days', 180);
        $latest = $this->now->modify("+{$horizonDays} days");
        if ($dateObj > $latest->setTime(23, 59, 59)) {
            return new AvailabilityResult([], 'after_horizon', $duration);
        }

        $weekday = (int) $dateObj->format('w'); // 0..6 Sun..Sat
        $hours = $this->hours->forWeekday($weekday);
        if ($hours === null || (int) $hours['is_open'] === 0) {
            return new AvailabilityResult([], 'closed_day', $duration);
        }

        // Existing reservations on the day (status in pending,confirmed)
        $existing = $this->db->all(
            "SELECT r.wished_time, r.package, p.duration_min, p.blocks_full_day
             FROM reservations r JOIN packages p ON p.code = r.package
             WHERE r.wished_date = ? AND r.status IN ('pending','confirmed')",
            [$date]
        );
        foreach ($existing as $e) {
            if ((int) $e['blocks_full_day'] === 1) {
                return new AvailabilityResult([], 'blocked_full_day', $duration);
            }
        }

        // If the requested package itself is "full-day" but the day is already partially booked
        if ((int) $pkg['blocks_full_day'] === 1 && count($existing) > 0) {
            return new AvailabilityResult([], 'blocked_full_day', $duration);
        }

        // Blocked periods
        $blockedToday = $this->blocked->listForDate($date);
        foreach ($blockedToday as $b) {
            if ($b['time_from'] === null && $b['time_to'] === null) {
                return new AvailabilityResult([], 'blocked_full_day', $duration);
            }
        }

        // Build free intervals starting from open_from..open_to
        $openFrom = $this->minutes((string) $hours['open_from']);
        $openTo   = $this->minutes((string) $hours['open_to']);
        $intervals = [[$openFrom, $openTo]];

        // Subtract blocked time-windows
        foreach ($blockedToday as $b) {
            $bStart = $this->minutes((string) $b['time_from']);
            $bEnd   = $this->minutes((string) $b['time_to']);
            $intervals = $this->subtract($intervals, $bStart, $bEnd);
        }

        // Subtract existing reservations + buffer
        $buffer = $this->settings->getInt('buffer_min', 30);
        foreach ($existing as $e) {
            if ((int) $e['blocks_full_day'] === 1) continue; // unreachable, defensive
            $rStart = $this->minutes((string) $e['wished_time']);
            $rEnd   = $rStart + (int) $e['duration_min'] + $buffer;
            $intervals = $this->subtract($intervals, $rStart, $rEnd);
        }

        // If date == today: shift start past now+lead
        if ($dateObj->format('Y-m-d') === $this->now->format('Y-m-d')) {
            $cutoff = $this->minutes($earliest->format('H:i'));
            $intervals = $this->subtract($intervals, 0, $cutoff);
        }

        // Generate slot start times
        $step = max(5, $this->settings->getInt('slot_increment_min', 30));
        $slots = [];
        foreach ($intervals as [$a, $b]) {
            $t = $this->alignUp($a, $step);
            while ($t + $duration <= $b) {
                $slots[] = $this->fmt($t);
                $t += $step;
            }
        }

        return new AvailabilityResult(
            $slots,
            $slots === [] ? 'full' : null,
            $duration
        );
    }

    /**
     * Returns a status map for every day of a given month for a given package.
     * Days outside the [today+lead, today+horizon] window are still reported but with
     * the appropriate reason so the UI can show them as disabled.
     *
     * @return array<string,array{status:string,slots_count:int,reason:?string}>
     */
    public function forMonth(string $month, string $packageCode): array
    {
        if (!preg_match('/^\d{4}-\d{2}$/', $month)) return [];
        $start = new \DateTimeImmutable($month . '-01');
        $end   = $start->modify('last day of this month');
        $out = [];
        $cursor = $start;
        while ($cursor <= $end) {
            $iso = $cursor->format('Y-m-d');
            $r = $this->forDate($iso, $packageCode);
            $out[$iso] = [
                'status'      => $r->slots === [] ? 'unavailable' : 'available',
                'slots_count' => count($r->slots),
                'reason'      => $r->reason,
            ];
            $cursor = $cursor->modify('+1 day');
        }
        return $out;
    }

    private function minutes(string $hms): int
    {
        $parts = explode(':', $hms);
        return ((int) $parts[0]) * 60 + (int) ($parts[1] ?? 0);
    }

    private function fmt(int $minutes): string
    {
        return sprintf('%02d:%02d', intdiv($minutes, 60), $minutes % 60);
    }

    private function alignUp(int $value, int $step): int
    {
        $r = $value % $step;
        return $r === 0 ? $value : $value + ($step - $r);
    }

    /**
     * Subtract [$start, $end) from a list of disjoint sorted intervals.
     * @param array<int,array{0:int,1:int}> $intervals
     * @return array<int,array{0:int,1:int}>
     */
    private function subtract(array $intervals, int $start, int $end): array
    {
        if ($start >= $end) return $intervals;
        $out = [];
        foreach ($intervals as [$a, $b]) {
            if ($end <= $a || $start >= $b) {
                $out[] = [$a, $b];
                continue;
            }
            if ($start > $a) $out[] = [$a, min($start, $b)];
            if ($end   < $b) $out[] = [max($end, $a), $b];
        }
        return $out;
    }
}
