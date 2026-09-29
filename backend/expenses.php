<?php
session_start();
header('Content-Type: application/json');
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/audit.php';

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Not logged in']);
    exit;
}

$action = $_POST['action'] ?? $_GET['action'] ?? '';
$userId = (int) $_SESSION['user_id'];

// GET expenses by date — available to all logged-in users
if ($action === 'by_date') {
    $date = trim($_GET['date'] ?? $_POST['date'] ?? '');
    if (!$date || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
        echo json_encode(['success' => false, 'message' => 'Invalid date.']);
        exit;
    }
    $stmt = $conn->prepare("SELECT id, category, description, amount FROM expenses WHERE expense_date = ? ORDER BY id ASC");
    $stmt->bind_param('s', $date);
    $stmt->execute();
    $items = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
    echo json_encode(['success' => true, 'items' => $items]);
    exit;
}

$isOwner = (($_SESSION['role'] ?? 'manager') === 'owner');

// SAVE expense (owner and manager)
if ($action === 'save') {
    $date = trim($_POST['expense_date'] ?? '');
    $cat = trim($_POST['category'] ?? '');
    $desc = trim($_POST['description'] ?? '');
    $amount = (float) ($_POST['amount'] ?? 0);

    if (!$date || !$cat || !$desc || $amount <= 0) {
        echo json_encode(['success' => false, 'message' => 'All fields are required and amount must be > 0.']);
        exit;
    }

    $stmt = $conn->prepare("INSERT INTO expenses (expense_date, category, description, amount, created_by) VALUES (?, ?, ?, ?, ?)");
    $stmt->bind_param('sssdi', $date, $cat, $desc, $amount, $userId);

    if ($stmt->execute()) {
        $newId = $conn->insert_id;
        logAudit($conn, $userId, 'CREATE', 'expenses', $newId, null, json_encode([
            'expense_date' => $date, 'category' => $cat, 'description' => $desc, 'amount' => $amount,
        ]));
        echo json_encode(['success' => true, 'message' => 'Expense saved.']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed: ' . $conn->error]);
    }
    $stmt->close();
    exit;
}

// DELETE expense (owner only)
if ($action === 'delete') {
    if (!$isOwner) {
        echo json_encode(['success' => false, 'message' => 'Only the owner can delete expenses.']);
        exit;
    }
    $id = (int) ($_POST['id'] ?? 0);

    if ($id <= 0) {
        echo json_encode(['success' => false, 'message' => 'Invalid ID.']);
        exit;
    }

    // Fetch old values for audit log before deletion
    $oldStmt = $conn->prepare("SELECT * FROM expenses WHERE id = ?");
    $oldStmt->bind_param('i', $id);
    $oldStmt->execute();
    $old = $oldStmt->get_result()->fetch_assoc();
    $oldStmt->close();

    $stmt = $conn->prepare("DELETE FROM expenses WHERE id = ?");
    $stmt->bind_param('i', $id);

    if ($stmt->execute() && $stmt->affected_rows > 0) {
        if ($old) {
            logAudit($conn, $userId, 'DELETE', 'expenses', $id, json_encode($old), null);
        }
        echo json_encode(['success' => true, 'message' => 'Expense deleted.']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Expense not found.']);
    }
    $stmt->close();
    exit;
}

// BULK DELETE (owner only)
if ($action === 'bulk_delete') {
    if (!$isOwner) {
        echo json_encode(['success' => false, 'message' => 'Only the owner can delete expenses.']);
        exit;
    }

    $ids = $_POST['ids'] ?? [];
    if (!is_array($ids) || empty($ids)) {
        echo json_encode(['success' => false, 'message' => 'No expenses selected.']);
        exit;
    }

    // Sanitize to positive integers only
    $ids = array_values(array_unique(array_filter(array_map('intval', $ids), fn($v) => $v > 0)));
    if (empty($ids)) {
        echo json_encode(['success' => false, 'message' => 'No valid IDs provided.']);
        exit;
    }

    $placeholders = implode(',', array_fill(0, count($ids), '?'));
    $types = str_repeat('i', count($ids));

    // Fetch old values first so each deletion is still individually audit-logged
    $oldStmt = $conn->prepare("SELECT * FROM expenses WHERE id IN ($placeholders)");
    $oldStmt->bind_param($types, ...$ids);
    $oldStmt->execute();
    $oldRows = $oldStmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $oldStmt->close();

    $stmt = $conn->prepare("DELETE FROM expenses WHERE id IN ($placeholders)");
    $stmt->bind_param($types, ...$ids);

    if ($stmt->execute()) {
        $deleted = $stmt->affected_rows;
        foreach ($oldRows as $old) {
            logAudit($conn, $userId, 'DELETE', 'expenses', (int) $old['id'], json_encode($old), null);
        }
        echo json_encode(['success' => true, 'deleted' => $deleted]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Bulk delete failed: ' . $conn->error]);
    }
    $stmt->close();
    exit;
}

echo json_encode(['success' => false, 'message' => 'Unknown action.']);