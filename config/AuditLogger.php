<?php
/**
 * AuditLogger Class
 * Tracks all user actions in the system
 */
class AuditLogger {
    private $conn;
    
    public function __construct($db_connection) {
        $this->conn = $db_connection;
    }
    
    /**
     * Log an action to the audit trail
     * 
     */
    public function log($action, $table_name = null, $record_id = null, $old_values = null, $new_values = null) {
        // Get user info from session
        $user_id = $_SESSION['user_id'] ?? null;
        $username = $_SESSION['username'] ?? 'Unknown';
        $role = $_SESSION['role'] ?? 'unknown';
        
        // Get IP address
        $ip_address = $this->getIpAddress();
        
        // Get user agent (browser info)
        $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown';
        
        // Convert arrays to JSON for storage
        $old_values_json = $old_values ? json_encode($old_values) : null;
        $new_values_json = $new_values ? json_encode($new_values) : null;
        
        // Insert into audit_logs table
        $stmt = $this->conn->prepare("
            INSERT INTO audit_logs 
            (user_id, username, role, action, table_name, record_id, old_values, new_values, ip_address, user_agent) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        
        $stmt->bind_param(
            "issssissss",
            $user_id,
            $username,
            $role,
            $action,
            $table_name,
            $record_id,
            $old_values_json,
            $new_values_json,
            $ip_address,
            $user_agent
        );
        
        $stmt->execute();
        $stmt->close();
    }
    
    /**
     * Get user's real IP address
     */
    private function getIpAddress() {
        if (!empty($_SERVER['HTTP_CLIENT_IP'])) {
            return $_SERVER['HTTP_CLIENT_IP'];
        } elseif (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
            return $_SERVER['HTTP_X_FORWARDED_FOR'];
        } else {
            return $_SERVER['REMOTE_ADDR'] ?? 'Unknown';
        }
    }
    
    /**
     * Quick log for simple actions (no old/new values)
     */
    public function logSimple($action) {
        $this->log($action);
    }
    
    /**
     * Log login attempts
     */
    public function logLogin($username, $role, $success = true) {
        $action = $success ? "Login Success" : "Login Failed";
        
        // Temporarily set session for logging
        $temp_user = $_SESSION['username'] ?? null;
        $_SESSION['username'] = $username;
        $_SESSION['role'] = $role;
        
        $this->log($action);
        
        // Restore original session
        if ($temp_user) {
            $_SESSION['username'] = $temp_user;
        }
    }
    
    /**
     * Get audit logs with filters
     */
    public function getLogs($filters = []) {
        $sql = "SELECT * FROM audit_logs WHERE 1=1";
        $params = [];
        $types = "";
        
        // Add filters
        if (!empty($filters['user_id'])) {
            $sql .= " AND user_id = ?";
            $params[] = $filters['user_id'];
            $types .= "i";
        }
        
        if (!empty($filters['role'])) {
            $sql .= " AND role = ?";
            $params[] = $filters['role'];
            $types .= "s";
        }
        
        if (!empty($filters['action'])) {
            $sql .= " AND action LIKE ?";
            $params[] = "%" . $filters['action'] . "%";
            $types .= "s";
        }
        
        if (!empty($filters['date_from'])) {
            $sql .= " AND created_at >= ?";
            $params[] = $filters['date_from'];
            $types .= "s";
        }
        
        if (!empty($filters['date_to'])) {
            $sql .= " AND created_at <= ?";
            $params[] = $filters['date_to'] . " 23:59:59";
            $types .= "s";
        }
        
        $sql .= " ORDER BY created_at DESC LIMIT 1000";
        
        if (!empty($params)) {
            $stmt = $this->conn->prepare($sql);
            $stmt->bind_param($types, ...$params);
            $stmt->execute();
            $result = $stmt->get_result();
            $stmt->close();
        } else {
            $result = $this->conn->query($sql);
        }
        
        return $result;
    }
    
    /**
     * Get recent activity for dashboard
     */
    public function getRecentActivity($limit = 10) {
        $sql = "SELECT * FROM audit_logs ORDER BY created_at DESC LIMIT ?";
        $stmt = $this->conn->prepare($sql);
        $stmt->bind_param("i", $limit);
        $stmt->execute();
        $result = $stmt->get_result();
        $stmt->close();
        
        return $result;
    }
}
?>