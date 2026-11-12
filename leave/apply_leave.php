<?php
session_start();
include("dbconnection.php");
include("../users/notify.php");

if (!isset($_SESSION['user_id'])) {
    header("Location: ../users/index.php");
    exit();
}

// Only students can apply for leave
$user_role = strtolower($_SESSION['role'] ?? 'student');
if ($user_role !== 'student') {
    header("Location: leave.php");
    exit();
}

// Find the student record (matched by email). We also need the name for the notification.
$stmt = $conn->prepare("SELECT s.student_id, s.full_name FROM students s JOIN users u ON u.email = s.email WHERE u.user_id = ?");
$stmt->bind_param("i", $_SESSION['user_id']);
$stmt->execute();
$me = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$me) {
    header("Location: leave.php?err=" . urlencode("Your account is not linked to a student record."));
    exit();
}

$error = "";
$today = date("Y-m-d");
$min_date = date("Y-m-d", strtotime("-7 days"));

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $from   = trim($_POST['from_date'] ?? '');
    $to     = trim($_POST['to_date'] ?? '');
    $reason = trim($_POST['reason'] ?? '');

    if (empty($from) || empty($to) || empty($reason)) {
        $error = "All fields are required.";
    } elseif (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $from) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $to)) {
        $error = "Please choose valid dates.";
    } elseif ($from > $to) {
        $error = "The 'From' date cannot be after the 'To' date.";
    } elseif ($from < $min_date) {
        $error = "You can only apply for leave starting from the last 7 days.";
    } elseif ((strtotime($to) - strtotime($from)) / 86400 >= 30) {
        $error = "A leave request can be at most 30 days.";
    } elseif (strlen($reason) < 5 || strlen($reason) > 255) {
        $error = "Please write a reason between 5 and 255 characters.";
    } else {
        // The new dates must not overlap another pending or approved request
        $check = $conn->prepare("SELECT leave_id FROM leave_requests
                                 WHERE student_id = ? AND status IN ('Pending', 'Approved')
                                   AND from_date <= ? AND to_date >= ?");
        $check->bind_param("iss", $me['student_id'], $to, $from);
        $check->execute();

        if ($check->get_result()->num_rows > 0) {
            $error = "You already have a pending or approved leave request on these dates.";
        } else {
            $stmt = $conn->prepare("INSERT INTO leave_requests (student_id, from_date, to_date, reason) VALUES (?, ?, ?, ?)");
            $stmt->bind_param("isss", $me['student_id'], $from, $to, $reason);

            if ($stmt->execute()) {
                // Tell every teacher and admin
                $new_id = $stmt->insert_id;
                $text = $me['full_name'] . " applied for leave (" . date("d M", strtotime($from)) . " - " . date("d M", strtotime($to)) . ")";
                notify_staff($conn, $text, "leave/view_leave.php?id=" . $new_id);

                header("Location: leave.php?msg=" . urlencode("Leave request sent. Please wait for approval."));
                exit();
            } else {
                $error = "Could not send the request. Please try again.";
            }
        }
        $check->close();
    }
}

$page_title = "Apply for Leave";
$page_sub   = "Your teacher or the administrator will review it.";
$active     = "leave";
include("../users/header.php");
?>

<div class="row">
    <div class="col-lg-7">
        <a href="leave.php" class="text-decoration-none text-muted small"><i class="bi bi-arrow-left me-1"></i> Back to My Requests</a>
        <h4 class="fw-bold mt-2 mb-3">New Leave Request</h4>

        <?php if ($error): ?>
            <div class="alert alert-danger border-0 shadow-sm mb-4"><i class="bi bi-exclamation-triangle-fill me-2"></i><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

        <div class="panel-card p-4 shadow-sm">
            <form action="apply_leave.php" method="POST" class="needs-validation" novalidate>
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label class="form-label fw-medium">From</label>
                        <input type="date" name="from_date" id="fromDate" class="form-control" min="<?= $min_date ?>" value="<?= htmlspecialchars($_POST['from_date'] ?? $today) ?>" required>
                        <div class="invalid-feedback">Please choose the first day.</div>
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="form-label fw-medium">To</label>
                        <input type="date" name="to_date" id="toDate" class="form-control" min="<?= $min_date ?>" value="<?= htmlspecialchars($_POST['to_date'] ?? $today) ?>" required>
                        <div class="invalid-feedback">Please choose the last day.</div>
                    </div>
                </div>

                <div class="mb-1">
                    <label class="form-label fw-medium">Reason</label>
                    <textarea name="reason" id="reason" class="form-control" rows="4" minlength="5" maxlength="255" placeholder="e.g. Fever, doctor advised rest" required><?= htmlspecialchars($_POST['reason'] ?? '') ?></textarea>
                    <div class="invalid-feedback">Please write a reason (at least 5 characters).</div>
                </div>
                <div class="text-end small text-muted mb-4"><span id="charCount">0</span> / 255</div>

                <div class="d-flex justify-content-end gap-2">
                    <a href="leave.php" class="btn btn-light border px-4">Cancel</a>
                    <button type="submit" class="btn btn-primary px-4"><i class="bi bi-send me-1"></i> Send Request</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
    // Character counter for the reason box
    var reason = document.getElementById('reason');
    var charCount = document.getElementById('charCount');
    function updateCount() { charCount.textContent = reason.value.length; }
    reason.addEventListener('input', updateCount);
    updateCount();

    // The "To" date can never be before the "From" date
    var fromDate = document.getElementById('fromDate');
    var toDate = document.getElementById('toDate');
    function syncDates() {
        toDate.min = fromDate.value;
        if (toDate.value < fromDate.value) { toDate.value = fromDate.value; }
    }
    fromDate.addEventListener('change', syncDates);
    syncDates();
</script>

<?php include("../users/footer.php"); ?>