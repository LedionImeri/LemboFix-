<?php
require __DIR__ . '/includes/bootstrap.php';
require_guest();

$categories = active_categories();
$role = input('role') === ROLE_PROVIDER ? ROLE_PROVIDER : ROLE_CUSTOMER;

if (is_post()) {
    require_csrf();
    $v = new Validator($_POST);
    $v->in('role', [ROLE_CUSTOMER, ROLE_PROVIDER])
      ->required('first_name', 'First name')->length('first_name', 'First name', 2, 60)
      ->required('last_name', 'Last name')->length('last_name', 'Last name', 2, 60)
      ->required('email', 'Email')->email('email')->length('email', 'Email', 5, 190)
      ->phone('phone')
      ->required('city', 'City')->length('city', 'City', 2, 80)
      ->required('password', 'Password')->password('password')
      ->matches('password_confirm', 'password', 'Passwords do not match.')
      ->custom(empty($_POST['terms']), 'terms', 'Please accept the terms to continue.');

    if ($role === ROLE_PROVIDER) {
        $categoryIds = array_column($categories, 'id');
        $v->required('business_name', 'Business name')->length('business_name', 'Business name', 3, 120)
          ->required('category_id', 'Main category')
          ->custom(!in_array((int) input('category_id'), array_map('intval', $categoryIds), true), 'category_id', 'Please choose a category.')
          ->number('experience_years', 'Years of experience', 0, 60)
          ->length('headline', 'Headline', 0, 160);
    }

    $email = strtolower($v->value('email'));
    if (!$v->fails() && db_value('SELECT id FROM users WHERE email = ?', [$email])) {
        $v->custom(true, 'email', 'An account with this email already exists. Try logging in instead.');
    }

    if ($v->fails()) {
        with_form_state($_POST, $v->errors());
        redirect('register.php?role=' . $role);
    }

    $pdo = db();
    $pdo->beginTransaction();
    try {
        db_query(
            'INSERT INTO users (role, first_name, last_name, email, password_hash, phone, city) VALUES (?, ?, ?, ?, ?, ?, ?)',
            [$role, $v->value('first_name'), $v->value('last_name'), $email, password_hash($v->value('password'), PASSWORD_DEFAULT),
             $v->value('phone') ?: null, $v->value('city')]
        );
        $userId = (int) $pdo->lastInsertId();

        if ($role === ROLE_CUSTOMER) {
            db_query('INSERT INTO customer_profiles (user_id) VALUES (?)', [$userId]);
        } else {
            $business = $v->value('business_name');
            db_query(
                'INSERT INTO provider_profiles (user_id, business_name, slug, headline, experience_years, city, service_area) VALUES (?, ?, ?, ?, ?, ?, ?)',
                [$userId, $business, unique_provider_slug($business), $v->value('headline') ?: null, (int) $v->value('experience_years'),
                 $v->value('city'), $v->value('city')]
            );
            $providerId = (int) $pdo->lastInsertId();
            db_query('INSERT INTO provider_categories (provider_id, category_id) VALUES (?, ?)', [$providerId, (int) $v->value('category_id')]);
            // Sensible default schedule: Mon–Fri 09:00–17:00 with a lunch break
            $insert = $pdo->prepare('INSERT INTO availability (provider_id, day_of_week, is_working, start_time, end_time, break_start, break_end) VALUES (?, ?, ?, ?, ?, ?, ?)');
            for ($d = 0; $d <= 6; $d++) {
                $insert->execute([$providerId, $d, $d >= 1 && $d <= 5 ? 1 : 0, '09:00:00', '17:00:00', '12:00:00', '12:30:00']);
            }
        }
        $pdo->commit();
    } catch (Throwable $e) {
        $pdo->rollBack();
        throw $e;
    }

    $user = db_one('SELECT * FROM users WHERE id = ?', [$userId]);
    login_user($user);
    $name = $v->value('first_name') . ' ' . $v->value('last_name');
    log_activity($userId, 'register', ($role === ROLE_PROVIDER ? 'New provider registered: ' . $v->value('business_name') : "New customer registered: $name"), 'user', $userId);

    if ($role === ROLE_PROVIDER) {
        notify($userId, 'welcome', 'Welcome to LemboFix 👋', 'Add your services and availability while our team reviews your profile.', 'provider/services.php');
        notify_admins('provider_pending', 'Provider awaiting approval', $v->value('business_name') . ' registered and is waiting for review.', 'admin/providers.php?status=pending');
        flash('success', 'Your provider account is ready! Complete your profile while we review it.');
        redirect('provider/dashboard.php');
    }
    notify($userId, 'welcome', 'Welcome to LemboFix 👋', 'Find trusted professionals and book in minutes.', 'search.php');
    flash('success', 'Welcome to LemboFix, ' . $v->value('first_name') . '!');
    redirect(intended_url(ROLE_CUSTOMER));
}

$role = old('role', $role) === ROLE_PROVIDER ? ROLE_PROVIDER : ROLE_CUSTOMER;
page_start(['title' => 'Create your account', 'description' => 'Create a free LemboFix account to book trusted professionals or offer your services.', 'noindex' => true]);
?>
<div class="auth">
    <div class="auth-panel">
        <div class="auth-box">
            <h1>Create your account</h1>
            <p>Free forever for customers. Takes less than a minute.</p>

            <?php if (form_errors()): ?>
                <div class="alert alert-danger" role="alert"><?= icon('x-circle', 20) ?><div>Please fix the highlighted fields below.</div></div>
            <?php endif; ?>

            <form method="post" action="<?= e(url('register.php')) ?>" data-validate novalidate data-register>
                <?= csrf_field() ?>
                <div class="role-switch" role="radiogroup" aria-label="Account type">
                    <label class="role-option">
                        <input type="radio" name="role" value="customer"<?= checked($role === ROLE_CUSTOMER) ?>>
                        <?= icon('user', 20) ?>
                        <div><strong>I need a service</strong><span>Find and book professionals</span></div>
                    </label>
                    <label class="role-option">
                        <input type="radio" name="role" value="provider"<?= checked($role === ROLE_PROVIDER) ?>>
                        <?= icon('briefcase', 20) ?>
                        <div><strong>I offer services</strong><span>Get bookings as a pro</span></div>
                    </label>
                </div>

                <div class="form-row">
                    <label class="field<?= error_class('first_name') ?>">
                        <span class="field-label">First name</span>
                        <input class="input" type="text" name="first_name" value="<?= e(old('first_name')) ?>" required minlength="2" maxlength="60" autocomplete="given-name">
                        <?= field_error('first_name') ?>
                    </label>
                    <label class="field<?= error_class('last_name') ?>">
                        <span class="field-label">Last name</span>
                        <input class="input" type="text" name="last_name" value="<?= e(old('last_name')) ?>" required minlength="2" maxlength="60" autocomplete="family-name">
                        <?= field_error('last_name') ?>
                    </label>
                </div>

                <fieldset class="provider-fields" data-provider-fields<?= $role === ROLE_PROVIDER ? '' : ' hidden disabled' ?> style="border:0;padding:0;margin:0">
                    <label class="field<?= error_class('business_name') ?>">
                        <span class="field-label">Business name</span>
                        <input class="input" type="text" name="business_name" value="<?= e(old('business_name')) ?>" required minlength="3" maxlength="120" placeholder="e.g. Smith Plumbing" autocomplete="organization">
                        <?= field_error('business_name') ?>
                    </label>
                    <div class="form-row">
                        <label class="field<?= error_class('category_id') ?>">
                            <span class="field-label">Main category</span>
                            <select class="select" name="category_id" required>
                                <option value="">Choose…</option>
                                <?php foreach ($categories as $c): ?>
                                    <option value="<?= (int) $c['id'] ?>"<?= selected($c['id'], old('category_id')) ?>><?= e($c['name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                            <?= field_error('category_id') ?>
                        </label>
                        <label class="field<?= error_class('experience_years') ?>">
                            <span class="field-label">Years of experience</span>
                            <input class="input" type="number" name="experience_years" value="<?= e(old('experience_years', '1')) ?>" min="0" max="60" required>
                            <?= field_error('experience_years') ?>
                        </label>
                    </div>
                    <label class="field<?= error_class('headline') ?>">
                        <span class="field-label">Short headline <span class="text-muted">(optional)</span></span>
                        <input class="input" type="text" name="headline" value="<?= e(old('headline')) ?>" maxlength="160" placeholder="e.g. Licensed electrician for homes and offices">
                        <?= field_error('headline') ?>
                    </label>
                </fieldset>

                <label class="field<?= error_class('email') ?>">
                    <span class="field-label">Email</span>
                    <input class="input" type="email" name="email" value="<?= e(old('email')) ?>" required maxlength="190" autocomplete="email" data-email-check>
                    <?= field_error('email') ?>
                </label>
                <div class="form-row">
                    <label class="field<?= error_class('phone') ?>">
                        <span class="field-label">Phone <span class="text-muted">(optional)</span></span>
                        <input class="input" type="tel" name="phone" value="<?= e(old('phone')) ?>" maxlength="20" pattern="\+?[0-9 ()\-]{6,20}" autocomplete="tel" placeholder="+383 44 123 456">
                        <?= field_error('phone') ?>
                    </label>
                    <label class="field<?= error_class('city') ?>">
                        <span class="field-label">City</span>
                        <input class="input" type="text" name="city" value="<?= e(old('city')) ?>" required minlength="2" maxlength="80" autocomplete="address-level2" list="register-cities">
                        <datalist id="register-cities"><?php foreach (provider_cities() as $c): ?><option value="<?= e($c) ?>"><?php endforeach; ?></datalist>
                        <?= field_error('city') ?>
                    </label>
                </div>
                <label class="field<?= error_class('password') ?>">
                    <span class="field-label">Password</span>
                    <div class="input-group">
                        <?= icon('lock', 18) ?>
                        <input class="input" type="password" name="password" required minlength="8" maxlength="200" autocomplete="new-password">
                        <button type="button" class="input-action" aria-label="Show password" data-password-toggle><?= icon('eye', 18) ?></button>
                    </div>
                    <div class="password-meter" data-password-meter="password" data-score="0" aria-hidden="true"><i></i><i></i><i></i><i></i></div>
                    <span class="field-hint">At least 8 characters with letters and numbers.</span>
                    <?= field_error('password') ?>
                </label>
                <label class="field<?= error_class('password_confirm') ?>">
                    <span class="field-label">Confirm password</span>
                    <input class="input" type="password" name="password_confirm" required minlength="8" maxlength="200" autocomplete="new-password" data-match="password">
                    <?= field_error('password_confirm') ?>
                </label>
                <label class="field<?= error_class('terms') ?>">
                    <span class="check"><input type="checkbox" name="terms" value="1" required> I agree to the LemboFix terms of service and privacy policy.</span>
                    <?= field_error('terms') ?>
                </label>
                <button class="btn btn-primary btn-lg btn-block" type="submit">Create account</button>
            </form>
            <p class="auth-switch">Already have an account? <a class="link" href="<?= e(url('login.php')) ?>">Log in</a></p>
        </div>
    </div>
    <?php partial('auth-aside', ['asideTitle' => 'Join thousands of happy customers and pros.', 'asideText' => 'Customers book trusted help in minutes. Professionals fill their calendar with local jobs.']); ?>
</div>
<?php page_end(); ?>
