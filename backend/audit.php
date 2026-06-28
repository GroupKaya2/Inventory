    <?php
    require_once __DIR__ . '/db.php';

    /**
     * Log an audit entry
     * 
     * @param mysqli $conn Database connection
     * @param int $userId User ID performing the action
     * @param string $actionType Action type (INSERT, UPDATE, DELETE)
     * @param string $tableName Table name affected
     * @param int $recordId ID of the record affected
     * @param string|null $oldValues JSON string of old values (for UPDATE)
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
     * Get audit lo
     * @param mysqli $conn Database connection
     * @param int|null $limit Number of entries to return (null for all)
     * @param int|null $userId Filter by user ID (null for all users)
     * @return array Array of audit log entries
     */
    function getAuditLog($conn, $limit = 50, $userId = null) {
        $query = "SELECT al.*, u.name AS username
                FROM audit_log al
                LEFT JOIN users u ON al.user_id = u.id
                WHERE 1=1";
        
        if ($userId !== null) {
            $query .= " AND al.user_id = " . (int)$userId;
        }
        
        $query .= " ORDER BY al.created_at DESC";
        
        if ($limit !== null) {
            $query .= " LIMIT " . (int)$limit;
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