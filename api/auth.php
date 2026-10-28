<?php
/**
 * Auth helpers for the frontend.
 *   GET api/auth.php?action=check_email&email=...   → { available: bool }  (rate limited)
 *   GET api/auth.php?action=me                       → current user summary (no sensitive fields)
 */
require dirname(__DIR__) . '/includes/bootstrap.php';
require_method('GET');

switch (input('action')) {
    case 'check_email':
        $email = strtolower(input('email'));
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            api_respond(false, 'Please enter a valid email address.');
        }
        // Limit lookups to make account enumeration impractical
        if (rate_limit_count('email_check', client_ip(), 600) >= 20) {
            json_response(['ok' => false, 'message' => 'Too many requests. Please slow down.'], 429);
        }
        rate_limit_hit('email_check', client_ip());
        json_response(['ok' => true, 'available' => !db_value('SELECT id FROM users WHERE email = ?', [$email])]);

    case 'me':
        $user = current_user();
        json_response(['ok' => true, 'user' => $user ? [
            'id' => (int) $user['id'], 'name' => $user['full_name'], 'role' => $user['role'],
            'unread_notifications' => unread_notification_count((int) $user['id']),
        ] : null]);

    default:
        api_respond(false, 'Unknown action.', [], 400);
}
