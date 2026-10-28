<?php
require dirname(__DIR__) . '/includes/bootstrap.php';
$admin = require_login(ROLE_ADMIN);

if (is_post()) {
    require_csrf();
    $targetId = input_int('user_id');
    $target = db_one('SELECT id, role, first_name, last_name, status FROM users WHERE id = ?', [$targetId]);
    $action = input('action');
    if (!$target) {
        api_respond(false, 'User not found.', [], 404);
    }
    if ((int) $target['id'] === (int) $admin['id'] || $target['role'] === ROLE_ADMIN) {
        api_respond(false, 'Administrator accounts cannot be suspended here.');
    }
    $name = $target['first_name'] . ' ' . $target['last_name'];
    if ($action === 'suspend' && $target['status'] === 'active') {
        db_query("UPDATE users SET status = 'suspended' WHERE id = ?", [$targetId]);
        log_activity((int) $admin['id'], 'user_suspend', "Suspended user $name" . (input('note') ? ': ' . input('note') : ''), 'user', $targetId);
        api_respond(true, "$name has been suspended and signed out.");
    }
    if ($action === 'activate' && $target['status'] === 'suspended') {
        db_query("UPDATE users SET status = 'active' WHERE id = ?", [$targetId]);
        notify($targetId, 'account_activated', 'Your account is active again', 'You can sign in and use LemboFix as usual.', null);
        log_activity((int) $admin['id'], 'user_activate', "Reactivated user $name", 'user', $targetId);
        api_respond(true, "$name has been reactivated.");
    }
    api_respond(false, 'Nothing to change.');
}

$role = in_array(input('role'), ['customer', 'provider', 'admin'], true) ? input('role') : '';
$status = in_array(input('status'), ['active', 'suspended'], true) ? input('status') : '';
$q = mb_substr(input('q'), 0, 80);
$where = ['1=1'];
$params = [];
if ($role) { $where[] = 'u.role = ?'; $params[] = $role; }
if ($status) { $where[] = 'u.status = ?'; $params[] = $status; }
if ($q !== '') {
    $where[] = "(CONCAT(u.first_name, ' ', u.last_name) LIKE ? OR u.email LIKE ? OR p.business_name LIKE ?)";
    array_push($params, "%$q%", "%$q%", "%$q%");
}
$whereSql = implode(' AND ', $where);
$pg = paginate((int) db_value("SELECT COUNT(*) FROM users u LEFT JOIN provider_profiles p ON p.user_id = u.id WHERE $whereSql", $params), 15, input_int('page', 1));
$users = db_all(
    "SELECT u.*, p.business_name, p.slug,
            (SELECT COUNT(*) FROM bookings b WHERE b.customer_id = u.id) AS customer_bookings,
            (SELECT COUNT(*) FROM bookings b WHERE b.provider_id = p.id) AS provider_bookings
     FROM users u LEFT JOIN provider_profiles p ON p.user_id = u.id
     WHERE $whereSql ORDER BY u.created_at DESC LIMIT {$pg['per_page']} OFFSET {$pg['offset']}",
    $params
);

dashboard_start(['title' => 'Users', 'subtitle' => number_format($pg['total']) . ' accounts match your filters.', 'active' => 'users']);
?>
<form class="toolbar" method="get" action="<?= e(url('admin/users.php')) ?>" data-auto-submit>
    <div class="input-group"><?= icon('search', 18) ?><input class="input" type="search" name="q" value="<?= e($q) ?>" placeholder="Search name, email or business"></div>
    <select class="select" name="role" aria-label="Role">
        <option value="">All roles</option>
        <?php foreach (['customer' => 'Customers', 'provider' => 'Providers', 'admin' => 'Admins'] as $k => $label): ?><option value="<?= $k ?>"<?= selected($k, $role) ?>><?= $label ?></option><?php endforeach; ?>
    </select>
    <select class="select" name="status" aria-label="Status">
        <option value="">Any status</option>
        <option value="active"<?= selected('active', $status) ?>>Active</option>
        <option value="suspended"<?= selected('suspended', $status) ?>>Suspended</option>
    </select>
</form>

<div class="card">
    <?php if ($users): ?>
    <div class="table-wrap">
        <table class="table">
            <thead><tr><th>User</th><th>Role</th><th>City</th><th>Bookings</th><th>Joined</th><th>Last login</th><th>Status</th><th class="right">Actions</th></tr></thead>
            <tbody>
            <?php foreach ($users as $u): $name = $u['first_name'] . ' ' . $u['last_name']; ?>
                <tr>
                    <td><div class="cell-user"><?= avatar_html($name, $u['avatar'], 'sm') ?><div><strong><?= e($name) ?></strong><span><?= e($u['email']) ?></span></div></div></td>
                    <td><span class="badge badge-outline"><?= e(ucfirst($u['role'])) ?></span><?php if ($u['business_name']): ?><br><a class="text-xs link" href="<?= e(url('admin/providers.php?q=' . urlencode($u['business_name']))) ?>"><?= e($u['business_name']) ?></a><?php endif; ?></td>
                    <td><?= e($u['city'] ?: '—') ?></td>
                    <td><?= (int) ($u['role'] === 'provider' ? $u['provider_bookings'] : $u['customer_bookings']) ?></td>
                    <td class="nowrap"><?= e(format_date($u['created_at'], 'M j, Y')) ?></td>
                    <td class="nowrap text-sm"><?= $u['last_login_at'] ? e(time_ago($u['last_login_at'])) : '<span class="text-muted">Never</span>' ?></td>
                    <td><?= status_badge($u['status']) ?></td>
                    <td>
                        <div class="actions">
                            <?php if ($u['role'] !== 'admin'): ?>
                                <form method="post" action="<?= e(url('admin/users.php')) ?>" data-ajax data-on-success="reload">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="user_id" value="<?= (int) $u['id'] ?>">
                                    <?php if ($u['status'] === 'active'): ?>
                                        <input type="hidden" name="action" value="suspend">
                                        <button class="btn btn-xs btn-danger-soft" type="submit" data-confirm="Suspend <?= e($name) ?>?" data-confirm-text="They will be signed out immediately and cannot log in until reactivated." data-confirm-ok="Suspend" data-confirm-note="Internal note (optional)"><?= icon('lock', 14) ?> Suspend</button>
                                    <?php else: ?>
                                        <input type="hidden" name="action" value="activate">
                                        <button class="btn btn-xs btn-success-soft" type="submit" data-confirm="Reactivate <?= e($name) ?>?" data-confirm-tone="success" data-confirm-ok="Reactivate"><?= icon('check', 14) ?> Activate</button>
                                    <?php endif; ?>
                                </form>
                            <?php else: ?>
                                <span class="text-xs text-muted">Protected</span>
                            <?php endif; ?>
                        </div>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php else: ?>
        <?= empty_state('No users found', 'Try a different search or clear the filters.', 'users') ?>
    <?php endif; ?>
</div>
<?= pagination_html($pg) ?>
<?php dashboard_end(); ?>
