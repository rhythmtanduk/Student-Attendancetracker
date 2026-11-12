<?php
session_start();
include("dbconnection.php");

if (!isset($_SESSION['user_id'])) {
    header("Location: ../users/index.php");
    exit();
}

// Only the administrator can edit classes
$user_role = strtolower($_SESSION['role'] ?? 'student');
if ($user_role !== 'admin' && $user_role !== 'administrator') {
    header("Location: ../users/dashboard.php");
    exit();
}

$error = '';
$id = isset($_GET['id']) ? intval($_GET['id']) : (isset($_POST['id']) ? intval($_POST['id']) : 0);

$stmt = $conn->prepare("SELECT class_id, class_name, section FROM classes WHERE class_id = ?");
$stmt->bind_param("i", $id);
$stmt->execute();
$class = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$class) {
    header("Location: classes.php?err=Class not found.");
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $class_name = trim($_POST['class_name'] ?? '');
    $section    = strtoupper(trim($_POST['section'] ?? ''));

    if (empty($class_name) || empty($section)) {
        $error = "All fields are required.";
    } elseif (strlen($class_name) > 20 || strlen($section) > 5) {
        $error = "Class can have up to 20 characters and section up to 5.";
    } else {
        // Another class must not already use this class and section
        $check = $conn->prepare("SELECT class_id FROM classes WHERE class_name = ? AND section = ? AND class_id != ?");
        $check->bind_param("ssi", $class_name, $section, $id);
        $check->execute();

        if ($check->get_result()->num_rows > 0) {
            $error = "Another class already uses this class and section.";
        } else {
            $update = $conn->prepare("UPDATE classes SET class_name = ?, section = ? WHERE class_id = ?");
            $update->bind_param("ssi", $class_name, $section, $id);

            if ($update->execute()) {
                // Keep the class/section text of the students in this class in sync
                $sync = $conn->prepare("UPDATE students SET class = ?, section = ? WHERE class_id = ?");
                $sync->bind_param("ssi", $class_name, $section, $id);
                $sync->execute();
                $sync->close();

                header("Location: classes.php?msg=Class updated successfully.");
                exit();
            } else {
                $error = "Could not update the class. Please try again.";
            }
            $update->close();
        }
        $check->close();
    }
}

$page_title = "Edit Class";
$page_sub   = "Change the class or section name.";
$active     = "classes";
include("../users/header.php");
?>

<div class="row">
    <div class="col-lg-7">
        <a href="classes.php" class="text-decoration-none text-muted small"><i class="bi bi-arrow-left me-1"></i> Back to Classes</a>
        <h4 class="fw-bold mt-2 mb-3">Edit Class</h4>

        <?php if ($error): ?>
            <div class="alert alert-danger border-0 shadow-sm mb-4"><i class="bi bi-exclamation-triangle-fill me-2"></i><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

        <div class="panel-card p-4 shadow-sm">
            <form action="update_class.php" method="POST" class="needs-validation" novalidate>
                <input type="hidden" name="id" value="<?= $class['class_id'] ?>">
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label class="form-label fw-medium">Class</label>
                        <input type="text" name="class_name" class="form-control" maxlength="20" value="<?= htmlspecialchars($class['class_name']) ?>" required>
                        <div class="invalid-feedback">Please enter the class.</div>
                    </div>
                    <div class="col-md-6 mb-4">
                        <label class="form-label fw-medium">Section</label>
                        <input type="text" name="section" class="form-control" maxlength="5" value="<?= htmlspecialchars($class['section']) ?>" required>
                        <div class="invalid-feedback">Please enter the section.</div>
                    </div>
                </div>
                <div class="d-flex justify-content-end gap-2">
                    <a href="classes.php" class="btn btn-light border px-4">Cancel</a>
                    <button type="submit" class="btn btn-primary px-4"><i class="bi bi-check-lg me-1"></i> Update Class</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php include("../users/footer.php"); ?>