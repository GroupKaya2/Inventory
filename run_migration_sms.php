<?php
/**
 * Run SMS migration script
 * Creates SMS history and settings tables
 */

include __DIR__ . '/db.php';

echo "Running SMS migration...\n";

// Read migration file
$migrationFile = __DIR__ . '/migration_sms.sql';
if (!file_exists($migrationFile)) {
    die("Error: migration_sms.sql not found!\n");
}

$sql = file_get_contents($migrationFile);

// Split by semicolon and execute each statement
$statements = array_filter(
    array_map('trim', explode(';', $sql)),
    function($stmt) {
        return !empty($stmt) && !preg_match('/^--/', $stmt);
    }
);

$successCount = 0;
$errorCount = 0;

foreach ($statements as $statement) {
    if (empty(trim($statement))) continue;
    
    try {
        if ($conn->query($statement)) {
            $successCount++;
            echo "✓ Executed: " . substr($statement, 0, 50) . "...\n";
        } else {
            $errorCount++;
            echo "✗ Error: " . $conn->error . "\n";
            echo "  Statement: " . substr($statement, 0, 100) . "...\n";
        }
    } catch (Exception $e) {
        $errorCount++;
        echo "✗ Exception: " . $e->getMessage() . "\n";
    }
}

echo "\nMigration completed!\n";
echo "Success: $successCount statements\n";
echo "Errors: $errorCount statements\n";

$conn->close();
