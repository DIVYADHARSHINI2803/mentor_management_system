<?php
require_once '../includes/session.php';
require_once '../includes/db_connect.php';

if($_SESSION['role'] != 'admin') {
    header("Location: ../login.php");
    exit();
}

$success = '';
$error = '';

// Get all students without mentor
$stmt = $pdo->query("SELECT sd.id, u.full_name, sd.roll_number 
                     FROM student_details sd 
                     JOIN users u ON sd.user_id = u.id 
                     WHERE sd.mentor_id IS NULL");
$unassigned_students = $stmt->fetchAll();

// Get all mentors
$stmt2 = $pdo->query("SELECT id, full_name FROM users WHERE role = 'mentor'");
$mentors = $stmt2->fetchAll();

if($_SERVER['REQUEST_METHOD'] == 'POST') {
    $student_id = $_POST['student_id'];
    $mentor_id = $_POST['mentor_id'];
    
    $stmt3 = $pdo->prepare("UPDATE student_details SET mentor_id = ? WHERE id = ?");
    if($stmt3->execute([$mentor_id, $student_id])) {
        $success = "Mentor assigned successfully!";
        header("Refresh:0");
    } else {
        $error = "Failed to assign mentor.";
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Assign Mentor</title>
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
            <h2>Assign Mentor to Students</h2>
            
            <?php if($success): ?>
                <div class="alert alert-success"><?php echo $success; ?></div>
            <?php endif; ?>
            
            <div class="card">
                <form method="POST">
                    <div class="form-group">
                        <label>Select Student</label>
                        <select name="student_id" required>
                            <option value="">Choose Student</option>
                            <?php foreach($unassigned_students as $student): ?>
                            <option value="<?php echo $student['id']; ?>">
                                <?php echo $student['roll_number'] . ' - ' . $student['full_name']; ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Select Mentor</label>
                        <select name="mentor_id" required>
                            <option value="">Choose Mentor</option>
                            <?php foreach($mentors as $mentor): ?>
                            <option value="<?php echo $mentor['id']; ?>">
                                <?php echo $mentor['full_name']; ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <button type="submit">Assign Mentor</button>
                </form>
            </div>
        </div>
    </div>
</body>
</html>