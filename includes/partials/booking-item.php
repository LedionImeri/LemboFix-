<?php
/**
 * One booking row card.
 * @var array  $b     row from list_bookings()
 * @var string $view  'customer' | 'provider' | 'admin'
 */
$view = $view ?? 'customer';
$actions = booking_actions_for($b, $view);
$counterpart = $view === 'customer'
    ? ['name' => $b['business_name'], 'avatar' => $b['provider_avatar'], 'link' => url('provider.php?slug=' . $b['provider_slug'])]
    : ['name' => $b['customer_first_name'] . ' ' . $b['customer_last_name'], 'avatar' => $b['customer_avatar'], 'link' => null];
$confirmCopy = [
    'confirmed'   => ['Accept this booking?', 'The customer will be notified that you confirmed the appointment.', 'Accept booking', 'success', false],
    'rejected'    => ['Decline this booking?', 'Let the customer know why you can\'t take this job.', 'Decline booking', 'danger', true],
    'in_progress' => ['Start this job?', 'The customer will see that the work has started.', 'Start job', 'brand', false],
    'completed'   => ['Mark as completed?', 'The customer will be asked to leave a review.', 'Mark completed', 'success', true],
    'cancelled'   => ['Cancel this booking?', $view === 'customer' ? 'The provider will be notified. Card payments are refunded automatically (demo).' : 'The other party will be notified.', 'Cancel booking', 'danger', true],
];
?>
<article class="booking-item" id="booking-<?= (int) $b['id'] ?>">
    <div class="booking-date">
        <small><?= e(date('M', strtotime($b['booking_date']))) ?></small>
        <strong><?= e(date('j', strtotime($b['booking_date']))) ?></strong>
        <span><?= e(date('D', strtotime($b['booking_date']))) ?></span>
    </div>
    <div class="booking-info">
        <div class="booking-info-top">
            <?= avatar_html($counterpart['name'], $counterpart['avatar'], 'xs') ?>
            <strong>
                <?php if ($counterpart['link']): ?><a href="<?= e($counterpart['link']) ?>"><?= e($counterpart['name']) ?></a>
                <?php else: ?><?= e($counterpart['name']) ?><?php endif; ?>
            </strong>
            <?= status_badge($b['status']) ?>
        </div>
        <div class="booking-services"><?= e($b['service_titles']) ?></div>
        <div class="booking-meta">
            <span><?= icon('calendar', 14) ?> <?= e(relative_day($b['booking_date'])) ?></span>
            <span><?= icon('clock', 14) ?> <?= e(format_time($b['start_time'])) ?>–<?= e(format_time($b['end_time'])) ?></span>
            <span><?= icon('map-pin', 14) ?> <?= e($b['address'] . ', ' . $b['city']) ?></span>
            <span><?= icon($b['payment_method'] === 'card' ? 'credit-card' : 'banknote', 14) ?> <?= e(ucfirst($b['payment_method'])) ?> · <?= e(ucfirst($b['payment_status'])) ?></span>
            <span class="text-xs">#<?= e($b['reference']) ?></span>
        </div>
        <?php if ($view !== 'customer' && $b['customer_phone'] && in_array($b['status'], ['confirmed', 'in_progress'], true)): ?>
            <div class="booking-meta mt-1"><span><?= icon('phone', 14) ?> <?= e($b['customer_phone']) ?></span></div>
        <?php endif; ?>
        <?php if ($b['customer_message']): ?>
            <div class="booking-note"><?= icon('message', 14) ?> “<?= e($b['customer_message']) ?>”</div>
        <?php endif; ?>
        <?php if ($b['provider_note'] && in_array($b['status'], ['rejected', 'completed', 'confirmed'], true)): ?>
            <div class="booking-note"><strong>Provider note:</strong> <?= e($b['provider_note']) ?></div>
        <?php endif; ?>
        <?php if ($b['status'] === 'cancelled' && $b['cancellation_reason']): ?>
            <div class="booking-note"><strong>Cancelled by <?= e($b['cancelled_by']) ?>:</strong> <?= e($b['cancellation_reason']) ?></div>
        <?php endif; ?>
    </div>
    <div class="booking-side">
        <div class="booking-price"><?= money($view === 'provider' ? $b['subtotal'] : $b['total']) ?></div>
        <div class="booking-actions">
            <?php foreach ($actions as $to => [$label, $tone, $ico]): [$title, $text, $ok, $confirmTone, $withNote] = $confirmCopy[$to]; ?>
                <form method="post" action="<?= e(url('api/bookings.php')) ?>" data-ajax data-on-success="reload">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="status">
                    <input type="hidden" name="booking_id" value="<?= (int) $b['id'] ?>">
                    <input type="hidden" name="status" value="<?= e($to) ?>">
                    <button type="submit" class="btn btn-xs btn-<?= $tone === 'danger' ? 'danger-soft' : ($tone === 'success' ? 'success' : 'brand-soft') ?>"
                            data-confirm="<?= e($title) ?>" data-confirm-text="<?= e($text) ?>" data-confirm-ok="<?= e($ok) ?>"
                            data-confirm-tone="<?= e($confirmTone) ?>"<?= $withNote ? ' data-confirm-note="' . ($to === 'rejected' ? 'Reason for declining' : 'Note (optional)') . '"' . ($to === 'rejected' ? ' data-confirm-note-required' : '') : '' ?>>
                        <?= icon($ico, 14) ?> <?= e($label) ?>
                    </button>
                </form>
            <?php endforeach; ?>
            <?php if ($view === 'customer' && $b['status'] === 'completed'): ?>
                <?php if ($b['review_id']): ?>
                    <span class="badge badge-success"><?= icon('star', 12) ?> Reviewed <?= (int) $b['review_rating'] ?>/5</span>
                <?php else: ?>
                    <button type="button" class="btn btn-xs btn-primary" data-modal-open="#review-modal"
                            data-fill-booking-id="<?= (int) $b['id'] ?>" data-fill-provider-name="<?= e($b['business_name']) ?>">
                        <?= icon('star', 14) ?> Leave a review
                    </button>
                <?php endif; ?>
                <a class="btn btn-xs btn-secondary" href="<?= e(url('book.php?provider=' . $b['provider_slug'])) ?>"><?= icon('refresh', 14) ?> Book again</a>
            <?php endif; ?>
            <?php if ($view === 'customer' && in_array($b['status'], ['pending', 'confirmed'], true)): ?>
                <a class="btn btn-xs btn-ghost" href="<?= e(url('booking-success.php?ref=' . $b['reference'])) ?>"><?= icon('receipt', 14) ?> Details</a>
            <?php endif; ?>
        </div>
    </div>
</article>
