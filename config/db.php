<?php
$servername = "localhost";
$username = "root";
$password = "";
$dbname = "studentmanagement";

$conn = new mysqli($servername, $username, $password, $dbname);

if ($conn->connect_error) {
	die("Connection failed: " . $conn->connect_error);
}

require_once __DIR__ . '/Security.php';
// Start session if not already started (needed for rate limiting)
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
$security = new Security($conn);
// Allow max 5 requests per 2 seconds
$security->checkRateLimit(5, 2);
?>

