<?php
/**
 * Provider service management (POST, CSRF protected, provider only).
 *   action=save    id? service_id title description price price_type duration_minutes location_type is_active
 *   action=delete  id
 * Public GET ?provider_id= returns a provider's bookable services.
 */
require dirname(__DIR__) . '/includes/bootstrap.php';

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'GET') {
    $providerId = input_int('provider_id');
    $ok = db_value("SELECT 1 FROM provider_profiles WHERE id = ? AND approval_status = 'approved'", [$providerId]);
    if (!$ok) {
        api_respond(false, 'Provider not found.', [], 404);
    }
    $services = array_map(fn ($s) => [
        'id' => (int) $s['id'], 'title' => $s['title'], 'price' => (float) $s['price'], 'price_type' => $s['price_type'],
        'duration_minutes' => (int) $s['duration_minutes'], 'category' => $s['category_name'],
    ], provider_services_list($providerId));
    json_response(['ok' => true, 'services' => $services]);
}

$user = require_login(ROLE_PROVIDER);
require_csrf();
$providerId = (int) current_provider()['id'];

switch (input('action')) {
    case 'save':
        $v = new Validator($_POST);
        $v->required('service_id', 'Service type')
          ->required('title', 'Title')->length('title', 'Title', 3, 140)
          ->length('description', 'Description', 0, 1000)
          ->required('price', 'Price')->number('price', 'Price', 0, 100000)
          ->in('price_type', ['fixed', 'from', 'hourly'])
          ->number('duration_minutes', 'Duration', 15, 600)
          ->in('location_type', ['customer', 'provider', 'remote']);
        $serviceType = db_one(
            'SELECT s.id, s.category_id FROM services s JOIN categories c ON c.id = s.category_id WHERE s.id = ? AND s.is_active = 1 AND c.is_active = 1',
            [input_int('service_id')]
        );
        $v->custom(!$serviceType, 'service_id', 'Please choose a valid service type.');
        if ($v->fails()) {
            api_respond(false, $v->firstError(), ['errors' => $v->errors()]);
        }

        $id = input_int('id');
        $values = [(int) $serviceType['id'], $v->value('title'), $v->value('description') ?: null, round((float) $v->value('price'), 2),
                   $v->value('price_type'), (int) $v->value('duration_minutes'), $v->value('location_type'), empty($_POST['is_active']) ? 0 : 1];
        if ($id) {
            db_query(
                'UPDATE provider_services SET service_id = ?, title = ?, description = ?, price = ?, price_type = ?, duration_minutes = ?,
                        location_type = ?, is_active = ? WHERE id = ? AND provider_id = ?',
                array_merge($values, [$id, $providerId])
            );
            if (!db_value('SELECT id FROM provider_services WHERE id = ? AND provider_id = ?', [$id, $providerId])) {
                api_respond(false, 'Service not found.', [], 404);
            }
            $message = 'Service updated.';
        } else {
            db_query(
                'INSERT INTO provider_services (service_id, title, description, price, price_type, duration_minutes, location_type, is_active, provider_id)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)',
                array_merge($values, [$providerId])
            );
            $message = 'Service added — customers can book it now.';
        }
        // Keep the provider's categories in sync with the services they offer
        db_query('INSERT IGNORE INTO provider_categories (provider_id, category_id) VALUES (?, ?)', [$providerId, $serviceType['category_id']]);
        log_activity((int) $user['id'], 'service_save', ($id ? 'Updated' : 'Added') . ' service “' . $v->value('title') . '”', 'provider_service', $id ?: null);
        api_respond(true, $message);

    case 'delete':
        $id = input_int('id');
        $deleted = db_query('DELETE FROM provider_services WHERE id = ? AND provider_id = ?', [$id, $providerId])->rowCount();
        if ($deleted) {
            log_activity((int) $user['id'], 'service_delete', 'Deleted a service', 'provider_service', $id);
        }
        api_respond((bool) $deleted, $deleted ? 'Service deleted.' : 'Service not found.');

    default:
        api_respond(false, 'Unknown action.', [], 400);
}
