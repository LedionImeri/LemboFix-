<?php
require __DIR__ . '/includes/bootstrap.php';

$filters = search_filters_from_request($_GET);
$result = search_providers($filters);
$categories = active_categories();
$cities = provider_cities();
$currentCategory = null;
foreach ($categories as $c) {
    if ($c['slug'] === $filters['category']) {
        $currentCategory = $c;
    }
}

$heading = $currentCategory ? $currentCategory['name'] . ' professionals' : ($filters['q'] !== '' ? 'Results for “' . $filters['q'] . '”' : 'Find a professional');
if ($filters['city'] !== '') {
    $heading .= ' in ' . $filters['city'];
}

page_start([
    'title'       => $heading,
    'description' => $currentCategory
        ? "Compare verified {$currentCategory['name']} professionals on LemboFix. See prices, reviews and real-time availability, then book online."
        : 'Search verified local service providers by service, city, price and rating. Book online with real-time availability.',
    'active'      => 'search',
    'scripts'     => ['search.js'],
]);
?>
<section class="search-top">
    <div class="container">
        <nav class="breadcrumb" aria-label="Breadcrumb">
            <a href="<?= e(url('index.php')) ?>">Home</a><?= icon('chevron-right', 14) ?>
            <?php if ($currentCategory): ?>
                <a href="<?= e(url('search.php')) ?>">Search</a><?= icon('chevron-right', 14) ?><span><?= e($currentCategory['name']) ?></span>
            <?php else: ?><span>Search</span><?php endif; ?>
        </nav>
        <h1 data-search-heading><?= e($heading) ?></h1>
        <p class="text-muted mb-0"><?= $currentCategory ? e($currentCategory['description']) : 'Compare verified local pros by price, rating and availability.' ?></p>
    </div>
</section>

<div class="container">
    <form class="search-layout" action="<?= e(url('search.php')) ?>" method="get" data-search-form role="search">
        <aside class="filters card" id="filters" aria-label="Filters">
            <div class="card-body">
                <div class="flex justify-between items-center mb-2">
                    <h3 class="mb-0"><?= icon('sliders', 18) ?></h3>
                    <strong class="text-sm" style="margin-right:auto;margin-left:8px">Filters</strong>
                    <a class="link-btn" href="<?= e(url('search.php')) ?>" data-clear-filters>Clear all</a>
                    <button type="button" class="icon-btn filters-close" aria-label="Close filters" data-filters-close><?= icon('x', 20) ?></button>
                </div>

                <div class="filter-group">
                    <h4>Keyword</h4>
                    <div class="input-group">
                        <?= icon('search', 18) ?>
                        <input class="input" type="search" name="q" value="<?= e($filters['q']) ?>" placeholder="Service or provider" maxlength="80" aria-label="Service or provider name">
                    </div>
                </div>

                <div class="filter-group">
                    <h4>Category</h4>
                    <div class="filter-options">
                        <label class="filter-option">
                            <input type="radio" name="category" value=""<?= checked($filters['category'] === '') ?>> All categories
                        </label>
                        <?php foreach ($categories as $c): ?>
                            <label class="filter-option">
                                <input type="radio" name="category" value="<?= e($c['slug']) ?>"<?= checked($filters['category'] === $c['slug']) ?>>
                                <?= e($c['name']) ?> <span class="count"><?= (int) $c['provider_count'] ?></span>
                            </label>
                        <?php endforeach; ?>
                    </div>
                </div>

                <div class="filter-group">
                    <h4>City</h4>
                    <select class="select" name="city" aria-label="City">
                        <option value="">Any city</option>
                        <?php foreach ($cities as $city): ?>
                            <option value="<?= e($city) ?>"<?= selected(mb_strtolower($city), mb_strtolower($filters['city'])) ?>><?= e($city) ?></option>
                        <?php endforeach; ?>
                        <?php if ($filters['city'] !== '' && !in_array(mb_strtolower($filters['city']), array_map('mb_strtolower', $cities), true)): ?>
                            <option value="<?= e($filters['city']) ?>" selected><?= e($filters['city']) ?></option>
                        <?php endif; ?>
                    </select>
                </div>

                <div class="filter-group">
                    <h4>Starting price (€)</h4>
                    <div class="price-inputs">
                        <input class="input" type="number" name="min_price" min="0" step="5" placeholder="Min" value="<?= e($filters['min_price'] ?? '') ?>" aria-label="Minimum price">
                        <input class="input" type="number" name="max_price" min="0" step="5" placeholder="Max" value="<?= e($filters['max_price'] ?? '') ?>" aria-label="Maximum price">
                    </div>
                </div>

                <div class="filter-group">
                    <h4>Rating</h4>
                    <div class="rating-options">
                        <?php foreach (['' => 'Any', '3' => '3+', '4' => '4+', '4.5' => '4.5+'] as $value => $label): ?>
                            <label>
                                <input type="radio" name="min_rating" value="<?= e((string) $value) ?>"<?= checked((float) $filters['min_rating'] === (float) $value) ?>>
                                <span><?php if ($value): ?><?= icon('star', 13) ?><?php endif; ?><?= e($label) ?></span>
                            </label>
                        <?php endforeach; ?>
                    </div>
                </div>

                <div class="filter-group">
                    <label class="switch">
                        <input type="checkbox" name="verified" value="1"<?= checked($filters['verified']) ?>>
                        <span class="switch-track"></span> Verified pros only
                    </label>
                </div>

                <noscript><button class="btn btn-primary btn-block mt-2" type="submit">Apply filters</button></noscript>
                <button class="btn btn-primary btn-block mt-2 filters-close" type="button" data-filters-close>Show results</button>
            </div>
        </aside>

        <div>
            <div class="results-head">
                <div class="flex items-center gap-1">
                    <button type="button" class="btn btn-secondary btn-sm filters-toggle" data-filters-open aria-controls="filters"><?= icon('sliders', 16) ?> Filters</button>
                    <p class="results-count mb-0" data-results-count><strong><?= (int) $result['pagination']['total'] ?></strong> <?= $result['pagination']['total'] === 1 ? 'professional' : 'professionals' ?> found</p>
                </div>
                <label class="flex items-center gap-1 text-sm">
                    <span class="text-muted nowrap">Sort by</span>
                    <select class="select" name="sort" aria-label="Sort results">
                        <?php foreach (search_sort_options() as $value => $label): ?>
                            <option value="<?= e($value) ?>"<?= selected($value, $filters['sort']) ?>><?= e($label) ?></option>
                        <?php endforeach; ?>
                    </select>
                </label>
            </div>
            <input type="hidden" name="page" value="<?= (int) $filters['page'] ?>" data-page-input>
            <div class="results" data-results aria-live="polite" aria-busy="false">
                <?php partial('search-results', ['result' => $result, 'filters' => $filters]); ?>
            </div>
        </div>
    </form>
</div>
<?php page_end(); ?>
