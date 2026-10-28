<?php
/**
 * CSRF protection: one random token per session, sent as a hidden form field
 * (_csrf) or, for fetch() calls, as the X-CSRF-Token header.
 */

declare(strict_types=1);

function csrf_token(): string
{
    if (empty($_SESSION['_csrf'])) {
        $_SESSION['_csrf'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['_csrf'];
}

function csrf_field(): string
{
    return '<input type="hidden" name="_csrf" value="' . e(csrf_token()) . '">';
}

function csrf_valid(): bool
{
    $sent = $_POST['_csrf'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? json_input()['_csrf'] ?? '';
    return is_string($sent) && $sent !== '' && hash_equals(csrf_token(), $sent);
}

/** Abort the request when the CSRF token is missing or wrong. */
function require_csrf(): void
{
    if (!csrf_valid()) {
        // 403 rather than Laravel-style 419: Apache rewrites unknown status codes to 500
        api_respond(false, 'Your session has expired. Please refresh the page and try again.', [], 403);
    }
}
