<?php
/**
 * debug_forecast.php
 * TEMPORARY - delete after fixing
 * Visit: http://localhost/Inventory_Dispeedway/debug_forecast.php
 */
session_start();
header('Content-Type: text/html; charset=utf-8');
include 'db.php';

echo "<h2>Forecast Debug</h2>";

// 1. Check session
echo "<h3>1. Session</h3>";
echo "user_id: " . ($_SESSION['user_id'] ?? '<b style=color:red>NOT SET - not logged in!</b>') . "<br>";
echo "role: " . ($_SESSION['role'] ?? 'not set') . "<br>";

// 2. Check tables
echo "<h3>2. Tables</h3>";
$tables = ['sales','sale_items','products','categories','inventory_transactions','work_orders'];
foreach ($tables as $t) {
    $exists = $conn->query("SHOW TABLES LIKE '$t'")->num_rows > 0;
    echo "$t: " . ($exists ? "<span style=color:green>EXISTS</span>" : "<span style=color:red>MISSING</span>") . "<br>";
}

// 3. Check sale_items columns
echo "<h3>3. sale_items columns</h3>";
$r = $conn->query("SHOW COLUMNS FROM sale_items");
if ($r) { while($row=$r->fetch_assoc()) echo $row['Field']." (".$row['Type'].")<br>"; }
else echo "<span style=color:red>Table missing or error</span><br>";

// 4. Check sales count
echo "<h3>4. Data counts</h3>";
$r = $conn->query("SELECT COUNT(*) AS c FROM sales");
echo "sales rows: " . ($r ? $r->fetch_assoc()['c'] : 'error') . "<br>";
$r = $conn->query("SELECT COUNT(*) AS c FROM sale_items");
echo "sale_items rows: " . ($r ? $r->fetch_assoc()['c'] : 'error') . "<br>";
$r = $conn->query("SELECT COUNT(*) AS c FROM products");
echo "products rows: " . ($r ? $r->fetch_assoc()['c'] : 'error') . "<br>";

// 5. Check line_type values
echo "<h3>5. line_type values in sale_items</h3>";
$r = $conn->query("SELECT DISTINCT line_type FROM sale_items LIMIT 10");
if ($r) { while($row=$r->fetch_assoc()) echo $row['line_type']."<br>"; }
else echo "No data or error<br>";

// 6. Try the actual forecast query
echo "<h3>6. Test forecast query</h3>";
$r = $conn->query("
    SELECT si.product_id, si.description, p.code, SUM(si.quantity) AS total_qty
    FROM sale_items si
    JOIN sales s ON s.id = si.sale_id
    LEFT JOIN products p ON si.product_id = p.product_id
    WHERE si.line_type = 'parts'
    AND si.product_id IS NOT NULL
    AND s.sale_date >= DATE_SUB(CURDATE(), INTERVAL 12 MONTH)
    GROUP BY si.product_id, si.description, p.code
    ORDER BY total_qty DESC
    LIMIT 5
");
if ($r) {
    $rows = [];
    while($row=$r->fetch_assoc()) $rows[]=$row;
    if (count($rows)) {
        foreach($rows as $row) echo $row['description']." - qty:".$row['total_qty']."<br>";
    } else {
        echo "<span style=color:orange>Query works but NO DATA (no sales with line_type='parts' in last 12 months)</span><br>";
    }
} else {
    echo "<span style=color:red>QUERY ERROR: ".$conn->error."</span><br>";
}

// 7. Check JS file exists
echo "<h3>7. Files check</h3>";
$files = ['inventory-forecast.js','fetch_sales_for_forecast.php','inventory.php','add_stock.php'];
foreach($files as $f) {
    echo $f.": ".( file_exists(__DIR__.'/'.$f) ? "<span style=color:green>EXISTS</span>" : "<span style=color:red>MISSING - not uploaded!</span>" )."<br>";
}

echo "<hr><p style=color:gray>Delete debug_forecast.php after fixing!</p>";