<?php
require_once '../includes/session.php';
require_once '../includes/db_connect.php';

if($_SESSION['role'] != 'mentor') {
    header("Location: ../login.php");
    exit();
}

$class = isset($_GET['class']) ? $_GET['class'] : '';
$students_data = [];

if($class) {
    $stmt = $pdo->prepare("SELECT sd.id, u.full_name, sd.roll_number, sd.class, sd.section 
                           FROM student_details sd 
                           JOIN users u ON sd.user_id = u.id 
                           WHERE sd.class = ? AND sd.mentor_id = ?");
    $stmt->execute([$class, $_SESSION['user_id']]);
    $students = $stmt->fetchAll();
    
    foreach($students as $student) {
        // Get marks
        $stmt2 = $pdo->prepare("SELECT subject, marks_obtained, total_marks, grade FROM semester_marks WHERE student_id = ?");
        $stmt2->execute([$student['id']]);
        $marks = $stmt2->fetchAll();
        
        // Get achievements
        $stmt3 = $pdo->prepare("SELECT achievement_title, verified_by_mentor FROM achievements WHERE student_id = ?");
        $stmt3->execute([$student['id']]);
        $achievements = $stmt3->fetchAll();
        
        $students_data[] = [
            'student' => $student,
            'marks' => $marks,
            'achievements' => $achievements
        ];
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Class Report</title>
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
            <h2>Generate Class Report</h2>
            
            <div class="card">
                <form method="GET">
                    <div class="form-group">
                        <label>Select Class</label>
                        <select name="class" required>
                            <option value="">Select Class</option>
                            <option value="9th">9th Standard</option>
                            <option value="10th">10th Standard</option>
                            <option value="11th">11th Standard</option>
                            <option value="12th">12th Standard</option>
                        </select>
                    </div>
                    <button type="submit">Generate Report</button>
                </form>
            </div>
            
            <?php if($class && count($students_data) > 0): ?>
                <?php foreach($students_data as $data): ?>
                <div class="card">
                    <h3><?php echo $data['student']['full_name']; ?> (<?php echo $data['student']['roll_number']; ?>)</h3>
                    <h4>📊 Semester Marks</h4>
                    <?php if(count($data['marks']) > 0): ?>
                    <table>
                        <thead>
                            <tr><th>Subject</th><th>Marks</th><th>Grade</th></tr>
                        </thead>
                        <tbody>
                            <?php foreach($data['marks'] as $mark): ?>
                            <tr>
                                <td><?php echo $mark['subject']; ?></td>
                                <td><?php echo $mark['marks_obtained'] . '/' . $mark['total_marks']; ?></td>
                                <td><?php echo $mark['grade']; ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                    <?php else: ?>
                    <p>No marks added yet.</p>
                    <?php endif; ?>
                    
                    <h4>🏆 Achievements</h4>
                    <?php if(count($data['achievements']) > 0): ?>
                    <ul>
                        <?php foreach($data['achievements'] as $ach): ?>
                        <li><?php echo $ach['achievement_title']; ?> 
                            (<?php echo $ach['verified_by_mentor'] ? 'Verified' : 'Pending'; ?>)
                        </li>
                        <?php endforeach; ?>
                    </ul>
                    <?php else: ?>
                    <p>No achievements added.</p>
                    <?php endif; ?>
                </div>
                <?php endforeach; ?>
            <?php elseif($class): ?>
                <div class="alert alert-info">No students found in this class.</div>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>