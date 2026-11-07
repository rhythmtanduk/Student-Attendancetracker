<?php
session_start();
include("dbconnection.php");

if (!isset($_SESSION['user_id'])) {
    header("Location: ../users/index.php");
    exit();
}

$user_role  = strtolower($_SESSION['role'] ?? 'student');
$is_staff   = ($user_role === 'admin' || $user_role === 'administrator' || $user_role === 'teacher');
$is_student = ($user_role === 'student');

if (!$is_staff && !$is_student) {
    header("Location: ../users/dashboard.php");
    exit();
}

$id = isset($_GET['id']) ? intval($_GET['id']) : 0;

$stmt = $conn->prepare("SELECT l.leave_id, l.student_id, l.from_date, l.to_date, l.reason, l.status, l.review_note, l.created_at,
                               s.roll_no, s.full_name, s.class, s.section,
                               u.full_name AS reviewer
                        FROM leave_requests l
                        JOIN students s ON s.student_id = l.student_id
                        LEFT JOIN users u ON u.user_id = l.reviewed_by
                        WHERE l.leave_id = ?");
$stmt->bind_param("i", $id);
$stmt->execute();
$leave = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$leave) {
    header("Location: leave.php?err=" . urlencode("Leave request not found."));
    exit();
}

// A student may only open their own requests
if ($is_student) {
    $stmt = $conn->prepare("SELECT s.student_id FROM students s JOIN users u ON u.email = s.email WHERE u.user_id = ?");
    $stmt->bind_param("i", $_SESSION['user_id']);
    $stmt->execute();
    $me = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$me || $me['student_id'] != $leave['student_id']) {
        header("Location: leave.php?err=" . urlencode("You can only view your own requests."));
        exit();
    }
}

$colors = ['Pending' => 'warning text-dark', 'Approved' => 'success', 'Rejected' => 'danger'];
$days = (strtotime($leave['to_date']) - strtotime($leave['from_date'])) / 86400 + 1;

$page_title = "Leave Request";
$page_sub   = "Details of the selected request.";
$active     = "leave";
include("../users/header.php");
?>

<div class="row">
    <div class="col-lg-7">
        <a href="leave.php" class="text-decoration-none text-muted small"><i class="bi bi-arrow-left me-1"></i> Back to Requests</a>
        <h4 class="fw-bold mt-2 mb-3">Leave Request</h4>

        <div class="panel-card p-4 shadow-sm">
            <div class="d-flex align-items-center gap-3 mb-4 pb-3 border-bottom">
                <div class="bg-primary-subtle text-primary rounded-circle d-flex align-items-center justify-content-center" style="width: 64px; height: 64px; font-size: 1.8rem;">
                    <i class="bi bi-envelope-paper"></i>
                </div>
                <div>
                    <h5 class="fw-bold mb-0"><?= htmlspecialchars($leave['full_name']) ?></h5>
                    <span class="badge bg-<?= $colors[$leave['status']] ?> mt-1"><?= $leave['status'] ?></span>
                </div>
            </div>

            <div class="row g-3">
                <div class="col-sm-6">
                    <label class="text-muted small d-block">Roll Number</label>
                    <span class="fw-medium"><code><?= htmlspecialchars($leave['roll_no']) ?></code></span>
                </div>
                <div class="col-sm-6">
                    <label class="text-muted small d-block">Class</label>
                    <span class="fw-medium"><?= htmlspecialchars($leave['class']) ?> - <?= htmlspecialchars($leave['section']) ?></span>
                </div>
                <div class="col-sm-6">
                    <label class="text-muted small d-block">From</label>
                    <span class="fw-medium"><?= date("l, d M Y", strtotime($leave['from_date'])) ?></span>
                </div>
                <div class="col-sm-6">
                    <label class="text-muted small d-block">To</label>
                    <span class="fw-medium"><?= date("l, d M Y", strtotime($leave['to_date'])) ?></span>
                </div>
                <div class="col-sm-6">
                    <label class="text-muted small d-block">Total Days</label>
                    <span class="fw-medium"><?= $days ?></span>
                </div>
                <div class="col-sm-6">
                    <label class="text-muted small d-block">Applied On</label>
                    <span class="fw-medium"><?= date("d M Y", strtotime($leave['created_at'])) ?></span>
                </div>
                <div class="col-12">
                    <label class="text-muted small d-block">Reason</label>
                    <span class="fw-medium"><?= nl2br(htmlspecialchars($leave['reason'])) ?></span>
                </div>

                <?php if ($leave['status'] != 'Pending'): ?>
                    <div class="col-sm-6">
                        <label class="text-muted small d-block"><?= $leave['status'] ?> By</label>
                        <span class="fw-medium"><?= $leave['reviewer'] ? htmlspecialchars($leave['reviewer']) : '-' ?></span>
                    </div>
                    <div class="col-sm-6">
                        <label class="text-muted small d-block">Note</label>
                        <span class="fw-medium"><?= $leave['review_note'] ? htmlspecialchars($leave['review_note']) : '-' ?></span>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Staff: approve or reject a pending request -->
            <?php if ($is_staff && $leave['status'] == 'Pending'): ?>
                <form action="review_leave.php" method="POST" class="mt-4 pt-3 border-top">
                    <input type="hidden" name="id" value="<?= $leave['leave_id'] ?>">
                    <label class="form-label fw-medium">Note (required when rejecting)</label>
                    <textarea name="note" id="note" class="form-control mb-2" rows="2" maxlength="255" placeholder="e.g. Please bring a doctor's letter"></textarea>
                    <div class="invalid-feedback">Please write why you are rejecting this request.</div>
                    <div class="d-flex justify-content-end gap-2 mt-3">
                        <button type="submit" name="decision" value="Rejected" id="rejectBtn" class="btn btn-outline-danger px-4"><i class="bi bi-x-lg me-1"></i> Reject</button>
                        <button type="submit" name="decision" value="Approved" class="btn btn-success px-4"><i class="bi bi-check-lg me-1"></i> Approve</button>
                    </div>
                </form>

                <script>
                    // Rejecting needs a note
                    document.getElementById('rejectBtn').addEventListener('click', function (e) {
                        var note = document.getElementById('note');
                        if (note.value.trim() === '') {
                            e.preventDefault();
                            note.classList.add('is-invalid');
                            note.focus();
                        }
                    });
                </script>
            <?php endif; ?>

            <!-- Student: edit or cancel own pending request -->
            <?php if ($is_student && $leave['status'] == 'Pending'): ?>
                <div class="d-flex justify-content-end gap-2 mt-4 pt-3 border-top">
                    <a href="delete_leave.php?id=<?= $leave['leave_id'] ?>" class="btn btn-outline-danger px-3"
                       onclick="return confirm('Cancel this leave request?');"><i class="bi bi-x-lg me-1"></i> Cancel Request</a>
                    <a href="update_leave.php?id=<?= $leave['leave_id'] ?>" class="btn btn-warning px-3"><i class="bi bi-pencil me-1"></i> Edit Request</a>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php include("../users/footer.php"); ?>