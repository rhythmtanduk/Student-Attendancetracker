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
    $class_id  = intval($_POST['class_id'] ?? 0);
    $email     = trim($_POST['email']);
    $class_id  = intval($_POST['class_id'] ?? 0);
    $class     = "";
    $section   = "";

    // Look up the class and section from the classes table
    $cs = $conn->prepare("SELECT class_name, section FROM classes WHERE class_id = ?");
    $cs->bind_param("i", $class_id);
    $cs->execute();
    $crow = $cs->get_result()->fetch_assoc();
    $cs->close();
    if ($crow) { $class = $crow['class_name']; $section = $crow['section']; }

    if (empty($roll_no) || empty($full_name) || empty($email)) {
        $error = "All fields are required!";
    } elseif ($class == "") {
        $error = "Please choose a valid class.";
    } else {
        // Check for duplicate Roll No or Email
        $check_stmt = $conn->prepare("SELECT student_id FROM students WHERE roll_no = ? OR email = ?");
        $check_stmt->bind_param("ss", $roll_no, $email);
        $check_stmt->execute();

        if ($check_stmt->get_result()->num_rows > 0) {
            $error = "Roll Number or Email already exists!";
        } else {
            $stmt = $conn->prepare("INSERT INTO students (roll_no, full_name, class, section, email, class_id) VALUES (?, ?, ?, ?, ?, ?)");
            $stmt->bind_param("sssssi", $roll_no, $full_name, $class, $section, $email, $class_id);

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

// Class list for the dropdown
$classes = $conn->query("SELECT class_id, class_name, section FROM classes ORDER BY CAST(class_name AS UNSIGNED), class_name, section");

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
                    <input type="text" name="roll_no" class="form-control" required placeholder="Enter Roll Number" value="<?= htmlspecialchars($_POST['roll_no'] ?? '') ?>">
                    <div class="invalid-feedback">Please enter the roll number.</div>
                </div>

                <div class="mb-3">
                    <label class="form-label small fw-semibold">Full Name</label>
                    <input type="text" name="full_name" class="form-control" required placeholder="Enter Full Name" value="<?= htmlspecialchars($_POST['full_name'] ?? '') ?>">
                    <div class="invalid-feedback">Please enter the full name.</div>
                </div>

                <div class="mb-3">
                    <label class="form-label small fw-semibold">Class</label>
                    <select name="class_id" class="form-select" required>
                        <option value="">Select class...</option>
                        <?php while ($c = $classes->fetch_assoc()): ?>
                            <option value="<?= $c['class_id'] ?>" <?= (($_POST['class_id'] ?? '') == $c['class_id']) ? 'selected' : '' ?>>
                                Class <?= htmlspecialchars($c['class_name']) ?> - Section <?= htmlspecialchars($c['section']) ?>
                            </option>
                        <?php endwhile; ?>
                    </select>
                    <div class="invalid-feedback">Please choose a class.</div>
                    <div class="form-text">Class missing? <a href="../classes/add_class.php">Add a new class</a>.</div>
                </div>

                <div class="mb-3">
                    <label class="form-label small fw-semibold">Email Address</label>
                    <input type="email" name="email" class="form-control" required placeholder="Enter email address" value="<?= htmlspecialchars($_POST['email'] ?? '') ?>">
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