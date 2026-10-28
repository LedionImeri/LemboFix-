<?php
require dirname(__DIR__) . '/includes/bootstrap.php';
$user = require_login(ROLE_CUSTOMER);
$uid = (int) $user['id'];

$tabs = [
    'upcoming'  => ['Upcoming', ["b.status IN ('pending','confirmed','in_progress')", 'b.booking_date >= CURDATE()'], 'b.booking_date ASC, b.start_time ASC'],
    'pending'   => ['Awaiting confirmation', ["b.status = 'pending'"], 'b.booking_date ASC, b.start_time ASC'],
    'completed' => ['Completed', ["b.status = 'completed'"], 'b.booking_date DESC, b.start_time DESC'],
    'history'   => ['Cancelled & declined', ["b.status IN ('cancelled','rejected')"], 'b.booking_date DESC'],
    'all'       => ['All', [], 'b.booking_date DESC, b.start_time DESC'],
];
$status = array_key_exists(input('status'), $tabs) ? input('status') : 'upcoming';
[$label, $conditions, $order] = $tabs[$status];
$where = array_merge(['b.customer_id = ?'], $conditions);
$counts = [];
foreach ($tabs as $key => [, $cond]) {
    $counts[$key] = count_bookings(array_merge(['b.customer_id = ?'], $cond), [$uid]);
}
$pg = paginate($counts[$status], 10, input_int('page', 1));
$bookings = list_bookings($where, [$uid], $order, $pg['per_page'], $pg['offset']);

dashboard_start([
    'title'    => 'My bookings',
    'subtitle' => 'Track, cancel and review your appointments.',
    'active'   => 'bookings',
    'actions'  => '<a class="btn btn-primary" href="' . e(url('search.php')) . '">' . icon('plus', 18) . ' New booking</a>',
]);
?>
<nav class="tabs" aria-label="Booking filters">
    <?php foreach ($tabs as $key => [$tabLabel]): ?>
        <a class="tab<?= $key === $status ? ' is-active' : '' ?>" href="<?= e(url('customer/bookings.php?status=' . $key)) ?>"><?= e($tabLabel) ?> <span class="count"><?= $counts[$key] ?></span></a>
    <?php endforeach; ?>
</nav>

<?php if ($bookings): ?>
    <div class="booking-list">
        <?php foreach ($bookings as $b): partial('booking-item', ['b' => $b, 'view' => 'customer']); endforeach; ?>
    </div>
    <?= pagination_html($pg) ?>
<?php else: ?>
    <div class="card">
        <?= empty_state(
            $status === 'upcoming' ? 'No upcoming bookings' : 'No bookings found yet.',
            $status === 'upcoming' ? 'Need something fixed? Find a trusted pro and book a time that suits you.' : 'Bookings in this category will appear here.',
            'calendar',
            '<a class="btn btn-primary" href="' . e(url('search.php')) . '">Find a professional</a>'
        ) ?>
    </div>
<?php endif; ?>

<p class="text-sm text-muted mt-3"><?= icon('info', 14) ?> Pending bookings can be cancelled anytime. Confirmed bookings can be cancelled up to <?= (int) config('booking.customer_cancel_hours') ?> hours before the start time.</p>

<?php partial('review-modal'); ?>
<?php dashboard_end(); ?>
