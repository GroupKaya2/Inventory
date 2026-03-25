<?php
session_start();
header('Content-Type: application/json');
require_once __DIR__ . '/db.php';

// Logged in check
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Not logged in']);
    exit;
}

$action  = $_GET['action'] ?? $_POST['action'] ?? '';
$isOwner = ($_SESSION['role'] ?? 'manager') === 'owner';

// FETCH all products
if ($action === 'fetch') {
    $result = $conn->query("
        SELECT product_id, code, description, unit, unit_cost, selling_price, margin,
            initial_quantity, reorder_threshold, current_stock, category_id, category_name
        FROM product_stock
        ORDER BY category_name, description
    ");

    $data = [];
    if ($result) {
        while ($row = $result->fetch_assoc())
            $data[] = $row;
    }

    echo json_encode(['success' => true, 'data' => $data]);
    exit;
}

// FETCH single product
if ($action === 'get') {
    $id = (int) ($_GET['id'] ?? 0);

    if ($id <= 0) {
        echo json_encode(['success' => false, 'message' => 'Invalid ID']);
        exit;
    }

    $stmt = $conn->prepare("SELECT * FROM product_stock WHERE product_id = ?");
    $stmt->bind_param('i', $id);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$row) {
        echo json_encode(['success' => false, 'message' => 'Product not found']);
        exit;
    }

    echo json_encode(['success' => true, 'data' => $row]);
    exit;
}

// FETCH categories
if ($action === 'categories') {
    $result = $conn->query("SELECT category_id, category_name FROM categories ORDER BY category_name");

    $data = [];
    if ($result)
        while ($row = $result->fetch_assoc())
            $data[] = $row;

    echo json_encode(['success' => true, 'data' => $data]);
    exit;
}

// ADD product (owner only)
if ($action === 'add') {

    if (!$isOwner) {
        echo json_encode(['success' => false, 'message' => 'Owner only']);
        exit;
    }

    $catId  = (int)    ($_POST['category_id']       ?? 0);
    $desc   = trim(     $_POST['description']        ?? '');
    $unit   = trim(     $_POST['unit']               ?? '');
    $code   = trim(     $_POST['code']               ?? '');
    $cost   = (float)  ($_POST['unit_cost']          ?? 0);
    $price  = (float)  ($_POST['selling_price']      ?? 0);
    $qty    = (int)    ($_POST['initial_quantity']   ?? 0);
    $thresh = (int)    ($_POST['reorder_threshold']  ?? 5);

    if (!$catId || !$desc || !$unit) {
        echo json_encode(['success' => false, 'message' => 'Category, description and unit are required.']);
        exit;
    }

    $stmt = $conn->prepare("
        INSERT INTO products
        (category_id, description, unit, code, unit_cost, selling_price, initial_quantity, reorder_threshold)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?)
    ");

    $stmt->bind_param('isssddii', $catId, $desc, $unit, $code, $cost, $price, $qty, $thresh);

    if ($stmt->execute()) {
        echo json_encode(['success' => true, 'message' => 'Product added successfully.']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed: ' . $conn->error]);
    }

    $stmt->close();
    exit;
}

// UPDATE product (owner only)
if ($action === 'update') {

    if (!$isOwner) {
        echo json_encode(['success' => false, 'message' => 'Owner only']);
        exit;
    }

    $id     = (int)    ($_POST['product_id']         ?? 0);
    $catId  = (int)    ($_POST['category_id']        ?? 0);
    $desc   = trim(     $_POST['description']        ?? '');
    $unit   = trim(     $_POST['unit']               ?? '');
    $code   = trim(     $_POST['code']               ?? '');
    $cost   = (float)  ($_POST['unit_cost']          ?? 0);
    $price  = (float)  ($_POST['selling_price']      ?? 0);
    $qty    = (int)    ($_POST['initial_quantity']   ?? 0);
    $thresh = (int)    ($_POST['reorder_threshold']  ?? 5);

    if (!$id || !$catId || !$desc || !$unit) {
        echo json_encode(['success' => false, 'message' => 'Missing required fields.']);
        exit;
    }

    $stmt = $conn->prepare("
        UPDATE products
        SET category_id=?, description=?, unit=?, code=?,
            unit_cost=?, selling_price=?, initial_quantity=?, reorder_threshold=?
        WHERE product_id=?
    ");

    $stmt->bind_param('isssddiii', $catId, $desc, $unit, $code, $cost, $price, $qty, $thresh, $id);

    if ($stmt->execute()) {
        echo json_encode(['success' => true, 'message' => 'Product updated.']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Update failed: ' . $conn->error]);
    }

    $stmt->close();
    exit;
}

// DELETE product (owner only)
if ($action === 'delete') {

    if (!$isOwner) {
        echo json_encode(['success' => false, 'message' => 'Owner only']);
        exit;
    }

    $id = (int) ($_POST['id'] ?? 0);

    if ($id <= 0) {
        echo json_encode(['success' => false, 'message' => 'Invalid ID']);
        exit;
    }

    $check = $conn->prepare("SELECT COUNT(*) AS c FROM sale_items WHERE product_id = ?");
    $check->bind_param('i', $id);
    $check->execute();
    $count = (int) $check->get_result()->fetch_assoc()['c'];
    $check->close();

    if ($count > 0) {
        echo json_encode(['success' => false, 'message' => 'Cannot delete: product has sales records.']);
        exit;
    }

    $stmt = $conn->prepare("DELETE FROM products WHERE product_id = ?");
    $stmt->bind_param('i', $id);

    if ($stmt->execute() && $stmt->affected_rows > 0) {
        echo json_encode(['success' => true, 'message' => 'Deleted successfully.']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Product not found.']);
    }

    $stmt->close();
    exit;
}

// RESTOCK
if ($action === 'restock') {

    $productId = (int) ($_POST['product_id'] ?? 0);
    $quantity  = (int) ($_POST['quantity']   ?? 0);
    $remarks   = trim( $_POST['remarks']     ?? 'Restock');
    $userId    = (int) $_SESSION['user_id'];

    if ($productId <= 0 || $quantity <= 0) {
        echo json_encode(['success' => false, 'message' => 'Invalid input.']);
        exit;
    }

    $stmt = $conn->prepare("
        INSERT INTO inventory_transactions
        (product_id, transaction_date, quantity_change, transaction_type, remarks, created_by)
        VALUES (?, CURDATE(), ?, 'restock', ?, ?)
    ");

    $stmt->bind_param('iisi', $productId, $quantity, $remarks, $userId);

    if ($stmt->execute()) {
        echo json_encode(['success' => true, 'message' => 'Restocked successfully.']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed: ' . $conn->error]);
    }

    $stmt->close();
    exit;
}

// REORDER LIST
if ($action === 'reorder-list') {
    $items = [];

    $result = $conn->query("
        SELECT product_id, code, description, category_name,
            current_stock, reorder_threshold, unit
        FROM product_stock
        WHERE current_stock <= reorder_threshold
        ORDER BY current_stock ASC
    ");

    if ($result)
        while ($row = $result->fetch_assoc())
            $items[] = $row;

    echo json_encode(['success' => true, 'items' => $items]);
    exit;
}

echo json_encode(['success' => false, 'message' => 'Unknown action.']);
