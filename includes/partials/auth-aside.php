<?php
$quote = latest_platform_reviews(1)[0] ?? null;
?>
<aside class="auth-aside" aria-hidden="true">
    <h2><?= e($asideTitle ?? 'Trusted pros, booked in minutes.') ?></h2>
    <p><?= e($asideText ?? 'Compare verified local professionals, see real availability and manage every booking in one place.') ?></p>
    <?php if ($quote): ?>
        <blockquote class="auth-quote">
            <?= stars_html((float) $quote['rating']) ?>
            <p class="mt-1">“<?= e(excerpt($quote['comment'], 170)) ?>”</p>
            <footer>
                <?= avatar_html($quote['first_name'] . ' ' . $quote['last_name'], $quote['avatar'], 'md') ?>
                <div><strong><?= e($quote['first_name'] . ' ' . mb_substr($quote['last_name'], 0, 1) . '.') ?></strong>Booked <?= e($quote['business_name']) ?></div>
            </footer>
        </blockquote>
    <?php endif; ?>
</aside>
