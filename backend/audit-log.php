    <?php
    session_start();
    header('Content-Type: application/json');
    require_once __DIR__ . '/db.php';
    require_once __DIR__ . '/audit.php';

    if (!isset($_SESSION['user_id'])) {
        echo json_encode(['success' => false, 'message' => 'Unauthorized']);
        exit;
    }

    // This page shows what every staff member has been doing -- owner only.
    $isOwner = ($_SESSION['role'] ?? 'manager') === 'owner';
    if (!$isOwner) {
        echo json_encode(['success' => false, 'message' => 'Owner only.']);
        exit;
    }

    $action = $_GET['action'] ?? '';

    // Activity (create/update/delete) log, with filters
    if ($action === 'fetch') {
        $limit      = (int) ($_GET['limit'] ?? 100);
        $userId     = isset($_GET['user_id']) && $_GET['user_id'] !== '' ? (int) $_GET['user_id'] : null;
        $tableName  = $_GET['module'] ?? null;
        $actionType = $_GET['action_type'] ?? null;
        $dateFrom   = $_GET['date_from'] ?? null;
        $dateTo     = $_GET['date_to'] ?? null;

        $entries = getAuditLog($conn, $limit, $userId, $tableName, $actionType, $dateFrom, $dateTo);

        echo json_encode(['success' => true, 'data' => $entries]);
        exit;
    }

    // Login history, with filters
    if ($action === 'fetch_logins') {
        $limit    = (int) ($_GET['limit'] ?? 100);
        $userId   = isset($_GET['user_id']) && $_GET['user_id'] !== '' ? (int) $_GET['user_id'] : null;
        $dateFrom = $_GET['date_from'] ?? null;
        $dateTo   = $_GET['date_to'] ?? null;

        $entries = getLoginHistory($conn, $limit, $userId, $dateFrom, $dateTo);

        echo json_encode(['success' => true, 'data' => $entries]);
        exit;
    }

    // List of users for the filter dropdown
    if ($action === 'users') {
        $result = $conn->query("SELECT id, name, role FROM users ORDER BY name ASC");
        $users = [];
        if ($result) {
            while ($row = $result->fetch_assoc()) {
                $users[] = $row;
            }
        }
        echo json_encode(['success' => true, 'data' => $users]);
        exit;
    }

    echo json_encode(['success' => false, 'message' => 'Unknown action']);
    $conn->close();