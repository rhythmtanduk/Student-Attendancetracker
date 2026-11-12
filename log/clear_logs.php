<?php
session_start();
include("dbconnection.php");
include("../users/log_activity.php");

// Auth check
if (!isset($_SESSION['user_id'])) {
    header("Location: ../users/index.php");
    exit();
}

// Only the administrator can clear the log
$user_role = strtolower($_SESSION['role'] ?? 'student');
if ($user_role !== 'admin' && $user_role !== 'administrator') {
    header("Location: ../users/dashboard.php");
    exit();
}

$stmt = $conn->prepare("DELETE FROM activity_logs WHERE created_at < (NOW() - INTERVAL 30 DAY)");
$stmt->execute();
$removed = $stmt->affected_rows;
$stmt->close();

// Write this action in the log too, so it is not secret
log_activity($conn, "Cleared old activity log (" . $removed . " lines older than 30 days)");

header("Location: logs.php?msg=" . urlencode($removed . " old log lines removed."));
exit();