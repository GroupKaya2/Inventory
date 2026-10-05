    <?php
    require_once __DIR__ . '/db.php';

    // Login history table -- separate from audit_log since a login isn't tied to
    // any particular record/table the way a create/update/delete is.
    // Wrapped in try/catch: this file is require_once'd by nearly every page in
    // the app, so a schema issue here must never take down the whole site.
    try {
        $conn->query("CREATE TABLE IF NOT EXISTS login_history (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL,
            login_time TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            logout_time TIMESTAMP NULL DEFAULT NULL,
            ip_address VARCHAR(45) NULL,
            user_agent VARCHAR(255) NULL,
            INDEX idx_user (user_id),
            INDEX idx_login_time (login_time)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    } catch (\Throwable $e) {
        // Swallow it -- worst case, login history just won't record until this
        // is fixed, instead of every page in the app breaking.
        error_log('login_history table creation failed: ' . $e->getMessage());
    }

    /**
     * Log an audit entry
     *
     * @param mysqli $conn Database connection
     * @param int $userId User ID performing the action
     * @param string $actionType Action type (CREATE, UPDATE, DELETE, ADJUSTMENT)
     * @param string $tableName Table name affected
     * @param int $recordId ID of the record affected
     * @param string|null $oldValues JSON string of old values (for UPDATE/DELETE)
     * @param string|null $newValues JSON string of new values
     * @return bool Success status
     */
    function logAudit($conn, $userId, $actionType, $tableName, $recordId, $oldValues = null, $newValues = null) {
        $ipAddress = $_SERVER['REMOTE_ADDR'] ?? 'Unknown';

        $stmt = $conn->prepare("INSERT INTO audit_log (user_id, action_type, table_name, record_id, old_values, new_values, ip_address)
                            VALUES (?, ?, ?, ?, ?, ?, ?)");
        $stmt->bind_param('ississs', $userId, $actionType, $tableName, $recordId, $oldValues, $newValues, $ipAddress);

        $result = $stmt->execute();
        $stmt->close();

        return $result;
    }

    /**
     * Get audit log entries, with optional filters.
     *
     * @param mysqli $conn
     * @param int|null $limit Number of entries to return (null for all)
     * @param int|null $userId Filter by user ID
     * @param string|null $tableName Filter by module/table (e.g. 'expenses', 'sales')
     * @param string|null $actionType Filter by action (CREATE, UPDATE, DELETE, ADJUSTMENT)
     * @param string|null $dateFrom Filter from this date (Y-m-d), inclusive
     * @param string|null $dateTo Filter to this date (Y-m-d), inclusive
     * @return array
     */
    function getAuditLog($conn, $limit = 50, $userId = null, $tableName = null, $actionType = null, $dateFrom = null, $dateTo = null) {
        $query = "SELECT al.*, u.name AS username, u.role AS user_role
                FROM audit_log al
                LEFT JOIN users u ON al.user_id = u.id
                WHERE 1=1";

        if ($userId !== null) {
            $query .= " AND al.user_id = " . (int) $userId;
        }
        if ($tableName !== null && $tableName !== '') {
            $query .= " AND al.table_name = '" . $conn->real_escape_string($tableName) . "'";
        }
        if ($actionType !== null && $actionType !== '') {
            $query .= " AND al.action_type = '" . $conn->real_escape_string($actionType) . "'";
        }
        if ($dateFrom) {
            $query .= " AND DATE(al.created_at) >= '" . $conn->real_escape_string($dateFrom) . "'";
        }
        if ($dateTo) {
            $query .= " AND DATE(al.created_at) <= '" . $conn->real_escape_string($dateTo) . "'";
        }

        $query .= " ORDER BY al.created_at DESC";

        if ($limit !== null) {
            $query .= " LIMIT " . (int) $limit;
        }

        $result = $conn->query($query);
        $entries = [];

        if ($result) {
            while ($row = $result->fetch_assoc()) {
                $entries[] = $row;
            }
        }

        return $entries;
    }

    /**
     * Record a successful login. Returns the new login_history row's ID so the
     * session can remember it and later fill in logout_time.
     */
    function logLogin($conn, $userId) {
        $ipAddress = $_SERVER['REMOTE_ADDR'] ?? 'Unknown';
        $userAgent = substr($_SERVER['HTTP_USER_AGENT'] ?? 'Unknown', 0, 255);

        try {
            $stmt = $conn->prepare("INSERT INTO login_history (user_id, ip_address, user_agent) VALUES (?, ?, ?)");
            $stmt->bind_param('iss', $userId, $ipAddress, $userAgent);
            $stmt->execute();
            $loginId = $conn->insert_id;
            $stmt->close();
            return $loginId;
        } catch (\Throwable $e) {
            error_log('logLogin failed: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Mark a login session as logged out.
     */
    function logLogout($conn, $loginId) {
        if (!$loginId) return false;

        try {
            $stmt = $conn->prepare("UPDATE login_history SET logout_time = NOW() WHERE id = ? AND logout_time IS NULL");
            $stmt->bind_param('i', $loginId);
            $result = $stmt->execute();
            $stmt->close();
            return $result;
        } catch (\Throwable $e) {
            error_log('logLogout failed: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Get login history, with optional filters.
     */
    function getLoginHistory($conn, $limit = 100, $userId = null, $dateFrom = null, $dateTo = null) {
        $query = "SELECT lh.*, u.name AS username, u.role AS user_role
                FROM login_history lh
                LEFT JOIN users u ON lh.user_id = u.id
                WHERE 1=1";

        if ($userId !== null) {
            $query .= " AND lh.user_id = " . (int) $userId;
        }
        if ($dateFrom) {
            $query .= " AND DATE(lh.login_time) >= '" . $conn->real_escape_string($dateFrom) . "'";
        }
        if ($dateTo) {
            $query .= " AND DATE(lh.login_time) <= '" . $conn->real_escape_string($dateTo) . "'";
        }

        $query .= " ORDER BY lh.login_time DESC";

        if ($limit !== null) {
            $query .= " LIMIT " . (int) $limit;
        }

        $result = $conn->query($query);
        $entries = [];

        if ($result) {
            while ($row = $result->fetch_assoc()) {
                $entries[] = $row;
            }
        }

        return $entries;
    }