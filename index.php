<?php
require __DIR__ . '/includes/bootstrap.php';

$categories = active_categories();
$featured = featured_providers(6);
$reviews = latest_platform_reviews(3);
$cities = provider_cities();

$stats = db_one(
    "SELECT (SELECT COUNT(*) FROM provider_profiles p JOIN users u ON u.id = p.user_id WHERE p.approval_status = 'approved' AND u.status = 'active') AS providers,
            (SELECT COUNT(*) FROM bookings WHERE status = 'completed') AS completed,
            (SELECT ROUND(AVG(rating), 1) FROM reviews WHERE status = 'published') AS rating,
            (SELECT COUNT(*) FROM reviews WHERE status = 'published') AS reviews"
);

$popularServices = db_all(
    "SELECT s.name, c.slug AS category_slug, c.icon, MIN(ps.price) AS min_price, COUNT(DISTINCT ps.provider_id) AS providers,
            COUNT(bi.id) AS bookings
     FROM services s
     JOIN categories c ON c.id = s.category_id AND c.is_active = 1
     JOIN provider_services ps ON ps.service_id = s.id AND ps.is_active = 1
     JOIN provider_profiles p ON p.id = ps.provider_id AND p.approval_status = 'approved'
     LEFT JOIN booking_items bi ON bi.provider_service_id = ps.id
     WHERE s.is_active = 1
     GROUP BY s.id
     ORDER BY bookings DESC
     LIMIT 8"
);

// Live data for the hero illustration: the top provider, its services and real free slots
$heroProvider = $featured[0] ?? null;
$heroServices = $heroProvider ? array_slice(provider_services_list((int) $heroProvider['id']), 0, 3) : [];
$heroDays = $heroProvider ? next_available_days((int) $heroProvider['id'], 60, 1) : [];
$heroSlots = $heroDays ? array_slice($heroDays[0]['slots'], 0, 6) : [];

page_start([
    'title'       => '',
    'description' => 'Find trusted plumbers, electricians, cleaners, mechanics and more near you. Compare verified professionals, see real-time availability and book online with LemboFix.',
    'body_class'  => 'is-home',
]);
?>

<section class="hero">
    <div class="container hero-grid">
        <div>
            <span class="hero-badge"><b>New</b> Real-time availability for <?= (int) $stats['providers'] ?> local pros</span>
            <h1>Find trusted professionals for <em>any job.</em></h1>
            <p class="hero-lead">From a leaking sink to a full house move — compare verified providers, check their real availability and book in minutes.</p>

            <form class="hero-search" action="<?= e(url('search.php')) ?>" method="get" role="search">
                <label>
                    <?= icon('search', 20) ?>
                    <div>
                        <span>Service</span>
                        <input type="text" name="q" placeholder="What service do you need?" aria-label="What service do you need?" maxlength="80" list="hero-services">
                    </div>
                </label>
                <label>
                    <?= icon('map-pin', 20) ?>
                    <div>
                        <span>Location</span>
                        <input type="text" name="city" placeholder="Where?" aria-label="Where?" maxlength="80" list="hero-cities">
                    </div>
                </label>
                <button class="btn btn-primary btn-lg" type="submit">Find a professional</button>
                <datalist id="hero-cities"><?php foreach ($cities as $c): ?><option value="<?= e($c) ?>"><?php endforeach; ?></datalist>
                <datalist id="hero-services"><?php foreach ($popularServices as $s): ?><option value="<?= e($s['name']) ?>"><?php endforeach; ?></datalist>
            </form>

            <div class="hero-popular">
                <span>Popular:</span>
                <a href="<?= e(url('search.php?category=plumbing')) ?>">Plumber</a>
                <a href="<?= e(url('search.php?category=electrical')) ?>">Electrician</a>
                <a href="<?= e(url('search.php?category=home-cleaning')) ?>">Home cleaning</a>
                <a href="<?= e(url('search.php?category=moving')) ?>">Movers</a>
            </div>

            <div class="hero-stats">
                <div><strong><?= number_format((float) $stats['rating'], 1) ?>/5</strong><span>Average rating</span></div>
                <div><strong><?= number_format((int) $stats['completed']) ?>+</strong><span>Jobs completed</span></div>
                <div><strong><?= count($categories) ?></strong><span>Service categories</span></div>
            </div>
        </div>

        <?php if ($heroProvider): ?>
        <div class="hero-visual" aria-hidden="true">
            <div class="float-card fc-provider">
                <div class="fc-head">
                    <?= avatar_html($heroProvider['business_name'], $heroProvider['avatar'], 'md') ?>
                    <div>
                        <strong><?= e($heroProvider['business_name']) ?></strong>
                        <span class="rating-inline text-sm"><?= icon('star', 14) ?> <?= number_format((float) $heroProvider['avg_rating'], 1) ?> <span>(<?= (int) $heroProvider['review_count'] ?> reviews)</span></span>
                    </div>
                    <?php if ($heroProvider['is_verified']): ?><span style="margin-left:auto"><?= verified_badge() ?></span><?php endif; ?>
                </div>
                <div class="fc-services">
                    <?php foreach ($heroServices as $s): ?>
                        <div><span><?= e($s['title']) ?></span><b><?= money($s['price']) ?></b></div>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php if ($heroSlots): ?>
            <div class="float-card fc-slots">
                <div class="fc-label"><?= e(relative_day($heroDays[0]['date'])) ?> · free slots</div>
                <div class="fc-slots-grid">
                    <?php foreach ($heroSlots as $i => $slot): ?>
                        <span class="<?= $i === 1 ? 'is-picked' : '' ?>"><?= e($slot) ?></span>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endif; ?>
            <div class="float-card fc-toast">
                <span class="fc-check"><?= icon('check', 22) ?></span>
                <div><strong>Booking confirmed</strong><span>You'll get a reminder 24h before.</span></div>
            </div>
        </div>
        <?php endif; ?>
    </div>
</section>

<div class="trust-bar">
    <div class="container">
        <div class="trust-item"><?= icon('badge-check', 26) ?><div><strong>Verified professionals</strong>Every provider is reviewed by our team</div></div>
        <div class="trust-item"><?= icon('calendar-check', 26) ?><div><strong>Real availability</strong>Only see times that are actually free</div></div>
        <div class="trust-item"><?= icon('star', 26) ?><div><strong>Honest reviews</strong>Only from completed bookings</div></div>
        <div class="trust-item"><?= icon('shield', 26) ?><div><strong>Secure booking</strong>Cancel free up to <?= (int) config('booking.customer_cancel_hours') ?>h before</div></div>
    </div>
</div>

<section class="section" id="categories">
    <div class="container">
        <div class="section-head">
            <div>
                <span class="eyebrow">Categories</span>
                <h2>What can we help you with?</h2>
                <p>Browse <?= count($categories) ?> service categories and find the right professional for the job.</p>
            </div>
            <a class="btn btn-secondary" href="<?= e(url('categories.php')) ?>">All categories <?= icon('arrow-right', 18) ?></a>
        </div>
        <div class="category-grid">
            <?php foreach ($categories as $cat): ?>
                <a class="category-tile" href="<?= e(url('search.php?category=' . $cat['slug'])) ?>">
                    <span class="category-icon"><?= icon($cat['icon'], 24) ?></span>
                    <div>
                        <strong><?= e($cat['name']) ?></strong><br>
                        <span><?= (int) $cat['provider_count'] ? plural((int) $cat['provider_count'], 'pro') . ' available' : 'Coming soon' ?></span>
                    </div>
                </a>
            <?php endforeach; ?>
        </div>
    </div>
</section>

<section class="section section-alt">
    <div class="container">
        <div class="section-head">
            <div>
                <span class="eyebrow">Featured</span>
                <h2>Top-rated professionals</h2>
                <p>Highly reviewed, verified providers that customers book again and again.</p>
            </div>
            <a class="btn btn-secondary" href="<?= e(url('search.php?sort=rating')) ?>">Browse all <?= icon('arrow-right', 18) ?></a>
        </div>
        <?php if ($featured): ?>
            <div class="provider-grid">
                <?php foreach ($featured as $p): partial('provider-card', ['p' => $p]); endforeach; ?>
            </div>
        <?php else: ?>
            <div class="card"><?= empty_state('No providers yet', 'Professionals will appear here once they are approved.', 'users') ?></div>
        <?php endif; ?>
    </div>
</section>

<section class="section" id="how-it-works">
    <div class="container">
        <div class="section-head">
            <div>
                <span class="eyebrow">How it works</span>
                <h2>Booked in three simple steps</h2>
                <p>No phone tag, no guesswork. See prices and free time slots before you book.</p>
            </div>
        </div>
        <div class="steps">
            <div class="step">
                <div class="step-num">1</div>
                <h3>Search &amp; compare</h3>
                <p>Tell us what you need and where. Compare verified pros by rating, price and real reviews.</p>
                <span class="step-arrow hide-mobile"><?= icon('chevron-right', 32) ?></span>
            </div>
            <div class="step">
                <div class="step-num">2</div>
                <h3>Pick a time that works</h3>
                <p>Choose services and an available time slot from the provider's live calendar.</p>
                <span class="step-arrow hide-mobile"><?= icon('chevron-right', 32) ?></span>
            </div>
            <div class="step">
                <div class="step-num">3</div>
                <h3>Get it done &amp; review</h3>
                <p>The pro confirms, gets the job done, and you share your experience to help others.</p>
            </div>
        </div>
    </div>
</section>

<?php if ($popularServices): ?>
<section class="section section-alt">
    <div class="container">
        <div class="section-head">
            <div>
                <span class="eyebrow">Popular services</span>
                <h2>Most booked this season</h2>
            </div>
        </div>
        <div class="service-pills">
            <?php foreach ($popularServices as $s): ?>
                <a class="service-pill" href="<?= e(url('search.php?q=' . urlencode($s['name']))) ?>">
                    <div>
                        <strong><?= e($s['name']) ?></strong>
                        <span><?= plural((int) $s['providers'], 'provider') ?></span>
                    </div>
                    <span class="price">from <?= money($s['min_price']) ?></span>
                </a>
            <?php endforeach; ?>
        </div>
    </div>
</section>
<?php endif; ?>

<?php if ($reviews): ?>
<section class="section">
    <div class="container">
        <div class="section-head">
            <div>
                <span class="eyebrow">Customer reviews</span>
                <h2>Loved by homeowners</h2>
                <p><?= number_format((int) $stats['reviews']) ?> verified reviews with an average of <?= number_format((float) $stats['rating'], 1) ?> stars.</p>
            </div>
        </div>
        <div class="testimonials">
            <?php foreach ($reviews as $r): ?>
                <figure class="testimonial">
                    <?= stars_html((float) $r['rating']) ?>
                    <p>“<?= e(excerpt($r['comment'], 220)) ?>”</p>
                    <figcaption class="testimonial-foot">
                        <?= avatar_html($r['first_name'] . ' ' . $r['last_name'], $r['avatar'], 'md') ?>
                        <div>
                            <strong><?= e($r['first_name'] . ' ' . mb_substr($r['last_name'], 0, 1) . '.') ?></strong>
                            <span>booked <a class="link" href="<?= e(url('provider.php?slug=' . $r['slug'])) ?>"><?= e($r['business_name']) ?></a></span>
                        </div>
                    </figcaption>
                </figure>
            <?php endforeach; ?>
        </div>
    </div>
</section>
<?php endif; ?>

<section class="section-sm">
    <div class="container">
        <div class="cta-band">
            <div>
                <span class="eyebrow">For professionals</span>
                <h2>Grow your business with LemboFix</h2>
                <p>Get discovered by customers in your city, manage bookings and availability in one place, and build your reputation with verified reviews.</p>
                <ul class="cta-list">
                    <li><?= icon('check-circle', 18) ?> Free profile — pay only a small fee per completed booking</li>
                    <li><?= icon('check-circle', 18) ?> Smart calendar that prevents double-bookings</li>
                </ul>
            </div>
            <div class="cta-actions">
                <a class="btn btn-primary btn-lg" href="<?= e(url('register.php?role=provider')) ?>">Join as a professional</a>
                <a class="btn btn-secondary btn-lg" href="<?= e(url('for-professionals.php')) ?>">Learn more</a>
            </div>
        </div>
    </div>
</section>

<?php page_end(); ?>
