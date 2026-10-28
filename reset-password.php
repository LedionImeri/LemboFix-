<?php
require __DIR__ . '/includes/bootstrap.php';
require_guest();

$token = preg_replace('/[^a-f0-9]/', '', input('token'));
$reset = strlen($token) === 64
    ? db_one('SELECT * FROM password_resets WHERE token_hash = ? AND used_at IS NULL AND expires_at > NOW()', [hash('sha256', $token)])
    : null;

if ($reset && is_post()) {
    require_csrf();
    $v = new Validator($_POST);
    $v->required('password', 'Password')->password('password')
      ->matches('password_confirm', 'password', 'Passwords do not match.');
    if ($v->fails()) {
        with_form_state([], $v->errors());
        redirect('reset-password.php?token=' . $token);
    }
    db_query('UPDATE users SET password_hash = ? WHERE id = ?', [password_hash($v->value('password'), PASSWORD_DEFAULT), $reset['user_id']]);
    db_query('UPDATE password_resets SET used_at = NOW() WHERE id = ?', [$reset['id']]);
    log_activity((int) $reset['user_id'], 'password_reset', 'Password reset via email link', 'user', (int) $reset['user_id']);
    flash('success', 'Your password has been updated. You can log in now.');
    redirect('login.php');
}

page_start(['title' => 'Choose a new password', 'noindex' => true]);
?>
<div class="auth">
    <div class="auth-panel">
        <div class="auth-box">
            <?php if (!$reset): ?>
                <h1>Link expired</h1>
                <div class="alert alert-warning"><?= icon('alert', 20) ?><div>This password reset link is invalid or has expired. Please request a new one.</div></div>
                <a class="btn btn-primary btn-block" href="<?= e(url('forgot-password.php')) ?>">Request a new link</a>
            <?php else: ?>
                <h1>Choose a new password</h1>
                <p>Use at least 8 characters with letters and numbers.</p>
                <form method="post" action="<?= e(url('reset-password.php?token=' . $token)) ?>" data-validate novalidate>
                    <?= csrf_field() ?>
                    <label class="field<?= error_class('password') ?>">
                        <span class="field-label">New password</span>
                        <input class="input" type="password" name="password" required minlength="8" maxlength="200" autocomplete="new-password">
                        <div class="password-meter" data-password-meter="password" data-score="0" aria-hidden="true"><i></i><i></i><i></i><i></i></div>
                        <?= field_error('password') ?>
                    </label>
                    <label class="field<?= error_class('password_confirm') ?>">
                        <span class="field-label">Confirm new password</span>
                        <input class="input" type="password" name="password_confirm" required minlength="8" maxlength="200" autocomplete="new-password" data-match="password">
                        <?= field_error('password_confirm') ?>
                    </label>
                    <button class="btn btn-primary btn-lg btn-block" type="submit">Update password</button>
                </form>
            <?php endif; ?>
        </div>
    </div>
    <?php partial('auth-aside'); ?>
</div>
<?php page_end(); ?>
