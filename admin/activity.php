<?php
require dirname(__DIR__) . '/includes/bootstrap.php';
require_login(ROLE_ADMIN);

$action = preg_replace('/[^a-z_]/', '', input('action'));
$where = $action ? 'a.action = ?' : '1=1';
$params = $action ? [$action] : [];
$actions = array_column(db_all('SELECT DISTINCT action FROM activity_logs ORDER BY action'), 'action');
$pg = paginate((int) db_value("SELECT COUNT(*) FROM activity_logs a WHERE $where", $params), 25, input_int('page', 1));
$logs = db_all(
    "SELECT a.*, u.first_name, u.last_name, u.avatar, u.role FROM activity_logs a LEFT JOIN users u ON u.id = a.user_id
     WHERE $where ORDER BY a.created_at DESC, a.id DESC LIMIT {$pg['per_page']} OFFSET {$pg['offset']}",
    $params
);

dashboard_start(['title' => 'Activity log', 'subtitle' => 'An audit trail of important actions on the platform.', 'active' => 'activity']);
?>
<form class="toolbar" method="get" action="<?= e(url('admin/activity.php')) ?>" data-auto-submit>
    <select class="select" name="action" aria-label="Action type">
        <option value="">All actions</option>
        <?php foreach ($actions as $a): ?><option value="<?= e($a) ?>"<?= selected($a, $action) ?>><?= e(str_replace('_', ' ', $a)) ?></option><?php endforeach; ?>
    </select>
</form>
<div class="card">
    <?php if ($logs): ?>
    <div class="table-wrap">
        <table class="table">
            <thead><tr><th>When</th><th>User</th><th>Action</th><th>Description</th><th>IP</th></tr></thead>
            <tbody>
            <?php foreach ($logs as $l): $name = $l['first_name'] ? $l['first_name'] . ' ' . $l['last_name'] : 'System'; ?>
                <tr>
                    <td class="nowrap text-sm"><?= e(format_date($l['created_at'], 'M j, H:i')) ?></td>
                    <td><div class="cell-user"><?= avatar_html($name, $l['avatar'], 'xs') ?><div><strong><?= e($name) ?></strong><span><?= e($l['role'] ?? '') ?></span></div></div></td>
                    <td><span class="badge badge-outline"><?= e(str_replace('_', ' ', $l['action'])) ?></span></td>
                    <td class="text-sm"><?= e($l['description']) ?></td>
                    <td class="text-xs text-muted"><?= e($l['ip_address']) ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php else: ?>
        <?= empty_state('No activity yet', 'Actions such as logins, bookings and approvals are recorded here.', 'activity') ?>
    <?php endif; ?>
</div>
<?= pagination_html($pg) ?>
<?php dashboard_end(); ?>
