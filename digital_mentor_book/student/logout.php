<?php
// Student specific logout
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Log the logout activity (optional - for audit trail)
if (isset($_SESSION['user_id']) && isset($_SESSION['username'])) {
    // You can add logout log here if you have a logs table
    // $log_stmt = $pdo->prepare("INSERT INTO user_logs (user_id, action, timestamp) VALUES (?, 'logout', NOW())");
    // $log_stmt->execute([$_SESSION['user_id']]);
}

// Clear all session variables
$_SESSION = array();

// Destroy session cookie
if (isset($_COOKIE[session_name()])) {
    setcookie(session_name(), '', time()-3600, '/');
}

// Destroy session
session_destroy();

// Redirect to login page with message
header("Location: ../login.php?message=loggedout");
exit();
?>