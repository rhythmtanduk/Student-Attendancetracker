<?php
// log_activity.php - writes one line in the activity log.
// Use it like this:   include("../users/log_activity.php");
//                     log_activity($conn, "Marked attendance for Class 11-A");

function log_activity($conn, $action) {
    $uid = isset($_SESSION['user_id']) ? intval($_SESSION['user_id']) : null;
    $action = mb_strimwidth($action, 0, 250, "...");

    $stmt = $conn->prepare("INSERT INTO activity_logs (user_id, action) VALUES (?, ?)");
    $stmt->bind_param("is", $uid, $action);
    $stmt->execute();
    $stmt->close();
}