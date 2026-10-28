<?php
/** @var array $result  from search_providers()  @var array $filters */
$items = $result['items'];
$pg = $result['pagination'];
?>
<?php if (!$items): ?>
    <div class="card">
        <?= empty_state(
            'We couldn\'t find any providers matching your search.',
            'Try a different service, remove a filter, or search in a nearby city.',
            'search',
            '<a class="btn btn-secondary" href="' . e(url('search.php')) . '">Clear all filters</a>'
        ) ?>
    </div>
<?php else: ?>
    <div class="provider-grid">
        <?php foreach ($items as $p): ?>
            <?php partial('provider-card', ['p' => $p]); ?>
        <?php endforeach; ?>
    </div>
    <?= pagination_html($pg) ?>
<?php endif; ?>
