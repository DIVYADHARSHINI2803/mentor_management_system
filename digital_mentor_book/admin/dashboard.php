<?php
require_once '../includes/session.php';
require_once '../includes/db_connect.php';

if($_SESSION['role'] != 'admin') {
    header("Location: ../login.php");
    exit();
}

// Get statistics
$stmt = $pdo->query("SELECT COUNT(*) as total FROM users WHERE role = 'student'");
$total_students = $stmt->fetch()['total'];

$stmt = $pdo->query("SELECT COUNT(*) as total FROM users WHERE role = 'mentor'");
$total_mentors = $stmt->fetch()['total'];

$stmt = $pdo->query("SELECT COUNT(*) as total FROM achievements WHERE verified_by_mentor = FALSE");
$pending_achievements = $stmt->fetch()['total'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard</title>
    <link rel="stylesheet" href="../assets/css/style.css">
</head>
<body>
    <div class="dashboard">
        <div class="sidebar">
            <h3>📚 Admin Panel</h3>
            <a href="dashboard.php">🏠 Dashboard</a>
            <a href="manage_users.php">👥 Manage Users</a>
            <a href="assign_mentor.php">🤝 Assign Mentor</a>
            <a href="view_reports.php">📊 View Reports</a>
            <a href="logout.php">🚪 Logout</a>
        </div>
        <div class="main-content">
            <h2>Admin Dashboard</h2>
            
            <div class="card">
                <h3>📊 System Statistics</h3>
                <p><strong>Total Students:</strong> <?php echo $total_students; ?></p>
                <p><strong>Total Mentors:</strong> <?php echo $total_mentors; ?></p>
                <p><strong>Pending Achievements:</strong> <?php echo $pending_achievements; ?></p>
            </div>
        </div>
    </div>
</body>
</html>