<?php
// header.php - the top part that every page shares (sidebar + top bar).
// Use it like the w3schools "PHP include" lesson:  include("../users/header.php");
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
        <?php endif; ?>

        <?php if ($user_role === 'student'): ?>
            <li class="menu-header">Student Area</li>
            <li>
                <a href="../attendance/attendance.php" class="<?= $active == 'attendance' ? 'active' : '' ?>">
                    <i class="bi bi-calendar2-week"></i><span>My Attendance</span>
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

        <div class="d-flex align-items-center gap-2">
            <div class="bg-primary text-white fw-bold rounded-circle d-flex align-items-center justify-content-center" style="width:38px; height:38px; font-size: 0.9rem;">
                <?= strtoupper(substr($full_name, 0, 1)) ?>
            </div>
            <div class="d-none d-md-block">
                <div class="fw-bold small lh-1 text-dark mb-1"><?= $full_name ?></div>
                <div class="text-muted lh-1 text-capitalize" style="font-size: 0.75rem;"><?= $user_role ?></div>
            </div>
        </div>
    </div>

    <!-- MAIN BODY CONTAINER (closed in footer.php) -->
    <div class="p-4">
