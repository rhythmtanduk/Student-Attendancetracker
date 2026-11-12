<?php
session_start();
include("dbconnection.php");

// Auth check
if (!isset($_SESSION['user_id'])) {
    header("Location: ../users/index.php");
    exit();
}

// Only the administrator can add classes
$user_role = strtolower($_SESSION['role'] ?? 'student');
if ($user_role !== 'admin' && $user_role !== 'administrator') {
    header("Location: ../users/dashboard.php");
    exit();
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $class_name = trim($_POST['class_name'] ?? '');
    $section    = strtoupper(trim($_POST['section'] ?? ''));

    if (empty($class_name) || empty($section)) {
        $error = "All fields are required.";
    } elseif (strlen($class_name) > 20 || strlen($section) > 5) {
        $error = "Class can have up to 20 characters and section up to 5.";
    } else {
        // The same class and section cannot be added twice
        $check = $conn->prepare("SELECT class_id FROM classes WHERE class_name = ? AND section = ?");
        $check->bind_param("ss", $class_name, $section);
        $check->execute();

        if ($check->get_result()->num_rows > 0) {
            $error = "This class and section already exist.";
        } else {
            $stmt = $conn->prepare("INSERT INTO classes (class_name, section) VALUES (?, ?)");
            $stmt->bind_param("ss", $class_name, $section);

            if ($stmt->execute()) {
                header("Location: classes.php?msg=Class added successfully.");
                exit();
            } else {
                $error = "Could not save the class. Please try again.";
            }
        }
        $check->close();
    }
}

$page_title = "Add Class";
$page_sub   = "Create a new class and section.";
$active     = "classes";
include("../users/header.php");
?>

<div class="row">
    <div class="col-lg-7">
        <a href="classes.php" class="text-decoration-none text-muted small"><i class="bi bi-arrow-left me-1"></i> Back to Classes</a>
        <h4 class="fw-bold mt-2 mb-3">Add New Class</h4>

        <?php if ($error): ?>
            <div class="alert alert-danger border-0 shadow-sm mb-4"><i class="bi bi-exclamation-triangle-fill me-2"></i><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

        <div class="panel-card p-4 shadow-sm">
            <form action="add_class.php" method="POST" class="needs-validation" novalidate>
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label class="form-label fw-medium">Class</label>
                        <input type="text" name="class_name" class="form-control" maxlength="20" placeholder="e.g. 11" required>
                        <div class="invalid-feedback">Please enter the class.</div>
                    </div>
                    <div class="col-md-6 mb-4">
                        <label class="form-label fw-medium">Section</label>
                        <input type="text" name="section" class="form-control" maxlength="5" placeholder="e.g. A" required>
                        <div class="invalid-feedback">Please enter the section.</div>
                    </div>
                </div>
                <div class="d-flex justify-content-end gap-2">
                    <a href="classes.php" class="btn btn-light border px-4">Cancel</a>
                    <button type="submit" class="btn btn-primary px-4"><i class="bi bi-check-lg me-1"></i> Save Class</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php include("../users/footer.php"); ?>