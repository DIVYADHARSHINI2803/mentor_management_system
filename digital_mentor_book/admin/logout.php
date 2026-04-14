<?php
// Admin specific logout
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Log admin logout for security
if (isset($_SESSION['user_id']) && isset($_SESSION['username'])) {
    // Optional: Insert into admin_logs table
    /*
    require_once '../includes/db_connect.php';
    $stmt = $pdo->prepare("INSERT INTO admin_logs (admin_id, action, ip_address, timestamp) VALUES (?, 'logout', ?, NOW())");
    $stmt->execute([$_SESSION['user_id'], $_SERVER['REMOTE_ADDR']]);
    */
}

// Clear all session variables
$_SESSION = array();

// Destroy session cookie
if (isset($_COOKIE[session_name()])) {
    setcookie(session_name(), '', time()-3600, '/');
}

// Destroy session
session_destroy();

// Clear any admin-specific cookies
if (isset($_COOKIE['admin_preferences'])) {
    setcookie('admin_preferences', '', time()-3600, '/');
}

// Redirect to login page
header("Location: ../login.php?message=loggedout");
exit();
?>