<?php
session_start();
include("dbconnection.php");

if (!isset($_SESSION['user_id'])) {
    header("Location: ../users/index.php");
    exit();
}

// Only the administrator can add students
$user_role = strtolower($_SESSION['role'] ?? 'student');
if ($user_role !== 'admin' && $user_role !== 'administrator') {
    header("Location: ../users/dashboard.php");
    exit();
}

$error = "";

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $roll_no   = trim($_POST['roll_no']);
    $full_name = trim($_POST['full_name']);
    $class     = trim($_POST['class']);
    $section   = strtoupper(trim($_POST['section']));
    $email     = trim($_POST['email']);

    if (empty($roll_no) || empty($full_name) || empty($class) || empty($section) || empty($email)) {
        $error = "All fields are required!";
    } else {
        // Check for duplicate Roll No or Email
        $check_stmt = $conn->prepare("SELECT student_id FROM students WHERE roll_no = ? OR email = ?");
        $check_stmt->bind_param("ss", $roll_no, $email);
        $check_stmt->execute();

        if ($check_stmt->get_result()->num_rows > 0) {
            $error = "Roll Number or Email already exists!";
        } else {
            $stmt = $conn->prepare("INSERT INTO students (roll_no, full_name, class, section, email) VALUES (?, ?, ?, ?, ?)");
            $stmt->bind_param("sssss", $roll_no, $full_name, $class, $section, $email);

            if ($stmt->execute()) {
                header("Location: students.php?msg=" . urlencode("Student added successfully!"));
                exit();
            } else {
                $error = "Failed to add student record. Please try again.";
            }
            $stmt->close();
        }
        $check_stmt->close();
    }
}

$page_title = "Add Student";
$page_sub   = "Fill in the details of the new student.";
$active     = "students";
include("../users/header.php");
?>

<div class="row">
    <div class="col-lg-7">
        <a href="students.php" class="text-decoration-none text-muted small"><i class="bi bi-arrow-left me-1"></i> Back to Directory</a>

        <div class="panel-card shadow-sm p-4 mt-2">
            <?php if (!empty($error)): ?>
                <div class="alert alert-danger py-2 small"><?= htmlspecialchars($error) ?></div>
            <?php endif; ?>

            <form method="post" class="needs-validation" novalidate>
                <div class="mb-3">
                    <label class="form-label small fw-semibold">Roll Number</label>
                    <input type="text" name="roll_no" class="form-control" required placeholder="Enter Roll Number">
                    <div class="invalid-feedback">Please enter the roll number.</div>
                </div>

                <div class="mb-3">
                    <label class="form-label small fw-semibold">Full Name</label>
                    <input type="text" name="full_name" class="form-control" required placeholder="Enter Full Name">
                    <div class="invalid-feedback">Please enter the full name.</div>
                </div>

                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label class="form-label small fw-semibold">Class</label>
                        <input type="text" name="class" class="form-control" required placeholder="e.g. 11">
                        <div class="invalid-feedback">Please enter the class.</div>
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="form-label small fw-semibold">Section</label>
                        <input type="text" name="section" class="form-control" required placeholder="e.g. A">
                        <div class="invalid-feedback">Please enter the section.</div>
                    </div>
                </div>

                <div class="mb-3">
                    <label class="form-label small fw-semibold">Email Address</label>
                    <input type="email" name="email" class="form-control" required placeholder="Enter email address">
                    <div class="invalid-feedback">Please enter a valid email.</div>
                </div>

                <div class="d-flex justify-content-between pt-2">
                    <a href="students.php" class="btn btn-outline-secondary">Cancel</a>
                    <button type="submit" class="btn btn-primary fw-semibold">Save Student</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php include("../users/footer.php"); ?>
