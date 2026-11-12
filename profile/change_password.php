<?php
session_start();
include("dbconnection.php");
include("../users/log_activity.php");

// Auth check
if (!isset($_SESSION['user_id'])) {
    header("Location: ../users/index.php");
    exit();
}

$uid   = intval($_SESSION['user_id']);
$error = "";

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $current = $_POST['current_password'] ?? '';
    $new     = $_POST['new_password'] ?? '';
    $confirm = $_POST['confirm_password'] ?? '';

    // The saved (hashed) password of this user
    $stmt = $conn->prepare("SELECT password FROM users WHERE user_id = ?");
    $stmt->bind_param("i", $uid);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if ($current === '' || $new === '' || $confirm === '') {
        $error = "All fields are required.";
    } elseif (!$row || !password_verify($current, $row['password'])) {
        $error = "Your current password is wrong.";
    } elseif (strlen($new) < 6) {
        $error = "The new password must be at least 6 characters.";
    } elseif ($new !== $confirm) {
        $error = "The new password and the confirmation do not match.";
    } elseif (password_verify($new, $row['password'])) {
        $error = "The new password must be different from the current one.";
    } else {
        $hash = password_hash($new, PASSWORD_BCRYPT);

        $update = $conn->prepare("UPDATE users SET password = ? WHERE user_id = ?");
        $update->bind_param("si", $hash, $uid);

        if ($update->execute()) {
            session_regenerate_id(true);
            log_activity($conn, "Changed own password");
            header("Location: profile.php?msg=Password changed successfully.");
            exit();
        } else {
            $error = "Could not change the password. Please try again.";
        }
        $update->close();
    }
}

$page_title = "Change Password";
$page_sub   = "Choose a new password for your account.";
$active     = "profile";
include("../users/header.php");
?>

<div class="row">
    <div class="col-lg-6">
        <a href="profile.php" class="text-decoration-none text-muted small"><i class="bi bi-arrow-left me-1"></i> Back to Profile</a>
        <h4 class="fw-bold mt-2 mb-3">Change Password</h4>

        <?php if ($error): ?>
            <div class="alert alert-danger border-0 shadow-sm mb-4"><i class="bi bi-exclamation-triangle-fill me-2"></i><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

        <div class="panel-card p-4 shadow-sm">
            <form action="change_password.php" method="POST" class="needs-validation" novalidate>
                <div class="mb-3">
                    <label class="form-label fw-medium">Current Password</label>
                    <input type="password" name="current_password" class="form-control pw" required>
                    <div class="invalid-feedback">Please enter your current password.</div>
                </div>

                <div class="mb-2">
                    <label class="form-label fw-medium">New Password</label>
                    <input type="password" name="new_password" id="newPassword" class="form-control pw" minlength="6" required>
                    <div class="invalid-feedback">The new password needs at least 6 characters.</div>
                </div>
                <div class="progress mb-1" style="height: 6px;">
                    <div id="strengthBar" class="progress-bar" style="width: 0%"></div>
                </div>
                <div class="small text-muted mb-3">Strength: <span id="strengthText">-</span></div>

                <div class="mb-2">
                    <label class="form-label fw-medium">Confirm New Password</label>
                    <input type="password" name="confirm_password" id="confirmPassword" class="form-control pw" required>
                    <div class="invalid-feedback">Please confirm the new password.</div>
                </div>
                <div id="matchText" class="small mb-3"></div>

                <div class="form-check mb-4">
                    <input class="form-check-input" type="checkbox" id="showAll">
                    <label class="form-check-label small" for="showAll">Show passwords</label>
                </div>

                <div class="d-flex justify-content-end gap-2">
                    <a href="profile.php" class="btn btn-light border px-4">Cancel</a>
                    <button type="submit" class="btn btn-primary px-4"><i class="bi bi-check-lg me-1"></i> Change Password</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
    var newPw     = document.getElementById('newPassword');
    var confirmPw = document.getElementById('confirmPassword');
    var bar       = document.getElementById('strengthBar');
    var barText   = document.getElementById('strengthText');
    var matchText = document.getElementById('matchText');

    // Strength: one point for each good habit
    function checkStrength() {
        var p = newPw.value;
        var score = 0;
        if (p.length >= 6)  { score++; }
        if (p.length >= 10) { score++; }
        if (/[a-z]/.test(p) && /[A-Z]/.test(p)) { score++; }
        if (/[0-9]/.test(p)) { score++; }
        if (/[^A-Za-z0-9]/.test(p)) { score++; }

        var levels = [
            { text: '-',         color: 'bg-secondary', width: 0 },
            { text: 'Very weak', color: 'bg-danger',    width: 20 },
            { text: 'Weak',      color: 'bg-danger',    width: 40 },
            { text: 'Okay',      color: 'bg-warning',   width: 60 },
            { text: 'Good',      color: 'bg-success',   width: 80 },
            { text: 'Strong',    color: 'bg-success',   width: 100 }
        ];
        var level = (p.length === 0) ? levels[0] : levels[Math.max(score, 1)];

        bar.className = 'progress-bar ' + level.color;
        bar.style.width = level.width + '%';
        barText.textContent = level.text;
    }

    // Do the two new passwords match?
    function checkMatch() {
        if (confirmPw.value === '') { matchText.textContent = ''; return; }
        if (confirmPw.value === newPw.value) {
            matchText.innerHTML = '<span class="text-success"><i class="bi bi-check-circle"></i> The passwords match</span>';
        } else {
            matchText.innerHTML = '<span class="text-danger"><i class="bi bi-x-circle"></i> The passwords do not match</span>';
        }
    }

    newPw.addEventListener('input', function () { checkStrength(); checkMatch(); });
    confirmPw.addEventListener('input', checkMatch);

    // Show / hide all three boxes
    document.getElementById('showAll').addEventListener('change', function (e) {
        document.querySelectorAll('.pw').forEach(function (input) {
            input.type = e.target.checked ? 'text' : 'password';
        });
    });
</script>

<?php include("../users/footer.php"); ?>