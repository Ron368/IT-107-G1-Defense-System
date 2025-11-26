<?php
session_start();
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
	header('Location: ../index.php');
	exit;
}

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/AuditLogger.php';

$audit = new AuditLogger($conn); 

// Get filters from request
$filters = [
	'role' => $_GET['filter_role'] ?? '',
	'action' => $_GET['filter_action'] ?? '',
	'date_from' => $_GET['date_from'] ?? '',
	'date_to' => $_GET['date_to'] ?? ''
];

// Get logs based on filters
$logs = $audit->getLogs(array_filter($filters));
?>

<!DOCTYPE html>
<html>
<head>
	<title>Audit Logs</title>
	<style>
		body { font-family: Arial, sans-serif; margin: 20px; }
		.header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; }
		.filters { background: #f5f5f5; padding: 15px; margin-bottom: 20px; border-radius: 5px; }
		.filters form { display: flex; gap: 10px; flex-wrap: wrap; align-items: end; }
		.filter-group { display: flex; flex-direction: column; }
		.filter-group label { font-size: 12px; margin-bottom: 3px; }
		.filter-group input, .filter-group select { padding: 5px; }
		table { width: 100%; border-collapse: collapse; background: white; }
		th { background: #3b7ddd; color: white; padding: 10px; text-align: left; }
		td { padding: 8px; border-bottom: 1px solid #ddd; font-size: 13px; }
		tr:hover { background: #f9f9f9; }
		.details { font-family: monospace; font-size: 11px; color: #666; max-width: 300px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
		.action { font-weight: bold; }
		.role-badge { padding: 3px 8px; border-radius: 3px; font-size: 11px; font-weight: bold; }
		.role-admin { background: #dc3545; color: white; }
		.role-teacher { background: #28a745; color: white; }
		.role-student { background: #007bff; color: white; }
		button { padding: 8px 15px; background: #3b7ddd; color: white; border: none; border-radius: 4px; cursor: pointer; }
		button:hover { background: #2f69bf; }
		.back-btn { background: #6c757d; }
		.back-btn:hover { background: #5a6268; }
	</style>
</head>
<body>
	<div class="header">
		<h1>Audit Logs</h1>
		<a href="admin_dashboard.php"><button class="back-btn">← Back to Dashboard</button></a>
	</div>

	<div class="filters">
		<form method="GET">
			<div class="filter-group">
				<label>Role:</label>
				<select name="filter_role">
					<option value="">All Roles</option>
					<option value="admin" <?php echo $filters['role'] === 'admin' ? 'selected' : ''; ?>>Admin</option>
					<option value="teacher" <?php echo $filters['role'] === 'teacher' ? 'selected' : ''; ?>>Teacher</option>
					<option value="student" <?php echo $filters['role'] === 'student' ? 'selected' : ''; ?>>Student</option>
				</select>
			</div>
			
			<div class="filter-group">
				<label>Action:</label>
				<input type="text" name="filter_action" placeholder="e.g., Login, Add Teacher" value="<?php echo htmlspecialchars($filters['action']); ?>">
			</div>
			
			<div class="filter-group">
				<label>From Date:</label>
				<input type="date" name="date_from" value="<?php echo htmlspecialchars($filters['date_from']); ?>">
			</div>
			
			<div class="filter-group">
				<label>To Date:</label>
				<input type="date" name="date_to" value="<?php echo htmlspecialchars($filters['date_to']); ?>">
			</div>
			
			<div class="filter-group">
				<label>&nbsp;</label>
				<button type="submit">Filter</button>
			</div>
		</form>
	</div>

	<table>
		<thead>
			<tr>
				<th>Date & Time</th>
				<th>User</th>
				<th>Role</th>
				<th>Action</th>
				<th>Table</th>
				<th>Record ID</th>
				<th>IP Address</th>
				<th>Details</th>
			</tr>
		</thead>
		<tbody>
			<?php if ($logs->num_rows > 0): ?>
				<?php while ($log = $logs->fetch_assoc()): ?>
				<tr>
					<td><?php echo date('Y-m-d H:i:s', strtotime($log['created_at'])); ?></td>
					<td><?php echo htmlspecialchars($log['username']); ?></td>
					<td>
						<span class="role-badge role-<?php echo $log['role']; ?>">
							<?php echo strtoupper($log['role']); ?>
						</span>
					</td>
					<td class="action"><?php echo htmlspecialchars($log['action']); ?></td>
					<td><?php echo htmlspecialchars($log['table_name'] ?? '-'); ?></td>
					<td><?php echo htmlspecialchars($log['record_id'] ?? '-'); ?></td>
					<td><?php echo htmlspecialchars($log['ip_address']); ?></td>
					<td>
						<?php if ($log['new_values']): ?>
							<span class="details" title="<?php echo htmlspecialchars($log['new_values']); ?>">
								<?php 
								$data = json_decode($log['new_values'], true);
								if ($data) {
									echo "New: " . implode(', ', array_map(function($k, $v) {
										return "$k=$v";
									}, array_keys($data), $data));
								}
								?>
							</span>
						<?php endif; ?>
						
						<?php if ($log['old_values']): ?>
							<br><span class="details" title="<?php echo htmlspecialchars($log['old_values']); ?>">
								<?php 
								$data = json_decode($log['old_values'], true);
								if ($data) {
									echo "Old: " . implode(', ', array_map(function($k, $v) {
										return "$k=$v";
									}, array_keys($data), $data));
								}
								?>
							</span>
						<?php endif; ?>
					</td>
				</tr>
				<?php endwhile; ?>
			<?php else: ?>
				<tr>
					<td colspan="8" style="text-align: center; padding: 30px; color: #999;">
						No audit logs found matching your filters.
					</td>
				</tr>
			<?php endif; ?>
		</tbody>
	</table>

	<p style="margin-top: 20px; color: #666; font-size: 13px;">
		Showing last 1,000 records. Total logs: <?php echo $logs->num_rows; ?>
	</p>
</body>
</html>

<?php
$conn->close();
?>