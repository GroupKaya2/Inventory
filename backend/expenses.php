<?php
// backend/expenses.php — Expenses API (Save, Delete, Fetch)

session_start();
header('Content-Type: application/json');
require_once __DIR__ . '/db.php';

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Not logged in']);
    exit;
}

$isOwner = ($_SESSION['role'] ?? 'manager') === 'owner';
$userId  = (int)$_SESSION['user_id'];
$action  = $_GET['action'] ?? $_POST['action'] ?? '';

// Auto-create expenses table
$conn->query("CREATE TABLE IF NOT EXISTS expenses (
    id INT AUTO_INCREMENT PRIMARY KEY,
    expense_date DATE NOT NULL,
    category VARCHAR(100) NOT NULL,
    description VARCHAR(255) NOT NULL,
    amount DECIMAL(12,2) NOT NULL DEFAULT 0,
    created_by INT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

// ── SAVE EXPENSE ────────────────────────────────────────
if ($action === 'save') {
    if (!$isOwner) {
        echo json_encode(['success' => false, 'message' => 'Owner only.']);
        exit;
    }

    $date   = trim($_POST['expense_date'] ?? '');
    $cat    = trim($_POST['category']     ?? '');
    $desc   = trim($_POST['description']  ?? '');
    $amount = (float)($_POST['amount']    ?? 0);

    if (!$date || !$cat || !$desc || $amount <= 0) {
        echo json_encode(['success' => false, 'message' => 'All fields are required and amount must be > 0.']);
        exit;
    }

    $stmt = $conn->prepare("INSERT INTO expenses (expense_date, category, description, amount, created_by) VALUES (?,?,?,?,?)");
    $stmt->bind_param('sssdi', $date, $cat, $desc, $amount, $userId);

    if ($stmt->execute()) {
        echo json_encode(['success' => true, 'message' => 'Saved successfully.']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed: ' . $conn->error]);
    }
    $stmt->close();
    exit;
}

// ── DELETE EXPENSE ──────────────────────────────────────
if ($action === 'delete') {
    if (!$isOwner) {
        echo json_encode(['success' => false, 'message' => 'Owner only.']);
        exit;
    }

    $id = (int)($_POST['id'] ?? 0);
    if ($id <= 0) {
        echo json_encode(['success' => false, 'message' => 'Invalid ID.']);
        exit;
    }

    $stmt = $conn->prepare("DELETE FROM expenses WHERE id = ?");
    $stmt->bind_param('i', $id);

    if ($stmt->execute() && $stmt->affected_rows > 0) {
        echo json_encode(['success' => true, 'message' => 'Deleted.']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Expense not found.']);
    }
    $stmt->close();
    exit;
}

echo json_encode(['success' => false, 'message' => 'Unknown action.']);
