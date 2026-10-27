<?php
session_start();
error_reporting(0);
ini_set('display_errors', '0');
ob_start();
header('Content-Type: application/json');
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/audit.php';
ob_clean();

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Not logged in']);
    exit;
}

$userId = (int) $_SESSION['user_id'];
$action = isset($_GET['action']) ? $_GET['action'] : (isset($_POST['action']) ? $_POST['action'] : '');

// "Less Expenses" lines of the month-end summary, in the order they appear in
// the Excel sales-monitoring sheet. All are encoded manually.
$EXPENSE_LINES = [
    'salaries'          => 'Salaries',
    'light_water'       => 'Light & Water Bills',
    'office_supplies'   => 'Office Supplies',
    'rep_entertainment' => 'Representation & Entertainment',
    'postage_tel'       => 'Postage, Telephone & Telegram',
    'repairs_maint'     => 'Repairs & Maintenance',
    'periodicals'       => 'Periodicals & Magazine',
    'gasoline_oil'      => 'Gasoline & Oil',
    'transport_travel'  => 'Transportation & Travel',
    'employee_benefits' => 'Employees Benefits',
    'misc'              => 'MISC.',
    'sc_supplies'       => 'SC Supplies',
    'others'            => 'Others',
];

function mr_valid_month($m)
{
    return is_string($m) && preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $m) === 1;
}

function mr_money($v)
{
    $v = is_numeric($v) ? (float) $v : 0.0;
    $v = max(0.0, min($v, 9999999999.99));
    return round($v, 2);
}

try {

    // Manual per-day entries (Credit, COS Weekly, Cost of Sales Labor).
    // No foreign keys on purpose -- this file must never be able to break a page.
    $conn->query("CREATE TABLE IF NOT EXISTS monthly_report_days (
        report_date DATE NOT NULL PRIMARY KEY,
        credit      DECIMAL(12,2) NOT NULL DEFAULT 0,
        cos_parts   DECIMAL(12,2) NOT NULL DEFAULT 0,
        cos_labor   DECIMAL(12,2) NOT NULL DEFAULT 0,
        updated_by  INT NULL,
        updated_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    // Manual month-end expense lines.
    $conn->query("CREATE TABLE IF NOT EXISTS monthly_report_expenses (
        report_month CHAR(7) NOT NULL,
        line_key     VARCHAR(40) NOT NULL,
        amount       DECIMAL(12,2) NOT NULL DEFAULT 0,
        updated_by   INT NULL,
        updated_at   TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (report_month, line_key)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    /* ══════════════════════════════════
       FETCH -- every date of the month, sales totals from the system,
       plus whatever has been encoded manually.
    ══════════════════════════════════ */
    if ($action === 'fetch') {
        $month = isset($_GET['month']) ? $_GET['month'] : date('Y-m');
        if (!mr_valid_month($month)) {
            echo json_encode(['success' => false, 'message' => 'Invalid month.']);
            exit;
        }

        $first = $month . '-01';
        $daysInMonth = (int) date('t', strtotime($first));
        $last = $month . '-' . str_pad((string) $daysInMonth, 2, '0', STR_PAD_LEFT);

        // Parts / Labor per day (these are already net of any discounts)
        $salesByDay = [];
        $stmt = $conn->prepare(
            "SELECT sale_date,
                    COALESCE(SUM(parts_total), 0) AS parts,
                    COALESCE(SUM(labor_total), 0) AS labor,
                    COUNT(*) AS txn
             FROM sales
             WHERE sale_date BETWEEN ? AND ?
             GROUP BY sale_date"
        );
        $stmt->bind_param('ss', $first, $last);
        $stmt->execute();
        $res = $stmt->get_result();
        while ($row = $res->fetch_assoc()) {
            $salesByDay[$row['sale_date']] = $row;
        }
        $stmt->close();

        // Customer / ref numbers per day
        $hasRef = $conn->query("SHOW COLUMNS FROM sales LIKE 'reference_number'")->num_rows > 0;
        $refSelect = $hasRef ? 'reference_number' : "'' AS reference_number";
        $refsByDay = [];
        $stmt = $conn->prepare(
            "SELECT sale_date, $refSelect, customer_name
             FROM sales
             WHERE sale_date BETWEEN ? AND ?
             ORDER BY sale_date, id"
        );
        $stmt->bind_param('ss', $first, $last);
        $stmt->execute();
        $res = $stmt->get_result();
        while ($row = $res->fetch_assoc()) {
            $refsByDay[$row['sale_date']][] = [
                'ref'      => (string) $row['reference_number'],
                'customer' => (string) $row['customer_name'],
            ];
        }
        $stmt->close();

        // Manual per-day entries
        $manualByDay = [];
        $stmt = $conn->prepare(
            "SELECT report_date, credit, cos_parts, cos_labor
             FROM monthly_report_days
             WHERE report_date BETWEEN ? AND ?"
        );
        $stmt->bind_param('ss', $first, $last);
        $stmt->execute();
        $res = $stmt->get_result();
        while ($row = $res->fetch_assoc()) {
            $manualByDay[$row['report_date']] = $row;
        }
        $stmt->close();

        // Every date 1..N, zero-filled when nothing was sold
        $days = [];
        for ($d = 1; $d <= $daysInMonth; $d++) {
            $date = sprintf('%s-%02d', $month, $d);
            $ts = strtotime($date);
            $s = isset($salesByDay[$date]) ? $salesByDay[$date] : null;
            $m = isset($manualByDay[$date]) ? $manualByDay[$date] : null;
            $days[] = [
                'date'      => $date,
                'day'       => $d,
                'dow'       => (int) date('N', $ts),
                'day_name'  => date('D', $ts),
                'parts'     => $s ? (float) $s['parts'] : 0.0,
                'labor'     => $s ? (float) $s['labor'] : 0.0,
                'txn'       => $s ? (int) $s['txn'] : 0,
                'sales'     => isset($refsByDay[$date]) ? $refsByDay[$date] : [],
                'credit'    => $m ? (float) $m['credit'] : 0.0,
                'cos_parts' => $m ? (float) $m['cos_parts'] : 0.0,
                'cos_labor' => $m ? (float) $m['cos_labor'] : 0.0,
            ];
        }

        // Manual expense lines
        $expenses = [];
        $lines = [];
        foreach ($EXPENSE_LINES as $key => $label) {
            $expenses[$key] = 0.0;
            $lines[] = ['key' => $key, 'label' => $label];
        }
        $stmt = $conn->prepare("SELECT line_key, amount FROM monthly_report_expenses WHERE report_month = ?");
        $stmt->bind_param('s', $month);
        $stmt->execute();
        $res = $stmt->get_result();
        while ($row = $res->fetch_assoc()) {
            if (array_key_exists($row['line_key'], $expenses)) {
                $expenses[$row['line_key']] = (float) $row['amount'];
            }
        }
        $stmt->close();

        echo json_encode([
            'success'       => true,
            'month'         => $month,
            'month_label'   => date('F Y', strtotime($first)),
            'days_in_month' => $daysInMonth,
            'days'          => $days,
            'expense_lines' => $lines,
            'expenses'      => $expenses,
        ]);
        exit;
    }

    /* ══════════════════════════════════
       SAVE -- only the manual fields. Sales figures always come from the
       sales table, so they can't be overwritten from here.
    ══════════════════════════════════ */
    if ($action === 'save') {
        $input = json_decode(file_get_contents('php://input'), true);
        if (!is_array($input)) {
            echo json_encode(['success' => false, 'message' => 'Invalid payload.']);
            exit;
        }

        $month = isset($input['month']) ? $input['month'] : '';
        if (!mr_valid_month($month)) {
            echo json_encode(['success' => false, 'message' => 'Invalid month.']);
            exit;
        }

        $dayRows = (isset($input['days']) && is_array($input['days'])) ? $input['days'] : [];
        $expRows = (isset($input['expenses']) && is_array($input['expenses'])) ? $input['expenses'] : [];
        $changes = 0;

        $conn->begin_transaction();
        try {
            foreach ($dayRows as $row) {
                $date = isset($row['date']) ? (string) $row['date'] : '';
                if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) || substr($date, 0, 7) !== $month) {
                    continue;
                }
                if (!checkdate((int) substr($date, 5, 2), (int) substr($date, 8, 2), (int) substr($date, 0, 4))) {
                    continue;
                }

                $credit   = mr_money(isset($row['credit']) ? $row['credit'] : 0);
                $cosParts = mr_money(isset($row['cos_parts']) ? $row['cos_parts'] : 0);
                $cosLabor = mr_money(isset($row['cos_labor']) ? $row['cos_labor'] : 0);

                $sel = $conn->prepare("SELECT credit, cos_parts, cos_labor FROM monthly_report_days WHERE report_date = ?");
                $sel->bind_param('s', $date);
                $sel->execute();
                $old = $sel->get_result()->fetch_assoc();
                $sel->close();

                if ($old) {
                    if (abs((float) $old['credit'] - $credit) < 0.005
                        && abs((float) $old['cos_parts'] - $cosParts) < 0.005
                        && abs((float) $old['cos_labor'] - $cosLabor) < 0.005) {
                        continue; // nothing actually changed
                    }
                } elseif ($credit == 0 && $cosParts == 0 && $cosLabor == 0) {
                    continue; // don't store empty rows
                }

                $up = $conn->prepare(
                    "INSERT INTO monthly_report_days (report_date, credit, cos_parts, cos_labor, updated_by)
                     VALUES (?, ?, ?, ?, ?)
                     ON DUPLICATE KEY UPDATE credit = VALUES(credit), cos_parts = VALUES(cos_parts),
                                             cos_labor = VALUES(cos_labor), updated_by = VALUES(updated_by)"
                );
                $up->bind_param('sdddi', $date, $credit, $cosParts, $cosLabor, $userId);
                $up->execute();
                $up->close();

                logAudit(
                    $conn, $userId, $old ? 'UPDATE' : 'CREATE', 'monthly_report',
                    (int) str_replace('-', '', $date),
                    $old ? json_encode(['date' => $date, 'credit' => (float) $old['credit'], 'cos_parts' => (float) $old['cos_parts'], 'cos_labor' => (float) $old['cos_labor']]) : null,
                    json_encode(['date' => $date, 'credit' => $credit, 'cos_parts' => $cosParts, 'cos_labor' => $cosLabor])
                );
                $changes++;
            }

            $ym = (int) str_replace('-', '', $month);
            $idx = 0;
            foreach ($EXPENSE_LINES as $key => $label) {
                $idx++;
                if (!array_key_exists($key, $expRows)) {
                    continue;
                }
                $amount = mr_money($expRows[$key]);

                $sel = $conn->prepare("SELECT amount FROM monthly_report_expenses WHERE report_month = ? AND line_key = ?");
                $sel->bind_param('ss', $month, $key);
                $sel->execute();
                $old = $sel->get_result()->fetch_assoc();
                $sel->close();

                if ($old) {
                    if (abs((float) $old['amount'] - $amount) < 0.005) {
                        continue;
                    }
                } elseif ($amount == 0) {
                    continue;
                }

                $up = $conn->prepare(
                    "INSERT INTO monthly_report_expenses (report_month, line_key, amount, updated_by)
                     VALUES (?, ?, ?, ?)
                     ON DUPLICATE KEY UPDATE amount = VALUES(amount), updated_by = VALUES(updated_by)"
                );
                $up->bind_param('ssdi', $month, $key, $amount, $userId);
                $up->execute();
                $up->close();

                // Offset by 50 so these IDs can never collide with the per-day (YYYYMMDD) ones.
                logAudit(
                    $conn, $userId, $old ? 'UPDATE' : 'CREATE', 'monthly_report',
                    $ym * 100 + 50 + $idx,
                    $old ? json_encode(['month' => $month, 'line' => $label, 'amount' => (float) $old['amount']]) : null,
                    json_encode(['month' => $month, 'line' => $label, 'amount' => $amount])
                );
                $changes++;
            }

            $conn->commit();
            echo json_encode(['success' => true, 'saved' => $changes]);
        } catch (\Throwable $e) {
            $conn->rollback();
            echo json_encode(['success' => false, 'message' => 'Save failed: ' . $e->getMessage()]);
        }
        exit;
    }

    echo json_encode(['success' => false, 'message' => 'Unknown action.']);
    $conn->close();

} catch (\Throwable $e) {
    while (ob_get_level() > 0) {
        ob_end_clean();
    }
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Server error: ' . $e->getMessage()]);
    exit;
}