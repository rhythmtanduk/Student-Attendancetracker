<?php
// header.php - the top part that every page shares (sidebar + top bar).
// The page must set these variables BEFORE including this file:
//   $page_title = "Students";      (title shown on the top bar)
//   $page_sub   = "Small text";    (optional)
//   $active     = "students";      (which sidebar link is highlighted)

$user_role = strtolower($_SESSION['role'] ?? 'student');
$full_name = htmlspecialchars($_SESSION['full_name'] ?? 'User');
if (!isset($page_title)) { $page_title = "Attendance Tracker"; }
if (!isset($page_sub))   { $page_sub = ""; }
if (!isset($active))     { $active = ""; }
$is_admin = ($user_role === 'admin' || $user_role === 'administrator');

// Number of pending leave requests (red number in the sidebar for admin and teacher)
$pending_leaves = 0;
if (($is_admin || $user_role === 'teacher') && isset($conn)) {
    $hdr_r = $conn->query("SELECT COUNT(*) AS total FROM leave_requests WHERE status = 'Pending'");
    if ($hdr_r) { $pending_leaves = (int)$hdr_r->fetch_assoc()['total']; }
}

// Notifications for the bell
$unread_count = 0;
$latest_notes = [];
if (isset($conn) && isset($_SESSION['user_id'])) {
    $hdr_uid = intval($_SESSION['user_id']);
    $hdr_r = $conn->query("SELECT COUNT(*) AS total FROM notifications WHERE user_id = $hdr_uid AND is_read = 0");
    if ($hdr_r) { $unread_count = (int)$hdr_r->fetch_assoc()['total']; }
    $hdr_r = $conn->query("SELECT notification_id, message, is_read, created_at FROM notifications WHERE user_id = $hdr_uid ORDER BY created_at DESC, notification_id DESC LIMIT 5");
    if ($hdr_r) { $latest_notes = $hdr_r->fetch_all(MYSQLI_ASSOC); }
}
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= htmlspecialchars($page_title) ?> - Attendance Tracker</title>

    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Bootstrap Icons -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <!-- Google Font -->
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
    <!-- Our own CSS -->
    <link href="../users/style.css" rel="stylesheet">
</head>
<body>

<!-- SIDEBAR -->
<aside id="sidebar">
    <div class="sidebar-brand d-flex align-items-center gap-2">
        <div class="bg-primary text-white rounded p-1 d-flex align-items-center justify-content-center" style="width: 28px; height: 28px;">
            <i class="bi bi-shield-check fs-6"></i>
        </div>
        <div>
            <span class="d-block lh-1">ClassTrack</span>
            <small class="text-muted fw-normal" style="font-size: 0.7rem;">Student Attendance System</small>
        </div>
    </div>

    <ul class="sidebar-menu">
        <li class="menu-header">Overview</li>
        <li>
            <a href="../users/dashboard.php" class="<?= $active == 'dashboard' ? 'active' : '' ?>">
                <i class="bi bi-grid-1x2"></i><span>Dashboard</span>
            </a>
        </li>
        <li>
            <a href="../holiday/holidays.php" class="<?= $active == 'holiday' ? 'active' : '' ?>">
                <i class="bi bi-calendar-event"></i><span>Holidays</span>
            </a>
        </li>

        <?php if ($is_admin): ?>
            <li class="menu-header">Management</li>
            <li>
                <a href="../student/students.php" class="<?= $active == 'students' ? 'active' : '' ?>">
                    <i class="bi bi-people"></i><span>Students</span>
                </a>
            </li>
            <li>
                <a href="../teacher/teachers.php" class="<?= $active == 'teachers' ? 'active' : '' ?>">
                    <i class="bi bi-person-badge"></i><span>Teachers</span>
                </a>
            </li>
            <li>
                <a href="../log/logs.php" class="<?= $active == 'log' ? 'active' : '' ?>">
                    <i class="bi bi-clock-history"></i><span>Activity Log</span>
                </a>
            </li>
        <?php endif; ?>

        <?php if ($is_admin || $user_role === 'teacher'): ?>
            <li class="menu-header">Academic</li>
            <li>
                <a href="../attendance/attendance.php" class="<?= $active == 'attendance' ? 'active' : '' ?>">
                    <i class="bi bi-calendar-check"></i><span>Attendance</span>
                </a>
            </li>
            <li>
                <a href="../report/report.php" class="<?= $active == 'report' ? 'active' : '' ?>">
                    <i class="bi bi-bar-chart"></i><span>Reports</span>
                </a>
            </li>
            <li>
                <a href="../classes/classes.php" class="<?= $active == 'classes' ? 'active' : '' ?>">
                    <i class="bi bi-door-open"></i><span>Classes</span>
                </a>
            </li>
            <li>
                <a href="../subject/subjects.php" class="<?= $active == 'subject' ? 'active' : '' ?>">
                    <i class="bi bi-journal-bookmark"></i><span>Subjects</span>
                </a>
            </li>
            <li>
                <a href="../leave/leave.php" class="<?= $active == 'leave' ? 'active' : '' ?>">
                    <i class="bi bi-envelope-paper"></i><span>Leave Requests</span>
                    <?php if ($pending_leaves > 0): ?>
                        <span class="badge bg-danger ms-auto"><?= $pending_leaves ?></span>
                    <?php endif; ?>
                </a>
            </li>
        <?php endif; ?>

        <?php if ($user_role === 'student'): ?>
            <li class="menu-header">Student Area</li>
            <li>
                <a href="../attendance/attendance.php" class="<?= $active == 'attendance' ? 'active' : '' ?>">
                    <i class="bi bi-calendar2-week"></i><span>My Attendance</span>
                </a>
            </li>
            <li>
                <a href="../leave/leave.php" class="<?= $active == 'leave' ? 'active' : '' ?>">
                    <i class="bi bi-envelope-paper"></i><span>My Leave</span>
                </a>
            </li>
        <?php endif; ?>

        <li class="menu-header">System</li>
        <li>
            <a href="../users/logout.php" onclick="return confirm('Are you sure you want to log out?');" class="text-danger">
                <i class="bi bi-box-arrow-right"></i><span>Logout</span>
            </a>
        </li>
    </ul>
</aside>

<!-- MAIN CONTENT WRAPPER -->
<div id="main-content">

    <!-- TOP BAR -->
    <div class="top-navbar d-flex align-items-center justify-content-between">

        <!-- Left side: menu button + page title -->
        <div class="d-flex align-items-center gap-3">
            <button class="btn btn-sm btn-light border d-lg-none" id="sidebarToggle">
                <i class="bi bi-list fs-5"></i>
            </button>
            <div>
                <h5 class="fw-bold mb-0 text-dark"><?= htmlspecialchars($page_title) ?></h5>
                <?php if ($page_sub != ""): ?>
                    <small class="text-muted"><?= htmlspecialchars($page_sub) ?></small>
                <?php endif; ?>
            </div>
        </div>

        <!-- Right side: bell + user menu -->
        <div class="d-flex align-items-center gap-3">

            <!-- Notification bell -->
            <div class="dropdown">
                <button class="btn btn-light border rounded-circle position-relative p-0 d-flex align-items-center justify-content-center"
                        style="width:38px; height:38px;" data-bs-toggle="dropdown" aria-expanded="false" title="Notifications">
                    <i class="bi bi-bell"></i>
                    <span id="bellBadge" class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger"
                          <?= $unread_count == 0 ? 'style="display:none"' : '' ?>><?= $unread_count ?></span>
                </button>
                <div class="dropdown-menu dropdown-menu-end shadow border-0 p-0" style="width: 340px; max-width: 90vw;">
                    <div class="d-flex justify-content-between align-items-center px-3 py-2 border-bottom">
                        <span class="fw-bold">Notifications</span>
                        <a href="../notification/mark_all_read.php" class="small text-decoration-none">Mark all read</a>
                    </div>
                    <?php if (empty($latest_notes)): ?>
                        <div class="text-center text-muted small py-4">No notifications yet.</div>
                    <?php else: ?>
                        <?php foreach ($latest_notes as $n): ?>
                            <a href="../notification/open_notification.php?id=<?= $n['notification_id'] ?>"
                               class="dropdown-item d-block px-3 py-2 border-bottom <?= $n['is_read'] ? '' : 'bg-primary-subtle' ?>" style="white-space: normal;">
                                <div class="small <?= $n['is_read'] ? '' : 'fw-semibold' ?>"><?= htmlspecialchars($n['message']) ?></div>
                                <div class="text-muted" style="font-size: 0.72rem;"><?= date("d M, h:i A", strtotime($n['created_at'])) ?></div>
                            </a>
                        <?php endforeach; ?>
                    <?php endif; ?>
                    <a href="../notification/notifications.php" class="dropdown-item text-center small text-primary py-2">View all notifications</a>
                </div>
            </div>

            <!-- User menu -->
            <div class="dropdown">
                <a href="#" class="d-flex align-items-center gap-2 text-decoration-none" data-bs-toggle="dropdown" aria-expanded="false">
                    <div class="bg-primary text-white fw-bold rounded-circle d-flex align-items-center justify-content-center" style="width:38px; height:38px; font-size: 0.9rem;">
                        <?= strtoupper(substr($full_name, 0, 1)) ?>
                    </div>
                    <div class="d-none d-md-block text-start">
                        <div class="fw-bold small lh-1 text-dark mb-1"><?= $full_name ?></div>
                        <div class="text-muted lh-1 text-capitalize" style="font-size: 0.75rem;"><?= $user_role ?></div>
                    </div>
                    <i class="bi bi-chevron-down text-muted small d-none d-md-inline"></i>
                </a>
                <ul class="dropdown-menu dropdown-menu-end shadow border-0">
                    <li><a class="dropdown-item" href="../profile/profile.php"><i class="bi bi-person me-2"></i>My Profile</a></li>
                    <li><a class="dropdown-item" href="../profile/change_password.php"><i class="bi bi-key me-2"></i>Change Password</a></li>
                    <li><hr class="dropdown-divider"></li>
                    <li><a class="dropdown-item text-danger" href="../users/logout.php" onclick="return confirm('Are you sure you want to log out?');"><i class="bi bi-box-arrow-right me-2"></i>Logout</a></li>
                </ul>
            </div>

        </div>
    </div>

    <!-- MAIN BODY CONTAINER (closed in footer.php) -->
    <div class="p-4">