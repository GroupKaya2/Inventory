<?php
$conn = new mysqli("localhost", "root", "", "login_system");
if ($conn->connect_error) {
    ob_end_clean();
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['success'=>false,'message'=>'DB Error: '.$conn->connect_error]);
    exit;
}
$conn->set_charset("utf8mb4");