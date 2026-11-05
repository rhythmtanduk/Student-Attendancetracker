<?php
session_start();
include("dbconnection.php");

if (!isset($_SESSION['user_id'])) {
    header("Location: ../users/index.php");
    exit();
}

// Only the administrator can delete students
$user_role = strtolower($_SESSION['role'] ?? 'student');
if ($user_role !== 'admin' && $user_role !== 'administrator') {
    header("Location: ../users/dashboard.php");
    exit();
}

if (isset($_GET['id'])) {
    $id = intval($_GET['id']);

    $stmt = $conn->prepare("DELETE FROM students WHERE student_id = ?");
    $stmt->bind_param("i", $id);

    if ($stmt->execute()) {
        header("Location: students.php?msg=" . urlencode("Student record deleted successfully."));
    } else {
        header("Location: students.php?err=" . urlencode("Failed to delete student record."));
    }
    $stmt->close();
} else {
    header("Location: students.php");
}
exit();
?>
