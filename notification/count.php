<?php
session_start();
include("dbconnection.php");

header("Content-Type: application/json");

if (!isset($_SESSION['user_id'])) {
    echo json_encode(["unread" => 0]);
    exit();
}

$stmt = $conn->prepare("SELECT COUNT(*) AS total FROM notifications WHERE user_id = ? AND is_read = 0");
$stmt->bind_param("i", $_SESSION['user_id']);
$stmt->execute();
$total = $stmt->get_result()->fetch_assoc()['total'];
$stmt->close();

echo json_encode(["unread" => (int)$total]);