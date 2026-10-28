<?php
/**
 * Notifications for the logged-in user.
 *   GET  ?action=list | count
 *   POST { action: read, id } | { action: read_all } | { action: delete, id }
 */
require dirname(__DIR__) . '/includes/bootstrap.php';
$user = require_login();
$uid = (int) $user['id'];

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'GET') {
    if (input('action') === 'count') {
        json_response(['ok' => true, 'unread' => unread_notification_count($uid)]);
    }
    $items = array_map(fn ($n) => [
        'id'       => (int) $n['id'],
        'type'     => $n['type'],
        'title'    => $n['title'],
        'message'  => $n['message'],
        'link'     => $n['link'] ? url($n['link']) : null,
        'is_read'  => (bool) $n['is_read'],
        'time_ago' => time_ago($n['created_at']),
    ], recent_notifications($uid, 8));
    json_response(['ok' => true, 'unread' => unread_notification_count($uid), 'items' => $items]);
}

require_csrf();
$payload = $_POST ?: json_input();
$id = (int) ($payload['id'] ?? 0);

switch ($payload['action'] ?? '') {
    case 'read':
        db_query('UPDATE notifications SET is_read = 1 WHERE id = ? AND user_id = ?', [$id, $uid]);
        api_respond(true, '', ['unread' => unread_notification_count($uid)]);
    case 'read_all':
        db_query('UPDATE notifications SET is_read = 1 WHERE user_id = ? AND is_read = 0', [$uid]);
        api_respond(true, 'All notifications marked as read.', ['unread' => 0]);
    case 'delete':
        db_query('DELETE FROM notifications WHERE id = ? AND user_id = ?', [$id, $uid]);
        api_respond(true, 'Notification removed.', ['unread' => unread_notification_count($uid)]);
    default:
        api_respond(false, 'Unknown action.', [], 400);
}
