<?php
session_start();
require_once __DIR__ . '/db.php';

if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? 'manager') !== 'owner') {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

header('Content-Type: application/json');

$action = $_GET['action'] ?? '';

// Load backup directory from .env
$envFile = __DIR__ . '/../.env';
$backupDir = __DIR__ . '/../backups';
if (file_exists($envFile)) {
    $env = parse_ini_file($envFile, false, INI_SCANNER_RAW);
    $backupDir = __DIR__ . '/../' . ($env['BACKUP_DIR'] ?? 'backups');
}

// Create backup directory if it doesn't exist
if (!is_dir($backupDir)) {
    mkdir($backupDir, 0755, true);
}

if ($action === 'create') {
    // Create database backup
    $timestamp = date('Y-m-d_H-i-s');
    $backupFile = $backupDir . '/backup_' . $timestamp . '.sql';
    
    // Get database name from environment
    $dbName = defined('DB_NAME') ? DB_NAME : 'login_system';
    
    // Use mysqldump to create backup
    $command = sprintf(
        'mysqldump --user=%s --password=%s --host=%s %s > %s 2>&1',
        escapeshellarg(defined('DB_USER') ? DB_USER : 'root'),
        escapeshellarg(defined('DB_PASS') ? DB_PASS : ''),
        escapeshellarg(defined('DB_HOST') ? DB_HOST : 'localhost'),
        escapeshellarg($dbName),
        escapeshellarg($backupFile)
    );
    
    $output = [];
    $returnCode = 0;
    exec($command, $output, $returnCode);
    
    if ($returnCode === 0 && file_exists($backupFile)) {
        // Compress the backup file
        $zipFile = $backupFile . '.gz';
        $fp = fopen($backupFile, 'rb');
        $gz = gzopen($zipFile, 'wb9');
        while (!feof($fp)) {
            gzwrite($gz, fread($fp, 8192));
        }
        fclose($fp);
        gzclose($gz);
        
        // Delete the uncompressed file
        unlink($backupFile);
        
        // Clean up old backups
        $retentionDays = $env['BACKUP_RETENTION_DAYS'] ?? 30;
        $cutoffTime = time() - ($retentionDays * 86400);
        foreach (glob($backupDir . '/*.sql.gz') as $file) {
            if (filemtime($file) < $cutoffTime) {
                unlink($file);
            }
        }
        
        echo json_encode([
            'success' => true,
            'message' => 'Backup created successfully',
            'file' => basename($zipFile),
            'size' => filesize($zipFile)
        ]);
    } else {
        echo json_encode([
            'success' => false,
            'message' => 'Failed to create backup: ' . implode("\n", $output)
        ]);
    }
    
} elseif ($action === 'list') {
    // List all backups
    $backups = [];
    foreach (glob($backupDir . '/*.sql.gz') as $file) {
        $backups[] = [
            'name' => basename($file),
            'size' => filesize($file),
            'date' => date('Y-m-d H:i:s', filemtime($file))
        ];
    }
    
    // Sort by date (newest first)
    usort($backups, function($a, $b) {
        return strtotime($b['date']) - strtotime($a['date']);
    });
    
    echo json_encode([
        'success' => true,
        'backups' => $backups
    ]);
    
} elseif ($action === 'restore') {
    // Restore from backup
    $backupFile = $_POST['file'] ?? '';
    $backupPath = $backupDir . '/' . $backupFile;
    
    if (!file_exists($backupPath)) {
        echo json_encode(['success' => false, 'message' => 'Backup file not found']);
        exit;
    }
    
    // Decompress and restore
    $tempFile = $backupDir . '/temp_restore.sql';
    $gz = gzopen($backupPath, 'rb');
    $fp = fopen($tempFile, 'wb');
    while (!gzeof($gz)) {
        fwrite($fp, gzread($gz, 8192));
    }
    gzclose($gz);
    fclose($fp);
    
    // Restore using mysql command
    $dbName = defined('DB_NAME') ? DB_NAME : 'login_system';
    $command = sprintf(
        'mysql --user=%s --password=%s --host=%s %s < %s 2>&1',
        escapeshellarg(defined('DB_USER') ? DB_USER : 'root'),
        escapeshellarg(defined('DB_PASS') ? DB_PASS : ''),
        escapeshellarg(defined('DB_HOST') ? DB_HOST : 'localhost'),
        escapeshellarg($dbName),
        escapeshellarg($tempFile)
    );
    
    $output = [];
    $returnCode = 0;
    exec($command, $output, $returnCode);
    
    // Clean up temp file
    unlink($tempFile);
    
    if ($returnCode === 0) {
        echo json_encode(['success' => true, 'message' => 'Backup restored successfully']);
    } else {
        echo json_encode([
            'success' => false,
            'message' => 'Failed to restore backup: ' . implode("\n", $output)
        ]);
    }
    
} elseif ($action === 'delete') {
    // Delete a backup
    $backupFile = $_POST['file'] ?? '';
    $backupPath = $backupDir . '/' . $backupFile;
    
    if (!file_exists($backupPath)) {
        echo json_encode(['success' => false, 'message' => 'Backup file not found']);
        exit;
    }
    
    if (unlink($backupPath)) {
        echo json_encode(['success' => true, 'message' => 'Backup deleted successfully']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to delete backup']);
    }
    
} elseif ($action === 'download') {
    // Download a backup
    $backupFile = $_GET['file'] ?? '';
    $backupPath = $backupDir . '/' . $backupFile;
    
    if (!file_exists($backupPath)) {
        echo json_encode(['success' => false, 'message' => 'Backup file not found']);
        exit;
    }
    
    header('Content-Type: application/gzip');
    header('Content-Disposition: attachment; filename="' . $backupFile . '"');
    header('Content-Length: ' . filesize($backupPath));
    readfile($backupPath);
    exit;
    
} else {
    echo json_encode(['success' => false, 'message' => 'Invalid action']);
}

$conn->close();
