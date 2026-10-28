<?php
/**
 * Availability engine: turns weekly hours, breaks, time off and existing
 * bookings into bookable start times. The same functions power the customer
 * calendar (via api/availability.php) and the server-side booking validation.
 */

declare(strict_types=1);

const ACTIVE_BOOKING_STATUSES = ['pending', 'confirmed', 'in_progress'];

function weekday_names(): array
{
    return [1 => 'Monday', 2 => 'Tuesday', 3 => 'Wednesday', 4 => 'Thursday', 5 => 'Friday', 6 => 'Saturday', 0 => 'Sunday'];
}

function time_to_minutes(string $time): int
{
    [$h, $m] = array_map('intval', explode(':', $time) + [0, 0]);
    return $h * 60 + $m;
}

function minutes_to_time(int $minutes): string
{
    return sprintf('%02d:%02d', intdiv($minutes, 60), $minutes % 60);
}

/** Weekly schedule keyed by day of week (0 = Sunday). Missing days are treated as closed. */
function provider_weekly_hours(int $providerId): array
{
    $rows = db_all('SELECT * FROM availability WHERE provider_id = ?', [$providerId]);
    $week = [];
    foreach ($rows as $row) {
        $week[(int) $row['day_of_week']] = $row;
    }
    return $week;
}

/**
 * Load everything needed to compute slots for a date range with three queries,
 * so month views don't run queries per day.
 */
function availability_context(int $providerId, string $from, string $to, ?int $ignoreBookingId = null): array
{
    $timeOff = db_all(
        'SELECT start_date, end_date, type, reason FROM time_off WHERE provider_id = ? AND end_date >= ? AND start_date <= ?',
        [$providerId, $from, $to]
    );
    $bookings = db_all(
        "SELECT id, booking_date, start_time, end_time FROM bookings
         WHERE provider_id = ? AND booking_date BETWEEN ? AND ? AND status IN ('pending','confirmed','in_progress')",
        [$providerId, $from, $to]
    );
    $byDate = [];
    foreach ($bookings as $b) {
        if ($ignoreBookingId && (int) $b['id'] === $ignoreBookingId) {
            continue;
        }
        $byDate[$b['booking_date']][] = [time_to_minutes($b['start_time']), time_to_minutes($b['end_time'])];
    }
    return ['week' => provider_weekly_hours($providerId), 'time_off' => $timeOff, 'bookings' => $byDate];
}

function date_in_time_off(array $ctx, string $date): ?array
{
    foreach ($ctx['time_off'] as $off) {
        if ($date >= $off['start_date'] && $date <= $off['end_date']) {
            return $off;
        }
    }
    return null;
}

function booking_window(): array
{
    return [date('Y-m-d'), date('Y-m-d', strtotime('+' . (int) config('booking.max_days_ahead') . ' days'))];
}

/** Free start times ("HH:MM") on a date for a job lasting $duration minutes. */
function slots_for_date(array $ctx, string $date, int $duration): array
{
    [$first, $last] = booking_window();
    if ($date < $first || $date > $last || $duration <= 0) {
        return [];
    }
    $day = $ctx['week'][(int) date('w', strtotime($date))] ?? null;
    if (!$day || !(int) $day['is_working'] || date_in_time_off($ctx, $date)) {
        return [];
    }

    $open = time_to_minutes($day['start_time']);
    $close = time_to_minutes($day['end_time']);
    $busy = $ctx['bookings'][$date] ?? [];
    if ($day['break_start'] && $day['break_end']) {
        $busy[] = [time_to_minutes($day['break_start']), time_to_minutes($day['break_end'])];
    }

    $earliest = $open;
    if ($date === date('Y-m-d')) {
        $now = (int) date('G') * 60 + (int) date('i') + (int) config('booking.min_lead_minutes');
        $earliest = max($open, $now);
    }

    $step = max(15, (int) config('booking.slot_interval'));
    $slots = [];
    for ($start = $open; $start + $duration <= $close; $start += $step) {
        if ($start < $earliest) {
            continue;
        }
        $end = $start + $duration;
        foreach ($busy as [$bStart, $bEnd]) {
            if ($start < $bEnd && $end > $bStart) {
                continue 2;
            }
        }
        $slots[] = minutes_to_time($start);
    }
    return $slots;
}

function available_slots(int $providerId, string $date, int $duration): array
{
    return slots_for_date(availability_context($providerId, $date, $date), $date, $duration);
}

/** Per-day summary for a month view: ['2026-11-03' => ['slots' => 12, 'reason' => null], …]. */
function month_availability(int $providerId, string $month, int $duration): array
{
    $from = $month . '-01';
    $to = date('Y-m-t', strtotime($from));
    $ctx = availability_context($providerId, $from, $to);
    $days = [];
    for ($d = strtotime($from); $d <= strtotime($to); $d = strtotime("+1 day", $d)) {
        $date = date('Y-m-d', $d);
        $off = date_in_time_off($ctx, $date);
        $days[$date] = [
            'slots'  => count(slots_for_date($ctx, $date, $duration)),
            'reason' => $off ? ($off['type'] === 'vacation' ? 'Vacation' : 'Day off') : null,
        ];
    }
    return $days;
}

/** The next few dates with at least one free slot — used on the provider profile. */
function next_available_days(int $providerId, int $duration, int $limit = 5): array
{
    [$first] = booking_window();
    $to = date('Y-m-d', strtotime('+21 days'));
    $ctx = availability_context($providerId, $first, $to);
    $result = [];
    for ($d = strtotime($first); $d <= strtotime($to) && count($result) < $limit; $d = strtotime("+1 day", $d)) {
        $date = date('Y-m-d', $d);
        $slots = slots_for_date($ctx, $date, $duration);
        if ($slots) {
            $result[] = ['date' => $date, 'slots' => $slots];
        }
    }
    return $result;
}
