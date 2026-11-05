<?php
session_start();

// 1. Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit();
}

// 2. Local database connection (users directory)
include("dbconnection.php");

// 3. User details & normalizations
$user_role = strtolower($_SESSION['role'] ?? 'student');
$full_name = htmlspecialchars($_SESSION['full_name'] ?? 'User');
$today     = date("Y-m-d");

// --- STUDENT: show only their own attendance (matched by email) ---
if ($user_role === 'student') {
    $stmt = $conn->prepare("SELECT s.student_id FROM students s JOIN users u ON u.email = s.email WHERE u.user_id = ?");
    $stmt->bind_param("i", $_SESSION['user_id']);
    $stmt->execute();
    $me = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    $my_total = 0;
    $my_ok = 0;
    if ($me) {
        $r = $conn->query("SELECT COUNT(*) AS total, SUM(status IN ('Present','Late')) AS ok FROM attendance WHERE student_id = " . intval($me['student_id']))->fetch_assoc();
        $my_total = (int)$r['total'];
        $my_ok    = (int)$r['ok'];
    }
    $my_percent = $my_total > 0 ? round($my_ok * 100 / $my_total, 1) : 0;
}

// --- ADMIN / TEACHER: real numbers from the database ---
else {
    $student_count = $conn->query("SELECT COUNT(*) AS total FROM students")->fetch_assoc()['total'];
    $teacher_count = $conn->query("SELECT COUNT(*) AS total FROM teachers")->fetch_assoc()['total'];
    $class_count   = $conn->query("SELECT COUNT(*) AS total FROM (SELECT DISTINCT class, section FROM students) AS c")->fetch_assoc()['total'];

    // Today's attendance
    $t = $conn->query("SELECT COUNT(*) AS total, SUM(status='Present') AS present, SUM(status='Late') AS late, SUM(status='Absent') AS absent FROM attendance WHERE attendance_date = '$today'")->fetch_assoc();
    $today_total   = (int)$t['total'];
    $today_present = (int)$t['present'];
    $today_late    = (int)$t['late'];
    $today_absent  = (int)$t['absent'];
    $today_percent = $today_total > 0 ? round(($today_present + $today_late) * 100 / $today_total) : 0;

    // Last 7 days (used by the bar chart)
    $week_labels = [];
    $week_values = [];
    for ($i = 6; $i >= 0; $i--) {
        $d = date("Y-m-d", strtotime("-$i days"));
        $r = $conn->query("SELECT COUNT(*) AS total, SUM(status IN ('Present','Late')) AS ok FROM attendance WHERE attendance_date = '$d'")->fetch_assoc();
        $week_labels[] = date("D", strtotime($d));
        $week_values[] = $r['total'] > 0 ? round($r['ok'] * 100 / $r['total']) : 0;
    }

    // Students below 75% attendance
    $low_students = $conn->query("SELECT s.roll_no, s.full_name, s.class, s.section,
                                         COUNT(a.attendance_id) AS total,
                                         SUM(a.status IN ('Present','Late')) AS ok
                                  FROM students s
                                  JOIN attendance a ON a.student_id = s.student_id
                                  GROUP BY s.student_id, s.roll_no, s.full_name, s.class, s.section
                                  HAVING (SUM(a.status IN ('Present','Late')) * 100 / COUNT(a.attendance_id)) < 75
                                  ORDER BY (SUM(a.status IN ('Present','Late')) / COUNT(a.attendance_id)) ASC
                                  LIMIT 5");
}

$page_title = "Dashboard";
$page_sub   = "Welcome back! Today is " . date("l, d F Y");
$active     = "dashboard";
include("header.php");
?>

<?php if ($user_role === 'student'): ?>

    <?php if (!$me): ?>
        <div class="alert alert-warning border-0 shadow-sm">
            <i class="bi bi-exclamation-triangle-fill me-2"></i>
            We could not find a student record with your email. Ask the administrator to add you with the same email you registered with.
        </div>
    <?php else: ?>
        <div class="row g-3">
            <div class="col-12 col-md-4">
                <div class="metric-card">
                    <div class="text-muted small fw-medium">My Attendance</div>
                    <h3 class="fw-bold mt-1 mb-2 <?= ($my_total > 0 && $my_percent < 75) ? 'text-danger' : 'text-success' ?>">
                        <?= $my_total > 0 ? $my_percent . '%' : '-' ?>
                    </h3>
                    <div class="progress" style="height: 8px;">
                        <div class="progress-bar <?= $my_percent < 75 ? 'bg-danger' : 'bg-success' ?>" style="width: <?= $my_percent ?>%"></div>
                    </div>
                    <small class="text-muted">Minimum required: 75%</small>
                </div>
            </div>
            <div class="col-6 col-md-4">
                <div class="metric-card">
                    <div class="text-muted small fw-medium">Classes Attended</div>
                    <h3 class="fw-bold text-dark mt-1 mb-0"><?= $my_ok ?></h3>
                </div>
            </div>
            <div class="col-6 col-md-4">
                <div class="metric-card">
                    <div class="text-muted small fw-medium">Total Classes Held</div>
                    <h3 class="fw-bold text-dark mt-1 mb-0"><?= $my_total ?></h3>
                </div>
            </div>
        </div>
        <?php if ($my_total > 0 && $my_percent < 75): ?>
            <div class="alert alert-warning border-0 shadow-sm mt-3">
                <i class="bi bi-exclamation-circle-fill me-2"></i>Your attendance is below 75%. Please attend the upcoming classes regularly.
            </div>
        <?php endif; ?>
        <a href="../attendance/attendance.php" class="btn btn-primary mt-3">View my attendance history</a>
    <?php endif; ?>

<?php else: ?>

    <!-- TOP METRICS ROW -->
    <div class="row g-3 mb-4">
        <div class="col-12 col-sm-6 col-xl-3">
            <div class="metric-card">
                <div class="d-flex align-items-center justify-content-between mb-3">
                    <div class="icon-box bg-primary-subtle text-primary"><i class="bi bi-people-fill"></i></div>
                </div>
                <div class="text-muted small fw-medium">Total Students</div>
                <h3 class="fw-bold text-dark mt-1 mb-0"><?= number_format($student_count) ?></h3>
            </div>
        </div>
        <div class="col-12 col-sm-6 col-xl-3">
            <div class="metric-card">
                <div class="d-flex align-items-center justify-content-between mb-3">
                    <div class="icon-box bg-success-subtle text-success"><i class="bi bi-person-badge-fill"></i></div>
                </div>
                <div class="text-muted small fw-medium">Total Teachers</div>
                <h3 class="fw-bold text-dark mt-1 mb-0"><?= number_format($teacher_count) ?></h3>
            </div>
        </div>
        <div class="col-12 col-sm-6 col-xl-3">
            <div class="metric-card">
                <div class="d-flex align-items-center justify-content-between mb-3">
                    <div class="icon-box bg-warning-subtle text-warning"><i class="bi bi-door-open-fill"></i></div>
                </div>
                <div class="text-muted small fw-medium">Total Classes</div>
                <h3 class="fw-bold text-dark mt-1 mb-0"><?= number_format($class_count) ?></h3>
            </div>
        </div>
        <div class="col-12 col-sm-6 col-xl-3">
            <div class="metric-card">
                <div class="d-flex align-items-center justify-content-between mb-3">
                    <div class="icon-box bg-info-subtle text-info"><i class="bi bi-calendar2-check-fill"></i></div>
                </div>
                <div class="text-muted small fw-medium">Present Today</div>
                <h3 class="fw-bold text-dark mt-1 mb-0"><?= $today_total > 0 ? $today_percent . '%' : '-' ?></h3>
                <small class="text-muted"><?= $today_total > 0 ? ($today_present + $today_late) . ' of ' . $today_total . ' students' : 'Not marked yet today' ?></small>
            </div>
        </div>
    </div>

    <!-- CHARTS -->
    <div class="row g-3 mb-4">
        <div class="col-lg-8">
            <div class="panel-card p-3 h-100">
                <h6 class="fw-bold mb-3">Attendance % - Last 7 Days</h6>
                <div class="chart-box"><canvas id="weekChart"></canvas></div>
            </div>
        </div>
        <div class="col-lg-4">
            <div class="panel-card p-3 h-100">
                <h6 class="fw-bold mb-3">Today</h6>
                <?php if ($today_total > 0): ?>
                    <div class="chart-box"><canvas id="todayChart"></canvas></div>
                <?php else: ?>
                    <div class="text-center text-muted py-5">
                        <i class="bi bi-calendar-x fs-2 d-block mb-2"></i>
                        No attendance marked today.<br>
                        <a href="../attendance/attendance.php" class="btn btn-sm btn-primary mt-3">Mark Attendance</a>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- LOW ATTENDANCE TABLE -->
    <div class="panel-card">
        <div class="p-3 border-bottom fw-bold">Students below 75% attendance</div>
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr><th class="ps-3">Roll No</th><th>Name</th><th>Class</th><th class="text-end pe-3">Attendance</th></tr>
                </thead>
                <tbody>
                    <?php if ($low_students && $low_students->num_rows > 0): ?>
                        <?php while ($s = $low_students->fetch_assoc()): ?>
                            <tr>
                                <td class="ps-3"><code><?= htmlspecialchars($s['roll_no']) ?></code></td>
                                <td class="fw-semibold"><?= htmlspecialchars($s['full_name']) ?></td>
                                <td><?= htmlspecialchars($s['class']) ?> - <?= htmlspecialchars($s['section']) ?></td>
                                <td class="text-end pe-3"><span class="badge bg-danger"><?= round($s['ok'] * 100 / $s['total'], 1) ?>%</span></td>
                            </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr><td colspan="4" class="text-center text-muted py-4">No student is below 75% right now.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Chart.js (a free JavaScript library for charts) -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.3/dist/chart.umd.min.js"></script>
    <script>
        // PHP gives us the numbers, JavaScript draws the charts
        var weekLabels = <?= json_encode($week_labels) ?>;
        var weekValues = <?= json_encode($week_values) ?>;

        new Chart(document.getElementById('weekChart'), {
            type: 'bar',
            data: {
                labels: weekLabels,
                datasets: [{
                    label: 'Present %',
                    data: weekValues,
                    backgroundColor: '#0284c7',
                    borderRadius: 6
                }]
            },
            options: {
                maintainAspectRatio: false,
                plugins: { legend: { display: false } },
                scales: { y: { beginAtZero: true, max: 100 } }
            }
        });

        var todayCanvas = document.getElementById('todayChart');
        if (todayCanvas) {
            new Chart(todayCanvas, {
                type: 'doughnut',
                data: {
                    labels: ['Present', 'Late', 'Absent'],
                    datasets: [{
                        data: [<?= $today_present ?>, <?= $today_late ?>, <?= $today_absent ?>],
                        backgroundColor: ['#16a34a', '#f59e0b', '#dc2626']
                    }]
                },
                options: { maintainAspectRatio: false }
            });
        }
    </script>

<?php endif; ?>

<?php include("footer.php"); ?>
