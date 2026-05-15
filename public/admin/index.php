<?php
declare(strict_types=1);

require dirname(__DIR__, 2) . '/private/lib/App.php';
\Kuko\App::bootstrap();

use Kuko\Auth;
use Kuko\Db;
use Kuko\Renderer;
use Kuko\Router;
use Kuko\ReservationRepo;
use Kuko\SettingsRepo;
use Kuko\PackagesRepo;
use Kuko\OpeningHoursRepo;
use Kuko\BlockedPeriodsRepo;
use Kuko\Availability;

$renderer = new Renderer(APP_ROOT . '/private/templates/admin');
$router   = new Router();

$path  = parse_url($_SERVER['REQUEST_URI'] ?? '/admin', PHP_URL_PATH) ?: '/admin';
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

// === Login / logout routes (no auth required) ===

$router->get('/admin/login', function () use ($renderer) {
    if (Auth::isAuthenticated()) {
        header('Location: /admin');
        return;
    }
    echo $renderer->render('login', ['next' => (string) ($_GET['next'] ?? '/admin')]);
});

$router->post('/admin/login', function () use ($renderer) {
    $user     = trim((string) ($_POST['username'] ?? ''));
    $pass     = (string) ($_POST['password'] ?? '');
    $remember = !empty($_POST['remember']);
    $next     = (string) ($_POST['next'] ?? '/admin');
    if (!preg_match('#^/admin#', $next)) $next = '/admin';

    if (Auth::attempt($user, $pass, $remember)) {
        header('Location: ' . $next);
        return;
    }
    http_response_code(401);
    echo $renderer->render('login', ['error' => true, 'next' => $next]);
});

$router->get('/admin/logout', function () {
    Auth::logout();
    header('Location: /admin/login');
});

$router->post('/admin/logout', function () {
    Auth::logout();
    header('Location: /admin/login');
});

// === All other admin routes require login ===

$loginRoutePaths = ['/admin/login', '/admin/login/', '/admin/logout', '/admin/logout/'];
$isLoginRoute = in_array(rtrim($path, '/'), array_map(fn($p) => rtrim($p, '/'), $loginRoutePaths), true);

if (!$isLoginRoute) {
    if (!Auth::isAuthenticated()) {
        header('Location: /admin/login?next=' . rawurlencode($path));
        return;
    }
}

try {
    $db = Db::fromConfig();
} catch (\Throwable $e) {
    http_response_code(500);
    echo '<h1>Database connection failed</h1><p>' . htmlspecialchars($e->getMessage()) . '</p>';
    return;
}

$repo       = new ReservationRepo($db);
$settings   = new SettingsRepo($db);
$packages   = new PackagesRepo($db);
$hours      = new OpeningHoursRepo($db);
$blocked    = new BlockedPeriodsRepo($db);
$adminUser  = Auth::user() ?? 'unknown';

$nowTz = new \DateTimeZone(\Kuko\Config::get('app.tz', 'Europe/Bratislava'));
$availability = fn() => new Availability($db, $settings, $packages, $hours, $blocked, new \DateTimeImmutable('now', $nowTz));

$flash = function (string $msg, string $type = 'ok') {
    $_SESSION['flash'][] = ['msg' => $msg, 'type' => $type];
};

$audit = function (string $action, string $table, int $id, array $payload = []) use ($db, $adminUser) {
    $secret = (string) \Kuko\Config::get('security.ip_hash_secret', '');
    $db->execStmt(
        'INSERT INTO admin_actions (admin_user, action, target_table, target_id, payload_json, ip_hash) VALUES (?,?,?,?,?,?)',
        [$adminUser, $action, $table, $id, json_encode($payload), hash('sha256', ($_SERVER['REMOTE_ADDR'] ?? '') . '|' . $secret)]
    );
};

$flashes = $_SESSION['flash'] ?? [];
$_SESSION['flash'] = [];

// ===== Reservations =====
$router->get('/admin', function () use ($renderer, $repo, $adminUser, $flashes) {
    $filter = [
        'status'  => $_GET['status']  ?? null,
        'package' => $_GET['package'] ?? null,
        'from'    => $_GET['from']    ?? null,
        'to'      => $_GET['to']      ?? null,
    ];
    $rows = $repo->list(array_filter($filter, fn($v) => $v !== null && $v !== ''));
    echo $renderer->render('list', ['rows' => $rows, 'filter' => $filter, 'user' => $adminUser, 'flashes' => $flashes]);
});

$router->get('/admin/reservation/{id}', function (array $p) use ($renderer, $repo, $adminUser, $flashes) {
    $row = $repo->find((int) $p['id']);
    if ($row === null) {
        http_response_code(404);
        echo $renderer->render('not-found', ['user' => $adminUser, 'flashes' => $flashes]);
        return;
    }
    echo $renderer->render('detail', ['r' => $row, 'user' => $adminUser, 'flashes' => $flashes]);
});

$router->post('/admin/reservation/{id}/status', function (array $p) use ($repo, $audit, $flash) {
    if (!\Kuko\Csrf::verify((string) ($_POST['csrf'] ?? ''))) { http_response_code(403); echo 'csrf'; return; }
    $id = (int) $p['id'];
    $status = (string) ($_POST['status'] ?? '');
    try {
        $previous = $repo->find($id);
        $repo->setStatus($id, $status);
        if ($status === 'confirmed') $repo->markConfirmed($id);
        if ($status === 'cancelled') $repo->markCancelled($id, (string) ($_POST['reason'] ?? ''));
        $audit('set_status', 'reservations', $id, ['status' => $status]);
        $flash('Status zmenený na ' . $status . '.');

        if ($previous !== null && $previous['status'] !== $status && in_array($status, ['confirmed', 'cancelled'], true)) {
            try {
                $current = $repo->find($id);
                $mailCfg = \Kuko\Config::get('mail');
                $mailer = new \Kuko\Mailer($mailCfg);
                $mailRenderer = new \Kuko\Renderer(APP_ROOT . '/private/templates/mail');
                $appUrl = rtrim((string) \Kuko\Config::get('app.url', ''), '/');
                $statusLink = $appUrl . '/rezervacia/' . (string) $current['view_token'];

                $template = $status === 'confirmed' ? 'reservation_confirmed' : 'reservation_cancelled';
                $subject  = $status === 'confirmed'
                    ? 'Rezervácia potvrdená — KUKO detský svet'
                    : 'Rezervácia zrušená — KUKO detský svet';
                $html = $mailRenderer->render($template . '.html', ['r' => $current, 'statusLink' => $statusLink]);
                $text = $mailRenderer->render($template . '.text', ['r' => $current, 'statusLink' => $statusLink]);
                $mailer->send((string) $current['email'], $subject, $html, $text);
            } catch (\Throwable $e) {
                error_log('[admin/status] notify mail failed: ' . $e->getMessage());
            }
        }
    } catch (\InvalidArgumentException) {
        http_response_code(400); echo 'bad status'; return;
    }
    header('Location: /admin/reservation/' . $id);
});

$router->post('/admin/reservation/{id}/move', function (array $p) use ($repo, $audit, $flash, $availability) {
    if (!\Kuko\Csrf::verify((string) ($_POST['csrf'] ?? ''))) { http_response_code(403); echo 'csrf'; return; }
    $id = (int) $p['id'];
    $row = $repo->find($id);
    if ($row === null) { http_response_code(404); echo 'not found'; return; }
    $newDate = (string) ($_POST['wished_date'] ?? '');
    $newTime = (string) ($_POST['wished_time'] ?? '');
    $repo->setStatus($id, 'cancelled');
    $slots = $availability()->forDate($newDate, (string) $row['package'])->slots;
    if (!in_array($newTime, $slots, true)) {
        $repo->setStatus($id, (string) $row['status']);
        $flash('Termín nie je dostupný. Vyberte iný čas.', 'err');
        header('Location: /admin/reservation/' . $id);
        return;
    }
    $repo->moveTo($id, $newDate, $newTime);
    $repo->setStatus($id, (string) $row['status']);
    $audit('move', 'reservations', $id, ['from' => $row['wished_date'] . ' ' . $row['wished_time'], 'to' => $newDate . ' ' . $newTime]);
    $flash("Termín presunutý na $newDate o $newTime.");
    header('Location: /admin/reservation/' . $id);
});

// ===== Settings =====
$router->get('/admin/settings', function () use ($renderer, $settings, $adminUser, $flashes) {
    echo $renderer->render('settings', ['settings' => $settings->all(), 'user' => $adminUser, 'flashes' => $flashes]);
});
$router->post('/admin/settings', function () use ($settings, $audit, $flash) {
    if (!\Kuko\Csrf::verify((string) ($_POST['csrf'] ?? ''))) { http_response_code(403); echo 'csrf'; return; }
    foreach (SettingsRepo::KNOWN_KEYS as $k) {
        if (isset($_POST[$k])) {
            $v = (string) (int) $_POST[$k];
            $settings->set($k, $v);
        }
    }
    $audit('update_settings', 'settings', 0, $_POST);
    $flash('Nastavenia uložené.');
    header('Location: /admin/settings');
});

// ===== Content blocks =====
$router->get('/admin/content', function () use ($renderer, $db, $adminUser, $flashes) {
    $cb = new \Kuko\ContentBlocksRepo($db);
    echo $renderer->render('content', ['groups' => $cb->listGrouped(), 'user' => $adminUser, 'flashes' => $flashes]);
});
$router->post('/admin/content/save', function () use ($db, $audit, $flash, $adminUser) {
    if (!\Kuko\Csrf::verify((string) ($_POST['csrf'] ?? ''))) { http_response_code(403); echo 'csrf'; return; }
    $key  = (string) ($_POST['block_key'] ?? '');
    $type = (string) ($_POST['content_type'] ?? 'text');
    $val  = (string) ($_POST['value'] ?? '');
    if ($key === '') { $flash('Chýba kľúč bloku.', 'err'); header('Location: /admin/content'); return; }
    if (!in_array($type, ['text', 'html'], true)) $type = 'text';
    $cb = new \Kuko\ContentBlocksRepo($db);
    $cb->set($key, $val, $type, $adminUser);
    $audit('content_save', 'content_blocks', 0, ['key' => $key]);
    $flash('Blok „' . $key . '" uložený.');
    header('Location: /admin/content');
});

// ===== Contact (kontakt content blocks + social settings) =====
$router->get('/admin/contact', function () use ($renderer, $db, $settings, $adminUser, $flashes) {
    $cb = new \Kuko\ContentBlocksRepo($db);
    $contact = [
        'address' => $cb->get('kontakt.address') ?? 'Bratislavská 141, 921 01 Piešťany',
        'phone'   => $cb->get('kontakt.phone')   ?? '+421 915 319 934',
        'email'   => $cb->get('kontakt.email')   ?? 'info@kuko-detskysvet.sk',
        'hours'   => $cb->get('kontakt.hours')   ?? 'Pondelok – Nedeľa: 9:00 – 20:00',
        'facebook'  => $settings->get('social.facebook')  ?? (string) \Kuko\Config::get('social.facebook', ''),
        'instagram' => $settings->get('social.instagram') ?? (string) \Kuko\Config::get('social.instagram', ''),
    ];
    echo $renderer->render('contact', ['contact' => $contact, 'user' => $adminUser, 'flashes' => $flashes]);
});
$router->post('/admin/contact', function () use ($db, $settings, $audit, $flash, $adminUser) {
    if (!\Kuko\Csrf::verify((string) ($_POST['csrf'] ?? ''))) { http_response_code(403); echo 'csrf'; return; }
    $cb = new \Kuko\ContentBlocksRepo($db);
    foreach (['address', 'phone', 'email', 'hours'] as $f) {
        $cb->set('kontakt.' . $f, trim((string) ($_POST[$f] ?? '')), 'text', $adminUser);
    }
    $settings->set('social.facebook',  trim((string) ($_POST['facebook']  ?? '')));
    $settings->set('social.instagram', trim((string) ($_POST['instagram'] ?? '')));
    $audit('contact_save', 'content_blocks', 0);
    $flash('Kontaktné údaje uložené.');
    header('Location: /admin/contact');
});

// ===== Gallery =====
$galleryDir = APP_ROOT . '/public/assets/img/gallery';
$router->get('/admin/gallery', function () use ($renderer, $db, $galleryDir, $adminUser, $flashes) {
    $photos = (new \Kuko\MediaRepo($db, $galleryDir))->listAll();
    echo $renderer->render('gallery', ['photos' => $photos, 'user' => $adminUser, 'flashes' => $flashes]);
});
$router->post('/admin/gallery/upload', function () use ($db, $galleryDir, $audit, $flash) {
    if (!\Kuko\Csrf::verify((string) ($_POST['csrf'] ?? ''))) { http_response_code(403); echo 'csrf'; return; }
    try {
        $row = (new \Kuko\MediaRepo($db, $galleryDir))->upload($_FILES['photo'] ?? [], trim((string) ($_POST['alt'] ?? '')));
        $audit('gallery_upload', 'gallery_photos', (int) $row['id']);
        $flash('Fotka nahraná.');
    } catch (\RuntimeException $e) {
        $flash($e->getMessage(), 'err');
    }
    header('Location: /admin/gallery');
});
$router->post('/admin/gallery/{id}/delete', function (array $p) use ($db, $galleryDir, $audit, $flash) {
    if (!\Kuko\Csrf::verify((string) ($_POST['csrf'] ?? ''))) { http_response_code(403); echo 'csrf'; return; }
    $id = (int) $p['id'];
    (new \Kuko\MediaRepo($db, $galleryDir))->delete($id);
    $audit('gallery_delete', 'gallery_photos', $id);
    $flash('Fotka zmazaná.');
    header('Location: /admin/gallery');
});
$router->post('/admin/gallery/{id}/visibility', function (array $p) use ($db, $galleryDir, $audit, $flash) {
    if (!\Kuko\Csrf::verify((string) ($_POST['csrf'] ?? ''))) { http_response_code(403); echo 'csrf'; return; }
    $id = (int) $p['id'];
    $visible = !empty($_POST['visible']);
    (new \Kuko\MediaRepo($db, $galleryDir))->setVisibility($id, $visible);
    $audit('gallery_visibility', 'gallery_photos', $id, ['visible' => $visible]);
    $flash($visible ? 'Fotka zobrazená.' : 'Fotka skrytá.');
    header('Location: /admin/gallery');
});
$router->post('/admin/gallery/{id}/alt', function (array $p) use ($db, $galleryDir, $audit, $flash) {
    if (!\Kuko\Csrf::verify((string) ($_POST['csrf'] ?? ''))) { http_response_code(403); echo 'csrf'; return; }
    $id = (int) $p['id'];
    (new \Kuko\MediaRepo($db, $galleryDir))->updateAlt($id, trim((string) ($_POST['alt'] ?? '')));
    $audit('gallery_alt', 'gallery_photos', $id);
    $flash('ALT text uložený.');
    header('Location: /admin/gallery');
});
$router->post('/admin/gallery/reorder', function () use ($db, $galleryDir) {
    $body  = json_decode(file_get_contents('php://input') ?: '', true);
    $token = (string) ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? '');
    if (!\Kuko\Csrf::verify($token)) {
        http_response_code(403);
        header('Content-Type: application/json');
        echo json_encode(['error' => 'csrf']);
        return;
    }
    $order = (is_array($body) ? ($body['order'] ?? []) : []);
    if (is_array($order)) {
        (new \Kuko\MediaRepo($db, $galleryDir))->reorder(array_map('intval', $order));
    }
    header('Content-Type: application/json');
    echo json_encode(['ok' => true]);
});

// ===== Opening hours =====
$router->get('/admin/opening-hours', function () use ($renderer, $hours, $adminUser, $flashes) {
    echo $renderer->render('opening-hours', ['hours' => $hours->all(), 'user' => $adminUser, 'flashes' => $flashes]);
});
$router->post('/admin/opening-hours', function () use ($hours, $audit, $flash) {
    if (!\Kuko\Csrf::verify((string) ($_POST['csrf'] ?? ''))) { http_response_code(403); echo 'csrf'; return; }
    for ($d = 0; $d < 7; $d++) {
        $isOpen = !empty($_POST['is_open'][$d]);
        $from = (string) ($_POST['open_from'][$d] ?? '09:00');
        $to   = (string) ($_POST['open_to'][$d]   ?? '20:00');
        $hours->update($d, $isOpen, $from, $to);
    }
    $audit('update_opening_hours', 'opening_hours', 0);
    $flash('Otváracie hodiny uložené.');
    header('Location: /admin/opening-hours');
});

// ===== Blocked periods =====
$router->get('/admin/blocked-periods', function () use ($renderer, $blocked, $adminUser, $flashes) {
    echo $renderer->render('blocked-periods', ['rows' => $blocked->listAll(), 'user' => $adminUser, 'flashes' => $flashes]);
});
$router->post('/admin/blocked-periods', function () use ($blocked, $audit, $flash) {
    if (!\Kuko\Csrf::verify((string) ($_POST['csrf'] ?? ''))) { http_response_code(403); echo 'csrf'; return; }
    $df = (string) ($_POST['date_from'] ?? '');
    $dt = (string) ($_POST['date_to']   ?? '');
    $tf = (string) ($_POST['time_from'] ?? '');
    $tt = (string) ($_POST['time_to']   ?? '');
    if ($df === '' || $dt === '') { $flash('Dátumy sú povinné.', 'err'); header('Location: /admin/blocked-periods'); return; }
    $id = $blocked->create(
        $df, $dt,
        $tf === '' ? null : $tf,
        $tt === '' ? null : $tt,
        (string) ($_POST['reason'] ?? '') ?: null
    );
    $audit('create_blocked', 'blocked_periods', $id);
    $flash('Blokované obdobie pridané.');
    header('Location: /admin/blocked-periods');
});
$router->post('/admin/blocked-periods/{id}/delete', function (array $p) use ($blocked, $audit, $flash) {
    if (!\Kuko\Csrf::verify((string) ($_POST['csrf'] ?? ''))) { http_response_code(403); echo 'csrf'; return; }
    $id = (int) $p['id'];
    $blocked->delete($id);
    $audit('delete_blocked', 'blocked_periods', $id);
    $flash('Blokované obdobie zmazané.');
    header('Location: /admin/blocked-periods');
});

// ===== Packages =====
$router->get('/admin/packages', function () use ($renderer, $packages, $adminUser, $flashes) {
    echo $renderer->render('packages', ['rows' => $packages->listAll(), 'user' => $adminUser, 'flashes' => $flashes]);
});
$router->post('/admin/packages/{code}', function (array $p) use ($packages, $audit, $flash) {
    if (!\Kuko\Csrf::verify((string) ($_POST['csrf'] ?? ''))) { http_response_code(403); echo 'csrf'; return; }
    $code = (string) $p['code'];
    $accent = (string) ($_POST['accent_color'] ?? 'blue');
    if (!in_array($accent, ['blue', 'purple', 'yellow'], true)) $accent = 'blue';
    $items = array_values(array_filter(array_map('trim', explode("\n", (string) ($_POST['included'] ?? '')))));
    $includedJson = json_encode($items, JSON_UNESCAPED_UNICODE);
    $packages->update($code, [
        'name'            => (string) $_POST['name'],
        'duration_min'    => (int) $_POST['duration_min'],
        'blocks_full_day' => !empty($_POST['blocks_full_day']),
        'is_active'       => !empty($_POST['is_active']),
        'sort_order'      => (int) ($_POST['sort_order'] ?? 0),
        'description'     => \Kuko\HtmlSanitizer::clean((string) ($_POST['description'] ?? '')),
        'price_text'      => (string) ($_POST['price_text'] ?? ''),
        'kids_count_text' => (string) ($_POST['kids_count_text'] ?? ''),
        'duration_text'   => (string) ($_POST['duration_text'] ?? ''),
        'included_json'   => $includedJson,
        'accent_color'    => $accent,
    ]);
    $audit('update_package', 'packages', 0, ['code' => $code]);
    $flash("Balíček $code uložený.");
    header('Location: /admin/packages');
});

// ===== Calendar =====
$router->get('/admin/calendar', function () use ($renderer, $db, $blocked, $hours, $adminUser, $flashes, $nowTz) {
    $month = (string) ($_GET['month'] ?? (new \DateTimeImmutable('now', $nowTz))->format('Y-m'));
    if (!preg_match('/^\d{4}-\d{2}$/', $month)) { $month = (new \DateTimeImmutable('now', $nowTz))->format('Y-m'); }
    $start = new \DateTimeImmutable($month . '-01', $nowTz);
    $end   = $start->modify('last day of this month');
    $rows = $db->all(
        "SELECT id, package, wished_date, wished_time, status, name FROM reservations
         WHERE wished_date BETWEEN ? AND ? ORDER BY wished_date, wished_time",
        [$start->format('Y-m-d'), $end->format('Y-m-d')]
    );
    $blockedPeriods = $blocked->listOverlapping($start->format('Y-m-d'), $end->format('Y-m-d'));
    echo $renderer->render('calendar', [
        'month'     => $month,
        'start'     => $start,
        'end'       => $end,
        'prev'      => $start->modify('-1 month')->format('Y-m'),
        'next'      => $start->modify('+1 month')->format('Y-m'),
        'rows'      => $rows,
        'blocked'   => $blockedPeriods,
        'hours'     => $hours->all(),
        'user'      => $adminUser,
        'flashes'   => $flashes,
    ]);
});

// iCal feed (subscribe in Google/Apple Calendar)
$router->get('/admin/calendar.ics', function () use ($db, $packages) {
    header('Content-Type: text/calendar; charset=utf-8');
    header('Content-Disposition: attachment; filename="kuko-rezervacie.ics"');
    $rows = $db->all(
        "SELECT * FROM reservations WHERE status IN ('pending','confirmed') ORDER BY wished_date, wished_time"
    );
    $tz = (string) \Kuko\Config::get('app.tz', 'Europe/Bratislava');
    $lines = [
        'BEGIN:VCALENDAR',
        'VERSION:2.0',
        'PRODID:-//KUKO detský svet//Rezervácie//SK',
        'CALSCALE:GREGORIAN',
        'X-WR-CALNAME:KUKO rezervácie',
        'X-WR-TIMEZONE:' . $tz,
    ];
    foreach ($rows as $r) {
        $pkg = $packages->find((string) $r['package']);
        $duration = (int) ($pkg['duration_min'] ?? 120);
        $startStr = (string) $r['wished_date'] . ' ' . (string) $r['wished_time'];
        try {
            $start = new \DateTimeImmutable($startStr, new \DateTimeZone($tz));
        } catch (\Throwable) { continue; }
        $end = $start->modify("+{$duration} minutes");
        $statusLabel = match ((string) $r['status']) {
            'pending'   => 'PENDING',
            'confirmed' => 'CONFIRMED',
            default     => 'TENTATIVE',
        };
        $summary = sprintf('%s — %s (%dx, %s)', strtoupper((string) $r['package']), $r['name'], (int) $r['kids_count'], $statusLabel);
        $desc = sprintf("Balíček: %s\\nKlient: %s\\nTelefón: %s\\nE-mail: %s\\nDetí: %d\\nPoznámka: %s",
            strtoupper((string) $r['package']),
            (string) $r['name'],
            (string) $r['phone'],
            (string) $r['email'],
            (int) $r['kids_count'],
            str_replace(["\r", "\n"], ['', ' / '], (string) ($r['note'] ?? '—'))
        );
        $lines[] = 'BEGIN:VEVENT';
        $lines[] = 'UID:kuko-' . (int) $r['id'] . '@kuko-detskysvet.sk';
        $lines[] = 'DTSTAMP:' . (new \DateTimeImmutable('now', new \DateTimeZone('UTC')))->format('Ymd\THis\Z');
        $lines[] = 'DTSTART:' . $start->setTimezone(new \DateTimeZone('UTC'))->format('Ymd\THis\Z');
        $lines[] = 'DTEND:'   . $end->setTimezone(new \DateTimeZone('UTC'))->format('Ymd\THis\Z');
        $lines[] = 'SUMMARY:' . str_replace([',', ';'], ['\\,', '\\;'], $summary);
        $lines[] = 'DESCRIPTION:' . str_replace([',', ';'], ['\\,', '\\;'], $desc);
        $lines[] = 'STATUS:' . ($r['status'] === 'confirmed' ? 'CONFIRMED' : 'TENTATIVE');
        $lines[] = 'LOCATION:Bratislavská 141\\, 921 01 Piešťany';
        $lines[] = 'END:VEVENT';
    }
    $lines[] = 'END:VCALENDAR';
    echo implode("\r\n", $lines) . "\r\n";
});

$match = $router->match($method, $path);
if ($match === null) {
    http_response_code(404);
    echo $renderer->render('not-found', ['user' => $adminUser, 'flashes' => $flashes]);
    return;
}
($match->handler)($match->params ?? []);
