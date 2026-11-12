<?php
// notify.php - small helper functions to create notifications.
// Use it like this:   include("../users/notify.php");
//                     notify_user($conn, $user_id, "Message", "leave/view_leave.php?id=5");

// Send a notification to ONE user
function notify_user($conn, $user_id, $message, $link = null) {
    $message = mb_strimwidth($message, 0, 250, "...");
    $stmt = $conn->prepare("INSERT INTO notifications (user_id, message, link) VALUES (?, ?, ?)");
    $stmt->bind_param("iss", $user_id, $message, $link);
    $stmt->execute();
    $stmt->close();
}

// Send the same notification to every administrator and teacher
function notify_staff($conn, $message, $link = null) {
    $staff = $conn->query("SELECT user_id FROM users WHERE role IN ('Administrator', 'Teacher') AND status = 'active'");
    while ($u = $staff->fetch_assoc()) {
        notify_user($conn, $u['user_id'], $message, $link);
    }
}