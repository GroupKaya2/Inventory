<?php
session_start();
header('Content-Type: application/json');
require_once __DIR__ . '/db.php';

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Not logged in']);
    exit;
}

$action = $_GET['action'] ?? $_POST['action'] ?? '';
$isOwner = ($_SESSION['role'] ?? 'manager') === 'owner';
$userId = (int) $_SESSION['user_id'];

/* FETCH ALL PRODUCTS */
if ($action === 'fetch') {
    $result = $conn->query("
            SELECT p.product_id, p.code, p.description, p.unit,
                p.unit_cost, p.selling_price,
                (p.selling_price - p.unit_cost) AS margin,
                ps.current_stock,
                p.reorder_threshold,
                c.category_name
            FROM products p
            LEFT JOIN categories c ON p.category_id = c.category_id
            LEFT JOIN product_stock ps ON p.product_id = ps.product_id
            ORDER BY c.category_name, p.description
        ");
    $data = [];
    if ($result)
        while ($row = $result->fetch_assoc())
            $data[] = $row;
    echo json_encode(['success' => true, 'data' => $data]);
    exit;
}

/* FETCH SINGLE PRODUCT */
if ($action === 'get') {
    $id = (int) ($_GET['id'] ?? 0);
    if ($id <= 0) {
        echo json_encode(['success' => false, 'message' => 'Invalid ID']);
        exit;
    }

    $stmt = $conn->prepare("SELECT * FROM products WHERE product_id = ?");
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

/* FETCH CATEGORIES */
if ($action === 'categories') {
    $result = $conn->query("SELECT category_id, category_name FROM categories ORDER BY category_name");
    $data = [];
    if ($result)
        while ($row = $result->fetch_assoc())
            $data[] = $row;
    echo json_encode(['success' => true, 'data' => $data]);
    exit;
}

/* ADD PRODUCT (owner only) */
if ($action === 'add') {
    if (!$isOwner) {
        echo json_encode(['success' => false, 'message' => 'Owner only']);
        exit;
    }

    $catId = (int) ($_POST['category_id'] ?? 0);
    $desc = trim($_POST['description'] ?? '');
    $unit = trim($_POST['unit'] ?? '');
    $code = trim($_POST['code'] ?? '');
    $cost = (float) ($_POST['unit_cost'] ?? 0);
    $price = (float) ($_POST['selling_price'] ?? 0);
    $qty = (int) ($_POST['initial_quantity'] ?? 0);
    $thresh = (int) ($_POST['reorder_threshold'] ?? 5);

    if (!$catId || !$desc || !$unit) {
        echo json_encode(['success' => false, 'message' => 'Required fields missing']);
        exit;
    }

    $stmt = $conn->prepare(
        "INSERT INTO products (category_id, description, unit, code, unit_cost, selling_price, initial_quantity, reorder_threshold)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?)"
    );
    $stmt->bind_param('isssddii', $catId, $desc, $unit, $code, $cost, $price, $qty, $thresh);
    if ($stmt->execute()) {
        // Record initial stock transaction
        $newId = $conn->insert_id;
        if ($qty > 0) {
            $conn->query("INSERT INTO inventory_transactions (product_id, transaction_date, quantity_change, transaction_type, remarks, created_by)
                            VALUES ($newId, CURDATE(), $qty, 'initial', 'Initial stock', $userId)");
        }
        echo json_encode(['success' => true, 'message' => 'Product added successfully']);
    } else {
        echo json_encode(['success' => false, 'message' => 'SQL Error: ' . $stmt->error]);
    }
    $stmt->close();
    exit;
}

/* UPDATE PRODUCT (owner only) */
if ($action === 'update') {
    if (!$isOwner) {
        echo json_encode(['success' => false, 'message' => 'Owner only']);
        exit;
    }

    $id = (int) ($_POST['product_id'] ?? 0);
    $catId = (int) ($_POST['category_id'] ?? 0);
    $desc = trim($_POST['description'] ?? '');
    $unit = trim($_POST['unit'] ?? '');
    $code = trim($_POST['code'] ?? '');
    $cost = (float) ($_POST['unit_cost'] ?? 0);
    $price = (float) ($_POST['selling_price'] ?? 0);
    $qty = (int) ($_POST['initial_quantity'] ?? 0);
    $thresh = (int) ($_POST['reorder_threshold'] ?? 5);

    if (!$id || !$catId || !$desc || !$unit) {
        echo json_encode(['success' => false, 'message' => 'Missing fields']);
        exit;
    }

    $stmt = $conn->prepare(
        "UPDATE products SET category_id=?, description=?, unit=?, code=?,
            unit_cost=?, selling_price=?, initial_quantity=?, reorder_threshold=?
            WHERE product_id=?"
    );
    $stmt->bind_param('isssddiii', $catId, $desc, $unit, $code, $cost, $price, $qty, $thresh, $id);
    if ($stmt->execute()) {
        echo json_encode(['success' => true, 'message' => 'Updated successfully']);
    } else {
        echo json_encode(['success' => false, 'message' => 'SQL Error: ' . $stmt->error]);
    }
    $stmt->close();
    exit;
}

/* DELETE PRODUCT (owner only) */
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

    $stmt = $conn->prepare("DELETE FROM products WHERE product_id = ?");
    $stmt->bind_param('i', $id);
    if ($stmt->execute()) {
        echo json_encode(['success' => true, 'message' => 'Deleted successfully']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Delete failed: ' . $stmt->error]);
    }
    $stmt->close();
    exit;
}

/* RESTOCK*/
if ($action === 'restock') {
    $productId = (int) ($_POST['product_id'] ?? 0);
    $qty = (int) ($_POST['quantity'] ?? 0);
    $remarks = trim($_POST['remarks'] ?? 'Restock');

    if ($productId <= 0 || $qty <= 0) {
        echo json_encode(['success' => false, 'message' => 'Invalid product or quantity.']);
        exit;
    }

    // Verify product exists
    $check = $conn->prepare("SELECT product_id FROM products WHERE product_id = ?");
    $check->bind_param('i', $productId);
    $check->execute();
    if (!$check->get_result()->num_rows) {
        echo json_encode(['success' => false, 'message' => 'Product not found.']);
        exit;
    }
    $check->close();

    $today = date('Y-m-d');
    $stmt = $conn->prepare(
        "INSERT INTO inventory_transactions (product_id, transaction_date, quantity_change, transaction_type, remarks, created_by)
            VALUES (?, ?, ?, 'restock', ?, ?)"
    );
    $stmt->bind_param('iissi', $productId, $today, $qty, $remarks, $userId);
    if ($stmt->execute()) {
        // Return new stock level
        $st = $conn->prepare("SELECT current_stock FROM product_stock WHERE product_id = ?");
        $st->bind_param('i', $productId);
        $st->execute();
        $newStock = (int) ($st->get_result()->fetch_assoc()['current_stock'] ?? 0);
        $st->close();

        echo json_encode(['success' => true, 'message' => 'Restocked successfully.', 'new_stock' => $newStock]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Restock failed: ' . $stmt->error]);
    }
    $stmt->close();
    exit;
}

/* REORDER LIST */
if ($action === 'reorder-list') {
    $result = $conn->query("
            SELECT p.product_id, p.code, p.description,
                c.category_name,
                ps.current_stock,
                p.reorder_threshold,
                p.unit
            FROM products p
            LEFT JOIN categories c  ON p.category_id = c.category_id
            LEFT JOIN product_stock ps ON p.product_id = ps.product_id
            WHERE ps.current_stock <= p.reorder_threshold
            ORDER BY ps.current_stock ASC
        ");
    $items = [];
    if ($result)
        while ($row = $result->fetch_assoc())
            $items[] = $row;
    echo json_encode(['success' => true, 'items' => $items]);
    exit;
}

echo json_encode(['success' => false, 'message' => 'Unknown action.']);