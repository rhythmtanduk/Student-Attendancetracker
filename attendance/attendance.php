<?php
session_start();
include("dbconnection.php");

// Auth check
if (!isset($_SESSION['user_id'])) {
    header("Location: ../users/index.php");
    exit();
}

$user_role = strtolower($_SESSION['role'] ?? 'student');
$today = date("Y-m-d");

/* ==========================================================
   STUDENT: sees only their own attendance (matched by email)
   ========================================================== */
if ($user_role === 'student') {

    $stmt = $conn->prepare("SELECT s.student_id, s.roll_no, s.full_name, s.class, s.section
                            FROM students s JOIN users u ON u.email = s.email
                            WHERE u.user_id = ?");
    $stmt->bind_param("i", $_SESSION['user_id']);
    $stmt->execute();
    $me = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    $my_total = 0; $my_present = 0; $my_late = 0; $my_absent = 0; $history = null;

    if ($me) {
        $sid = intval($me['student_id']);
        $r = $conn->query("SELECT COUNT(*) AS total, SUM(status='Present') AS p, SUM(status='Late') AS l, SUM(status='Absent') AS a
                           FROM attendance WHERE student_id = $sid")->fetch_assoc();
        $my_total   = (int)$r['total'];
        $my_present = (int)$r['p'];
        $my_late    = (int)$r['l'];
        $my_absent  = (int)$r['a'];
        $history = $conn->query("SELECT attendance_date, status FROM attendance WHERE student_id = $sid ORDER BY attendance_date DESC LIMIT 60");
    }
    $my_percent = $my_total > 0 ? round(($my_present + $my_late) * 100 / $my_total, 1) : 0;
}

/* ==========================================================
   ADMIN / TEACHER: list of attendance records
   ========================================================== */
else {
    if ($user_role !== 'admin' && $user_role !== 'administrator' && $user_role !== 'teacher') {
        header("Location: ../users/dashboard.php");
        exit();
    }

    // Filters (the date is today by default; click "All dates" to see everything)
    $date   = isset($_GET['date']) ? $_GET['date'] : $today;
    $sel    = $_GET['class'] ?? "";
    $status = $_GET['status'] ?? "";

    if ($date != "" && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) { $date = $today; }
    if (!in_array($status, ['Present', 'Late', 'Absent'])) { $status = ""; }

    // Class list for the dropdown
    $classes = $conn->query("SELECT DISTINCT class, section FROM students ORDER BY class, section");

    // (? = '' OR ...) means: if the filter is empty, do not filter
    $stmt = $conn->prepare("SELECT a.attendance_id, a.attendance_date, a.status,
                                   s.roll_no, s.full_name, s.class, s.section,
                                   t.full_name AS teacher_name
                            FROM attendance a
                            JOIN students s ON s.student_id = a.student_id
                            LEFT JOIN teachers t ON t.teacher_id = a.teacher_id
                            WHERE (? = '' OR a.attendance_date = ?)
                              AND (? = '' OR CONCAT(s.class, '|', s.section) = ?)
                              AND (? = '' OR a.status = ?)
                            ORDER BY a.attendance_date DESC, s.class, s.section, CAST(s.roll_no AS UNSIGNED)
                            LIMIT 500");
    $stmt->bind_param("ssssss", $date, $date, $sel, $sel, $status, $status);
    $stmt->execute();
    $result = $stmt->get_result();

    $records = [];
    $count_p = 0; $count_l = 0; $count_a = 0;
    while ($row = $result->fetch_assoc()) {
        $records[] = $row;
        if ($row['status'] == 'Present') { $count_p++; }
        if ($row['status'] == 'Late')    { $count_l++; }
        if ($row['status'] == 'Absent')  { $count_a++; }
    }
    $stmt->close();
}

$page_title = ($user_role === 'student') ? "My Attendance" : "Attendance Records";
$page_sub   = ($user_role === 'student') ? "Your attendance record and percentage." : "View, edit or delete saved attendance.";
$active     = "attendance";
include("../users/header.php");

$colors = ['Present' => 'success', 'Late' => 'warning', 'Absent' => 'danger'];
?>

<?php if ($user_role === 'student'): ?>
<!-- ================= STUDENT VIEW ================= -->

    <?php if (!$me): ?>
        <div class="alert alert-warning border-0 shadow-sm">
            <i class="bi bi-exclamation-triangle-fill me-2"></i>
            We could not find a student record with your email. Ask the administrator to add you with the same email you registered with.
        </div>
    <?php else: ?>
        <div class="row g-3 mb-4">
            <div class="col-12 col-md-3">
                <div class="metric-card">
                    <div class="text-muted small fw-medium">Attendance</div>
                    <h3 class="fw-bold mt-1 mb-2 <?= ($my_total > 0 && $my_percent < 75) ? 'text-danger' : 'text-success' ?>">
                        <?= $my_total > 0 ? $my_percent . '%' : '-' ?>
                    </h3>
                    <div class="progress" style="height: 8px;">
                        <div class="progress-bar <?= $my_percent < 75 ? 'bg-danger' : 'bg-success' ?>" style="width: <?= $my_percent ?>%"></div>
                    </div>
                </div>
            </div>
            <div class="col-4 col-md-3"><div class="metric-card"><div class="text-muted small">Present</div><h3 class="fw-bold text-success mb-0"><?= $my_present ?></h3></div></div>
            <div class="col-4 col-md-3"><div class="metric-card"><div class="text-muted small">Late</div><h3 class="fw-bold text-warning mb-0"><?= $my_late ?></h3></div></div>
            <div class="col-4 col-md-3"><div class="metric-card"><div class="text-muted small">Absent</div><h3 class="fw-bold text-danger mb-0"><?= $my_absent ?></h3></div></div>
        </div>

        <?php if ($my_total > 0 && $my_percent < 75): ?>
            <div class="alert alert-warning border-0 shadow-sm">
                <i class="bi bi-exclamation-circle-fill me-2"></i>Your attendance is below 75%. Please attend classes regularly.
            </div>
        <?php endif; ?>

        <div class="panel-card shadow-sm">
            <div class="p-3 border-bottom fw-bold">
                <?= htmlspecialchars($me['full_name']) ?> &mdash; Roll <?= htmlspecialchars($me['roll_no']) ?>, Class <?= htmlspecialchars($me['class']) ?>-<?= htmlspecialchars($me['section']) ?>
            </div>
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light"><tr><th class="ps-3">Date</th><th>Day</th><th>Status</th></tr></thead>
                    <tbody>
                        <?php if ($history && $history->num_rows > 0): ?>
                            <?php while ($h = $history->fetch_assoc()): ?>
                                <tr>
                                    <td class="ps-3"><?= date("d M Y", strtotime($h['attendance_date'])) ?></td>
                                    <td class="text-muted"><?= date("l", strtotime($h['attendance_date'])) ?></td>
                                    <td><span class="badge bg-<?= $colors[$h['status']] ?? 'secondary' ?>"><?= htmlspecialchars($h['status']) ?></span></td>
                                </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr><td colspan="3" class="text-center text-muted py-4">No attendance has been marked for you yet.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    <?php endif; ?>

<?php else: ?>
<!-- ================= ADMIN / TEACHER VIEW ================= -->

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
    <div class="d-flex justify-content-between align-items-center mb-3">
        <div>
            <h4 class="fw-bold mb-1">Attendance Records</h4>
            <span class="text-muted small">
                Showing <span id="rowCount"><?= count($records) ?></span> of <?= count($records) ?> records
                <?= $date != "" ? "for " . date("d M Y", strtotime($date)) : "(all dates)" ?>
            </span>
        </div>
        <a href="mark_attendance.php" class="btn btn-primary px-3 shadow-sm">
            <i class="bi bi-calendar-plus me-1"></i> Mark Attendance
        </a>
    </div>

    <!-- Filters -->
    <form method="get" class="panel-card shadow-sm p-3 mb-3">
        <div class="row g-3 align-items-end">
            <div class="col-md-3">
                <label class="form-label small fw-semibold">Date</label>
                <input type="date" name="date" class="form-control" value="<?= htmlspecialchars($date) ?>" max="<?= $today ?>">
            </div>
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
            <div class="col-md-2">
                <label class="form-label small fw-semibold">Status</label>
                <select name="status" class="form-select">
                    <option value="">All</option>
                    <option value="Present" <?= $status == 'Present' ? 'selected' : '' ?>>Present</option>
                    <option value="Late" <?= $status == 'Late' ? 'selected' : '' ?>>Late</option>
                    <option value="Absent" <?= $status == 'Absent' ? 'selected' : '' ?>>Absent</option>
                </select>
            </div>
            <div class="col-md-4 d-flex gap-2">
                <button type="submit" class="btn btn-primary"><i class="bi bi-funnel me-1"></i> Filter</button>
                <a href="attendance.php?date=" class="btn btn-outline-secondary">All dates</a>
                <a href="attendance.php" class="btn btn-outline-secondary">Today</a>
            </div>
        </div>
    </form>

    <!-- Small summary -->
    <div class="mb-3">
        <span class="badge bg-success fs-6">Present: <?= $count_p ?></span>
        <span class="badge bg-warning text-dark fs-6">Late: <?= $count_l ?></span>
        <span class="badge bg-danger fs-6">Absent: <?= $count_a ?></span>
    </div>

    <!-- Search box (JavaScript in ../users/app.js does the work) -->
    <div class="row g-2 mb-3">
        <div class="col-md-6">
            <div class="input-group">
                <span class="input-group-text bg-white"><i class="bi bi-search"></i></span>
                <input type="text" id="searchBox" class="form-control" placeholder="Search by name or roll no...">
            </div>
        </div>
    </div>

    <div class="panel-card shadow-sm">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0" id="dataTable">
                <thead class="table-light">
                    <tr>
                        <th class="ps-3">Date</th>
                        <th>Roll No</th>
                        <th>Student</th>
                        <th>Class</th>
                        <th>Status</th>
                        <th>Marked By</th>
                        <th class="text-end pe-3" style="width: 140px;">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($records)): ?>
                        <tr>
                            <td colspan="7" class="text-center text-muted py-5">
                                <i class="bi bi-inbox fs-2 d-block mb-2 text-secondary"></i>
                                No attendance records found. Try another date or click "Mark Attendance".
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($records as $r): ?>
                            <tr data-row>
                                <td class="ps-3"><?= date("d M Y", strtotime($r['attendance_date'])) ?></td>
                                <td><code><?= htmlspecialchars($r['roll_no']) ?></code></td>
                                <td class="fw-semibold"><?= htmlspecialchars($r['full_name']) ?></td>
                                <td><?= htmlspecialchars($r['class']) ?>-<?= htmlspecialchars($r['section']) ?></td>
                                <td><span class="badge bg-<?= $colors[$r['status']] ?? 'secondary' ?>"><?= htmlspecialchars($r['status']) ?></span></td>
                                <td class="text-muted small"><?= $r['teacher_name'] ? htmlspecialchars($r['teacher_name']) : 'Admin' ?></td>
                                <td class="text-end pe-3">
                                    <div class="btn-group btn-group-sm">
                                        <a href="view_attendance.php?id=<?= $r['attendance_id'] ?>" class="btn btn-outline-secondary" title="View">
                                            <i class="bi bi-eye"></i>
                                        </a>
                                        <a href="update_attendance.php?id=<?= $r['attendance_id'] ?>" class="btn btn-outline-warning" title="Edit">
                                            <i class="bi bi-pencil"></i>
                                        </a>
                                        <a href="delete_attendance.php?id=<?= $r['attendance_id'] ?>" class="btn btn-outline-danger"
                                           onclick="return confirm('Are you sure you want to delete this attendance record?');" title="Delete">
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

<?php endif; ?>

<?php include("../users/footer.php"); ?>