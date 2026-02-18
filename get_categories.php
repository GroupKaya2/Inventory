<?php
header('Content-Type: application/json');
include "db.php";

try {
    // First, ensure categories exist in database
    $categories = [
        'Diesel Engine Oil',
        'Gasoline Engine Oils',
        'Transmission Fluids',
        'Coolants',
        'Differential Oil'
    ];
    
    // Check if categories table exists and has data
    $checkSql = "SELECT COUNT(*) as count FROM categories";
    $result = $conn->query($checkSql);
    
    if ($result && $result->num_rows > 0) {
        $row = $result->fetch_assoc();
        if ($row['count'] == 0) {
            // Insert categories if table is empty
            $insertSql = "INSERT INTO categories (category_name) VALUES (?)";
            $stmt = $conn->prepare($insertSql);
            foreach ($categories as $catName) {
                $stmt->bind_param("s", $catName);
                $stmt->execute();
            }
            $stmt->close();
        }
    } else {
        // Create categories table if it doesn't exist
        $createTable = "CREATE TABLE IF NOT EXISTS categories (
            category_id INT AUTO_INCREMENT PRIMARY KEY,
            category_name VARCHAR(100) NOT NULL UNIQUE
        )";
        $conn->query($createTable);
        
        // Insert categories
        $insertSql = "INSERT INTO categories (category_name) VALUES (?)";
        $stmt = $conn->prepare($insertSql);
        foreach ($categories as $catName) {
            $stmt->bind_param("s", $catName);
            $stmt->execute();
        }
        $stmt->close();
    }
    
    // Fetch all categories
    $sql = "SELECT category_id, category_name FROM categories ORDER BY category_name";
    $result = $conn->query($sql);
    
    $categoryList = [];
    while ($row = $result->fetch_assoc()) {
        $categoryList[] = [
            'category_id' => $row['category_id'],
            'category_name' => $row['category_name']
        ];
    }
    
    echo json_encode([
        'success' => true,
        'data' => $categoryList
    ]);
    
} catch (Exception $e) {
    // If database fails, return hardcoded categories
    $categoryList = [];
    foreach ($categories as $index => $catName) {
        $categoryList[] = [
            'category_id' => $index + 1,
            'category_name' => $catName
        ];
    }
    
    echo json_encode([
        'success' => true,
        'data' => $categoryList
    ]);
}

$conn->close();
?>

