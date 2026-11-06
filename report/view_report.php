<?php
session_start();
include("dbconnection.php");

if (!isset($_SESSION['user_id'])) {
    header("Location: ../users/index.php");
    exit();
}

// Only admin and teacher can see reports
$user_role = strtolower($_SESSION['role'] ?? 'student');
if ($user_role !== 'admin' && $user_role !== 'administrator' && $user_role !== 'teacher') {
    header("Location: ../users/dashboard.php");
    exit();
}

$id = isset($_GET['id']) ? intval($_GET['id']) : 0;

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

// Attendance of the same student and period RIGHT NOW (to check if the saved report is outdated)
$live = $conn->prepare("SELECT COUNT(*) AS total, COALESCE(SUM(status IN ('Present','Late')), 0) AS attended
                        FROM attendance
                        WHERE student_id = ? AND attendance_date BETWEEN ? AND ?");
$live->bind_param("iss", $report['student_id'], $report['from_date'], $report['to_date']);
$live->execute();
$now = $live->get_result()->fetch_assoc();
$live->close();

$outdated = ($now['total'] != $report['total_days'] || $now['attended'] != $report['attended_days']);
$absent   = $report['total_days'] - $report['attended_days'];

$page_title = "Report Details";
$page_sub   = "Details of the selected report.";
$active     = "report";
include("../users/header.php");
?>

<div class="row">
    <div class="col-lg-7">
        <a href="report.php" class="text-decoration-none text-muted small"><i class="bi bi-arrow-left me-1"></i> Back to Reports</a>
        <h4 class="fw-bold mt-2 mb-3">Attendance Report</h4>

        <?php if ($outdated): ?>
            <div class="alert alert-warning border-0 shadow-sm">
                <i class="bi bi-exclamation-circle-fill me-2"></i>
                Attendance has changed since this report was saved. Click "Edit Report" and tick "Recalculate" to update it.
            </div>
        <?php endif; ?>

        <div class="panel-card p-4 shadow-sm">
            <div class="d-flex align-items-center gap-3 mb-4 pb-3 border-bottom">
                <div class="bg-primary-subtle text-primary rounded-circle d-flex align-items-center justify-content-center" style="width: 64px; height: 64px; font-size: 1.8rem;">
                    <i class="bi bi-bar-chart"></i>
                </div>
                <div>
                    <h5 class="fw-bold mb-0"><?= htmlspecialchars($report['full_name']) ?></h5>
                    <span class="badge <?= $report['attendance_percentage'] < 75 ? 'bg-danger' : 'bg-success' ?> mt-1"><?= $report['attendance_percentage'] ?>% attendance</span>
                </div>
            </div>

            <div class="row g-3">
                <div class="col-sm-6">
                    <label class="text-muted small d-block">Report ID</label>
                    <span class="fw-medium">#<?= $report['report_id'] ?></span>
                </div>
                <div class="col-sm-6">
                    <label class="text-muted small d-block">Saved On</label>
                    <span class="fw-medium"><?= date("d M Y", strtotime($report['report_date'])) ?></span>
                </div>
                <div class="col-sm-6">
                    <label class="text-muted small d-block">Roll Number</label>
                    <span class="fw-medium"><code><?= htmlspecialchars($report['roll_no']) ?></code></span>
                </div>
                <div class="col-sm-6">
                    <label class="text-muted small d-block">Class</label>
                    <span class="fw-medium"><?= htmlspecialchars($report['class']) ?> - <?= htmlspecialchars($report['section']) ?></span>
                </div>
                <div class="col-sm-12">
                    <label class="text-muted small d-block">Period</label>
                    <span class="fw-medium"><?= date("d M Y", strtotime($report['from_date'])) ?> to <?= date("d M Y", strtotime($report['to_date'])) ?></span>
                </div>
                <div class="col-4">
                    <label class="text-muted small d-block">Total Days</label>
                    <span class="fw-medium"><?= $report['total_days'] ?></span>
                </div>
                <div class="col-4">
                    <label class="text-muted small d-block">Attended</label>
                    <span class="fw-medium text-success"><?= $report['attended_days'] ?></span>
                </div>
                <div class="col-4">
                    <label class="text-muted small d-block">Absent</label>
                    <span class="fw-medium text-danger"><?= $absent ?></span>
                </div>
                <div class="col-12">
                    <label class="text-muted small d-block">Remarks</label>
                    <span class="fw-medium"><?= $report['remarks'] ? htmlspecialchars($report['remarks']) : '-' ?></span>
                </div>
            </div>

            <div class="d-flex justify-content-end gap-2 mt-4 pt-3 border-top">
                <a href="update_report.php?id=<?= $report['report_id'] ?>" class="btn btn-warning px-3"><i class="bi bi-pencil me-1"></i> Edit Report</a>
            </div>
        </div>
    </div>
</div>

<?php include("../users/footer.php"); ?>