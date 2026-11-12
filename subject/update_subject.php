<?php
session_start();
include("dbconnection.php");

if (!isset($_SESSION['user_id'])) {
    header("Location: ../users/index.php");
    exit();
}

// Only the administrator can edit subjects
$user_role = strtolower($_SESSION['role'] ?? 'student');
if ($user_role !== 'admin' && $user_role !== 'administrator') {
    header("Location: ../users/dashboard.php");
    exit();
}

$error = '';
$id = isset($_GET['id']) ? intval($_GET['id']) : (isset($_POST['id']) ? intval($_POST['id']) : 0);

$stmt = $conn->prepare("SELECT subject_id, subject_name, class_id, teacher_id FROM subjects WHERE subject_id = ?");
$stmt->bind_param("i", $id);
$stmt->execute();
$subject = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$subject) {
    header("Location: subjects.php?err=Subject not found.");
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name       = trim($_POST['subject_name'] ?? '');
    $class_id   = intval($_POST['class_id'] ?? 0);
    $teacher_id = intval($_POST['teacher_id'] ?? 0);

    if (empty($name) || $class_id == 0) {
        $error = "Subject name and class are required.";
    } elseif (strlen($name) > 100) {
        $error = "The subject name must be 100 characters or less.";
    } else {
        $c = $conn->prepare("SELECT class_id FROM classes WHERE class_id = ?");
        $c->bind_param("i", $class_id);
        $c->execute();
        $class_exists = $c->get_result()->num_rows > 0;
        $c->close();

        $teacher_ok = true;
        if ($teacher_id == 0) {
            $teacher_id = null;
        } else {
            $t = $conn->prepare("SELECT teacher_id FROM teachers WHERE teacher_id = ?");
            $t->bind_param("i", $teacher_id);
            $t->execute();
            $teacher_ok = $t->get_result()->num_rows > 0;
            $t->close();
        }

        if (!$class_exists) {
            $error = "Please choose a valid class.";
        } elseif (!$teacher_ok) {
            $error = "Please choose a valid teacher.";
        } else {
            // Another subject of the same class must not use this name
            $check = $conn->prepare("SELECT subject_id FROM subjects WHERE subject_name = ? AND class_id = ? AND subject_id != ?");
            $check->bind_param("sii", $name, $class_id, $id);
            $check->execute();

            if ($check->get_result()->num_rows > 0) {
                $error = "This subject is already added to this class.";
            } else {
                $update = $conn->prepare("UPDATE subjects SET subject_name = ?, class_id = ?, teacher_id = ? WHERE subject_id = ?");
                $update->bind_param("siii", $name, $class_id, $teacher_id, $id);

                if ($update->execute()) {
                    header("Location: subjects.php?class_id=" . $class_id . "&msg=Subject updated successfully.");
                    exit();
                } else {
                    $error = "Could not update the subject. Please try again.";
                }
                $update->close();
            }
            $check->close();
        }
    }
}

$classes  = $conn->query("SELECT class_id, class_name, section FROM classes ORDER BY CAST(class_name AS UNSIGNED), class_name, section");
$teachers = $conn->query("SELECT teacher_id, full_name, subject FROM teachers ORDER BY full_name");

$page_title = "Edit Subject";
$page_sub   = "Change the subject, class or teacher.";
$active     = "subject";
include("../users/header.php");
?>

<div class="row">
    <div class="col-lg-7">
        <a href="subjects.php" class="text-decoration-none text-muted small"><i class="bi bi-arrow-left me-1"></i> Back to Subjects</a>
        <h4 class="fw-bold mt-2 mb-3">Edit Subject</h4>

        <?php if ($error): ?>
            <div class="alert alert-danger border-0 shadow-sm mb-4"><i class="bi bi-exclamation-triangle-fill me-2"></i><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

        <div class="panel-card p-4 shadow-sm">
            <form action="update_subject.php" method="POST" class="needs-validation" novalidate>
                <input type="hidden" name="id" value="<?= $subject['subject_id'] ?>">

                <div class="mb-3">
                    <label class="form-label fw-medium">Subject Name</label>
                    <input type="text" name="subject_name" class="form-control" maxlength="100" value="<?= htmlspecialchars($subject['subject_name']) ?>" required>
                    <div class="invalid-feedback">Please enter the subject name.</div>
                </div>

                <div class="mb-3">
                    <label class="form-label fw-medium">Class</label>
                    <select name="class_id" class="form-select" required>
                        <?php while ($c = $classes->fetch_assoc()): ?>
                            <option value="<?= $c['class_id'] ?>" <?= $subject['class_id'] == $c['class_id'] ? 'selected' : '' ?>>
                                Class <?= htmlspecialchars($c['class_name']) ?> - Section <?= htmlspecialchars($c['section']) ?>
                            </option>
                        <?php endwhile; ?>
                    </select>
                </div>

                <div class="mb-4">
                    <label class="form-label fw-medium">Teacher (optional)</label>
                    <select name="teacher_id" class="form-select">
                        <option value="0">Not assigned</option>
                        <?php while ($t = $teachers->fetch_assoc()): ?>
                            <option value="<?= $t['teacher_id'] ?>" <?= $subject['teacher_id'] == $t['teacher_id'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($t['full_name']) ?> (<?= htmlspecialchars($t['subject']) ?>)
                            </option>
                        <?php endwhile; ?>
                    </select>
                </div>

                <div class="d-flex justify-content-end gap-2">
                    <a href="subjects.php" class="btn btn-light border px-4">Cancel</a>
                    <button type="submit" class="btn btn-primary px-4"><i class="bi bi-check-lg me-1"></i> Update Subject</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php include("../users/footer.php"); ?>