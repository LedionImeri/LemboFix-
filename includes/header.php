<?php
/** @var array $page  set by page_start() */
$user = current_user();
$appName = config('app.name');
$fullTitle = $page['title'] ? $page['title'] . ' · ' . $appName : $appName . ' — Find trusted professionals for any job';
$unread = $user ? unread_notification_count((int) $user['id']) : 0;
$script = current_script();
$publicNav = [
    'search'     => ['Find services', 'search.php'],
    'categories' => ['Categories', 'categories.php'],
    'how'        => ['How it works', 'index.php#how-it-works'],
    'pros'       => ['For professionals', 'for-professionals.php'],
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <title><?= e($fullTitle) ?></title>
    <meta name="description" content="<?= e($page['description']) ?>">
    <?php if ($page['noindex']): ?><meta name="robots" content="noindex"><?php endif; ?>
    <meta name="theme-color" content="#0E1726">
    <meta name="csrf-token" content="<?= e(csrf_token()) ?>">
    <meta property="og:title" content="<?= e($fullTitle) ?>">
    <meta property="og:description" content="<?= e($page['description']) ?>">
    <meta property="og:type" content="website">
    <link rel="icon" type="image/svg+xml" href="<?= e(asset('images/favicon.svg')) ?>">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Plus+Jakarta+Sans:wght@600;700;800&display=swap">
    <link rel="stylesheet" href="<?= e(asset('css/app.css')) ?>">
    <link rel="stylesheet" href="<?= e(asset('css/lembobot.css')) ?>">
</head>
<body class="<?= e($page['body_class']) ?>" data-base="<?= e(base_path()) ?>" data-role="<?= e($user['role'] ?? 'guest') ?>">
<a class="skip-link" href="#main">Skip to content</a>

<header class="site-header" id="site-header">
    <div class="container header-inner">
        <a class="brand" href="<?= e(url('index.php')) ?>" aria-label="<?= e($appName) ?> home">
            <span class="brand-mark"><?= icon('wrench', 18) ?></span>
            <span class="brand-word">Lembo<span>Fix</span></span>
        </a>

        <nav class="main-nav" aria-label="Main">
            <?php foreach ($publicNav as $key => [$label, $href]): ?>
                <a href="<?= e(url($href)) ?>" class="<?= $page['active'] === $key ? 'is-active' : '' ?>"><?= e($label) ?></a>
            <?php endforeach; ?>
        </nav>

        <div class="header-actions">
            <?php if ($user): ?>
                <div class="dropdown notif-dropdown" data-dropdown data-notifications>
                    <button type="button" class="icon-btn" aria-label="Notifications" aria-haspopup="true" aria-expanded="false" data-dropdown-toggle>
                        <?= icon('bell', 20) ?>
                        <span class="notif-count<?= $unread ? '' : ' is-hidden' ?>" data-notif-count><?= $unread > 9 ? '9+' : $unread ?></span>
                    </button>
                    <div class="dropdown-menu dropdown-menu-wide" role="menu" data-dropdown-menu>
                        <div class="dropdown-head">
                            <strong>Notifications</strong>
                            <button type="button" class="link-btn" data-notif-read-all>Mark all read</button>
                        </div>
                        <div class="notif-list" data-notif-list>
                            <div class="notif-loading"><span class="spinner"></span> Loading…</div>
                        </div>
                        <a class="dropdown-foot" href="<?= e(url('notifications.php')) ?>">View all notifications</a>
                    </div>
                </div>

                <div class="dropdown user-dropdown" data-dropdown>
                    <button type="button" class="user-btn" aria-haspopup="true" aria-expanded="false" data-dropdown-toggle>
                        <?= avatar_html($user['full_name'], $user['avatar'], 'sm') ?>
                        <span class="user-btn-name"><?= e($user['first_name']) ?></span>
                        <?= icon('chevron-down', 16) ?>
                    </button>
                    <div class="dropdown-menu" role="menu" data-dropdown-menu>
                        <div class="dropdown-user">
                            <strong><?= e($user['full_name']) ?></strong>
                            <span><?= e($user['email']) ?></span>
                        </div>
                        <?php foreach (array_slice(dashboard_nav($user['role']), 0, 5, true) as [$label, $href, $ico]): ?>
                            <a role="menuitem" href="<?= e(url($href)) ?>"><?= icon($ico, 16) ?> <?= e($label) ?></a>
                        <?php endforeach; ?>
                        <form method="post" action="<?= e(url('logout.php')) ?>">
                            <?= csrf_field() ?>
                            <button type="submit" role="menuitem" class="dropdown-logout"><?= icon('log-out', 16) ?> Log out</button>
                        </form>
                    </div>
                </div>
            <?php else: ?>
                <a class="btn btn-ghost btn-sm hide-mobile" href="<?= e(url('login.php')) ?>">Log in</a>
                <a class="btn btn-primary btn-sm hide-mobile" href="<?= e(url('register.php')) ?>">Get started</a>
            <?php endif; ?>

            <button type="button" class="icon-btn nav-toggle" aria-label="Open menu" aria-controls="mobile-nav" aria-expanded="false" data-nav-open>
                <?= icon('menu', 22) ?>
            </button>
        </div>
    </div>
</header>

<!-- Mobile navigation lives outside the header so its stacking context never traps it -->
<div class="mobile-nav-backdrop" data-nav-close hidden></div>
<nav class="mobile-nav" id="mobile-nav" aria-label="Mobile" aria-hidden="true" inert>
    <div class="mobile-nav-head">
        <a class="brand" href="<?= e(url('index.php')) ?>">
            <span class="brand-mark"><?= icon('wrench', 18) ?></span>
            <span class="brand-word">Lembo<span>Fix</span></span>
        </a>
        <button type="button" class="icon-btn" aria-label="Close menu" data-nav-close><?= icon('x', 22) ?></button>
    </div>
    <div class="mobile-nav-body">
        <?php if ($user): ?>
            <div class="mobile-user">
                <?= avatar_html($user['full_name'], $user['avatar'], 'md') ?>
                <div><strong><?= e($user['full_name']) ?></strong><span><?= e(ucfirst($user['role'])) ?></span></div>
            </div>
            <p class="mobile-nav-label">Your account</p>
            <?php foreach (dashboard_nav($user['role']) as [$label, $href, $ico, $badge]): ?>
                <a href="<?= e(url($href)) ?>" class="<?= $script === $href ? 'is-active' : '' ?>">
                    <?= icon($ico, 18) ?> <?= e($label) ?>
                    <?php if ($badge > 0 || ($href === 'notifications.php' && $unread)): ?><span class="dash-badge"><?= $href === 'notifications.php' ? $unread : $badge ?></span><?php endif; ?>
                </a>
            <?php endforeach; ?>
        <?php endif; ?>
        <p class="mobile-nav-label">Explore</p>
        <?php foreach ($publicNav as $key => [$label, $href]): ?>
            <a href="<?= e(url($href)) ?>"><?= icon(['search' => 'search', 'categories' => 'grid', 'how' => 'info', 'pros' => 'briefcase'][$key], 18) ?> <?= e($label) ?></a>
        <?php endforeach; ?>
    </div>
    <div class="mobile-nav-foot">
        <?php if ($user): ?>
            <form method="post" action="<?= e(url('logout.php')) ?>">
                <?= csrf_field() ?>
                <button type="submit" class="btn btn-secondary btn-block"><?= icon('log-out', 18) ?> Log out</button>
            </form>
        <?php else: ?>
            <a class="btn btn-secondary btn-block" href="<?= e(url('login.php')) ?>">Log in</a>
            <a class="btn btn-primary btn-block" href="<?= e(url('register.php')) ?>">Create free account</a>
        <?php endif; ?>
    </div>
</nav>

<div class="toast-stack" aria-live="polite" aria-atomic="false" data-toasts>
    <?php foreach (get_flashes() as $flash): ?>
        <div class="toast toast-<?= e($flash['type']) ?>" role="status" data-toast>
            <?= icon(['success' => 'check-circle', 'error' => 'x-circle', 'warning' => 'alert', 'info' => 'info'][$flash['type']] ?? 'info', 20) ?>
            <span><?= e($flash['message']) ?></span>
            <button type="button" class="toast-close" aria-label="Dismiss" data-toast-close><?= icon('x', 16) ?></button>
        </div>
    <?php endforeach; ?>
</div>

<main id="main" class="site-main">
