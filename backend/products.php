<?php
session_start();
error_reporting(0);
ini_set('display_errors', 0);
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/audit.php';
header('Content-Type: application/json');

function json_out($data) {
    echo json_encode($data);
    exit;
}

if (!isset($_SESSION['user_id'])) {
    json_out(['success' => false, 'message' => 'Not logged in']);
    exit;
}

$action = $_GET['action'] ?? $_POST['action'] ?? '';
$isOwner = ($_SESSION['role'] ?? 'manager') === 'owner';
$userId = (int) $_SESSION['user_id'];

// inventory_transactions table auto-created by db.php

// product_stock view auto-created by db.php
function getCurrentStock($conn, $productId)
{
    // Try product_stock view first
    $r = $conn->query("SELECT current_stock FROM product_stock WHERE product_id = " . (int) $productId);
    if ($r && $row = $r->fetch_assoc()) {
        return (int) $row['current_stock'];
    }
    // Fallback: calculate from initial_quantity + transactions
    $r2 = $conn->query("
            SELECT
                COALESCE(p.initial_quantity, 0) + COALESCE(SUM(t.quantity_change), 0) AS stock
            FROM products p
            LEFT JOIN inventory_transactions t ON t.product_id = p.product_id
            WHERE p.product_id = " . (int) $productId . "
            GROUP BY p.product_id
        ");
    if ($r2 && $row2 = $r2->fetch_assoc()) {
        return (int) $row2['stock'];
    }
    return 0;
}

/* ══════════════════════
FETCH ALL PRODUCTS
══════════════════════ */
if ($action === 'fetch') {
    $result = $conn->query("
            SELECT p.product_id, p.code, p.description, p.unit,
                p.unit_cost, p.selling_price,
                (p.selling_price - p.unit_cost) AS margin,
                COALESCE(ps.current_stock,
                    p.initial_quantity + COALESCE((
                        SELECT SUM(t.quantity_change)
                        FROM inventory_transactions t
                        WHERE t.product_id = p.product_id
                    ), 0)
                ) AS current_stock,
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
    json_out(['success' => true, 'data' => $data]);
    exit;
}

/* ══════════════════════
FETCH SINGLE PRODUCT
══════════════════════ */
if ($action === 'get') {
    $id = (int) ($_GET['id'] ?? 0);
    if ($id <= 0) {
        json_out(['success' => false, 'message' => 'Invalid ID']);
        exit;
    }

    $stmt = $conn->prepare("SELECT * FROM products WHERE product_id = ?");
    $stmt->bind_param('i', $id);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$row) {
        json_out(['success' => false, 'message' => 'Product not found']);
        exit;
    }
    json_out(['success' => true, 'data' => $row]);
    exit;
}

/* ══════════════════════
FETCH CATEGORIES
══════════════════════ */
if ($action === 'categories') {
    $result = $conn->query("SELECT category_id, category_name FROM categories ORDER BY category_name");
    $data = [];
    if ($result)
        while ($row = $result->fetch_assoc())
            $data[] = $row;
    json_out(['success' => true, 'data' => $data]);
    exit;
}

/* ══════════════════════
ADD PRODUCT (owner)
══════════════════════ */
if ($action === 'add') {
    if (!$isOwner) {
        json_out(['success' => false, 'message' => 'Owner only']);
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
        json_out(['success' => false, 'message' => 'Required fields missing']);
        exit;
    }

    $stmt = $conn->prepare(
        "INSERT INTO products (category_id, description, unit, code, unit_cost, selling_price, initial_quantity, reorder_threshold)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?)"
    );
    $stmt->bind_param('isssddii', $catId, $desc, $unit, $code, $cost, $price, $qty, $thresh);
    if ($stmt->execute()) {
        $newId = $conn->insert_id;
        // Log audit entry
        $newValues = json_encode([
            'category_id' => $catId,
            'description' => $desc,
            'unit' => $unit,
            'code' => $code,
            'unit_cost' => $cost,
            'selling_price' => $price,
            'initial_quantity' => $qty,
            'reorder_threshold' => $thresh
        ]);
        logAudit($conn, $userId, 'INSERT', 'products', $newId, null, $newValues);
        json_out(['success' => true, 'message' => 'Product added successfully']);
    } else {
        json_out(['success' => false, 'message' => 'SQL Error: ' . $stmt->error]);
    }
    $stmt->close();
    exit;
}

/* ══════════════════════
UPDATE PRODUCT (owner)
══════════════════════ */
if ($action === 'update') {
    if (!$isOwner) {
        json_out(['success' => false, 'message' => 'Owner only']);
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
        json_out(['success' => false, 'message' => 'Missing fields']);
        exit;
    }

    // Fetch old values for audit log
    $oldStmt = $conn->prepare("SELECT * FROM products WHERE product_id = ?");
    $oldStmt->bind_param('i', $id);
    $oldStmt->execute();
    $oldResult = $oldStmt->get_result()->fetch_assoc();
    $oldStmt->close();

    $stmt = $conn->prepare(
        "UPDATE products SET category_id=?, description=?, unit=?, code=?,
            unit_cost=?, selling_price=?, initial_quantity=?, reorder_threshold=?
            WHERE product_id=?"
    );
    $stmt->bind_param('isssddiii', $catId, $desc, $unit, $code, $cost, $price, $qty, $thresh, $id);
    if ($stmt->execute()) {
        // Log audit entry
        $oldValues = json_encode($oldResult);
        $newValues = json_encode([
            'category_id' => $catId,
            'description' => $desc,
            'unit' => $unit,
            'code' => $code,
            'unit_cost' => $cost,
            'selling_price' => $price,
            'initial_quantity' => $qty,
            'reorder_threshold' => $thresh
        ]);
        logAudit($conn, $userId, 'UPDATE', 'products', $id, $oldValues, $newValues);
        json_out(['success' => true, 'message' => 'Updated successfully']);
    } else {
        json_out(['success' => false, 'message' => 'SQL Error: ' . $stmt->error]);
    }
    $stmt->close();
    exit;
}

/* ══════════════════════
DELETE PRODUCT (owner)
══════════════════════ */
if ($action === 'delete') {
    if (!$isOwner) {
        json_out(['success' => false, 'message' => 'Owner only']);
        exit;
    }

    $id = (int) ($_POST['id'] ?? 0);
    if ($id <= 0) {
        json_out(['success' => false, 'message' => 'Invalid ID']);
        exit;
    }

    // Fetch old values for audit log before deletion
    $oldStmt = $conn->prepare("SELECT * FROM products WHERE product_id = ?");
    $oldStmt->bind_param('i', $id);
    $oldStmt->execute();
    $oldResult = $oldStmt->get_result()->fetch_assoc();
    $oldStmt->close();

    $stmt = $conn->prepare("DELETE FROM products WHERE product_id = ?");
    $stmt->bind_param('i', $id);
    if ($stmt->execute()) {
        // Log audit entry
        $oldValues = json_encode($oldResult);
        logAudit($conn, $userId, 'DELETE', 'products', $id, $oldValues, null);
        json_out(['success' => true, 'message' => 'Deleted successfully']);
    } else {
        json_out(['success' => false, 'message' => 'Delete failed: ' . $stmt->error]);
    }
    $stmt->close();
    exit;
}

/* ══════════════════════════════════
RESTOCK  ← fixed, bulletproof
══════════════════════════════════ */
if ($action === 'restock') {
    $productId = (int) ($_POST['product_id'] ?? 0);
    $qty = (int) ($_POST['quantity'] ?? 0);
    $remarks = trim($_POST['remarks'] ?? 'Restock');

    if ($productId <= 0) {
        json_out(['success' => false, 'message' => 'Invalid product ID.']);
        exit;
    }
    if ($qty <= 0) {
        json_out(['success' => false, 'message' => 'Quantity must be greater than 0.']);
        exit;
    }

    // Verify product exists
    $check = $conn->prepare("SELECT product_id, description FROM products WHERE product_id = ?");
    $check->bind_param('i', $productId);
    $check->execute();
    $prod = $check->get_result()->fetch_assoc();
    $check->close();

    if (!$prod) {
        json_out(['success' => false, 'message' => 'Product not found.']);
        exit;
    }

    $today = date('Y-m-d');

    // Insert inventory transaction
    $stmt = $conn->prepare(
        "INSERT INTO inventory_transactions
            (product_id, transaction_date, quantity_change, transaction_type, remarks, created_by)
            VALUES (?, ?, ?, 'restock', ?, ?)"
    );
    $stmt->bind_param('iissi', $productId, $today, $qty, $remarks, $userId);

    if (!$stmt->execute()) {
        json_out(['success' => false, 'message' => 'Restock failed: ' . $stmt->error]);
        exit;
    }
    $stmt->close();

    // NOTE: Do NOT update initial_quantity here.
    // Stock is calculated as: initial_quantity + SUM(inventory_transactions.quantity_change)
    // The transaction insert above is the only thing needed.

    // Get updated stock level
    $newStock = getCurrentStock($conn, $productId);

    json_out([
        'success' => true,
        'message' => 'Restocked successfully.',
        'new_stock' => $newStock,
        'product' => $prod['description'],
        'qty_added' => $qty,
    ]);
}

/* ══════════════════════
REORDER LIST
══════════════════════ */
if ($action === 'reorder-list') {
    $result = $conn->query("
            SELECT p.product_id, p.code, p.description,
                COALESCE(c.category_name,'') AS category_name,
                COALESCE(ps.current_stock,
                    p.initial_quantity + COALESCE((
                        SELECT SUM(t.quantity_change)
                        FROM inventory_transactions t
                        WHERE t.product_id = p.product_id
                    ), 0)
                ) AS current_stock,
                p.reorder_threshold,
                p.unit
            FROM products p
            LEFT JOIN categories c ON p.category_id = c.category_id
            LEFT JOIN product_stock ps ON p.product_id = ps.product_id
            WHERE COALESCE(ps.current_stock,
                    p.initial_quantity + COALESCE((
                        SELECT SUM(t2.quantity_change)
                        FROM inventory_transactions t2
                        WHERE t2.product_id = p.product_id
                    ), 0)
                ) <= p.reorder_threshold
            ORDER BY current_stock ASC
        ");
    $items = [];
    if ($result)
        while ($row = $result->fetch_assoc())
            $items[] = $row;
    json_out(['success' => true, 'items' => $items]);
    exit;
}

/*STOCK LEDGER — monthly in/out per product*/
if ($action === 'stock-ledger') {
    $year = max(2020, min(2100, (int) ($_GET['year'] ?? date('Y'))));
    $month = max(1, min(12, (int) ($_GET['month'] ?? date('n'))));

    $monthStart = sprintf('%04d-%02d-01', $year, $month);
    $monthEnd = date('Y-m-t', strtotime($monthStart));
    $txnPk = inventory_txn_pk_column($conn);

    // Get all products
    $products = [];
    $rp = $conn->query("SELECT p.product_id, p.description, p.unit, p.initial_quantity, c.category_name
            FROM products p
            LEFT JOIN categories c ON p.category_id = c.category_id
            ORDER BY c.category_name, p.description");
    if (!$rp) {
        json_out(['success' => false, 'message' => 'Could not load products: ' . $conn->error]);
    }
    while ($row = $rp->fetch_assoc()) {
        $products[] = $row;
    }

    $ledger = [];

    foreach ($products as $p) {
        $pid = (int) $p['product_id'];

        // Beginning stock = initial_qty + all transactions BEFORE this month
        $r1 = $conn->query("
                SELECT COALESCE(SUM(quantity_change), 0) AS pre_change
                FROM inventory_transactions
                WHERE product_id = $pid AND transaction_date < '$monthStart'
            ");
        if (!$r1) {
            json_out(['success' => false, 'message' => 'Ledger query failed: ' . $conn->error]);
        }
        $preChange = (int) ($r1->fetch_assoc()['pre_change'] ?? 0);
        $beginStock = (int) $p['initial_quantity'] + $preChange;

        // Restocked (bought) this month — positive changes
        $r2 = $conn->query("
                SELECT COALESCE(SUM(quantity_change), 0) AS added
                FROM inventory_transactions
                WHERE product_id = $pid
                AND transaction_date BETWEEN '$monthStart' AND '$monthEnd'
                AND quantity_change > 0
            ");
        if (!$r2) {
            json_out(['success' => false, 'message' => 'Ledger query failed: ' . $conn->error]);
        }
        $added = (int) ($r2->fetch_assoc()['added'] ?? 0);

        // Used (sales/deductions) this month — negative changes
        $r3 = $conn->query("
                SELECT COALESCE(SUM(quantity_change), 0) AS deducted
                FROM inventory_transactions
                WHERE product_id = $pid
                AND transaction_date BETWEEN '$monthStart' AND '$monthEnd'
                AND quantity_change < 0
            ");
        if (!$r3) {
            json_out(['success' => false, 'message' => 'Ledger query failed: ' . $conn->error]);
        }
        $deducted = (int) ($r3->fetch_assoc()['deducted'] ?? 0);
        $used = abs($deducted);

        // Ending stock
        $endStock = $beginStock + $added - $used;

        // Transaction details for this month
        $r4 = $conn->query("
                SELECT transaction_date, quantity_change, transaction_type, remarks
                FROM inventory_transactions
                WHERE product_id = $pid
                AND transaction_date BETWEEN '$monthStart' AND '$monthEnd'
                ORDER BY transaction_date ASC, {$txnPk} ASC
            ");
        if (!$r4) {
            json_out(['success' => false, 'message' => 'Ledger query failed: ' . $conn->error]);
        }
        $txns = [];
        while ($row = $r4->fetch_assoc()) {
            $txns[] = $row;
        }

        // Only include products that had any activity OR have stock
        if ($beginStock == 0 && $added == 0 && $used == 0) {
            continue;
        }

        $ledger[] = [
            'product_id' => $pid,
            'description' => $p['description'],
            'unit' => $p['unit'],
            'category' => $p['category_name'] ?? '',
            'begin_stock' => $beginStock,
            'added' => $added,
            'used' => $used,
            'end_stock' => $endStock,
            'transactions' => $txns,
        ];
    }

    json_out([
        'success' => true,
        'year' => $year,
        'month' => $month,
        'month_name' => date('F Y', strtotime($monthStart)),
        'ledger' => $ledger,
    ]);
}

json_out(['success' => false, 'message' => 'Unknown action.']);