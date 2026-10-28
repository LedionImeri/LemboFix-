<?php
require dirname(__DIR__) . '/includes/bootstrap.php';
$admin = require_login(ROLE_ADMIN);

if (is_post()) {
    require_csrf();
    $review = db_one('SELECT r.id, r.status, b.provider_id FROM reviews r JOIN bookings b ON b.id = r.booking_id WHERE r.id = ?', [input_int('review_id')]);
    if (!$review) {
        api_respond(false, 'Review not found.', [], 404);
    }
    switch (input('action')) {
        case 'hide':
        case 'publish':
            $newStatus = input('action') === 'hide' ? 'hidden' : 'published';
            db_query('UPDATE reviews SET status = ? WHERE id = ?', [$newStatus, $review['id']]);
            if ($newStatus === 'hidden') {
                db_query("UPDATE reports SET status = 'resolved', resolved_by = ?, resolved_at = NOW(), admin_note = 'Review hidden' WHERE review_id = ? AND status = 'open'", [$admin['id'], $review['id']]);
            }
            refresh_provider_rating((int) $review['provider_id']);
            log_activity((int) $admin['id'], 'review_' . input('action'), ($newStatus === 'hidden' ? 'Hid' : 'Republished') . ' review #' . $review['id'], 'review', (int) $review['id']);
            api_respond(true, $newStatus === 'hidden' ? 'Review hidden from the public profile.' : 'Review is public again.');
        case 'delete':
            db_query('DELETE FROM reviews WHERE id = ?', [$review['id']]);
            refresh_provider_rating((int) $review['provider_id']);
            log_activity((int) $admin['id'], 'review_delete', 'Deleted review #' . $review['id'], 'review', (int) $review['id']);
            api_respond(true, 'Review deleted permanently.');
    }
    api_respond(false, 'Unknown action.', [], 400);
}

$filter = in_array(input('filter'), ['reported', 'hidden', 'low'], true) ? input('filter') : '';
$where = match ($filter) {
    'reported' => "EXISTS (SELECT 1 FROM reports rp WHERE rp.review_id = r.id AND rp.status = 'open')",
    'hidden'   => "r.status = 'hidden'",
    'low'      => 'r.rating <= 2',
    default    => '1=1',
};
$pg = paginate((int) db_value("SELECT COUNT(*) FROM reviews r WHERE $where"), 15, input_int('page', 1));
$reviews = db_all(
    "SELECT r.*, p.business_name, p.slug, u.first_name, u.last_name, u.avatar,
            (SELECT COUNT(*) FROM reports rp WHERE rp.review_id = r.id AND rp.status = 'open') AS open_reports
     FROM reviews r JOIN bookings b ON b.id = r.booking_id JOIN provider_profiles p ON p.id = b.provider_id JOIN users u ON u.id = b.customer_id
     WHERE $where ORDER BY open_reports DESC, r.created_at DESC LIMIT {$pg['per_page']} OFFSET {$pg['offset']}"
);
$reportedCount = (int) db_value("SELECT COUNT(DISTINCT review_id) FROM reports WHERE status = 'open' AND review_id IS NOT NULL");

dashboard_start(['title' => 'Reviews', 'subtitle' => 'Moderate customer reviews. Hidden reviews don\'t count towards ratings.', 'active' => 'reviews']);
?>
<nav class="tabs">
    <?php foreach (['' => 'All', 'reported' => 'Reported', 'hidden' => 'Hidden', 'low' => '1–2 stars'] as $key => $label): ?>
        <a class="tab<?= $filter === $key ? ' is-active' : '' ?>" href="<?= e(url('admin/reviews.php' . ($key ? '?filter=' . $key : ''))) ?>"><?= $label ?><?= $key === 'reported' ? ' <span class="count">' . $reportedCount . '</span>' : '' ?></a>
    <?php endforeach; ?>
</nav>
<div class="card">
    <div class="card-body">
        <?php if ($reviews): foreach ($reviews as $r): $name = $r['first_name'] . ' ' . $r['last_name']; ?>
            <div class="review">
                <div class="review-head">
                    <?= avatar_html($name, $r['avatar'], 'md') ?>
                    <div>
                        <strong><?= e($name) ?> → <a class="link" href="<?= e(url('provider.php?slug=' . $r['slug'])) ?>"><?= e($r['business_name']) ?></a></strong>
                        <span><?= stars_html((float) $r['rating'], 'sm') ?> · <?= e(format_date($r['created_at'], 'M j, Y')) ?></span>
                    </div>
                    <div class="review-actions">
                        <?php if ($r['open_reports']): ?><span class="badge badge-danger"><?= icon('flag', 12) ?> <?= plural((int) $r['open_reports'], 'report') ?></span><?php endif; ?>
                        <?= status_badge($r['status']) ?>
                    </div>
                </div>
                <p><?= nl2br(e($r['comment'])) ?></p>
                <?php if ($r['provider_reply']): ?><div class="review-reply"><strong><?= icon('reply', 14) ?> Provider reply</strong><p><?= e($r['provider_reply']) ?></p></div><?php endif; ?>
                <div class="form-actions mt-2">
                    <form method="post" action="<?= e(url('admin/reviews.php')) ?>" data-ajax data-on-success="reload">
                        <?= csrf_field() ?><input type="hidden" name="review_id" value="<?= (int) $r['id'] ?>">
                        <?php if ($r['status'] === 'published'): ?>
                            <input type="hidden" name="action" value="hide">
                            <button class="btn btn-xs btn-secondary" type="submit" data-confirm="Hide this review?" data-confirm-text="It will disappear from the provider's profile and rating." data-confirm-ok="Hide review"><?= icon('eye-off', 14) ?> Hide</button>
                        <?php else: ?>
                            <input type="hidden" name="action" value="publish">
                            <button class="btn btn-xs btn-success-soft" type="submit"><?= icon('eye', 14) ?> Publish again</button>
                        <?php endif; ?>
                    </form>
                    <form method="post" action="<?= e(url('admin/reviews.php')) ?>" data-ajax data-on-success="reload">
                        <?= csrf_field() ?><input type="hidden" name="review_id" value="<?= (int) $r['id'] ?>"><input type="hidden" name="action" value="delete">
                        <button class="btn btn-xs btn-danger-soft" type="submit" data-confirm="Delete this review permanently?" data-confirm-text="This cannot be undone. The customer will be able to review the booking again." data-confirm-ok="Delete"><?= icon('trash', 14) ?> Delete</button>
                    </form>
                </div>
            </div>
        <?php endforeach; else: ?>
            <?= empty_state('No reviews here', $filter === 'reported' ? 'Nothing has been reported. 🎉' : 'Reviews will appear after completed bookings.', 'star') ?>
        <?php endif; ?>
    </div>
</div>
<?= pagination_html($pg) ?>
<?php dashboard_end(); ?>
