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

// Filters
$sel   = $_GET['class'] ?? "";
$level = (($_GET['level'] ?? "") == 'low') ? 'low' : "";   // 'low' = below 75% only

// Class list for the dropdown
$classes = $conn->query("SELECT DISTINCT class, section FROM students ORDER BY class, section");

// (? = '' OR ...) means: if the filter is empty, do not filter
$stmt = $conn->prepare("SELECT r.report_id, r.report_date, r.from_date, r.to_date,
                               r.total_days, r.attended_days, r.attendance_percentage, r.remarks,
                               s.roll_no, s.full_name, s.class, s.section
                        FROM reports r
                        JOIN students s ON s.student_id = r.student_id
                        WHERE (? = '' OR CONCAT(s.class, '|', s.section) = ?)
                          AND (? = '' OR r.attendance_percentage < 75)
                        ORDER BY r.report_date DESC, r.report_id DESC
                        LIMIT 500");
$stmt->bind_param("sss", $sel, $sel, $level);
$stmt->execute();
$result = $stmt->get_result();

$rows = [];
$sum = 0;
$below = 0;
while ($r = $result->fetch_assoc()) {
    $rows[] = $r;
    $sum += $r['attendance_percentage'];
    if ($r['attendance_percentage'] < 75) { $below++; }
}
$stmt->close();
$average = count($rows) > 0 ? round($sum / count($rows), 1) : 0;

$page_title = "Attendance Reports";
$page_sub   = "Saved reports: view, edit or delete.";
$active     = "report";
include("../users/header.php");
?>

<!-- Flash Messages -->
<?php if (isset($_GET['msg'])): ?>
    <div class="alert alert-success alert-dismissible fade show border-0 shadow-sm" role="alert">
        <i class="bi bi-check-circle-fill me-2"></i><?= htmlspecialchars($_GET['msg']) ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
<?php endif; ?>

<?php if (isset($_GET['err'])): ?>
    <div class="alert alert-danger alert-dismissible fade show border-0 shadow-sm" role="alert">
        <i class="bi bi-exclamation-triangle-fill me-2"></i><?= htmlspecialchars($_GET['err']) ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
<?php endif; ?>

<!-- Action Header -->
<div class="d-flex justify-content-between align-items-center mb-3 no-print">
    <div>
        <h4 class="fw-bold mb-1">Saved Reports</h4>
        <span class="text-muted small">Showing <span id="rowCount"><?= count($rows) ?></span> of <?= count($rows) ?> reports</span>
    </div>
    <a href="create_report.php" class="btn btn-primary px-3 shadow-sm">
        <i class="bi bi-file-earmark-plus me-1"></i> Generate Report
    </a>
</div>

<!-- Filters -->
<form method="get" class="panel-card shadow-sm p-3 mb-3 no-print">
    <div class="row g-3 align-items-end">
        <div class="col-md-4">
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
            <label class="form-label small fw-semibold">Show</label>
            <select name="level" class="form-select">
                <option value="">All reports</option>
                <option value="low" <?= $level == 'low' ? 'selected' : '' ?>>Below 75% only</option>
            </select>
        </div>
        <div class="col-md-4 d-flex gap-2">
            <button type="submit" class="btn btn-primary"><i class="bi bi-funnel me-1"></i> Filter</button>
            <a href="report.php" class="btn btn-outline-secondary">Reset</a>
        </div>
    </div>
</form>

<!-- Summary cards -->
<div class="row g-3 mb-3">
    <div class="col-12 col-md-4">
        <div class="metric-card">
            <div class="text-muted small fw-medium">Reports shown</div>
            <h3 class="fw-bold text-dark mt-1 mb-0"><?= count($rows) ?></h3>
        </div>
    </div>
    <div class="col-6 col-md-4">
        <div class="metric-card">
            <div class="text-muted small fw-medium">Average attendance</div>
            <h3 class="fw-bold mt-1 mb-0 <?= (count($rows) > 0 && $average < 75) ? 'text-danger' : 'text-success' ?>"><?= count($rows) > 0 ? $average . '%' : '-' ?></h3>
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
        <button type="button" id="exportCsv" data-filename="saved_reports.csv" class="btn btn-outline-success"><i class="bi bi-file-earmark-spreadsheet me-1"></i> Download CSV</button>
        <button type="button" id="printBtn" class="btn btn-outline-secondary"><i class="bi bi-printer me-1"></i> Print</button>
    </div>
</div>

<div class="panel-card shadow-sm">
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0" id="dataTable">
            <thead class="table-light">
                <tr>
                    <th class="ps-3">Roll No</th>
                    <th>Student</th>
                    <th>Class</th>
                    <th>Period</th>
                    <th class="text-center">Days</th>
                    <th class="text-center">Attendance</th>
                    <th>Saved On</th>
                    <th>Remarks</th>
                    <th class="text-end pe-3 no-print" style="width: 140px;">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($rows)): ?>
                    <tr>
                        <td colspan="9" class="text-center text-muted py-5">
                            <i class="bi bi-inbox fs-2 d-block mb-2 text-secondary"></i>
                            No saved reports yet. Click "Generate Report" to create one.
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($rows as $r): ?>
                        <tr data-row>
                            <td class="ps-3"><code><?= htmlspecialchars($r['roll_no']) ?></code></td>
                            <td class="fw-semibold"><?= htmlspecialchars($r['full_name']) ?></td>
                            <td><?= htmlspecialchars($r['class']) ?>-<?= htmlspecialchars($r['section']) ?></td>
                            <td class="small"><?= date("d M", strtotime($r['from_date'])) ?> - <?= date("d M Y", strtotime($r['to_date'])) ?></td>
                            <td class="text-center"><?= $r['attended_days'] ?> / <?= $r['total_days'] ?></td>
                            <td class="text-center">
                                <span class="badge <?= $r['attendance_percentage'] < 75 ? 'bg-danger' : 'bg-success' ?>"><?= $r['attendance_percentage'] ?>%</span>
                            </td>
                            <td class="small text-muted"><?= date("d M Y", strtotime($r['report_date'])) ?></td>
                            <td class="small text-muted"><?= $r['remarks'] ? htmlspecialchars($r['remarks']) : '-' ?></td>
                            <td class="text-end pe-3 no-print">
                                <div class="btn-group btn-group-sm">
                                    <a href="view_report.php?id=<?= $r['report_id'] ?>" class="btn btn-outline-secondary" title="View">
                                        <i class="bi bi-eye"></i>
                                    </a>
                                    <a href="update_report.php?id=<?= $r['report_id'] ?>" class="btn btn-outline-warning" title="Edit">
                                        <i class="bi bi-pencil"></i>
                                    </a>
                                    <a href="delete_report.php?id=<?= $r['report_id'] ?>" class="btn btn-outline-danger"
                                       onclick="return confirm('Are you sure you want to delete this report?');" title="Delete">
                                        <i class="bi bi-trash"></i>
                                    </a>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php include("../users/footer.php"); ?>