<?php
/**
 * Loaded at the top of every page and API endpoint.
 */

declare(strict_types=1);

define('APP_ROOT', dirname(__DIR__));

require APP_ROOT . '/config/config.php';
require APP_ROOT . '/config/database.php';
require APP_ROOT . '/includes/functions.php';
require APP_ROOT . '/includes/icons.php';
require APP_ROOT . '/includes/validation.php';
require APP_ROOT . '/includes/csrf.php';
require APP_ROOT . '/includes/rate_limit.php';
require APP_ROOT . '/includes/auth.php';
require APP_ROOT . '/includes/notifications.php';
require APP_ROOT . '/includes/providers.php';
require APP_ROOT . '/includes/availability.php';
require APP_ROOT . '/includes/bookings.php';
require APP_ROOT . '/includes/reviews.php';
require APP_ROOT . '/includes/uploads.php';
require APP_ROOT . '/includes/account.php';
require APP_ROOT . '/includes/charts.php';
require APP_ROOT . '/includes/layout.php';

date_default_timezone_set((string) config('app.timezone'));

$isProduction = config('app.env') === 'production';
error_reporting(E_ALL);
ini_set('display_errors', $isProduction ? '0' : '1');
ini_set('log_errors', '1');

// Friendly error page instead of a white screen / stack trace in production
set_exception_handler(function (Throwable $e) use ($isProduction): void {
    error_log('[LemboFix] ' . $e);
    if (is_ajax()) {
        json_response(['ok' => false, 'message' => 'Something went wrong on our side. Please try again.'], 500);
    }
    http_response_code(500);
    if (!$isProduction) {
        echo '<pre style="padding:16px;white-space:pre-wrap">' . e((string) $e) . '</pre>';
        exit;
    }
    render_error_page(500);
});

send_security_headers();
start_secure_session();

// Logged-in housekeeping: suspended accounts are signed out, reminders are generated lazily
if ($u = current_user()) {
    if ($u['status'] !== 'active') {
        logout_user();
        start_secure_session();
        flash('error', 'Your account has been suspended. Please contact support.');
    } else {
        maybe_generate_reminders((int) $u['id']);
    }
}
