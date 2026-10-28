<?php
require dirname(__DIR__) . '/includes/bootstrap.php';
$user = require_login(ROLE_CUSTOMER);
$self = 'customer/profile.php';

if (is_post()) {
    require_csrf();
    switch (input('form')) {
        case 'avatar':
            handle_avatar_form($user, $self);
        case 'password':
            handle_password_form($user, $self);
        case 'profile':
            $v = new Validator($_POST);
            $v->required('first_name', 'First name')->length('first_name', 'First name', 2, 60)
              ->required('last_name', 'Last name')->length('last_name', 'Last name', 2, 60)
              ->phone('phone')
              ->required('city', 'City')->length('city', 'City', 2, 80)
              ->length('address', 'Address', 0, 255)
              ->length('postal_code', 'Postal code', 0, 20)
              ->in('preferred_contact', ['email', 'phone']);
            if ($v->fails()) {
                with_form_state($_POST, $v->errors());
                flash('error', 'Please fix the highlighted fields.');
                redirect($self);
            }
            db_query('UPDATE users SET first_name = ?, last_name = ?, phone = ?, city = ? WHERE id = ?',
                [$v->value('first_name'), $v->value('last_name'), $v->value('phone') ?: null, $v->value('city'), $user['id']]);
            db_query(
                'INSERT INTO customer_profiles (user_id, address, postal_code, preferred_contact) VALUES (?, ?, ?, ?)
                 ON DUPLICATE KEY UPDATE address = VALUES(address), postal_code = VALUES(postal_code), preferred_contact = VALUES(preferred_contact)',
                [$user['id'], $v->value('address') ?: null, $v->value('postal_code') ?: null, $v->value('preferred_contact')]
            );
            flash('success', 'Your profile has been saved.');
            redirect($self);
    }
}

$profile = db_one('SELECT * FROM customer_profiles WHERE user_id = ?', [$user['id']]) ?? ['address' => '', 'postal_code' => '', 'preferred_contact' => 'email'];
$hasOld = old('first_name') !== '';
$val = fn ($key, $fallback) => $hasOld ? old($key) : (string) $fallback;

dashboard_start(['title' => 'Profile & settings', 'subtitle' => 'Keep your details up to date for faster bookings.', 'active' => 'profile']);
?>
<div class="dash-grid">
    <div>
        <div class="card dash-section">
            <div class="card-head"><h2>Personal details</h2></div>
            <div class="card-body">
                <form method="post" action="<?= e(url($self)) ?>" data-validate novalidate>
                    <?= csrf_field() ?>
                    <input type="hidden" name="form" value="profile">
                    <div class="form-row">
                        <label class="field<?= error_class('first_name') ?>"><span class="field-label">First name</span>
                            <input class="input" name="first_name" value="<?= e($val('first_name', $user['first_name'])) ?>" required minlength="2" maxlength="60" autocomplete="given-name"><?= field_error('first_name') ?></label>
                        <label class="field<?= error_class('last_name') ?>"><span class="field-label">Last name</span>
                            <input class="input" name="last_name" value="<?= e($val('last_name', $user['last_name'])) ?>" required minlength="2" maxlength="60" autocomplete="family-name"><?= field_error('last_name') ?></label>
                    </div>
                    <label class="field"><span class="field-label">Email</span>
                        <input class="input" value="<?= e($user['email']) ?>" disabled><span class="field-hint">Contact support to change your login email.</span></label>
                    <div class="form-row">
                        <label class="field<?= error_class('phone') ?>"><span class="field-label">Phone</span>
                            <input class="input" type="tel" name="phone" value="<?= e($val('phone', $user['phone'])) ?>" maxlength="20" pattern="\+?[0-9 ()\-]{6,20}" autocomplete="tel"><?= field_error('phone') ?></label>
                        <label class="field<?= error_class('city') ?>"><span class="field-label">City</span>
                            <input class="input" name="city" value="<?= e($val('city', $user['city'])) ?>" required minlength="2" maxlength="80" autocomplete="address-level2"><?= field_error('city') ?></label>
                    </div>
                    <div class="form-row">
                        <label class="field<?= error_class('address') ?>"><span class="field-label">Default address</span>
                            <input class="input" name="address" value="<?= e($val('address', $profile['address'])) ?>" maxlength="255" autocomplete="street-address"><?= field_error('address') ?></label>
                        <label class="field<?= error_class('postal_code') ?>"><span class="field-label">Postal code</span>
                            <input class="input" name="postal_code" value="<?= e($val('postal_code', $profile['postal_code'])) ?>" maxlength="20" autocomplete="postal-code"><?= field_error('postal_code') ?></label>
                    </div>
                    <label class="field"><span class="field-label">Preferred contact</span>
                        <select class="select" name="preferred_contact">
                            <option value="email"<?= selected('email', $val('preferred_contact', $profile['preferred_contact'])) ?>>Email</option>
                            <option value="phone"<?= selected('phone', $val('preferred_contact', $profile['preferred_contact'])) ?>>Phone</option>
                        </select></label>
                    <button type="submit" class="btn btn-primary"><?= icon('check', 18) ?> Save changes</button>
                </form>
            </div>
        </div>
    </div>
    <div>
        <?php render_account_security($user, $self); ?>
    </div>
</div>
<?php dashboard_end(); ?>
