<?php
session_start();
include("dbconnection.php");

if (!isset($_SESSION['user_id'])) {
    header("Location: ../users/index.php");
    exit();
}

// Only the administrator can edit teachers
$user_role = strtolower($_SESSION['role'] ?? 'student');
if ($user_role !== 'admin' && $user_role !== 'administrator') {
    header("Location: ../users/dashboard.php");
    exit();
}

$error = '';
$id = $_GET['id'] ?? $_POST['id'] ?? null;

if (!$id) {
    header("Location: teachers.php?err=Invalid ID.");
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name'] ?? '');
    $subject = trim($_POST['subject'] ?? '');
    $email = trim($_POST['email'] ?? '');

    if (empty($name) || empty($subject) || empty($email)) {
        $error = "All fields are required.";
    } else {
        // Check that another teacher does not already use this email
        $check = $conn->prepare("SELECT teacher_id FROM teachers WHERE email = ? AND teacher_id != ?");
        $check->bind_param("si", $email, $id);
        $check->execute();

        if ($check->get_result()->num_rows > 0) {
            $error = "Another teacher already uses this email.";
        } else {
            $stmt = $conn->prepare("UPDATE teachers SET full_name = ?, subject = ?, email = ? WHERE teacher_id = ?");
            $stmt->bind_param("sssi", $name, $subject, $email, $id);

            if ($stmt->execute()) {
                header("Location: teachers.php?msg=Teacher updated successfully.");
                exit();
            } else {
                $error = "Could not update the teacher. Please try again.";
            }
        }
        $check->close();
    }
}

$stmt = $conn->prepare("SELECT * FROM teachers WHERE teacher_id = ?");
$stmt->bind_param("i", $id);
$stmt->execute();
$teacher = $stmt->get_result()->fetch_assoc();

if (!$teacher) {
    header("Location: teachers.php?err=Teacher not found.");
    exit();
}

$page_title = "Edit Teacher";
$page_sub   = "Update the teacher's details.";
$active     = "teachers";
include("../users/header.php");
?>

<div class="row">
    <div class="col-lg-7">
        <a href="teachers.php" class="text-decoration-none text-muted small"><i class="bi bi-arrow-left me-1"></i> Back to Directory</a>
        <h4 class="fw-bold mt-2 mb-3">Edit Teacher Profile</h4>

        <?php if ($error): ?>
            <div class="alert alert-danger border-0 shadow-sm mb-4"><i class="bi bi-exclamation-triangle-fill me-2"></i><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

        <div class="panel-card p-4 shadow-sm">
            <form action="update_teacher.php" method="POST" class="needs-validation" novalidate>
                <input type="hidden" name="id" value="<?= htmlspecialchars($teacher['teacher_id']) ?>">
                <div class="mb-3">
                    <label class="form-label fw-medium">Full Name</label>
                    <input type="text" name="name" class="form-control" value="<?= htmlspecialchars($teacher['full_name']) ?>" required>
                    <div class="invalid-feedback">Please enter the teacher's name.</div>
                </div>
                <div class="mb-3">
                    <label class="form-label fw-medium">Assigned Subject</label>
                    <input type="text" name="subject" class="form-control" value="<?= htmlspecialchars($teacher['subject']) ?>" required>
                    <div class="invalid-feedback">Please enter the subject.</div>
                </div>
                <div class="mb-4">
                    <label class="form-label fw-medium">Email Address</label>
                    <input type="email" name="email" class="form-control" value="<?= htmlspecialchars($teacher['email']) ?>" required>
                    <div class="invalid-feedback">Please enter a valid email.</div>
                </div>
                <div class="d-flex justify-content-end gap-2">
                    <a href="teachers.php" class="btn btn-light border px-4">Cancel</a>
                    <button type="submit" class="btn btn-primary px-4"><i class="bi bi-check-lg me-1"></i> Update Profile</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php include("../users/footer.php"); ?>
