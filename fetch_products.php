<?php
header('Content-Type: application/json');
include "db.php";

try {
    $sql = "SELECT p.product_id, p.category_id, p.description, p.unit, p.unit_cost, p.selling_price, p.code, p.initial_quantity, p.created_at,
                   c.category_name,
                   COALESCE(s.current_stock, p.initial_quantity) AS current_stock
            FROM products p
            LEFT JOIN categories c ON p.category_id = c.category_id
            LEFT JOIN product_stock s ON s.product_id = p.product_id
            ORDER BY p.product_id DESC";
    
    $stmt = $conn->prepare($sql);
    
    if (!$stmt) {
        throw new Exception("Prepare failed: " . $conn->error);
    }
    
    $stmt->execute();
    $result = $stmt->get_result();
    
    $products = [];
    while ($row = $result->fetch_assoc()) {
        $margin = $row['selling_price'] - $row['unit_cost'];
        $products[] = [
            'product_id' => $row['product_id'],
            'category_id' => $row['category_id'],
            'category_name' => $row['category_name'] ?? 'N/A',
            'description' => $row['description'],
            'unit' => $row['unit'],
            'unit_cost' => floatval($row['unit_cost']),
            'selling_price' => floatval($row['selling_price']),
            'margin' => $margin,
            'code' => $row['code'],
            'initial_quantity' => (int)$row['initial_quantity'],
            'current_stock' => (int)($row['current_stock'] ?? $row['initial_quantity']),
            'created_at' => $row['created_at']
        ];
    }
    
    $stmt->close();
    
    echo json_encode([
        'success' => true,
        'data' => $products
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Error fetching products: ' . $e->getMessage()
    ]);
}

$conn->close();
?>

