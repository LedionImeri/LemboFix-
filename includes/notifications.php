<?php
/**
 * In-app notifications.
 */

declare(strict_types=1);

function notify(int $userId, string $type, string $title, string $message, ?string $link = null): void
{
    db_query(
        'INSERT INTO notifications (user_id, type, title, message, link) VALUES (?, ?, ?, ?, ?)',
        [$userId, $type, mb_substr($title, 0, 140), mb_substr($message, 0, 255), $link]
    );
}

/** Notify every active administrator. */
function notify_admins(string $type, string $title, string $message, ?string $link = null): void
{
    foreach (db_all("SELECT id FROM users WHERE role = 'admin' AND status = 'active'") as $admin) {
        notify((int) $admin['id'], $type, $title, $message, $link);
    }
}

function unread_notification_count(int $userId): int
{
    return (int) db_value('SELECT COUNT(*) FROM notifications WHERE user_id = ? AND is_read = 0', [$userId]);
}

function recent_notifications(int $userId, int $limit = 8): array
{
    return db_all('SELECT * FROM notifications WHERE user_id = ? ORDER BY created_at DESC, id DESC LIMIT ' . max(1, $limit), [$userId]);
}

function notification_icon(string $type): string
{
    return match (true) {
        str_starts_with($type, 'booking_request')   => 'calendar',
        $type === 'booking_confirmed'               => 'calendar-check',
        in_array($type, ['booking_rejected', 'booking_cancelled'], true) => 'calendar-off',
        $type === 'booking_completed'               => 'check-circle',
        $type === 'booking_reminder'                => 'clock',
        $type === 'booking_in_progress'             => 'wrench',
        str_starts_with($type, 'review')            => 'star',
        str_starts_with($type, 'provider')          => 'badge-check',
        str_starts_with($type, 'report')            => 'flag',
        default                                     => 'bell',
    };
}

/**
 * Appointment reminders without a cron job: when a user is active we create
 * reminders for their confirmed bookings starting within the next 24 hours.
 * Throttled to once every 10 minutes per session.
 */
function maybe_generate_reminders(int $userId): void
{
    if (($_SESSION['_reminders_checked'] ?? 0) > time() - 600) {
        return;
    }
    $_SESSION['_reminders_checked'] = time();

    $due = db_all(
        "SELECT b.id, b.reference, b.customer_id, b.booking_date, b.start_time, p.user_id AS provider_user_id, p.business_name,
                CONCAT(u.first_name, ' ', u.last_name) AS customer_name
         FROM bookings b
         JOIN provider_profiles p ON p.id = b.provider_id
         JOIN users u ON u.id = b.customer_id
         WHERE b.status = 'confirmed' AND b.reminder_sent_at IS NULL
           AND TIMESTAMP(b.booking_date, b.start_time) BETWEEN NOW() AND (NOW() + INTERVAL 24 HOUR)
           AND (b.customer_id = ? OR p.user_id = ?)",
        [$userId, $userId]
    );

    foreach ($due as $b) {
        $when = relative_day($b['booking_date']) . ' at ' . format_time($b['start_time']);
        notify((int) $b['customer_id'], 'booking_reminder', 'Upcoming appointment',
            "Reminder: {$b['business_name']} is booked for {$when}.", 'customer/bookings.php');
        notify((int) $b['provider_user_id'], 'booking_reminder', 'Upcoming job',
            "Reminder: {$b['customer_name']} is booked for {$when}.", 'provider/bookings.php');
        db_query('UPDATE bookings SET reminder_sent_at = NOW() WHERE id = ?', [$b['id']]);
    }
}
