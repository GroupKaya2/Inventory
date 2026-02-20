<?php
header('Content-Type: application/json');
include "db.php";

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit();
}

try {
    $product_id = intval($_GET['product_id'] ?? 0);
    
    if ($product_id <= 0) {
        throw new Exception("Invalid product ID");
    }
    
    $hasThreshold = @$conn->query("SHOW COLUMNS FROM products LIKE 'reorder_threshold'")->num_rows > 0;
    $threshCol = $hasThreshold ? ', p.reorder_threshold' : '';
    $sql = "SELECT p.product_id, p.category_id, p.description, p.unit, p.unit_cost, p.selling_price, p.code, p.initial_quantity, p.created_at $threshCol, c.category_name
            FROM products p
            LEFT JOIN categories c ON p.category_id = c.category_id
            WHERE p.product_id = ?";
    
    $stmt = $conn->prepare($sql);
    
    if (!$stmt) {
        throw new Exception("Prepare failed: " . $conn->error);
    }
    
    $stmt->bind_param("i", $product_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        $stmt->close();
        throw new Exception("Product not found");
    }
    
    $product = $result->fetch_assoc();
    $stmt->close();
    if (!isset($product['reorder_threshold'])) {
        $product['reorder_threshold'] = 5;
    }
    echo json_encode([
        'success' => true,
        'data' => $product
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}

$conn->close();
?>

