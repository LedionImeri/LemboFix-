<?php
require __DIR__ . '/includes/bootstrap.php';
$user = require_login();
$uid = (int) $user['id'];

$filter = input('filter') === 'unread' ? 'unread' : 'all';
$where = 'user_id = ?' . ($filter === 'unread' ? ' AND is_read = 0' : '');
$pg = paginate((int) db_value("SELECT COUNT(*) FROM notifications WHERE $where", [$uid]), 15, input_int('page', 1));
$items = db_all("SELECT * FROM notifications WHERE $where ORDER BY created_at DESC, id DESC LIMIT {$pg['per_page']} OFFSET {$pg['offset']}", [$uid]);
$unread = unread_notification_count($uid);

dashboard_start([
    'title'    => 'Notifications',
    'subtitle' => $unread ? plural($unread, 'unread notification') : 'You\'re all caught up.',
    'active'   => 'notifications',
    'actions'  => $unread ? '<form method="post" action="' . e(url('api/notifications.php')) . '" data-ajax data-on-success="reload">' . csrf_field()
        . '<input type="hidden" name="action" value="read_all"><button class="btn btn-secondary" type="submit">' . icon('check', 18) . ' Mark all as read</button></form>' : '',
]);
?>
<nav class="tabs">
    <a class="tab<?= $filter === 'all' ? ' is-active' : '' ?>" href="<?= e(url('notifications.php')) ?>">All</a>
    <a class="tab<?= $filter === 'unread' ? ' is-active' : '' ?>" href="<?= e(url('notifications.php?filter=unread')) ?>">Unread <span class="count"><?= $unread ?></span></a>
</nav>
<div class="card">
    <?php if ($items): ?>
        <ul class="list-plain">
            <?php foreach ($items as $n): ?>
                <li class="notif-item<?= $n['is_read'] ? '' : ' is-unread' ?>" data-removable style="display:flex">
                    <span class="notif-ico"><?= icon(notification_icon($n['type']), 18) ?></span>
                    <div class="notif-text">
                        <strong><?= e($n['title']) ?></strong>
                        <p><?= e($n['message']) ?></p>
                        <time datetime="<?= e($n['created_at']) ?>"><?= e(time_ago($n['created_at'])) ?></time>
                    </div>
                    <div class="flex gap-1 items-center">
                        <?php if ($n['link']): ?><a class="btn btn-xs btn-secondary" href="<?= e(url($n['link'])) ?>">Open</a><?php endif; ?>
                        <form method="post" action="<?= e(url('api/notifications.php')) ?>" data-ajax data-on-success="remove">
                            <?= csrf_field() ?>
                            <input type="hidden" name="action" value="delete">
                            <input type="hidden" name="id" value="<?= (int) $n['id'] ?>">
                            <button class="icon-btn" type="submit" aria-label="Delete notification"><?= icon('trash', 16) ?></button>
                        </form>
                    </div>
                </li>
            <?php endforeach; ?>
        </ul>
    <?php else: ?>
        <?= empty_state($filter === 'unread' ? 'No unread notifications' : 'No notifications yet', 'Updates about bookings, reviews and your account will appear here.', 'bell') ?>
    <?php endif; ?>
</div>
<?= pagination_html($pg) ?>
<?php
// Opening the page marks the visible notifications as read
if ($items) {
    $ids = array_column(array_filter($items, fn ($n) => !$n['is_read']), 'id');
    if ($ids) {
        db_query('UPDATE notifications SET is_read = 1 WHERE user_id = ? AND id IN (' . implode(',', array_map('intval', $ids)) . ')', [$uid]);
    }
}
dashboard_end();
