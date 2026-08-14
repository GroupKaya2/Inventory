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

$envFile   = __DIR__ . '/../.env';
$backupDir = __DIR__ . '/../backups';
$env       = [];
if (file_exists($envFile)) {
    $env       = parse_ini_file($envFile, false, INI_SCANNER_RAW);
    $backupDir = __DIR__ . '/../' . ($env['BACKUP_DIR'] ?? 'backups');
}

if (!is_dir($backupDir)) {
    mkdir($backupDir, 0755, true);
}

$dbName = defined('DB_NAME') ? DB_NAME : 'login_system';
$dbUser = defined('DB_USER') ? DB_USER : 'root';
$dbPass = defined('DB_PASS') ? DB_PASS : '';
$dbHost = defined('DB_HOST') ? DB_HOST : 'localhost';

$mysqldump = 'C:/xampp/mysql/bin/mysqldump.exe';
$mysql     = 'C:/xampp/mysql/bin/mysql.exe';
if (!file_exists($mysqldump)) $mysqldump = 'mysqldump';
if (!file_exists($mysql))     $mysql     = 'mysql';

if ($action === 'create') {

    $timestamp  = date('Y-m-d_H-i-s');
    $backupFile = $backupDir . '/backup_' . $timestamp . '.sql';
    $passArg    = $dbPass !== '' ? '--password=' . escapeshellarg($dbPass) : '--password=';

    $command = sprintf(
        '"%s" --user=%s %s --host=%s %s > "%s" 2>&1',
        $mysqldump,
        escapeshellarg($dbUser),
        $passArg,
        escapeshellarg($dbHost),
        escapeshellarg($dbName),
        $backupFile
    );

    $output     = [];
    $returnCode = 0;
    exec($command, $output, $returnCode);

    if ($returnCode === 0 && file_exists($backupFile) && filesize($backupFile) > 100) {
        $zipFile = $backupFile . '.gz';
        $fp = fopen($backupFile, 'rb');
        $gz = gzopen($zipFile, 'wb9');
        while (!feof($fp)) {
            gzwrite($gz, fread($fp, 8192));
        }
        fclose($fp);
        gzclose($gz);
        unlink($backupFile);

        $retentionDays = (int)($env['BACKUP_RETENTION_DAYS'] ?? 30);
        $cutoffTime    = time() - ($retentionDays * 86400);
        foreach (glob($backupDir . '/*.sql.gz') as $file) {
            if (filemtime($file) < $cutoffTime) {
                unlink($file);
            }
        }

        echo json_encode([
            'success' => true,
            'message' => 'Backup created successfully',
            'file'    => basename($zipFile),
            'size'    => filesize($zipFile),
        ]);
    } else {
        $errMsg = implode(' | ', $output);
        echo json_encode(['success' => false, 'message' => 'Failed to create backup: ' . $errMsg]);
    }

} elseif ($action === 'list') {

    $backups = [];
    foreach (glob($backupDir . '/*.sql.gz') as $file) {
        $backups[] = [
            'name' => basename($file),
            'size' => filesize($file),
            'date' => date('Y-m-d H:i:s', filemtime($file)),
        ];
    }
    usort($backups, function($a, $b) {
        return strtotime($b['date']) - strtotime($a['date']);
    });
    echo json_encode(['success' => true, 'backups' => $backups]);

} elseif ($action === 'restore') {

    $backupFile = basename($_POST['file'] ?? '');
    $backupPath = $backupDir . '/' . $backupFile;

    if (!$backupFile || !file_exists($backupPath)) {
        echo json_encode(['success' => false, 'message' => 'Backup file not found']);
        exit;
    }

    $tempFile = $backupDir . '/temp_restore.sql';
    $gz = gzopen($backupPath, 'rb');
    $fp = fopen($tempFile, 'wb');
    while (!gzeof($gz)) {
        fwrite($fp, gzread($gz, 8192));
    }
    gzclose($gz);
    fclose($fp);

    $passArg = $dbPass !== '' ? '--password=' . escapeshellarg($dbPass) : '--password=';
    $command = sprintf(
        '"%s" --user=%s %s --host=%s %s < "%s" 2>&1',
        $mysql,
        escapeshellarg($dbUser),
        $passArg,
        escapeshellarg($dbHost),
        escapeshellarg($dbName),
        $tempFile
    );

    $output     = [];
    $returnCode = 0;
    exec($command, $output, $returnCode);
    @unlink($tempFile);

    if ($returnCode === 0) {
        echo json_encode(['success' => true, 'message' => 'Backup restored successfully']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to restore: ' . implode(' | ', $output)]);
    }

} elseif ($action === 'delete') {

    $backupFile = basename($_POST['file'] ?? '');
    $backupPath = $backupDir . '/' . $backupFile;

    if (!$backupFile || !file_exists($backupPath)) {
        echo json_encode(['success' => false, 'message' => 'Backup file not found']);
        exit;
    }

    if (unlink($backupPath)) {
        echo json_encode(['success' => true, 'message' => 'Backup deleted successfully']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to delete backup']);
    }

} elseif ($action === 'download') {

    $backupFile = basename($_GET['file'] ?? '');
    $backupPath = $backupDir . '/' . $backupFile;

    if (!$backupFile || !file_exists($backupPath)) {
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