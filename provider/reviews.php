<?php
require dirname(__DIR__) . '/includes/bootstrap.php';
$user = require_login(ROLE_PROVIDER);
$provider = current_provider();
$pid = (int) $provider['id'];

$filter = in_array(input('filter'), ['unanswered', 'low'], true) ? input('filter') : 'all';
$all = provider_reviews($pid, 200, 0, true);
$reviews = array_values(array_filter($all, fn ($r) => match ($filter) {
    'unanswered' => !$r['provider_reply'],
    'low'        => (int) $r['rating'] <= 3,
    default      => true,
}));
$breakdown = rating_breakdown($pid);
$unanswered = count(array_filter($all, fn ($r) => !$r['provider_reply']));

dashboard_start(['title' => 'Reviews', 'subtitle' => 'What customers say about ' . $provider['business_name'] . '.', 'active' => 'reviews']);
?>
<div class="stat-grid">
    <div class="stat"><div class="stat-icon tone-warning"><?= icon('star', 20) ?></div><div class="stat-label">Average rating</div><div class="stat-value"><?= (int) $provider['review_count'] ? number_format((float) $provider['avg_rating'], 2) : '—' ?></div></div>
    <div class="stat"><div class="stat-icon tone-brand"><?= icon('message', 20) ?></div><div class="stat-label">Published reviews</div><div class="stat-value"><?= (int) $provider['review_count'] ?></div></div>
    <div class="stat"><div class="stat-icon tone-success"><?= icon('award', 20) ?></div><div class="stat-label">5-star reviews</div><div class="stat-value"><?= $breakdown[5] ?></div></div>
    <div class="stat"><div class="stat-icon tone-info"><?= icon('reply', 20) ?></div><div class="stat-label">Awaiting your reply</div><div class="stat-value"><?= $unanswered ?></div></div>
</div>

<nav class="tabs">
    <a class="tab<?= $filter === 'all' ? ' is-active' : '' ?>" href="<?= e(url('provider/reviews.php')) ?>">All <span class="count"><?= count($all) ?></span></a>
    <a class="tab<?= $filter === 'unanswered' ? ' is-active' : '' ?>" href="<?= e(url('provider/reviews.php?filter=unanswered')) ?>">Unanswered <span class="count"><?= $unanswered ?></span></a>
    <a class="tab<?= $filter === 'low' ? ' is-active' : '' ?>" href="<?= e(url('provider/reviews.php?filter=low')) ?>">3 stars or less</a>
</nav>

<div class="card">
    <div class="card-body">
        <?php if ($reviews): ?>
            <?php foreach ($reviews as $r): partial('review-item', ['r' => $r, 'canReply' => true, 'canReport' => true, 'providerName' => $provider['business_name']]); endforeach; ?>
        <?php else: ?>
            <?= empty_state('No reviews here yet', 'Customers can review you after a booking is marked as completed.', 'star') ?>
        <?php endif; ?>
    </div>
</div>

<div class="modal" id="report-modal" role="dialog" aria-modal="true" aria-labelledby="report-title" hidden>
    <div class="modal-backdrop" data-modal-close></div>
    <form class="modal-dialog" method="post" action="<?= e(url('api/reviews.php')) ?>" data-ajax data-validate>
        <div class="modal-head"><h3 id="report-title">Report review</h3><button type="button" class="icon-btn" aria-label="Close" data-modal-close><?= icon('x', 20) ?></button></div>
        <div class="modal-body">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="report">
            <input type="hidden" name="review_id" value="">
            <p class="text-sm">If a review is fake, abusive or advertising, our moderators will check it. Honest negative feedback is not removed.</p>
            <label class="field"><span class="field-label">Reason</span>
                <select class="select" name="reason" required><?php foreach (report_reasons() as $k => $label): ?><option value="<?= e($k) ?>"><?= e($label) ?></option><?php endforeach; ?></select></label>
            <label class="field mb-0"><span class="field-label">Details</span><textarea class="textarea" name="details" maxlength="500" rows="3"></textarea></label>
        </div>
        <div class="modal-foot"><button type="button" class="btn btn-secondary" data-modal-close>Cancel</button><button type="submit" class="btn btn-danger"><?= icon('flag', 16) ?> Send report</button></div>
    </form>
</div>
<?php dashboard_end(); ?>
