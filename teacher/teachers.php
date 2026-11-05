<?php
session_start();
include("dbconnection.php");

// Authentication & Role Check
if (!isset($_SESSION['user_id'])) {
    header("Location: ../users/index.php");
    exit();
}

// strtolower() makes 'Administrator' (saved in the database) match 'administrator'
$user_role = strtolower($_SESSION['role'] ?? 'student');

// Restrict access to admin/administrator role
if ($user_role !== 'admin' && $user_role !== 'administrator') {
    header("Location: ../users/dashboard.php?err=Unauthorized access.");
    exit();
}

// Column names match the teachers table: teacher_id, full_name, subject, email
$result = $conn->query("SELECT * FROM teachers ORDER BY teacher_id DESC");
$total  = $result ? $result->num_rows : 0;

$page_title = "Teacher Directory";
$page_sub   = "Manage registered faculty members, assigned subjects, and contact emails.";
$active     = "teachers";
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
        <h4 class="fw-bold mb-1">Teacher Directory</h4>
        <span class="text-muted small">Showing <span id="rowCount"><?= $total ?></span> of <?= $total ?> teachers</span>
    </div>
    <a href="add_teacher.php" class="btn btn-primary px-3 shadow-sm">
        <i class="bi bi-person-plus-fill me-1"></i> Add New Teacher
    </a>
</div>

<!-- Search box (JavaScript in ../users/app.js does the work) -->
<div class="row g-2 mb-3">
    <div class="col-md-6">
        <div class="input-group">
            <span class="input-group-text bg-white"><i class="bi bi-search"></i></span>
            <input type="text" id="searchBox" class="form-control" placeholder="Search by name, subject or email...">
        </div>
    </div>
</div>

<!-- Teacher Table Panel Card -->
<div class="panel-card shadow-sm">
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0" id="dataTable">
            <thead class="table-light">
                <tr>
                    <th class="ps-4" style="width: 80px;">ID</th>
                    <th>Name</th>
                    <th>Subject</th>
                    <th>Email</th>
                    <th class="text-end pe-4" style="width: 160px;">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if ($result && $result->num_rows > 0): ?>
                    <?php while ($row = $result->fetch_assoc()): ?>
                        <tr data-row>
                            <td class="ps-4 text-muted fw-medium">#<?= htmlspecialchars($row['teacher_id']) ?></td>
                            <td class="fw-semibold text-dark"><?= htmlspecialchars($row['full_name']) ?></td>
                            <td>
                                <span class="badge bg-primary-subtle text-primary border border-primary-subtle px-2 py-1">
                                    <?= htmlspecialchars($row['subject']) ?>
                                </span>
                            </td>
                            <td class="text-muted small"><?= htmlspecialchars($row['email']) ?></td>
                            <td class="text-end pe-4">
                                <div class="btn-group btn-group-sm">
                                    <a href="view_teacher.php?id=<?= $row['teacher_id'] ?>" class="btn btn-outline-secondary" title="View Profile">
                                        <i class="bi bi-eye"></i>
                                    </a>
                                    <a href="update_teacher.php?id=<?= $row['teacher_id'] ?>" class="btn btn-outline-warning" title="Edit">
                                        <i class="bi bi-pencil"></i>
                                    </a>
                                    <a href="delete_teacher.php?id=<?= $row['teacher_id'] ?>" class="btn btn-outline-danger"
                                       onclick="return confirm('Are you sure you want to delete this teacher?');" title="Delete">
                                        <i class="bi bi-trash"></i>
                                    </a>
                                </div>
                            </td>
                        </tr>
                    <?php endwhile; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="5" class="text-center text-muted py-5">
                            <i class="bi bi-inbox fs-2 d-block mb-2 text-secondary"></i>
                            No teacher records found in the database.
                        </td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php include("../users/footer.php"); ?>
