<?php
/**
 * Page shells. Public pages call page_start()/page_end(); dashboard pages call
 * dashboard_start()/dashboard_end(), which add the role sidebar.
 */

declare(strict_types=1);

function page_start(array $opts = []): void
{
    $page = array_merge([
        'title'       => '',
        'description' => 'LemboFix connects you with trusted local professionals. Compare providers, check real availability and book plumbers, electricians, cleaners and more online.',
        'body_class'  => '',
        'active'      => '',
        'scripts'     => [],
        'noindex'     => false,
    ], $opts);
    $GLOBALS['__page'] = $page;
    require APP_ROOT . '/includes/header.php';
}

function page_end(): void
{
    $page = $GLOBALS['__page'] ?? ['scripts' => [], 'body_class' => ''];
    require APP_ROOT . '/includes/footer.php';
}

/** Sidebar items for each role: key => [label, url, icon, badge]. */
function dashboard_nav(string $role): array
{
    $uid = user_id();
    return match ($role) {
        ROLE_CUSTOMER => [
            'dashboard'     => ['Overview', 'customer/dashboard.php', 'dashboard', 0],
            'bookings'      => ['My bookings', 'customer/bookings.php', 'calendar', (int) db_value("SELECT COUNT(*) FROM bookings WHERE customer_id = ? AND status IN ('pending','confirmed','in_progress') AND booking_date >= CURDATE()", [$uid])],
            'favorites'     => ['Favorites', 'customer/favorites.php', 'heart', 0],
            'reviews'       => ['My reviews', 'customer/reviews.php', 'star', 0],
            'notifications' => ['Notifications', 'notifications.php', 'bell', 0],
            'profile'       => ['Profile & settings', 'customer/profile.php', 'settings', 0],
        ],
        ROLE_PROVIDER => [
            'dashboard'     => ['Overview', 'provider/dashboard.php', 'dashboard', 0],
            'bookings'      => ['Bookings', 'provider/bookings.php', 'calendar', (int) db_value("SELECT COUNT(*) FROM bookings WHERE provider_id = ? AND status = 'pending'", [(int) (current_provider()['id'] ?? 0)])],
            'services'      => ['Services', 'provider/services.php', 'layers', 0],
            'availability'  => ['Availability', 'provider/availability.php', 'clock', 0],
            'customers'     => ['Customers', 'provider/customers.php', 'users', 0],
            'reviews'       => ['Reviews', 'provider/reviews.php', 'star', 0],
            'notifications' => ['Notifications', 'notifications.php', 'bell', 0],
            'profile'       => ['Business profile', 'provider/profile.php', 'settings', 0],
        ],
        ROLE_ADMIN => [
            'dashboard'     => ['Overview', 'admin/dashboard.php', 'dashboard', 0],
            'users'         => ['Users', 'admin/users.php', 'users', 0],
            'providers'     => ['Providers', 'admin/providers.php', 'briefcase', (int) db_value("SELECT COUNT(*) FROM provider_profiles WHERE approval_status = 'pending'")],
            'bookings'      => ['Bookings', 'admin/bookings.php', 'calendar', 0],
            'categories'    => ['Categories', 'admin/categories.php', 'grid', 0],
            'services'      => ['Service catalog', 'admin/services.php', 'layers', 0],
            'reviews'       => ['Reviews', 'admin/reviews.php', 'star', 0],
            'reports'       => ['Reports', 'admin/reports.php', 'flag', (int) db_value("SELECT COUNT(*) FROM reports WHERE status = 'open'")],
            'activity'      => ['Activity log', 'admin/activity.php', 'activity', 0],
            'notifications' => ['Notifications', 'notifications.php', 'bell', 0],
        ],
        default => [],
    };
}

function dashboard_start(array $opts): void
{
    $user = current_user();
    $opts['body_class'] = trim(($opts['body_class'] ?? '') . ' is-dashboard');
    $opts['noindex'] = true;
    page_start($opts);

    $nav = dashboard_nav($user['role']);
    $active = $opts['active'] ?? '';
    $roleLabel = ['customer' => 'Customer', 'provider' => 'Service provider', 'admin' => 'Administrator'][$user['role']];
    ?>
    <div class="dash">
        <aside class="dash-sidebar" aria-label="Dashboard navigation">
            <div class="dash-user">
                <?= avatar_html($user['full_name'], $user['avatar'], 'md') ?>
                <div>
                    <strong><?= e($user['role'] === ROLE_PROVIDER ? current_provider()['business_name'] : $user['full_name']) ?></strong>
                    <span><?= e($roleLabel) ?></span>
                </div>
            </div>
            <nav class="dash-nav">
                <?php foreach ($nav as $key => [$label, $href, $ico, $badge]): ?>
                    <a href="<?= e(url($href)) ?>" class="dash-link<?= $key === $active ? ' is-active' : '' ?>"<?= $key === $active ? ' aria-current="page"' : '' ?>>
                        <?= icon($ico, 18) ?><span><?= e($label) ?></span>
                        <?php if ($badge > 0): ?><span class="dash-badge"><?= $badge ?></span><?php endif; ?>
                    </a>
                <?php endforeach; ?>
            </nav>
            <?php if ($user['role'] === ROLE_PROVIDER && current_provider()['approval_status'] === 'approved'): ?>
                <a class="dash-public-link" href="<?= e(url('provider.php?slug=' . current_provider()['slug'])) ?>"><?= icon('eye', 16) ?> View public profile</a>
            <?php elseif ($user['role'] === ROLE_CUSTOMER): ?>
                <a class="dash-public-link" href="<?= e(url('search.php')) ?>"><?= icon('search', 16) ?> Find a professional</a>
            <?php endif; ?>
        </aside>
        <div class="dash-main">
            <nav class="dash-tabs" aria-label="Dashboard sections">
                <?php foreach ($nav as $key => [$label, $href, $ico, $badge]): ?>
                    <a href="<?= e(url($href)) ?>" class="dash-tab<?= $key === $active ? ' is-active' : '' ?>"><?= icon($ico, 16) ?><?= e($label) ?><?php if ($badge > 0): ?><span class="dash-badge"><?= $badge ?></span><?php endif; ?></a>
                <?php endforeach; ?>
            </nav>
            <?php if ($user['role'] === ROLE_PROVIDER && current_provider()['approval_status'] !== 'approved'): ?>
                <div class="alert alert-<?= current_provider()['approval_status'] === 'rejected' ? 'danger' : 'warning' ?>">
                    <?= icon(current_provider()['approval_status'] === 'rejected' ? 'x-circle' : 'clock', 20) ?>
                    <div>
                        <?php if (current_provider()['approval_status'] === 'rejected'): ?>
                            <strong>Your profile was not approved.</strong>
                            <?= e(current_provider()['rejection_reason'] ?: 'Please update your profile and contact support.') ?>
                        <?php else: ?>
                            <strong>Your profile is under review.</strong>
                            Set up your services and availability now — customers will see you as soon as an admin approves your account.
                        <?php endif; ?>
                    </div>
                </div>
            <?php endif; ?>
            <header class="dash-header">
                <div>
                    <h1><?= e($opts['title'] ?? '') ?></h1>
                    <?php if (!empty($opts['subtitle'])): ?><p><?= e($opts['subtitle']) ?></p><?php endif; ?>
                </div>
                <?php if (!empty($opts['actions'])): ?><div class="dash-header-actions"><?= $opts['actions'] ?></div><?php endif; ?>
            </header>
    <?php
}

function dashboard_end(): void
{
    echo '</div></div>';
    page_end();
}

/** Friendly full-page error (403, 404, 500) and stop. */
function render_error_page(int $code, string $message = ''): never
{
    http_response_code($code);
    $content = [
        403 => ['Access denied', 'You don\'t have permission to view this page.', 'lock'],
        404 => ['Page not found', 'We couldn\'t find the page you were looking for. It may have been moved or removed.', 'search'],
        500 => ['Something went wrong', 'We hit an unexpected problem. Please try again in a moment.', 'alert'],
    ][$code] ?? ['Error', 'Something went wrong.', 'alert'];

    if (session_status() !== PHP_SESSION_ACTIVE || headers_sent()) {
        echo '<h1>' . e($content[0]) . '</h1><p>' . e($message ?: $content[1]) . '</p>';
        exit;
    }
    page_start(['title' => $content[0], 'body_class' => 'is-error', 'noindex' => true]);
    ?>
    <section class="error-page container">
        <div class="error-code"><?= $code ?></div>
        <div class="error-icon"><?= icon($content[2], 32) ?></div>
        <h1><?= e($content[0]) ?></h1>
        <p><?= e($message ?: $content[1]) ?></p>
        <div class="error-actions">
            <a class="btn btn-primary" href="<?= e(url('index.php')) ?>"><?= icon('home', 18) ?> Back to home</a>
            <?php if ($code === 403 && !is_logged_in()): ?>
                <a class="btn btn-secondary" href="<?= e(url('login.php')) ?>">Sign in</a>
            <?php else: ?>
                <a class="btn btn-secondary" href="<?= e(url('search.php')) ?>">Find a professional</a>
            <?php endif; ?>
        </div>
    </section>
    <?php
    page_end();
    exit;
}
