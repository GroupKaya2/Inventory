<?php
header('Content-Type: application/json');
include "db.php";

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit();
}

try {
    // Get and validate input
    $description = trim($_POST['description'] ?? '');
    $unit = trim($_POST['unit'] ?? '');
    $unit_cost = floatval($_POST['unit_cost'] ?? 0);
    $selling_price = floatval($_POST['selling_price'] ?? 0);
    $code = trim($_POST['code'] ?? '');
    $initial_quantity = intval($_POST['initial_quantity'] ?? 0);
    $category_id = intval($_POST['category_id'] ?? 0); // Optional category
    
    // Validation
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
    
    // Check if product code already exists
    $checkSql = "SELECT product_id FROM products WHERE code = ?";
    $checkStmt = $conn->prepare($checkSql);
    $checkStmt->bind_param("s", $code);
    $checkStmt->execute();
    $checkResult = $checkStmt->get_result();
    
    if ($checkResult->num_rows > 0) {
        $checkStmt->close();
        throw new Exception("Product code already exists");
    }
    $checkStmt->close();
    
    // Insert product using prepared statement
    $sql = "INSERT INTO products (category_id, description, unit, unit_cost, selling_price, code, initial_quantity) 
            VALUES (?, ?, ?, ?, ?, ?, ?)";
    
    $stmt = $conn->prepare($sql);
    
    if (!$stmt) {
        throw new Exception("Prepare failed: " . $conn->error);
    }
    
    $stmt->bind_param("issddsi", $category_id, $description, $unit, $unit_cost, $selling_price, $code, $initial_quantity);
    
    if ($stmt->execute()) {
        $product_id = $conn->insert_id;
        $stmt->close();
        
        echo json_encode([
            'success' => true,
            'message' => 'Product added successfully',
            'product_id' => $product_id
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

