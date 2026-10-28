<?php
require dirname(__DIR__) . '/includes/bootstrap.php';
$user = require_login(ROLE_ADMIN);

$stats = db_one(
    "SELECT (SELECT COUNT(*) FROM users) AS users,
            (SELECT COUNT(*) FROM users WHERE role = 'customer') AS customers,
            (SELECT COUNT(*) FROM users WHERE role = 'provider') AS providers,
            (SELECT COUNT(*) FROM provider_profiles WHERE approval_status = 'pending') AS pending_providers,
            (SELECT COUNT(*) FROM users WHERE created_at >= CURDATE() - INTERVAL 30 DAY) AS new_users,
            (SELECT COUNT(*) FROM bookings) AS bookings,
            (SELECT COUNT(*) FROM bookings WHERE status = 'completed') AS completed,
            (SELECT COUNT(*) FROM bookings WHERE status = 'pending') AS pending,
            (SELECT COALESCE(SUM(total), 0) FROM bookings WHERE status = 'completed') AS gmv,
            (SELECT COALESCE(SUM(platform_fee), 0) FROM bookings WHERE status = 'completed') AS revenue,
            (SELECT ROUND(AVG(rating), 2) FROM reviews WHERE status = 'published') AS rating,
            (SELECT COUNT(*) FROM reviews) AS reviews,
            (SELECT COUNT(*) FROM reports WHERE status = 'open') AS open_reports"
);
$bookingSeries = monthly_series(
    "SELECT DATE_FORMAT(booking_date, '%Y-%m') AS ym, COUNT(*) AS value FROM bookings
     WHERE booking_date >= DATE_SUB(DATE_FORMAT(CURDATE(), '%Y-%m-01'), INTERVAL 5 MONTH) GROUP BY ym",
    []
);
$revenueSeries = monthly_series(
    "SELECT DATE_FORMAT(booking_date, '%Y-%m') AS ym, SUM(platform_fee) AS value FROM bookings
     WHERE status = 'completed' AND booking_date >= DATE_SUB(DATE_FORMAT(CURDATE(), '%Y-%m-01'), INTERVAL 5 MONTH) GROUP BY ym",
    []
);
$statusCounts = array_column(db_all('SELECT status, COUNT(*) AS total FROM bookings GROUP BY status'), 'total', 'status');
$statusColors = ['completed' => '#079455', 'confirmed' => '#1570EF', 'pending' => '#F79009', 'in_progress' => '#FF6A2B', 'cancelled' => '#98A2B3', 'rejected' => '#F04438'];
$slices = [];
foreach ($statusColors as $status => $color) {
    $slices[] = ['label' => booking_status_label($status), 'value' => (int) ($statusCounts[$status] ?? 0), 'color' => $color];
}
$topCategories = db_all(
    "SELECT c.name AS label, COUNT(DISTINCT b.id) AS value
     FROM bookings b JOIN booking_items bi ON bi.booking_id = b.id
     JOIN provider_services ps ON ps.id = bi.provider_service_id JOIN services s ON s.id = ps.service_id JOIN categories c ON c.id = s.category_id
     WHERE b.status = 'completed' GROUP BY c.id ORDER BY value DESC LIMIT 6"
);
$pendingProviders = db_all(
    "SELECT p.id, p.business_name, p.city, p.created_at, u.avatar FROM provider_profiles p JOIN users u ON u.id = p.user_id
     WHERE p.approval_status = 'pending' ORDER BY p.created_at ASC LIMIT 5"
);
$activity = db_all(
    "SELECT a.*, u.first_name, u.last_name, u.avatar FROM activity_logs a LEFT JOIN users u ON u.id = a.user_id ORDER BY a.created_at DESC, a.id DESC LIMIT 8"
);

dashboard_start(['title' => 'Platform overview', 'subtitle' => 'Live numbers across LemboFix.', 'active' => 'dashboard']);
?>
<?php if ($stats['pending_providers'] || $stats['open_reports']): ?>
    <div class="alert alert-warning">
        <?= icon('alert', 20) ?>
        <div>
            <strong>Needs your attention:</strong>
            <?php if ($stats['pending_providers']): ?><a class="link" href="<?= e(url('admin/providers.php?status=pending')) ?>"><?= plural((int) $stats['pending_providers'], 'provider') ?> awaiting approval</a><?php endif; ?>
            <?php if ($stats['pending_providers'] && $stats['open_reports']): ?> · <?php endif; ?>
            <?php if ($stats['open_reports']): ?><a class="link" href="<?= e(url('admin/reports.php')) ?>"><?= plural((int) $stats['open_reports'], 'open report') ?></a><?php endif; ?>
        </div>
    </div>
<?php endif; ?>

<div class="stat-grid">
    <div class="stat"><div class="stat-icon"><?= icon('users', 20) ?></div><div class="stat-label">Total users</div><div class="stat-value"><?= number_format((int) $stats['users']) ?></div><div class="stat-sub up">+<?= (int) $stats['new_users'] ?> in the last 30 days</div></div>
    <div class="stat"><div class="stat-icon tone-info"><?= icon('user', 20) ?></div><div class="stat-label">Customers</div><div class="stat-value"><?= number_format((int) $stats['customers']) ?></div></div>
    <div class="stat"><div class="stat-icon tone-brand"><?= icon('briefcase', 20) ?></div><div class="stat-label">Providers</div><div class="stat-value"><?= number_format((int) $stats['providers']) ?></div><div class="stat-sub"><?= (int) $stats['pending_providers'] ?> pending approval</div></div>
    <div class="stat"><div class="stat-icon tone-warning"><?= icon('star', 20) ?></div><div class="stat-label">Average rating</div><div class="stat-value"><?= e($stats['rating'] ?? '—') ?></div><div class="stat-sub"><?= plural((int) $stats['reviews'], 'review') ?></div></div>
    <div class="stat"><div class="stat-icon"><?= icon('calendar', 20) ?></div><div class="stat-label">Total bookings</div><div class="stat-value"><?= number_format((int) $stats['bookings']) ?></div></div>
    <div class="stat"><div class="stat-icon tone-success"><?= icon('check-circle', 20) ?></div><div class="stat-label">Completed</div><div class="stat-value"><?= number_format((int) $stats['completed']) ?></div></div>
    <div class="stat"><div class="stat-icon tone-warning"><?= icon('clock', 20) ?></div><div class="stat-label">Pending</div><div class="stat-value"><?= number_format((int) $stats['pending']) ?></div></div>
    <div class="stat"><div class="stat-icon tone-brand"><?= icon('euro', 20) ?></div><div class="stat-label">Platform revenue</div><div class="stat-value"><?= money($stats['revenue']) ?></div><div class="stat-sub"><?= money($stats['gmv']) ?> booking volume</div></div>
</div>

<div class="dash-grid">
    <div>
        <section class="card dash-section">
            <div class="card-head"><h3>Bookings per month</h3><span class="text-sm text-muted">By appointment date</span></div>
            <div class="card-body"><?= chart_bars($bookingSeries) ?></div>
        </section>
        <section class="card dash-section">
            <div class="card-head"><h3>Platform fee revenue</h3><span class="text-sm text-muted">Completed bookings</span></div>
            <div class="card-body"><?= chart_bars($revenueSeries, true) ?></div>
        </section>
        <section class="card dash-section">
            <div class="card-head"><h3>Recent activity</h3><a class="link text-sm" href="<?= e(url('admin/activity.php')) ?>">Full log</a></div>
            <ul class="list-plain mini-list">
                <?php foreach ($activity as $a): ?>
                    <li>
                        <?= avatar_html(trim(($a['first_name'] ?? 'System') . ' ' . ($a['last_name'] ?? '')), $a['avatar'] ?? null, 'sm') ?>
                        <div class="grow"><strong><?= e($a['description']) ?></strong><span><?= e(time_ago($a['created_at'])) ?> · <?= e($a['action']) ?></span></div>
                    </li>
                <?php endforeach; ?>
            </ul>
        </section>
    </div>
    <div>
        <section class="card dash-section">
            <div class="card-head"><h3>Booking status</h3></div>
            <div class="card-body"><?= chart_donut($slices, 'bookings') ?></div>
        </section>
        <section class="card dash-section">
            <div class="card-head"><h3>Top categories</h3><span class="text-sm text-muted">Completed jobs</span></div>
            <div class="card-body"><?= chart_hbars(array_map(fn ($r) => ['label' => $r['label'], 'value' => (int) $r['value']], $topCategories)) ?></div>
        </section>
        <section class="card dash-section">
            <div class="card-head"><h3>Awaiting approval</h3><a class="link text-sm" href="<?= e(url('admin/providers.php?status=pending')) ?>">Review</a></div>
            <?php if ($pendingProviders): ?>
                <ul class="list-plain mini-list">
                    <?php foreach ($pendingProviders as $p): ?>
                        <li><?= avatar_html($p['business_name'], $p['avatar'], 'sm') ?><div class="grow"><strong><?= e($p['business_name']) ?></strong><span><?= e($p['city']) ?> · <?= e(time_ago($p['created_at'])) ?></span></div></li>
                    <?php endforeach; ?>
                </ul>
            <?php else: ?>
                <?= empty_state('All caught up', 'No providers waiting for review.', 'check-circle') ?>
            <?php endif; ?>
        </section>
    </div>
</div>
<?php dashboard_end(); ?>
