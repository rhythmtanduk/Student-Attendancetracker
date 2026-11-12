<?php
session_start();
include("dbconnection.php");

if (!isset($_SESSION['user_id'])) {
    header("Location: ../users/index.php");
    exit();
}

$uid = intval($_SESSION['user_id']);
$id  = isset($_GET['id']) ? intval($_GET['id']) : 0;

// Only YOUR notification can be opened
$stmt = $conn->prepare("SELECT notification_id, link FROM notifications WHERE notification_id = ? AND user_id = ?");
$stmt->bind_param("ii", $id, $uid);
$stmt->execute();
$note = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$note) {
    header("Location: notifications.php?err=" . urlencode("Notification not found."));
    exit();
}

// Mark it as read
$stmt = $conn->prepare("UPDATE notifications SET is_read = 1 WHERE notification_id = ?");
$stmt->bind_param("i", $id);
$stmt->execute();
$stmt->close();

// Go to its page. The link must look like  folder/file.php  or  folder/file.php?id=5
// (this stops anyone from sending users to another website)
if ($note['link'] && preg_match('/^[a-z_]+\/[a-z_]+\.php(\?id=\d+)?$/', $note['link'])) {
    header("Location: ../" . $note['link']);
} else {
    header("Location: notifications.php");
}
exit();