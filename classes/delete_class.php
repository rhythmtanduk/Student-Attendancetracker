<?php
session_start();
include("dbconnection.php");

// Auth check
if (!isset($_SESSION['user_id'])) {
    header("Location: ../users/index.php");
    exit();
}

// Only the administrator can delete classes
$user_role = strtolower($_SESSION['role'] ?? 'student');
if ($user_role !== 'admin' && $user_role !== 'administrator') {
    header("Location: ../users/dashboard.php");
    exit();
}

$id = isset($_GET['id']) ? intval($_GET['id']) : 0;

if ($id <= 0) {
    header("Location: classes.php?err=Invalid class ID.");
    exit();
}

// A class that still has students cannot be deleted
$stmt = $conn->prepare("SELECT COUNT(*) AS total FROM students WHERE class_id = ?");
$stmt->bind_param("i", $id);
$stmt->execute();
$students = $stmt->get_result()->fetch_assoc()['total'];
$stmt->close();

if ($students > 0) {
    header("Location: classes.php?err=" . urlencode("This class still has $students student(s). Move or delete them first."));
    exit();
}

// The subjects of this class are deleted together with it
$stmt = $conn->prepare("DELETE FROM classes WHERE class_id = ?");
$stmt->bind_param("i", $id);

if ($stmt->execute()) {
    header("Location: classes.php?msg=Class deleted successfully.");
} else {
    header("Location: classes.php?err=Failed to delete the class.");
}
$stmt->close();
exit();