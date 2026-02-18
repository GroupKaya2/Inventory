<?php
header('Content-Type: application/json');
include "db.php";

try {
    $sql = "SELECT p.product_id, p.category_id, p.description, p.unit, p.unit_cost, p.selling_price, p.code, p.initial_quantity, p.created_at,
                   c.category_name
            FROM products p
            LEFT JOIN categories c ON p.category_id = c.category_id
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
            'unit_cost' => floatval($row['unit_cost']), // Raw value for JavaScript formatting
            'selling_price' => floatval($row['selling_price']), // Raw value for JavaScript formatting
            'margin' => $margin, // Raw value for JavaScript formatting
            'code' => $row['code'],
            'initial_quantity' => $row['initial_quantity'],
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

