<?php
header('Content-Type: application/json');
include "db.php";

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit();
}

try {
    // Get and validate input
    $product_id = intval($_POST['product_id'] ?? 0);
    $description = trim($_POST['description'] ?? '');
    $unit = trim($_POST['unit'] ?? '');
    $unit_cost = floatval($_POST['unit_cost'] ?? 0);
    $selling_price = floatval($_POST['selling_price'] ?? 0);
    $code = trim($_POST['code'] ?? '');
    $initial_quantity = intval($_POST['initial_quantity'] ?? 0);
    $category_id = intval($_POST['category_id'] ?? 0);
    
    // Validation
    if ($product_id <= 0) {
        throw new Exception("Invalid product ID");
    }
    
    if (empty($description) || empty($unit) || empty($code) || $category_id <= 0) {
        throw new Exception("Category, Description, Unit, and Code are required fields");
    }
    
    if ($unit_cost < 0 || $selling_price < 0 || $initial_quantity < 0) {
        throw new Exception("Cost, price, and quantity must be non-negative");
    }
    
    // Validate unit is either Gallon or Liter
    if ($unit !== 'Gallon' && $unit !== 'Liter') {
        throw new Exception("Unit must be either Gallon or Liter");
    }
    
    // Check if product code already exists for another product
    $checkSql = "SELECT product_id FROM products WHERE code = ? AND product_id != ?";
    $checkStmt = $conn->prepare($checkSql);
    $checkStmt->bind_param("si", $code, $product_id);
    $checkStmt->execute();
    $checkResult = $checkStmt->get_result();
    
    if ($checkResult->num_rows > 0) {
        $checkStmt->close();
        throw new Exception("Product code already exists for another product");
    }
    $checkStmt->close();
    
    // Update product using prepared statement
    $sql = "UPDATE products 
            SET category_id = ?, description = ?, unit = ?, unit_cost = ?, selling_price = ?, code = ?, initial_quantity = ?
            WHERE product_id = ?";
    
    $stmt = $conn->prepare($sql);
    
    if (!$stmt) {
        throw new Exception("Prepare failed: " . $conn->error);
    }
    
    $stmt->bind_param("issddsii", $category_id, $description, $unit, $unit_cost, $selling_price, $code, $initial_quantity, $product_id);
    
    if ($stmt->execute()) {
        $stmt->close();
        
        echo json_encode([
            'success' => true,
            'message' => 'Product updated successfully'
        ]);
    } else {
        throw new Exception("Execute failed: " . $stmt->error);
    }
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}

$conn->close();
?>

