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

$isOwner = ($_SESSION['role'] ?? 'manager') === 'owner';
$userId  = (int) $_SESSION['user_id'];
$action  = $_GET['action'] ?? $_POST['action'] ?? '';

try {

    $conn->query("CREATE TABLE IF NOT EXISTS services (
        service_id   INT AUTO_INCREMENT PRIMARY KEY,
        service_name VARCHAR(150) NOT NULL UNIQUE,
        price        DECIMAL(10,2) NOT NULL DEFAULT 0,
        created_at   TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at   TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    // Fetch — available to any logged-in user (needed for the Labor dropdown)
    if ($action === 'fetch') {
        $rows = [];
        $r = $conn->query("SELECT service_id, service_name, price FROM services ORDER BY service_name ASC");
        if ($r) {
            while ($row = $r->fetch_assoc()) {
                $rows[] = $row;
            }
        }
        echo json_encode(['success' => true, 'data' => $rows]);
        exit;
    }

    // Everything below this line is Owner-only
    if (!$isOwner) {
        echo json_encode(['success' => false, 'message' => 'Owner only.']);
        exit;
    }

    if ($action === 'add') {
        $name  = trim($_POST['service_name'] ?? '');
        $price = (float) ($_POST['price'] ?? 0);

        if ($name === '') {
            echo json_encode(['success' => false, 'message' => 'Service name is required.']);
            exit;
        }
        if ($price < 0) {
            echo json_encode(['success' => false, 'message' => 'Price cannot be negative.']);
            exit;
        }

        $check = $conn->prepare("SELECT service_id FROM services WHERE service_name = ?");
        $check->bind_param('s', $name);
        $check->execute();
        if ($check->get_result()->num_rows > 0) {
            $check->close();
            echo json_encode(['success' => false, 'message' => 'A service with that name already exists.']);
            exit;
        }
        $check->close();

        $stmt = $conn->prepare("INSERT INTO services (service_name, price) VALUES (?, ?)");
        $stmt->bind_param('sd', $name, $price);

        if ($stmt->execute()) {
            $newId = $conn->insert_id;
            logAudit($conn, $userId, 'CREATE', 'services', $newId, null, json_encode(['service_name' => $name, 'price' => $price]));
            echo json_encode(['success' => true, 'message' => 'Service added.']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Failed to add service.']);
        }
        $stmt->close();
        exit;
    }

    if ($action === 'update') {
        $id    = (int) ($_POST['service_id'] ?? 0);
        $name  = trim($_POST['service_name'] ?? '');
        $price = (float) ($_POST['price'] ?? 0);

        if ($id <= 0 || $name === '') {
            echo json_encode(['success' => false, 'message' => 'Invalid input.']);
            exit;
        }
        if ($price < 0) {
            echo json_encode(['success' => false, 'message' => 'Price cannot be negative.']);
            exit;
        }

        $check = $conn->prepare("SELECT service_id FROM services WHERE service_name = ? AND service_id != ?");
        $check->bind_param('si', $name, $id);
        $check->execute();
        if ($check->get_result()->num_rows > 0) {
            $check->close();
            echo json_encode(['success' => false, 'message' => 'Another service already uses that name.']);
            exit;
        }
        $check->close();

        $oldStmt = $conn->prepare("SELECT * FROM services WHERE service_id = ?");
        $oldStmt->bind_param('i', $id);
        $oldStmt->execute();
        $old = $oldStmt->get_result()->fetch_assoc();
        $oldStmt->close();

        $stmt = $conn->prepare("UPDATE services SET service_name = ?, price = ? WHERE service_id = ?");
        $stmt->bind_param('sdi', $name, $price, $id);

        if ($stmt->execute()) {
            logAudit($conn, $userId, 'UPDATE', 'services', $id, json_encode($old), json_encode(['service_name' => $name, 'price' => $price]));
            echo json_encode(['success' => true, 'message' => 'Service updated.']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Failed to update service.']);
        }
        $stmt->close();
        exit;
    }

    if ($action === 'delete') {
        $id = (int) ($_POST['service_id'] ?? 0);
        if ($id <= 0) {
            echo json_encode(['success' => false, 'message' => 'Invalid service ID.']);
            exit;
        }

        $oldStmt = $conn->prepare("SELECT * FROM services WHERE service_id = ?");
        $oldStmt->bind_param('i', $id);
        $oldStmt->execute();
        $old = $oldStmt->get_result()->fetch_assoc();
        $oldStmt->close();

        $stmt = $conn->prepare("DELETE FROM services WHERE service_id = ?");
        $stmt->bind_param('i', $id);

        if ($stmt->execute()) {
            logAudit($conn, $userId, 'DELETE', 'services', $id, json_encode($old), null);
            echo json_encode(['success' => true, 'message' => 'Service deleted.']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Failed to delete service.']);
        }
        $stmt->close();
        exit;
    }

    echo json_encode(['success' => false, 'message' => 'Unknown action.']);
    $conn->close();

} catch (\Throwable $e) {
    while (ob_get_level() > 0) { ob_end_clean(); }
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Server error: ' . $e->getMessage()]);
    exit;
    
}