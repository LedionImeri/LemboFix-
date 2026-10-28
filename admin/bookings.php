<?php
require dirname(__DIR__) . '/includes/bootstrap.php';
$admin = require_login(ROLE_ADMIN);

$statuses = ['pending', 'confirmed', 'in_progress', 'completed', 'cancelled', 'rejected'];
$status = in_array(input('status'), $statuses, true) ? input('status') : '';
$q = mb_substr(input('q'), 0, 80);
$from = preg_match('/^\d{4}-\d{2}-\d{2}$/', input('from')) ? input('from') : '';
$to = preg_match('/^\d{4}-\d{2}-\d{2}$/', input('to')) ? input('to') : '';

$where = [];
$params = [];
if ($status) { $where[] = 'b.status = ?'; $params[] = $status; }
if ($from) { $where[] = 'b.booking_date >= ?'; $params[] = $from; }
if ($to) { $where[] = 'b.booking_date <= ?'; $params[] = $to; }
if ($q !== '') {
    $where[] = "(b.reference LIKE ? OR p.business_name LIKE ? OR CONCAT(cu.first_name, ' ', cu.last_name) LIKE ?)";
    array_push($params, "%$q%", "%$q%", "%$q%");
}
$counts = array_column(db_all('SELECT status, COUNT(*) AS total FROM bookings GROUP BY status'), 'total', 'status');
$pg = paginate(count_bookings($where, $params), 15, input_int('page', 1));
$bookings = list_bookings($where, $params, 'b.booking_date DESC, b.start_time DESC', $pg['per_page'], $pg['offset']);
$totals = db_one(
    'SELECT COALESCE(SUM(b.total), 0) AS total, COALESCE(SUM(b.platform_fee), 0) AS fees FROM bookings b
     JOIN provider_profiles p ON p.id = b.provider_id JOIN users cu ON cu.id = b.customer_id WHERE ' . ($where ? implode(' AND ', $where) : '1=1'),
    $params
);

dashboard_start(['title' => 'Bookings', 'subtitle' => number_format($pg['total']) . ' bookings · ' . money($totals['total']) . ' volume · ' . money($totals['fees']) . ' fees', 'active' => 'bookings']);
?>
<nav class="tabs">
    <a class="tab<?= $status === '' ? ' is-active' : '' ?>" href="<?= e(url('admin/bookings.php')) ?>">All <span class="count"><?= array_sum($counts) ?></span></a>
    <?php foreach ($statuses as $s): ?>
        <a class="tab<?= $status === $s ? ' is-active' : '' ?>" href="<?= e(url('admin/bookings.php?status=' . $s)) ?>"><?= e(booking_status_label($s)) ?> <span class="count"><?= (int) ($counts[$s] ?? 0) ?></span></a>
    <?php endforeach; ?>
</nav>
<form class="toolbar" method="get" action="<?= e(url('admin/bookings.php')) ?>" data-auto-submit>
    <input type="hidden" name="status" value="<?= e($status) ?>">
    <div class="input-group"><?= icon('search', 18) ?><input class="input" type="search" name="q" value="<?= e($q) ?>" placeholder="Reference, provider or customer"></div>
    <input class="input" type="date" name="from" value="<?= e($from) ?>" aria-label="From date">
    <input class="input" type="date" name="to" value="<?= e($to) ?>" aria-label="To date">
</form>

<?php if ($bookings): ?>
<div class="card">
    <div class="table-wrap">
        <table class="table">
            <thead><tr><th>Reference</th><th>Customer</th><th>Provider</th><th>Service</th><th>When</th><th>Status</th><th>Payment</th><th class="right">Total</th><th class="right">Actions</th></tr></thead>
            <tbody>
            <?php foreach ($bookings as $b): $customer = $b['customer_first_name'] . ' ' . $b['customer_last_name']; ?>
                <tr>
                    <td class="nowrap"><a class="link" href="<?= e(url('booking-success.php?ref=' . $b['reference'])) ?>"><?= e($b['reference']) ?></a></td>
                    <td><div class="cell-user" style="min-width:140px"><?= avatar_html($customer, $b['customer_avatar'], 'xs') ?><strong><?= e($customer) ?></strong></div></td>
                    <td><a href="<?= e(url('provider.php?slug=' . $b['provider_slug'])) ?>"><?= e($b['business_name']) ?></a></td>
                    <td class="text-sm"><?= e(excerpt($b['service_titles'], 40)) ?></td>
                    <td class="nowrap text-sm"><?= e(format_date($b['booking_date'], 'M j, Y')) ?><br><span class="text-muted"><?= e(format_time($b['start_time'])) ?>–<?= e(format_time($b['end_time'])) ?></span></td>
                    <td><?= status_badge($b['status']) ?></td>
                    <td class="text-sm"><?= e(ucfirst($b['payment_method'])) ?><br><?= status_badge($b['payment_status']) ?></td>
                    <td class="right fw-600"><?= money($b['total']) ?></td>
                    <td>
                        <div class="actions">
                            <?php foreach (booking_actions_for($b, ROLE_ADMIN) as $toStatus => [$label, $tone, $ico]): ?>
                                <form method="post" action="<?= e(url('api/bookings.php')) ?>" data-ajax data-on-success="reload">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="action" value="status">
                                    <input type="hidden" name="booking_id" value="<?= (int) $b['id'] ?>">
                                    <input type="hidden" name="status" value="<?= e($toStatus) ?>">
                                    <button type="submit" class="btn btn-xs btn-<?= $tone === 'danger' ? 'danger-soft' : ($tone === 'success' ? 'success-soft' : 'brand-soft') ?>"
                                            data-confirm="<?= e($label) ?> booking <?= e($b['reference']) ?>?" data-confirm-text="Both the customer and the provider will be notified."
                                            data-confirm-ok="<?= e($label) ?>" data-confirm-tone="<?= $tone === 'danger' ? 'danger' : 'success' ?>"<?= in_array($toStatus, ['cancelled', 'rejected'], true) ? ' data-confirm-note="Reason (optional)"' : '' ?>>
                                        <?= icon($ico, 14) ?> <?= e($label) ?>
                                    </button>
                                </form>
                            <?php endforeach; ?>
                        </div>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<?= pagination_html($pg) ?>
<?php else: ?>
    <div class="card"><?= empty_state('No bookings found yet.', 'Try changing the filters.', 'calendar') ?></div>
<?php endif; ?>
<?php dashboard_end(); ?>
