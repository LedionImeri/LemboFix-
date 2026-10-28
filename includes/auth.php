<?php
/**
 * Sessions, authentication and role-based access control.
 */

declare(strict_types=1);

const ROLE_CUSTOMER = 'customer';
const ROLE_PROVIDER = 'provider';
const ROLE_ADMIN    = 'admin';

function start_secure_session(): void
{
    if (session_status() === PHP_SESSION_ACTIVE) {
        return;
    }
    $secure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');
    ini_set('session.use_strict_mode', '1');
    ini_set('session.use_only_cookies', '1');
    session_name('LEMBOFIX_SID');
    session_set_cookie_params([
        'lifetime' => 0,
        'path'     => base_path() . '/',
        'secure'   => $secure,
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_start();

    // Expire idle sessions
    $idleLimit = (int) config('security.session_idle_minutes') * 60;
    $last = $_SESSION['_last_activity'] ?? null;
    if ($last && (time() - $last) > $idleLimit && !empty($_SESSION['user_id'])) {
        $_SESSION = [];
        session_regenerate_id(true);
        flash('info', 'You were signed out after a period of inactivity.');
    }
    $_SESSION['_last_activity'] = time();
}

/** The logged-in user row (plus provider profile for providers), cached per request. */
function current_user(bool $refresh = false): ?array
{
    static $user = false;
    if ($refresh) {
        $user = false;
    }
    if ($user !== false) {
        return $user;
    }
    $id = (int) ($_SESSION['user_id'] ?? 0);
    if ($id <= 0) {
        return $user = null;
    }
    $row = db_one('SELECT id, role, first_name, last_name, email, phone, city, avatar, status, created_at FROM users WHERE id = ?', [$id]);
    if (!$row) {
        unset($_SESSION['user_id']);
        return $user = null;
    }
    $row['full_name'] = $row['first_name'] . ' ' . $row['last_name'];
    if ($row['role'] === ROLE_PROVIDER) {
        $row['provider'] = db_one('SELECT * FROM provider_profiles WHERE user_id = ?', [$id]);
    }
    return $user = $row;
}

function is_logged_in(): bool
{
    return current_user() !== null;
}

function user_role(): ?string
{
    return current_user()['role'] ?? null;
}

function user_id(): int
{
    return (int) (current_user()['id'] ?? 0);
}

function has_role(string ...$roles): bool
{
    return in_array(user_role(), $roles, true);
}

/** Provider profile of the logged-in provider. */
function current_provider(): ?array
{
    return current_user()['provider'] ?? null;
}

function dashboard_url(?string $role = null): string
{
    return match ($role ?? user_role()) {
        ROLE_ADMIN    => 'admin/dashboard.php',
        ROLE_PROVIDER => 'provider/dashboard.php',
        ROLE_CUSTOMER => 'customer/dashboard.php',
        default       => 'login.php',
    };
}

/**
 * Guard for pages and endpoints. Guests are sent to login (or get a 401 JSON),
 * logged-in users without the right role get a 403.
 */
function require_login(string ...$roles): array
{
    $user = current_user();
    if (!$user) {
        if (is_ajax() || str_starts_with(current_script(), 'api/')) {
            json_response(['ok' => false, 'message' => 'Please sign in to continue.', 'login' => url('login.php')], 401);
        }
        $_SESSION['_intended'] = $_SERVER['REQUEST_URI'] ?? null;
        flash('info', 'Please sign in to continue.');
        redirect('login.php');
    }
    if ($roles && !in_array($user['role'], $roles, true)) {
        if (is_ajax() || str_starts_with(current_script(), 'api/')) {
            json_response(['ok' => false, 'message' => 'You do not have permission to do that.'], 403);
        }
        render_error_page(403);
    }
    return $user;
}

function require_guest(): void
{
    if (is_logged_in()) {
        redirect(dashboard_url());
    }
}

function login_user(array $user): void
{
    session_regenerate_id(true);
    $_SESSION['user_id'] = (int) $user['id'];
    unset($_SESSION['lembobot_history']);
    db_query('UPDATE users SET last_login_at = NOW() WHERE id = ?', [$user['id']]);
    current_user(true);
}

function logout_user(): void
{
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $p = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000, $p['path'], $p['domain'], $p['secure'], $p['httponly']);
    }
    session_destroy();
}

/**
 * Verify credentials with throttling per email+IP.
 * Returns [user|null, error message].
 */
function attempt_login(string $email, string $password): array
{
    $identifier = strtolower($email) . '|' . client_ip();
    $window = (int) config('security.login_window_minutes') * 60;
    $maxAttempts = (int) config('security.login_max_attempts');

    if (rate_limit_count('login', $identifier, $window) >= $maxAttempts) {
        return [null, 'Too many failed attempts. Please wait ' . config('security.login_window_minutes') . ' minutes and try again.'];
    }

    $user = db_one('SELECT * FROM users WHERE email = ?', [$email]);

    // Verify against a dummy hash when the user does not exist to keep timing similar
    $hash = $user['password_hash'] ?? '$2y$12$CfdSiUht5pgAOtIG4EA5DOxvdI0qm7TMIzaEB28IoKwlxVtjwZCVS';
    $valid = password_verify($password, $hash) && $user !== null;

    if (!$valid) {
        rate_limit_hit('login', $identifier);
        return [null, 'The email or password you entered is incorrect.'];
    }
    if ($user['status'] !== 'active') {
        return [null, 'Your account has been suspended. Please contact support@lembofix.com.'];
    }

    rate_limit_clear('login', $identifier);
    if (password_needs_rehash($user['password_hash'], PASSWORD_DEFAULT)) {
        db_query('UPDATE users SET password_hash = ? WHERE id = ?', [password_hash($password, PASSWORD_DEFAULT), $user['id']]);
    }
    return [$user, ''];
}

/** Where to send a user after login: the page they wanted, or their dashboard. */
function intended_url(string $role): string
{
    $intended = $_SESSION['_intended'] ?? null;
    unset($_SESSION['_intended']);
    if (is_string($intended) && str_starts_with($intended, '/') && !str_starts_with($intended, '//')
        && !str_contains($intended, '/api/')) {
        return $intended;
    }
    return url(dashboard_url($role));
}
