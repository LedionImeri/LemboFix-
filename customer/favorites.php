<?php
require dirname(__DIR__) . '/includes/bootstrap.php';
$user = require_login(ROLE_CUSTOMER);

$favorites = attach_favorite_flags(db_all(
    "SELECT p.id, p.business_name, p.slug, p.headline, p.city, p.is_verified, p.avg_rating, p.review_count, p.experience_years,
            u.first_name, u.last_name, u.avatar,
            (SELECT MIN(ps.price) FROM provider_services ps WHERE ps.provider_id = p.id AND ps.is_active = 1) AS min_price,
            (SELECT COUNT(*) FROM bookings b WHERE b.provider_id = p.id AND b.status = 'completed') AS completed_jobs,
            (SELECT GROUP_CONCAT(c.name ORDER BY c.sort_order SEPARATOR ', ') FROM provider_categories pc JOIN categories c ON c.id = pc.category_id WHERE pc.provider_id = p.id) AS category_names,
            (SELECT c.icon FROM provider_categories pc JOIN categories c ON c.id = pc.category_id WHERE pc.provider_id = p.id ORDER BY c.sort_order LIMIT 1) AS category_icon
     FROM favorites f
     JOIN provider_profiles p ON p.id = f.provider_id
     JOIN users u ON u.id = p.user_id
     WHERE f.customer_id = ? AND p.approval_status = 'approved' AND u.status = 'active'
     ORDER BY f.created_at DESC",
    [$user['id']]
));

dashboard_start(['title' => 'Favorites', 'subtitle' => 'Professionals you saved for later.', 'active' => 'favorites']);
?>
<?php if ($favorites): ?>
    <div class="provider-grid">
        <?php foreach ($favorites as $p): partial('provider-card', ['p' => $p, 'removable' => true]); endforeach; ?>
    </div>
<?php else: ?>
    <div class="card"><?= empty_state('No favorites yet', 'Tap the heart on any provider to save them here and book them again in seconds.', 'heart', '<a class="btn btn-primary" href="' . e(url('search.php')) . '">Browse professionals</a>') ?></div>
<?php endif; ?>
<?php dashboard_end(); ?>
