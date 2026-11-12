<?php
session_start();
include("dbconnection.php");
include("../users/log_activity.php");

if (!isset($_SESSION['user_id'])) {
    header("Location: ../users/index.php");
    exit();
}

// Only admin and teacher can edit reports
$user_role = strtolower($_SESSION['role'] ?? 'student');
if ($user_role !== 'admin' && $user_role !== 'administrator' && $user_role !== 'teacher') {
    header("Location: ../users/dashboard.php");
    exit();
}

$error = "";
$today = date("Y-m-d");
$id = isset($_GET['id']) ? intval($_GET['id']) : (isset($_POST['id']) ? intval($_POST['id']) : 0);

// Fetch the report
$stmt = $conn->prepare("SELECT r.report_id, r.student_id, r.report_date, r.from_date, r.to_date,
                               r.total_days, r.attended_days, r.attendance_percentage, r.remarks,
                               s.roll_no, s.full_name, s.class, s.section
                        FROM reports r
                        JOIN students s ON s.student_id = r.student_id
                        WHERE r.report_id = ?");
$stmt->bind_param("i", $id);
$stmt->execute();
$report = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$report) {
    header("Location: report.php?err=" . urlencode("Report not found."));
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $remarks = trim($_POST['remarks'] ?? '');
    $recalc  = isset($_POST['recalc']);

    if (strlen($remarks) > 255) {
        $error = "Remarks must be 255 characters or less.";
    } else {
        if ($remarks === '') { $remarks = null; }

        if ($recalc) {
            // Count the attendance again for the same student and period
            $live = $conn->prepare("SELECT COUNT(*) AS total, COALESCE(SUM(status IN ('Present','Late')), 0) AS attended
                                    FROM attendance
                                    WHERE student_id = ? AND attendance_date BETWEEN ? AND ?");
            $live->bind_param("iss", $report['student_id'], $report['from_date'], $report['to_date']);
            $live->execute();
            $now = $live->get_result()->fetch_assoc();
            $live->close();

            if ($now['total'] == 0) {
                $error = "No attendance found in this period, so it cannot be recalculated.";
            } else {
                $percent = round($now['attended'] * 100 / $now['total'], 2);
                $update = $conn->prepare("UPDATE reports SET total_days = ?, attended_days = ?, attendance_percentage = ?, report_date = ?, remarks = ? WHERE report_id = ?");
                $update->bind_param("iidssi", $now['total'], $now['attended'], $percent, $today, $remarks, $id);
            }
        } else {
            // Only the remarks change
            $update = $conn->prepare("UPDATE reports SET remarks = ? WHERE report_id = ?");
            $update->bind_param("si", $remarks, $id);
        }

        if ($error == "") {
            if ($update->execute()) {
                log_activity($conn, "Edited report #" . $id . " of " . $report['full_name']);
                header("Location: report.php?msg=" . urlencode("Report updated successfully."));
                exit();
            } else {
                $error = "Could not update the report. Please try again.";
            }
            $update->close();
        }
    }
}

$page_title = "Edit Report";
$page_sub   = "Change the remarks or recalculate the percentage.";
$active     = "report";
include("../users/header.php");
?>

<div class="row">
    <div class="col-lg-7">
        <a href="report.php" class="text-decoration-none text-muted small"><i class="bi bi-arrow-left me-1"></i> Back to Reports</a>
        <h4 class="fw-bold mt-2 mb-3">Edit Report</h4>

        <?php if ($error): ?>
            <div class="alert alert-danger border-0 shadow-sm mb-4"><i class="bi bi-exclamation-triangle-fill me-2"></i><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

        <div class="panel-card p-4 shadow-sm">
            <!-- Student and period cannot be changed here -->
            <div class="mb-4 pb-3 border-bottom">
                <div class="fw-bold"><?= htmlspecialchars($report['full_name']) ?></div>
                <small class="text-muted">
                    Roll <?= htmlspecialchars($report['roll_no']) ?> &middot; Class <?= htmlspecialchars($report['class']) ?>-<?= htmlspecialchars($report['section']) ?>
                    &middot; <?= date("d M Y", strtotime($report['from_date'])) ?> to <?= date("d M Y", strtotime($report['to_date'])) ?>
                </small>
                <div class="mt-2">
                    <span class="badge <?= $report['attendance_percentage'] < 75 ? 'bg-danger' : 'bg-success' ?>"><?= $report['attendance_percentage'] ?>%</span>
                    <small class="text-muted ms-1">(<?= $report['attended_days'] ?> of <?= $report['total_days'] ?> days)</small>
                </div>
            </div>

            <form action="update_report.php" method="POST" class="needs-validation" novalidate>
                <input type="hidden" name="id" value="<?= $report['report_id'] ?>">

                <div class="mb-3">
                    <label class="form-label fw-medium">Remarks</label>
                    <input type="text" name="remarks" class="form-control" maxlength="255" value="<?= htmlspecialchars($report['remarks'] ?? '') ?>" placeholder="e.g. Monthly report for October">
                </div>

                <div class="form-check mb-4">
                    <input class="form-check-input" type="checkbox" name="recalc" id="recalc">
                    <label class="form-check-label" for="recalc">
                        Recalculate the percentage from the current attendance records
                    </label>
                </div>

                <div class="d-flex justify-content-end gap-2">
                    <a href="report.php" class="btn btn-light border px-4">Cancel</a>
                    <button type="submit" class="btn btn-primary px-4"><i class="bi bi-check-lg me-1"></i> Update Report</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php include("../users/footer.php"); ?>