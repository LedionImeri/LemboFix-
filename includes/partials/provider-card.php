<?php
/** @var array $p  row from search_providers() */
$name = $p['first_name'] . ' ' . $p['last_name'];
$removable = $removable ?? false;
?>
<article class="provider-card"<?= $removable ? ' data-removable' : '' ?>>
    <div class="provider-card-cover">
        <span class="cover-icon"><?= icon($p['category_icon'] ?: 'wrench', 44) ?></span>
    </div>
    <?php if (!has_role(ROLE_PROVIDER, ROLE_ADMIN)): ?>
        <button type="button" class="fav-btn<?= !empty($p['is_favorite']) ? ' is-active' : '' ?>"
                data-favorite="<?= (int) $p['id'] ?>" aria-pressed="<?= !empty($p['is_favorite']) ? 'true' : 'false' ?>"
                aria-label="Save <?= e($p['business_name']) ?> to favorites"<?= $removable ? ' data-remove-on-unfavorite' : '' ?>>
            <?= icon('heart', 18) ?>
        </button>
    <?php endif; ?>
    <div class="provider-card-body">
        <?= avatar_html($p['business_name'], $p['avatar'], 'lg') ?>
        <h3><a href="<?= e(url('provider.php?slug=' . $p['slug'])) ?>"><?= e($p['business_name']) ?></a></h3>
        <div class="provider-card-meta">
            <?php if ((int) $p['review_count'] > 0): ?>
                <span class="rating-inline"><?= icon('star', 15) ?> <?= number_format((float) $p['avg_rating'], 1) ?> <span>(<?= (int) $p['review_count'] ?>)</span></span>
            <?php else: ?>
                <span class="badge badge-brand">New</span>
            <?php endif; ?>
            <?php if ((int) $p['is_verified']): ?><?= verified_badge() ?><?php endif; ?>
            <span><?= icon('map-pin', 15) ?> <?= e($p['city']) ?></span>
        </div>
        <p class="provider-card-headline"><?= e($p['headline'] ?: $p['category_names']) ?></p>
        <div class="provider-card-foot">
            <div class="provider-card-price">
                <small>Starting at</small>
                <strong><?= money($p['min_price']) ?></strong>
            </div>
            <div class="provider-card-jobs">
                <strong class="text-sm"><?= e($p['category_names']) ?></strong><br>
                <?= plural((int) $p['completed_jobs'], 'job') ?> completed
            </div>
        </div>
    </div>
</article>
