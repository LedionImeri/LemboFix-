<?php
require __DIR__ . '/includes/bootstrap.php';
require_guest();

$error = '';
if (is_post()) {
    require_csrf();
    $email = strtolower(input('email'));
    $password = (string) ($_POST['password'] ?? '');

    if ($email === '' || $password === '') {
        $error = 'Please enter your email and password.';
    } else {
        [$user, $error] = attempt_login($email, $password);
        if ($user) {
            login_user($user);
            log_activity((int) $user['id'], 'login', "{$user['first_name']} {$user['last_name']} signed in", 'user', (int) $user['id']);
            flash('success', 'Welcome back, ' . $user['first_name'] . '!');
            redirect(intended_url($user['role']));
        }
    }
}

$demoAccounts = [
    ['Customer', 'customer@lembofix.com', 'Ardit Krasniqi — bookings, favorites, reviews', 'user'],
    ['Provider', 'provider@lembofix.com', 'Alex Plumbing — requests, calendar, earnings', 'briefcase'],
    ['Admin', 'admin@lembofix.com', 'Platform admin — approvals, reports, stats', 'shield'],
];

page_start(['title' => 'Log in', 'description' => 'Log in to LemboFix to manage your bookings.', 'noindex' => true]);
?>
<div class="auth">
    <div class="auth-panel">
        <div class="auth-box">
            <h1>Welcome back</h1>
            <p>Log in to manage your bookings and messages.</p>

            <?php if ($error): ?>
                <div class="alert alert-danger" role="alert"><?= icon('x-circle', 20) ?><div><?= e($error) ?></div></div>
            <?php endif; ?>

            <form method="post" action="<?= e(url('login.php')) ?>" data-validate data-login novalidate>
                <?= csrf_field() ?>
                <label class="field">
                    <span class="field-label">Email</span>
                    <div class="input-group">
                        <?= icon('mail', 18) ?>
                        <input class="input" type="email" name="email" value="<?= e(input('email')) ?>" required autocomplete="email" maxlength="190" autofocus>
                    </div>
                </label>
                <label class="field">
                    <span class="field-label field-label-row">Password <a class="link text-xs" href="<?= e(url('forgot-password.php')) ?>">Forgot password?</a></span>
                    <div class="input-group">
                        <?= icon('lock', 18) ?>
                        <input class="input" type="password" name="password" required autocomplete="current-password" maxlength="200">
                        <button type="button" class="input-action" aria-label="Show password" data-password-toggle><?= icon('eye', 18) ?></button>
                    </div>
                </label>
                <button class="btn btn-primary btn-lg btn-block" type="submit">Log in</button>
            </form>

            <div class="auth-divider">or try a demo account</div>
            <div class="demo-accounts">
                <?php foreach ($demoAccounts as [$label, $email, $desc, $ico]): ?>
                    <button type="button" class="demo-account" data-demo-email="<?= e($email) ?>" data-demo-password="Demo@1234">
                        <span class="stat-icon tone-brand mb-0"><?= icon($ico, 18) ?></span>
                        <div><strong><?= e($label) ?></strong><span><?= e($desc) ?></span></div>
                        <?= icon('arrow-right', 18) ?>
                    </button>
                <?php endforeach; ?>
            </div>

            <p class="auth-switch">New to LemboFix? <a class="link" href="<?= e(url('register.php')) ?>">Create a free account</a></p>
        </div>
    </div>
    <?php partial('auth-aside'); ?>
</div>
<?php page_end(); ?>
