<?php
session_start();
include("dbconnection.php");

if (!isset($_SESSION['user_id'])) {
    header("Location: ../users/index.php");
    exit();
}

// Only admin and teacher can see attendance records
$user_role = strtolower($_SESSION['role'] ?? 'student');
if ($user_role !== 'admin' && $user_role !== 'administrator' && $user_role !== 'teacher') {
    header("Location: ../users/dashboard.php");
    exit();
}

$id = isset($_GET['id']) ? intval($_GET['id']) : 0;

$stmt = $conn->prepare("SELECT a.attendance_id, a.student_id, a.attendance_date, a.status,
                               s.roll_no, s.full_name, s.class, s.section,
                               t.full_name AS teacher_name
                        FROM attendance a
                        JOIN students s ON s.student_id = a.student_id
                        LEFT JOIN teachers t ON t.teacher_id = a.teacher_id
                        WHERE a.attendance_id = ?");
$stmt->bind_param("i", $id);
$stmt->execute();
$record = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$record) {
    header("Location: attendance.php?err=" . urlencode("Attendance record not found."));
    exit();
}

// This student's overall attendance
$sum = $conn->query("SELECT COUNT(*) AS total, SUM(status IN ('Present','Late')) AS ok FROM attendance WHERE student_id = " . intval($record['student_id']))->fetch_assoc();
$percent = $sum['total'] > 0 ? round($sum['ok'] * 100 / $sum['total'], 1) : 0;

$colors = ['Present' => 'success', 'Late' => 'warning', 'Absent' => 'danger'];

$page_title = "Attendance Record";
$page_sub   = "Details of the selected record.";
$active     = "attendance";
include("../users/header.php");
?>

<div class="row">
    <div class="col-lg-7">
        <a href="attendance.php?date=<?= $record['attendance_date'] ?>" class="text-decoration-none text-muted small"><i class="bi bi-arrow-left me-1"></i> Back to Records</a>
        <h4 class="fw-bold mt-2 mb-3">Attendance Record</h4>

        <div class="panel-card p-4 shadow-sm">
            <div class="d-flex align-items-center gap-3 mb-4 pb-3 border-bottom">
                <div class="bg-primary-subtle text-primary rounded-circle d-flex align-items-center justify-content-center" style="width: 64px; height: 64px; font-size: 1.8rem;">
                    <i class="bi bi-calendar-check"></i>
                </div>
                <div>
                    <h5 class="fw-bold mb-0"><?= htmlspecialchars($record['full_name']) ?></h5>
                    <span class="badge bg-<?= $colors[$record['status']] ?? 'secondary' ?> mt-1"><?= htmlspecialchars($record['status']) ?></span>
                </div>
            </div>

            <div class="row g-3">
                <div class="col-sm-6">
                    <label class="text-muted small d-block">Record ID</label>
                    <span class="fw-medium">#<?= $record['attendance_id'] ?></span>
                </div>
                <div class="col-sm-6">
                    <label class="text-muted small d-block">Date</label>
                    <span class="fw-medium"><?= date("l, d M Y", strtotime($record['attendance_date'])) ?></span>
                </div>
                <div class="col-sm-6">
                    <label class="text-muted small d-block">Roll Number</label>
                    <span class="fw-medium"><code><?= htmlspecialchars($record['roll_no']) ?></code></span>
                </div>
                <div class="col-sm-6">
                    <label class="text-muted small d-block">Class</label>
                    <span class="fw-medium"><?= htmlspecialchars($record['class']) ?> - <?= htmlspecialchars($record['section']) ?></span>
                </div>
                <div class="col-sm-6">
                    <label class="text-muted small d-block">Marked By</label>
                    <span class="fw-medium"><?= $record['teacher_name'] ? htmlspecialchars($record['teacher_name']) : 'Admin' ?></span>
                </div>
                <div class="col-sm-6">
                    <label class="text-muted small d-block">Overall Attendance of Student</label>
                    <span class="badge <?= $percent < 75 ? 'bg-danger' : 'bg-success' ?>"><?= $percent ?>%</span>
                    <small class="text-muted">(<?= (int)$sum['ok'] ?> of <?= (int)$sum['total'] ?> days)</small>
                </div>
            </div>

            <div class="d-flex justify-content-end gap-2 mt-4 pt-3 border-top">
                <a href="update_attendance.php?id=<?= $record['attendance_id'] ?>" class="btn btn-warning px-3"><i class="bi bi-pencil me-1"></i> Edit Record</a>
            </div>
        </div>
    </div>
</div>

<?php include("../users/footer.php"); ?>