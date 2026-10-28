<?php
/**
 * Account settings shared by customers and providers (avatar + password).
 * Each handler validates input, applies the change and redirects (PRG).
 */

declare(strict_types=1);

function handle_avatar_form(array $user, string $returnTo): never
{
    if (!empty($_POST['remove_avatar'])) {
        delete_uploaded_file($user['avatar']);
        db_query('UPDATE users SET avatar = NULL WHERE id = ?', [$user['id']]);
        flash('success', 'Profile photo removed.');
        redirect($returnTo);
    }
    $result = store_uploaded_image($_FILES['avatar'] ?? [], 'avatars');
    if (!$result['ok']) {
        with_form_state([], ['avatar' => $result['error']]);
        flash('error', $result['error']);
        redirect($returnTo);
    }
    delete_uploaded_file($user['avatar']);
    db_query('UPDATE users SET avatar = ? WHERE id = ?', [$result['path'], $user['id']]);
    log_activity((int) $user['id'], 'avatar_update', 'Updated profile photo', 'user', (int) $user['id']);
    flash('success', 'Profile photo updated.');
    redirect($returnTo);
}

function handle_password_form(array $user, string $returnTo): never
{
    $v = new Validator($_POST);
    $v->required('current_password', 'Current password')
      ->required('password', 'New password')->password('password')
      ->matches('password_confirm', 'password', 'Passwords do not match.');

    $hash = (string) db_value('SELECT password_hash FROM users WHERE id = ?', [$user['id']]);
    if (!$v->fails() && !password_verify($v->value('current_password'), $hash)) {
        $v->custom(true, 'current_password', 'Your current password is incorrect.');
    }
    if ($v->fails()) {
        with_form_state(['_form' => 'password'], $v->errors());
        flash('error', $v->firstError());
        redirect($returnTo . '#security');
    }
    db_query('UPDATE users SET password_hash = ? WHERE id = ?', [password_hash($v->value('password'), PASSWORD_DEFAULT), $user['id']]);
    session_regenerate_id(true);
    log_activity((int) $user['id'], 'password_change', 'Changed account password', 'user', (int) $user['id']);
    flash('success', 'Your password has been changed.');
    redirect($returnTo);
}

/** Card with photo upload + password change, used on both profile pages. */
function render_account_security(array $user, string $action): void
{
    ?>
    <div class="card dash-section">
        <div class="card-head"><h2>Profile photo</h2></div>
        <div class="card-body">
            <form method="post" action="<?= e(url($action)) ?>" enctype="multipart/form-data">
                <?= csrf_field() ?>
                <input type="hidden" name="form" value="avatar">
                <input type="hidden" name="MAX_FILE_SIZE" value="<?= (int) config('uploads.max_bytes') ?>">
                <div class="file-drop">
                    <span id="avatar-preview"><?= avatar_html($user['full_name'], $user['avatar'], 'lg') ?></span>
                    <div>
                        <input type="file" name="avatar" accept="image/jpeg,image/png,image/webp" data-image-preview="#avatar-preview > *" required aria-label="Choose profile photo">
                        <span class="field-hint">JPG, PNG or WebP · max <?= round(config('uploads.max_bytes') / 1048576) ?> MB · at least 64×64 px</span>
                        <?= field_error('avatar') ?>
                    </div>
                </div>
                <div class="form-actions mt-2">
                    <button type="submit" class="btn btn-secondary btn-sm"><?= icon('upload', 16) ?> Upload photo</button>
                </div>
            </form>
            <?php if ($user['avatar']): ?>
                <form method="post" action="<?= e(url($action)) ?>" class="mt-1">
                    <?= csrf_field() ?>
                    <input type="hidden" name="form" value="avatar">
                    <input type="hidden" name="remove_avatar" value="1">
                    <button type="submit" class="link-btn text-danger" data-confirm="Remove your photo?" data-confirm-ok="Remove">Remove current photo</button>
                </form>
            <?php endif; ?>
        </div>
    </div>

    <div class="card dash-section" id="security">
        <div class="card-head"><h2>Password &amp; security</h2></div>
        <div class="card-body">
            <form method="post" action="<?= e(url($action)) ?>" data-validate novalidate>
                <?= csrf_field() ?>
                <input type="hidden" name="form" value="password">
                <label class="field<?= error_class('current_password') ?>">
                    <span class="field-label">Current password</span>
                    <input class="input" type="password" name="current_password" required autocomplete="current-password">
                    <?= field_error('current_password') ?>
                </label>
                <div class="form-row">
                    <label class="field<?= error_class('password') ?>">
                        <span class="field-label">New password</span>
                        <input class="input" type="password" name="password" required minlength="8" autocomplete="new-password">
                        <div class="password-meter" data-password-meter="password" data-score="0" aria-hidden="true"><i></i><i></i><i></i><i></i></div>
                        <?= field_error('password') ?>
                    </label>
                    <label class="field<?= error_class('password_confirm') ?>">
                        <span class="field-label">Confirm new password</span>
                        <input class="input" type="password" name="password_confirm" required minlength="8" autocomplete="new-password" data-match="password">
                        <?= field_error('password_confirm') ?>
                    </label>
                </div>
                <button type="submit" class="btn btn-dark btn-sm"><?= icon('lock', 16) ?> Change password</button>
            </form>
        </div>
    </div>
    <?php
}
