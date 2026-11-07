<?php
session_start();
include("dbconnection.php");

// Auth check
if (!isset($_SESSION['user_id'])) {
    header("Location: ../users/index.php");
    exit();
}

$user_role = strtolower($_SESSION['role'] ?? 'student');
$is_admin  = ($user_role === 'admin' || $user_role === 'administrator');
$id        = isset($_GET['id']) ? intval($_GET['id']) : 0;

if ($id <= 0) {
    header("Location: leave.php?err=" . urlencode("Invalid request ID."));
    exit();
}

if ($is_admin) {
    // The administrator can delete any request
    $stmt = $conn->prepare("DELETE FROM leave_requests WHERE leave_id = ?");
    $stmt->bind_param("i", $id);

} elseif ($user_role === 'student') {
    // A student can only cancel their own PENDING request
    $stmt = $conn->prepare("DELETE FROM leave_requests
                            WHERE leave_id = ? AND status = 'Pending'
                              AND student_id = (SELECT s.student_id FROM students s JOIN users u ON u.email = s.email WHERE u.user_id = ?)");
    $stmt->bind_param("ii", $id, $_SESSION['user_id']);

} else {
    header("Location: leave.php?err=" . urlencode("You are not allowed to delete requests."));
    exit();
}

$stmt->execute();

if ($stmt->affected_rows > 0) {
    header("Location: leave.php?msg=" . urlencode("Leave request removed."));
} else {
    header("Location: leave.php?err=" . urlencode("Request not found, or it was already reviewed."));
}
$stmt->close();
exit();