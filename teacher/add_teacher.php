<?php
session_start();
include("dbconnection.php");

// Auth check
if (!isset($_SESSION['user_id'])) {
    header("Location: ../users/index.php");
    exit();
}

// Only the administrator can add teachers
$user_role = strtolower($_SESSION['role'] ?? 'student');
if ($user_role !== 'admin' && $user_role !== 'administrator') {
    header("Location: ../users/dashboard.php");
    exit();
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name'] ?? '');
    $subject = trim($_POST['subject'] ?? '');
    $email = trim($_POST['email'] ?? '');

    if (empty($name) || empty($subject) || empty($email)) {
        $error = "All fields are required.";
    } else {
        // Check that the email is not already used by another teacher
        $check = $conn->prepare("SELECT teacher_id FROM teachers WHERE email = ?");
        $check->bind_param("s", $email);
        $check->execute();

        if ($check->get_result()->num_rows > 0) {
            $error = "A teacher with this email already exists.";
        } else {
            // teachers table columns: full_name, subject, email
            $stmt = $conn->prepare("INSERT INTO teachers (full_name, subject, email) VALUES (?, ?, ?)");
            $stmt->bind_param("sss", $name, $subject, $email);

            if ($stmt->execute()) {
                header("Location: teachers.php?msg=Teacher added successfully.");
                exit();
            } else {
                $error = "Could not save the teacher. Please try again.";
            }
        }
        $check->close();
    }
}

$page_title = "Add Teacher";
$page_sub   = "Fill in the details of the new teacher.";
$active     = "teachers";
include("../users/header.php");
?>

<div class="row">
    <div class="col-lg-7">
        <a href="teachers.php" class="text-decoration-none text-muted small"><i class="bi bi-arrow-left me-1"></i> Back to Directory</a>
        <h4 class="fw-bold mt-2 mb-3">Add New Teacher</h4>

        <?php if ($error): ?>
            <div class="alert alert-danger border-0 shadow-sm mb-4"><i class="bi bi-exclamation-triangle-fill me-2"></i><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

        <div class="panel-card p-4 shadow-sm">
            <form action="add_teacher.php" method="POST" class="needs-validation" novalidate>
                <div class="mb-3">
                    <label class="form-label fw-medium">Full Name</label>
                    <input type="text" name="name" class="form-control" placeholder="e.g. Dr. Sarah Jenkins" required>
                    <div class="invalid-feedback">Please enter the teacher's name.</div>
                </div>
                <div class="mb-3">
                    <label class="form-label fw-medium">Assigned Subject</label>
                    <input type="text" name="subject" class="form-control" placeholder="e.g. Computer Architecture" required>
                    <div class="invalid-feedback">Please enter the subject.</div>
                </div>
                <div class="mb-4">
                    <label class="form-label fw-medium">Email Address</label>
                    <input type="email" name="email" class="form-control" placeholder="e.g. s.jenkins@university.edu" required>
                    <div class="invalid-feedback">Please enter a valid email.</div>
                </div>
                <div class="d-flex justify-content-end gap-2">
                    <a href="teachers.php" class="btn btn-light border px-4">Cancel</a>
                    <button type="submit" class="btn btn-primary px-4"><i class="bi bi-check-lg me-1"></i> Save Teacher</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php include("../users/footer.php"); ?>
