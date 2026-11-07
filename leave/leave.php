<?php
session_start();
include("dbconnection.php");

// Auth check
if (!isset($_SESSION['user_id'])) {
    header("Location: ../users/index.php");
    exit();
}

$user_role  = strtolower($_SESSION['role'] ?? 'student');
$is_admin   = ($user_role === 'admin' || $user_role === 'administrator');
$is_staff   = ($is_admin || $user_role === 'teacher');
$is_student = ($user_role === 'student');

if (!$is_staff && !$is_student) {
    header("Location: ../users/dashboard.php");
    exit();
}

$colors = ['Pending' => 'warning text-dark', 'Approved' => 'success', 'Rejected' => 'danger'];

/* ================= STUDENT: only their own requests ================= */
if ($is_student) {
    $stmt = $conn->prepare("SELECT s.student_id FROM students s JOIN users u ON u.email = s.email WHERE u.user_id = ?");
    $stmt->bind_param("i", $_SESSION['user_id']);
    $stmt->execute();
    $me = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    $rows = [];
    if ($me) {
        $stmt = $conn->prepare("SELECT leave_id, from_date, to_date, reason, status, review_note, created_at
                                FROM leave_requests WHERE student_id = ?
                                ORDER BY created_at DESC, leave_id DESC");
        $stmt->bind_param("i", $me['student_id']);
        $stmt->execute();
        $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
    }
}

/* ================= ADMIN / TEACHER: all requests ================= */
else {
    $status = $_GET['status'] ?? "";
    if (!in_array($status, ['Pending', 'Approved', 'Rejected'])) { $status = ""; }

    // (? = '' OR ...) means: if no status is chosen, show all
    $stmt = $conn->prepare("SELECT l.leave_id, l.from_date, l.to_date, l.reason, l.status, l.created_at,
                                   s.roll_no, s.full_name, s.class, s.section
                            FROM leave_requests l
                            JOIN students s ON s.student_id = l.student_id
                            WHERE (? = '' OR l.status = ?)
                            ORDER BY FIELD(l.status, 'Pending', 'Approved', 'Rejected'), l.created_at DESC
                            LIMIT 300");
    $stmt->bind_param("ss", $status, $status);
    $stmt->execute();
    $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();

    // Totals for the summary cards
    $counts = ['Pending' => 0, 'Approved' => 0, 'Rejected' => 0];
    $c = $conn->query("SELECT status, COUNT(*) AS total FROM leave_requests GROUP BY status");
    while ($r = $c->fetch_assoc()) { $counts[$r['status']] = (int)$r['total']; }
}

$page_title = $is_student ? "My Leave Requests" : "Leave Requests";
$page_sub   = $is_student ? "Apply for leave and track the answer." : "Approve or reject student leave requests.";
$active     = "leave";
include("../users/header.php");
?>

<!-- Flash Messages -->
<?php if (isset($_GET['msg'])): ?>
    <div class="alert alert-success alert-dismissible fade show border-0 shadow-sm mb-4" role="alert">
        <i class="bi bi-check-circle-fill me-2"></i><?= htmlspecialchars($_GET['msg']) ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
<?php endif; ?>

<?php if (isset($_GET['err'])): ?>
    <div class="alert alert-danger alert-dismissible fade show border-0 shadow-sm mb-4" role="alert">
        <i class="bi bi-exclamation-triangle-fill me-2"></i><?= htmlspecialchars($_GET['err']) ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
<?php endif; ?>

<?php if ($is_student): ?>
<!-- ================= STUDENT VIEW ================= -->

    <?php if (!$me): ?>
        <div class="alert alert-warning border-0 shadow-sm">
            <i class="bi bi-exclamation-triangle-fill me-2"></i>
            We could not find a student record with your email. Ask the administrator to add you with the same email you registered with.
        </div>
    <?php else: ?>
        <div class="d-flex justify-content-between align-items-center mb-3">
            <div>
                <h4 class="fw-bold mb-1">My Leave Requests</h4>
                <span class="text-muted small"><?= count($rows) ?> requests</span>
            </div>
            <a href="apply_leave.php" class="btn btn-primary px-3 shadow-sm">
                <i class="bi bi-send me-1"></i> Apply for Leave
            </a>
        </div>

        <div class="panel-card shadow-sm">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th class="ps-4">From</th>
                            <th>To</th>
                            <th>Days</th>
                            <th>Reason</th>
                            <th>Status</th>
                            <th class="text-end pe-4" style="width: 150px;">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($rows)): ?>
                            <tr>
                                <td colspan="6" class="text-center text-muted py-5">
                                    <i class="bi bi-inbox fs-2 d-block mb-2 text-secondary"></i>
                                    You have not applied for any leave yet.
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($rows as $r): ?>
                                <tr>
                                    <td class="ps-4 fw-medium"><?= date("d M Y", strtotime($r['from_date'])) ?></td>
                                    <td><?= date("d M Y", strtotime($r['to_date'])) ?></td>
                                    <td><?= (strtotime($r['to_date']) - strtotime($r['from_date'])) / 86400 + 1 ?></td>
                                    <td class="text-muted small"><?= htmlspecialchars(mb_strimwidth($r['reason'], 0, 50, '...')) ?></td>
                                    <td><span class="badge bg-<?= $colors[$r['status']] ?>"><?= $r['status'] ?></span></td>
                                    <td class="text-end pe-4">
                                        <div class="btn-group btn-group-sm">
                                            <a href="view_leave.php?id=<?= $r['leave_id'] ?>" class="btn btn-outline-secondary" title="View"><i class="bi bi-eye"></i></a>
                                            <?php if ($r['status'] == 'Pending'): ?>
                                                <a href="update_leave.php?id=<?= $r['leave_id'] ?>" class="btn btn-outline-warning" title="Edit"><i class="bi bi-pencil"></i></a>
                                                <a href="delete_leave.php?id=<?= $r['leave_id'] ?>" class="btn btn-outline-danger"
                                                   onclick="return confirm('Cancel this leave request?');" title="Cancel"><i class="bi bi-x-lg"></i></a>
                                            <?php endif; ?>
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

<?php else: ?>
<!-- ================= ADMIN / TEACHER VIEW ================= -->

    <div class="row g-3 mb-3">
        <div class="col-4"><div class="metric-card"><div class="text-muted small">Pending</div><h3 class="fw-bold text-warning mb-0"><?= $counts['Pending'] ?></h3></div></div>
        <div class="col-4"><div class="metric-card"><div class="text-muted small">Approved</div><h3 class="fw-bold text-success mb-0"><?= $counts['Approved'] ?></h3></div></div>
        <div class="col-4"><div class="metric-card"><div class="text-muted small">Rejected</div><h3 class="fw-bold text-danger mb-0"><?= $counts['Rejected'] ?></h3></div></div>
    </div>

    <!-- Filter and search -->
    <form method="get" class="row g-2 mb-3">
        <div class="col-md-4">
            <div class="input-group">
                <span class="input-group-text bg-white"><i class="bi bi-search"></i></span>
                <input type="text" id="searchBox" class="form-control" placeholder="Search by name or roll no...">
            </div>
        </div>
        <div class="col-md-3">
            <select name="status" class="form-select" onchange="this.form.submit()">
                <option value="">All statuses</option>
                <option value="Pending" <?= $status == 'Pending' ? 'selected' : '' ?>>Pending</option>
                <option value="Approved" <?= $status == 'Approved' ? 'selected' : '' ?>>Approved</option>
                <option value="Rejected" <?= $status == 'Rejected' ? 'selected' : '' ?>>Rejected</option>
            </select>
        </div>
        <div class="col-md-5 text-md-end text-muted small align-self-center">
            Showing <span id="rowCount"><?= count($rows) ?></span> of <?= count($rows) ?> requests
        </div>
    </form>

    <div class="panel-card shadow-sm">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0" id="dataTable">
                <thead class="table-light">
                    <tr>
                        <th class="ps-4">Student</th>
                        <th>Class</th>
                        <th>Period</th>
                        <th>Days</th>
                        <th>Status</th>
                        <th class="text-end pe-4" style="width: 120px;">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($rows)): ?>
                        <tr>
                            <td colspan="6" class="text-center text-muted py-5">
                                <i class="bi bi-inbox fs-2 d-block mb-2 text-secondary"></i>
                                No leave requests found.
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($rows as $r): ?>
                            <tr data-row>
                                <td class="ps-4">
                                    <div class="fw-semibold"><?= htmlspecialchars($r['full_name']) ?></div>
                                    <small class="text-muted">Roll <?= htmlspecialchars($r['roll_no']) ?></small>
                                </td>
                                <td><?= htmlspecialchars($r['class']) ?>-<?= htmlspecialchars($r['section']) ?></td>
                                <td class="small"><?= date("d M", strtotime($r['from_date'])) ?> - <?= date("d M Y", strtotime($r['to_date'])) ?></td>
                                <td><?= (strtotime($r['to_date']) - strtotime($r['from_date'])) / 86400 + 1 ?></td>
                                <td><span class="badge bg-<?= $colors[$r['status']] ?>"><?= $r['status'] ?></span></td>
                                <td class="text-end pe-4">
                                    <div class="btn-group btn-group-sm">
                                        <a href="view_leave.php?id=<?= $r['leave_id'] ?>" class="btn btn-outline-secondary" title="View / Review"><i class="bi bi-eye"></i></a>
                                        <?php if ($is_admin): ?>
                                            <a href="delete_leave.php?id=<?= $r['leave_id'] ?>" class="btn btn-outline-danger"
                                               onclick="return confirm('Delete this leave request?');" title="Delete"><i class="bi bi-trash"></i></a>
                                        <?php endif; ?>
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