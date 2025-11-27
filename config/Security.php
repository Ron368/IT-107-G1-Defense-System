<?php
class Security {
    private $conn;
    
    public function __construct($db_connection) {
        $this->conn = $db_connection;
    }

    // --- DDoS Protection (Rate Limiting) ---
    public function checkRateLimit($limit = 2, $seconds = 1) {
        // Use session to track request frequency per user
        if (!isset($_SESSION['last_request_time'])) {
            $_SESSION['last_request_time'] = microtime(true);
            $_SESSION['request_count'] = 1;
            return true;
        }

        $current_time = microtime(true);
        $time_diff = $current_time - $_SESSION['last_request_time'];

        if ($time_diff < $seconds) {
            $_SESSION['request_count']++;
            if ($_SESSION['request_count'] > $limit) {
                header('HTTP/1.1 429 Too Many Requests');
                die("Too many requests. Please wait a moment.");
            }
        } else {
            // Reset if time window passed
            $_SESSION['last_request_time'] = $current_time;
            $_SESSION['request_count'] = 1;
        }
        return true;
    }

    // --- Brute Force Protection ---
    public function checkBruteForce($ip, $username, $max_attempts = 3, $lockout_time = 15) {
        // Check attempts in the last X minutes
        $stmt = $this->conn->prepare("
            SELECT COUNT(*) as count 
            FROM login_attempts 
            WHERE ip_address = ? 
            AND (username = ? OR username IS NULL)
            AND attempt_time > (NOW() - INTERVAL ? MINUTE)
        ");
        
        $stmt->bind_param("ssi", $ip, $username, $lockout_time);
        $stmt->execute();
        $result = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if ($result['count'] >= $max_attempts) {
            return true; // Blocked
        }
        return false; // Allowed
    }

    public function logFailedAttempt($ip, $username) {
        $stmt = $this->conn->prepare("INSERT INTO login_attempts (ip_address, username) VALUES (?, ?)");
        $stmt->bind_param("ss", $ip, $username);
        $stmt->execute();
        $stmt->close();
    }

    public function clearLoginAttempts($ip, $username) {
        $stmt = $this->conn->prepare("DELETE FROM login_attempts WHERE ip_address = ? AND username = ?");
        $stmt->bind_param("ss", $ip, $username);
        $stmt->execute();
        $stmt->close();
    }
    
    public function getIpAddress() {
        if (!empty($_SERVER['HTTP_CLIENT_IP'])) return $_SERVER['HTTP_CLIENT_IP'];
        if (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) return $_SERVER['HTTP_X_FORWARDED_FOR'];
        return $_SERVER['REMOTE_ADDR'];
    }
}
?>