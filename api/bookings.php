<?php
/**
 * Booking endpoint (POST, CSRF protected).
 *   action=create  (customer)                      → creates a pending booking
 *   action=status  (customer | provider | admin)   → status transition allowed for that role
 */
require dirname(__DIR__) . '/includes/bootstrap.php';
require_method('POST');
$user = require_login();
require_csrf();

switch (input('action')) {
    case 'create':
        if ($user['role'] !== ROLE_CUSTOMER) {
            api_respond(false, 'Only customer accounts can make bookings.', [], 403);
        }
        // Basic abuse protection: at most 10 booking attempts per 10 minutes
        if (rate_limit_count('booking', 'u:' . $user['id'], 600) >= 10) {
            api_respond(false, 'You are booking very quickly. Please wait a few minutes and try again.', [], 429);
        }
        rate_limit_hit('booking', 'u:' . $user['id']);

        $result = create_booking((int) $user['id'], $_POST);
        if (!$result['ok']) {
            $first = (string) (array_values($result['errors'])[0] ?? 'Please check your booking details.');
            api_respond(false, $first, ['errors' => $result['errors'], 'slot_taken' => $result['slot_taken'] ?? false]);
        }
        api_respond(true, 'Booking request sent! 🎉', ['reference' => $result['booking']['reference']], 201,
            'booking-success.php?ref=' . urlencode($result['booking']['reference']));

    case 'status':
        $bookingId = input_int('booking_id');
        $status = input('status');
        if (!in_array($status, ['confirmed', 'rejected', 'in_progress', 'completed', 'cancelled'], true)) {
            api_respond(false, 'Unknown status.', [], 400);
        }
        $note = input('note');
        if ($status === 'rejected' && $user['role'] === ROLE_PROVIDER && mb_strlen($note) < 3) {
            api_respond(false, 'Please tell the customer why you are declining.', ['errors' => ['note' => 'A short reason is required.']]);
        }
        $result = change_booking_status($bookingId, $status, $user['role'], (int) $user['id'], $note);
        api_respond($result['ok'], $result['message'], [], $result['ok'] ? 200 : 422);

    default:
        api_respond(false, 'Unknown action.', [], 400);
}
