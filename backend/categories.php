<?php
session_start();
header('Content-Type: application/json');
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/audit.php';

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$isOwner = ($_SESSION['role'] ?? 'manager') === 'owner';
$action = $_GET['action'] ?? $_POST['action'] ?? '';

// Fetch all categories
if ($action === 'fetch') {
    $r = $conn->query("SELECT * FROM categories ORDER BY category_name ASC");
    $categories = [];
    if ($r) {
        while ($row = $r->fetch_assoc()) {
            $categories[] = $row;
        }
    }
    echo json_encode(['success' => true, 'data' => $categories]);
    exit;
}

// Add new category (owner only)
if ($action === 'add') {
    if (!$isOwner) {
        echo json_encode(['success' => false, 'message' => 'Owner only']);
        exit;
    }

    $name = trim($_POST['category_name'] ?? '');
    if (empty($name)) {
        echo json_encode(['success' => false, 'message' => 'Category name is required']);
        exit;
    }

    // Check if category already exists
    $check = $conn->prepare("SELECT category_id FROM categories WHERE category_name = ?");
    $check->bind_param('s', $name);
    $check->execute();
    if ($check->get_result()->num_rows > 0) {
        echo json_encode(['success' => false, 'message' => 'Category already exists']);
        exit;
    }
    $check->close();

    $stmt = $conn->prepare("INSERT INTO categories (category_name) VALUES (?)");
    $stmt->bind_param('s', $name);
    
    if ($stmt->execute()) {
        $newId = $conn->insert_id;
        // Log audit entry
        $newValues = json_encode(['category_name' => $name]);
        logAudit($conn, $_SESSION['user_id'], 'INSERT', 'categories', $newId, null, $newValues);
        echo json_encode(['success' => true, 'message' => 'Category added successfully']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to add category']);
    }
    $stmt->close();
    exit;
}

// Update category (owner only)
if ($action === 'update') {
    if (!$isOwner) {
        echo json_encode(['success' => false, 'message' => 'Owner only']);
        exit;
    }

    $id = (int) ($_POST['category_id'] ?? 0);
    $name = trim($_POST['category_name'] ?? '');

    if ($id <= 0 || empty($name)) {
        echo json_encode(['success' => false, 'message' => 'Invalid input']);
        exit;
    }

    // Check if category name already exists (excluding current category)
    $check = $conn->prepare("SELECT category_id FROM categories WHERE category_name = ? AND category_id != ?");
    $check->bind_param('si', $name, $id);
    $check->execute();
    if ($check->get_result()->num_rows > 0) {
        echo json_encode(['success' => false, 'message' => 'Category name already exists']);
        exit;
    }
    $check->close();

    // Fetch old values for audit log
    $oldStmt = $conn->prepare("SELECT * FROM categories WHERE category_id = ?");
    $oldStmt->bind_param('i', $id);
    $oldStmt->execute();
    $oldResult = $oldStmt->get_result()->fetch_assoc();
    $oldStmt->close();

    $stmt = $conn->prepare("UPDATE categories SET category_name = ? WHERE category_id = ?");
    $stmt->bind_param('si', $name, $id);
    
    if ($stmt->execute()) {
        // Log audit entry
        $oldValues = json_encode($oldResult);
        $newValues = json_encode(['category_name' => $name]);
        logAudit($conn, $_SESSION['user_id'], 'UPDATE', 'categories', $id, $oldValues, $newValues);
        echo json_encode(['success' => true, 'message' => 'Category updated successfully']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to update category']);
    }
    $stmt->close();
    exit;
}

// Delete category (owner only)
if ($action === 'delete') {
    if (!$isOwner) {
        echo json_encode(['success' => false, 'message' => 'Owner only']);
        exit;
    }

    $id = (int) ($_POST['category_id'] ?? 0);
    if ($id <= 0) {
        echo json_encode(['success' => false, 'message' => 'Invalid category ID']);
        exit;
    }

    // Check if category is being used by products
    $check = $conn->prepare("SELECT COUNT(*) as count FROM products WHERE category_id = ?");
    $check->bind_param('i', $id);
    $check->execute();
    $result = $check->get_result()->fetch_assoc();
    if ($result['count'] > 0) {
        echo json_encode(['success' => false, 'message' => 'Cannot delete category that is in use by products']);
        exit;
    }
    $check->close();

    // Fetch old values for audit log before deletion
    $oldStmt = $conn->prepare("SELECT * FROM categories WHERE category_id = ?");
    $oldStmt->bind_param('i', $id);
    $oldStmt->execute();
    $oldResult = $oldStmt->get_result()->fetch_assoc();
    $oldStmt->close();

    $stmt = $conn->prepare("DELETE FROM categories WHERE category_id = ?");
    $stmt->bind_param('i', $id);
    
    if ($stmt->execute()) {
        // Log audit entry
        $oldValues = json_encode($oldResult);
        logAudit($conn, $_SESSION['user_id'], 'DELETE', 'categories', $id, $oldValues, null);
        echo json_encode(['success' => true, 'message' => 'Category deleted successfully']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to delete category']);
    }
    $stmt->close();
    exit;
}

echo json_encode(['success' => false, 'message' => 'Unknown action']);
$conn->close();
