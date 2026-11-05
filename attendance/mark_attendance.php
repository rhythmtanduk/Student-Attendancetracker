<?php
session_start();
include("dbconnection.php");

// Auth check
if (!isset($_SESSION['user_id'])) {
    header("Location: ../users/index.php");
    exit();
}

// Only admin and teacher can mark attendance
$user_role = strtolower($_SESSION['role'] ?? 'student');
if ($user_role !== 'admin' && $user_role !== 'administrator' && $user_role !== 'teacher') {
    header("Location: ../users/dashboard.php");
    exit();
}

$error = "";
$today = date("Y-m-d");

// Class and date come from the filter form (GET) or from the save form (POST)
$sel  = $_POST['class'] ?? $_GET['class'] ?? "";
$date = $_POST['date'] ?? $_GET['date'] ?? $today;
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) { $date = $today; }

// If the logged in user is a teacher, remember their teacher_id (matched by email)
$teacher_id = null;
$stmt = $conn->prepare("SELECT t.teacher_id FROM teachers t JOIN users u ON u.email = t.email WHERE u.user_id = ?");
$stmt->bind_param("i", $_SESSION['user_id']);
$stmt->execute();
$trow = $stmt->get_result()->fetch_assoc();
$stmt->close();
if ($trow) { $teacher_id = $trow['teacher_id']; }

// SAVE
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if ($date > $today) {
        $error = "You cannot mark attendance for a future date.";
    } elseif (empty($_POST['status'])) {
        $error = "There are no students to save.";
    } else {
        // If the student already has a record for this date, it is updated (not duplicated)
        $stmt = $conn->prepare("INSERT INTO attendance (student_id, teacher_id, attendance_date, status)
                                VALUES (?, ?, ?, ?)
                                ON DUPLICATE KEY UPDATE status = VALUES(status), teacher_id = VALUES(teacher_id)");
        $allowed = ['Present', 'Absent', 'Late'];
        $saved = 0;

        foreach ($_POST['status'] as $student_id => $status) {
            if (!in_array($status, $allowed)) { continue; }
            $student_id = intval($student_id);
            $stmt->bind_param("iiss", $student_id, $teacher_id, $date, $status);
            if ($stmt->execute()) { $saved++; }
        }
        $stmt->close();

        header("Location: attendance.php?date=" . $date . "&msg=" . urlencode("Attendance saved for $saved students."));
        exit();
    }
}

// Class list for the dropdown
$classes = $conn->query("SELECT DISTINCT class, section FROM students ORDER BY class, section");

// Students of the selected class (with their status if already marked)
$students = [];
$already_marked = false;
if ($sel != "" && strpos($sel, "|") !== false) {
    list($c, $s) = explode("|", $sel, 2);
    $stmt = $conn->prepare("SELECT s.student_id, s.roll_no, s.full_name, a.status
                            FROM students s
                            LEFT JOIN attendance a ON a.student_id = s.student_id AND a.attendance_date = ?
                            WHERE s.class = ? AND s.section = ?
                            ORDER BY CAST(s.roll_no AS UNSIGNED), s.roll_no");
    $stmt->bind_param("sss", $date, $c, $s);
    $stmt->execute();
    $res = $stmt->get_result();
    while ($row = $res->fetch_assoc()) {
        $students[] = $row;
        if ($row['status'] !== null) { $already_marked = true; }
    }
    $stmt->close();
}

$page_title = "Mark Attendance";
$page_sub   = "Choose a class and date, then mark each student.";
$active     = "attendance";
include("../users/header.php");
?>

<a href="attendance.php" class="text-decoration-none text-muted small"><i class="bi bi-arrow-left me-1"></i> Back to Records</a>

<?php if ($error != ""): ?>
    <div class="alert alert-danger border-0 shadow-sm mt-3">
        <i class="bi bi-exclamation-triangle-fill me-2"></i><?= htmlspecialchars($error) ?>
    </div>
<?php endif; ?>

<!-- Step 1: choose class and date -->
<form method="get" class="panel-card shadow-sm p-3 my-3">
    <div class="row g-3 align-items-end">
        <div class="col-md-4">
            <label class="form-label small fw-semibold">Class</label>
            <select name="class" class="form-select" required>
                <option value="">Select class...</option>
                <?php while ($c = $classes->fetch_assoc()): ?>
                    <?php $value = $c['class'] . "|" . $c['section']; ?>
                    <option value="<?= htmlspecialchars($value) ?>" <?= ($sel == $value) ? 'selected' : '' ?>>
                        Class <?= htmlspecialchars($c['class']) ?> - Section <?= htmlspecialchars($c['section']) ?>
                    </option>
                <?php endwhile; ?>
            </select>
        </div>
        <div class="col-md-3">
            <label class="form-label small fw-semibold">Date</label>
            <input type="date" name="date" class="form-control" value="<?= htmlspecialchars($date) ?>" max="<?= $today ?>" required>
        </div>
        <div class="col-md-3">
            <button type="submit" class="btn btn-primary w-100"><i class="bi bi-search me-1"></i> Load Students</button>
        </div>
    </div>
</form>

<!-- Step 2: mark each student -->
<?php if ($sel != "" && empty($students)): ?>
    <div class="alert alert-info border-0 shadow-sm">No students found in this class.</div>

<?php elseif (!empty($students)): ?>
    <form method="post">
        <input type="hidden" name="class" value="<?= htmlspecialchars($sel) ?>">
        <input type="hidden" name="date" value="<?= htmlspecialchars($date) ?>">

        <?php if ($already_marked): ?>
            <div class="alert alert-warning border-0 shadow-sm py-2 small">
                <i class="bi bi-info-circle-fill me-1"></i> Attendance for this date was already saved. Saving again will update it.
            </div>
        <?php endif; ?>

        <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
            <div>
                <span class="badge bg-success fs-6">Present: <span id="countPresent">0</span></span>
                <span class="badge bg-warning text-dark fs-6">Late: <span id="countLate">0</span></span>
                <span class="badge bg-danger fs-6">Absent: <span id="countAbsent">0</span></span>
            </div>
            <div class="btn-group btn-group-sm">
                <button type="button" class="btn btn-outline-success" onclick="markAll('Present')">All Present</button>
                <button type="button" class="btn btn-outline-danger" onclick="markAll('Absent')">All Absent</button>
            </div>
        </div>

        <div class="panel-card shadow-sm">
            <div class="table-responsive">
                <table class="table align-middle mb-0">
                    <thead class="table-light">
                        <tr><th class="ps-3">Roll No</th><th>Name</th><th class="text-end pe-3">Status</th></tr>
                    </thead>
                    <tbody>
                        <?php foreach ($students as $st): ?>
                            <?php $current = $st['status'] ?? 'Present'; $id = $st['student_id']; ?>
                            <tr>
                                <td class="ps-3"><code><?= htmlspecialchars($st['roll_no']) ?></code></td>
                                <td class="fw-semibold"><?= htmlspecialchars($st['full_name']) ?></td>
                                <td class="text-end pe-3">
                                    <div class="btn-group btn-group-sm">
                                        <input type="radio" class="btn-check" name="status[<?= $id ?>]" id="p<?= $id ?>" value="Present" <?= $current == 'Present' ? 'checked' : '' ?>>
                                        <label class="btn btn-outline-success" for="p<?= $id ?>">Present</label>

                                        <input type="radio" class="btn-check" name="status[<?= $id ?>]" id="l<?= $id ?>" value="Late" <?= $current == 'Late' ? 'checked' : '' ?>>
                                        <label class="btn btn-outline-warning" for="l<?= $id ?>">Late</label>

                                        <input type="radio" class="btn-check" name="status[<?= $id ?>]" id="a<?= $id ?>" value="Absent" <?= $current == 'Absent' ? 'checked' : '' ?>>
                                        <label class="btn btn-outline-danger" for="a<?= $id ?>">Absent</label>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <div class="d-flex justify-content-between mt-3">
            <a href="attendance.php" class="btn btn-light border px-4">Cancel</a>
            <button type="submit" class="btn btn-primary px-4"><i class="bi bi-check-lg me-1"></i> Save Attendance</button>
        </div>
    </form>

    <script>
        // Count Present / Late / Absent and colour each row
        function updateCounts() {
            document.getElementById('countPresent').textContent = document.querySelectorAll('.btn-check[value="Present"]:checked').length;
            document.getElementById('countLate').textContent    = document.querySelectorAll('.btn-check[value="Late"]:checked').length;
            document.getElementById('countAbsent').textContent  = document.querySelectorAll('.btn-check[value="Absent"]:checked').length;

            document.querySelectorAll('.btn-check:checked').forEach(function (radio) {
                var row = radio.closest('tr');
                row.className = 'row-' + radio.value.toLowerCase();
            });
        }

        // Buttons "All Present" / "All Absent"
        function markAll(status) {
            document.querySelectorAll('.btn-check[value="' + status + '"]').forEach(function (radio) {
                radio.checked = true;
            });
            updateCounts();
        }

        // Update every time a radio button is clicked
        document.querySelectorAll('.btn-check').forEach(function (radio) {
            radio.addEventListener('change', updateCounts);
        });

        updateCounts();
    </script>

<?php else: ?>
    <div class="text-center text-muted py-5">
        <i class="bi bi-calendar-check fs-1 d-block mb-2"></i>
        Select a class and date above, then click "Load Students".
    </div>
<?php endif; ?>

<?php include("../users/footer.php"); ?>