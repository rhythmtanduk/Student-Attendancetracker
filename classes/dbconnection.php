<?php

$servername = "localhost";
$username = "root";
$password = "";
$dbname = "attendancedb";

$conn = new mysqli($servername,$username,$password,$dbname);

if($conn->connect_error){
    die("Database Connection Failed!".$conn->connect_error);
}

// Tell MySQL which user is logged in. The activity log triggers use this.
if (isset($_SESSION['user_id'])) {
    $conn->query("SET @app_user = " . intval($_SESSION['user_id']));
}

?>