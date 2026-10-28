<?php
/**
 * Reviews (POST, CSRF protected).
 *   action=create  (customer, completed booking only)  booking_id, rating, comment
 *   action=reply   (provider who received the review)  review_id, reply
 *   action=report  (any logged-in user)                review_id, reason, details
 */
require dirname(__DIR__) . '/includes/bootstrap.php';
require_method('POST');
$user = require_login();
require_csrf();

switch (input('action')) {
    case 'create':
        if ($user['role'] !== ROLE_CUSTOMER) {
            api_respond(false, 'Only customers can leave reviews.', [], 403);
        }
        $result = create_review((int) $user['id'], input_int('booking_id'), input_int('rating'), input('comment'));
        api_respond($result['ok'], $result['message']);

    case 'reply':
        if ($user['role'] !== ROLE_PROVIDER) {
            api_respond(false, 'Only providers can reply to reviews.', [], 403);
        }
        $reply = input('reply');
        if (mb_strlen($reply) < 5 || mb_strlen($reply) > 600) {
            api_respond(false, 'Replies must be between 5 and 600 characters.');
        }
        $updated = db_query(
            'UPDATE reviews r JOIN bookings b ON b.id = r.booking_id
             SET r.provider_reply = ?, r.replied_at = NOW()
             WHERE r.id = ? AND b.provider_id = ? AND r.provider_reply IS NULL',
            [$reply, input_int('review_id'), (int) current_provider()['id']]
        )->rowCount();
        api_respond($updated > 0, $updated ? 'Your reply is now public.' : 'Review not found or already answered.');

    case 'report':
        $reviewId = input_int('review_id');
        $reason = input('reason');
        if (!array_key_exists($reason, report_reasons())) {
            api_respond(false, 'Please choose a reason.');
        }
        $review = db_one(
            'SELECT r.id, b.customer_id, p.business_name FROM reviews r JOIN bookings b ON b.id = r.booking_id
             JOIN provider_profiles p ON p.id = b.provider_id WHERE r.id = ?',
            [$reviewId]
        );
        if (!$review) {
            api_respond(false, 'Review not found.', [], 404);
        }
        if ((int) $review['customer_id'] === (int) $user['id']) {
            api_respond(false, 'You cannot report your own review.');
        }
        if (db_value("SELECT id FROM reports WHERE reporter_id = ? AND review_id = ? AND status = 'open'", [$user['id'], $reviewId])) {
            api_respond(true, 'You already reported this review — our team is on it.');
        }
        db_query('INSERT INTO reports (reporter_id, review_id, reason, details) VALUES (?, ?, ?, ?)',
            [$user['id'], $reviewId, $reason, mb_substr(input('details'), 0, 500) ?: null]);
        notify_admins('report_new', 'New report', "A review on {$review['business_name']} was reported (" . report_reasons()[$reason] . ').', 'admin/reports.php');
        api_respond(true, 'Thanks — our moderation team will review this report.');

    default:
        api_respond(false, 'Unknown action.', [], 400);
}
