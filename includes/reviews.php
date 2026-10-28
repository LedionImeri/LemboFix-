<?php
/**
 * Reviews, provider replies and content reports.
 */

declare(strict_types=1);

function provider_reviews(int $providerId, int $limit = 10, int $offset = 0, bool $includeHidden = false): array
{
    return db_all(
        "SELECT r.*, b.reference, b.booking_date, u.first_name, u.last_name, u.avatar, u.city,
                (SELECT GROUP_CONCAT(bi.title SEPARATOR ', ') FROM booking_items bi WHERE bi.booking_id = b.id) AS service_titles
         FROM reviews r
         JOIN bookings b ON b.id = r.booking_id
         JOIN users u ON u.id = b.customer_id
         WHERE b.provider_id = ?" . ($includeHidden ? '' : " AND r.status = 'published'") . "
         ORDER BY r.created_at DESC
         LIMIT $limit OFFSET $offset",
        [$providerId]
    );
}

function latest_platform_reviews(int $limit = 3): array
{
    return db_all(
        "SELECT r.rating, r.comment, r.created_at, u.first_name, u.last_name, u.avatar, u.city, p.business_name, p.slug
         FROM reviews r
         JOIN bookings b ON b.id = r.booking_id
         JOIN users u ON u.id = b.customer_id
         JOIN provider_profiles p ON p.id = b.provider_id
         WHERE r.status = 'published' AND r.rating = 5 AND CHAR_LENGTH(r.comment) > 70
         ORDER BY r.created_at DESC
         LIMIT $limit"
    );
}

function create_review(int $customerId, int $bookingId, int $rating, string $comment): array
{
    $booking = find_booking($bookingId);
    if (!$booking || (int) $booking['customer_id'] !== $customerId) {
        return ['ok' => false, 'message' => 'Booking not found.'];
    }
    if ($booking['status'] !== 'completed') {
        return ['ok' => false, 'message' => 'You can review a provider once the booking is completed.'];
    }
    if ($booking['review_id']) {
        return ['ok' => false, 'message' => 'You already reviewed this booking.'];
    }
    $comment = trim($comment);
    if ($rating < 1 || $rating > 5) {
        return ['ok' => false, 'message' => 'Please choose a rating from 1 to 5 stars.'];
    }
    if (mb_strlen($comment) < 10 || mb_strlen($comment) > 1000) {
        return ['ok' => false, 'message' => 'Please write between 10 and 1000 characters.'];
    }

    try {
        db_query('INSERT INTO reviews (booking_id, rating, comment) VALUES (?, ?, ?)', [$bookingId, $rating, $comment]);
    } catch (PDOException $e) {
        if ($e->getCode() === '23000') { // unique booking_id — double submit
            return ['ok' => false, 'message' => 'You already reviewed this booking.'];
        }
        throw $e;
    }
    refresh_provider_rating((int) $booking['provider_id']);
    notify((int) $booking['provider_user_id'], 'review_new', "New {$rating}-star review",
        "{$booking['customer_first_name']} reviewed your " . implode(', ', array_column($booking['items'], 'title')) . '.', 'provider/reviews.php');
    log_activity($customerId, 'review_create', "Review ({$rating}★) for {$booking['business_name']}", 'booking', $bookingId);

    return ['ok' => true, 'message' => 'Thank you! Your review has been published.'];
}

function report_reasons(): array
{
    return ['spam' => 'Spam or advertising', 'offensive' => 'Offensive language', 'fake' => 'Fake or misleading',
            'inappropriate' => 'Inappropriate content', 'other' => 'Something else'];
}
