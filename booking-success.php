<?php
require __DIR__ . '/includes/bootstrap.php';
$user = require_login();

$booking = find_booking_by_reference(preg_replace('/[^A-Z0-9\-]/', '', strtoupper(input('ref'))));
$allowed = $booking && (
    (int) $booking['customer_id'] === (int) $user['id']
    || (int) $booking['provider_user_id'] === (int) $user['id']
    || $user['role'] === ROLE_ADMIN
);
if (!$allowed) {
    render_error_page(404, 'We couldn\'t find that booking.');
}
$isNew = $booking['status'] === 'pending' && strtotime($booking['created_at']) > time() - 600;

page_start(['title' => 'Booking ' . $booking['reference'], 'noindex' => true]);
?>
<section class="success-hero container">
    <?php if ($isNew): ?>
        <div class="success-icon"><?= icon('check', 40) ?></div>
        <h1>Booking request sent!</h1>
        <p class="text-muted"><?= e($booking['business_name']) ?> will confirm your appointment shortly. We'll notify you as soon as they respond.</p>
    <?php else: ?>
        <div class="success-icon"><?= icon('receipt', 36) ?></div>
        <h1>Booking details</h1>
        <p class="text-muted">Reference <?= e($booking['reference']) ?></p>
    <?php endif; ?>
</section>

<div class="container">
    <div class="card receipt">
        <div class="card-head">
            <h2>Summary</h2>
            <?= status_badge($booking['status']) ?>
        </div>
        <div class="card-body">
            <div class="summary-provider">
                <?= avatar_html($booking['business_name'], $booking['provider_avatar'], 'md') ?>
                <div><strong><?= e($booking['business_name']) ?></strong><span class="text-sm text-muted"><?= e($booking['provider_city']) ?></span></div>
            </div>
            <div class="receipt-row"><span>Reference</span><strong><?= e($booking['reference']) ?></strong></div>
            <div class="receipt-row"><span>Date</span><strong><?= e(format_date($booking['booking_date'], 'l, F j, Y')) ?></strong></div>
            <div class="receipt-row"><span>Time</span><strong><?= e(format_time($booking['start_time'])) ?> – <?= e(format_time($booking['end_time'])) ?></strong></div>
            <div class="receipt-row"><span>Address</span><strong><?= e($booking['address'] . ', ' . $booking['city']) ?></strong></div>
            <?php foreach ($booking['items'] as $item): ?>
                <div class="receipt-row"><span><?= e($item['title']) ?> · <?= e(duration_label((int) $item['duration_minutes'])) ?></span><strong><?= money($item['price']) ?></strong></div>
            <?php endforeach; ?>
            <div class="receipt-row"><span>Platform fee</span><strong><?= money($booking['platform_fee']) ?></strong></div>
            <div class="receipt-row"><span>Payment</span><strong><?= e($booking['payment_method'] === 'card' ? 'Demo card' : 'Cash after the job') ?> · <?= status_badge($booking['payment_status']) ?></strong></div>
            <div class="summary-total"><span class="fw-600">Total</span><strong><?= money($booking['total']) ?></strong></div>
            <?php if ($booking['payment_method'] === 'card'): ?>
                <p class="text-xs text-muted mt-2 mb-0">Demo payment — no real money was charged.</p>
            <?php endif; ?>
        </div>
        <div class="card-foot form-actions">
            <?php if ($user['role'] === ROLE_CUSTOMER): ?>
                <a class="btn btn-primary" href="<?= e(url('customer/bookings.php')) ?>"><?= icon('calendar', 18) ?> My bookings</a>
                <a class="btn btn-secondary" href="<?= e(url('search.php')) ?>">Book another service</a>
            <?php else: ?>
                <a class="btn btn-primary" href="<?= e(url(dashboard_url())) ?>">Back to dashboard</a>
            <?php endif; ?>
        </div>
    </div>
</div>
<?php page_end(); ?>
