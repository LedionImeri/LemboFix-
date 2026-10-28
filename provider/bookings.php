<?php
require dirname(__DIR__) . '/includes/bootstrap.php';
$user = require_login(ROLE_PROVIDER);
$pid = (int) current_provider()['id'];

$tabs = [
    'pending'   => ['Requests', ["b.status = 'pending'"], 'b.booking_date ASC, b.start_time ASC'],
    'upcoming'  => ['Upcoming', ["b.status IN ('confirmed','in_progress')", 'b.booking_date >= CURDATE()'], 'b.booking_date ASC, b.start_time ASC'],
    'active'    => ['In progress', ["b.status = 'in_progress'"], 'b.booking_date ASC'],
    'completed' => ['Completed', ["b.status = 'completed'"], 'b.booking_date DESC, b.start_time DESC'],
    'closed'    => ['Cancelled & declined', ["b.status IN ('cancelled','rejected')"], 'b.booking_date DESC'],
    'all'       => ['All', [], 'b.booking_date DESC, b.start_time DESC'],
];
$status = array_key_exists(input('status'), $tabs) ? input('status') : 'pending';
$view = input('view') === 'cards' ? 'cards' : 'table';
$q = mb_substr(input('q'), 0, 60);

[$tabLabel, $conditions, $order] = $tabs[$status];
$where = array_merge(['b.provider_id = ?'], $conditions);
$params = [$pid];
if ($q !== '') {
    $where[] = "(CONCAT(cu.first_name, ' ', cu.last_name) LIKE ? OR b.reference LIKE ?)";
    $params[] = "%$q%";
    $params[] = "%$q%";
}
$counts = [];
foreach ($tabs as $key => [, $cond]) {
    $counts[$key] = count_bookings(array_merge(['b.provider_id = ?'], $cond), [$pid]);
}
$pg = paginate(count_bookings($where, $params), 12, input_int('page', 1));
$bookings = list_bookings($where, $params, $order, $pg['per_page'], $pg['offset']);

dashboard_start(['title' => 'Bookings', 'subtitle' => 'Accept requests, start jobs and mark them complete.', 'active' => 'bookings']);
?>
<nav class="tabs" aria-label="Booking status">
    <?php foreach ($tabs as $key => [$label]): ?>
        <a class="tab<?= $key === $status ? ' is-active' : '' ?>" href="<?= e(url('provider/bookings.php?status=' . $key . '&view=' . $view)) ?>"><?= e($label) ?> <span class="count"><?= $counts[$key] ?></span></a>
    <?php endforeach; ?>
</nav>

<form class="toolbar" method="get" action="<?= e(url('provider/bookings.php')) ?>" data-auto-submit>
    <input type="hidden" name="status" value="<?= e($status) ?>">
    <input type="hidden" name="view" value="<?= e($view) ?>">
    <div class="input-group"><?= icon('search', 18) ?><input class="input" type="search" name="q" value="<?= e($q) ?>" placeholder="Search customer or reference"></div>
    <a class="btn btn-sm <?= $view === 'table' ? 'btn-dark' : 'btn-secondary' ?>" href="<?= e(query_with(['view' => 'table'])) ?>"><?= icon('list', 16) ?> Table</a>
    <a class="btn btn-sm <?= $view === 'cards' ? 'btn-dark' : 'btn-secondary' ?>" href="<?= e(query_with(['view' => 'cards'])) ?>"><?= icon('grid', 16) ?> Cards</a>
</form>

<?php if (!$bookings): ?>
    <div class="card"><?= empty_state('No bookings found yet.', $status === 'pending' ? 'New requests will appear here. Keep your availability up to date to get more bookings.' : 'Bookings with this status will appear here.', 'calendar') ?></div>
<?php elseif ($view === 'cards'): ?>
    <div class="booking-list"><?php foreach ($bookings as $b): partial('booking-item', ['b' => $b, 'view' => 'provider']); endforeach; ?></div>
<?php else: ?>
    <div class="card">
        <div class="table-wrap">
            <table class="table">
                <thead><tr><th>Customer</th><th>Service</th><th>Date</th><th>Time</th><th>Status</th><th class="right">Amount</th><th class="right">Actions</th></tr></thead>
                <tbody>
                <?php foreach ($bookings as $b): $name = $b['customer_first_name'] . ' ' . $b['customer_last_name']; ?>
                    <tr>
                        <td><div class="cell-user"><?= avatar_html($name, $b['customer_avatar'], 'sm') ?><div><strong><?= e($name) ?></strong><span><?= e($b['city']) ?> · #<?= e($b['reference']) ?></span></div></div></td>
                        <td><?= e($b['service_titles']) ?><?php if ($b['customer_message']): ?><br><span class="text-xs text-muted" title="<?= e($b['customer_message']) ?>"><?= icon('message', 12) ?> <?= e(excerpt($b['customer_message'], 40)) ?></span><?php endif; ?></td>
                        <td class="nowrap"><?= e(relative_day($b['booking_date'])) ?></td>
                        <td class="nowrap"><?= e(format_time($b['start_time'])) ?>–<?= e(format_time($b['end_time'])) ?></td>
                        <td><?= status_badge($b['status']) ?></td>
                        <td class="right fw-600"><?= money($b['subtotal']) ?></td>
                        <td>
                            <div class="actions">
                                <?php foreach (booking_actions_for($b, ROLE_PROVIDER) as $to => [$label, $tone, $ico]): ?>
                                    <form method="post" action="<?= e(url('api/bookings.php')) ?>" data-ajax data-on-success="reload">
                                        <?= csrf_field() ?>
                                        <input type="hidden" name="action" value="status">
                                        <input type="hidden" name="booking_id" value="<?= (int) $b['id'] ?>">
                                        <input type="hidden" name="status" value="<?= e($to) ?>">
                                        <button type="submit" class="btn btn-xs btn-<?= $tone === 'danger' ? 'danger-soft' : ($tone === 'success' ? 'success' : 'brand-soft') ?>"
                                            data-confirm="<?= e($label) ?> this booking?"
                                            data-confirm-text="<?= e($name . ' · ' . $b['service_titles'] . ' · ' . relative_day($b['booking_date']) . ' ' . format_time($b['start_time'])) ?>"
                                            data-confirm-ok="<?= e($label) ?>" data-confirm-tone="<?= $tone === 'danger' ? 'danger' : ($tone === 'success' ? 'success' : 'brand') ?>"
                                            <?= $to === 'rejected' ? 'data-confirm-note="Reason for declining (shared with the customer)" data-confirm-note-required' : ($to === 'cancelled' ? 'data-confirm-note="Reason (shared with the customer)"' : '') ?>>
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
<?php endif; ?>
<?= pagination_html($pg) ?>
<?php dashboard_end(); ?>
