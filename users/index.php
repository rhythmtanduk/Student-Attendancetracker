<?php
session_start();

// Make sure the relative path to dbconnection.php is correct
include("dbconnection.php");

// REDIRECT FUNCTION (Routes authenticated users to users/dashboard.php)
function redirectByRole() {
    // If index.php is inside the 'auth' folder, use '../users/dashboard.php'
    // If index.php is in the root folder, change this to 'users/dashboard.php'
    header("Location: ../users/dashboard.php");
    exit();
}

// Check if already logged in (Redirects logged-in users away from login page)
if (isset($_SESSION['user_id'])) {
    redirectByRole();
}

$login_error = "";
$reg_error   = "";
$reg_success = "";
$view_mode   = isset($_GET['view']) && $_GET['view'] === 'register' ? 'register' : 'login';

// PROCESS FORM SUBMISSIONS
if ($_SERVER['REQUEST_METHOD'] == "POST") {

    // --- HANDLE LOGIN ---
    if (isset($_POST['action']) && $_POST['action'] === 'login') {
        $view_mode = "login";
        $username  = trim($_POST['username']);
        $password  = $_POST['password'];

        if (empty($username) || empty($password)) {
            $login_error = "Please fill in all fields.";
        } else {
            $stmt = $conn->prepare("SELECT user_id, full_name, username, password, role FROM users WHERE username = ?");
            $stmt->bind_param("s", $username);
            $stmt->execute();
            $result = $stmt->get_result();

            if ($result->num_rows === 1) {
                $row = $result->fetch_assoc();

                // Supports bcrypt password verification or fallback plain text
                if (password_verify($password, $row['password'])) {
                    $_SESSION['user_id']   = $row['user_id'];
                    $_SESSION['full_name'] = $row['full_name'];
                    $_SESSION['username']  = $row['username'];
                    $_SESSION['role']      = $row['role']; 

                    redirectByRole();
                } else {
                    $login_error = "Invalid Username or Password";
                }
            } else {
                $login_error = "Invalid Username or Password";
            }
            $stmt->close();
        }
    }

    // --- HANDLE REGISTRATION ---
    elseif (isset($_POST['action']) && $_POST['action'] === 'register') {
        $view_mode = "register";
        $full_name = trim($_POST['full_name']);
        $username  = trim($_POST['username']);
        $email     = trim($_POST['email']);
        $password  = $_POST['password'];
        $role      = $_POST['role'];

        if (empty($full_name) || empty($username) || empty($email) || empty($password) || empty($role)) {
            $reg_error = "Please fill in all fields.";
        } else {
            $check_stmt = $conn->prepare("SELECT user_id FROM users WHERE username = ? OR email = ?");
            $check_stmt->bind_param("ss", $username, $email);
            $check_stmt->execute();
            $check_result = $check_stmt->get_result();

            if ($check_result->num_rows > 0) {
                $reg_error = "Username or Email already exists!";
            } else {
                $hashed_password = password_hash($password, PASSWORD_BCRYPT);
                $stmt = $conn->prepare("INSERT INTO users (full_name, username, email, password, role) VALUES (?, ?, ?, ?, ?)");
                $stmt->bind_param("sssss", $full_name, $username, $email, $hashed_password, $role);

                if ($stmt->execute()) {
                    $reg_success = "Account created successfully! You can now log in.";
                    $view_mode   = "login";
                } else {
                    $reg_error = "Registration failed. Try again.";
                }
                $stmt->close();
            }
            $check_stmt->close();
        }
    }
}
?>

<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Attendance Tracker - Sign In</title>
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Bootstrap Icons -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    
    <style>
        body {
            background: linear-gradient(135deg, #eef2f3 0%, #8e9eab 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }

        .auth-card {
            border: none;
            border-radius: 16px;
            overflow: hidden;
            box-shadow: 0 15px 35px rgba(0, 0, 0, 0.15);
        }

        .auth-left {
            background: linear-gradient(135deg, #0d6efd 0%, #0a58ca 100%);
            color: white;
            padding: 3rem 2rem;
            display: flex;
            flex-direction: column;
            justify-content: center;
            align-items: center;
            text-align: center;
        }

        .auth-illustration {
            max-width: 85%;
            height: auto;
            margin-bottom: 1.5rem;
            filter: drop-shadow(0 8px 16px rgba(0, 0, 0, 0.2));
        }

        .auth-right {
            background: #ffffff;
            padding: 2.5rem;
        }

        .form-control, .form-select {
            border-radius: 8px;
            padding: 0.65rem 0.9rem;
            border: 1px solid #ced4da;
        }

        .form-control:focus, .form-select:focus {
            border-color: #0d6efd;
            box-shadow: 0 0 0 0.25rem rgba(13, 110, 253, 0.15);
        }

        .btn-custom {
            border-radius: 8px;
            padding: 0.7rem;
            font-weight: 600;
            letter-spacing: 0.3px;
        }

        .auth-toggle-link {
            color: #0d6efd;
            text-decoration: none;
            font-weight: 600;
        }

        .auth-toggle-link:hover {
            text-decoration: underline;
        }
    </style>
</head>
<body>

<div class="container py-5">
    <div class="row justify-content-center">
        <div class="col-xl-10 col-lg-11">
            <div class="card auth-card">
                <div class="row g-0">
                    
                    <!-- LEFT COLUMN -->
                    <div class="col-md-5 auth-left d-none d-md-flex">
                        <img src="https://img.freepik.com/free-vector/learning-concept-illustration_114360-618.jpg" 
                             alt="Attendance Tracking Illustration" 
                             class="auth-illustration rounded">
                        <h4 class="fw-bold mb-2">Attendance Portal</h4>
                        <p class="text-white-50 small mb-0">Streamlined tracking for Students, Teachers, and Administrators.</p>
                    </div>

                    <!-- RIGHT COLUMN: Forms -->
                    <div class="col-md-7 auth-right">
                        
                        <!-- LOGIN FORM -->
                        <div id="loginFormSection" style="display: <?= ($view_mode === 'login') ? 'block' : 'none' ?>;">
                            <div class="mb-4">
                                <h3 class="fw-bold text-dark mb-1">Welcome Back!</h3>
                                <p class="text-muted small">Please sign in to access your account dashboard.</p>
                            </div>

                            <?php if (!empty($login_error)): ?>
                                <div class="alert alert-danger py-2 small"><?= $login_error ?></div>
                            <?php endif; ?>

                            <?php if (!empty($reg_success)): ?>
                                <div class="alert alert-success py-2 small"><?= $reg_success ?></div>
                            <?php endif; ?>

                            <form method="post">
                                <input type="hidden" name="action" value="login">
                                
                                <div class="mb-3">
                                    <label class="form-label small fw-semibold">Username</label>
                                    <div class="input-group">
                                        <span class="input-group-text bg-light text-muted"><i class="bi bi-person"></i></span>
                                        <input type="text" name="username" class="form-control" placeholder="Enter username" required>
                                    </div>
                                </div>

                                <div class="mb-3">
                                    <label class="form-label small fw-semibold">Password</label>
                                    <div class="input-group">
                                        <span class="input-group-text bg-light text-muted"><i class="bi bi-lock"></i></span>
                                        <input type="password" name="password" class="form-control" placeholder="Enter password" required>
                                    </div>
                                </div>

                                <div class="d-grid mt-4">
                                    <button type="submit" class="btn btn-primary btn-custom">Sign In</button>
                                </div>
                            </form>

                            <div class="text-center mt-4 pt-2 border-top">
                                <span class="text-muted small">Don't have an account yet?</span>
                                <a href="#" id="showRegisterBtn" class="auth-toggle-link ms-1 small">Register Here</a>
                            </div>
                        </div>

                        <!-- REGISTER FORM -->
                        <div id="registerFormSection" style="display: <?= ($view_mode === 'register') ? 'block' : 'none' ?>;">
                            <div class="mb-3">
                                <h3 class="fw-bold text-dark mb-1">Create Account</h3>
                                <p class="text-muted small">Register to get started with the attendance system.</p>
                            </div>

                            <?php if (!empty($reg_error)): ?>
                                <div class="alert alert-danger py-2 small"><?= $reg_error ?></div>
                            <?php endif; ?>

                            <form method="post">
                                <input type="hidden" name="action" value="register">

                                <div class="mb-2">
                                    <label class="form-label small fw-semibold">Full Name</label>
                                    <input type="text" name="full_name" class="form-control" placeholder="Enter Full Name" required>
                                </div>

                                <div class="row g-2 mb-2">
                                    <div class="col-md-6">
                                        <label class="form-label small fw-semibold">Username</label>
                                        <input type="text" name="username" class="form-control" placeholder="Enter username" required>
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label small fw-semibold">Email</label>
                                        <input type="email" name="email" class="form-control" placeholder="Enter Email" required>
                                    </div>
                                </div>

                                <div class="mb-2">
                                    <label class="form-label small fw-semibold">Password</label>
                                    <input type="password" name="password" class="form-control" placeholder="Create a password" required>
                                </div>

                                <div class="mb-3">
                                    <label class="form-label small fw-semibold">Register As (Role)</label>
                                    <select name="role" class="form-select" required>
                                        <option value="">Select Role...</option>
                                        <option value="Student">Student</option>
                                        <option value="Teacher">Teacher</option>
                                        <option value="Administrator">Administrator</option>
                                    </select>
                                </div>

                                <div class="d-grid mt-3">
                                    <button type="submit" class="btn btn-success btn-custom">Create Account</button>
                                </div>
                            </form>

                            <div class="text-center mt-3 pt-2 border-top">
                                <span class="text-muted small">Already registered?</span>
                                <a href="#" id="showLoginBtn" class="auth-toggle-link ms-1 small">Sign In Here</a>
                            </div>
                        </div>

                    </div>
                </div>
            </div>
            <div class="text-center mt-3 text-muted">
                <small>BCA Project &mdash; Student Attendance Tracker System</small>
            </div>
        </div>
    </div>
</div>

<script>
    const loginSection = document.getElementById('loginFormSection');
    const registerSection = document.getElementById('registerFormSection');
    const showRegisterBtn = document.getElementById('showRegisterBtn');
    const showLoginBtn = document.getElementById('showLoginBtn');

    showRegisterBtn.addEventListener('click', function(e) {
        e.preventDefault();
        loginSection.style.display = 'none';
        registerSection.style.display = 'block';
    });

    showLoginBtn.addEventListener('click', function(e) {
        e.preventDefault();
        registerSection.style.display = 'none';
        loginSection.style.display = 'block';
    });
</script>
</body>
</html>