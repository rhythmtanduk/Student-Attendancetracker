<?php
session_start();
include("dbconnection.php");

if (!isset($_SESSION['user_id'])) {
    header("Location: ../users/index.php");
    exit();
}

// Only the administrator can edit students
$user_role = strtolower($_SESSION['role'] ?? 'student');
if ($user_role !== 'admin' && $user_role !== 'administrator') {
    header("Location: ../users/dashboard.php");
    exit();
}

$error = "";
$id = isset($_GET['id']) ? intval($_GET['id']) : 0;

// Fetch student
$stmt = $conn->prepare("SELECT student_id, roll_no, full_name, class, section, email FROM students WHERE student_id = ?");
$stmt->bind_param("i", $id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    header("Location: students.php?err=" . urlencode("Student record not found."));
    exit();
}

$student = $result->fetch_assoc();
$stmt->close();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $roll_no   = trim($_POST['roll_no']);
    $full_name = trim($_POST['full_name']);
    $class     = trim($_POST['class']);
    $section   = strtoupper(trim($_POST['section']));
    $email     = trim($_POST['email']);

    if (empty($roll_no) || empty($full_name) || empty($class) || empty($section) || empty($email)) {
        $error = "All fields are required!";
    } else {
        // Check duplicate roll_no or email for other students
        $check_stmt = $conn->prepare("SELECT student_id FROM students WHERE (roll_no = ? OR email = ?) AND student_id != ?");
        $check_stmt->bind_param("ssi", $roll_no, $email, $id);
        $check_stmt->execute();

        if ($check_stmt->get_result()->num_rows > 0) {
            $error = "Roll Number or Email is already used by another student!";
        } else {
            $update_stmt = $conn->prepare("UPDATE students SET roll_no = ?, full_name = ?, class = ?, section = ?, email = ? WHERE student_id = ?");
            $update_stmt->bind_param("sssssi", $roll_no, $full_name, $class, $section, $email, $id);

            if ($update_stmt->execute()) {
                header("Location: students.php?msg=" . urlencode("Student details updated successfully!"));
                exit();
            } else {
                $error = "Failed to update student record.";
            }
            $update_stmt->close();
        }
        $check_stmt->close();
    }
}

$page_title = "Edit Student";
$page_sub   = "Update the student's details.";
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
                    <input type="text" name="roll_no" class="form-control" value="<?= htmlspecialchars($student['roll_no']) ?>" required>
                    <div class="invalid-feedback">Please enter the roll number.</div>
                </div>

                <div class="mb-3">
                    <label class="form-label small fw-semibold">Full Name</label>
                    <input type="text" name="full_name" class="form-control" value="<?= htmlspecialchars($student['full_name']) ?>" required>
                    <div class="invalid-feedback">Please enter the full name.</div>
                </div>

                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label class="form-label small fw-semibold">Class</label>
                        <input type="text" name="class" class="form-control" value="<?= htmlspecialchars($student['class']) ?>" required>
                        <div class="invalid-feedback">Please enter the class.</div>
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="form-label small fw-semibold">Section</label>
                        <input type="text" name="section" class="form-control" value="<?= htmlspecialchars($student['section']) ?>" required>
                        <div class="invalid-feedback">Please enter the section.</div>
                    </div>
                </div>

                <div class="mb-3">
                    <label class="form-label small fw-semibold">Email</label>
                    <input type="email" name="email" class="form-control" value="<?= htmlspecialchars($student['email']) ?>" required>
                    <div class="invalid-feedback">Please enter a valid email.</div>
                </div>

                <div class="d-flex justify-content-between pt-2">
                    <a href="students.php" class="btn btn-outline-secondary">Cancel</a>
                    <button type="submit" class="btn btn-warning fw-semibold">Update Student</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php include("../users/footer.php"); ?>
