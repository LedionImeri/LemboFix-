<?php
require dirname(__DIR__) . '/includes/bootstrap.php';
$admin = require_login(ROLE_ADMIN);

if (is_post()) {
    require_csrf();
    $report = db_one('SELECT * FROM reports WHERE id = ?', [input_int('report_id')]);
    if (!$report || $report['status'] !== 'open') {
        api_respond(false, 'This report was already handled.');
    }
    $note = mb_substr(input('note'), 0, 255) ?: null;
    switch (input('action')) {
        case 'hide_review':
            if (!$report['review_id']) {
                api_respond(false, 'This report is not about a review.');
            }
            $providerId = (int) db_value('SELECT b.provider_id FROM reviews r JOIN bookings b ON b.id = r.booking_id WHERE r.id = ?', [$report['review_id']]);
            db_query("UPDATE reviews SET status = 'hidden' WHERE id = ?", [$report['review_id']]);
            refresh_provider_rating($providerId);
            db_query("UPDATE reports SET status = 'resolved', admin_note = ?, resolved_by = ?, resolved_at = NOW() WHERE review_id = ? AND status = 'open'",
                [$note ?? 'Review hidden', $admin['id'], $report['review_id']]);
            if ($report['reporter_id']) {
                notify((int) $report['reporter_id'], 'report_resolved', 'Report resolved', 'Thanks — the review you reported has been removed.', null);
            }
            log_activity((int) $admin['id'], 'report_resolve', 'Hid reported review #' . $report['review_id'], 'report', (int) $report['id']);
            api_respond(true, 'Review hidden and report resolved.');
        case 'resolve':
        case 'dismiss':
            $status = input('action') === 'resolve' ? 'resolved' : 'dismissed';
            db_query('UPDATE reports SET status = ?, admin_note = ?, resolved_by = ?, resolved_at = NOW() WHERE id = ?', [$status, $note, $admin['id'], $report['id']]);
            log_activity((int) $admin['id'], 'report_' . $status, ucfirst($status) . ' report #' . $report['id'], 'report', (int) $report['id']);
            api_respond(true, 'Report ' . $status . '.');
    }
    api_respond(false, 'Unknown action.', [], 400);
}

$status = in_array(input('status'), ['open', 'resolved', 'dismissed'], true) ? input('status') : 'open';
$counts = array_column(db_all('SELECT status, COUNT(*) AS total FROM reports GROUP BY status'), 'total', 'status');
$reports = db_all(
    "SELECT rp.*, CONCAT(ru.first_name, ' ', ru.last_name) AS reporter_name, ru.role AS reporter_role,
            r.comment AS review_comment, r.rating AS review_rating, r.status AS review_status,
            COALESCE(rp_p.business_name, rv_p.business_name) AS business_name, COALESCE(rp_p.slug, rv_p.slug) AS slug,
            CONCAT(au.first_name, ' ', au.last_name) AS resolver_name
     FROM reports rp
     LEFT JOIN users ru ON ru.id = rp.reporter_id
     LEFT JOIN reviews r ON r.id = rp.review_id
     LEFT JOIN bookings b ON b.id = r.booking_id
     LEFT JOIN provider_profiles rv_p ON rv_p.id = b.provider_id
     LEFT JOIN provider_profiles rp_p ON rp_p.id = rp.provider_id
     LEFT JOIN users au ON au.id = rp.resolved_by
     WHERE rp.status = ? ORDER BY rp.created_at DESC",
    [$status]
);

dashboard_start(['title' => 'Reported content', 'subtitle' => 'Reports from customers and providers about reviews and profiles.', 'active' => 'reports']);
?>
<nav class="tabs">
    <?php foreach (['open' => 'Open', 'resolved' => 'Resolved', 'dismissed' => 'Dismissed'] as $key => $label): ?>
        <a class="tab<?= $status === $key ? ' is-active' : '' ?>" href="<?= e(url('admin/reports.php?status=' . $key)) ?>"><?= $label ?> <span class="count"><?= (int) ($counts[$key] ?? 0) ?></span></a>
    <?php endforeach; ?>
</nav>
<?php if ($reports): ?>
    <div class="booking-list">
        <?php foreach ($reports as $rp): ?>
            <article class="card">
                <div class="card-body">
                    <div class="flex justify-between items-center wrap gap-1 mb-1">
                        <div class="flex items-center gap-1 wrap">
                            <span class="badge badge-danger"><?= icon('flag', 12) ?> <?= e(report_reasons()[$rp['reason']] ?? $rp['reason']) ?></span>
                            <span class="badge badge-outline"><?= $rp['review_id'] ? 'Review' : 'Provider profile' ?></span>
                            <strong><a class="link" href="<?= e(url('provider.php?slug=' . $rp['slug'])) ?>"><?= e($rp['business_name']) ?></a></strong>
                        </div>
                        <span class="text-sm text-muted">Reported by <?= e($rp['reporter_name'] ?? 'deleted user') ?> (<?= e($rp['reporter_role'] ?? '—') ?>) · <?= e(time_ago($rp['created_at'])) ?></span>
                    </div>
                    <?php if ($rp['details']): ?><p class="text-sm"><strong>Details:</strong> <?= e($rp['details']) ?></p><?php endif; ?>
                    <?php if ($rp['review_id']): ?>
                        <div class="review-reply" style="border-left-color:var(--danger-600)">
                            <strong><?= stars_html((float) $rp['review_rating'], 'sm') ?> Reported review <?= $rp['review_status'] === 'hidden' ? '(hidden)' : '' ?></strong>
                            <p><?= e($rp['review_comment']) ?></p>
                        </div>
                    <?php endif; ?>
                    <?php if ($status !== 'open'): ?>
                        <p class="text-sm text-muted mt-2 mb-0"><?= e(ucfirst($rp['status'])) ?> by <?= e($rp['resolver_name'] ?? 'admin') ?> · <?= e(time_ago((string) $rp['resolved_at'])) ?><?= $rp['admin_note'] ? ' — ' . e($rp['admin_note']) : '' ?></p>
                    <?php else: ?>
                        <div class="form-actions mt-2">
                            <?php
                            $actions = [];
                            if ($rp['review_id'] && $rp['review_status'] === 'published') {
                                $actions[] = ['hide_review', 'Hide review', 'btn-danger', 'eye-off', 'danger'];
                            }
                            $actions[] = ['resolve', 'Mark resolved', 'btn-success-soft', 'check', 'success'];
                            $actions[] = ['dismiss', 'Dismiss', 'btn-secondary', 'x', 'brand'];
                            foreach ($actions as [$action, $label, $class, $ico, $tone]): ?>
                                <form method="post" action="<?= e(url('admin/reports.php')) ?>" data-ajax data-on-success="reload">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="report_id" value="<?= (int) $rp['id'] ?>">
                                    <input type="hidden" name="action" value="<?= $action ?>">
                                    <button type="submit" class="btn btn-sm <?= $class ?>" data-confirm="<?= e($label) ?>?" data-confirm-ok="<?= e($label) ?>" data-confirm-tone="<?= $tone ?>" data-confirm-note="Admin note (optional)"><?= icon($ico, 16) ?> <?= e($label) ?></button>
                                </form>
                            <?php endforeach; ?>
                            <?php if (!$rp['review_id']): ?><a class="btn btn-sm btn-ghost" href="<?= e(url('admin/providers.php?q=' . urlencode((string) $rp['business_name']))) ?>"><?= icon('briefcase', 16) ?> Open in providers</a><?php endif; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </article>
        <?php endforeach; ?>
    </div>
<?php else: ?>
    <div class="card"><?= empty_state($status === 'open' ? 'No open reports' : 'Nothing here', 'Reported reviews and profiles will show up here for moderation.', 'flag') ?></div>
<?php endif; ?>
<?php dashboard_end(); ?>
