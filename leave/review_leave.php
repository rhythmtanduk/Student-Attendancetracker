<?php
session_start();
include("dbconnection.php");
include("../users/notify.php");

// Auth check
if (!isset($_SESSION['user_id'])) {
    header("Location: ../users/index.php");
    exit();
}

// Only admin and teacher can review leave
$user_role = strtolower($_SESSION['role'] ?? 'student');
if ($user_role !== 'admin' && $user_role !== 'administrator' && $user_role !== 'teacher') {
    header("Location: ../users/dashboard.php");
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: leave.php");
    exit();
}

$id       = isset($_POST['id']) ? intval($_POST['id']) : 0;
$decision = $_POST['decision'] ?? '';
$note     = trim($_POST['note'] ?? '');

if (!in_array($decision, ['Approved', 'Rejected'])) {
    header("Location: leave.php?err=" . urlencode("Invalid decision."));
    exit();
}

if ($decision == 'Rejected' && $note === '') {
    header("Location: view_leave.php?id=$id&err=" . urlencode("Please write a note when rejecting."));
    exit();
}

if (strlen($note) > 255) {
    header("Location: view_leave.php?id=$id&err=" . urlencode("The note must be 255 characters or less."));
    exit();
}

if ($note === '') { $note = null; }

// Only a PENDING request can be reviewed (so it cannot be changed twice)
$stmt = $conn->prepare("UPDATE leave_requests SET status = ?, reviewed_by = ?, review_note = ?
                        WHERE leave_id = ? AND status = 'Pending'");
$stmt->bind_param("sisi", $decision, $_SESSION['user_id'], $note, $id);
$stmt->execute();

if ($stmt->affected_rows > 0) {
    // Tell the student (found by matching the student's email with the login email)
    $info = $conn->prepare("SELECT u.user_id, l.from_date, l.to_date
                            FROM leave_requests l
                            JOIN students s ON s.student_id = l.student_id
                            JOIN users u ON u.email = s.email
                            WHERE l.leave_id = ?");
    $info->bind_param("i", $id);
    $info->execute();
    $who = $info->get_result()->fetch_assoc();
    $info->close();

    if ($who) {
        $text = "Your leave request (" . date("d M", strtotime($who['from_date'])) . " - " . date("d M", strtotime($who['to_date'])) . ") was " . strtolower($decision) . ".";
        if ($note) { $text .= " Note: " . $note; }
        notify_user($conn, $who['user_id'], $text, "leave/view_leave.php?id=" . $id);
    }

    header("Location: leave.php?msg=" . urlencode("Leave request " . strtolower($decision) . "."));
} else {
    header("Location: leave.php?err=" . urlencode("This request was already reviewed or does not exist."));
}
$stmt->close();
exit();