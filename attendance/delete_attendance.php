<?php
session_start();
include("dbconnection.php");
include("../users/log_activity.php");

// Auth check
if (!isset($_SESSION['user_id'])) {
    header("Location: ../users/index.php");
    exit();
}

// Only admin and teacher can delete attendance
$user_role = strtolower($_SESSION['role'] ?? 'student');
if ($user_role !== 'admin' && $user_role !== 'administrator' && $user_role !== 'teacher') {
    header("Location: ../users/dashboard.php");
    exit();
}

$id = isset($_GET['id']) ? intval($_GET['id']) : 0;

if ($id > 0) {
    $stmt = $conn->prepare("DELETE FROM attendance WHERE attendance_id = ?");
    $stmt->bind_param("i", $id);

    if ($stmt->execute()) {
        log_activity($conn, "Deleted attendance record #" . $id);
        header("Location: attendance.php?date=&msg=" . urlencode("Attendance record deleted successfully."));
        exit();
    } else {
        header("Location: attendance.php?err=" . urlencode("Failed to delete the record."));
        exit();
    }
} else {
    header("Location: attendance.php?err=" . urlencode("Invalid record ID."));
    exit();
}