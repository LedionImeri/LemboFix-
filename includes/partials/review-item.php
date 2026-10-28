<?php
/**
 * @var array $r              review row from provider_reviews()
 * @var bool  $canReply       provider owner view
 * @var bool  $canReport
 * @var string|null $providerName
 */
$canReply = $canReply ?? false;
$canReport = $canReport ?? false;
$customerName = $r['first_name'] . ' ' . mb_substr($r['last_name'], 0, 1) . '.';
?>
<div class="review" id="review-<?= (int) $r['id'] ?>">
    <div class="review-head">
        <?= avatar_html($r['first_name'] . ' ' . $r['last_name'], $r['avatar'], 'md') ?>
        <div>
            <strong><?= e($customerName) ?></strong>
            <span><?= stars_html((float) $r['rating'], 'sm') ?> · <?= e(time_ago($r['created_at'])) ?><?= $r['city'] ? ' · ' . e($r['city']) : '' ?></span>
        </div>
        <div class="review-actions">
            <?php if (($r['status'] ?? 'published') === 'hidden'): ?><span class="badge badge-neutral">Hidden by admin</span><?php endif; ?>
            <?php if ($canReport): ?>
                <button type="button" class="btn btn-xs btn-ghost" data-modal-open="#report-modal" data-fill-review-id="<?= (int) $r['id'] ?>" aria-label="Report review">
                    <?= icon('flag', 14) ?><span class="hide-mobile">Report</span>
                </button>
            <?php endif; ?>
        </div>
    </div>
    <p><?= nl2br(e($r['comment'])) ?></p>
    <?php if (!empty($r['service_titles'])): ?>
        <div class="review-service"><?= icon('check-circle', 13) ?> Verified booking · <?= e($r['service_titles']) ?></div>
    <?php endif; ?>
    <?php if ($r['provider_reply']): ?>
        <div class="review-reply">
            <strong><?= icon('reply', 14) ?> Response from <?= e($providerName ?? 'the provider') ?></strong>
            <p><?= nl2br(e($r['provider_reply'])) ?></p>
        </div>
    <?php elseif ($canReply): ?>
        <form class="mt-2" method="post" action="<?= e(url('api/reviews.php')) ?>" data-ajax data-on-success="reload" data-validate>
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="reply">
            <input type="hidden" name="review_id" value="<?= (int) $r['id'] ?>">
            <label class="field mb-1">
                <span class="sr-only">Your reply</span>
                <textarea class="textarea" name="reply" rows="2" minlength="5" maxlength="600" required placeholder="Write a public reply to thank the customer or respond to feedback…"></textarea>
            </label>
            <button type="submit" class="btn btn-sm btn-secondary"><?= icon('reply', 16) ?> Post reply</button>
        </form>
    <?php endif; ?>
</div>
