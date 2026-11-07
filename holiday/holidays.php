<?php
session_start();
include("dbconnection.php");

// Auth check (every logged in user can see the holidays)
if (!isset($_SESSION['user_id'])) {
    header("Location: ../users/index.php");
    exit();
}

$user_role = strtolower($_SESSION['role'] ?? 'student');
$is_admin  = ($user_role === 'admin' || $user_role === 'administrator');
$today     = date("Y-m-d");

$result = $conn->query("SELECT holiday_id, holiday_date, title FROM holidays ORDER BY holiday_date DESC");
$total  = $result ? $result->num_rows : 0;

// Count upcoming holidays for the small summary
$upcoming = $conn->query("SELECT COUNT(*) AS total FROM holidays WHERE holiday_date >= '$today'")->fetch_assoc()['total'];

$page_title = "Holidays";
$page_sub   = "Days when no attendance is taken.";
$active     = "holiday";
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

<!-- Action Header -->
<div class="d-flex justify-content-between align-items-center mb-3">
    <div>
        <h4 class="fw-bold mb-1">Holiday Calendar</h4>
        <span class="text-muted small">
            Showing <span id="rowCount"><?= $total ?></span> of <?= $total ?> holidays &middot; <?= $upcoming ?> upcoming
        </span>
    </div>
    <?php if ($is_admin): ?>
        <a href="add_holiday.php" class="btn btn-primary px-3 shadow-sm">
            <i class="bi bi-calendar-plus me-1"></i> Add Holiday
        </a>
    <?php endif; ?>
</div>

<!-- Search box (JavaScript in ../users/app.js does the work) -->
<div class="row g-2 mb-3">
    <div class="col-md-6">
        <div class="input-group">
            <span class="input-group-text bg-white"><i class="bi bi-search"></i></span>
            <input type="text" id="searchBox" class="form-control" placeholder="Search by holiday name or date...">
        </div>
    </div>
</div>

<div class="panel-card shadow-sm">
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0" id="dataTable">
            <thead class="table-light">
                <tr>
                    <th class="ps-4">Date</th>
                    <th>Day</th>
                    <th>Holiday</th>
                    <th>Status</th>
                    <?php if ($is_admin): ?>
                        <th class="text-end pe-4" style="width: 120px;">Actions</th>
                    <?php endif; ?>
                </tr>
            </thead>
            <tbody>
                <?php if ($result && $result->num_rows > 0): ?>
                    <?php while ($row = $result->fetch_assoc()): ?>
                        <?php
                        if ($row['holiday_date'] == $today)     { $badge = 'success';   $label = 'Today'; }
                        elseif ($row['holiday_date'] > $today)  { $badge = 'primary';   $label = 'Upcoming'; }
                        else                                    { $badge = 'secondary'; $label = 'Past'; }
                        ?>
                        <tr data-row>
                            <td class="ps-4 fw-medium"><?= date("d M Y", strtotime($row['holiday_date'])) ?></td>
                            <td class="text-muted"><?= date("l", strtotime($row['holiday_date'])) ?></td>
                            <td class="fw-semibold text-dark"><?= htmlspecialchars($row['title']) ?></td>
                            <td><span class="badge bg-<?= $badge ?>"><?= $label ?></span></td>
                            <?php if ($is_admin): ?>
                                <td class="text-end pe-4">
                                    <div class="btn-group btn-group-sm">
                                        <a href="update_holiday.php?id=<?= $row['holiday_id'] ?>" class="btn btn-outline-warning" title="Edit">
                                            <i class="bi bi-pencil"></i>
                                        </a>
                                        <a href="delete_holiday.php?id=<?= $row['holiday_id'] ?>" class="btn btn-outline-danger"
                                           onclick="return confirm('Are you sure you want to delete this holiday?');" title="Delete">
                                            <i class="bi bi-trash"></i>
                                        </a>
                                    </div>
                                </td>
                            <?php endif; ?>
                        </tr>
                    <?php endwhile; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="5" class="text-center text-muted py-5">
                            <i class="bi bi-calendar-x fs-2 d-block mb-2 text-secondary"></i>
                            No holidays added yet.
                        </td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php include("../users/footer.php"); ?>