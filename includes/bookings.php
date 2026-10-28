<?php
/**
 * Booking lifecycle: creation (with double-booking protection), status
 * transitions per role, payments (demo) and notifications.
 */

declare(strict_types=1);

/** Allowed status changes per actor role. */
function booking_transitions(): array
{
    return [
        ROLE_CUSTOMER => ['pending' => ['cancelled'], 'confirmed' => ['cancelled']],
        ROLE_PROVIDER => [
            'pending'     => ['confirmed', 'rejected'],
            'confirmed'   => ['in_progress', 'completed', 'cancelled'],
            'in_progress' => ['completed'],
        ],
        ROLE_ADMIN => [
            'pending'     => ['confirmed', 'rejected', 'cancelled'],
            'confirmed'   => ['in_progress', 'completed', 'cancelled'],
            'in_progress' => ['completed', 'cancelled'],
        ],
    ];
}

function booking_fee(float $subtotal): float
{
    return round($subtotal * (float) config('booking.platform_fee_percent') / 100, 2);
}

function generate_booking_reference(): string
{
    $alphabet = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';
    do {
        $ref = 'LF-';
        for ($i = 0; $i < 6; $i++) {
            $ref .= $alphabet[random_int(0, strlen($alphabet) - 1)];
        }
    } while (db_value('SELECT id FROM bookings WHERE reference = ?', [$ref]));
    return $ref;
}

/**
 * Validate and create a booking. Returns ['ok' => bool, 'errors' => [...], 'booking' => row].
 *
 * Double-booking protection: inside a transaction we lock the provider row
 * (SELECT … FOR UPDATE), so concurrent requests for the same provider are
 * serialised, then re-check the slot against committed bookings before inserting.
 */
function create_booking(int $customerId, array $input): array
{
    $v = new Validator($input);
    $v->required('provider_id', 'Provider')
      ->required('date', 'Date')->date('date', 'Date')
      ->required('time', 'Time')->time('time', 'Time')
      ->required('address', 'Address')->length('address', 'Address', 5, 255)
      ->required('city', 'City')->length('city', 'City', 2, 80)
      ->length('message', 'Message', 0, 1000)
      ->in('payment_method', ['card', 'cash'], 'Please choose a payment method.');

    $serviceIds = array_values(array_unique(array_filter(array_map('intval', (array) ($input['services'] ?? [])))));
    $v->custom($serviceIds === [], 'services', 'Please choose at least one service.')
      ->custom(count($serviceIds) > (int) config('booking.max_items'), 'services', 'You can book up to ' . config('booking.max_items') . ' services at once.');

    if ($v->fails()) {
        return ['ok' => false, 'errors' => $v->errors()];
    }

    $providerId = (int) $input['provider_id'];
    $provider = db_one(
        "SELECT p.*, u.status AS user_status FROM provider_profiles p JOIN users u ON u.id = p.user_id WHERE p.id = ?",
        [$providerId]
    );
    if (!$provider || $provider['approval_status'] !== 'approved' || $provider['user_status'] !== 'active') {
        return ['ok' => false, 'errors' => ['provider_id' => 'This provider is not accepting bookings right now.']];
    }
    if ((int) $provider['user_id'] === $customerId) {
        return ['ok' => false, 'errors' => ['provider_id' => 'You cannot book your own services.']];
    }

    $placeholders = implode(',', array_fill(0, count($serviceIds), '?'));
    $services = db_all(
        "SELECT id, title, price, duration_minutes FROM provider_services
         WHERE provider_id = ? AND is_active = 1 AND id IN ($placeholders)",
        array_merge([$providerId], $serviceIds)
    );
    if (count($services) !== count($serviceIds)) {
        return ['ok' => false, 'errors' => ['services' => 'One of the selected services is no longer available.']];
    }

    $duration = array_sum(array_map(fn ($s) => (int) $s['duration_minutes'], $services));
    if ($duration > 600) {
        return ['ok' => false, 'errors' => ['services' => 'The selected services are too long for a single visit. Please book them separately.']];
    }
    $subtotal = array_sum(array_map(fn ($s) => (float) $s['price'], $services));
    $fee = booking_fee($subtotal);
    $date = $v->value('date');
    $time = substr($v->value('time'), 0, 5);
    $endTime = minutes_to_time(time_to_minutes($time) + $duration);
    $method = $v->value('payment_method');

    $pdo = db();
    $pdo->beginTransaction();
    try {
        db_query('SELECT id FROM provider_profiles WHERE id = ? FOR UPDATE', [$providerId]);

        if (!in_array($time, available_slots($providerId, $date, $duration), true)) {
            $pdo->rollBack();
            return ['ok' => false, 'errors' => ['time' => 'Sorry, that time slot is no longer available. Please pick another time.'], 'slot_taken' => true];
        }

        $clash = db_value(
            "SELECT id FROM bookings WHERE customer_id = ? AND booking_date = ? AND status IN ('pending','confirmed','in_progress')
             AND start_time < ? AND end_time > ? LIMIT 1",
            [$customerId, $date, $endTime . ':00', $time . ':00']
        );
        if ($clash) {
            $pdo->rollBack();
            return ['ok' => false, 'errors' => ['time' => 'You already have another booking at this time.']];
        }

        $reference = generate_booking_reference();
        db_query(
            'INSERT INTO bookings (reference, customer_id, provider_id, booking_date, start_time, end_time, address, city,
                                   customer_message, status, subtotal, platform_fee, total, payment_method, payment_status)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, \'pending\', ?, ?, ?, ?, ?)',
            [$reference, $customerId, $providerId, $date, $time . ':00', $endTime . ':00', $v->value('address'), $v->value('city'),
             $v->value('message') ?: null, $subtotal, $fee, $subtotal + $fee, $method,
             $method === 'card' ? 'paid' : 'unpaid']
        );
        $bookingId = (int) $pdo->lastInsertId();

        $insertItem = $pdo->prepare('INSERT INTO booking_items (booking_id, provider_service_id, title, price, duration_minutes) VALUES (?, ?, ?, ?, ?)');
        foreach ($services as $s) {
            $insertItem->execute([$bookingId, $s['id'], $s['title'], $s['price'], $s['duration_minutes']]);
        }
        $pdo->commit();
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $e;
    }

    $customer = db_one('SELECT first_name, last_name FROM users WHERE id = ?', [$customerId]);
    $titles = implode(', ', array_column($services, 'title'));
    notify((int) $provider['user_id'], 'booking_request', 'New booking request',
        "{$customer['first_name']} {$customer['last_name']} requested {$titles} on " . format_date($date, 'M j') . " at {$time}.",
        'provider/bookings.php?status=pending');
    log_activity($customerId, 'booking_create', "{$customer['first_name']} {$customer['last_name']} booked {$provider['business_name']} ($reference)", 'booking', $bookingId);

    return ['ok' => true, 'booking' => find_booking($bookingId)];
}

/** Full booking row with provider, customer and items. */
function find_booking(int $id): ?array
{
    $booking = db_one(
        "SELECT b.*, p.business_name, p.slug AS provider_slug, p.user_id AS provider_user_id, p.city AS provider_city,
                pu.avatar AS provider_avatar, pu.phone AS provider_phone,
                cu.first_name AS customer_first_name, cu.last_name AS customer_last_name, cu.email AS customer_email,
                cu.phone AS customer_phone, cu.avatar AS customer_avatar,
                r.id AS review_id, r.rating AS review_rating
         FROM bookings b
         JOIN provider_profiles p ON p.id = b.provider_id
         JOIN users pu ON pu.id = p.user_id
         JOIN users cu ON cu.id = b.customer_id
         LEFT JOIN reviews r ON r.booking_id = b.id
         WHERE b.id = ?",
        [$id]
    );
    if ($booking) {
        $booking['items'] = db_all('SELECT * FROM booking_items WHERE booking_id = ? ORDER BY id', [$id]);
    }
    return $booking;
}

function find_booking_by_reference(string $reference): ?array
{
    $id = db_value('SELECT id FROM bookings WHERE reference = ?', [$reference]);
    return $id ? find_booking((int) $id) : null;
}

/**
 * List bookings for a customer, provider or admin with optional status/scope filters.
 * $scope: 'upcoming' | 'past' | null
 */
function list_bookings(array $where, array $params, string $order = 'b.booking_date DESC, b.start_time DESC', int $limit = 50, int $offset = 0): array
{
    $rows = db_all(
        "SELECT b.*, p.business_name, p.slug AS provider_slug, pu.avatar AS provider_avatar,
                cu.first_name AS customer_first_name, cu.last_name AS customer_last_name, cu.avatar AS customer_avatar,
                cu.phone AS customer_phone, cu.email AS customer_email,
                (SELECT GROUP_CONCAT(bi.title ORDER BY bi.id SEPARATOR ', ') FROM booking_items bi WHERE bi.booking_id = b.id) AS service_titles,
                r.id AS review_id, r.rating AS review_rating
         FROM bookings b
         JOIN provider_profiles p ON p.id = b.provider_id
         JOIN users pu ON pu.id = p.user_id
         JOIN users cu ON cu.id = b.customer_id
         LEFT JOIN reviews r ON r.booking_id = b.id
         WHERE " . ($where ? implode(' AND ', $where) : '1=1') . "
         ORDER BY $order
         LIMIT $limit OFFSET $offset",
        $params
    );
    return $rows;
}

function count_bookings(array $where, array $params): int
{
    return (int) db_value(
        'SELECT COUNT(*) FROM bookings b JOIN provider_profiles p ON p.id = b.provider_id JOIN users cu ON cu.id = b.customer_id
         WHERE ' . ($where ? implode(' AND ', $where) : '1=1'),
        $params
    );
}

function booking_starts_at(array $booking): int
{
    return strtotime($booking['booking_date'] . ' ' . $booking['start_time']);
}

function booking_is_upcoming(array $booking): bool
{
    return booking_starts_at($booking) >= time() - 3600 && in_array($booking['status'], ACTIVE_BOOKING_STATUSES, true);
}

/** Can this actor move the booking to $to? Returns an error string or null when allowed. */
function booking_transition_error(array $booking, string $to, string $role, int $actorId): ?string
{
    $allowed = booking_transitions()[$role][$booking['status']] ?? [];
    if (!in_array($to, $allowed, true)) {
        return 'This booking can no longer be changed to "' . booking_status_label($to) . '".';
    }
    if ($role === ROLE_CUSTOMER && (int) $booking['customer_id'] !== $actorId) {
        return 'Booking not found.';
    }
    if ($role === ROLE_PROVIDER && (int) $booking['provider_user_id'] !== $actorId) {
        return 'Booking not found.';
    }
    if ($role === ROLE_CUSTOMER && $to === 'cancelled' && $booking['status'] === 'confirmed') {
        $hours = (int) config('booking.customer_cancel_hours');
        if (booking_starts_at($booking) - time() < $hours * 3600) {
            return "Confirmed bookings can be cancelled up to {$hours} hours before the start time. Please contact the provider.";
        }
    }
    if (in_array($to, ['in_progress', 'completed'], true) && $booking['booking_date'] > date('Y-m-d')) {
        return 'You can start or complete a job on or after its scheduled date.';
    }
    return null;
}

/**
 * Apply a status change with its side effects (timestamps, demo payment status, notifications).
 */
function change_booking_status(int $bookingId, string $to, string $role, int $actorId, string $note = ''): array
{
    $booking = find_booking($bookingId);
    if (!$booking) {
        return ['ok' => false, 'message' => 'Booking not found.'];
    }
    if ($error = booking_transition_error($booking, $to, $role, $actorId)) {
        return ['ok' => false, 'message' => $error];
    }

    $note = mb_substr(trim($note), 0, 255);
    $sets = ['status = ?'];
    $params = [$to];
    switch ($to) {
        case 'confirmed':
            $sets[] = 'confirmed_at = NOW()';
            break;
        case 'completed':
            $sets[] = 'completed_at = NOW()';
            if ($booking['payment_method'] === 'cash') {
                $sets[] = "payment_status = 'paid'";
            }
            break;
        case 'cancelled':
        case 'rejected':
            if ($to === 'cancelled') {
                $sets[] = 'cancelled_at = NOW()';
                $sets[] = 'cancelled_by = ?';
                $params[] = $role;
                $sets[] = 'cancellation_reason = ?';
                $params[] = $note ?: null;
            }
            if ($booking['payment_status'] === 'paid') {
                $sets[] = "payment_status = 'refunded'";
            }
            break;
    }
    if ($note !== '' && in_array($to, ['rejected', 'confirmed', 'completed'], true)) {
        $sets[] = 'provider_note = ?';
        $params[] = $note;
    }
    $params[] = $bookingId;
    $params[] = $booking['status'];
    $updated = db_query('UPDATE bookings SET ' . implode(', ', $sets) . ' WHERE id = ? AND status = ?', $params)->rowCount();
    if ($updated === 0) {
        return ['ok' => false, 'message' => 'This booking was just updated by someone else. Please refresh.'];
    }

    notify_booking_change($booking, $to, $role, $note);
    log_activity($actorId, 'booking_' . $to, "Booking {$booking['reference']} marked as " . booking_status_label($to), 'booking', $bookingId);

    return ['ok' => true, 'message' => 'Booking ' . strtolower(booking_status_label($to)) . '.', 'booking' => find_booking($bookingId)];
}

function notify_booking_change(array $b, string $to, string $actorRole, string $note): void
{
    $services = implode(', ', array_column($b['items'], 'title'));
    $when = format_date($b['booking_date'], 'M j') . ' at ' . format_time($b['start_time']);
    $customerName = $b['customer_first_name'] . ' ' . $b['customer_last_name'];
    $customerLink = 'customer/bookings.php';
    $providerLink = 'provider/bookings.php';

    switch ($to) {
        case 'confirmed':
            notify((int) $b['customer_id'], 'booking_confirmed', 'Booking confirmed 🎉', "{$b['business_name']} confirmed your {$services} on {$when}.", $customerLink);
            break;
        case 'rejected':
            notify((int) $b['customer_id'], 'booking_rejected', 'Booking declined', "{$b['business_name']} couldn't take your {$services} request" . ($note ? ": {$note}" : '.'), $customerLink);
            break;
        case 'in_progress':
            notify((int) $b['customer_id'], 'booking_in_progress', 'Your job has started', "{$b['business_name']} started working on {$services}.", $customerLink);
            break;
        case 'completed':
            notify((int) $b['customer_id'], 'booking_completed', 'Job completed — how did it go?', "Your {$services} with {$b['business_name']} is complete. Leave a review!", 'customer/bookings.php?status=completed');
            break;
        case 'cancelled':
            if ($actorRole !== ROLE_CUSTOMER) {
                notify((int) $b['customer_id'], 'booking_cancelled', 'Booking cancelled', "Your {$services} on {$when} with {$b['business_name']} was cancelled.", $customerLink);
            }
            if ($actorRole !== ROLE_PROVIDER) {
                notify((int) $b['provider_user_id'], 'booking_cancelled', 'Booking cancelled', "{$customerName}'s booking on {$when} was cancelled.", $providerLink);
            }
            break;
    }
}

/** Actions a role may take on a booking right now (used to render buttons). */
function booking_actions_for(array $booking, string $role): array
{
    $labels = [
        'confirmed'   => ['Accept', 'success', 'check'],
        'rejected'    => ['Decline', 'danger', 'x'],
        'in_progress' => ['Start job', 'brand', 'play'],
        'completed'   => ['Mark completed', 'success', 'check-circle'],
        'cancelled'   => ['Cancel', 'danger', 'x-circle'],
    ];
    $actions = [];
    foreach (booking_transitions()[$role][$booking['status']] ?? [] as $to) {
        if (in_array($to, ['in_progress', 'completed'], true) && $booking['booking_date'] > date('Y-m-d')) {
            continue;
        }
        if ($role === ROLE_PROVIDER && $to === 'completed' && $booking['status'] === 'confirmed') {
            continue; // providers start the job first; "complete" appears once in progress
        }
        $actions[$to] = $labels[$to];
    }
    return $actions;
}
