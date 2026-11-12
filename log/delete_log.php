<?php
session_start();
include("dbconnection.php");

// Auth check
if (!isset($_SESSION['user_id'])) {
    header("Location: ../users/index.php");
    exit();
}

// Only the administrator can delete log lines
$user_role = strtolower($_SESSION['role'] ?? 'student');
if ($user_role !== 'admin' && $user_role !== 'administrator') {
    header("Location: ../users/dashboard.php");
    exit();
}

$id = isset($_GET['id']) ? intval($_GET['id']) : 0;

if ($id > 0) {
    $stmt = $conn->prepare("DELETE FROM activity_logs WHERE log_id = ?");
    $stmt->bind_param("i", $id);

    if ($stmt->execute()) {
        header("Location: logs.php?msg=" . urlencode("Log line deleted."));
        exit();
    } else {
        header("Location: logs.php?err=" . urlencode("Failed to delete the log line."));
        exit();
    }
} else {
    header("Location: logs.php?err=" . urlencode("Invalid log ID."));
    exit();
}