<?php
session_start();
include("dbconnection.php");

// Auth check
if (!isset($_SESSION['user_id'])) {
    header("Location: ../users/index.php");
    exit();
}

// Only the administrator can delete holidays
$user_role = strtolower($_SESSION['role'] ?? 'student');
if ($user_role !== 'admin' && $user_role !== 'administrator') {
    header("Location: ../users/dashboard.php");
    exit();
}

$id = isset($_GET['id']) ? intval($_GET['id']) : 0;

if ($id > 0) {
    $stmt = $conn->prepare("DELETE FROM holidays WHERE holiday_id = ?");
    $stmt->bind_param("i", $id);

    if ($stmt->execute()) {
        header("Location: holidays.php?msg=Holiday deleted successfully.");
        exit();
    } else {
        header("Location: holidays.php?err=Failed to delete the holiday.");
        exit();
    }
} else {
    header("Location: holidays.php?err=Invalid holiday ID.");
    exit();
}