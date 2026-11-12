<?php
session_start();
include("dbconnection.php");

// Auth check
if (!isset($_SESSION['user_id'])) {
    header("Location: ../users/index.php");
    exit();
}

// Only the administrator can see the activity log
$user_role = strtolower($_SESSION['role'] ?? 'student');
if ($user_role !== 'admin' && $user_role !== 'administrator') {
    header("Location: ../users/dashboard.php");
    exit();
}

// Filters
$sel_user = isset($_GET['user']) ? intval($_GET['user']) : 0;     // 0 = all users
$date     = $_GET['date'] ?? "";
if ($date != "" && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) { $date = ""; }

$users = $conn->query("SELECT user_id, full_name, role FROM users ORDER BY full_name");

// (? = 0 OR ...) and (? = '' OR ...) mean: if the filter is empty, do not filter
$stmt = $conn->prepare("SELECT l.log_id, l.action, l.created_at, u.full_name, u.role
                        FROM activity_logs l
                        LEFT JOIN users u ON u.user_id = l.user_id
                        WHERE (? = 0 OR l.user_id = ?)
                          AND (? = '' OR DATE(l.created_at) = ?)
                        ORDER BY l.created_at DESC, l.log_id DESC
                        LIMIT 300");
$stmt->bind_param("iiss", $sel_user, $sel_user, $date, $date);
$stmt->execute();
$rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// Summary numbers
$total_logs = $conn->query("SELECT COUNT(*) AS total FROM activity_logs")->fetch_assoc()['total'];
$today_logs = $conn->query("SELECT COUNT(*) AS total FROM activity_logs WHERE DATE(created_at) = CURDATE()")->fetch_assoc()['total'];
$week_logs  = $conn->query("SELECT COUNT(*) AS total FROM activity_logs WHERE created_at >= (NOW() - INTERVAL 7 DAY)")->fetch_assoc()['total'];

// Colour of the badge, chosen from the first word of the action
function action_color($action) {
    if (preg_match('/^(Added|New|Saved|Marked|Registered|Approved|Logged in)/i', $action)) { return 'success'; }
    if (preg_match('/^(Edited|Updated)/i', $action))                                       { return 'warning'; }
    if (preg_match('/^(Deleted|Removed|Rejected|Cleared)/i', $action))                     { return 'danger'; }
    return 'secondary';
}

$page_title = "Activity Log";
$page_sub   = "Who did what, and when.";
$active     = "log";
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

<!-- Summary cards -->
<div class="row g-3 mb-3 no-print">
    <div class="col-4"><div class="metric-card"><div class="text-muted small">Today</div><h3 class="fw-bold text-primary mb-0"><?= $today_logs ?></h3></div></div>
    <div class="col-4"><div class="metric-card"><div class="text-muted small">Last 7 days</div><h3 class="fw-bold text-dark mb-0"><?= $week_logs ?></h3></div></div>
    <div class="col-4"><div class="metric-card"><div class="text-muted small">All time</div><h3 class="fw-bold text-dark mb-0"><?= $total_logs ?></h3></div></div>
</div>

<!-- Filters -->
<form method="get" class="panel-card shadow-sm p-3 mb-3 no-print">
    <div class="row g-3 align-items-end">
        <div class="col-md-4">
            <label class="form-label small fw-semibold">User</label>
            <select name="user" class="form-select">
                <option value="0">All users</option>
                <?php while ($u = $users->fetch_assoc()): ?>
                    <option value="<?= $u['user_id'] ?>" <?= $sel_user == $u['user_id'] ? 'selected' : '' ?>>
                        <?= htmlspecialchars($u['full_name']) ?> (<?= htmlspecialchars($u['role']) ?>)
                    </option>
                <?php endwhile; ?>
            </select>
        </div>
        <div class="col-md-3">
            <label class="form-label small fw-semibold">Date</label>
            <input type="date" name="date" class="form-control" value="<?= htmlspecialchars($date) ?>">
        </div>
        <div class="col-md-5 d-flex gap-2">
            <button type="submit" class="btn btn-primary"><i class="bi bi-funnel me-1"></i> Filter</button>
            <a href="logs.php" class="btn btn-outline-secondary">Reset</a>
        </div>
    </div>
</form>

<!-- Search + buttons -->
<div class="row g-2 mb-3 no-print">
    <div class="col-md-5">
        <div class="input-group">
            <span class="input-group-text bg-white"><i class="bi bi-search"></i></span>
            <input type="text" id="searchBox" class="form-control" placeholder="Search the log...">
        </div>
    </div>
    <div class="col-md-7 text-md-end">
        <span class="text-muted small me-2">Showing <span id="rowCount"><?= count($rows) ?></span> of <?= count($rows) ?> (latest 300)</span>
        <button type="button" id="exportCsv" data-filename="activity_log.csv" class="btn btn-outline-success btn-sm"><i class="bi bi-file-earmark-spreadsheet me-1"></i> CSV</button>
        <button type="button" id="printBtn" class="btn btn-outline-secondary btn-sm"><i class="bi bi-printer me-1"></i> Print</button>
        <a href="clear_logs.php" class="btn btn-outline-danger btn-sm"
           onclick="return confirm('Delete all log lines older than 30 days?');"><i class="bi bi-trash me-1"></i> Clear old (30+ days)</a>
    </div>
</div>

<div class="panel-card shadow-sm">
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0" id="dataTable">
            <thead class="table-light">
                <tr>
                    <th class="ps-3" style="width: 170px;">Time</th>
                    <th style="width: 220px;">User</th>
                    <th>Action</th>
                    <th class="text-end pe-3 no-print" style="width: 70px;"></th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($rows)): ?>
                    <tr>
                        <td colspan="4" class="text-center text-muted py-5">
                            <i class="bi bi-clock-history fs-2 d-block mb-2 text-secondary"></i>
                            No activity found.
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($rows as $r): ?>
                        <?php $color = action_color($r['action']); ?>
                        <tr data-row>
                            <td class="ps-3 small text-muted"><?= date("d M Y, h:i A", strtotime($r['created_at'])) ?></td>
                            <td>
                                <?php if ($r['full_name']): ?>
                                    <div class="fw-semibold"><?= htmlspecialchars($r['full_name']) ?></div>
                                    <small class="text-muted"><?= htmlspecialchars($r['role']) ?></small>
                                <?php else: ?>
                                    <span class="text-muted">System</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <span class="badge bg-<?= $color ?>-subtle text-<?= $color ?> border border-<?= $color ?>-subtle fw-medium text-wrap text-start">
                                    <?= htmlspecialchars($r['action']) ?>
                                </span>
                            </td>
                            <td class="text-end pe-3 no-print">
                                <a href="delete_log.php?id=<?= $r['log_id'] ?>" class="btn btn-outline-danger btn-sm"
                                   onclick="return confirm('Delete this log line?');" title="Delete"><i class="bi bi-trash"></i></a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php include("../users/footer.php"); ?>