<?php
/**
 * POST api/favorites.php { provider_id }  → toggles a saved provider for the logged-in customer.
 */
require dirname(__DIR__) . '/includes/bootstrap.php';
require_method('POST');
$user = require_login(ROLE_CUSTOMER);
require_csrf();

$providerId = (int) ($_POST['provider_id'] ?? json_input()['provider_id'] ?? 0);
$provider = db_one("SELECT id, business_name FROM provider_profiles WHERE id = ? AND approval_status = 'approved'", [$providerId]);
if (!$provider) {
    api_respond(false, 'Provider not found.', [], 404);
}

$deleted = db_query('DELETE FROM favorites WHERE customer_id = ? AND provider_id = ?', [$user['id'], $providerId])->rowCount();
if ($deleted) {
    api_respond(true, 'Removed from your favorites.', ['favorite' => false]);
}
db_query('INSERT IGNORE INTO favorites (customer_id, provider_id) VALUES (?, ?)', [$user['id'], $providerId]);
api_respond(true, $provider['business_name'] . ' saved to your favorites.', ['favorite' => true]);
