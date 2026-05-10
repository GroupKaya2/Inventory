<?php
session_start();
header('Content-Type: application/json');
require_once __DIR__ . '/db.php';

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$year  = max(2020, min(2100, (int) ($_GET['year']  ?? date('Y'))));
$month = max(1,    min(12,   (int) ($_GET['month'] ?? date('n'))));

$monthStart = sprintf('%04d-%02d-01', $year, $month);
$monthEnd   = date('Y-m-t', strtotime($monthStart));

/* ── Sales aggregated by day ── */
$salesByDay = [];
$r = $conn->query("
    SELECT
        sale_date,
        COALESCE(SUM(parts_total), 0)               AS parts,
        COALESCE(SUM(labor_total), 0)               AS labor,
        COALESCE(SUM(parts_total + labor_total), 0) AS gross,
        COUNT(*) AS txn_count
    FROM sales
    WHERE sale_date BETWEEN '$monthStart' AND '$monthEnd'
    GROUP BY sale_date
");
if ($r) while ($row = $r->fetch_assoc()) $salesByDay[$row['sale_date']] = $row;

/* ── Expenses: total + item list per day ── */
$expTotalByDay = [];
$expItemsByDay = [];
$r = $conn->query("
    SELECT expense_date, description, amount
    FROM expenses
    WHERE expense_date BETWEEN '$monthStart' AND '$monthEnd'
    ORDER BY expense_date, id
");
if ($r) while ($row = $r->fetch_assoc()) {
    $dt = $row['expense_date'];
    $expTotalByDay[$dt] = ($expTotalByDay[$dt] ?? 0) + (float) $row['amount'];
    $expItemsByDay[$dt][] = [
        'description' => $row['description'],
        'amount'      => (float) $row['amount'],
    ];
}

/* ── Build daily rows, skip Sundays ── */
$days     = [];
$cursor   = strtotime($monthStart);
$endStamp = strtotime($monthEnd);

while ($cursor <= $endStamp) {
    $dow  = (int) date('N', $cursor);   // 1=Mon … 7=Sun
    $date = date('Y-m-d', $cursor);

    if ($dow !== 7) {
        $s      = $salesByDay[$date]  ?? null;
        $exp    = $expTotalByDay[$date] ?? 0.0;
        $expItems = $expItemsByDay[$date] ?? [];
        $parts  = $s ? (float) $s['parts'] : 0.0;
        $labor  = $s ? (float) $s['labor'] : 0.0;
        $gross  = $parts + $labor;
        $net    = $gross - $exp;

        $days[] = [
            'date'          => $date,
            'dow'           => $dow,
            'day_name'      => date('D', $cursor),
            'parts'         => $parts,
            'labor'         => $labor,
            'gross'         => $gross,
            'expenses'      => $exp,
            'expense_items' => $expItems,   // ← list with descriptions
            'net'           => $net,
            'txn_count'     => $s ? (int) $s['txn_count'] : 0,
            'has_data'      => $s !== null,
        ];
    }
    $cursor = strtotime('+1 day', $cursor);
}

/* ── Group into weeks (new week every Monday) ── */
$weeks   = [];
$weekIdx = -1;
foreach ($days as $day) {
    if ($weekIdx < 0 || $day['dow'] === 1) {
        $weeks[] = [];
        $weekIdx = count($weeks) - 1;
    }
    $weeks[$weekIdx][] = $day;
}

$weekSummaries = array_map(fn($w) => [
    'days'     => $w,
    'parts'    => array_sum(array_column($w, 'parts')),
    'labor'    => array_sum(array_column($w, 'labor')),
    'gross'    => array_sum(array_column($w, 'gross')),
    'expenses' => array_sum(array_column($w, 'expenses')),
    'net'      => array_sum(array_column($w, 'net')),
], $weeks);

/* ── Monthly totals ── */
$totals = [
    'parts'    => array_sum(array_column($days, 'parts')),
    'labor'    => array_sum(array_column($days, 'labor')),
    'gross'    => array_sum(array_column($days, 'parts')) + array_sum(array_column($days, 'labor')),
    'expenses' => array_sum(array_column($days, 'expenses')),
    'net'      => array_sum(array_column($days, 'net')),
];

echo json_encode([
    'success'    => true,
    'year'       => $year,
    'month'      => $month,
    'month_name' => date('F Y', strtotime($monthStart)),
    'weeks'      => $weekSummaries,
    'totals'     => $totals,
]);
$conn->close();