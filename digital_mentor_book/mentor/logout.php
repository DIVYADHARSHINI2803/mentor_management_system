<?php
// Mentor specific logout
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Log mentor logout
if (isset($_SESSION['user_id']) && isset($_SESSION['username'])) {
    // Optional: Add to activity log
    error_log("Mentor {$_SESSION['username']} logged out at " . date('Y-m-d H:i:s'));
}

// Clear all session variables
$_SESSION = array();

// Destroy session cookie
if (isset($_COOKIE[session_name()])) {
    setcookie(session_name(), '', time()-3600, '/');
}

// Destroy session
session_destroy();

// Redirect to login page
header("Location: ../login.php?message=loggedout");
exit();
?>