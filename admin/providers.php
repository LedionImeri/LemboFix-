<?php
require dirname(__DIR__) . '/includes/bootstrap.php';
$admin = require_login(ROLE_ADMIN);

if (is_post()) {
    require_csrf();
    $provider = db_one('SELECT p.*, u.status AS user_status FROM provider_profiles p JOIN users u ON u.id = p.user_id WHERE p.id = ?', [input_int('provider_id')]);
    if (!$provider) {
        api_respond(false, 'Provider not found.', [], 404);
    }
    $pid = (int) $provider['id'];
    $name = $provider['business_name'];
    switch (input('action')) {
        case 'approve':
            db_query("UPDATE provider_profiles SET approval_status = 'approved', rejection_reason = NULL, approved_at = NOW() WHERE id = ?", [$pid]);
            notify((int) $provider['user_id'], 'provider_approved', 'Your profile is live ✓', 'Great news — LemboFix approved your profile. Customers can now find and book you.', 'provider/dashboard.php');
            log_activity((int) $admin['id'], 'provider_approve', "Approved provider $name", 'provider', $pid);
            api_respond(true, "$name is approved and now visible to customers.");
        case 'reject':
            $reason = mb_substr(input('note'), 0, 255);
            if (mb_strlen($reason) < 5) {
                api_respond(false, 'Please give the provider a short reason.');
            }
            db_query("UPDATE provider_profiles SET approval_status = 'rejected', rejection_reason = ?, is_verified = 0 WHERE id = ?", [$reason, $pid]);
            notify((int) $provider['user_id'], 'provider_rejected', 'Profile not approved', $reason, 'provider/profile.php');
            log_activity((int) $admin['id'], 'provider_reject', "Rejected provider $name: $reason", 'provider', $pid);
            api_respond(true, "$name was rejected and notified.");
        case 'verify':
            $verified = $provider['is_verified'] ? 0 : 1;
            db_query('UPDATE provider_profiles SET is_verified = ? WHERE id = ?', [$verified, $pid]);
            if ($verified) {
                notify((int) $provider['user_id'], 'provider_verified', 'You are now a Verified Professional', 'A verified badge now appears on your profile and in search.', 'provider/dashboard.php');
            }
            log_activity((int) $admin['id'], 'provider_verify', ($verified ? 'Verified' : 'Removed verification from') . " $name", 'provider', $pid);
            api_respond(true, $verified ? "$name is now verified." : "Verification removed from $name.");
        case 'suspend':
        case 'activate':
            $newStatus = input('action') === 'suspend' ? 'suspended' : 'active';
            db_query('UPDATE users SET status = ? WHERE id = ?', [$newStatus, $provider['user_id']]);
            log_activity((int) $admin['id'], 'provider_' . input('action'), ucfirst(input('action')) . "ed provider $name", 'provider', $pid);
            api_respond(true, $newStatus === 'suspended' ? "$name is suspended and hidden from search." : "$name is active again.");
    }
    api_respond(false, 'Unknown action.', [], 400);
}

$status = in_array(input('status'), ['pending', 'approved', 'rejected'], true) ? input('status') : '';
$q = mb_substr(input('q'), 0, 80);
$where = ['1=1'];
$params = [];
if ($status) { $where[] = 'p.approval_status = ?'; $params[] = $status; }
if ($q !== '') {
    $where[] = "(p.business_name LIKE ? OR CONCAT(u.first_name, ' ', u.last_name) LIKE ? OR p.city LIKE ?)";
    array_push($params, "%$q%", "%$q%", "%$q%");
}
$whereSql = implode(' AND ', $where);
$counts = array_column(db_all('SELECT approval_status, COUNT(*) AS total FROM provider_profiles GROUP BY approval_status'), 'total', 'approval_status');
$pg = paginate((int) db_value("SELECT COUNT(*) FROM provider_profiles p JOIN users u ON u.id = p.user_id WHERE $whereSql", $params), 12, input_int('page', 1));
$providers = db_all(
    "SELECT p.*, u.first_name, u.last_name, u.email, u.phone, u.avatar, u.status AS user_status,
            (SELECT COUNT(*) FROM provider_services ps WHERE ps.provider_id = p.id) AS services,
            (SELECT COUNT(*) FROM bookings b WHERE b.provider_id = p.id AND b.status = 'completed') AS completed,
            (SELECT GROUP_CONCAT(c.name SEPARATOR ', ') FROM provider_categories pc JOIN categories c ON c.id = pc.category_id WHERE pc.provider_id = p.id) AS category_names
     FROM provider_profiles p JOIN users u ON u.id = p.user_id
     WHERE $whereSql
     ORDER BY FIELD(p.approval_status, 'pending', 'approved', 'rejected'), p.created_at DESC
     LIMIT {$pg['per_page']} OFFSET {$pg['offset']}",
    $params
);

dashboard_start(['title' => 'Providers', 'subtitle' => 'Approve new professionals and manage verification.', 'active' => 'providers']);
?>
<nav class="tabs">
    <?php foreach (['' => 'All', 'pending' => 'Pending', 'approved' => 'Approved', 'rejected' => 'Rejected'] as $key => $label): ?>
        <a class="tab<?= $status === $key ? ' is-active' : '' ?>" href="<?= e(url('admin/providers.php' . ($key ? '?status=' . $key : ''))) ?>"><?= $label ?>
            <span class="count"><?= $key ? (int) ($counts[$key] ?? 0) : array_sum($counts) ?></span></a>
    <?php endforeach; ?>
</nav>
<form class="toolbar" method="get" action="<?= e(url('admin/providers.php')) ?>" data-auto-submit>
    <input type="hidden" name="status" value="<?= e($status) ?>">
    <div class="input-group"><?= icon('search', 18) ?><input class="input" type="search" name="q" value="<?= e($q) ?>" placeholder="Search business, owner or city"></div>
</form>

<?php if ($providers): ?>
<div class="card">
    <div class="table-wrap">
        <table class="table">
            <thead><tr><th>Provider</th><th>Categories</th><th>City</th><th>Rating</th><th>Jobs</th><th>Status</th><th class="right">Actions</th></tr></thead>
            <tbody>
            <?php foreach ($providers as $p): ?>
                <tr>
                    <td>
                        <div class="cell-user"><?= avatar_html($p['business_name'], $p['avatar'], 'sm') ?>
                            <div><strong><?= e($p['business_name']) ?> <?= $p['is_verified'] ? icon('badge-check', 14, 'text-success') : '' ?></strong>
                                <span><?= e($p['first_name'] . ' ' . $p['last_name']) ?> · <?= e($p['email']) ?></span></div></div>
                        <?php if ($p['approval_status'] === 'rejected' && $p['rejection_reason']): ?><div class="text-xs text-danger mt-1"><?= e($p['rejection_reason']) ?></div><?php endif; ?>
                    </td>
                    <td class="text-sm"><?= e($p['category_names'] ?: '—') ?><br><span class="text-xs text-muted"><?= plural((int) $p['services'], 'service') ?> · <?= (int) $p['experience_years'] ?> yrs exp.</span></td>
                    <td><?= e($p['city']) ?></td>
                    <td class="nowrap"><?= (int) $p['review_count'] ? '★ ' . number_format((float) $p['avg_rating'], 1) . ' <span class="text-muted text-xs">(' . (int) $p['review_count'] . ')</span>' : '—' ?></td>
                    <td><?= (int) $p['completed'] ?></td>
                    <td><?= status_badge($p['approval_status']) ?><?= $p['user_status'] === 'suspended' ? '<br>' . status_badge('suspended') : '' ?></td>
                    <td>
                        <div class="actions">
                            <a class="btn btn-xs btn-ghost" href="<?= e(url('provider.php?slug=' . $p['slug'])) ?>" target="_blank" rel="noopener"><?= icon('eye', 14) ?> View</a>
                            <?php
                            $buttons = [];
                            if ($p['approval_status'] !== 'approved') {
                                $buttons[] = ['approve', 'Approve', 'btn-success', 'check', 'Approve ' . $p['business_name'] . '?', 'The profile becomes public and bookable immediately.', 'success', ''];
                            }
                            if ($p['approval_status'] !== 'rejected') {
                                $buttons[] = ['reject', 'Reject', 'btn-danger-soft', 'x', 'Reject ' . $p['business_name'] . '?', 'The provider will see your reason and the profile will be hidden.', 'danger', 'data-confirm-note="Reason (shared with the provider)" data-confirm-note-required'];
                            }
                            if ($p['approval_status'] === 'approved') {
                                $buttons[] = ['verify', $p['is_verified'] ? 'Unverify' : 'Verify', 'btn-secondary', 'badge-check', ($p['is_verified'] ? 'Remove verification from ' : 'Verify ') . $p['business_name'] . '?', 'Verified providers show a badge on their profile and rank higher in search.', 'brand', ''];
                            }
                            $buttons[] = $p['user_status'] === 'active'
                                ? ['suspend', 'Suspend', 'btn-danger-soft', 'lock', 'Suspend ' . $p['business_name'] . '?', 'The account is signed out and hidden from search until reactivated.', 'danger', '']
                                : ['activate', 'Activate', 'btn-success-soft', 'refresh', 'Reactivate ' . $p['business_name'] . '?', '', 'success', ''];
                            foreach ($buttons as [$action, $label, $class, $ico, $title, $text, $tone, $extra]): ?>
                                <form method="post" action="<?= e(url('admin/providers.php')) ?>" data-ajax data-on-success="reload">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="provider_id" value="<?= (int) $p['id'] ?>">
                                    <input type="hidden" name="action" value="<?= e($action) ?>">
                                    <button type="submit" class="btn btn-xs <?= $class ?>" data-confirm="<?= e($title) ?>" data-confirm-text="<?= e($text) ?>" data-confirm-ok="<?= e($label) ?>" data-confirm-tone="<?= $tone ?>" <?= $extra ?>><?= icon($ico, 14) ?> <?= e($label) ?></button>
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
    <div class="card"><?= empty_state($status === 'pending' ? 'No providers waiting for approval' : 'No providers found', 'New registrations will appear here for review.', 'briefcase') ?></div>
<?php endif; ?>
<?php dashboard_end(); ?>
