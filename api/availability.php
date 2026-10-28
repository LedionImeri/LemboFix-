<?php
/**
 * Public availability lookups for the booking calendar.
 *   GET ?action=month&provider_id=1&month=2026-11&duration=60  → per-day free slot counts
 *   GET ?action=slots&provider_id=1&date=2026-11-03&duration=60 → free start times
 */
require dirname(__DIR__) . '/includes/bootstrap.php';
require_method('GET');

$providerId = input_int('provider_id');
$duration = input_int('duration', 60);
if ($duration < 15 || $duration > 600) {
    api_respond(false, 'Invalid duration.', [], 400);
}
$provider = db_one(
    "SELECT p.id FROM provider_profiles p JOIN users u ON u.id = p.user_id
     WHERE p.id = ? AND p.approval_status = 'approved' AND u.status = 'active'",
    [$providerId]
);
if (!$provider) {
    api_respond(false, 'Provider not found.', [], 404);
}

[$firstDay, $lastDay] = booking_window();

switch (input('action')) {
    case 'month':
        $month = input('month');
        if (!preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $month)) {
            api_respond(false, 'Invalid month.', [], 400);
        }
        json_response([
            'ok'        => true,
            'month'     => $month,
            'first_day' => $firstDay,
            'last_day'  => $lastDay,
            'days'      => month_availability($providerId, $month, $duration),
        ]);

    case 'slots':
        $date = input('date');
        $d = DateTime::createFromFormat('Y-m-d', $date);
        if (!$d || $d->format('Y-m-d') !== $date) {
            api_respond(false, 'Invalid date.', [], 400);
        }
        json_response(['ok' => true, 'date' => $date, 'slots' => available_slots($providerId, $date, $duration)]);

    default:
        api_respond(false, 'Unknown action.', [], 400);
}
