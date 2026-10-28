<?php
/**
 * General helpers: output escaping, URLs, responses, flash messages and formatting.
 */

declare(strict_types=1);

// ---------------------------------------------------------------------
//  Output & URLs
// ---------------------------------------------------------------------

/** Escape for HTML output. Use for every piece of dynamic data. */
function e(mixed $value): string
{
    return htmlspecialchars((string) ($value ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function base_path(): string
{
    static $base = null;
    if ($base !== null) {
        return $base;
    }
    $configured = trim((string) config('app.url'));
    if ($configured !== '') {
        return $base = rtrim($configured, '/');
    }
    $docRoot = realpath((string) ($_SERVER['DOCUMENT_ROOT'] ?? ''));
    $appRoot = realpath(APP_ROOT);
    if ($docRoot && $appRoot && stripos($appRoot, $docRoot) === 0) {
        $relative = str_replace('\\', '/', substr($appRoot, strlen($docRoot)));
        return $base = rtrim($relative, '/');
    }
    return $base = '';
}

function url(string $path = ''): string
{
    return base_path() . '/' . ltrim($path, '/');
}

/** Asset URL with a cache-busting version based on the file's modification time. */
function asset(string $path): string
{
    $file = APP_ROOT . '/assets/' . ltrim($path, '/');
    $version = is_file($file) ? (string) filemtime($file) : '1';
    return url('assets/' . ltrim($path, '/')) . '?v=' . $version;
}

function upload_url(?string $path): ?string
{
    return $path ? url(config('uploads.dir') . '/' . ltrim($path, '/')) : null;
}

/** Absolute URL of the current request path (used for canonical / redirects). */
function current_path(): string
{
    return strtok((string) ($_SERVER['REQUEST_URI'] ?? '/'), '?') ?: '/';
}

/** Relative path of the current script inside the app, e.g. "provider/bookings.php". */
function current_script(): string
{
    $script = str_replace('\\', '/', (string) ($_SERVER['SCRIPT_NAME'] ?? ''));
    $base = base_path();
    if ($base !== '' && str_starts_with($script, $base)) {
        $script = substr($script, strlen($base));
    }
    return ltrim($script, '/');
}

/** Build a query string from the current GET params with overrides (null removes a key). */
function query_with(array $overrides = []): string
{
    $params = array_merge($_GET, $overrides);
    $params = array_filter($params, fn ($v) => $v !== null && $v !== '');
    return $params ? '?' . http_build_query($params) : '';
}

// ---------------------------------------------------------------------
//  Requests & responses
// ---------------------------------------------------------------------

function is_post(): bool
{
    return ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST';
}

function is_ajax(): bool
{
    return strtolower($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '') === 'xmlhttprequest'
        || str_contains(strtolower($_SERVER['HTTP_ACCEPT'] ?? ''), 'application/json');
}

/** Trimmed string input from POST (then GET). */
function input(string $key, string $default = ''): string
{
    $value = $_POST[$key] ?? $_GET[$key] ?? $default;
    return is_string($value) ? trim($value) : $default;
}

function input_int(string $key, int $default = 0): int
{
    $value = $_POST[$key] ?? $_GET[$key] ?? null;
    return is_numeric($value) ? (int) $value : $default;
}

/** Read a JSON request body (for fetch() calls that send JSON). */
function json_input(): array
{
    static $data = null;
    if ($data === null) {
        $decoded = json_decode((string) file_get_contents('php://input'), true);
        $data = is_array($decoded) ? $decoded : [];
    }
    return $data;
}

function redirect(string $to): never
{
    if (!preg_match('#^https?://#', $to) && !str_starts_with($to, '/')) {
        $to = url($to);
    }
    header('Location: ' . $to);
    exit;
}

/** Same-site path to return to after a form post, never an external URL. */
function safe_return_path(?string $candidate, string $fallback): string
{
    $candidate = (string) $candidate;
    if ($candidate !== '' && str_starts_with($candidate, '/') && !str_starts_with($candidate, '//')
        && !preg_match('/[\r\n]/', $candidate)) {
        return $candidate;
    }
    $referer = (string) ($_SERVER['HTTP_REFERER'] ?? '');
    $host = (string) ($_SERVER['HTTP_HOST'] ?? '');
    if ($referer !== '' && parse_url($referer, PHP_URL_HOST) === parse_url('http://' . $host, PHP_URL_HOST)) {
        $path = parse_url($referer, PHP_URL_PATH) ?: '/';
        $query = parse_url($referer, PHP_URL_QUERY);
        return $path . ($query ? '?' . $query : '');
    }
    return url($fallback);
}

function json_response(array $data, int $status = 200): never
{
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store');
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

/**
 * Unified API response. AJAX callers get JSON; classic form posts get a flash
 * message and a redirect, so every endpoint also works without JavaScript.
 */
function api_respond(bool $ok, string $message = '', array $data = [], ?int $status = null, ?string $redirect = null): never
{
    if (is_ajax()) {
        $payload = ['ok' => $ok, 'message' => $message] + $data;
        if ($redirect !== null) {
            $payload['redirect'] = str_starts_with($redirect, '/') ? $redirect : url($redirect);
        }
        json_response($payload, $status ?? ($ok ? 200 : 422));
    }
    if ($message !== '') {
        flash($ok ? 'success' : 'error', $message);
    }
    redirect($redirect ?? safe_return_path(input('_back'), 'index.php'));
}

function require_method(string $method): void
{
    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== $method) {
        header('Allow: ' . $method);
        api_respond(false, 'Method not allowed.', [], 405);
    }
}

function send_security_headers(): void
{
    if (headers_sent()) {
        return;
    }
    header('X-Frame-Options: DENY');
    header('X-Content-Type-Options: nosniff');
    header('Referrer-Policy: strict-origin-when-cross-origin');
    header('Permissions-Policy: camera=(), microphone=(), geolocation=()');
    header("Content-Security-Policy: default-src 'self'; script-src 'self'; "
        . "style-src 'self' 'unsafe-inline' https://fonts.googleapis.com; font-src 'self' https://fonts.gstatic.com; "
        . "img-src 'self' data:; connect-src 'self'; frame-ancestors 'none'; base-uri 'self'; form-action 'self'");
}

function client_ip(): string
{
    $ip = (string) ($_SERVER['REMOTE_ADDR'] ?? '0.0.0.0');
    return filter_var($ip, FILTER_VALIDATE_IP) ? $ip : '0.0.0.0';
}

// ---------------------------------------------------------------------
//  Flash messages & form state
// ---------------------------------------------------------------------

function flash(string $type, string $message): void
{
    $_SESSION['_flash'][] = ['type' => $type, 'message' => $message];
}

function get_flashes(): array
{
    $messages = $_SESSION['_flash'] ?? [];
    unset($_SESSION['_flash']);
    return $messages;
}

/** Remember submitted values and errors for the next request (Post/Redirect/Get). */
function with_form_state(array $old, array $errors): void
{
    unset($old['password'], $old['password_confirm'], $old['current_password'], $old['_csrf']);
    $_SESSION['_old'] = $old;
    $_SESSION['_errors'] = $errors;
}

function old(string $key, mixed $default = ''): string
{
    static $old = null;
    if ($old === null) {
        $old = $_SESSION['_old'] ?? [];
        unset($_SESSION['_old']);
    }
    $value = $old[$key] ?? $default;
    return is_scalar($value) ? (string) $value : '';
}

function form_errors(): array
{
    static $errors = null;
    if ($errors === null) {
        $errors = $_SESSION['_errors'] ?? [];
        unset($_SESSION['_errors']);
    }
    return $errors;
}

function field_error(string $field): string
{
    $errors = form_errors();
    return isset($errors[$field]) ? '<p class="field-error" role="alert">' . e($errors[$field]) . '</p>' : '';
}

function error_class(string $field): string
{
    return isset(form_errors()[$field]) ? ' is-invalid' : '';
}

function selected(mixed $a, mixed $b): string
{
    return (string) $a === (string) $b ? ' selected' : '';
}

function checked(bool $condition): string
{
    return $condition ? ' checked' : '';
}

// ---------------------------------------------------------------------
//  Formatting
// ---------------------------------------------------------------------

function money(float|int|string|null $amount): string
{
    $amount = (float) $amount;
    $decimals = fmod($amount, 1.0) === 0.0 ? 0 : 2;
    return config('app.currency') . number_format($amount, $decimals);
}

function price_label(float|string $price, string $type): string
{
    return match ($type) {
        'hourly' => money($price) . '<small>/hr</small>',
        'from'   => '<small>from</small> ' . money($price),
        default  => money($price),
    };
}

function duration_label(int $minutes): string
{
    $h = intdiv($minutes, 60);
    $m = $minutes % 60;
    if ($h && $m) {
        return "{$h} h {$m} min";
    }
    return $h ? "{$h} h" : "{$m} min";
}

function format_date(?string $date, string $format = 'D, M j, Y'): string
{
    return $date ? date($format, strtotime($date)) : '';
}

function format_time(?string $time): string
{
    return $time ? substr($time, 0, 5) : '';
}

function relative_day(string $date): string
{
    $diff = (int) round((strtotime($date) - strtotime(date('Y-m-d'))) / 86400);
    return match (true) {
        $diff === 0  => 'Today',
        $diff === 1  => 'Tomorrow',
        $diff === -1 => 'Yesterday',
        default      => format_date($date, 'D, M j'),
    };
}

function time_ago(string $datetime): string
{
    $seconds = time() - strtotime($datetime);
    return match (true) {
        $seconds < 60      => 'just now',
        $seconds < 3600    => floor($seconds / 60) . ' min ago',
        $seconds < 86400   => floor($seconds / 3600) . ' h ago',
        $seconds < 604800  => floor($seconds / 86400) . ' d ago',
        default            => format_date($datetime, 'M j, Y'),
    };
}

function slugify(string $text): string
{
    $text = strtolower(trim(preg_replace('/[^A-Za-z0-9]+/', '-', iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $text) ?: $text), '-'));
    return $text !== '' ? $text : 'item';
}

function initials(string $name): string
{
    $parts = preg_split('/\s+/', trim($name)) ?: [];
    $letters = '';
    foreach (array_slice($parts, 0, 2) as $part) {
        $letters .= mb_strtoupper(mb_substr($part, 0, 1));
    }
    return $letters ?: '?';
}

function excerpt(string $text, int $length = 140): string
{
    $text = trim(preg_replace('/\s+/', ' ', $text));
    return mb_strlen($text) > $length ? rtrim(mb_substr($text, 0, $length - 1)) . '…' : $text;
}

function plural(int $count, string $singular, ?string $plural = null): string
{
    return $count . ' ' . ($count === 1 ? $singular : ($plural ?? $singular . 's'));
}

// ---------------------------------------------------------------------
//  UI fragments
// ---------------------------------------------------------------------

function avatar_html(string $name, ?string $avatar = null, string $size = 'md', string $extra = ''): string
{
    $class = 'avatar avatar-' . $size . ($extra ? ' ' . $extra : '');
    if ($avatar) {
        return '<img class="' . e($class) . '" src="' . e(upload_url($avatar)) . '" alt="' . e($name) . '" loading="lazy" decoding="async">';
    }
    $tone = (crc32($name) % 6) + 1;
    return '<span class="' . e($class) . ' avatar-tone-' . $tone . '" aria-hidden="true">' . e(initials($name)) . '</span>';
}

function stars_html(float $rating, string $size = ''): string
{
    $rounded = round($rating * 2) / 2;
    $html = '<span class="stars' . ($size ? ' stars-' . $size : '') . '" aria-label="' . e(number_format($rating, 1)) . ' out of 5 stars">';
    for ($i = 1; $i <= 5; $i++) {
        $state = $rounded >= $i ? 'full' : ($rounded >= $i - 0.5 ? 'half' : 'empty');
        $html .= '<span class="star star-' . $state . '">' . icon('star', 16) . '</span>';
    }
    return $html . '</span>';
}

function booking_status_label(string $status): string
{
    return [
        'pending'     => 'Pending',
        'confirmed'   => 'Confirmed',
        'rejected'    => 'Rejected',
        'in_progress' => 'In progress',
        'completed'   => 'Completed',
        'cancelled'   => 'Cancelled',
    ][$status] ?? ucfirst($status);
}

function status_badge(string $status): string
{
    $tone = [
        'pending' => 'warning', 'confirmed' => 'info', 'in_progress' => 'brand', 'completed' => 'success',
        'rejected' => 'danger', 'cancelled' => 'neutral', 'active' => 'success', 'suspended' => 'danger',
        'approved' => 'success', 'published' => 'success', 'hidden' => 'neutral', 'open' => 'warning',
        'resolved' => 'success', 'dismissed' => 'neutral', 'paid' => 'success', 'unpaid' => 'warning', 'refunded' => 'neutral',
    ][$status] ?? 'neutral';
    $label = booking_status_label($status);
    return '<span class="badge badge-' . $tone . '"><span class="badge-dot"></span>' . e($label) . '</span>';
}

function verified_badge(): string
{
    return '<span class="verified" title="Verified professional">' . icon('badge-check', 16) . '<span>Verified</span></span>';
}

function empty_state(string $title, string $text = '', string $icon = 'inbox', string $actionHtml = ''): string
{
    return '<div class="empty-state"><div class="empty-state-icon">' . icon($icon, 28) . '</div>'
        . '<h3>' . e($title) . '</h3>' . ($text ? '<p>' . e($text) . '</p>' : '') . $actionHtml . '</div>';
}

function paginate(int $total, int $perPage, int $page): array
{
    $pages = max(1, (int) ceil($total / $perPage));
    $page = min(max(1, $page), $pages);
    return ['total' => $total, 'per_page' => $perPage, 'page' => $page, 'pages' => $pages, 'offset' => ($page - 1) * $perPage];
}

function pagination_html(array $p, string $param = 'page'): string
{
    if ($p['pages'] <= 1) {
        return '';
    }
    $html = '<nav class="pagination" aria-label="Pagination">';
    $html .= $p['page'] > 1
        ? '<a class="page-link" href="' . e(query_with([$param => $p['page'] - 1])) . '" data-page="' . ($p['page'] - 1) . '" aria-label="Previous page">' . icon('chevron-left', 16) . '</a>'
        : '<span class="page-link is-disabled">' . icon('chevron-left', 16) . '</span>';
    for ($i = 1; $i <= $p['pages']; $i++) {
        if ($i !== 1 && $i !== $p['pages'] && abs($i - $p['page']) > 1) {
            if ($i === 2 || $i === $p['pages'] - 1) {
                $html .= '<span class="page-gap">…</span>';
            }
            continue;
        }
        $html .= $i === $p['page']
            ? '<span class="page-link is-active" aria-current="page">' . $i . '</span>'
            : '<a class="page-link" href="' . e(query_with([$param => $i])) . '" data-page="' . $i . '">' . $i . '</a>';
    }
    $html .= $p['page'] < $p['pages']
        ? '<a class="page-link" href="' . e(query_with([$param => $p['page'] + 1])) . '" data-page="' . ($p['page'] + 1) . '" aria-label="Next page">' . icon('chevron-right', 16) . '</a>'
        : '<span class="page-link is-disabled">' . icon('chevron-right', 16) . '</span>';
    return $html . '</nav>';
}

/** Render a template from includes/partials with the given variables in scope. */
function partial(string $name, array $vars = []): void
{
    extract($vars, EXTR_SKIP);
    require APP_ROOT . '/includes/partials/' . $name . '.php';
}

function partial_html(string $name, array $vars = []): string
{
    ob_start();
    partial($name, $vars);
    return (string) ob_get_clean();
}

// ---------------------------------------------------------------------
//  Auditing
// ---------------------------------------------------------------------

function log_activity(?int $userId, string $action, string $description, ?string $entityType = null, ?int $entityId = null): void
{
    try {
        db_query(
            'INSERT INTO activity_logs (user_id, action, entity_type, entity_id, description, ip_address) VALUES (?, ?, ?, ?, ?, ?)',
            [$userId, $action, $entityType, $entityId, mb_substr($description, 0, 255), client_ip()]
        );
    } catch (Throwable $e) {
        error_log('[LemboFix] activity log failed: ' . $e->getMessage());
    }
}
