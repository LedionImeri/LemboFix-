<?php
require __DIR__ . '/includes/bootstrap.php';
$user = require_login(ROLE_CUSTOMER);

$provider = find_provider_by_slug(preg_replace('/[^a-z0-9\-]/', '', input('provider')));
if (!$provider) {
    render_error_page(404, 'This professional is not available for booking.');
}
$services = provider_services_list((int) $provider['id']);
if (!$services) {
    flash('info', $provider['business_name'] . ' has no bookable services right now.');
    redirect('provider.php?slug=' . $provider['slug']);
}
$preselected = input_int('service');
$profile = db_one('SELECT address FROM customer_profiles WHERE user_id = ?', [$user['id']]) ?? [];
$feePercent = (float) config('booking.platform_fee_percent');

page_start([
    'title'   => 'Book ' . $provider['business_name'],
    'noindex' => true,
    'scripts' => ['booking.js'],
]);
?>
<section class="page-hero" style="padding-bottom:24px">
    <div class="container">
        <nav class="breadcrumb" aria-label="Breadcrumb">
            <a href="<?= e(url('index.php')) ?>">Home</a><?= icon('chevron-right', 14) ?>
            <a href="<?= e(url('provider.php?slug=' . $provider['slug'])) ?>"><?= e($provider['business_name']) ?></a><?= icon('chevron-right', 14) ?>
            <span>Book</span>
        </nav>
        <h1 class="mb-0">Book <?= e($provider['business_name']) ?></h1>
    </div>
</section>

<div class="container">
    <form class="booking-layout" id="booking-form" method="post" action="<?= e(url('api/bookings.php')) ?>" data-ajax data-keep-open
          data-provider-id="<?= (int) $provider['id'] ?>" data-fee-percent="<?= e((string) $feePercent) ?>" novalidate>
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="create">
        <input type="hidden" name="provider_id" value="<?= (int) $provider['id'] ?>">
        <input type="hidden" name="date" value="" data-date-input>
        <input type="hidden" name="time" value="" data-time-input>

        <div>
            <ol class="stepper" data-stepper>
                <li class="is-active">Services</li>
                <li>Date &amp; time</li>
                <li>Details</li>
                <li>Payment</li>
            </ol>

            <!-- Step 1: services -->
            <section class="booking-step is-active card" data-step="1" aria-labelledby="step1-title">
                <div class="card-head"><h2 id="step1-title">Which services do you need?</h2><span class="text-sm text-muted">Select one or more</span></div>
                <div class="card-body">
                    <div class="service-choice">
                        <?php foreach ($services as $s): ?>
                            <label class="service-option">
                                <input type="checkbox" name="services[]" value="<?= (int) $s['id'] ?>"
                                       data-title="<?= e($s['title']) ?>" data-price="<?= e($s['price']) ?>" data-duration="<?= (int) $s['duration_minutes'] ?>"
                                       <?= checked($preselected === (int) $s['id'] || (count($services) === 1)) ?>>
                                <span class="tick"><?= icon('check', 16) ?></span>
                                <span class="service-option-main">
                                    <strong><?= e($s['title']) ?></strong>
                                    <span><?= e(duration_label((int) $s['duration_minutes'])) ?> · <?= e(['customer' => 'At your place', 'provider' => 'At the provider', 'remote' => 'Remote'][$s['location_type']]) ?></span>
                                </span>
                                <span class="service-option-price"><?= price_label($s['price'], $s['price_type']) ?></span>
                            </label>
                        <?php endforeach; ?>
                    </div>
                    <p class="field-error" data-step-error="1" hidden>Please choose at least one service.</p>
                    <div class="step-nav">
                        <a class="btn btn-ghost" href="<?= e(url('provider.php?slug=' . $provider['slug'])) ?>"><?= icon('arrow-left', 18) ?> Back to profile</a>
                        <button type="button" class="btn btn-primary" data-next>Choose a time <?= icon('arrow-right', 18) ?></button>
                    </div>
                </div>
            </section>

            <!-- Step 2: date & time -->
            <section class="booking-step card" data-step="2" aria-labelledby="step2-title">
                <div class="card-head"><h2 id="step2-title">Pick a date &amp; time</h2><span class="text-sm text-muted" data-duration-label></span></div>
                <div class="card-body">
                    <div class="calendar-wrap">
                        <div class="calendar" data-calendar>
                            <div class="calendar-head">
                                <button type="button" class="icon-btn" aria-label="Previous month" data-cal-prev><?= icon('chevron-left', 20) ?></button>
                                <strong data-cal-title>…</strong>
                                <button type="button" class="icon-btn" aria-label="Next month" data-cal-next><?= icon('chevron-right', 20) ?></button>
                            </div>
                            <div class="calendar-grid" data-cal-grid role="grid" aria-label="Available dates"></div>
                            <div class="calendar-legend"><span><i></i> Available</span><span><i class="off"></i> Unavailable / day off</span></div>
                        </div>
                        <div class="slots-panel" data-slots-panel>
                            <h4 data-slots-title>Select a date</h4>
                            <p data-slots-sub>Days with a green dot have free time slots.</p>
                            <div data-slots><div class="slots-empty"><?= icon('calendar', 24) ?><br>Choose a day on the calendar to see free times.</div></div>
                        </div>
                    </div>
                    <p class="field-error" data-step-error="2" hidden>Please choose a date and a time slot.</p>
                    <div class="step-nav">
                        <button type="button" class="btn btn-ghost" data-prev><?= icon('arrow-left', 18) ?> Back</button>
                        <button type="button" class="btn btn-primary" data-next>Continue <?= icon('arrow-right', 18) ?></button>
                    </div>
                </div>
            </section>

            <!-- Step 3: details -->
            <section class="booking-step card" data-step="3" aria-labelledby="step3-title">
                <div class="card-head"><h2 id="step3-title">Where should the pro come?</h2></div>
                <div class="card-body">
                    <label class="field">
                        <span class="field-label">Address</span>
                        <input class="input" type="text" name="address" value="<?= e($profile['address'] ?? '') ?>" required minlength="5" maxlength="255" autocomplete="street-address" placeholder="Street, number, apartment">
                    </label>
                    <label class="field">
                        <span class="field-label">City</span>
                        <input class="input" type="text" name="city" value="<?= e($user['city'] ?: $provider['city']) ?>" required minlength="2" maxlength="80" autocomplete="address-level2">
                    </label>
                    <label class="field">
                        <span class="field-label">Message for <?= e($provider['first_name']) ?> <span class="text-muted">(optional)</span></span>
                        <textarea class="textarea" name="message" maxlength="1000" rows="4" placeholder="Describe the problem, access details, parking…" data-count="#message-count"></textarea>
                        <span class="field-hint" id="message-count"></span>
                    </label>
                    <div class="step-nav">
                        <button type="button" class="btn btn-ghost" data-prev><?= icon('arrow-left', 18) ?> Back</button>
                        <button type="button" class="btn btn-primary" data-next>Continue to payment <?= icon('arrow-right', 18) ?></button>
                    </div>
                </div>
            </section>

            <!-- Step 4: demo payment -->
            <section class="booking-step card" data-step="4" aria-labelledby="step4-title">
                <div class="card-head"><h2 id="step4-title">Payment</h2><span class="badge badge-warning">Demo mode</span></div>
                <div class="card-body">
                    <div class="alert alert-demo"><?= icon('info', 20) ?><div><strong>This is a demo payment system.</strong> No real money is charged and no card details are collected. Choose a method to complete the booking flow.</div></div>
                    <div class="pay-methods" role="radiogroup" aria-label="Payment method">
                        <label class="pay-method">
                            <input type="radio" name="payment_method" value="card" checked>
                            <?= icon('credit-card', 22) ?>
                            <div><strong>Demo card</strong><span>Simulated instant payment, refunded automatically if cancelled</span></div>
                        </label>
                        <label class="pay-method">
                            <input type="radio" name="payment_method" value="cash">
                            <?= icon('banknote', 22) ?>
                            <div><strong>Pay after the job</strong><span>Pay the pro in cash when the work is done</span></div>
                        </label>
                    </div>
                    <div class="demo-card" data-demo-card aria-hidden="true">
                        <div class="demo-card-top"><span>LemboPay</span><span class="demo-card-chip"></span></div>
                        <div class="demo-card-number">4242 •••• •••• 4242</div>
                        <div class="demo-card-bottom"><span><?= e($user['full_name']) ?></span><span>12/30</span></div>
                        <span class="demo-stamp">DEMO</span>
                    </div>
                    <p class="text-sm text-muted mt-3">By confirming you agree to the provider's cancellation policy: free cancellation for pending bookings, and up to <?= (int) config('booking.customer_cancel_hours') ?> hours before the start for confirmed ones.</p>
                    <div class="step-nav">
                        <button type="button" class="btn btn-ghost" data-prev><?= icon('arrow-left', 18) ?> Back</button>
                        <button type="submit" class="btn btn-primary btn-lg"><?= icon('lock', 18) ?> Confirm booking · <span data-total-btn></span></button>
                    </div>
                </div>
            </section>
        </div>

        <aside>
            <div class="card summary-card">
                <div class="card-body">
                    <div class="summary-provider">
                        <?= avatar_html($provider['business_name'], $provider['avatar'], 'md') ?>
                        <div>
                            <strong><?= e($provider['business_name']) ?></strong>
                            <span class="rating-inline text-sm"><?= icon('star', 14) ?> <?= number_format((float) $provider['avg_rating'], 1) ?> <span>(<?= (int) $provider['review_count'] ?>)</span></span>
                        </div>
                    </div>
                    <div class="summary-when">
                        <div><?= icon('calendar', 16) ?> <span data-sum-date>No date selected</span></div>
                        <div><?= icon('clock', 16) ?> <span data-sum-time>No time selected</span></div>
                    </div>
                    <div class="summary-lines" data-sum-lines><p class="summary-empty">No services selected yet.</p></div>
                    <div class="summary-lines mt-2">
                        <div class="summary-line"><span>Subtotal</span><strong data-sum-subtotal>€0</strong></div>
                        <div class="summary-line"><span>Platform fee (<?= e((string) $feePercent) ?>%)</span><strong data-sum-fee>€0</strong></div>
                    </div>
                    <div class="summary-total"><span class="fw-600">Total</span><strong data-sum-total>€0</strong></div>
                    <p class="text-xs text-muted mt-2 mb-0">“From” and hourly prices are estimates — the final price is agreed with the provider.</p>
                </div>
            </div>
        </aside>
    </form>
</div>
<?php page_end(); ?>
