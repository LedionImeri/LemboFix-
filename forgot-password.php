<?php
require __DIR__ . '/includes/bootstrap.php';
require_guest();

$sent = false;
$demoLink = null;
if (is_post()) {
    require_csrf();
    $email = strtolower(input('email'));
    $sent = true; // Always show the same message so emails can't be enumerated

    if (filter_var($email, FILTER_VALIDATE_EMAIL) && rate_limit_count('password_reset', client_ip(), 3600) < 5) {
        rate_limit_hit('password_reset', client_ip());
        $user = db_one("SELECT id FROM users WHERE email = ? AND status = 'active'", [$email]);
        if ($user) {
            $token = bin2hex(random_bytes(32));
            db_query('UPDATE password_resets SET used_at = NOW() WHERE user_id = ? AND used_at IS NULL', [$user['id']]);
            db_query(
                'INSERT INTO password_resets (user_id, token_hash, expires_at) VALUES (?, ?, NOW() + INTERVAL 1 HOUR)',
                [$user['id'], hash('sha256', $token)]
            );
            // No mail server in local/demo setups: show the link only in development mode
            if (config('app.env') !== 'production') {
                $demoLink = url('reset-password.php?token=' . $token);
            }
        }
    }
}

page_start(['title' => 'Reset your password', 'noindex' => true]);
?>
<div class="auth">
    <div class="auth-panel">
        <div class="auth-box">
            <h1>Forgot your password?</h1>
            <p>Enter your account email and we'll send you a secure link to choose a new one.</p>
            <?php if ($sent): ?>
                <div class="alert alert-success"><?= icon('check-circle', 20) ?><div>If an account exists for that email, a reset link is on its way. The link expires in 1 hour.</div></div>
                <?php if ($demoLink): ?>
                    <div class="alert alert-demo"><?= icon('info', 20) ?><div><strong>Development mode:</strong> emails aren't sent locally, so here is the link:<br><a class="link" href="<?= e($demoLink) ?>">Reset my password</a></div></div>
                <?php endif; ?>
            <?php endif; ?>
            <form method="post" action="<?= e(url('forgot-password.php')) ?>" data-validate novalidate>
                <?= csrf_field() ?>
                <label class="field">
                    <span class="field-label">Email</span>
                    <input class="input" type="email" name="email" required maxlength="190" autocomplete="email">
                </label>
                <button class="btn btn-primary btn-lg btn-block" type="submit">Send reset link</button>
            </form>
            <p class="auth-switch"><a class="link" href="<?= e(url('login.php')) ?>"><?= icon('arrow-left', 14) ?> Back to log in</a></p>
        </div>
    </div>
    <?php partial('auth-aside'); ?>
</div>
<?php page_end(); ?>
