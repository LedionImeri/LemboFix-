<?php
require __DIR__ . '/includes/bootstrap.php';

// Logout is a state change, so it requires POST + CSRF (prevents forced logout via <img src>)
if (!is_post()) {
    redirect(is_logged_in() ? dashboard_url() : 'index.php');
}
require_csrf();

if ($user = current_user()) {
    log_activity((int) $user['id'], 'logout', "{$user['full_name']} signed out", 'user', (int) $user['id']);
}
logout_user();
start_secure_session();
flash('success', 'You have been logged out. See you soon!');
redirect('index.php');
