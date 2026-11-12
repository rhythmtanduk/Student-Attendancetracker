<?php
session_start();
include("dbconnection.php");
include("../users/log_activity.php");

// Auth check
if (!isset($_SESSION['user_id'])) {
    header("Location: ../users/index.php");
    exit();
}

// Only admin and teacher can create reports
$user_role = strtolower($_SESSION['role'] ?? 'student');
if ($user_role !== 'admin' && $user_role !== 'administrator' && $user_role !== 'teacher') {
    header("Location: ../users/dashboard.php");
    exit();
}

$error = "";
$today = date("Y-m-d");

// Filters come from the preview form (GET) or from the save form (POST)
$from = $_POST['from'] ?? $_GET['from'] ?? date("Y-m-01");
$to   = $_POST['to'] ?? $_GET['to'] ?? $today;
$sel  = $_POST['class'] ?? $_GET['class'] ?? "";
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $from)) { $from = date("Y-m-01"); }
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $to))   { $to = $today; }
if ($to > $today) { $to = $today; }

if ($from > $to) {
    $error = "The 'From' date must not be after the 'To' date.";
}

// Class list for the dropdown
$classes = $conn->query("SELECT DISTINCT class, section FROM students ORDER BY class, section");

// Calculate the report for every student (live, from the attendance table)
$rows = [];
if ($error == "") {
    $stmt = $conn->prepare("SELECT s.student_id, s.roll_no, s.full_name, s.class, s.section,
                                   COUNT(a.attendance_id) AS total,
                                   COALESCE(SUM(a.status = 'Present'), 0) AS present,
                                   COALESCE(SUM(a.status = 'Late'), 0) AS late,
                                   COALESCE(SUM(a.status = 'Absent'), 0) AS absent
                            FROM students s
                            LEFT JOIN attendance a ON a.student_id = s.student_id
                                 AND a.attendance_date BETWEEN ? AND ?
                            WHERE (? = '' OR CONCAT(s.class, '|', s.section) = ?)
                            GROUP BY s.student_id, s.roll_no, s.full_name, s.class, s.section
                            ORDER BY s.class, s.section, CAST(s.roll_no AS UNSIGNED)");
    $stmt->bind_param("ssss", $from, $to, $sel, $sel);
    $stmt->execute();
    $result = $stmt->get_result();

    while ($r = $result->fetch_assoc()) {
        $r['attended'] = $r['present'] + $r['late'];
        $r['percent']  = $r['total'] > 0 ? round($r['attended'] * 100 / $r['total'], 2) : null;
        $rows[] = $r;
    }
    $stmt->close();
}

// How many students have data (only these can be saved)
$with_data = 0;
foreach ($rows as $r) {
    if ($r['total'] > 0) { $with_data++; }
}

// SAVE
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $error == "") {
    $remarks = trim($_POST['remarks'] ?? '');

    if (strlen($remarks) > 255) {
        $error = "Remarks must be 255 characters or less.";
    } elseif ($with_data == 0) {
        $error = "There is no attendance in this period, so there is nothing to save.";
    } else {
        if ($remarks === '') { $remarks = null; }

        // If a report for the same student and period exists, it is updated (not duplicated)
        $save = $conn->prepare("INSERT INTO reports (student_id, report_date, from_date, to_date, total_days, attended_days, attendance_percentage, remarks)
                                VALUES (?, ?, ?, ?, ?, ?, ?, ?)
                                ON DUPLICATE KEY UPDATE report_date = VALUES(report_date),
                                                        total_days = VALUES(total_days),
                                                        attended_days = VALUES(attended_days),
                                                        attendance_percentage = VALUES(attendance_percentage),
                                                        remarks = VALUES(remarks)");
        $saved = 0;
        foreach ($rows as $r) {
            if ($r['total'] == 0) { continue; }
            $save->bind_param("isssiids", $r['student_id'], $today, $from, $to, $r['total'], $r['attended'], $r['percent'], $remarks);
            if ($save->execute()) { $saved++; }
        }
        $save->close();

        log_activity($conn, "Saved attendance reports: " . $saved . " students, " . $from . " to " . $to);
        header("Location: report.php?msg=" . urlencode("Report saved for $saved students."));
        exit();
    }
}

$page_title = "Generate Report";
$page_sub   = "Choose a class and period, check the preview, then save.";
$active     = "report";
include("../users/header.php");
?>

<a href="report.php" class="text-decoration-none text-muted small no-print"><i class="bi bi-arrow-left me-1"></i> Back to Reports</a>

<?php if ($error != ""): ?>
    <div class="alert alert-danger border-0 shadow-sm mt-3">
        <i class="bi bi-exclamation-triangle-fill me-2"></i><?= htmlspecialchars($error) ?>
    </div>
<?php endif; ?>

<!-- Step 1: choose class and period -->
<form method="get" class="panel-card shadow-sm p-3 my-3 no-print">
    <div class="row g-3 align-items-end">
        <div class="col-md-3">
            <label class="form-label small fw-semibold">Class</label>
            <select name="class" class="form-select">
                <option value="">All classes</option>
                <?php while ($c = $classes->fetch_assoc()): ?>
                    <?php $value = $c['class'] . "|" . $c['section']; ?>
                    <option value="<?= htmlspecialchars($value) ?>" <?= ($sel == $value) ? 'selected' : '' ?>>
                        Class <?= htmlspecialchars($c['class']) ?> - <?= htmlspecialchars($c['section']) ?>
                    </option>
                <?php endwhile; ?>
            </select>
        </div>
        <div class="col-md-3">
            <label class="form-label small fw-semibold">From</label>
            <input type="date" name="from" class="form-control" value="<?= htmlspecialchars($from) ?>" max="<?= $today ?>" required>
        </div>
        <div class="col-md-3">
            <label class="form-label small fw-semibold">To</label>
            <input type="date" name="to" class="form-control" value="<?= htmlspecialchars($to) ?>" max="<?= $today ?>" required>
        </div>
        <div class="col-md-3">
            <button type="submit" class="btn btn-primary w-100"><i class="bi bi-eye me-1"></i> Preview Report</button>
        </div>
    </div>
</form>

<!-- Step 2: preview -->
<?php if ($error == ""): ?>

    <div class="row g-2 mb-3 no-print">
        <div class="col-md-5">
            <div class="input-group">
                <span class="input-group-text bg-white"><i class="bi bi-search"></i></span>
                <input type="text" id="searchBox" class="form-control" placeholder="Search by name or roll no...">
            </div>
        </div>
        <div class="col-md-7 text-md-end">
            <button type="button" id="exportCsv" data-filename="attendance_preview.csv" class="btn btn-outline-success"><i class="bi bi-file-earmark-spreadsheet me-1"></i> Download CSV</button>
            <button type="button" id="printBtn" class="btn btn-outline-secondary"><i class="bi bi-printer me-1"></i> Print</button>
        </div>
    </div>

    <div class="panel-card shadow-sm">
        <div class="p-3 border-bottom fw-bold">
            Preview: <?= date("d M Y", strtotime($from)) ?> to <?= date("d M Y", strtotime($to)) ?>
            <span class="text-muted fw-normal small ms-2">(<span id="rowCount"><?= count($rows) ?></span> students, <?= $with_data ?> with attendance data)</span>
        </div>
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0" id="dataTable">
                <thead class="table-light">
                    <tr>
                        <th class="ps-3">Roll No</th>
                        <th>Name</th>
                        <th>Class</th>
                        <th class="text-center">Total Days</th>
                        <th class="text-center">Present</th>
                        <th class="text-center">Late</th>
                        <th class="text-center">Absent</th>
                        <th class="text-end pe-3">Attendance %</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($rows)): ?>
                        <tr><td colspan="8" class="text-center text-muted py-4">No students found.</td></tr>
                    <?php else: ?>
                        <?php foreach ($rows as $r): ?>
                            <tr data-row>
                                <td class="ps-3"><code><?= htmlspecialchars($r['roll_no']) ?></code></td>
                                <td class="fw-semibold"><?= htmlspecialchars($r['full_name']) ?></td>
                                <td><?= htmlspecialchars($r['class']) ?>-<?= htmlspecialchars($r['section']) ?></td>
                                <td class="text-center"><?= $r['total'] ?></td>
                                <td class="text-center text-success"><?= $r['present'] ?></td>
                                <td class="text-center text-warning"><?= $r['late'] ?></td>
                                <td class="text-center text-danger"><?= $r['absent'] ?></td>
                                <td class="text-end pe-3">
                                    <?php if ($r['percent'] === null): ?>
                                        <span class="text-muted">No data</span>
                                    <?php else: ?>
                                        <span class="badge <?= $r['percent'] < 75 ? 'bg-danger' : 'bg-success' ?>"><?= $r['percent'] ?>%</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Step 3: save -->
    <?php if ($with_data > 0): ?>
        <form method="post" class="panel-card shadow-sm p-3 mt-3 no-print">
            <input type="hidden" name="class" value="<?= htmlspecialchars($sel) ?>">
            <input type="hidden" name="from" value="<?= htmlspecialchars($from) ?>">
            <input type="hidden" name="to" value="<?= htmlspecialchars($to) ?>">

            <div class="row g-3 align-items-end">
                <div class="col-md-8">
                    <label class="form-label small fw-semibold">Remarks (optional)</label>
                    <input type="text" name="remarks" class="form-control" maxlength="255" placeholder="e.g. Monthly report for October">
                    <div class="form-text">
                        This saves <?= $with_data ?> reports. If a report for the same student and period already exists, it will be updated.
                    </div>
                </div>
                <div class="col-md-4 d-flex gap-2 justify-content-md-end">
                    <a href="report.php" class="btn btn-light border px-4">Cancel</a>
                    <button type="submit" class="btn btn-primary px-4"><i class="bi bi-check-lg me-1"></i> Save Report</button>
                </div>
            </div>
        </form>
    <?php else: ?>
        <div class="alert alert-info border-0 shadow-sm mt-3 no-print">
            No attendance has been marked in this period, so there is nothing to save yet.
        </div>
    <?php endif; ?>

<?php endif; ?>

<?php include("../users/footer.php"); ?>