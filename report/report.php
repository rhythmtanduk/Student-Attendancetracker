<?php
session_start();
include("dbconnection.php");

// Auth check
if (!isset($_SESSION['user_id'])) {
    header("Location: ../users/index.php");
    exit();
}

// Only admin and teacher can see reports
$user_role = strtolower($_SESSION['role'] ?? 'student');
if ($user_role !== 'admin' && $user_role !== 'administrator' && $user_role !== 'teacher') {
    header("Location: ../users/dashboard.php");
    exit();
}

$today = date("Y-m-d");

// Filters from the form (default: this month, all classes)
$from = $_GET['from'] ?? date("Y-m-01");
$to   = $_GET['to'] ?? $today;
$sel  = $_GET['class'] ?? "";
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $from)) { $from = date("Y-m-01"); }
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $to))   { $to = $today; }

// Class list for the dropdown
$classes = $conn->query("SELECT DISTINCT class, section FROM students ORDER BY class, section");

// Main report query: one row per student
// (? = '' OR ...) means: if no class is chosen, show every class
$stmt = $conn->prepare("SELECT s.roll_no, s.full_name, s.class, s.section,
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

$rows = [];
$sum_percent = 0;
$counted = 0;
$below = 0;
while ($r = $result->fetch_assoc()) {
    $r['percent'] = $r['total'] > 0 ? round(($r['present'] + $r['late']) * 100 / $r['total'], 1) : null;
    if ($r['percent'] !== null) {
        $sum_percent += $r['percent'];
        $counted++;
        if ($r['percent'] < 75) { $below++; }
    }
    $rows[] = $r;
}
$stmt->close();
$average = $counted > 0 ? round($sum_percent / $counted, 1) : 0;

$page_title = "Attendance Report";
$page_sub   = "Percentage of attendance for each student.";
$active     = "report";
include("../users/header.php");
?>

<!-- Filters -->
<form method="get" class="panel-card shadow-sm p-3 mb-4 no-print">
    <div class="row g-3 align-items-end">
        <div class="col-md-3">
            <label class="form-label small fw-semibold">Class</label>
            <select name="class" class="form-select">
                <option value="">All classes</option>
                <?php while ($c = $classes->fetch_assoc()): ?>
                    <?php $value = $c['class'] . "|" . $c['section']; ?>
                    <option value="<?= htmlspecialchars($value) ?>" <?= ($sel == $value) ? 'selected' : '' ?>>
                        Class <?= htmlspecialchars($c['class']) ?> - Section <?= htmlspecialchars($c['section']) ?>
                    </option>
                <?php endwhile; ?>
            </select>
        </div>
        <div class="col-md-3">
            <label class="form-label small fw-semibold">From</label>
            <input type="date" name="from" class="form-control" value="<?= htmlspecialchars($from) ?>" max="<?= $today ?>">
        </div>
        <div class="col-md-3">
            <label class="form-label small fw-semibold">To</label>
            <input type="date" name="to" class="form-control" value="<?= htmlspecialchars($to) ?>" max="<?= $today ?>">
        </div>
        <div class="col-md-3">
            <button type="submit" class="btn btn-primary w-100"><i class="bi bi-funnel me-1"></i> Show Report</button>
        </div>
    </div>
</form>

<!-- Summary cards -->
<div class="row g-3 mb-4">
    <div class="col-12 col-md-4">
        <div class="metric-card">
            <div class="text-muted small fw-medium">Students in report</div>
            <h3 class="fw-bold text-dark mt-1 mb-0"><?= count($rows) ?></h3>
        </div>
    </div>
    <div class="col-6 col-md-4">
        <div class="metric-card">
            <div class="text-muted small fw-medium">Average attendance</div>
            <h3 class="fw-bold mt-1 mb-0 <?= ($counted > 0 && $average < 75) ? 'text-danger' : 'text-success' ?>"><?= $counted > 0 ? $average . '%' : '-' ?></h3>
        </div>
    </div>
    <div class="col-6 col-md-4">
        <div class="metric-card">
            <div class="text-muted small fw-medium">Below 75%</div>
            <h3 class="fw-bold text-danger mt-1 mb-0"><?= $below ?></h3>
        </div>
    </div>
</div>

<!-- Search + buttons -->
<div class="row g-2 mb-3 no-print">
    <div class="col-md-5">
        <div class="input-group">
            <span class="input-group-text bg-white"><i class="bi bi-search"></i></span>
            <input type="text" id="searchBox" class="form-control" placeholder="Search by name or roll no...">
        </div>
    </div>
    <div class="col-md-7 text-md-end">
        <button type="button" id="exportCsv" data-filename="attendance_report.csv" class="btn btn-outline-success"><i class="bi bi-file-earmark-spreadsheet me-1"></i> Download CSV</button>
        <button type="button" id="printBtn" class="btn btn-outline-secondary"><i class="bi bi-printer me-1"></i> Print</button>
    </div>
</div>

<div class="panel-card shadow-sm">
    <div class="p-3 border-bottom fw-bold">
        Report from <?= date("d M Y", strtotime($from)) ?> to <?= date("d M Y", strtotime($to)) ?>
        <span class="text-muted fw-normal small ms-2">(showing <span id="rowCount"><?= count($rows) ?></span> students)</span>
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

<?php include("../users/footer.php"); ?>
