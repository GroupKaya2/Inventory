<?php
ob_start();
session_start();

function sendJson($arr) {
    ob_end_clean();
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($arr);
    exit;
}

if (empty($_SESSION['user_id'])) {
    sendJson(['success' => false, 'message' => 'Unauthorized']);
}

// SMS feature has been removed from this system.
// Return empty history so any leftover calls don't break.
sendJson([
    'success' => true,
    'history' => [],
    'message' => 'SMS feature is disabled.'
]);