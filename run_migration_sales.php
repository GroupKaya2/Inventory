<?php
/**
 * Run once to create sales and sale_items tables.
 * Visit in browser: run_migration_sales.php
 */
session_start();
if (!isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit;
}

$output = [];
$sql = file_get_contents(__DIR__ . '/migration_sales.sql');
$statements = array_filter(array_map('trim', explode(';', $sql)));
$conn = new mysqli("localhost", "root", "", "login_system");

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

foreach ($statements as $stmt) {
    if ($stmt === '' || preg_match('/^--/', $stmt)) continue;
    if ($conn->query($stmt)) {
        $output[] = 'OK: ' . substr($stmt, 0, 60) . '...';
    } else {
        $output[] = 'Error: ' . $conn->error . ' - ' . substr($stmt, 0, 80);
    }
}
$conn->close();

header('Content-Type: text/html; charset=utf-8');
echo '<h2>Sales migration</h2><pre>' . implode("\n", $output) . '</pre>';
echo '<p><a href="sales.php">Go to Sales</a></p>';
?>
