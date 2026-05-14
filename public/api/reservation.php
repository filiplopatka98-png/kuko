<?php
declare(strict_types=1);

require dirname(__DIR__, 2) . '/private/lib/App.php';
\Kuko\App::bootstrap();

if (session_status() === PHP_SESSION_NONE) {
    session_set_cookie_params([
        'lifetime' => 0,
        'path'     => '/',
        'secure'   => isset($_SERVER['HTTPS']),
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_start();
}

header('Content-Type: application/json; charset=utf-8');

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'method_not_allowed']);
    return;
}

$raw = file_get_contents('php://input') ?: '';
$data = json_decode($raw, true);
if (!is_array($data)) {
    http_response_code(400);
    echo json_encode(['error' => 'bad_json']);
    return;
}

// Honeypot — bot detection (fake success)
if (!empty($data['website'])) {
    echo json_encode(['ok' => true, 'message' => 'Ďakujeme, ozveme sa do 24h.']);
    return;
}

// CSRF
$csrf = (string) ($data['csrf'] ?? '');
if (!\Kuko\Csrf::verify($csrf)) {
    http_response_code(403);
    echo json_encode(['error' => 'csrf_invalid']);
    return;
}

// Rate limit by IP
$secret = \Kuko\Config::get('security.ip_hash_secret', '');
$ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
$ipHash = hash('sha256', $ip . '|' . $secret);
$rl = new \Kuko\RateLimit(
    APP_ROOT . '/private/logs/rate',
    (int) \Kuko\Config::get('security.rate_limit_per_hour', 3)
);
if (!$rl->allow($ipHash, 'reservation')) {
    http_response_code(429);
    echo json_encode(['error' => 'rate_limited']);
    return;
}

// reCAPTCHA verify
$captcha = new \Kuko\Recaptcha(
    (string) \Kuko\Config::get('recaptcha.secret_key', ''),
    (float) \Kuko\Config::get('recaptcha.min_score', 0.5),
    new \Kuko\CurlHttpClient()
);
$captchaResult = $captcha->verify(
    (string) ($data['recaptcha_token'] ?? ''),
    'reservation'
);
if (!$captchaResult->ok) {
    http_response_code(400);
    echo json_encode(['error' => 'spam_blocked', 'reason' => $captchaResult->reason]);
    return;
}

// Validate
$errors = \Kuko\Reservation::validate($data);
if ($errors) {
    http_response_code(422);
    echo json_encode(['error' => 'validation', 'fields' => $errors]);
    return;
}

// Persist + mail
try {
    $db = \Kuko\Db::fromConfig();
} catch (\Throwable $e) {
    error_log('[api/reservation] DB connect failed: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'server_error']);
    return;
}

$repo = new \Kuko\ReservationRepo($db);
$id = $repo->create([
    'package'         => $data['package'],
    'wished_date'     => $data['wished_date'],
    'wished_time'     => $data['wished_time'],
    'kids_count'      => (int) $data['kids_count'],
    'name'            => trim((string) $data['name']),
    'phone'           => trim((string) $data['phone']),
    'email'           => trim((string) $data['email']),
    'note'            => trim((string) ($data['note'] ?? '')) ?: null,
    'ip_hash'         => $ipHash,
    'recaptcha_score' => $captchaResult->score,
    'user_agent'      => substr((string) ($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 250),
]);

$record = $repo->find($id);
if ($record === null) {
    http_response_code(500);
    echo json_encode(['error' => 'server_error']);
    return;
}

// Send mails (best effort — failure is logged but does not break the API response)
$mailCfg  = \Kuko\Config::get('mail');
$mailer   = new \Kuko\Mailer($mailCfg);
$renderer = new \Kuko\Renderer(APP_ROOT . '/private/templates/mail');

try {
    $adminHtml = $renderer->render('reservation_admin.html', ['r' => $record]);
    $adminText = $renderer->render('reservation_admin.text', ['r' => $record]);
    $mailer->send(
        (string) $mailCfg['admin_to'],
        '[KUKO] Nová rezervácia — ' . strtoupper((string) $record['package']),
        $adminHtml,
        $adminText,
        (string) $record['email']
    );

    $custHtml = $renderer->render('reservation_customer.html', ['r' => $record]);
    $custText = $renderer->render('reservation_customer.text', ['r' => $record]);
    $mailer->send(
        (string) $record['email'],
        'Potvrdenie prijatia rezervácie — KUKO detský svet',
        $custHtml,
        $custText
    );
} catch (\Throwable $e) {
    error_log('[api/reservation] mail render/send failed: ' . $e->getMessage());
}

echo json_encode(['ok' => true, 'id' => $id, 'message' => 'Ďakujeme, ozveme sa do 24h.']);
