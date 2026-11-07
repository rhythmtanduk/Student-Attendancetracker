<?php
session_start();
include("dbconnection.php");

// Auth check
if (!isset($_SESSION['user_id'])) {
    header("Location: ../users/index.php");
    exit();
}

// Only the administrator can add holidays
$user_role = strtolower($_SESSION['role'] ?? 'student');
if ($user_role !== 'admin' && $user_role !== 'administrator') {
    header("Location: ../users/dashboard.php");
    exit();
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $date  = trim($_POST['holiday_date'] ?? '');
    $title = trim($_POST['title'] ?? '');

    if (empty($date) || empty($title)) {
        $error = "All fields are required.";
    } elseif (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
        $error = "Please choose a valid date.";
    } elseif (strlen($title) > 100) {
        $error = "The holiday name must be 100 characters or less.";
    } else {
        // Only one holiday per date
        $check = $conn->prepare("SELECT holiday_id FROM holidays WHERE holiday_date = ?");
        $check->bind_param("s", $date);
        $check->execute();

        if ($check->get_result()->num_rows > 0) {
            $error = "A holiday is already added on this date.";
        } else {
            $stmt = $conn->prepare("INSERT INTO holidays (holiday_date, title) VALUES (?, ?)");
            $stmt->bind_param("ss", $date, $title);

            if ($stmt->execute()) {
                header("Location: holidays.php?msg=Holiday added successfully.");
                exit();
            } else {
                $error = "Could not save the holiday. Please try again.";
            }
        }
        $check->close();
    }
}

$page_title = "Add Holiday";
$page_sub   = "Attendance cannot be marked on a holiday.";
$active     = "holiday";
include("../users/header.php");
?>

<div class="row">
    <div class="col-lg-7">
        <a href="holidays.php" class="text-decoration-none text-muted small"><i class="bi bi-arrow-left me-1"></i> Back to Holidays</a>
        <h4 class="fw-bold mt-2 mb-3">Add New Holiday</h4>

        <?php if ($error): ?>
            <div class="alert alert-danger border-0 shadow-sm mb-4"><i class="bi bi-exclamation-triangle-fill me-2"></i><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

        <div class="panel-card p-4 shadow-sm">
            <form action="add_holiday.php" method="POST" class="needs-validation" novalidate>
                <div class="mb-3">
                    <label class="form-label fw-medium">Date</label>
                    <input type="date" name="holiday_date" class="form-control" required>
                    <div class="invalid-feedback">Please choose a date.</div>
                </div>
                <div class="mb-4">
                    <label class="form-label fw-medium">Holiday Name</label>
                    <input type="text" name="title" class="form-control" maxlength="100" placeholder="e.g. Dashain" required>
                    <div class="invalid-feedback">Please enter the holiday name.</div>
                </div>
                <div class="d-flex justify-content-end gap-2">
                    <a href="holidays.php" class="btn btn-light border px-4">Cancel</a>
                    <button type="submit" class="btn btn-primary px-4"><i class="bi bi-check-lg me-1"></i> Save Holiday</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php include("../users/footer.php"); ?>