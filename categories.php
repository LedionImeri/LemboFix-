<?php
require __DIR__ . '/includes/bootstrap.php';

$categories = active_categories();
$servicesByCategory = [];
foreach (db_all("SELECT category_id, name FROM services WHERE is_active = 1 ORDER BY name") as $s) {
    $servicesByCategory[$s['category_id']][] = $s['name'];
}

page_start([
    'title'       => 'All service categories',
    'description' => 'Browse every LemboFix service category: plumbing, electrical, cleaning, car repair, IT support, moving, gardening, beauty, photography and more.',
    'active'      => 'categories',
]);
?>
<section class="page-hero">
    <div class="container">
        <nav class="breadcrumb" aria-label="Breadcrumb"><a href="<?= e(url('index.php')) ?>">Home</a><?= icon('chevron-right', 14) ?><span>Categories</span></nav>
        <h1>All categories</h1>
        <p>Whatever needs doing around your home, car or business — there's a verified professional for it.</p>
    </div>
</section>
<section class="container" style="padding-bottom:80px">
    <?php if ($categories): ?>
        <div class="grid-3">
            <?php foreach ($categories as $cat): ?>
                <a class="category-tile category-tile-lg" href="<?= e(url('search.php?category=' . $cat['slug'])) ?>">
                    <span class="category-icon"><?= icon($cat['icon'], 24) ?></span>
                    <div>
                        <strong><?= e($cat['name']) ?></strong>
                        <p><?= e($cat['description']) ?></p>
                        <span><?= e(implode(' · ', array_slice($servicesByCategory[$cat['id']] ?? [], 0, 3))) ?></span><br>
                        <span class="badge badge-brand mt-1"><?= (int) $cat['provider_count'] ? plural((int) $cat['provider_count'], 'pro') . ' available' : 'Coming soon' ?></span>
                    </div>
                </a>
            <?php endforeach; ?>
        </div>
    <?php else: ?>
        <div class="card"><?= empty_state('No categories yet', 'Categories will appear here once an administrator adds them.', 'grid') ?></div>
    <?php endif; ?>
</section>
<?php page_end(); ?>
