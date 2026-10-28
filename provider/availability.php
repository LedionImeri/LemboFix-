<?php
require dirname(__DIR__) . '/includes/bootstrap.php';
$user = require_login(ROLE_PROVIDER);
$provider = current_provider();
$pid = (int) $provider['id'];
$self = 'provider/availability.php';

if (is_post()) {
    require_csrf();
    switch (input('form')) {
        case 'hours':
            $days = $_POST['days'] ?? [];
            $errors = [];
            $rows = [];
            foreach (weekday_names() as $dow => $name) {
                $d = is_array($days[$dow] ?? null) ? $days[$dow] : [];
                $working = !empty($d['working']);
                $time = fn ($k) => preg_match('/^([01]\d|2[0-3]):[0-5]\d$/', (string) ($d[$k] ?? '')) ? $d[$k] : null;
                [$start, $end, $bStart, $bEnd] = [$time('start'), $time('end'), $time('break_start'), $time('break_end')];
                if ($working) {
                    if (!$start || !$end || $start >= $end) {
                        $errors[] = "$name: the end time must be after the start time.";
                        continue;
                    }
                    if (($bStart xor $bEnd) || ($bStart && ($bStart >= $bEnd || $bStart < $start || $bEnd > $end))) {
                        $errors[] = "$name: the break must be inside working hours (or leave both break fields empty).";
                        continue;
                    }
                }
                $rows[] = [$pid, $dow, $working ? 1 : 0, ($start ?: '09:00') . ':00', ($end ?: '17:00') . ':00',
                           $bStart ? $bStart . ':00' : null, $bEnd ? $bEnd . ':00' : null];
            }
            if ($errors) {
                flash('error', $errors[0]);
                redirect($self);
            }
            $stmt = db()->prepare(
                'INSERT INTO availability (provider_id, day_of_week, is_working, start_time, end_time, break_start, break_end)
                 VALUES (?, ?, ?, ?, ?, ?, ?)
                 ON DUPLICATE KEY UPDATE is_working = VALUES(is_working), start_time = VALUES(start_time), end_time = VALUES(end_time),
                                         break_start = VALUES(break_start), break_end = VALUES(break_end)'
            );
            foreach ($rows as $row) {
                $stmt->execute($row);
            }
            flash('success', 'Working hours saved. Customers now see your updated free slots.');
            redirect($self);

        case 'time_off':
            $v = new Validator($_POST);
            $v->required('start_date', 'Start date')->date('start_date', 'Start date')
              ->required('end_date', 'End date')->date('end_date', 'End date')
              ->in('type', ['day_off', 'vacation'])
              ->length('reason', 'Reason', 0, 160);
            $v->custom(!$v->fails() && $v->value('end_date') < $v->value('start_date'), 'end_date', 'The end date must be on or after the start date.')
              ->custom(!$v->fails() && $v->value('end_date') < date('Y-m-d'), 'end_date', 'Time off must be in the future.');
            if ($v->fails()) {
                flash('error', $v->firstError());
                redirect($self . '#time-off');
            }
            $conflicts = (int) db_value(
                "SELECT COUNT(*) FROM bookings WHERE provider_id = ? AND booking_date BETWEEN ? AND ? AND status IN ('pending','confirmed','in_progress')",
                [$pid, $v->value('start_date'), $v->value('end_date')]
            );
            db_query('INSERT INTO time_off (provider_id, start_date, end_date, type, reason) VALUES (?, ?, ?, ?, ?)',
                [$pid, $v->value('start_date'), $v->value('end_date'), $v->value('type'), $v->value('reason') ?: null]);
            flash($conflicts ? 'warning' : 'success', $conflicts
                ? "Time off saved. Note: you still have $conflicts active booking(s) in that period — please handle them in Bookings."
                : 'Time off saved. Those days are now blocked for new bookings.');
            redirect($self . '#time-off');

        case 'delete_time_off':
            db_query('DELETE FROM time_off WHERE id = ? AND provider_id = ?', [input_int('id'), $pid]);
            flash('success', 'Time off removed.');
            redirect($self . '#time-off');
    }
}

$week = provider_weekly_hours($pid);
$timeOff = db_all('SELECT * FROM time_off WHERE provider_id = ? AND end_date >= CURDATE() ORDER BY start_date', [$pid]);
$preview = [];
$ctx = availability_context($pid, date('Y-m-d'), date('Y-m-d', strtotime('+13 days')));
for ($i = 0; $i < 14; $i++) {
    $date = date('Y-m-d', strtotime("+$i days"));
    $preview[] = ['date' => $date, 'slots' => count(slots_for_date($ctx, $date, 60)), 'off' => date_in_time_off($ctx, $date), 'booked' => count($ctx['bookings'][$date] ?? [])];
}

dashboard_start(['title' => 'Availability', 'subtitle' => 'Set when customers can book you. Booked times are blocked automatically.', 'active' => 'availability']);
?>
<div class="card dash-section">
    <div class="card-head"><h2>Next 14 days</h2><span class="text-sm text-muted">Free 1-hour slots per day</span></div>
    <div class="card-body">
        <div class="calendar-grid" style="grid-template-columns:repeat(7,minmax(0,1fr))">
            <?php foreach ($preview as $p): ?>
                <div class="calendar-day <?= $p['slots'] ? 'is-available' : ($p['off'] ? 'is-off' : '') ?><?= $p['date'] === date('Y-m-d') ? ' is-today' : '' ?>"
                     style="aspect-ratio:auto;padding:10px 4px;cursor:default" title="<?= e($p['off'] ? ($p['off']['reason'] ?: 'Time off') : $p['booked'] . ' booking(s)') ?>">
                    <span class="text-xs text-muted"><?= e(date('D', strtotime($p['date']))) ?></span>
                    <span><?= e(date('j M', strtotime($p['date']))) ?></span>
                    <span class="text-xs <?= $p['slots'] ? 'text-success' : 'text-muted' ?>"><?= $p['off'] ? 'Off' : ($p['slots'] ? $p['slots'] . ' free' : 'Full/closed') ?></span>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
</div>

<form class="card dash-section" method="post" action="<?= e(url($self)) ?>">
    <?= csrf_field() ?>
    <input type="hidden" name="form" value="hours">
    <div class="card-head"><h2>Weekly working hours</h2><button type="submit" class="btn btn-primary btn-sm"><?= icon('check', 16) ?> Save hours</button></div>
    <?php foreach (weekday_names() as $dow => $name): $d = $week[$dow] ?? ['is_working' => 0, 'start_time' => '09:00', 'end_time' => '17:00', 'break_start' => null, 'break_end' => null]; ?>
        <div class="availability-row<?= (int) $d['is_working'] ? '' : ' is-off' ?>" data-day-row>
            <label class="switch">
                <input type="checkbox" name="days[<?= $dow ?>][working]" value="1"<?= checked((bool) (int) $d['is_working']) ?> data-day-toggle>
                <span class="switch-track"></span> <?= e($name) ?>
            </label>
            <div class="availability-times">
                <input class="input" type="time" name="days[<?= $dow ?>][start]" value="<?= e(format_time($d['start_time'])) ?>" step="900" aria-label="<?= e($name) ?> start">
                <span>to</span>
                <input class="input" type="time" name="days[<?= $dow ?>][end]" value="<?= e(format_time($d['end_time'])) ?>" step="900" aria-label="<?= e($name) ?> end">
                <span class="break-label">Break</span>
                <input class="input" type="time" name="days[<?= $dow ?>][break_start]" value="<?= e(format_time($d['break_start'])) ?>" step="900" aria-label="<?= e($name) ?> break start">
                <span>–</span>
                <input class="input" type="time" name="days[<?= $dow ?>][break_end]" value="<?= e(format_time($d['break_end'])) ?>" step="900" aria-label="<?= e($name) ?> break end">
            </div>
        </div>
    <?php endforeach; ?>
    <div class="card-foot text-sm text-muted"><?= icon('info', 14) ?> Customers can book start times every <?= (int) config('booking.slot_interval') ?> minutes inside these hours, as long as the whole job fits before closing and outside your break.</div>
</form>

<div class="dash-grid" id="time-off">
    <div class="card dash-section">
        <div class="card-head"><h2>Days off &amp; vacations</h2></div>
        <?php if ($timeOff): ?>
            <ul class="list-plain mini-list">
                <?php foreach ($timeOff as $t): ?>
                    <li>
                        <span class="notif-ico"><?= icon($t['type'] === 'vacation' ? 'sparkles' : 'calendar-off', 16) ?></span>
                        <div class="grow">
                            <strong><?= e(format_date($t['start_date'], 'D, M j')) ?><?= $t['end_date'] !== $t['start_date'] ? ' – ' . e(format_date($t['end_date'], 'D, M j')) : '' ?></strong>
                            <span><?= e($t['type'] === 'vacation' ? 'Vacation' : 'Day off') ?><?= $t['reason'] ? ' · ' . e($t['reason']) : '' ?></span>
                        </div>
                        <form method="post" action="<?= e(url($self)) ?>">
                            <?= csrf_field() ?>
                            <input type="hidden" name="form" value="delete_time_off">
                            <input type="hidden" name="id" value="<?= (int) $t['id'] ?>">
                            <button class="icon-btn" type="submit" aria-label="Remove" data-confirm="Remove this time off?" data-confirm-text="These days will become bookable again." data-confirm-ok="Remove"><?= icon('trash', 16) ?></button>
                        </form>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php else: ?>
            <?= empty_state('No upcoming time off', 'Add days off or vacations to block them in your calendar.', 'calendar-off') ?>
        <?php endif; ?>
    </div>
    <form class="card dash-section" method="post" action="<?= e(url($self)) ?>" data-validate novalidate>
        <?= csrf_field() ?>
        <input type="hidden" name="form" value="time_off">
        <div class="card-head"><h2>Add time off</h2></div>
        <div class="card-body">
            <div class="form-row">
                <label class="field"><span class="field-label">From</span><input class="input" type="date" name="start_date" min="<?= date('Y-m-d') ?>" required></label>
                <label class="field"><span class="field-label">To</span><input class="input" type="date" name="end_date" min="<?= date('Y-m-d') ?>" required></label>
            </div>
            <label class="field"><span class="field-label">Type</span>
                <select class="select" name="type"><option value="day_off">Day off</option><option value="vacation">Vacation</option></select></label>
            <label class="field"><span class="field-label">Note <span class="text-muted">(private)</span></span>
                <input class="input" name="reason" maxlength="160" placeholder="e.g. Family holiday"></label>
            <button type="submit" class="btn btn-dark btn-block"><?= icon('plus', 18) ?> Block these dates</button>
        </div>
    </form>
</div>
<?php dashboard_end(); ?>
