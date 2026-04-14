<?php
require_once '../includes/session.php';
require_once '../includes/db_connect.php';

if($_SESSION['role'] != 'mentor') {
    header("Location: ../login.php");
    exit();
}

$success = '';
$error = '';

// Get assigned students
$stmt = $pdo->prepare("SELECT sd.id, u.full_name, sd.roll_number, sd.class, sd.section 
                       FROM student_details sd 
                       JOIN users u ON sd.user_id = u.id 
                       WHERE sd.mentor_id = ?");
$stmt->execute([$_SESSION['user_id']]);
$students = $stmt->fetchAll();

if($_SERVER['REQUEST_METHOD'] == 'POST') {
    $student_id = $_POST['student_id'];
    $class = $_POST['class'];
    $section = $_POST['section'];
    
    $stmt2 = $pdo->prepare("UPDATE student_details SET class = ?, section = ? WHERE id = ?");
    if($stmt2->execute([$class, $section, $student_id])) {
        $success = "Class allocated successfully!";
    } else {
        $error = "Failed to allocate class.";
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Allocate Class</title>
    <link rel="stylesheet" href="../assets/css/style.css">
</head>
<body>
    <div class="dashboard">
        <div class="sidebar">
            <h3>📚 Mentor Panel</h3>
            <a href="dashboard.php">🏠 Dashboard</a>
            <a href="assigned_students.php">👨‍🎓 Assigned Students</a>
            <a href="assign_class.php">📚 Allocate Class</a>
            <a href="add_marks.php">📝 Add/Edit Marks</a>
            <a href="verify_achievements.php">✅ Verify Achievements</a>
            <a href="give_feedback.php">💬 Give Feedback</a>
            <a href="class_report.php">📊 Class Report</a>
            <a href="logout.php">🚪 Logout</a>
        </div>
        <div class="main-content">
            <h2>Allocate Class to Students</h2>
            
            <?php if($success): ?>
                <div class="alert alert-success"><?php echo $success; ?></div>
            <?php endif; ?>
            <?php if($error): ?>
                <div class="alert alert-error"><?php echo $error; ?></div>
            <?php endif; ?>
            
            <div class="card">
                <h3>Allocate Class/Section</h3>
                <form method="POST">
                    <div class="form-group">
                        <label>Select Student</label>
                        <select name="student_id" required>
                            <option value="">Choose Student</option>
                            <?php foreach($students as $student): ?>
                            <option value="<?php echo $student['id']; ?>">
                                <?php echo $student['roll_number'] . ' - ' . $student['full_name']; ?>
                                (Current: <?php echo $student['class'] ?: 'Not set'; ?>)
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Class</label>
                        <select name="class" required>
                            <option value="9th">9th Standard</option>
                            <option value="10th">10th Standard</option>
                            <option value="11th">11th Standard</option>
                            <option value="12th">12th Standard</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Section</label>
                        <select name="section" required>
                            <option value="A">Section A</option>
                            <option value="B">Section B</option>
                            <option value="C">Section C</option>
                        </select>
                    </div>
                    <button type="submit">Allocate Class</button>
                </form>
            </div>
        </div>
    </div>
</body>
</html>