<?php
session_start();
include("dbconnection.php");
include("../users/log_activity.php");

// Auth check (every logged in user has a profile)
if (!isset($_SESSION['user_id'])) {
    header("Location: ../users/index.php");
    exit();
}

$uid   = intval($_SESSION['user_id']);
$error = "";

// Get this account
$stmt = $conn->prepare("SELECT user_id, full_name, username, email, role, status, last_login, created_at FROM users WHERE user_id = ?");
$stmt->bind_param("i", $uid);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$user) {
    header("Location: ../users/logout.php");
    exit();
}

// Save the new name
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['full_name'] ?? '');

    if (empty($name)) {
        $error = "The name cannot be empty.";
    } elseif (strlen($name) > 100) {
        $error = "The name must be 100 characters or less.";
    } else {
        $update = $conn->prepare("UPDATE users SET full_name = ? WHERE user_id = ?");
        $update->bind_param("si", $name, $uid);

        if ($update->execute()) {
            $_SESSION['full_name'] = $name;   // so the top bar shows the new name
            log_activity($conn, "Updated own profile name to: " . $name);
            header("Location: profile.php?msg=Profile updated successfully.");
            exit();
        } else {
            $error = "Could not update the profile. Please try again.";
        }
        $update->close();
    }
}

// The linked student or teacher record (matched by email)
$linked = null;
if (strtolower($user['role']) == 'student') {
    $stmt = $conn->prepare("SELECT roll_no, class, section FROM students WHERE email = ?");
    $stmt->bind_param("s", $user['email']);
    $stmt->execute();
    $linked = $stmt->get_result()->fetch_assoc();
    $stmt->close();
} elseif (strtolower($user['role']) == 'teacher') {
    $stmt = $conn->prepare("SELECT subject FROM teachers WHERE email = ?");
    $stmt->bind_param("s", $user['email']);
    $stmt->execute();
    $linked = $stmt->get_result()->fetch_assoc();
    $stmt->close();
}

$page_title = "My Profile";
$page_sub   = "Your account details.";
$active     = "profile";
include("../users/header.php");
?>

<!-- Flash Messages -->
<?php if (isset($_GET['msg'])): ?>
    <div class="alert alert-success alert-dismissible fade show border-0 shadow-sm mb-4" role="alert">
        <i class="bi bi-check-circle-fill me-2"></i><?= htmlspecialchars($_GET['msg']) ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
<?php endif; ?>

<?php if ($error): ?>
    <div class="alert alert-danger border-0 shadow-sm mb-4"><i class="bi bi-exclamation-triangle-fill me-2"></i><?= htmlspecialchars($error) ?></div>
<?php endif; ?>

<div class="row g-3">

    <!-- Account card -->
    <div class="col-lg-5">
        <div class="panel-card shadow-sm p-4 h-100">
            <div class="text-center mb-4 pb-3 border-bottom">
                <div class="bg-primary text-white fw-bold rounded-circle d-inline-flex align-items-center justify-content-center mb-2" style="width: 80px; height: 80px; font-size: 2rem;">
                    <?= strtoupper(substr($user['full_name'], 0, 1)) ?>
                </div>
                <h5 class="fw-bold mb-1"><?= htmlspecialchars($user['full_name']) ?></h5>
                <span class="badge bg-primary-subtle text-primary border border-primary-subtle"><?= htmlspecialchars($user['role']) ?></span>
            </div>

            <div class="mb-3">
                <label class="text-muted small d-block">Username</label>
                <span class="fw-medium"><?= htmlspecialchars($user['username']) ?></span>
            </div>
            <div class="mb-3">
                <label class="text-muted small d-block">Email</label>
                <span class="fw-medium"><?= htmlspecialchars($user['email']) ?></span>
            </div>

            <?php if ($linked && isset($linked['roll_no'])): ?>
                <div class="mb-3">
                    <label class="text-muted small d-block">Roll Number and Class</label>
                    <span class="fw-medium"><code><?= htmlspecialchars($linked['roll_no']) ?></code> &middot; Class <?= htmlspecialchars($linked['class']) ?>-<?= htmlspecialchars($linked['section']) ?></span>
                </div>
            <?php elseif ($linked && isset($linked['subject'])): ?>
                <div class="mb-3">
                    <label class="text-muted small d-block">Subject</label>
                    <span class="fw-medium"><?= htmlspecialchars($linked['subject']) ?></span>
                </div>
            <?php endif; ?>

            <div class="mb-3">
                <label class="text-muted small d-block">Account Status</label>
                <span class="badge <?= $user['status'] == 'active' ? 'bg-success' : 'bg-secondary' ?>"><?= htmlspecialchars(ucfirst($user['status'])) ?></span>
            </div>
            <div class="mb-3">
                <label class="text-muted small d-block">Member Since</label>
                <span class="fw-medium"><?= date("d M Y", strtotime($user['created_at'])) ?></span>
            </div>
            <div>
                <label class="text-muted small d-block">Last Login</label>
                <span class="fw-medium"><?= $user['last_login'] ? date("d M Y, h:i A", strtotime($user['last_login'])) : 'Not recorded yet' ?></span>
            </div>
        </div>
    </div>

    <!-- Edit name -->
    <div class="col-lg-7">
        <div class="panel-card shadow-sm p-4 mb-3">
            <h6 class="fw-bold mb-3">Edit Profile</h6>
            <form action="profile.php" method="POST" class="needs-validation" novalidate>
                <div class="mb-3">
                    <label class="form-label fw-medium">Full Name</label>
                    <input type="text" name="full_name" class="form-control" maxlength="100" value="<?= htmlspecialchars($user['full_name']) ?>" required>
                    <div class="invalid-feedback">Please enter your name.</div>
                </div>
                <div class="mb-4">
                    <label class="form-label fw-medium">Email</label>
                    <input type="email" class="form-control" value="<?= htmlspecialchars($user['email']) ?>" disabled>
                    <div class="form-text">The email links your login to your student or teacher record, so only the administrator can change it.</div>
                </div>
                <div class="d-flex justify-content-end">
                    <button type="submit" class="btn btn-primary px-4"><i class="bi bi-check-lg me-1"></i> Save Changes</button>
                </div>
            </form>
        </div>

        <div class="panel-card shadow-sm p-4 d-flex justify-content-between align-items-center">
            <div>
                <h6 class="fw-bold mb-1">Password</h6>
                <span class="text-muted small">Change it regularly and never share it.</span>
            </div>
            <a href="change_password.php" class="btn btn-outline-primary"><i class="bi bi-key me-1"></i> Change Password</a>
        </div>
    </div>
</div>

<?php include("../users/footer.php"); ?>