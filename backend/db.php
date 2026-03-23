    <?php
    $conn = new mysqli("localhost", "root", "", "login_system");

    if ($conn->connect_error) {
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
