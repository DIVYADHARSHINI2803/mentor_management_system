<?php
require_once '../includes/session.php';
require_once '../includes/db_connect.php';

if($_SESSION['role'] != 'student') {
    header("Location: ../login.php");
    exit();
}

// Get student details
$stmt = $pdo->prepare("SELECT sd.*, u.full_name, u.email FROM student_details sd 
                       JOIN users u ON sd.user_id = u.id 
                       WHERE sd.user_id = ?");
$stmt->execute([$_SESSION['user_id']]);
$student = $stmt->fetch();

// Get recent achievements
$stmt2 = $pdo->prepare("SELECT * FROM achievements WHERE student_id = ? ORDER BY created_at DESC LIMIT 5");
$stmt2->execute([$student['id']]);
$achievements = $stmt2->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Student Dashboard - Digital Mentor Book</title>
    <link rel="stylesheet" href="../assets/css/style.css">
</head>
<body>
    <div class="dashboard">
        <div class="sidebar">
            <h3>📚 Mentor Book</h3>
            <a href="dashboard.php">🏠 Dashboard</a>
            <a href="profile.php">👤 My Profile</a>
            <a href="semester_marks.php">📊 Semester Marks</a>
            <a href="add_achievement.php">🏆 Add Achievement</a>
            <a href="upload_certificate.php">📎 Upload Certificate</a>
            <a href="logout.php">🚪 Logout</a>
        </div>
        <div class="main-content">
            <h2>Welcome, <?php echo $_SESSION['full_name']; ?>!</h2>
            
            <div class="card">
                <h3>📋 Student Information</h3>
                <p><strong>Roll Number:</strong> <?php echo $student['roll_number']; ?></p>
                <p><strong>Class:</strong> <?php echo $student['class'] ?: 'Not assigned yet'; ?></p>
                <p><strong>Section:</strong> <?php echo $student['section'] ?: 'Not assigned'; ?></p>
                <p><strong>Email:</strong> <?php echo $student['email']; ?></p>
            </div>
            
            <div class="card">
                <h3>🏆 Recent Achievements</h3>
                <?php if(count($achievements) > 0): ?>
                    <table>
                        <thead>
                            <tr><th>Title</th><th>Status</th><th>Date</th></tr>
                        </thead>
                        <tbody>
                            <?php foreach($achievements as $ach): ?>
                            <tr>
                                <td><?php echo $ach['achievement_title']; ?></td>
                                <td><?php echo $ach['verified_by_mentor'] ? '✅ Verified' : '⏳ Pending'; ?></td>
                                <td><?php echo date('d-m-Y', strtotime($ach['created_at'])); ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php else: ?>
                    <p>No achievements added yet.</p>
                <?php endif; ?>
            </div>
        </div>
    </div>
</body>
</html>