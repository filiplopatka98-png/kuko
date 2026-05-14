<?php
/** @var string $month */
/** @var \DateTimeImmutable $start */
/** @var \DateTimeImmutable $end */
/** @var string $prev */
/** @var string $next */
/** @var array<int,array<string,mixed>> $rows */
/** @var array<int,array<string,mixed>> $blocked */
/** @var array<int,array<string,mixed>> $hours */
/** @var string $user */
$title = "Kalendár {$month} — KUKO admin";

// Map: date => array of reservations
$byDay = [];
foreach ($rows as $r) {
    $byDay[(string)$r['wished_date']][] = $r;
}
$blockedByDay = [];
foreach ($blocked as $b) {
    $cursor = new \DateTimeImmutable((string)$b['date_from']);
    $stop   = new \DateTimeImmutable((string)$b['date_to']);
    while ($cursor <= $stop) {
        $blockedByDay[$cursor->format('Y-m-d')][] = $b;
        $cursor = $cursor->modify('+1 day');
    }
}

// Build a 6-row grid starting on Monday
$startGrid = $start->modify('-' . ((((int)$start->format('N')) - 1)) . ' day');
$today = (new \DateTimeImmutable('today'))->format('Y-m-d');

ob_start();
?>
<div class="admin-calendar-bar">
  <a class="admin-btn" href="/admin/calendar?month=<?= e($prev) ?>">← <?= e($prev) ?></a>
  <h2><?= e($start->format('F Y')) ?> · <?= e($month) ?></h2>
  <a class="admin-btn" href="/admin/calendar?month=<?= e($next) ?>"><?= e($next) ?> →</a>
</div>

<div class="admin-calendar">
  <div class="admin-calendar__head">
    <div>Po</div><div>Ut</div><div>St</div><div>Št</div><div>Pia</div><div>So</div><div>Ne</div>
  </div>
  <?php for ($w = 0; $w < 6; $w++): ?>
    <div class="admin-calendar__row">
    <?php for ($d = 0; $d < 7; $d++):
      $day = $startGrid->modify('+' . ($w * 7 + $d) . ' day');
      $iso = $day->format('Y-m-d');
      $isCurrentMonth = $day->format('Y-m') === $start->format('Y-m');
      $weekday = (int)$day->format('w');
      $closedDay = empty($hours[$weekday]['is_open']);
      $blocks = $blockedByDay[$iso] ?? [];
      $resvs  = $byDay[$iso] ?? [];
    ?>
      <div class="admin-calendar__cell <?= $isCurrentMonth ? '' : 'admin-calendar__cell--off' ?> <?= $closedDay ? 'admin-calendar__cell--closed' : '' ?> <?= $iso === $today ? 'admin-calendar__cell--today' : '' ?>">
        <div class="admin-calendar__date"><?= (int)$day->format('j') ?></div>
        <?php if ($closedDay): ?>
          <div class="admin-calendar__tag admin-calendar__tag--closed">zatvorené</div>
        <?php endif; ?>
        <?php foreach ($blocks as $b): ?>
          <div class="admin-calendar__tag admin-calendar__tag--block" title="<?= e($b['reason'] ?? '—') ?>">
            <?php if ($b['time_from']): ?>
              <?= e(substr((string)$b['time_from'], 0, 5)) ?>–<?= e(substr((string)$b['time_to'], 0, 5)) ?>
            <?php else: ?>
              blokácia
            <?php endif; ?>
          </div>
        <?php endforeach; ?>
        <?php foreach ($resvs as $r): ?>
          <a class="admin-calendar__resv admin-calendar__resv--<?= e($r['package']) ?> admin-calendar__resv--<?= e($r['status']) ?>"
             href="/admin/reservation/<?= (int)$r['id'] ?>"
             title="<?= e($r['name']) ?> – <?= e($r['status']) ?>">
            <?= e(substr((string)$r['wished_time'], 0, 5)) ?> <?= e(strtoupper((string)$r['package'])) ?>
          </a>
        <?php endforeach; ?>
      </div>
    <?php endfor; ?>
    </div>
  <?php endfor; ?>
</div>

<div class="admin-calendar-legend">
  <span class="legend-item legend-item--mini">MINI</span>
  <span class="legend-item legend-item--maxi">MAXI</span>
  <span class="legend-item legend-item--closed">UZAVRETÁ</span>
  <span class="legend-item legend-item--cancelled">zrušené</span>
  <span class="legend-item legend-item--block">blokácia</span>
</div>
<?php
$content = ob_get_clean();
require __DIR__ . '/layout.php';
