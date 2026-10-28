<?php
require __DIR__ . '/includes/bootstrap.php';

$fee = (int) config('booking.platform_fee_percent');
page_start([
    'title'       => 'Grow your business as a LemboFix professional',
    'description' => 'Join LemboFix as a service provider: get discovered by local customers, manage bookings and availability, and build your reputation with verified reviews.',
    'active'      => 'pros',
]);
?>
<section class="page-hero">
    <div class="container">
        <span class="eyebrow">For professionals</span>
        <h1>More customers. Less admin.</h1>
        <p>LemboFix gives independent pros and small teams a beautiful profile, a smart booking calendar and a steady stream of local customers.</p>
        <div class="form-actions mt-3">
            <a class="btn btn-primary btn-lg" href="<?= e(url('register.php?role=provider')) ?>">Create your free profile</a>
            <a class="btn btn-secondary btn-lg" href="<?= e(url('provider.php?slug=alex-plumbing')) ?>">See an example profile</a>
        </div>
    </div>
</section>
<section class="section-sm">
    <div class="container">
        <div class="grid-3">
            <div class="step">
                <div class="step-num"><?= icon('calendar-check', 22) ?></div>
                <h3>Smart availability</h3>
                <p>Set working hours, breaks, days off and vacations. Customers only see real free slots — double-bookings are impossible.</p>
            </div>
            <div class="step">
                <div class="step-num"><?= icon('star', 22) ?></div>
                <h3>Verified reviews</h3>
                <p>Only customers with completed bookings can review you, so your rating reflects real work. Reply publicly to every review.</p>
            </div>
            <div class="step">
                <div class="step-num"><?= icon('trending-up', 22) ?></div>
                <h3>Clear earnings</h3>
                <p>Track bookings, revenue and ratings on your dashboard. A simple <?= $fee ?>% platform fee is added to the customer's total.</p>
            </div>
        </div>
    </div>
</section>
<section class="section">
    <div class="container">
        <div class="cta-band">
            <div>
                <h2>How it works for pros</h2>
                <ul class="cta-list">
                    <li><?= icon('check-circle', 18) ?> Register and describe your business and services</li>
                    <li><?= icon('check-circle', 18) ?> Our team reviews and approves your profile</li>
                    <li><?= icon('check-circle', 18) ?> Accept booking requests, do great work, collect reviews</li>
                </ul>
            </div>
            <div class="cta-actions">
                <a class="btn btn-primary btn-lg" href="<?= e(url('register.php?role=provider')) ?>">Get started</a>
            </div>
        </div>
    </div>
</section>
<?php page_end(); ?>
