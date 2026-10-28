<?php
require dirname(__DIR__) . '/includes/bootstrap.php';
$user = require_login(ROLE_PROVIDER);
$pid = (int) current_provider()['id'];
$q = mb_substr(input('q'), 0, 60);

$params = [$pid];
$search = '';
if ($q !== '') {
    $search = " AND CONCAT(u.first_name, ' ', u.last_name) LIKE ?";
    $params[] = "%$q%";
}
$customers = db_all(
    "SELECT u.id, u.first_name, u.last_name, u.avatar, u.city,
            COUNT(*) AS bookings,
            SUM(b.status = 'completed') AS completed,
            COALESCE(SUM(CASE WHEN b.status = 'completed' THEN b.subtotal END), 0) AS revenue,
            MAX(b.booking_date) AS last_booking,
            -- contact details are only shared once the provider has accepted a booking
            MAX(CASE WHEN b.status IN ('confirmed','in_progress','completed') THEN u.phone END) AS phone,
            (SELECT ROUND(AVG(r.rating), 1) FROM reviews r JOIN bookings b2 ON b2.id = r.booking_id WHERE b2.provider_id = ? AND b2.customer_id = u.id) AS rating
     FROM bookings b JOIN users u ON u.id = b.customer_id
     WHERE b.provider_id = ?$search
     GROUP BY u.id
     ORDER BY last_booking DESC",
    array_merge([$pid], $params)
);

dashboard_start(['title' => 'Customers', 'subtitle' => 'People who booked your services.', 'active' => 'customers']);
?>
<form class="toolbar" method="get" action="<?= e(url('provider/customers.php')) ?>" data-auto-submit>
    <div class="input-group"><?= icon('search', 18) ?><input class="input" type="search" name="q" value="<?= e($q) ?>" placeholder="Search customers"></div>
</form>
<?php if ($customers): ?>
    <div class="card">
        <div class="table-wrap">
            <table class="table">
                <thead><tr><th>Customer</th><th>Bookings</th><th>Completed</th><th>Revenue</th><th>Their rating</th><th>Last booking</th><th>Phone</th></tr></thead>
                <tbody>
                <?php foreach ($customers as $c): $name = $c['first_name'] . ' ' . $c['last_name']; ?>
                    <tr>
                        <td><div class="cell-user"><?= avatar_html($name, $c['avatar'], 'sm') ?><div><strong><?= e($name) ?></strong><span><?= e($c['city']) ?></span></div></div></td>
                        <td><?= (int) $c['bookings'] ?></td>
                        <td><?= (int) $c['completed'] ?></td>
                        <td class="fw-600"><?= money($c['revenue']) ?></td>
                        <td><?= $c['rating'] ? '★ ' . e($c['rating']) : '<span class="text-muted">—</span>' ?></td>
                        <td class="nowrap"><?= e(format_date($c['last_booking'], 'M j, Y')) ?></td>
                        <td class="nowrap"><?= $c['phone'] ? e($c['phone']) : '<span class="text-muted text-xs">After confirmation</span>' ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
<?php else: ?>
    <div class="card"><?= empty_state($q ? 'No customers match your search' : 'No customers yet', 'Customers appear here after their first booking with you.', 'users') ?></div>
<?php endif; ?>
<?php dashboard_end(); ?>
