<?php
session_start();
include("dbconnection.php");

// Auth check
if (!isset($_SESSION['user_id'])) {
    header("Location: ../users/index.php");
    exit();
}

// Only the administrator can delete teachers
$user_role = strtolower($_SESSION['role'] ?? 'student');
if ($user_role !== 'admin' && $user_role !== 'administrator') {
    header("Location: ../users/dashboard.php");
    exit();
}

$id = $_GET['id'] ?? null;

if ($id) {
    $stmt = $conn->prepare("DELETE FROM teachers WHERE teacher_id = ?");
    $stmt->bind_param("i", $id);

    if ($stmt->execute()) {
        header("Location: teachers.php?msg=Teacher deleted successfully.");
        exit();
    } else {
        header("Location: teachers.php?err=Failed to delete record.");
        exit();
    }
} else {
    header("Location: teachers.php?err=Invalid Teacher ID.");
    exit();
}
