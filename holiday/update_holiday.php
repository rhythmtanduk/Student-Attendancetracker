<?php
session_start();
include("dbconnection.php");

if (!isset($_SESSION['user_id'])) {
    header("Location: ../users/index.php");
    exit();
}

// Only the administrator can edit holidays
$user_role = strtolower($_SESSION['role'] ?? 'student');
if ($user_role !== 'admin' && $user_role !== 'administrator') {
    header("Location: ../users/dashboard.php");
    exit();
}

$error = '';
$id = isset($_GET['id']) ? intval($_GET['id']) : (isset($_POST['id']) ? intval($_POST['id']) : 0);

// Fetch the holiday
$stmt = $conn->prepare("SELECT holiday_id, holiday_date, title FROM holidays WHERE holiday_id = ?");
$stmt->bind_param("i", $id);
$stmt->execute();
$holiday = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$holiday) {
    header("Location: holidays.php?err=Holiday not found.");
    exit();
}

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
        // Another holiday must not already use this date
        $check = $conn->prepare("SELECT holiday_id FROM holidays WHERE holiday_date = ? AND holiday_id != ?");
        $check->bind_param("si", $date, $id);
        $check->execute();

        if ($check->get_result()->num_rows > 0) {
            $error = "Another holiday is already added on this date.";
        } else {
            $update = $conn->prepare("UPDATE holidays SET holiday_date = ?, title = ? WHERE holiday_id = ?");
            $update->bind_param("ssi", $date, $title, $id);

            if ($update->execute()) {
                header("Location: holidays.php?msg=Holiday updated successfully.");
                exit();
            } else {
                $error = "Could not update the holiday. Please try again.";
            }
        }
        $check->close();
    }
}

$page_title = "Edit Holiday";
$page_sub   = "Change the date or name of this holiday.";
$active     = "holiday";
include("../users/header.php");
?>

<div class="row">
    <div class="col-lg-7">
        <a href="holidays.php" class="text-decoration-none text-muted small"><i class="bi bi-arrow-left me-1"></i> Back to Holidays</a>
        <h4 class="fw-bold mt-2 mb-3">Edit Holiday</h4>

        <?php if ($error): ?>
            <div class="alert alert-danger border-0 shadow-sm mb-4"><i class="bi bi-exclamation-triangle-fill me-2"></i><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

        <div class="panel-card p-4 shadow-sm">
            <form action="update_holiday.php" method="POST" class="needs-validation" novalidate>
                <input type="hidden" name="id" value="<?= $holiday['holiday_id'] ?>">
                <div class="mb-3">
                    <label class="form-label fw-medium">Date</label>
                    <input type="date" name="holiday_date" class="form-control" value="<?= htmlspecialchars($holiday['holiday_date']) ?>" required>
                    <div class="invalid-feedback">Please choose a date.</div>
                </div>
                <div class="mb-4">
                    <label class="form-label fw-medium">Holiday Name</label>
                    <input type="text" name="title" class="form-control" maxlength="100" value="<?= htmlspecialchars($holiday['title']) ?>" required>
                    <div class="invalid-feedback">Please enter the holiday name.</div>
                </div>
                <div class="d-flex justify-content-end gap-2">
                    <a href="holidays.php" class="btn btn-light border px-4">Cancel</a>
                    <button type="submit" class="btn btn-primary px-4"><i class="bi bi-check-lg me-1"></i> Update Holiday</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php include("../users/footer.php"); ?>