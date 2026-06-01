<?php
session_start();

// Database credentials
define('DB_SERVER', 'localhost');
define('DB_USERNAME', 'root'); 
define('DB_PASSWORD', '');     
define('DB_NAME', 'lfs_db');

// Attempt to connect to MySQL database
$conn = new mysqli(DB_SERVER, DB_USERNAME, DB_PASSWORD, DB_NAME);

// Check connection
if($conn === false){
    die("ERROR: Could not connect. " . $conn->connect_error);
}

// AUDIT LOG FUNCTION: Logs actions for Admin review
function log_action($user_id, $action) {
    global $conn;
    if ($user_id > 0) {
        $ip = $_SERVER['REMOTE_ADDR'];
        $sql = "INSERT INTO audit_logs (user_id, action, ip_address) VALUES (?, ?, ?)";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("iss", $user_id, $action, $ip);
        $stmt->execute();
        $stmt->close();
    }
}

// HELPER FUNCTION: Check if the user is an Admin (Role-Based Access Control)
function is_admin() {
    return (isset($_SESSION['loggedin']) && $_SESSION['loggedin'] === true && $_SESSION['role'] === 'Admin');
}
?>