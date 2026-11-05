<?php
session_start();
include("dbconnection.php");

if (!isset($_SESSION['user_id'])) {
    header("Location: ../users/index.php");
    exit();
}

// Only the administrator can see student profiles
$user_role = strtolower($_SESSION['role'] ?? 'student');
if ($user_role !== 'admin' && $user_role !== 'administrator') {
    header("Location: ../users/dashboard.php");
    exit();
}

$id = isset($_GET['id']) ? intval($_GET['id']) : 0;

$stmt = $conn->prepare("SELECT student_id, roll_no, full_name, class, section, email FROM students WHERE student_id = ?");
$stmt->bind_param("i", $id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    header("Location: students.php?err=" . urlencode("Student not found."));
    exit();
}

$student = $result->fetch_assoc();
$stmt->close();

// Attendance summary of this student
$sum = $conn->query("SELECT COUNT(*) AS total, SUM(status IN ('Present','Late')) AS ok FROM attendance WHERE student_id = " . intval($student['student_id']))->fetch_assoc();
$total   = (int)$sum['total'];
$percent = $total > 0 ? round($sum['ok'] * 100 / $total, 1) : 0;

$page_title = "Student Profile";
$page_sub   = "Details and attendance summary.";
$active     = "students";
include("../users/header.php");
?>

<div class="row">
    <div class="col-lg-7">
        <a href="students.php" class="text-decoration-none text-muted small"><i class="bi bi-arrow-left me-1"></i> Back to List</a>

        <div class="panel-card shadow-sm p-4 mt-2">
            <table class="table table-borderless mb-0">
                <tr>
                    <th class="text-muted" style="width: 35%;">Student ID:</th>
                    <td class="fw-bold">#<?= $student['student_id'] ?></td>
                </tr>
                <tr>
                    <th class="text-muted">Roll Number:</th>
                    <td><code><?= htmlspecialchars($student['roll_no']) ?></code></td>
                </tr>
                <tr>
                    <th class="text-muted">Full Name:</th>
                    <td><?= htmlspecialchars($student['full_name']) ?></td>
                </tr>
                <tr>
                    <th class="text-muted">Class:</th>
                    <td><?= htmlspecialchars($student['class']) ?></td>
                </tr>
                <tr>
                    <th class="text-muted">Section:</th>
                    <td><?= htmlspecialchars($student['section']) ?></td>
                </tr>
                <tr>
                    <th class="text-muted">Email:</th>
                    <td><?= htmlspecialchars($student['email']) ?></td>
                </tr>
                <tr>
                    <th class="text-muted">Attendance:</th>
                    <td>
                        <?php if ($total > 0): ?>
                            <span class="badge <?= $percent < 75 ? 'bg-danger' : 'bg-success' ?>"><?= $percent ?>%</span>
                            <small class="text-muted ms-1">(<?= (int)$sum['ok'] ?> of <?= $total ?> days)</small>
                        <?php else: ?>
                            <span class="text-muted">No attendance marked yet</span>
                        <?php endif; ?>
                    </td>
                </tr>
            </table>

            <div class="d-flex justify-content-between mt-4">
                <a href="students.php" class="btn btn-outline-secondary"><i class="bi bi-arrow-left me-1"></i> Back to List</a>
                <a href="update_student.php?id=<?= $student['student_id'] ?>" class="btn btn-warning"><i class="bi bi-pencil me-1"></i> Edit Details</a>
            </div>
        </div>
    </div>
</div>

<?php include("../users/footer.php"); ?>
