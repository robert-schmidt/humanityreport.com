<?php
/**
 * Sign endpoint for the HumanityReport Manifesto.
 *
 *   POST application/json or form-encoded:
 *     { name, email?, country?, comment?, website? }
 *     "website" is a honeypot — bots fill every field; real users leave it blank.
 *     Email is optional; if provided, is hashed (not stored) for one-signature-per-person dedup.
 *
 *   GET ?action=stats (or just GET) -> { count, recent: [...] }
 *
 * Storage:
 *   Lives OUTSIDE the document root, in ../humanityreport-data/.
 *   Created automatically on first write. Never served over HTTP because the path is
 *   above the doc root the web server is configured for.
 *
 * Anti-abuse:
 *   - Same-origin check on POST (Origin / Referer must match HTTP_HOST)
 *   - IP rate limit: 5 signatures/hour
 *   - Email-hash dedup
 *   - Honeypot field
 *   - Strict input sanitization + length / shape validation
 */

declare(strict_types=1);

// Never leak warnings / deprecations into the JSON response body — they still
// go to the configured error log via error_log() calls below.
ini_set('display_errors', '0');
error_reporting(E_ALL);

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: same-origin');

const DATA_DIR_NAME   = 'humanityreport-data';
const MAX_NAME_LEN    = 80;
const MAX_EMAIL_LEN   = 120;
const MAX_COUNTRY_LEN = 60;
const MAX_COMMENT_LEN = 280;
const MAX_PER_IP_HOUR = 5;
const DEDUP_WINDOW    = 86400;          // 24h: same IP or same browser cookie cannot resign
const COOKIE_NAME     = 'hr_signed';
const COOKIE_LIFETIME = 30 * 86400;     // remember beyond the 24h window
const RECENT_LIMIT    = 24;          // default page size
const MAX_PER_PAGE    = 60;          // hard cap to prevent oversized payloads

function data_dir(): string {
    $dir = dirname(__DIR__) . DIRECTORY_SEPARATOR . DATA_DIR_NAME;
    if (!is_dir($dir)) {
        @mkdir($dir, 0750, true);
    }
    return $dir;
}

function store_path(): string { return data_dir() . '/signatures.json'; }
function rate_path():  string { return data_dir() . '/signrate.json'; }

function respond(int $code, array $payload): void {
    http_response_code($code);
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    // Flush response to client when running under PHP-FPM so any deferred
    // shutdown handlers (e.g. sending the thank-you email) don't delay the user.
    if (function_exists('fastcgi_finish_request')) {
        @fastcgi_finish_request();
    }
    exit;
}

function load_config(): array {
    $path = data_dir() . '/config.php';
    if (!is_readable($path)) return [];
    $cfg = @include $path;
    return is_array($cfg) ? $cfg : [];
}

function send_thankyou_email(string $email, string $rawName): void {
    $cfg   = load_config();
    $token = (string) ($cfg['postmark_token'] ?? '');
    $from  = (string) ($cfg['postmark_from']  ?? '');
    if ($token === '' || $from === '') {
        error_log('thank-you: postmark config missing; skipping send');
        return;
    }
    $fromName = (string) ($cfg['postmark_from_name'] ?? 'HumanityReport.com');
    $stream   = (string) ($cfg['postmark_stream']    ?? 'outbound');

    $firstName = trim((string) (explode(' ', trim($rawName))[0] ?? ''));
    if ($firstName === '') $firstName = 'friend';
    $safeName = htmlspecialchars($firstName, ENT_QUOTES | ENT_HTML5, 'UTF-8');

    $shareUrl = 'https://humanityreport.com/manifesto.html';

    $textBody = "Thank you for signing.\n\n"
              . "Hi {$firstName},\n\n"
              . "A manifesto with one signature is a diary entry. The most useful thing you can do next is forward it to someone whose silence is louder than yours.\n\n"
              . "Share it: {$shareUrl}\n\n"
              . "Read it again. Argue with the points. Pick one to live more loudly than you did yesterday.\n\n"
              . "— HumanityReport.com\n\n"
              . "---\n"
              . "You are receiving this because you just signed the Humanity Manifesto at humanityreport.com. "
              . "Your email is stored only as a one-way hash; we will not mail you again.";

    $htmlBody = '<!DOCTYPE html><html><head><meta charset="UTF-8"><title>Thank you for signing</title></head>'
              . '<body style="margin:0;padding:0;background:#f4f1ea;font-family:Georgia,serif;color:#2a2520;">'
              . '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="background:#f4f1ea;">'
              .   '<tr><td align="center" style="padding:40px 20px;">'
              .     '<table role="presentation" width="540" cellpadding="0" cellspacing="0" border="0" style="background:#ffffff;border:1px solid #e5dcc7;border-top:3px solid #c9a868;max-width:540px;width:100%;">'
              .       '<tr><td style="padding:36px 38px 30px;">'
              .         '<p style="margin:0 0 6px;font-size:11px;letter-spacing:3px;text-transform:uppercase;color:#c9a868;">HumanityReport.com</p>'
              .         '<h1 style="margin:0 0 22px;font-size:22px;font-weight:400;color:#2a2520;line-height:1.3;">Thank you for signing.</h1>'
              .         '<p style="margin:0 0 16px;font-size:15px;line-height:1.7;">Hi ' . $safeName . ',</p>'
              .         '<p style="margin:0 0 16px;font-size:15px;line-height:1.7;">A manifesto with one signature is a diary entry. The most useful thing you can do next is forward it to someone whose silence is louder than yours.</p>'
              .         '<p style="margin:22px 0 26px;text-align:center;">'
              .           '<a href="' . $shareUrl . '" style="display:inline-block;background:#c9a868;color:#1a1410;text-decoration:none;padding:13px 30px;font-size:12px;letter-spacing:2px;text-transform:uppercase;font-family:Georgia,serif;">Share the Manifesto</a>'
              .         '</p>'
              .         '<p style="margin:0 0 16px;font-size:15px;line-height:1.7;">Read it again. Argue with the points. Pick one to live more loudly than you did yesterday.</p>'
              .         '<p style="margin:24px 0 0;font-size:13px;color:#7a6f5e;font-style:italic;">— HumanityReport.com</p>'
              .       '</td></tr>'
              .       '<tr><td style="padding:18px 38px;border-top:1px solid #efe7d3;font-size:11px;color:#9a8f7c;line-height:1.55;">'
              .         'You are receiving this because you just signed the Humanity Manifesto at humanityreport.com. '
              .         'Your email is stored only as a one-way hash; we will not mail you again.'
              .       '</td></tr>'
              .     '</table>'
              .   '</td></tr>'
              . '</table></body></html>';

    $fromHeader = $fromName !== ''
        ? '"' . str_replace('"', '', $fromName) . '" <' . $from . '>'
        : $from;

    $payload = [
        'From'          => $fromHeader,
        'To'            => $email,
        'Subject'       => 'Thank you for signing the Humanity Manifesto',
        'HtmlBody'      => $htmlBody,
        'TextBody'      => $textBody,
        'MessageStream' => $stream,
    ];

    $ch = curl_init('https://api.postmarkapp.com/email');
    curl_setopt_array($ch, [
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => json_encode($payload, JSON_UNESCAPED_UNICODE),
        CURLOPT_HTTPHEADER     => [
            'Accept: application/json',
            'Content-Type: application/json',
            'X-Postmark-Server-Token: ' . $token,
        ],
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CONNECTTIMEOUT => 4,
        CURLOPT_TIMEOUT        => 8,
    ]);
    $resp = curl_exec($ch);
    $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    if (curl_errno($ch)) {
        error_log('postmark cURL: ' . curl_error($ch));
    } elseif ($code < 200 || $code >= 300) {
        // Log status + truncated body. Don't log the recipient address.
        error_log('postmark HTTP ' . $code . ': ' . substr((string) $resp, 0, 400));
    }
    // curl_close() is a no-op since PHP 8.0; the handle is freed by GC.
}

function defer_thankyou_email(string $email, string $name): void {
    register_shutdown_function(function () use ($email, $name) {
        try {
            send_thankyou_email($email, $name);
        } catch (\Throwable $t) {
            error_log('thank-you exception: ' . $t->getMessage());
        }
    });
}

function load_json(string $path, array $default): array {
    if (!file_exists($path)) return $default;
    $raw = @file_get_contents($path);
    if ($raw === false || $raw === '') return $default;
    $data = json_decode($raw, true);
    return is_array($data) ? $data : $default;
}

function save_json_atomic(string $path, array $data): bool {
    $tmp = $path . '.tmp';
    $json = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    if ($json === false) return false;
    if (file_put_contents($tmp, $json, LOCK_EX) === false) return false;
    return rename($tmp, $path);
}

function client_ip(): string {
    $ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
    if (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
        $forwarded = explode(',', (string) $_SERVER['HTTP_X_FORWARDED_FOR']);
        $candidate = trim($forwarded[0]);
        if (filter_var($candidate, FILTER_VALIDATE_IP)) $ip = $candidate;
    }
    return $ip;
}

/**
 * Strict same-origin check. Browsers set Origin / Referer for us — an attacker on
 * another site cannot forge them in a victim's browser. Non-browser clients (curl,
 * scripts) must send a matching Origin header to pass; that's intentional.
 */
function same_origin_check(): bool {
    $expectedHost = strtolower((string) ($_SERVER['HTTP_HOST'] ?? ''));
    if ($expectedHost === '') return false;

    $candidates = [];
    if (!empty($_SERVER['HTTP_ORIGIN']))  $candidates[] = (string) $_SERVER['HTTP_ORIGIN'];
    if (!empty($_SERVER['HTTP_REFERER'])) $candidates[] = (string) $_SERVER['HTTP_REFERER'];

    foreach ($candidates as $candidate) {
        $parts = parse_url($candidate);
        if (empty($parts['host'])) continue;
        $host = strtolower($parts['host']);
        if (isset($parts['port'])) $host .= ':' . $parts['port'];
        if ($host === $expectedHost) return true;
    }
    return false;
}

/** Strip null bytes and control chars, collapse whitespace, trim, hard-cap length. */
function sanitize_text(string $s, int $maxLen): string {
    $s = str_replace("\0", '', $s);
    $s = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', $s) ?? '';
    $s = preg_replace('/\s+/u', ' ', $s) ?? '';
    $s = trim($s);
    if (mb_strlen($s) > $maxLen) {
        $s = mb_substr($s, 0, $maxLen);
    }
    return $s;
}

function contains_html_or_link(string $s): bool {
    if ($s === '') return false;
    if (strpos($s, '<') !== false || strpos($s, '>') !== false) return true;
    if (preg_match('/https?:\/\/|www\./i', $s)) return true;
    return false;
}

function read_input(): array {
    $contentType = $_SERVER['CONTENT_TYPE'] ?? '';
    if (stripos($contentType, 'application/json') !== false) {
        $raw = file_get_contents('php://input');
        $data = json_decode((string) $raw, true);
        return is_array($data) ? $data : [];
    }
    return $_POST;
}

/**
 * Read the existing browser cookie (if any), returning a normalized hex token
 * or '' if none / malformed. Cookie values that don't look like hex of an
 * expected length are ignored so an attacker cannot supply arbitrary content.
 */
function read_cookie_token(): string {
    $raw = (string) ($_COOKIE[COOKIE_NAME] ?? '');
    if ($raw === '') return '';
    if (!preg_match('/^[a-f0-9]{32,128}$/', $raw)) return '';
    return $raw;
}

/** Issue a fresh, cryptographically-random cookie token. */
function mint_cookie_token(): string {
    return bin2hex(random_bytes(32));
}

/** Truncated SHA-256 — same shape as ip_hash for consistency. */
function short_hash(string $s): string {
    return substr(hash('sha256', $s), 0, 16);
}

/** Did either this IP or this browser cookie sign within the dedup window? */
function recently_signed(array $signatures, string $ipHash, ?string $cookieHash): bool {
    $now = time();
    foreach ($signatures as $s) {
        $ts = (int) ($s['ts'] ?? 0);
        if ($ts === 0 || ($now - $ts) > DEDUP_WINDOW) continue;
        if (($s['ip_hash'] ?? null) === $ipHash) return true;
        if ($cookieHash !== null && ($s['cookie_hash'] ?? null) === $cookieHash) return true;
    }
    return false;
}

/** Set Set-Cookie header with secure defaults (called before response body output). */
function set_signed_cookie(string $token): void {
    $isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
            || (strcasecmp((string) ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? ''), 'https') === 0);
    setcookie(COOKIE_NAME, $token, [
        'expires'  => time() + COOKIE_LIFETIME,
        'path'     => '/',
        'secure'   => $isHttps,
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
}

function rate_limit_check(string $ip): bool {
    $now    = time();
    $window = 3600;
    $rates  = load_json(rate_path(), []);
    foreach ($rates as $key => $entries) {
        if (!is_array($entries)) { unset($rates[$key]); continue; }
        $rates[$key] = array_values(array_filter($entries, fn($t) => is_int($t) && ($now - $t) < $window));
        if (empty($rates[$key])) unset($rates[$key]);
    }
    $own = $rates[$ip] ?? [];
    if (count($own) >= MAX_PER_IP_HOUR) {
        save_json_atomic(rate_path(), $rates);
        return false;
    }
    $own[] = $now;
    $rates[$ip] = $own;
    save_json_atomic(rate_path(), $rates);
    return true;
}

/** Strip a single stored signature down to its public-facing fields. */
function public_signature(array $s): array {
    return [
        'name'    => $s['display_name'] ?? '',
        'country' => $s['country'] ?? '',
        'comment' => $s['comment'] ?? '',
        'ts'      => $s['ts'] ?? null,
    ];
}

/**
 * Server-side pagination over the signatures array. Newest first.
 * Returns the page metadata + the page slice (mapped to public fields).
 */
function paginate_signatures(array $signatures, int $total, int $page, int $perPage): array {
    if ($perPage < 1) $perPage = RECENT_LIMIT;
    if ($perPage > MAX_PER_PAGE) $perPage = MAX_PER_PAGE;

    $totalPages = $total > 0 ? (int) ceil($total / $perPage) : 1;
    if ($page < 1) $page = 1;
    if ($page > $totalPages) $page = $totalPages;

    // Newest first. array_reverse keeps the original array intact and is fine
    // at this scale; if the list ever grows past tens of thousands we'd switch
    // to slicing-from-the-end without a full reverse.
    $reversed = array_reverse($signatures);
    $offset   = ($page - 1) * $perPage;
    $slice    = array_slice($reversed, $offset, $perPage);

    return [
        'page'        => $page,
        'per_page'    => $perPage,
        'total_pages' => $totalPages,
        'signatures'  => array_map('public_signature', $slice),
    ];
}

function display_name(string $name): string {
    $name = trim($name);
    if ($name === '') return 'Anonymous';
    $parts = preg_split('/\s+/', $name) ?: [];
    if (count($parts) === 1) return $parts[0];
    $first = array_shift($parts);
    $lastInitial = mb_strtoupper(mb_substr((string) $parts[count($parts) - 1], 0, 1));
    return $first . ' ' . $lastInitial . '.';
}

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

if ($method === 'GET') {
    $store      = load_json(store_path(), ['count' => 0, 'signatures' => []]);
    $signatures = is_array($store['signatures'] ?? null) ? $store['signatures'] : [];
    $total      = (int) ($store['count'] ?? count($signatures));
    $page       = (int) ($_GET['page']     ?? 1);
    $perPage    = (int) ($_GET['per_page'] ?? RECENT_LIMIT);
    $pagedata   = paginate_signatures($signatures, $total, $page, $perPage);

    respond(200, array_merge(['ok' => true, 'count' => $total], $pagedata));
}

if ($method !== 'POST') {
    respond(405, ['ok' => false, 'error' => 'method_not_allowed']);
}

if (!same_origin_check()) {
    respond(403, ['ok' => false, 'error' => 'forbidden_origin']);
}

$input = read_input();

// Honeypot — bots fill every field. Real users never see it (CSS-hidden + tabindex=-1).
if (!empty($input['website'])) {
    respond(200, ['ok' => true, 'count' => null, 'note' => 'received']);
}

$name    = sanitize_text((string) ($input['name']    ?? ''), MAX_NAME_LEN);
$email   = sanitize_text((string) ($input['email']   ?? ''), MAX_EMAIL_LEN);
$country = sanitize_text((string) ($input['country'] ?? ''), MAX_COUNTRY_LEN);
$comment = sanitize_text((string) ($input['comment'] ?? ''), MAX_COMMENT_LEN);

if ($name === '') {
    respond(400, ['ok' => false, 'error' => 'name_required']);
}
if (contains_html_or_link($name) || contains_html_or_link($comment) || contains_html_or_link($country)) {
    respond(400, ['ok' => false, 'error' => 'links_not_allowed']);
}
if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    respond(400, ['ok' => false, 'error' => 'invalid_email']);
}

$ip = client_ip();
if (!rate_limit_check($ip)) {
    respond(429, ['ok' => false, 'error' => 'rate_limited']);
}

$store      = load_json(store_path(), ['count' => 0, 'signatures' => []]);
$signatures = is_array($store['signatures'] ?? null) ? $store['signatures'] : [];

$ipHash       = short_hash($ip);
$cookieToken  = read_cookie_token();
$cookieHash   = $cookieToken !== '' ? short_hash($cookieToken) : null;

// 24h dedup by IP OR by browser cookie. This is a separate axis from the
// forever email-hash dedup below; same-network and same-device returns are
// caught even if the user tries a different email address.
if (recently_signed($signatures, $ipHash, $cookieHash)) {
    respond(200, [
        'ok'        => true,
        'duplicate' => true,
        'reason'    => 'recent_device',
        'count'     => (int) ($store['count'] ?? count($signatures)),
    ]);
}

$emailHash = $email !== '' ? hash('sha256', mb_strtolower($email)) : null;

if ($emailHash !== null) {
    foreach ($signatures as $s) {
        if (($s['email_hash'] ?? null) === $emailHash) {
            respond(200, [
                'ok'        => true,
                'duplicate' => true,
                'reason'    => 'email_already_signed',
                'count'     => (int) ($store['count'] ?? count($signatures)),
            ]);
        }
    }
}

// Mint a fresh cookie token if the visitor doesn't have one yet.
if ($cookieToken === '') {
    $cookieToken = mint_cookie_token();
    $cookieHash  = short_hash($cookieToken);
}

$signatures[] = [
    'display_name' => display_name($name),
    'email_hash'   => $emailHash,
    'country'      => $country,
    'comment'      => $comment,
    'ts'           => time(),
    'ip_hash'      => $ipHash,
    'cookie_hash'  => $cookieHash,
];

$store['signatures'] = $signatures;
$store['count']      = count($signatures);

if (!save_json_atomic(store_path(), $store)) {
    respond(500, ['ok' => false, 'error' => 'storage_failure']);
}

// Persist the cookie on the signer's browser so we can recognise them next time
// (also if they later clear cookies, the IP check still covers them for 24h).
set_signed_cookie($cookieToken);

// New signature with a real email address — send a one-time thank-you.
// Skipped automatically on duplicates (returned earlier) and on no-email signs.
if ($email !== '') {
    defer_thankyou_email($email, $name);
}

$pagedata = paginate_signatures($signatures, (int) $store['count'], 1, RECENT_LIMIT);
respond(200, array_merge(['ok' => true, 'count' => (int) $store['count']], $pagedata));
