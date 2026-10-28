<?php
/**
 * GET api/search.php?q=&category=&city=&min_price=&max_price=&min_rating=&verified=&sort=&page=
 * Returns server-rendered result cards so the AJAX view and the full page share one template.
 */
require dirname(__DIR__) . '/includes/bootstrap.php';
require_method('GET');

$filters = search_filters_from_request($_GET);
$result = search_providers($filters);
$total = $result['pagination']['total'];

json_response([
    'ok'    => true,
    'total' => $total,
    'label' => $total === 1 ? 'professional' : 'professionals',
    'html'  => partial_html('search-results', ['result' => $result, 'filters' => $filters]),
]);
