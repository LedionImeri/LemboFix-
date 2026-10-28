<?php
require dirname(__DIR__) . '/includes/bootstrap.php';
$user = require_login(ROLE_CUSTOMER);

$reviews = db_all(
    "SELECT r.*, p.business_name, p.slug, pu.avatar AS provider_avatar, b.booking_date,
            (SELECT GROUP_CONCAT(bi.title SEPARATOR ', ') FROM booking_items bi WHERE bi.booking_id = b.id) AS service_titles
     FROM reviews r
     JOIN bookings b ON b.id = r.booking_id
     JOIN provider_profiles p ON p.id = b.provider_id
     JOIN users pu ON pu.id = p.user_id
     WHERE b.customer_id = ?
     ORDER BY r.created_at DESC",
    [$user['id']]
);
$pendingCount = count_bookings(['b.customer_id = ?', "b.status = 'completed'", 'NOT EXISTS (SELECT 1 FROM reviews r WHERE r.booking_id = b.id)'], [$user['id']]);

dashboard_start(['title' => 'My reviews', 'subtitle' => 'Reviews you wrote after completed bookings.', 'active' => 'reviews']);
?>
<?php if ($pendingCount): ?>
    <div class="alert alert-info"><?= icon('star', 20) ?><div><?= plural($pendingCount, 'completed booking') ?> still waiting for your review. <a class="link" href="<?= e(url('customer/bookings.php?status=completed')) ?>">Review now</a></div></div>
<?php endif; ?>
<?php if ($reviews): ?>
    <div class="card"><div class="card-body">
        <?php foreach ($reviews as $r): ?>
            <div class="review">
                <div class="review-head">
                    <?= avatar_html($r['business_name'], $r['provider_avatar'], 'md') ?>
                    <div>
                        <strong><a href="<?= e(url('provider.php?slug=' . $r['slug'])) ?>"><?= e($r['business_name']) ?></a></strong>
                        <span><?= stars_html((float) $r['rating'], 'sm') ?> · <?= e(format_date($r['created_at'], 'M j, Y')) ?></span>
                    </div>
                    <div class="review-actions"><?= status_badge($r['status']) ?></div>
                </div>
                <p><?= nl2br(e($r['comment'])) ?></p>
                <div class="review-service"><?= icon('check-circle', 13) ?> <?= e($r['service_titles']) ?> · <?= e(format_date($r['booking_date'], 'M j, Y')) ?></div>
                <?php if ($r['provider_reply']): ?>
                    <div class="review-reply"><strong><?= icon('reply', 14) ?> Response from <?= e($r['business_name']) ?></strong><p><?= nl2br(e($r['provider_reply'])) ?></p></div>
                <?php endif; ?>
            </div>
        <?php endforeach; ?>
    </div></div>
<?php else: ?>
    <div class="card"><?= empty_state('No reviews yet', 'After a booking is completed you can rate the professional and help others choose.', 'star') ?></div>
<?php endif; ?>
<?php dashboard_end(); ?>
