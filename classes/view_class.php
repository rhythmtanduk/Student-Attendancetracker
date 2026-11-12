<?php
session_start();
include("dbconnection.php");

if (!isset($_SESSION['user_id'])) {
    header("Location: ../users/index.php");
    exit();
}

// Admin and teacher can see class details (only the admin can change things)
$user_role = strtolower($_SESSION['role'] ?? 'student');
$is_admin  = ($user_role === 'admin' || $user_role === 'administrator');
if (!$is_admin && $user_role !== 'teacher') {
    header("Location: ../users/dashboard.php");
    exit();
}

$id = isset($_GET['id']) ? intval($_GET['id']) : 0;

$stmt = $conn->prepare("SELECT class_id, class_name, section FROM classes WHERE class_id = ?");
$stmt->bind_param("i", $id);
$stmt->execute();
$class = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$class) {
    header("Location: classes.php?err=Class not found.");
    exit();
}

// Students of this class
$stmt = $conn->prepare("SELECT student_id, roll_no, full_name, email FROM students WHERE class_id = ? ORDER BY CAST(roll_no AS UNSIGNED), roll_no");
$stmt->bind_param("i", $id);
$stmt->execute();
$students = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// Subjects of this class (with the teacher's name)
$stmt = $conn->prepare("SELECT sb.subject_id, sb.subject_name, t.full_name AS teacher_name
                        FROM subjects sb
                        LEFT JOIN teachers t ON t.teacher_id = sb.teacher_id
                        WHERE sb.class_id = ? ORDER BY sb.subject_name");
$stmt->bind_param("i", $id);
$stmt->execute();
$subjects = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

$page_title = "Class " . $class['class_name'] . " - " . $class['section'];
$page_sub   = "Students and subjects of this class.";
$active     = "classes";
include("../users/header.php");
?>

<a href="classes.php" class="text-decoration-none text-muted small"><i class="bi bi-arrow-left me-1"></i> Back to Classes</a>

<div class="d-flex justify-content-between align-items-center my-3">
    <h4 class="fw-bold mb-0">Class <?= htmlspecialchars($class['class_name']) ?> - Section <?= htmlspecialchars($class['section']) ?></h4>
    <?php if ($is_admin): ?>
        <a href="update_class.php?id=<?= $class['class_id'] ?>" class="btn btn-warning px-3"><i class="bi bi-pencil me-1"></i> Edit Class</a>
    <?php endif; ?>
</div>

<div class="row g-3">
    <!-- Students -->
    <div class="col-lg-7">
        <div class="panel-card shadow-sm h-100">
            <div class="p-3 border-bottom fw-bold">Students (<?= count($students) ?>)</div>
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light"><tr><th class="ps-3">Roll No</th><th>Name</th><th>Email</th></tr></thead>
                    <tbody>
                        <?php if (empty($students)): ?>
                            <tr><td colspan="3" class="text-center text-muted py-4">No students in this class yet.</td></tr>
                        <?php else: ?>
                            <?php foreach ($students as $s): ?>
                                <tr>
                                    <td class="ps-3"><code><?= htmlspecialchars($s['roll_no']) ?></code></td>
                                    <td class="fw-semibold">
                                        <?php if ($is_admin): ?>
                                            <a href="../student/read_student.php?id=<?= $s['student_id'] ?>" class="text-decoration-none text-dark"><?= htmlspecialchars($s['full_name']) ?></a>
                                        <?php else: ?>
                                            <?= htmlspecialchars($s['full_name']) ?>
                                        <?php endif; ?>
                                    </td>                                    
                                    <td class="text-muted small"><?= htmlspecialchars($s['email']) ?></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Subjects -->
    <div class="col-lg-5">
        <div class="panel-card shadow-sm h-100">
            <div class="p-3 border-bottom d-flex justify-content-between align-items-center">
                <span class="fw-bold">Subjects (<?= count($subjects) ?>)</span>
                <?php if ($is_admin): ?>
                    <a href="../subject/add_subject.php?class_id=<?= $class['class_id'] ?>" class="btn btn-sm btn-outline-primary"><i class="bi bi-plus-lg"></i> Add</a>
                <?php endif; ?>
            </div>
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light"><tr><th class="ps-3">Subject</th><th>Teacher</th></tr></thead>
                    <tbody>
                        <?php if (empty($subjects)): ?>
                            <tr><td colspan="2" class="text-center text-muted py-4">No subjects yet.</td></tr>
                        <?php else: ?>
                            <?php foreach ($subjects as $sb): ?>
                                <tr>
                                    <td class="ps-3 fw-semibold"><?= htmlspecialchars($sb['subject_name']) ?></td>
                                    <td class="text-muted small"><?= $sb['teacher_name'] ? htmlspecialchars($sb['teacher_name']) : 'Not assigned' ?></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<?php include("../users/footer.php"); ?>