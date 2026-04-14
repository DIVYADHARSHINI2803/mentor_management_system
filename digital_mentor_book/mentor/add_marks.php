<?php
require_once '../includes/session.php';
require_once '../includes/db_connect.php';

if($_SESSION['role'] != 'mentor') {
    header("Location: ../login.php");
    exit();
}

$success = '';
$error = '';

// Get mentor's assigned students
$stmt_students = $pdo->prepare("SELECT sd.id, sd.roll_number, sd.class, sd.section, u.full_name, u.id as user_id
                                FROM student_details sd 
                                JOIN users u ON sd.user_id = u.id 
                                WHERE sd.mentor_id = ? 
                                ORDER BY sd.class, sd.section, u.full_name");
$stmt_students->execute([$_SESSION['user_id']]);
$students = $stmt_students->fetchAll();

// Get selected student
$selected_student_id = isset($_GET['student']) ? (int)$_GET['student'] : (isset($_POST['student_id']) ? (int)$_POST['student_id'] : 0);
$selected_student = null;

if($selected_student_id) {
    foreach($students as $s) {
        if($s['id'] == $selected_student_id) {
            $selected_student = $s;
            break;
        }
    }
}

// Handle add/edit marks
if($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['save_marks'])) {
    $student_id = $_POST['student_id'];
    $semester = (int)$_POST['semester'];
    $subject = trim($_POST['subject']);
    $marks_obtained = (float)$_POST['marks_obtained'];
    $total_marks = (float)$_POST['total_marks'];
    
    // Calculate grade
    $percentage = ($marks_obtained / $total_marks) * 100;
    $grade = calculateGrade($percentage);
    
    // Check if marks already exist for this subject and semester
    $stmt_check = $pdo->prepare("SELECT id FROM semester_marks WHERE student_id = ? AND semester = ? AND subject = ?");
    $stmt_check->execute([$student_id, $semester, $subject]);
    $existing = $stmt_check->fetch();
    
    if($existing) {
        // Update existing marks
        $stmt_update = $pdo->prepare("UPDATE semester_marks SET marks_obtained = ?, total_marks = ?, grade = ? WHERE id = ?");
        if($stmt_update->execute([$marks_obtained, $total_marks, $grade, $existing['id']])) {
            $success = "Marks updated successfully!";
        } else {
            $error = "Failed to update marks.";
        }
    } else {
        // Insert new marks
        $stmt_insert = $pdo->prepare("INSERT INTO semester_marks (student_id, semester, subject, marks_obtained, total_marks, grade) VALUES (?, ?, ?, ?, ?, ?)");
        if($stmt_insert->execute([$student_id, $semester, $subject, $marks_obtained, $total_marks, $grade])) {
            $success = "Marks added successfully!";
        } else {
            $error = "Failed to add marks.";
        }
    }
}

// Handle delete marks
if(isset($_GET['delete_marks'])) {
    $marks_id = (int)$_GET['delete_marks'];
    $student_id = (int)$_GET['student'];
    
    $stmt_delete = $pdo->prepare("DELETE FROM semester_marks WHERE id = ? AND student_id = ?");
    if($stmt_delete->execute([$marks_id, $student_id])) {
        $success = "Marks deleted successfully!";
    } else {
        $error = "Failed to delete marks.";
    }
}

// Get existing marks for selected student
$existing_marks = [];
$semesters = [];
if($selected_student_id) {
    $stmt_marks = $pdo->prepare("SELECT * FROM semester_marks WHERE student_id = ? ORDER BY semester, subject");
    $stmt_marks->execute([$selected_student_id]);
    $existing_marks = $stmt_marks->fetchAll();
    
    // Get unique semesters
    $stmt_sem = $pdo->prepare("SELECT DISTINCT semester FROM semester_marks WHERE student_id = ? ORDER BY semester");
    $stmt_sem->execute([$selected_student_id]);
    $semesters = $stmt_sem->fetchAll();
}

// Function to calculate grade
function calculateGrade($percentage) {
    if($percentage >= 90) return 'A+';
    if($percentage >= 80) return 'A';
    if($percentage >= 70) return 'B+';
    if($percentage >= 60) return 'B';
    if($percentage >= 50) return 'C+';
    if($percentage >= 40) return 'C';
    return 'F';
}

// Function to get grade color
function getGradeColor($grade) {
    switch($grade) {
        case 'A+': return '#28a745';
        case 'A': return '#34ce57';
        case 'B+': return '#5bc0de';
        case 'B': return '#5cb85c';
        case 'C+': return '#f0ad4e';
        case 'C': return '#ffc107';
        default: return '#dc3545';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Add/Edit Marks - Mentor Dashboard</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: #f5f6fa;
        }

        .dashboard {
            display: flex;
            min-height: 100vh;
        }

        /* Sidebar Styles */
        .sidebar {
            width: 280px;
            background: linear-gradient(135deg, #2c3e50 0%, #34495e 100%);
            color: white;
            padding: 30px 20px;
            position: fixed;
            height: 100vh;
            overflow-y: auto;
        }

        .sidebar h3 {
            margin-bottom: 30px;
            text-align: center;
            font-size: 1.3rem;
        }

        .sidebar h3 i {
            margin-right: 10px;
        }

        .sidebar a {
            display: flex;
            align-items: center;
            gap: 12px;
            color: rgba(255,255,255,0.8);
            text-decoration: none;
            padding: 12px 15px;
            margin: 5px 0;
            border-radius: 10px;
            transition: all 0.3s;
        }

        .sidebar a:hover {
            background: rgba(255,255,255,0.1);
            color: white;
            transform: translateX(5px);
        }

        .sidebar a.active {
            background: #667eea;
            color: white;
        }

        /* Main Content */
        .main-content {
            flex: 1;
            margin-left: 280px;
            padding: 30px;
        }

        /* Header */
        .page-header {
            margin-bottom: 30px;
        }

        .page-header h2 {
            font-size: 2rem;
            color: #333;
            margin-bottom: 10px;
        }

        .page-header p {
            color: #666;
        }

        /* Alert Messages */
        .alert {
            padding: 15px 20px;
            border-radius: 10px;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
            animation: slideDown 0.5s ease;
        }

        @keyframes slideDown {
            from {
                opacity: 0;
                transform: translateY(-20px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .alert-success {
            background: #d4edda;
            color: #155724;
            border-left: 4px solid #28a745;
        }

        .alert-error {
            background: #f8d7da;
            color: #721c24;
            border-left: 4px solid #dc3545;
        }

        /* Student Selector */
        .student-selector {
            background: white;
            padding: 25px;
            border-radius: 15px;
            margin-bottom: 30px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.08);
        }

        .student-selector h3 {
            margin-bottom: 15px;
            color: #333;
        }

        .student-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(250px, 1fr));
            gap: 15px;
            margin-top: 15px;
        }

        .student-card {
            background: #f8f9fa;
            padding: 15px;
            border-radius: 10px;
            cursor: pointer;
            transition: all 0.3s;
            border: 2px solid transparent;
        }

        .student-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
        }

        .student-card.selected {
            border-color: #667eea;
            background: linear-gradient(135deg, #667eea10 0%, #764ba210 100%);
        }

        .student-card h4 {
            color: #333;
            margin-bottom: 5px;
        }

        .student-card p {
            color: #666;
            font-size: 0.85rem;
        }

        /* Form Container */
        .form-container {
            background: white;
            border-radius: 15px;
            padding: 25px;
            margin-bottom: 30px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.08);
        }

        .form-container h3 {
            margin-bottom: 20px;
            color: #333;
        }

        .form-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
        }

        .form-group {
            margin-bottom: 20px;
        }

        .form-group label {
            display: block;
            margin-bottom: 8px;
            color: #555;
            font-weight: 500;
        }

        .form-group label i {
            margin-right: 8px;
            color: #667eea;
        }

        .form-group input,
        .form-group select {
            width: 100%;
            padding: 12px;
            border: 2px solid #e0e0e0;
            border-radius: 8px;
            font-size: 1rem;
            transition: all 0.3s;
        }

        .form-group input:focus,
        .form-group select:focus {
            outline: none;
            border-color: #667eea;
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
        }

        .btn-primary {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 12px 25px;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            font-size: 1rem;
            transition: all 0.3s;
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(102, 126, 234, 0.4);
        }

        /* Marks Table */
        .marks-table-container {
            background: white;
            border-radius: 15px;
            padding: 20px;
            overflow-x: auto;
            box-shadow: 0 5px 15px rgba(0,0,0,0.08);
        }

        .marks-table {
            width: 100%;
            border-collapse: collapse;
        }

        .marks-table th,
        .marks-table td {
            padding: 12px;
            text-align: left;
            border-bottom: 1px solid #f0f0f0;
        }

        .marks-table th {
            background: #f8f9fa;
            font-weight: 600;
            color: #555;
        }

        .marks-table tr:hover {
            background: #f8f9fa;
        }

        .grade-badge {
            display: inline-block;
            padding: 5px 12px;
            border-radius: 20px;
            font-weight: bold;
            font-size: 0.85rem;
        }

        .btn-delete {
            background: #dc3545;
            color: white;
            border: none;
            padding: 6px 12px;
            border-radius: 5px;
            cursor: pointer;
            font-size: 0.85rem;
            transition: all 0.3s;
        }

        .btn-delete:hover {
            background: #c82333;
        }

        /* Semester Tabs */
        .semester-tabs {
            display: flex;
            gap: 10px;
            margin-bottom: 20px;
            flex-wrap: wrap;
        }

        .semester-tab {
            padding: 10px 20px;
            background: #f8f9fa;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            transition: all 0.3s;
        }

        .semester-tab.active {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
        }

        .semester-content {
            display: none;
        }

        .semester-content.active {
            display: block;
        }

        /* No Data */
        .no-data {
            text-align: center;
            padding: 50px;
            color: #999;
        }

        .no-data i {
            font-size: 3rem;
            margin-bottom: 15px;
        }

        /* Responsive */
        @media (max-width: 768px) {
            .sidebar {
                width: 80px;
                padding: 20px 10px;
            }
            
            .sidebar span {
                display: none;
            }
            
            .sidebar a {
                justify-content: center;
            }
            
            .main-content {
                margin-left: 80px;
            }
            
            .student-grid {
                grid-template-columns: 1fr;
            }
            
            .form-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <div class="dashboard">
        <!-- Sidebar -->
        <div class="sidebar">
            <h3>
                <i class="fas fa-chalkboard-user"></i>
                <span>Mentor Panel</span>
            </h3>
            <a href="dashboard.php">
                <i class="fas fa-home"></i>
                <span>Dashboard</span>
            </a>
            <a href="assigned_students.php">
                <i class="fas fa-users"></i>
                <span>Assigned Students</span>
            </a>
            <a href="assign_class.php">
                <i class="fas fa-book"></i>
                <span>Allocate Class</span>
            </a>
            <a href="add_marks.php" class="active">
                <i class="fas fa-edit"></i>
                <span>Add/Edit Marks</span>
            </a>
            <a href="verify_achievements.php">
                <i class="fas fa-check-circle"></i>
                <span>Verify Achievements</span>
            </a>
            <a href="give_feedback.php">
                <i class="fas fa-comment"></i>
                <span>Give Feedback</span>
            </a>
            <a href="class_report.php">
                <i class="fas fa-chart-bar"></i>
                <span>Class Report</span>
            </a>
            <a href="../logout.php">
                <i class="fas fa-sign-out-alt"></i>
                <span>Logout</span>
            </a>
        </div>

        <!-- Main Content -->
        <div class="main-content">
            <div class="page-header">
                <h2>
                    <i class="fas fa-edit"></i> Add/Edit Student Marks
                </h2>
                <p>Manage semester-wise marks for your assigned students</p>
            </div>

            <?php if($success): ?>
                <div class="alert alert-success">
                    <i class="fas fa-check-circle"></i>
                    <?php echo $success; ?>
                </div>
            <?php endif; ?>

            <?php if($error): ?>
                <div class="alert alert-error">
                    <i class="fas fa-exclamation-circle"></i>
                    <?php echo $error; ?>
                </div>
            <?php endif; ?>

            <!-- Student Selector -->
            <div class="student-selector">
                <h3><i class="fas fa-user-graduate"></i> Select Student</h3>
                <div class="student-grid">
                    <?php foreach($students as $student): ?>
                        <div class="student-card <?php echo ($selected_student_id == $student['id']) ? 'selected' : ''; ?>" 
                             onclick="selectStudent(<?php echo $student['id']; ?>)">
                            <h4><?php echo htmlspecialchars($student['full_name']); ?></h4>
                            <p><i class="fas fa-id-card"></i> Roll: <?php echo htmlspecialchars($student['roll_number']); ?></p>
                            <p><i class="fas fa-graduation-cap"></i> Class: <?php echo htmlspecialchars($student['class'] ?: 'Not assigned'); ?> - Section <?php echo htmlspecialchars($student['section'] ?: 'N/A'); ?></p>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>

            <?php if($selected_student): ?>
                <!-- Add/Edit Marks Form -->
                <div class="form-container">
                    <h3><i class="fas fa-plus-circle"></i> Add/Edit Marks for <?php echo htmlspecialchars($selected_student['full_name']); ?></h3>
                    <form method="POST">
                        <input type="hidden" name="student_id" value="<?php echo $selected_student['id']; ?>">
                        <div class="form-grid">
                            <div class="form-group">
                                <label><i class="fas fa-calendar"></i> Semester</label>
                                <select name="semester" required>
                                    <option value="">Select Semester</option>
                                    <option value="1">Semester 1</option>
                                    <option value="2">Semester 2</option>
                                    <option value="3">Semester 3</option>
                                    <option value="4">Semester 4</option>
                                    <option value="5">Semester 5</option>
                                    <option value="6">Semester 6</option>
                                    <option value="7">Semester 7</option>
                                    <option value="8">Semester 8</option>
                                </select>
                            </div>
                            <div class="form-group">
                                <label><i class="fas fa-book"></i> Subject</label>
                                <input type="text" name="subject" placeholder="e.g., Mathematics, Physics, English" required>
                            </div>
                            <div class="form-group">
                                <label><i class="fas fa-star"></i> Marks Obtained</label>
                                <input type="number" name="marks_obtained" step="0.01" min="0" placeholder="Marks obtained" required>
                            </div>
                            <div class="form-group">
                                <label><i class="fas fa-flag-checkered"></i> Total Marks</label>
                                <input type="number" name="total_marks" step="0.01" min="0" placeholder="Total marks" required>
                            </div>
                        </div>
                        <button type="submit" name="save_marks" class="btn-primary">
                            <i class="fas fa-save"></i> Save Marks
                        </button>
                    </form>
                </div>

                <!-- Existing Marks -->
                <div class="marks-table-container">
                    <h3><i class="fas fa-history"></i> Existing Marks</h3>
                    <?php if(count($existing_marks) > 0): ?>
                        <div class="semester-tabs">
                            <?php foreach($semesters as $sem): ?>
                                <button class="semester-tab <?php echo $sem['semester'] == 1 ? 'active' : ''; ?>" 
                                        onclick="showSemester(<?php echo $sem['semester']; ?>)">
                                    Semester <?php echo $sem['semester']; ?>
                                </button>
                            <?php endforeach; ?>
                        </div>
                        
                        <?php foreach($semesters as $sem): ?>
                            <div id="semester-<?php echo $sem['semester']; ?>" class="semester-content <?php echo $sem['semester'] == 1 ? 'active' : ''; ?>">
                                <table class="marks-table">
                                    <thead>
                                        <tr>
                                            <th>Subject</th>
                                            <th>Marks Obtained</th>
                                            <th>Total Marks</th>
                                            <th>Percentage</th>
                                            <th>Grade</th>
                                            <th>Action</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php 
                                        $sem_marks = array_filter($existing_marks, function($mark) use ($sem) {
                                            return $mark['semester'] == $sem['semester'];
                                        });
                                        foreach($sem_marks as $mark): 
                                            $percentage = ($mark['marks_obtained'] / $mark['total_marks']) * 100;
                                            $grade_color = getGradeColor($mark['grade']);
                                        ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($mark['subject']); ?></strong></td>
                                            <td><?php echo $mark['marks_obtained']; ?></td>
                                            <td><?php echo $mark['total_marks']; ?></td>
                                            <td><?php echo number_format($percentage, 1); ?>%</td>
                                            <td>
                                                <span class="grade-badge" style="background: <?php echo $grade_color; ?>20; color: <?php echo $grade_color; ?>;">
                                                    <?php echo $mark['grade']; ?>
                                                </span>
                                            </td>
                                            <td>
                                                <a href="?student=<?php echo $selected_student['id']; ?>&delete_marks=<?php echo $mark['id']; ?>" 
                                                   class="btn-delete" 
                                                   onclick="return confirm('Are you sure you want to delete these marks?')">
                                                    <i class="fas fa-trash"></i> Delete
                                                </a>
                                            </td>
                                        </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                    <?php 
                                    // Calculate semester summary
                                    $sem_total_marks = 0;
                                    $sem_total_obtained = 0;
                                    foreach($sem_marks as $mark) {
                                        $sem_total_marks += $mark['total_marks'];
                                        $sem_total_obtained += $mark['marks_obtained'];
                                    }
                                    $sem_percentage = $sem_total_marks > 0 ? ($sem_total_obtained / $sem_total_marks) * 100 : 0;
                                    ?>
                                    <tfoot>
                                        <tr style="background: #f8f9fa; font-weight: bold;">
                                            <td colspan="2">Semester Summary</td>
                                            <td><?php echo $sem_total_obtained; ?> / <?php echo $sem_total_marks; ?></td>
                                            <td><?php echo number_format($sem_percentage, 1); ?>%</td>
                                            <td colspan="2">Grade: <?php echo calculateGrade($sem_percentage); ?></td>
                                        </tr>
                                    </tfoot>
                                </table>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div class="no-data">
                            <i class="fas fa-info-circle"></i>
                            <p>No marks added yet for this student.</p>
                        </div>
                    <?php endif; ?>
                </div>
            <?php elseif(count($students) > 0): ?>
                <div class="alert alert-info">
                    <i class="fas fa-info-circle"></i>
                    Please select a student from above to add or edit marks.
                </div>
            <?php else: ?>
                <div class="alert alert-info">
                    <i class="fas fa-info-circle"></i>
                    No students assigned to you yet. Please contact admin.
                </div>
            <?php endif; ?>
        </div>
    </div>

    <script>
        function selectStudent(studentId) {
            window.location.href = '?student=' + studentId;
        }
        
        function showSemester(semester) {
            // Hide all semester contents
            document.querySelectorAll('.semester-content').forEach(content => {
                content.classList.remove('active');
            });
            
            // Remove active class from all tabs
            document.querySelectorAll('.semester-tab').forEach(tab => {
                tab.classList.remove('active');
            });
            
            // Show selected semester
            document.getElementById('semester-' + semester).classList.add('active');
            
            // Add active class to clicked tab
            event.target.classList.add('active');
        }
        
        // Auto-calculate grade preview (optional)
        const marksObtainedInput = document.querySelector('input[name="marks_obtained"]');
        const totalMarksInput = document.querySelector('input[name="total_marks"]');
        
        if(marksObtainedInput && totalMarksInput) {
            function previewGrade() {
                const obtained = parseFloat(marksObtainedInput.value);
                const total = parseFloat(totalMarksInput.value);
                
                if(!isNaN(obtained) && !isNaN(total) && total > 0) {
                    const percentage = (obtained / total) * 100;
                    let grade = '';
                    
                    if(percentage >= 90) grade = 'A+';
                    else if(percentage >= 80) grade = 'A';
                    else if(percentage >= 70) grade = 'B+';
                    else if(percentage >= 60) grade = 'B';
                    else if(percentage >= 50) grade = 'C+';
                    else if(percentage >= 40) grade = 'C';
                    else grade = 'F';
                    
                    // Show preview (optional)
                    console.log(`Preview: ${percentage.toFixed(1)}% - Grade: ${grade}`);
                }
            }
            
            marksObtainedInput.addEventListener('input', previewGrade);
            totalMarksInput.addEventListener('input', previewGrade);
        }
    </script>
</body>
</html>