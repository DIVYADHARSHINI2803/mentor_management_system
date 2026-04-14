<?php
require_once '../includes/session.php';
require_once '../includes/db_connect.php';

if($_SESSION['role'] != 'mentor') {
    header("Location: ../login.php");
    exit();
}

// Get assigned students
$stmt = $pdo->prepare("SELECT u.id, u.full_name, u.email, sd.roll_number, sd.class, sd.section 
                       FROM student_details sd 
                       JOIN users u ON sd.user_id = u.id 
                       WHERE sd.mentor_id = ?");
$stmt->execute([$_SESSION['user_id']]);
$students = $stmt->fetchAll();

// Count pending verifications
$pending_count = 0;
foreach($students as $student) {
    $stmt2 = $pdo->prepare("SELECT COUNT(*) as count FROM achievements 
                            WHERE student_id = ? AND verified_by_mentor = FALSE");
    $stmt2->execute([$student['id']]);
    $pending = $stmt2->fetch();
    $pending_count += $pending['count'];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mentor Dashboard</title>
    <link rel="stylesheet" href="../assets/css/style.css">
</head>
<body>
    <div class="dashboard">
        <div class="sidebar">
            <h3>📚 Mentor Panel</h3>
            <a href="dashboard.php">🏠 Dashboard</a>
            <a href="assigned_students.php">👨‍🎓 Assigned Students</a>
            <a href="add_marks.php">📝 Add/Edit Marks</a>
            <a href="verify_achievements.php">✅ Verify Achievements</a>
            <a href="give_feedback.php">💬 Give Feedback</a>
            <a href="class_report.php">📊 Class Report</a>
            <a href="logout.php">🚪 Logout</a>
        </div>
        <div class="main-content">
            <h2>Welcome, Mentor <?php echo $_SESSION['full_name']; ?></h2>
            
            <div class="card">
                <h3>📊 Quick Stats</h3>
                <p><strong>Assigned Students:</strong> <?php echo count($students); ?></p>
                <p><strong>Pending Verifications:</strong> <?php echo $pending_count; ?></p>
            </div>
            
            <div class="card">
                <h3>👨‍🎓 My Assigned Students</h3>
                <?php if(count($students) > 0): ?>
                    <table>
                        <thead>
                            <tr><th>Roll Number</th><th>Name</th><th>Class</th><th>Section</th><th>Actions</th></tr>
                        </thead>
                        <tbody>
                            <?php foreach($students as $student): ?>
                            <tr>
                                <td><?php echo $student['roll_number']; ?></td>
                                <td><?php echo $student['full_name']; ?></td>
                                <td><?php echo $student['class'] ?: 'Not assigned'; ?></td>
                                <td><?php echo $student['section'] ?: 'Not assigned'; ?></td>
                                <td>
                                    <a href="add_marks.php?student=<?php echo $student['id']; ?>">Add Marks</a> |
                                    <a href="give_feedback.php?student=<?php echo $student['id']; ?>">Feedback</a>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php else: ?>
                    <p>No students assigned yet.</p>
                <?php endif; ?>
            </div>
        </div>
    </div>
</body>
</html>