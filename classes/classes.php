<?php
session_start();
include("dbconnection.php");

// Auth check
if (!isset($_SESSION['user_id'])) {
    header("Location: ../users/index.php");
    exit();
}

// Admin and teacher can open this page (only the admin can change things)
$user_role = strtolower($_SESSION['role'] ?? 'student');
$is_admin  = ($user_role === 'admin' || $user_role === 'administrator');
if (!$is_admin && $user_role !== 'teacher') {
    header("Location: ../users/dashboard.php");
    exit();
}

$result = $conn->query("SELECT c.class_id, c.class_name, c.section,
                               (SELECT COUNT(*) FROM students s WHERE s.class_id = c.class_id) AS student_count,
                               (SELECT COUNT(*) FROM subjects sb WHERE sb.class_id = c.class_id) AS subject_count
                        FROM classes c
                        ORDER BY CAST(c.class_name AS UNSIGNED), c.class_name, c.section");
$total = $result ? $result->num_rows : 0;

// Students that are not linked to any class
$no_class = $conn->query("SELECT COUNT(*) AS total FROM students WHERE class_id IS NULL")->fetch_assoc()['total'];

$page_title = "Classes";
$page_sub   = "Manage classes and sections.";
$active     = "classes";
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

<?php if ($no_class > 0 && $is_admin): ?>
    <div class="alert alert-warning border-0 shadow-sm">
        <i class="bi bi-exclamation-circle-fill me-2"></i>
        <?= $no_class ?> student(s) are not linked to a class. Edit them in the Students page and choose a class.
    </div>
<?php endif; ?>

<!-- Action Header -->
<div class="d-flex justify-content-between align-items-center mb-3">
    <div>
        <h4 class="fw-bold mb-1">Class List</h4>
        <span class="text-muted small">Showing <span id="rowCount"><?= $total ?></span> of <?= $total ?> classes</span>
    </div>
    <?php if ($is_admin): ?>
        <a href="add_class.php" class="btn btn-primary px-3 shadow-sm">
            <i class="bi bi-plus-lg me-1"></i> Add Class
        </a>
    <?php endif; ?>
</div>

<!-- Search box -->
<div class="row g-2 mb-3">
    <div class="col-md-6">
        <div class="input-group">
            <span class="input-group-text bg-white"><i class="bi bi-search"></i></span>
            <input type="text" id="searchBox" class="form-control" placeholder="Search by class or section...">
        </div>
    </div>
</div>

<div class="panel-card shadow-sm">
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0" id="dataTable">
            <thead class="table-light">
                <tr>
                    <th class="ps-4">Class</th>
                    <th>Section</th>
                    <th class="text-center">Students</th>
                    <th class="text-center">Subjects</th>
                    <th class="text-end pe-4" style="width: 160px;">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if ($result && $result->num_rows > 0): ?>
                    <?php while ($row = $result->fetch_assoc()): ?>
                        <tr data-row>
                            <td class="ps-4 fw-semibold text-dark">Class <?= htmlspecialchars($row['class_name']) ?></td>
                            <td><span class="badge bg-primary-subtle text-primary border border-primary-subtle px-2 py-1"><?= htmlspecialchars($row['section']) ?></span></td>
                            <td class="text-center"><?= $row['student_count'] ?></td>
                            <td class="text-center"><?= $row['subject_count'] ?></td>
                            <td class="text-end pe-4">
                                <div class="btn-group btn-group-sm">
                                    <a href="view_class.php?id=<?= $row['class_id'] ?>" class="btn btn-outline-secondary" title="View"><i class="bi bi-eye"></i></a>
                                    <?php if ($is_admin): ?>
                                        <a href="update_class.php?id=<?= $row['class_id'] ?>" class="btn btn-outline-warning" title="Edit"><i class="bi bi-pencil"></i></a>
                                        <a href="delete_class.php?id=<?= $row['class_id'] ?>" class="btn btn-outline-danger"
                                           onclick="return confirm('Are you sure you want to delete this class?');" title="Delete"><i class="bi bi-trash"></i></a>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                    <?php endwhile; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="5" class="text-center text-muted py-5">
                            <i class="bi bi-inbox fs-2 d-block mb-2 text-secondary"></i>
                            No classes yet. Click "Add Class".
                        </td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php include("../users/footer.php"); ?>