<?php
session_start();
header('Content-Type: application/json');
if (!isset($_SESSION['user_id'])) { echo json_encode(['success'=>false,'message'=>'Unauthorized']); exit; }
include 'db.php';

$id = (int)($_POST['id'] ?? 0);
if ($id <= 0) { echo json_encode(['success'=>false,'message'=>'Invalid ID']); exit; }

$stmt = $conn->prepare("DELETE FROM expenses WHERE id=?");
$stmt->bind_param('i', $id);
if ($stmt->execute() && $stmt->affected_rows > 0) {
    echo json_encode(['success'=>true,'message'=>'Expense deleted.']);
} else {
    echo json_encode(['success'=>false,'message'=>'Expense not found.']);
}
$stmt->close(); $conn->close();