<?php
session_start();
include("dbconnection.php");
include("log_activity.php");

// Write the logout in the activity log (before the session is destroyed)
if (isset($_SESSION['user_id'])) {
    log_activity($conn, "Logged out");
}

// Unset all session variables
$_SESSION = array();

// Destroy session cookie in browser
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(
        session_name(), 
        '', 
        time() - 42000,
        $params["path"], 
        $params["domain"],
        $params["secure"], 
        $params["httponly"]
    );
}

// Destroy session
session_destroy();

// Redirect back to login page
header("Location: index.php");
exit();
?>