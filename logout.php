<?php
session_start();

require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/config/AuditLogger.php';

// Log logout before destroying session
if (isset($_SESSION['username']) && isset($_SESSION['role'])) {
	$audit = new AuditLogger($conn);
	$audit->logSimple("Logout");
}

session_destroy();
$conn->close();

header('Location: index.php');
exit;
?>