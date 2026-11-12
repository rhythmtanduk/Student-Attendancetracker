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

$sel = isset($_GET['class_id']) ? intval($_GET['class_id']) : 0;   // 0 = all classes

$classes = $conn->query("SELECT class_id, class_name, section FROM classes ORDER BY CAST(class_name AS UNSIGNED), class_name, section");

// (? = 0 OR ...) means: if no class is chosen, show all
$stmt = $conn->prepare("SELECT sb.subject_id, sb.subject_name, c.class_name, c.section, t.full_name AS teacher_name
                        FROM subjects sb
                        JOIN classes c ON c.class_id = sb.class_id
                        LEFT JOIN teachers t ON t.teacher_id = sb.teacher_id
                        WHERE (? = 0 OR sb.class_id = ?)
                        ORDER BY CAST(c.class_name AS UNSIGNED), c.section, sb.subject_name");
$stmt->bind_param("ii", $sel, $sel);
$stmt->execute();
$rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

$page_title = "Subjects";
$page_sub   = "Subjects of each class and their teachers.";
$active     = "subject";
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
        <h4 class="fw-bold mb-1">Subject List</h4>
        <span class="text-muted small">Showing <span id="rowCount"><?= count($rows) ?></span> of <?= count($rows) ?> subjects</span>
    </div>
    <?php if ($is_admin): ?>
        <a href="add_subject.php<?= $sel > 0 ? '?class_id=' . $sel : '' ?>" class="btn btn-primary px-3 shadow-sm">
            <i class="bi bi-plus-lg me-1"></i> Add Subject
        </a>
    <?php endif; ?>
</div>

<!-- Filter and search -->
<form method="get" class="row g-2 mb-3">
    <div class="col-md-5">
        <div class="input-group">
            <span class="input-group-text bg-white"><i class="bi bi-search"></i></span>
            <input type="text" id="searchBox" class="form-control" placeholder="Search by subject or teacher...">
        </div>
    </div>
    <div class="col-md-3">
        <select name="class_id" class="form-select" onchange="this.form.submit()">
            <option value="0">All classes</option>
            <?php while ($c = $classes->fetch_assoc()): ?>
                <option value="<?= $c['class_id'] ?>" <?= $sel == $c['class_id'] ? 'selected' : '' ?>>
                    Class <?= htmlspecialchars($c['class_name']) ?> - <?= htmlspecialchars($c['section']) ?>
                </option>
            <?php endwhile; ?>
        </select>
    </div>
</form>

<div class="panel-card shadow-sm">
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0" id="dataTable">
            <thead class="table-light">
                <tr>
                    <th class="ps-4">Subject</th>
                    <th>Class</th>
                    <th>Teacher</th>
                    <?php if ($is_admin): ?>
                        <th class="text-end pe-4" style="width: 120px;">Actions</th>
                    <?php endif; ?>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($rows)): ?>
                    <tr>
                        <td colspan="4" class="text-center text-muted py-5">
                            <i class="bi bi-inbox fs-2 d-block mb-2 text-secondary"></i>
                            No subjects found. Click "Add Subject".
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($rows as $r): ?>
                        <tr data-row>
                            <td class="ps-4 fw-semibold text-dark"><?= htmlspecialchars($r['subject_name']) ?></td>
                            <td><span class="badge bg-primary-subtle text-primary border border-primary-subtle"><?= htmlspecialchars($r['class_name']) ?>-<?= htmlspecialchars($r['section']) ?></span></td>
                            <td class="<?= $r['teacher_name'] ? '' : 'text-muted' ?>"><?= $r['teacher_name'] ? htmlspecialchars($r['teacher_name']) : 'Not assigned' ?></td>
                            <?php if ($is_admin): ?>
                                <td class="text-end pe-4">
                                    <div class="btn-group btn-group-sm">
                                        <a href="update_subject.php?id=<?= $r['subject_id'] ?>" class="btn btn-outline-warning" title="Edit"><i class="bi bi-pencil"></i></a>
                                        <a href="delete_subject.php?id=<?= $r['subject_id'] ?>" class="btn btn-outline-danger"
                                           onclick="return confirm('Are you sure you want to delete this subject?');" title="Delete"><i class="bi bi-trash"></i></a>
                                    </div>
                                </td>
                            <?php endif; ?>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php include("../users/footer.php"); ?>