<?php
// fetch_workload.php
session_start();
header('Content-Type: application/json');
if (!isset($_SESSION['user_id'])) { echo json_encode(['success'=>false,'message'=>'Unauthorized']); exit; }
include 'db.php';

// Check if work_orders table exists
$chk = $conn->query("SHOW TABLES LIKE 'work_orders'");
if (!$chk || $chk->num_rows === 0) {
    echo json_encode(['success'=>false,'message'=>'work_orders table not found']);
    exit;
}

// Get weekly work order counts for last 12 weeks
$r = $conn->query("
    SELECT
        YEARWEEK(created_at, 1) AS yw,
        DATE_FORMAT(MIN(created_at), '%b %d') AS week_label,
        COUNT(*) AS count
    FROM work_orders
    WHERE created_at >= DATE_SUB(NOW(), INTERVAL 12 WEEK)
    GROUP BY yw
    ORDER BY yw ASC
");

$weeks = [];
if ($r) while ($row = $r->fetch_assoc()) $weeks[] = $row;

$avg = 0;
if (count($weeks)) {
    $avg = round(array_sum(array_column($weeks, 'count')) / count($weeks), 1);
}

echo json_encode(['success'=>true,'weeks'=>$weeks,'avg_per_week'=>$avg]);
$conn->close();