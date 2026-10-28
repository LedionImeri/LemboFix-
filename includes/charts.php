<?php
/**
 * Lightweight server-rendered charts (HTML/CSS only, no chart library).
 */

declare(strict_types=1);

/** Vertical bar chart. $series = [['label' => 'Jan', 'value' => 12.5], …] */
function chart_bars(array $series, bool $isMoney = false): string
{
    $max = max(1, ...array_map(fn ($p) => (float) $p['value'], $series ?: [['value' => 1]]));
    $html = '<div class="chart-bars" role="img" aria-label="Bar chart">';
    foreach ($series as $point) {
        $height = max(2, round((float) $point['value'] / $max * 100));
        $value = $isMoney ? money($point['value']) : number_format((float) $point['value']);
        $html .= '<div class="chart-bar"><div class="chart-bar-fill" style="height:' . $height . '%"><span>' . e($value) . '</span></div>'
            . '<label>' . e($point['label']) . '</label></div>';
    }
    return $html . '</div>';
}

/** Horizontal bars. $rows = [['label' => 'Plumbing', 'value' => 40], …] */
function chart_hbars(array $rows, bool $isMoney = false): string
{
    if (!$rows) {
        return '<p class="text-muted text-sm">No data yet.</p>';
    }
    $max = max(1, ...array_map(fn ($r) => (float) $r['value'], $rows));
    $html = '<div class="hbars">';
    foreach ($rows as $row) {
        $pct = round((float) $row['value'] / $max * 100);
        $html .= '<div><div class="hbar-top"><span>' . e($row['label']) . '</span><strong>'
            . e($isMoney ? money($row['value']) : number_format((float) $row['value'])) . '</strong></div>'
            . '<div class="hbar-track"><div class="hbar-fill" style="width:' . $pct . '%"></div></div></div>';
    }
    return $html . '</div>';
}

/** Donut chart built with a conic-gradient. $slices = [['label', 'value', 'color'], …] */
function chart_donut(array $slices, string $centerLabel): string
{
    $total = array_sum(array_column($slices, 'value'));
    $stops = [];
    $start = 0.0;
    foreach ($slices as $s) {
        $deg = $total ? $s['value'] / $total * 360 : 0;
        $stops[] = $s['color'] . ' ' . round($start, 2) . 'deg ' . round($start + $deg, 2) . 'deg';
        $start += $deg;
    }
    $gradient = $total ? 'conic-gradient(' . implode(', ', $stops) . ')' : 'var(--ink-100)';
    $html = '<div class="donut-wrap"><div class="donut" style="background:' . e($gradient) . '" role="img" aria-label="' . e($centerLabel) . ' breakdown">'
        . '<div class="donut-center"><div><strong>' . number_format((int) $total) . '</strong><span>' . e($centerLabel) . '</span></div></div></div><div class="legend">';
    foreach ($slices as $s) {
        $html .= '<div><i style="background:' . e($s['color']) . '"></i>' . e($s['label']) . '<b>' . number_format((int) $s['value']) . '</b></div>';
    }
    return $html . '</div></div>';
}
