<?php
require __DIR__ . '/includes/bootstrap.php';

$slug = preg_replace('/[^a-z0-9\-]/', '', input('slug'));
// Admins and the owner may preview profiles that are not public yet
$provider = $slug !== '' ? find_provider_by_slug($slug, true) : null;
$isOwner = $provider && has_role(ROLE_PROVIDER) && (int) $provider['user_id'] === user_id();
$isPublic = $provider && $provider['approval_status'] === 'approved' && $provider['user_status'] === 'active';
if (!$provider || (!$isPublic && !$isOwner && !has_role(ROLE_ADMIN))) {
    render_error_page(404, 'This professional could not be found or is no longer available on LemboFix.');
}

$providerId = (int) $provider['id'];
$services = provider_services_list($providerId);
$week = provider_weekly_hours($providerId);
$breakdown = rating_breakdown($providerId);
$showAll = input('reviews') === 'all';
$reviews = provider_reviews($providerId, $showAll ? 100 : 5);
$minDuration = $services ? min(array_map(fn ($s) => (int) $s['duration_minutes'], $services)) : 60;
$nextDays = next_available_days($providerId, $minDuration, 4);
$isFavorite = has_role(ROLE_CUSTOMER)
    && (bool) db_value('SELECT 1 FROM favorites WHERE customer_id = ? AND provider_id = ?', [user_id(), $providerId]);
$canBook = $isPublic && !has_role(ROLE_PROVIDER, ROLE_ADMIN);
$canReport = is_logged_in() && !$isOwner;
$minPrice = $services ? min(array_map(fn ($s) => (float) $s['price'], $services)) : null;
$bookUrl = url('book.php?provider=' . $provider['slug']);

page_start([
    'title'       => $provider['business_name'] . ' — ' . ($provider['categories'][0]['name'] ?? 'Professional') . ' in ' . $provider['city'],
    'description' => excerpt(($provider['headline'] ? $provider['headline'] . '. ' : '') . (string) $provider['bio'], 155),
    'active'      => 'search',
]);
?>
<section class="profile-hero">
    <div class="container">
        <nav class="breadcrumb" aria-label="Breadcrumb">
            <a href="<?= e(url('index.php')) ?>">Home</a><?= icon('chevron-right', 14) ?>
            <?php if ($provider['categories']): ?>
                <a href="<?= e(url('search.php?category=' . $provider['categories'][0]['slug'])) ?>"><?= e($provider['categories'][0]['name']) ?></a><?= icon('chevron-right', 14) ?>
            <?php endif; ?>
            <span><?= e($provider['business_name']) ?></span>
        </nav>

        <?php if (!$isPublic): ?>
            <div class="alert alert-warning"><?= icon('eye-off', 20) ?><div><strong>Preview only.</strong> This profile is not visible to customers yet (status: <?= e($provider['approval_status']) ?>).</div></div>
        <?php endif; ?>

        <div class="profile-head">
            <?= avatar_html($provider['business_name'], $provider['avatar'], 'xl') ?>
            <div class="profile-head-main">
                <h1><?= e($provider['business_name']) ?></h1>
                <?php if ($provider['headline']): ?><p class="profile-headline"><?= e($provider['headline']) ?></p><?php endif; ?>
                <div class="profile-meta">
                    <?php if ($provider['is_verified']): ?><span><?= verified_badge() ?><span class="text-success fw-600">&nbsp;Professional</span></span><?php endif; ?>
                    <?php if ((int) $provider['review_count']): ?>
                        <span class="rating-inline"><?= icon('star', 16) ?> <?= number_format((float) $provider['avg_rating'], 1) ?> <span>(<?= plural((int) $provider['review_count'], 'review') ?>)</span></span>
                    <?php else: ?>
                        <span class="badge badge-brand">New on LemboFix</span>
                    <?php endif; ?>
                    <span><?= icon('map-pin', 16) ?> <?= e($provider['city']) ?></span>
                    <span><?= icon('user', 16) ?> Run by <?= e($provider['first_name'] . ' ' . $provider['last_name']) ?></span>
                </div>
            </div>
            <div class="profile-actions">
                <?php if (!has_role(ROLE_PROVIDER, ROLE_ADMIN)): ?>
                    <button type="button" class="btn btn-secondary fav-toggle<?= $isFavorite ? ' is-active' : '' ?>" data-favorite="<?= $providerId ?>" aria-pressed="<?= $isFavorite ? 'true' : 'false' ?>">
                        <?= icon('heart', 18) ?> <span data-fav-label><?= $isFavorite ? 'Saved' : 'Save' ?></span>
                    </button>
                <?php endif; ?>
                <?php if ($canBook && $services): ?>
                    <a class="btn btn-primary" href="<?= e($bookUrl) ?>"><?= icon('calendar', 18) ?> Book now</a>
                <?php elseif ($isOwner): ?>
                    <a class="btn btn-dark" href="<?= e(url('provider/profile.php')) ?>"><?= icon('edit', 18) ?> Edit profile</a>
                <?php endif; ?>
            </div>
        </div>

        <div class="profile-stats">
            <div><strong><?= (int) $provider['review_count'] ? number_format((float) $provider['avg_rating'], 1) : '—' ?></strong><span>Average rating</span></div>
            <div><strong><?= number_format((int) $provider['completed_jobs']) ?></strong><span>Completed jobs</span></div>
            <div><strong><?= (int) $provider['experience_years'] ?>+ yrs</strong><span>Experience</span></div>
            <div><strong><?= e(date('Y', strtotime($provider['member_since']))) ?></strong><span>On LemboFix since</span></div>
        </div>
    </div>
</section>

<div class="container profile-layout">
    <div>
        <section class="card profile-section" aria-labelledby="about-title">
            <div class="card-head"><h2 id="about-title">About</h2></div>
            <div class="card-body">
                <p class="bio"><?= e($provider['bio'] ?: 'This professional has not added a description yet.') ?></p>
                <div class="tag-list">
                    <?php foreach ($provider['categories'] as $c): ?>
                        <a class="chip" href="<?= e(url('search.php?category=' . $c['slug'])) ?>"><?= icon($c['icon'], 15) ?> <?= e($c['name']) ?></a>
                    <?php endforeach; ?>
                    <?php if ($provider['service_area']): ?><span class="chip"><?= icon('map-pin', 15) ?> Serves: <?= e($provider['service_area']) ?></span><?php endif; ?>
                    <span class="chip"><?= icon('users', 15) ?> <?= plural((int) $provider['happy_customers'], 'happy customer') ?></span>
                </div>
            </div>
        </section>

        <section class="card profile-section" id="services" aria-labelledby="services-title">
            <div class="card-head">
                <h2 id="services-title">Services &amp; prices</h2>
                <span class="text-sm text-muted"><?= plural(count($services), 'service') ?></span>
            </div>
            <?php if ($services): ?>
                <div class="service-list">
                    <?php foreach ($services as $s): ?>
                        <div class="service-row">
                            <span class="service-row-icon"><?= icon($s['category_icon'], 20) ?></span>
                            <div class="service-row-main">
                                <strong><?= e($s['title']) ?></strong>
                                <?php if ($s['description']): ?><p><?= e($s['description']) ?></p><?php endif; ?>
                                <div class="service-row-meta">
                                    <span><?= icon('clock', 14) ?> <?= e(duration_label((int) $s['duration_minutes'])) ?></span>
                                    <span><?= icon($s['location_type'] === 'remote' ? 'monitor' : ($s['location_type'] === 'provider' ? 'briefcase' : 'home'), 14) ?>
                                        <?= e(['customer' => 'At your place', 'provider' => 'At the provider', 'remote' => 'Remote'][$s['location_type']]) ?></span>
                                </div>
                            </div>
                            <div class="service-row-price">
                                <strong><?= price_label($s['price'], $s['price_type']) ?></strong>
                                <?php if ($canBook): ?>
                                    <a class="btn btn-sm btn-secondary" href="<?= e($bookUrl . '&service=' . $s['id']) ?>">Book</a>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <?= empty_state('No services listed yet', 'This provider has not published any services.', 'layers') ?>
            <?php endif; ?>
        </section>

        <section class="card profile-section" id="reviews" aria-labelledby="reviews-title">
            <div class="card-head">
                <h2 id="reviews-title">Reviews</h2>
                <span class="text-sm text-muted">Only customers with completed bookings can review</span>
            </div>
            <div class="card-body">
                <?php if ((int) $provider['review_count'] > 0): ?>
                    <div class="rating-summary">
                        <div class="rating-big">
                            <strong><?= number_format((float) $provider['avg_rating'], 1) ?></strong>
                            <?= stars_html((float) $provider['avg_rating'], 'lg') ?>
                            <span><?= plural((int) $provider['review_count'], 'review') ?></span>
                        </div>
                        <div class="rating-bars">
                            <?php foreach ($breakdown as $stars => $count): $pct = $provider['review_count'] ? round($count / $provider['review_count'] * 100) : 0; ?>
                                <div class="rating-bar">
                                    <span><?= $stars ?> ★</span>
                                    <div class="rating-bar-track"><div class="rating-bar-fill" style="width: <?= $pct ?>%"></div></div>
                                    <span class="text-muted"><?= $count ?></span>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    <?php foreach ($reviews as $r): partial('review-item', ['r' => $r, 'canReport' => $canReport, 'providerName' => $provider['business_name']]); endforeach; ?>
                    <?php if (!$showAll && (int) $provider['review_count'] > count($reviews)): ?>
                        <div class="text-center mt-3">
                            <a class="btn btn-secondary" href="<?= e(url('provider.php?slug=' . $provider['slug'] . '&reviews=all#reviews')) ?>">Show all <?= (int) $provider['review_count'] ?> reviews</a>
                        </div>
                    <?php endif; ?>
                <?php else: ?>
                    <?= empty_state('No reviews yet', 'Be the first to book and share your experience with ' . $provider['business_name'] . '.', 'star') ?>
                <?php endif; ?>
            </div>
        </section>
    </div>

    <aside>
        <div class="booking-card">
            <div class="card profile-section">
                <div class="card-body">
                    <?php if ($minPrice !== null): ?>
                        <div class="booking-card-price"><span class="text-muted">From</span> <strong><?= money($minPrice) ?></strong></div>
                    <?php endif; ?>
                    <p class="text-sm text-muted"><?= plural(count($services), 'service') ?> · Pay by card or cash (demo)</p>

                    <?php if ($nextDays): ?>
                        <p class="fw-600 text-sm mb-1">Next available</p>
                        <div class="next-days">
                            <?php foreach ($nextDays as $d): ?>
                                <div class="next-day"><strong><?= e(relative_day($d['date'])) ?></strong><span><?= plural(count($d['slots']), 'free slot') ?> · from <?= e($d['slots'][0]) ?></span></div>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <div class="alert alert-info mb-0"><?= icon('calendar-off', 18) ?><div>No free slots in the next 3 weeks. Save this provider and check back soon.</div></div>
                    <?php endif; ?>

                    <?php if ($canBook && $services): ?>
                        <a class="btn btn-primary btn-lg btn-block mt-3" href="<?= e($bookUrl) ?>"><?= icon('calendar-check', 20) ?> Check availability &amp; book</a>
                    <?php elseif (has_role(ROLE_PROVIDER, ROLE_ADMIN) && !$isOwner): ?>
                        <p class="text-xs text-muted mt-2 mb-0">Bookings are made from a customer account.</p>
                    <?php endif; ?>
                    <ul class="booking-perks">
                        <li><?= icon('check-circle', 16) ?> Free cancellation up to <?= (int) config('booking.customer_cancel_hours') ?>h before</li>
                        <li><?= icon('check-circle', 16) ?> Live status updates in your notifications</li>
                        <li><?= icon('check-circle', 16) ?> Reviews only from real customers</li>
                    </ul>
                </div>
            </div>

            <div class="card profile-section">
                <div class="card-head"><h3>Working hours</h3></div>
                <div class="card-body">
                    <table class="hours-table">
                        <?php foreach (weekday_names() as $dow => $dayName): $d = $week[$dow] ?? null; ?>
                            <tr class="<?= (int) date('w') === $dow ? 'is-today' : '' ?>">
                                <td><?= e($dayName) ?></td>
                                <td>
                                    <?php if ($d && (int) $d['is_working']): ?>
                                        <?= e(format_time($d['start_time'])) ?>–<?= e(format_time($d['end_time'])) ?>
                                    <?php else: ?><span class="closed">Closed</span><?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </table>
                </div>
            </div>
        </div>
    </aside>
</div>

<?php if ($canReport): ?>
<div class="modal" id="report-modal" role="dialog" aria-modal="true" aria-labelledby="report-title" hidden>
    <div class="modal-backdrop" data-modal-close></div>
    <form class="modal-dialog" method="post" action="<?= e(url('api/reviews.php')) ?>" data-ajax data-validate>
        <div class="modal-head">
            <h3 id="report-title">Report review</h3>
            <button type="button" class="icon-btn" aria-label="Close" data-modal-close><?= icon('x', 20) ?></button>
        </div>
        <div class="modal-body">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="report">
            <input type="hidden" name="review_id" value="">
            <p class="text-sm">Our team reviews every report. Reviews that break our guidelines are removed.</p>
            <label class="field">
                <span class="field-label">Reason</span>
                <select class="select" name="reason" required>
                    <?php foreach (report_reasons() as $value => $label): ?><option value="<?= e($value) ?>"><?= e($label) ?></option><?php endforeach; ?>
                </select>
            </label>
            <label class="field mb-0">
                <span class="field-label">Details (optional)</span>
                <textarea class="textarea" name="details" maxlength="500" rows="3" placeholder="Tell us what's wrong with this review"></textarea>
            </label>
        </div>
        <div class="modal-foot">
            <button type="button" class="btn btn-secondary" data-modal-close>Cancel</button>
            <button type="submit" class="btn btn-danger"><?= icon('flag', 16) ?> Send report</button>
        </div>
    </form>
</div>
<?php endif; ?>
<?php page_end(); ?>
