<?php
session_start();
include("dbconnection.php");

if (!isset($_SESSION['user_id'])) {
    header("Location: ../users/index.php");
    exit();
}

// Only the administrator can see teacher profiles
$user_role = strtolower($_SESSION['role'] ?? 'student');
if ($user_role !== 'admin' && $user_role !== 'administrator') {
    header("Location: ../users/dashboard.php");
    exit();
}

$id = $_GET['id'] ?? null;

if (!$id) {
    header("Location: teachers.php?err=Invalid Teacher ID.");
    exit();
}

$stmt = $conn->prepare("SELECT * FROM teachers WHERE teacher_id = ?");
$stmt->bind_param("i", $id);
$stmt->execute();
$teacher = $stmt->get_result()->fetch_assoc();

if (!$teacher) {
    header("Location: teachers.php?err=Teacher record not found.");
    exit();
}

$page_title = "Teacher Profile";
$page_sub   = "Details of the selected teacher.";
$active     = "teachers";
include("../users/header.php");
?>

<div class="row">
    <div class="col-lg-7">
        <a href="teachers.php" class="text-decoration-none text-muted small"><i class="bi bi-arrow-left me-1"></i> Back to Directory</a>
        <h4 class="fw-bold mt-2 mb-3">Teacher Profile</h4>

        <div class="panel-card p-4 shadow-sm">
            <div class="d-flex align-items-center gap-3 mb-4 pb-3 border-bottom">
                <div class="bg-primary-subtle text-primary rounded-circle d-flex align-items-center justify-content-center" style="width: 64px; height: 64px; font-size: 1.8rem;">
                    <i class="bi bi-person-badge"></i>
                </div>
                <div>
                    <h5 class="fw-bold mb-0"><?= htmlspecialchars($teacher['full_name']) ?></h5>
                    <span class="badge bg-primary-subtle text-primary border mt-1"><?= htmlspecialchars($teacher['subject']) ?></span>
                </div>
            </div>

            <div class="row g-3">
                <div class="col-sm-6">
                    <label class="text-muted small d-block">Teacher ID</label>
                    <span class="fw-medium">#<?= htmlspecialchars($teacher['teacher_id']) ?></span>
                </div>
                <div class="col-sm-6">
                    <label class="text-muted small d-block">Email Address</label>
                    <span class="fw-medium"><?= htmlspecialchars($teacher['email']) ?></span>
                </div>
            </div>

            <div class="d-flex justify-content-end gap-2 mt-4 pt-3 border-top">
                <a href="update_teacher.php?id=<?= $teacher['teacher_id'] ?>" class="btn btn-warning px-3"><i class="bi bi-pencil me-1"></i> Edit Profile</a>
            </div>
        </div>
    </div>
</div>

<?php include("../users/footer.php"); ?>
