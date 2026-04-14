<?php
require_once '../includes/session.php';
require_once '../includes/db_connect.php';

if($_SESSION['role'] != 'student') {
    header("Location: ../login.php");
    exit();
}

// Get student details
$stmt = $pdo->prepare("SELECT id FROM student_details WHERE user_id = ?");
$stmt->execute([$_SESSION['user_id']]);
$student = $stmt->fetch();

$success = '';
$error = '';

if($_SERVER['REQUEST_METHOD'] == 'POST') {
    $title = $_POST['title'];
    $description = $_POST['description'];
    
    $stmt2 = $pdo->prepare("INSERT INTO achievements (student_id, achievement_title, description) VALUES (?, ?, ?)");
    if($stmt2->execute([$student['id'], $title, $description])) {
        $success = "Achievement added successfully! Waiting for mentor verification.";
    } else {
        $error = "Failed to add achievement.";
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Add Achievement</title>
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
            <h2>Add New Achievement</h2>
            
            <?php if($success): ?>
                <div class="alert alert-success"><?php echo $success; ?></div>
            <?php endif; ?>
            <?php if($error): ?>
                <div class="alert alert-error"><?php echo $error; ?></div>
            <?php endif; ?>
            
            <div class="card">
                <form method="POST">
                    <div class="form-group">
                        <label>Achievement Title</label>
                        <input type="text" name="title" required>
                    </div>
                    <div class="form-group">
                        <label>Description</label>
                        <textarea name="description" rows="5" required></textarea>
                    </div>
                    <button type="submit">Add Achievement</button>
                </form>
            </div>
        </div>
    </div>
</body>
</html>