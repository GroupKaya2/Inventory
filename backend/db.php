    <?php
    // backend/db.php — Database Connection
    // This file is backend only. Never include it in frontend pages directly.

    $conn = new mysqli("localhost", "root", "", "login_system");

    if ($conn->connect_error) {
        // Return JSON error if this is an API call, otherwise show plain message
        $is_api = (strpos($_SERVER['HTTP_ACCEPT'] ?? '', 'application/json') !== false)
            || (strpos($_SERVER['REQUEST_URI'] ?? '', 'backend/') !== false);

        if ($is_api) {
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode(['success' => false, 'message' => 'Database connection failed.']);
        } else {
            http_response_code(500);
            echo "Database connection failed. Please check server settings.";
        }
        exit;
    }

    $conn->set_charset("utf8mb4");
