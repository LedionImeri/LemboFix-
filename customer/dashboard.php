<?php
require dirname(__DIR__) . '/includes/bootstrap.php';
$user = require_login(ROLE_CUSTOMER);
$uid = (int) $user['id'];

$profile = db_one('SELECT * FROM customer_profiles WHERE user_id = ?', [$uid]) ?? [];
$completionChecks = ['Phone number' => !empty($user['phone']), 'City' => !empty($user['city']), 'Address' => !empty($profile['address']), 'Profile photo' => !empty($user['avatar'])];
$completion = (int) round(count(array_filter($completionChecks)) / count($completionChecks) * 100);

$stats = db_one(
    "SELECT SUM(status IN ('pending','confirmed','in_progress') AND booking_date >= CURDATE()) AS upcoming,
            SUM(status = 'completed') AS completed,
            COALESCE(SUM(CASE WHEN status = 'completed' THEN total END), 0) AS spent
     FROM bookings WHERE customer_id = ?",
    [$uid]
);
$favoritesCount = (int) db_value('SELECT COUNT(*) FROM favorites WHERE customer_id = ?', [$uid]);
$toReview = list_bookings(["b.customer_id = ?", "b.status = 'completed'", 'r.id IS NULL'], [$uid], 'b.booking_date DESC', 3);
$upcoming = list_bookings(
    ['b.customer_id = ?', "b.status IN ('pending','confirmed','in_progress')", 'b.booking_date >= CURDATE()'],
    [$uid], 'b.booking_date ASC, b.start_time ASC', 3
);
$recent = list_bookings(['b.customer_id = ?', "b.status IN ('completed','cancelled','rejected')"], [$uid], 'b.booking_date DESC, b.start_time DESC', 3);
$favorites = db_all(
    'SELECT p.id, p.business_name, p.slug, p.city, p.avg_rating, p.review_count, u.avatar
     FROM favorites f JOIN provider_profiles p ON p.id = f.provider_id JOIN users u ON u.id = p.user_id
     WHERE f.customer_id = ? ORDER BY f.created_at DESC LIMIT 4',
    [$uid]
);
$notifications = recent_notifications($uid, 5);

dashboard_start([
    'title'    => 'Overview',
    'subtitle' => 'Everything about your bookings in one place.',
    'active'   => 'dashboard',
    'actions'  => '<a class="btn btn-primary" href="' . e(url('search.php')) . '">' . icon('search', 18) . ' Book a service</a>',
]);
?>
<div class="welcome-card">
    <div>
        <h2>Hi <?= e($user['first_name']) ?> 👋</h2>
        <?php if ($upcoming): ?>
            <p>Your next appointment is <strong><?= e(strtolower(relative_day($upcoming[0]['booking_date']))) ?> at <?= e(format_time($upcoming[0]['start_time'])) ?></strong> with <?= e($upcoming[0]['business_name']) ?>.</p>
        <?php else: ?>
            <p>No upcoming appointments. What can we help you fix today?</p>
        <?php endif; ?>
        <?php if ($completion < 100): ?>
            <div class="progress"><div class="progress-fill" style="width: <?= $completion ?>%"></div></div>
            <p class="text-sm mt-1">Profile <?= $completion ?>% complete — add <?= e(strtolower(implode(', ', array_keys(array_filter($completionChecks, fn ($v) => !$v))))) ?> for faster bookings.</p>
        <?php endif; ?>
    </div>
    <a class="btn btn-primary" href="<?= e(url($completion < 100 ? 'customer/profile.php' : 'search.php')) ?>"><?= $completion < 100 ? 'Complete profile' : 'Find a pro' ?> <?= icon('arrow-right', 18) ?></a>
</div>

<div class="stat-grid">
    <div class="stat"><div class="stat-icon tone-brand"><?= icon('calendar', 20) ?></div><div class="stat-label">Upcoming bookings</div><div class="stat-value"><?= (int) $stats['upcoming'] ?></div></div>
    <div class="stat"><div class="stat-icon tone-success"><?= icon('check-circle', 20) ?></div><div class="stat-label">Completed jobs</div><div class="stat-value"><?= (int) $stats['completed'] ?></div></div>
    <div class="stat"><div class="stat-icon tone-info"><?= icon('heart', 20) ?></div><div class="stat-label">Saved providers</div><div class="stat-value"><?= $favoritesCount ?></div></div>
    <div class="stat"><div class="stat-icon tone-warning"><?= icon('euro', 20) ?></div><div class="stat-label">Total spent</div><div class="stat-value"><?= money($stats['spent']) ?></div></div>
</div>

<?php if ($toReview): ?>
    <div class="alert alert-info">
        <?= icon('star', 20) ?>
        <div><strong>How did it go?</strong> You have <?= plural(count($toReview), 'completed booking') ?> waiting for a review.
            <button type="button" class="link-btn" data-modal-open="#review-modal" data-fill-booking-id="<?= (int) $toReview[0]['id'] ?>" data-fill-provider-name="<?= e($toReview[0]['business_name']) ?>">Rate <?= e($toReview[0]['business_name']) ?> now</button>
        </div>
    </div>
<?php endif; ?>

<div class="dash-grid">
    <div>
        <section class="dash-section">
            <div class="section-head mb-2" style="margin-bottom:16px">
                <h2 class="mb-0" style="font-size:1.2rem">Upcoming bookings</h2>
                <a class="link text-sm" href="<?= e(url('customer/bookings.php')) ?>">View all</a>
            </div>
            <?php if ($upcoming): ?>
                <div class="booking-list"><?php foreach ($upcoming as $b): partial('booking-item', ['b' => $b, 'view' => 'customer']); endforeach; ?></div>
            <?php else: ?>
                <div class="card"><?= empty_state('No bookings found yet.', 'When you book a professional, your appointments will show up here.', 'calendar', '<a class="btn btn-primary" href="' . e(url('search.php')) . '">Find a professional</a>') ?></div>
            <?php endif; ?>
        </section>

        <?php if ($recent): ?>
        <section class="dash-section">
            <div class="section-head" style="margin-bottom:16px">
                <h2 class="mb-0" style="font-size:1.2rem">Recent bookings</h2>
                <a class="link text-sm" href="<?= e(url('customer/bookings.php?status=history')) ?>">History</a>
            </div>
            <div class="booking-list"><?php foreach ($recent as $b): partial('booking-item', ['b' => $b, 'view' => 'customer']); endforeach; ?></div>
        </section>
        <?php endif; ?>
    </div>

    <div>
        <section class="card dash-section">
            <div class="card-head"><h3>Notifications</h3><a class="link text-sm" href="<?= e(url('notifications.php')) ?>">See all</a></div>
            <?php if ($notifications): ?>
                <ul class="list-plain mini-list">
                    <?php foreach ($notifications as $n): ?>
                        <li>
                            <span class="notif-ico"><?= icon(notification_icon($n['type']), 16) ?></span>
                            <div class="grow"><strong><?= e($n['title']) ?></strong><span><?= e(time_ago($n['created_at'])) ?></span></div>
                            <?php if (!$n['is_read']): ?><span class="badge badge-brand">New</span><?php endif; ?>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php else: ?>
                <?= empty_state('All caught up', 'Booking updates will appear here.', 'bell') ?>
            <?php endif; ?>
        </section>

        <section class="card dash-section">
            <div class="card-head"><h3>Favorite providers</h3><a class="link text-sm" href="<?= e(url('customer/favorites.php')) ?>">Manage</a></div>
            <?php if ($favorites): ?>
                <ul class="list-plain mini-list">
                    <?php foreach ($favorites as $f): ?>
                        <li>
                            <?= avatar_html($f['business_name'], $f['avatar'], 'sm') ?>
                            <div class="grow"><strong><a href="<?= e(url('provider.php?slug=' . $f['slug'])) ?>"><?= e($f['business_name']) ?></a></strong><span><?= e($f['city']) ?> · ★ <?= number_format((float) $f['avg_rating'], 1) ?></span></div>
                            <a class="btn btn-xs btn-secondary" href="<?= e(url('book.php?provider=' . $f['slug'])) ?>">Book</a>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php else: ?>
                <?= empty_state('No favorites yet', 'Tap the heart on any provider to save them here.', 'heart') ?>
            <?php endif; ?>
        </section>
    </div>
</div>
<?php partial('review-modal'); ?>
<?php dashboard_end(); ?>
