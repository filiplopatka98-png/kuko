<?php
/** @var array<int,array<string,mixed>> $rows */
/** @var string $user */
/** @var array $flashes */
$title = 'Log — KUKO admin';

$fmtPayload = static function ($json): string {
    $s = (string) $json;
    if ($s === '' || $s === 'null' || $s === '[]' || $s === '{}') return '—';
    $d = json_decode($s, true);
    if (!is_array($d) || $d === []) return $s;
    $parts = [];
    foreach ($d as $k => $v) {
        if (is_array($v)) $v = json_encode($v, JSON_UNESCAPED_UNICODE);
        elseif (is_bool($v)) $v = $v ? 'true' : 'false';
        $parts[] = $k . '=' . (string) $v;
    }
    return implode(', ', $parts);
};
ob_start();
?>
<h2>Audit log</h2>
<p class="admin-lead">Posledných 200 záznamov administrátorských akcií.</p>

<?php if (empty($rows)): ?>
  <p class="admin-muted">Zatiaľ žiadne záznamy.</p>
<?php else: ?>
<div class="admin-table-wrap">
  <table class="admin-table">
    <thead>
      <tr><th>Čas</th><th>Používateľ</th><th>Akcia</th><th>Cieľ</th><th>Detaily</th></tr>
    </thead>
    <tbody>
    <?php foreach ($rows as $r): ?>
      <?php
        $tgt = (string) ($r['target_table'] ?? '');
        $tid = (int) ($r['target_id'] ?? 0);
        if ($tid > 0) $tgt .= ' #' . $tid;
      ?>
      <tr>
        <td><?= e((string) ($r['created_at'] ?? '')) ?></td>
        <td><?= e((string) ($r['admin_user'] ?? '')) ?></td>
        <td><code><?= e((string) ($r['action'] ?? '')) ?></code></td>
        <td><?= e($tgt) ?></td>
        <td class="admin-log-payload"><?= e($fmtPayload($r['payload_json'] ?? '')) ?></td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
</div>
<?php endif; ?>
<?php
$content = ob_get_clean();
require __DIR__ . '/layout.php';
