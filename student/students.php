<?php
session_start();
include("dbconnection.php");

// Auth check
if (!isset($_SESSION['user_id'])) {
    header("Location: ../users/index.php");
    exit();
}

// User details & normalizations
$user_role = strtolower($_SESSION['role'] ?? 'student');

// Only the administrator can manage students
if ($user_role !== 'admin' && $user_role !== 'administrator') {
    header("Location: ../users/dashboard.php");
    exit();
}

$students = [];
$class_list = [];
$query = "SELECT student_id, roll_no, full_name, class, section, email FROM students ORDER BY student_id DESC";
$result = $conn->query($query);
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $students[] = $row;
        // collect each class-section once (used by the filter dropdown)
        $key = $row['class'] . '-' . strtoupper($row['section']);
        $class_list[$key] = $key;
    }
}
ksort($class_list);

$page_title = "Student Directory";
$page_sub   = "Manage all registered student records here.";
$active     = "students";
include("../users/header.php");
?>

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

<div class="d-flex justify-content-between align-items-center mb-3">
    <div>
        <h4 class="fw-bold text-dark mb-0">Student Directory</h4>
        <span class="text-muted small">Showing <span id="rowCount"><?= count($students) ?></span> of <?= count($students) ?> students</span>
    </div>
    <a href="create_student.php" class="btn btn-primary d-flex align-items-center gap-2">
        <i class="bi bi-person-plus-fill"></i> Add New Student
    </a>
</div>

<!-- Search and filter (JavaScript in ../users/app.js does the work) -->
<div class="row g-2 mb-3">
    <div class="col-md-6">
        <div class="input-group">
            <span class="input-group-text bg-white"><i class="bi bi-search"></i></span>
            <input type="text" id="searchBox" class="form-control" placeholder="Search by name, roll no or email...">
        </div>
    </div>
    <div class="col-md-3">
        <select id="classFilter" class="form-select">
            <option value="">All classes</option>
            <?php foreach ($class_list as $c): ?>
                <option value="<?= htmlspecialchars($c) ?>">Class <?= htmlspecialchars($c) ?></option>
            <?php endforeach; ?>
        </select>
    </div>
</div>

<div class="panel-card shadow-sm">
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0" id="dataTable">
            <thead class="table-light">
                <tr>
                    <th class="ps-3">#</th>
                    <th>Roll No</th>
                    <th>Full Name</th>
                    <th>Class</th>
                    <th>Section</th>
                    <th>Email</th>
                    <th class="text-center pe-3" style="width: 180px;">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($students)): ?>
                    <tr>
                        <td colspan="7" class="text-center text-muted py-4">No student records found.</td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($students as $index => $s): ?>
                        <tr data-row data-class="<?= htmlspecialchars($s['class'] . '-' . strtoupper($s['section'])) ?>">
                            <td class="ps-3"><?= $index + 1 ?></td>
                            <td><code><?= htmlspecialchars($s['roll_no']) ?></code></td>
                            <td class="fw-semibold text-dark"><?= htmlspecialchars($s['full_name']) ?></td>
                            <td><?= htmlspecialchars($s['class']) ?></td>
                            <td><?= htmlspecialchars($s['section']) ?></td>
                            <td><?= htmlspecialchars($s['email']) ?></td>
                            <td class="text-center pe-3">
                                <a href="read_student.php?id=<?= $s['student_id'] ?>" class="btn btn-outline-info btn-sm me-1" title="View">
                                    <i class="bi bi-eye-fill"></i>
                                </a>
                                <a href="update_student.php?id=<?= $s['student_id'] ?>" class="btn btn-outline-warning btn-sm me-1" title="Edit">
                                    <i class="bi bi-pencil-fill"></i>
                                </a>
                                <a href="delete_student.php?id=<?= $s['student_id'] ?>" class="btn btn-outline-danger btn-sm"
                                   onclick="return confirm('Are you sure you want to delete this student?');" title="Delete">
                                    <i class="bi bi-trash-fill"></i>
                                </a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php include("../users/footer.php"); ?>
