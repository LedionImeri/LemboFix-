<?php
/**
 * Provider discovery, profiles and statistics.
 */

declare(strict_types=1);

function active_categories(): array
{
    static $cache = null;
    return $cache ??= db_all(
        "SELECT c.*, (SELECT COUNT(DISTINCT ps.provider_id)
                        FROM provider_services ps
                        JOIN services s ON s.id = ps.service_id
                        JOIN provider_profiles p ON p.id = ps.provider_id AND p.approval_status = 'approved'
                        JOIN users u ON u.id = p.user_id AND u.status = 'active'
                       WHERE s.category_id = c.id AND ps.is_active = 1) AS provider_count
         FROM categories c WHERE c.is_active = 1 ORDER BY c.sort_order, c.name"
    );
}

function provider_cities(): array
{
    return array_column(db_all(
        "SELECT DISTINCT p.city FROM provider_profiles p JOIN users u ON u.id = p.user_id
         WHERE p.approval_status = 'approved' AND u.status = 'active' ORDER BY p.city"
    ), 'city');
}

function search_sort_options(): array
{
    return [
        'recommended' => 'Recommended',
        'rating'      => 'Highest rated',
        'popular'     => 'Most popular',
        'price_asc'   => 'Price: low to high',
        'price_desc'  => 'Price: high to low',
        'newest'      => 'Newest',
    ];
}

/** Normalise raw request filters into a safe, typed array. */
function search_filters_from_request(array $source): array
{
    $sort = (string) ($source['sort'] ?? 'recommended');
    $num = fn ($key) => isset($source[$key]) && is_numeric($source[$key]) ? max(0, (float) $source[$key]) : null;
    return [
        'q'          => mb_substr(trim((string) ($source['q'] ?? '')), 0, 80),
        'category'   => preg_replace('/[^a-z0-9\-]/', '', (string) ($source['category'] ?? '')),
        'city'       => mb_substr(trim((string) ($source['city'] ?? '')), 0, 80),
        'min_price'  => $num('min_price'),
        'max_price'  => $num('max_price'),
        'min_rating' => min(5, (float) ($num('min_rating') ?? 0)),
        'verified'   => !empty($source['verified']),
        'sort'       => array_key_exists($sort, search_sort_options()) ? $sort : 'recommended',
        'page'       => max(1, (int) ($source['page'] ?? 1)),
    ];
}

/**
 * Search approved providers. Returns ['items' => [...], 'pagination' => [...]].
 * Every filter is bound as a prepared-statement parameter.
 */
function search_providers(array $f, int $perPage = 9): array
{
    $where = ["p.approval_status = 'approved'", "u.status = 'active'", 'ps.is_active = 1', 'c.is_active = 1'];
    $params = [];

    if ($f['q'] !== '') {
        $where[] = "(p.business_name LIKE :q1 OR CONCAT(u.first_name, ' ', u.last_name) LIKE :q2
                     OR ps.title LIKE :q3 OR s.name LIKE :q4 OR c.name LIKE :q5 OR p.headline LIKE :q6)";
        $like = '%' . str_replace(['\\', '%', '_'], ['\\\\', '\%', '\_'], $f['q']) . '%';
        foreach (range(1, 6) as $i) {
            $params["q$i"] = $like;
        }
    }
    if ($f['category'] !== '') {
        $where[] = 'c.slug = :category';
        $params['category'] = $f['category'];
    }
    if ($f['city'] !== '') {
        $where[] = '(p.city = :city OR p.service_area LIKE :city_like)';
        $params['city'] = $f['city'];
        $params['city_like'] = '%' . str_replace(['%', '_'], ['\%', '\_'], $f['city']) . '%';
    }
    if ($f['min_rating'] > 0) {
        $where[] = 'p.avg_rating >= :min_rating';
        $params['min_rating'] = $f['min_rating'];
    }
    if ($f['verified']) {
        $where[] = 'p.is_verified = 1';
    }

    $having = [];
    if ($f['min_price'] !== null) {
        $having[] = 'min_price >= :min_price';
        $params['min_price'] = $f['min_price'];
    }
    if ($f['max_price'] !== null && $f['max_price'] > 0) {
        $having[] = 'min_price <= :max_price';
        $params['max_price'] = $f['max_price'];
    }

    $order = match ($f['sort']) {
        'rating'     => 'p.avg_rating DESC, p.review_count DESC',
        'popular'    => 'completed_jobs DESC, p.avg_rating DESC',
        'price_asc'  => 'min_price ASC, p.avg_rating DESC',
        'price_desc' => 'min_price DESC, p.avg_rating DESC',
        'newest'     => 'p.created_at DESC',
        default      => 'p.is_verified DESC, (p.avg_rating * LEAST(p.review_count, 25)) DESC, p.id ASC',
    };

    $base = "FROM provider_profiles p
             JOIN users u ON u.id = p.user_id
             JOIN provider_services ps ON ps.provider_id = p.id
             JOIN services s ON s.id = ps.service_id
             JOIN categories c ON c.id = s.category_id
             WHERE " . implode(' AND ', $where) . "
             GROUP BY p.id" . ($having ? ' HAVING ' . implode(' AND ', $having) : '');

    $total = (int) db_value("SELECT COUNT(*) FROM (SELECT p.id, MIN(ps.price) AS min_price $base) t", $params);
    $pagination = paginate($total, $perPage, $f['page']);

    $items = db_all(
        "SELECT p.id, p.business_name, p.slug, p.headline, p.city, p.is_verified, p.avg_rating, p.review_count,
                p.experience_years, u.first_name, u.last_name, u.avatar,
                MIN(ps.price) AS min_price,
                (SELECT COUNT(*) FROM bookings b WHERE b.provider_id = p.id AND b.status = 'completed') AS completed_jobs,
                (SELECT GROUP_CONCAT(c2.name ORDER BY c2.sort_order SEPARATOR ', ')
                   FROM provider_categories pc JOIN categories c2 ON c2.id = pc.category_id
                  WHERE pc.provider_id = p.id) AS category_names,
                (SELECT c3.icon FROM provider_categories pc3 JOIN categories c3 ON c3.id = pc3.category_id
                  WHERE pc3.provider_id = p.id ORDER BY c3.sort_order LIMIT 1) AS category_icon
         $base
         ORDER BY $order
         LIMIT {$pagination['per_page']} OFFSET {$pagination['offset']}",
        $params
    );

    return ['items' => attach_favorite_flags($items), 'pagination' => $pagination];
}

/** Mark which providers the logged-in customer has saved. */
function attach_favorite_flags(array $providers): array
{
    $favorites = [];
    if (has_role(ROLE_CUSTOMER) && $providers) {
        $favorites = array_flip(array_column(db_all('SELECT provider_id FROM favorites WHERE customer_id = ?', [user_id()]), 'provider_id'));
    }
    foreach ($providers as &$p) {
        $p['is_favorite'] = isset($favorites[$p['id']]);
    }
    return $providers;
}

function featured_providers(int $limit = 6): array
{
    return search_providers(search_filters_from_request(['sort' => 'recommended']), $limit)['items'];
}

/** Public provider profile by slug (approved & active only, unless $includeHidden). */
function find_provider_by_slug(string $slug, bool $includeHidden = false): ?array
{
    $provider = db_one(
        "SELECT p.*, u.first_name, u.last_name, u.avatar, u.email, u.phone, u.status AS user_status, u.created_at AS member_since,
                (SELECT COUNT(*) FROM bookings b WHERE b.provider_id = p.id AND b.status = 'completed') AS completed_jobs,
                (SELECT COUNT(DISTINCT b.customer_id) FROM bookings b WHERE b.provider_id = p.id AND b.status = 'completed') AS happy_customers
         FROM provider_profiles p JOIN users u ON u.id = p.user_id
         WHERE p.slug = ?",
        [$slug]
    );
    if (!$provider) {
        return null;
    }
    if (!$includeHidden && ($provider['approval_status'] !== 'approved' || $provider['user_status'] !== 'active')) {
        return null;
    }
    $provider['categories'] = db_all(
        'SELECT c.id, c.name, c.slug, c.icon FROM provider_categories pc JOIN categories c ON c.id = pc.category_id
         WHERE pc.provider_id = ? ORDER BY c.sort_order',
        [$provider['id']]
    );
    return $provider;
}

function provider_services_list(int $providerId, bool $activeOnly = true): array
{
    return db_all(
        'SELECT ps.*, s.name AS service_name, c.name AS category_name, c.icon AS category_icon
         FROM provider_services ps
         JOIN services s ON s.id = ps.service_id
         JOIN categories c ON c.id = s.category_id
         WHERE ps.provider_id = ?' . ($activeOnly ? ' AND ps.is_active = 1' : '') . '
         ORDER BY ps.is_active DESC, ps.price ASC',
        [$providerId]
    );
}

/** Recompute the cached rating aggregate after any review change. */
function refresh_provider_rating(int $providerId): void
{
    db_query(
        "UPDATE provider_profiles p SET
            avg_rating = COALESCE((SELECT ROUND(AVG(r.rating), 2) FROM reviews r JOIN bookings b ON b.id = r.booking_id
                                   WHERE b.provider_id = p.id AND r.status = 'published'), 0),
            review_count = (SELECT COUNT(*) FROM reviews r JOIN bookings b ON b.id = r.booking_id
                            WHERE b.provider_id = p.id AND r.status = 'published')
         WHERE p.id = ?",
        [$providerId]
    );
}

function rating_breakdown(int $providerId): array
{
    $rows = db_all(
        "SELECT r.rating, COUNT(*) AS total FROM reviews r JOIN bookings b ON b.id = r.booking_id
         WHERE b.provider_id = ? AND r.status = 'published' GROUP BY r.rating",
        [$providerId]
    );
    $breakdown = array_fill_keys([5, 4, 3, 2, 1], 0);
    foreach ($rows as $row) {
        $breakdown[(int) $row['rating']] = (int) $row['total'];
    }
    return $breakdown;
}

/** Headline numbers for the provider dashboard. */
function provider_stats(int $providerId): array
{
    $row = db_one(
        "SELECT COUNT(*) AS total,
                SUM(status = 'pending') AS pending,
                SUM(status IN ('confirmed','in_progress')) AS upcoming,
                SUM(status = 'completed') AS completed,
                SUM(status IN ('cancelled','rejected')) AS cancelled,
                COALESCE(SUM(CASE WHEN status = 'completed' THEN subtotal END), 0) AS revenue,
                COALESCE(SUM(CASE WHEN status = 'completed' AND booking_date >= DATE_FORMAT(CURDATE(), '%Y-%m-01') THEN subtotal END), 0) AS revenue_month,
                COUNT(DISTINCT customer_id) AS customers
         FROM bookings WHERE provider_id = ?",
        [$providerId]
    );
    return array_map(fn ($v) => $v ?? 0, $row ?? []);
}

/** Totals per month for the last N months, e.g. for revenue charts. */
function monthly_series(string $sql, array $params, int $months = 6): array
{
    $series = [];
    for ($i = $months - 1; $i >= 0; $i--) {
        $key = date('Y-m', strtotime("first day of -$i month"));
        $series[$key] = ['label' => date('M', strtotime($key . '-01')), 'value' => 0.0];
    }
    foreach (db_all($sql, $params) as $row) {
        if (isset($series[$row['ym']])) {
            $series[$row['ym']]['value'] = (float) $row['value'];
        }
    }
    return array_values($series);
}

function provider_profile_completion(array $provider): int
{
    $checks = [
        !empty($provider['headline']),
        mb_strlen((string) $provider['bio']) >= 60,
        !empty(current_user()['avatar']),
        !empty($provider['service_area']),
        (int) db_value('SELECT COUNT(*) FROM provider_services WHERE provider_id = ? AND is_active = 1', [$provider['id']]) > 0,
        (int) db_value('SELECT COUNT(*) FROM availability WHERE provider_id = ? AND is_working = 1', [$provider['id']]) > 0,
    ];
    return (int) round(array_sum($checks) / count($checks) * 100);
}

/** Generate a unique slug for a provider business name. */
function unique_provider_slug(string $name, ?int $ignoreId = null): string
{
    $base = slugify($name);
    $slug = $base;
    $i = 2;
    while (db_value('SELECT id FROM provider_profiles WHERE slug = ? AND id <> ?', [$slug, $ignoreId ?? 0])) {
        $slug = $base . '-' . $i++;
    }
    return $slug;
}
