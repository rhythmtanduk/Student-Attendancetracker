<?php
session_start();
include("dbconnection.php");

// Auth check
if (!isset($_SESSION['user_id'])) {
    header("Location: ../users/index.php");
    exit();
}

// Only the administrator can delete subjects
$user_role = strtolower($_SESSION['role'] ?? 'student');
if ($user_role !== 'admin' && $user_role !== 'administrator') {
    header("Location: ../users/dashboard.php");
    exit();
}

$id = isset($_GET['id']) ? intval($_GET['id']) : 0;

if ($id > 0) {
    $stmt = $conn->prepare("DELETE FROM subjects WHERE subject_id = ?");
    $stmt->bind_param("i", $id);

    if ($stmt->execute()) {
        header("Location: subjects.php?msg=Subject deleted successfully.");
        exit();
    } else {
        header("Location: subjects.php?err=Failed to delete the subject.");
        exit();
    }
} else {
    header("Location: subjects.php?err=Invalid subject ID.");
    exit();
}