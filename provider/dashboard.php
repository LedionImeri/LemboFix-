<?php
require dirname(__DIR__) . '/includes/bootstrap.php';
$user = require_login(ROLE_PROVIDER);
$provider = current_provider();
$pid = (int) $provider['id'];

$stats = provider_stats($pid);
$completion = provider_profile_completion($provider);
$revenueSeries = monthly_series(
    "SELECT DATE_FORMAT(booking_date, '%Y-%m') AS ym, SUM(subtotal) AS value FROM bookings
     WHERE provider_id = ? AND status = 'completed' AND booking_date >= DATE_SUB(DATE_FORMAT(CURDATE(), '%Y-%m-01'), INTERVAL 5 MONTH)
     GROUP BY ym",
    [$pid]
);
$pending = list_bookings(['b.provider_id = ?', "b.status = 'pending'"], [$pid], 'b.booking_date ASC, b.start_time ASC', 5);
$schedule = list_bookings(['b.provider_id = ?', "b.status IN ('confirmed','in_progress')", 'b.booking_date >= CURDATE()'], [$pid], 'b.booking_date ASC, b.start_time ASC', 5);
$reviews = provider_reviews($pid, 3);
$topServices = db_all(
    "SELECT bi.title AS label, COUNT(*) AS value FROM booking_items bi JOIN bookings b ON b.id = bi.booking_id
     WHERE b.provider_id = ? AND b.status = 'completed' GROUP BY bi.title ORDER BY value DESC LIMIT 5",
    [$pid]
);

dashboard_start([
    'title'    => 'Welcome back, ' . $user['first_name'],
    'subtitle' => 'Here\'s how ' . $provider['business_name'] . ' is doing.',
    'active'   => 'dashboard',
    'actions'  => '<a class="btn btn-secondary" href="' . e(url('provider/availability.php')) . '">' . icon('clock', 18) . ' Availability</a>'
        . '<a class="btn btn-primary" href="' . e(url('provider/services.php')) . '">' . icon('plus', 18) . ' Add service</a>',
]);
?>
<?php if ($completion < 100): ?>
    <div class="card dash-section">
        <div class="card-body flex items-center gap-2 wrap">
            <div class="stat-icon tone-brand mb-0"><?= icon('award', 20) ?></div>
            <div style="flex:1;min-width:220px">
                <strong>Your profile is <?= $completion ?>% complete</strong>
                <p class="text-sm text-muted mb-0">Complete profiles with a photo, detailed bio and services get up to 3× more bookings.</p>
                <div class="progress progress-light" style="max-width:none"><div class="progress-fill" style="width: <?= $completion ?>%"></div></div>
            </div>
            <a class="btn btn-secondary btn-sm" href="<?= e(url('provider/profile.php')) ?>">Complete profile</a>
        </div>
    </div>
<?php endif; ?>

<div class="stat-grid stat-grid-5">
    <div class="stat"><div class="stat-icon"><?= icon('calendar', 20) ?></div><div class="stat-label">Total bookings</div><div class="stat-value"><?= (int) $stats['total'] ?></div><div class="stat-sub"><?= plural((int) $stats['customers'], 'customer') ?></div></div>
    <div class="stat"><div class="stat-icon tone-warning"><?= icon('clock', 20) ?></div><div class="stat-label">Pending requests</div><div class="stat-value"><?= (int) $stats['pending'] ?></div><div class="stat-sub"><?= (int) $stats['upcoming'] ?> upcoming confirmed</div></div>
    <div class="stat"><div class="stat-icon tone-success"><?= icon('check-circle', 20) ?></div><div class="stat-label">Completed</div><div class="stat-value"><?= (int) $stats['completed'] ?></div><div class="stat-sub"><?= (int) $stats['cancelled'] ?> cancelled/declined</div></div>
    <div class="stat"><div class="stat-icon tone-brand"><?= icon('euro', 20) ?></div><div class="stat-label">Revenue</div><div class="stat-value"><?= money($stats['revenue']) ?></div><div class="stat-sub up"><?= money($stats['revenue_month']) ?> this month</div></div>
    <div class="stat"><div class="stat-icon tone-info"><?= icon('star', 20) ?></div><div class="stat-label">Average rating</div><div class="stat-value"><?= (int) $provider['review_count'] ? number_format((float) $provider['avg_rating'], 2) : '—' ?></div><div class="stat-sub"><?= plural((int) $provider['review_count'], 'review') ?></div></div>
</div>

<div class="dash-grid">
    <div>
        <section class="dash-section">
            <div class="section-head" style="margin-bottom:16px">
                <h2 class="mb-0" style="font-size:1.2rem">Booking requests <?php if ($stats['pending']): ?><span class="badge badge-warning"><?= (int) $stats['pending'] ?> new</span><?php endif; ?></h2>
                <a class="link text-sm" href="<?= e(url('provider/bookings.php?status=pending')) ?>">View all</a>
            </div>
            <?php if ($pending): ?>
                <div class="booking-list"><?php foreach ($pending as $b): partial('booking-item', ['b' => $b, 'view' => 'provider']); endforeach; ?></div>
            <?php else: ?>
                <div class="card"><?= empty_state('No pending requests', 'New booking requests from customers will appear here for you to accept or decline.', 'inbox') ?></div>
            <?php endif; ?>
        </section>

        <section class="card dash-section">
            <div class="card-head"><h3>Revenue · last 6 months</h3><span class="text-sm text-muted">Completed jobs, excl. platform fee</span></div>
            <div class="card-body"><?= chart_bars($revenueSeries, true) ?></div>
        </section>
    </div>

    <div>
        <section class="card dash-section">
            <div class="card-head"><h3>Upcoming schedule</h3><a class="link text-sm" href="<?= e(url('provider/bookings.php?status=upcoming')) ?>">All</a></div>
            <?php if ($schedule): ?>
                <ul class="list-plain mini-list">
                    <?php foreach ($schedule as $b): ?>
                        <li>
                            <div class="booking-date" style="padding:6px 8px;min-width:52px"><small><?= e(date('M', strtotime($b['booking_date']))) ?></small><strong style="font-size:1.1rem"><?= e(date('j', strtotime($b['booking_date']))) ?></strong></div>
                            <div class="grow"><strong><?= e($b['service_titles']) ?></strong><span><?= e(format_time($b['start_time'])) ?> · <?= e($b['customer_first_name'] . ' ' . $b['customer_last_name']) ?> · <?= e($b['city']) ?></span></div>
                            <?= status_badge($b['status']) ?>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php else: ?>
                <?= empty_state('Nothing scheduled', 'Confirmed jobs will show up here.', 'calendar') ?>
            <?php endif; ?>
        </section>

        <section class="card dash-section">
            <div class="card-head"><h3>Top services</h3></div>
            <div class="card-body"><?= chart_hbars($topServices) ?></div>
        </section>

        <section class="card dash-section">
            <div class="card-head"><h3>Latest reviews</h3><a class="link text-sm" href="<?= e(url('provider/reviews.php')) ?>">All</a></div>
            <div class="card-body">
                <?php if ($reviews): foreach ($reviews as $r): ?>
                    <div class="review">
                        <div class="review-head"><?= avatar_html($r['first_name'] . ' ' . $r['last_name'], $r['avatar'], 'sm') ?><div><strong><?= e($r['first_name']) ?></strong><span><?= stars_html((float) $r['rating'], 'sm') ?></span></div></div>
                        <p class="text-sm"><?= e(excerpt($r['comment'], 120)) ?></p>
                    </div>
                <?php endforeach; else: ?>
                    <p class="text-muted text-sm mb-0">No reviews yet — they appear after completed jobs.</p>
                <?php endif; ?>
            </div>
        </section>
    </div>
</div>
<?php dashboard_end(); ?>
