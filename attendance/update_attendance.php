<?php
session_start();
include("dbconnection.php");
include("../users/log_activity.php");

if (!isset($_SESSION['user_id'])) {
    header("Location: ../users/index.php");
    exit();
}

// Only admin and teacher can edit attendance
$user_role = strtolower($_SESSION['role'] ?? 'student');
if ($user_role !== 'admin' && $user_role !== 'administrator' && $user_role !== 'teacher') {
    header("Location: ../users/dashboard.php");
    exit();
}

$error = "";
$today = date("Y-m-d");
$id = isset($_GET['id']) ? intval($_GET['id']) : (isset($_POST['id']) ? intval($_POST['id']) : 0);

// Fetch the record
$stmt = $conn->prepare("SELECT a.attendance_id, a.student_id, a.attendance_date, a.status,
                               s.roll_no, s.full_name, s.class, s.section
                        FROM attendance a
                        JOIN students s ON s.student_id = a.student_id
                        WHERE a.attendance_id = ?");
$stmt->bind_param("i", $id);
$stmt->execute();
$record = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$record) {
    header("Location: attendance.php?err=" . urlencode("Attendance record not found."));
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $date   = trim($_POST['date'] ?? '');
    $status = trim($_POST['status'] ?? '');

    if (empty($date) || empty($status)) {
        $error = "All fields are required.";
    } elseif (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) || !in_array($status, ['Present', 'Late', 'Absent'])) {
        $error = "Invalid date or status.";
    } elseif ($date > $today) {
        $error = "You cannot use a future date.";
    } else {
        // The same student cannot have two records on the same date
        $check = $conn->prepare("SELECT attendance_id FROM attendance WHERE student_id = ? AND attendance_date = ? AND attendance_id != ?");
        $check->bind_param("isi", $record['student_id'], $date, $id);
        $check->execute();

        if ($check->get_result()->num_rows > 0) {
            $error = "This student already has an attendance record on that date.";
        } else {
            $update = $conn->prepare("UPDATE attendance SET attendance_date = ?, status = ? WHERE attendance_id = ?");
            $update->bind_param("ssi", $date, $status, $id);

            if ($update->execute()) {
                log_activity($conn, "Edited attendance: " . $record['full_name'] . " on " . $date . " set to " . $status);
                header("Location: attendance.php?date=" . $date . "&msg=" . urlencode("Attendance record updated successfully."));
                exit();
            } else {
                $error = "Could not update the record. Please try again.";
            }
            $update->close();
        }
        $check->close();
    }
}

$page_title = "Edit Attendance";
$page_sub   = "Change the date or status of this record.";
$active     = "attendance";
include("../users/header.php");
?>

<div class="row">
    <div class="col-lg-7">
        <a href="attendance.php?date=<?= $record['attendance_date'] ?>" class="text-decoration-none text-muted small"><i class="bi bi-arrow-left me-1"></i> Back to Records</a>
        <h4 class="fw-bold mt-2 mb-3">Edit Attendance Record</h4>

        <?php if ($error): ?>
            <div class="alert alert-danger border-0 shadow-sm mb-4"><i class="bi bi-exclamation-triangle-fill me-2"></i><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

        <div class="panel-card p-4 shadow-sm">
            <!-- Student details cannot be changed here -->
            <div class="mb-4 pb-3 border-bottom">
                <div class="fw-bold"><?= htmlspecialchars($record['full_name']) ?></div>
                <small class="text-muted">Roll <?= htmlspecialchars($record['roll_no']) ?> &middot; Class <?= htmlspecialchars($record['class']) ?>-<?= htmlspecialchars($record['section']) ?></small>
            </div>

            <form action="update_attendance.php" method="POST" class="needs-validation" novalidate>
                <input type="hidden" name="id" value="<?= $record['attendance_id'] ?>">

                <div class="mb-3">
                    <label class="form-label fw-medium">Date</label>
                    <input type="date" name="date" class="form-control" value="<?= htmlspecialchars($record['attendance_date']) ?>" max="<?= $today ?>" required>
                    <div class="invalid-feedback">Please choose a date.</div>
                </div>

                <div class="mb-4">
                    <label class="form-label fw-medium d-block">Status</label>
                    <div class="btn-group">
                        <input type="radio" class="btn-check" name="status" id="sp" value="Present" <?= $record['status'] == 'Present' ? 'checked' : '' ?>>
                        <label class="btn btn-outline-success" for="sp">Present</label>

                        <input type="radio" class="btn-check" name="status" id="sl" value="Late" <?= $record['status'] == 'Late' ? 'checked' : '' ?>>
                        <label class="btn btn-outline-warning" for="sl">Late</label>

                        <input type="radio" class="btn-check" name="status" id="sa" value="Absent" <?= $record['status'] == 'Absent' ? 'checked' : '' ?>>
                        <label class="btn btn-outline-danger" for="sa">Absent</label>
                    </div>
                </div>

                <div class="d-flex justify-content-end gap-2">
                    <a href="attendance.php?date=<?= $record['attendance_date'] ?>" class="btn btn-light border px-4">Cancel</a>
                    <button type="submit" class="btn btn-primary px-4"><i class="bi bi-check-lg me-1"></i> Update Record</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php include("../users/footer.php"); ?>