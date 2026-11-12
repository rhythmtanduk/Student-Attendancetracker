<?php
session_start();
include("dbconnection.php");

if (!isset($_SESSION['user_id'])) {
    header("Location: ../users/index.php");
    exit();
}

$id = isset($_GET['id']) ? intval($_GET['id']) : 0;

// Only YOUR notification can be deleted
$stmt = $conn->prepare("DELETE FROM notifications WHERE notification_id = ? AND user_id = ?");
$stmt->bind_param("ii", $id, $_SESSION['user_id']);
$stmt->execute();

if ($stmt->affected_rows > 0) {
    header("Location: notifications.php?msg=" . urlencode("Notification deleted."));
} else {
    header("Location: notifications.php?err=" . urlencode("Notification not found."));
}
$stmt->close();
exit();