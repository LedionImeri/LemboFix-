<?php
require dirname(__DIR__) . '/includes/bootstrap.php';
$user = require_login(ROLE_PROVIDER);
$provider = current_provider();
$pid = (int) $provider['id'];
$self = 'provider/profile.php';
$categories = db_all('SELECT id, name, icon FROM categories WHERE is_active = 1 ORDER BY sort_order');

if (is_post()) {
    require_csrf();
    switch (input('form')) {
        case 'avatar':
            handle_avatar_form($user, $self);
        case 'password':
            handle_password_form($user, $self);
        case 'business':
            $v = new Validator($_POST);
            $v->required('business_name', 'Business name')->length('business_name', 'Business name', 3, 120)
              ->length('headline', 'Headline', 0, 160)
              ->length('bio', 'About', 0, 3000)
              ->number('experience_years', 'Years of experience', 0, 60)
              ->required('city', 'City')->length('city', 'City', 2, 80)
              ->length('service_area', 'Service area', 0, 160)
              ->length('address', 'Address', 0, 255)
              ->required('first_name', 'First name')->length('first_name', 'First name', 2, 60)
              ->required('last_name', 'Last name')->length('last_name', 'Last name', 2, 60)
              ->phone('phone');
            $validCategoryIds = array_map('intval', array_column($categories, 'id'));
            $chosen = array_values(array_intersect(array_map('intval', (array) ($_POST['categories'] ?? [])), $validCategoryIds));
            $v->custom($chosen === [], 'categories', 'Choose at least one category.');
            if ($v->fails()) {
                with_form_state($_POST, $v->errors());
                flash('error', $v->firstError());
                redirect($self);
            }
            $pdo = db();
            $pdo->beginTransaction();
            db_query(
                'UPDATE provider_profiles SET business_name = ?, slug = ?, headline = ?, bio = ?, experience_years = ?, city = ?, service_area = ?, address = ? WHERE id = ?',
                [$v->value('business_name'),
                 $v->value('business_name') === $provider['business_name'] ? $provider['slug'] : unique_provider_slug($v->value('business_name'), $pid),
                 $v->value('headline') ?: null, $v->value('bio') ?: null, (int) $v->value('experience_years'), $v->value('city'),
                 $v->value('service_area') ?: null, $v->value('address') ?: null, $pid]
            );
            db_query('UPDATE users SET first_name = ?, last_name = ?, phone = ?, city = ? WHERE id = ?',
                [$v->value('first_name'), $v->value('last_name'), $v->value('phone') ?: null, $v->value('city'), $user['id']]);
            db_query('DELETE FROM provider_categories WHERE provider_id = ?', [$pid]);
            $insert = $pdo->prepare('INSERT INTO provider_categories (provider_id, category_id) VALUES (?, ?)');
            foreach ($chosen as $cid) {
                $insert->execute([$pid, $cid]);
            }
            $pdo->commit();
            log_activity((int) $user['id'], 'provider_profile_update', 'Updated business profile', 'provider', $pid);
            flash('success', 'Your business profile has been saved.');
            redirect($self);
    }
}

$selectedCategories = array_map('intval', array_column(db_all('SELECT category_id FROM provider_categories WHERE provider_id = ?', [$pid]), 'category_id'));
$hasOld = old('business_name') !== '';
$val = fn ($key, $fallback) => $hasOld ? old($key) : (string) $fallback;

dashboard_start([
    'title'    => 'Business profile',
    'subtitle' => 'This is what customers see on your public page.',
    'active'   => 'profile',
    'actions'  => $provider['approval_status'] === 'approved'
        ? '<a class="btn btn-secondary" href="' . e(url('provider.php?slug=' . $provider['slug'])) . '">' . icon('eye', 18) . ' Preview</a>' : '',
]);
?>
<div class="dash-grid">
    <form class="card dash-section" method="post" action="<?= e(url($self)) ?>" data-validate novalidate>
        <?= csrf_field() ?>
        <input type="hidden" name="form" value="business">
        <div class="card-head">
            <h2>Business details</h2>
            <span class="flex gap-1"><?= status_badge($provider['approval_status']) ?><?= $provider['is_verified'] ? verified_badge() : '' ?></span>
        </div>
        <div class="card-body">
            <label class="field<?= error_class('business_name') ?>"><span class="field-label">Business name</span>
                <input class="input" name="business_name" value="<?= e($val('business_name', $provider['business_name'])) ?>" required minlength="3" maxlength="120"><?= field_error('business_name') ?></label>
            <label class="field<?= error_class('headline') ?>"><span class="field-label">Headline</span>
                <input class="input" name="headline" value="<?= e($val('headline', $provider['headline'])) ?>" maxlength="160" placeholder="One line that sums up what you do best"><?= field_error('headline') ?></label>
            <label class="field<?= error_class('bio') ?>"><span class="field-label">About your business</span>
                <textarea class="textarea" name="bio" rows="6" maxlength="3000" data-count="#bio-count" placeholder="Experience, specialities, guarantees, what customers can expect…"><?= e($val('bio', $provider['bio'])) ?></textarea>
                <span class="field-hint" id="bio-count"></span><?= field_error('bio') ?></label>

            <fieldset class="field<?= error_class('categories') ?>" style="border:0;padding:0">
                <legend class="field-label">Service categories</legend>
                <div class="tag-list" style="margin-top:4px">
                    <?php foreach ($categories as $c): ?>
                        <label class="chip" style="cursor:pointer"><input type="checkbox" name="categories[]" value="<?= (int) $c['id'] ?>" style="accent-color:var(--brand-500)"<?= checked(in_array((int) $c['id'], $selectedCategories, true)) ?>> <?= e($c['name']) ?></label>
                    <?php endforeach; ?>
                </div>
                <?= field_error('categories') ?>
            </fieldset>

            <div class="form-row">
                <label class="field<?= error_class('experience_years') ?>"><span class="field-label">Years of experience</span>
                    <input class="input" type="number" name="experience_years" min="0" max="60" value="<?= e($val('experience_years', $provider['experience_years'])) ?>" required></label>
                <label class="field<?= error_class('city') ?>"><span class="field-label">Base city</span>
                    <input class="input" name="city" value="<?= e($val('city', $provider['city'])) ?>" required minlength="2" maxlength="80"><?= field_error('city') ?></label>
            </div>
            <label class="field<?= error_class('service_area') ?>"><span class="field-label">Service area</span>
                <input class="input" name="service_area" value="<?= e($val('service_area', $provider['service_area'])) ?>" maxlength="160" placeholder="e.g. Prishtina, Fushë Kosovë, Graçanica">
                <span class="field-hint">Customers searching these places will find you.</span></label>
            <label class="field"><span class="field-label">Business address <span class="text-muted">(optional, for services at your location)</span></span>
                <input class="input" name="address" value="<?= e($val('address', $provider['address'])) ?>" maxlength="255"></label>

            <h3 class="mt-3">Contact person</h3>
            <div class="form-row-3">
                <label class="field<?= error_class('first_name') ?>"><span class="field-label">First name</span>
                    <input class="input" name="first_name" value="<?= e($val('first_name', $user['first_name'])) ?>" required minlength="2" maxlength="60"></label>
                <label class="field<?= error_class('last_name') ?>"><span class="field-label">Last name</span>
                    <input class="input" name="last_name" value="<?= e($val('last_name', $user['last_name'])) ?>" required minlength="2" maxlength="60"></label>
                <label class="field<?= error_class('phone') ?>"><span class="field-label">Phone</span>
                    <input class="input" type="tel" name="phone" value="<?= e($val('phone', $user['phone'])) ?>" maxlength="20" pattern="\+?[0-9 ()\-]{6,20}"><?= field_error('phone') ?></label>
            </div>
            <button type="submit" class="btn btn-primary"><?= icon('check', 18) ?> Save profile</button>
        </div>
    </form>
    <div>
        <?php render_account_security($user, $self); ?>
    </div>
</div>
<?php dashboard_end(); ?>
