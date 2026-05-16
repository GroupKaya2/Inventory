<?php
session_start();
header('Content-Type: application/json');
require_once __DIR__ . '/db.php';

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Not logged in']);
    exit;
}


// GET expenses by date
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

if (($_SESSION['role'] ?? 'manager') !== 'owner') {
    echo json_encode(['success' => false, 'message' => 'Owner only.']);
    exit;
}

$action = $_POST['action'] ?? $_GET['action'] ?? '';
$userId = (int) $_SESSION['user_id'];

// SAVE expense
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
        echo json_encode(['success' => true, 'message' => 'Expense saved.']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed: ' . $conn->error]);
    }
    $stmt->close();
    exit;
}

// DELETE expense
if ($action === 'delete') {
    $id = (int) ($_POST['id'] ?? 0);

    if ($id <= 0) {
        echo json_encode(['success' => false, 'message' => 'Invalid ID.']);
        exit;
    }

    $stmt = $conn->prepare("DELETE FROM expenses WHERE id = ?");
    $stmt->bind_param('i', $id);

    if ($stmt->execute() && $stmt->affected_rows > 0) {
        echo json_encode(['success' => true, 'message' => 'Expense deleted.']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Expense not found.']);
    }
    $stmt->close();
    exit;
}

echo json_encode(['success' => false, 'message' => 'Unknown action.']);

