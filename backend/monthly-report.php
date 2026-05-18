<?php
session_start();
header('Content-Type: application/json');
require_once __DIR__ . '/db.php';

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

/** Monday–Saturday week containing $dateStr (Y-m-d). Sundays map to the week that ended Saturday. */
function week_bounds_mon_sat(string $dateStr): array
{
    $ts = strtotime($dateStr);
    $dow = (int) date('N', $ts); // 1=Mon … 7=Sun
    if ($dow === 7) {
        $monday = strtotime('-6 days', $ts);
    } else {
        $monday = strtotime('-' . ($dow - 1) . ' days', $ts);
    }
    $saturday = strtotime('+5 days', $monday);
    return [date('Y-m-d', $monday), date('Y-m-d', $saturday)];
}

function sum_day_fields(array $days, string $field): float
{
    return array_sum(array_column($days, $field));
}

function load_sales_by_day(mysqli $conn, string $from, string $to): array
{
    $out = [];
    $r = $conn->query("
        SELECT
            sale_date,
            COALESCE(SUM(parts_total), 0)               AS parts,
            COALESCE(SUM(labor_total), 0)               AS labor,
            COALESCE(SUM(parts_total + labor_total), 0) AS gross,
            COUNT(*) AS txn_count
        FROM sales
        WHERE sale_date BETWEEN '$from' AND '$to'
        GROUP BY sale_date
    ");
    if ($r) {
        while ($row = $r->fetch_assoc()) {
            $out[$row['sale_date']] = $row;
        }
    }
    return $out;
}

function load_expenses_by_day(mysqli $conn, string $from, string $to): array
{
    $totals = [];
    $items = [];
    $r = $conn->query("
        SELECT expense_date, description, amount
        FROM expenses
        WHERE expense_date BETWEEN '$from' AND '$to'
        ORDER BY expense_date, id
    ");
    if ($r) {
        while ($row = $r->fetch_assoc()) {
            $dt = $row['expense_date'];
            $totals[$dt] = ($totals[$dt] ?? 0) + (float) $row['amount'];
            $items[$dt][] = [
                'description' => $row['description'],
                'amount' => (float) $row['amount'],
            ];
        }
    }
    return [$totals, $items];
}

function build_working_days(string $from, string $to, array $salesByDay, array $expTotalByDay, array $expItemsByDay): array
{
    $days = [];
    $cursor = strtotime($from);
    $endStamp = strtotime($to);

    while ($cursor <= $endStamp) {
        $dow = (int) date('N', $cursor);
        if ($dow !== 7) {
            $date = date('Y-m-d', $cursor);
            $s = $salesByDay[$date] ?? null;
            $exp = $expTotalByDay[$date] ?? 0.0;
            $expItems = $expItemsByDay[$date] ?? [];
            $parts = $s ? (float) $s['parts'] : 0.0;
            $labor = $s ? (float) $s['labor'] : 0.0;
            $gross = $parts + $labor;
            $net = $gross - $exp;

            $days[] = [
                'date' => $date,
                'dow' => $dow,
                'day_name' => date('D', $cursor),
                'parts' => $parts,
                'labor' => $labor,
                'gross' => $gross,
                'expenses' => $exp,
                'expense_items' => $expItems,
                'net' => $net,
                'txn_count' => $s ? (int) $s['txn_count'] : 0,
                'has_data' => $s !== null || $exp > 0,
            ];
        }
        $cursor = strtotime('+1 day', $cursor);
    }
    return $days;
}

function totals_from_days(array $days): array
{
    return [
        'parts' => sum_day_fields($days, 'parts'),
        'labor' => sum_day_fields($days, 'labor'),
        'gross' => sum_day_fields($days, 'gross'),
        'expenses' => sum_day_fields($days, 'expenses'),
        'net' => sum_day_fields($days, 'net'),
    ];
}

// Selected date (picker) or legacy month/year
$rawDate = trim($_GET['date'] ?? '');
if ($rawDate !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $rawDate)) {
    $selectedDate = $rawDate;
} else {
    $year = max(2020, min(2100, (int) ($_GET['year'] ?? date('Y'))));
    $month = max(1, min(12, (int) ($_GET['month'] ?? date('n'))));
    $day = min((int) date('t', strtotime("$year-$month-01")), max(1, (int) ($_GET['day'] ?? date('j'))));
    $selectedDate = sprintf('%04d-%02d-%02d', $year, $month, $day);
}

[$weekStart, $weekEnd] = week_bounds_mon_sat($selectedDate);

$monthStart = date('Y-m-01', strtotime($selectedDate));
$monthEnd = date('Y-m-t', strtotime($selectedDate));

// Week data
$weekSales = load_sales_by_day($conn, $weekStart, $weekEnd);
[$weekExpTotals, $weekExpItems] = load_expenses_by_day($conn, $weekStart, $weekEnd);
$weekDays = build_working_days($weekStart, $weekEnd, $weekSales, $weekExpTotals, $weekExpItems);
$weekTotals = totals_from_days($weekDays);

// Full month (Mon–Sat working days only, for monthly total row)
$monthSales = load_sales_by_day($conn, $monthStart, $monthEnd);
[$monthExpTotals, $monthExpItems] = load_expenses_by_day($conn, $monthStart, $monthEnd);
$monthDays = build_working_days($monthStart, $monthEnd, $monthSales, $monthExpTotals, $monthExpItems);
$monthTotals = totals_from_days($monthDays);

$weekStartLabel = date('M j', strtotime($weekStart));
$weekEndLabel = date('M j, Y', strtotime($weekEnd));

echo json_encode([
    'success' => true,
    'selected_date' => $selectedDate,
    'week_start' => $weekStart,
    'week_end' => $weekEnd,
    'week_label' => $weekStartLabel . ' – ' . $weekEndLabel,
    'week_subtitle' => 'Monday – Saturday',
    'month' => (int) date('n', strtotime($selectedDate)),
    'year' => (int) date('Y', strtotime($selectedDate)),
    'month_name' => date('F Y', strtotime($monthStart)),
    'days' => $weekDays,
    'week_totals' => $weekTotals,
    'month_totals' => $monthTotals,
    'prev_week_date' => date('Y-m-d', strtotime($weekStart . ' -7 days')),
    'next_week_date' => date('Y-m-d', strtotime($weekStart . ' +7 days')),
]);
$conn->close();
