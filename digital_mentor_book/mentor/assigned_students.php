<?php
require_once '../includes/session.php';
require_once '../includes/db_connect.php';

if($_SESSION['role'] != 'mentor') {
    header("Location: ../login.php");
    exit();
}

$success = '';
$error = '';

// Get mentor's assigned students with complete details
$stmt = $pdo->prepare("SELECT 
                        sd.id as student_detail_id,
                        sd.roll_number,
                        sd.class,
                        sd.section,
                        sd.parent_phone,
                        sd.address,
                        u.id as user_id,
                        u.full_name,
                        u.username,
                        u.email,
                        u.created_at as joined_date,
                        (SELECT COUNT(*) FROM semester_marks WHERE student_id = sd.id) as total_subjects,
                        (SELECT COUNT(*) FROM achievements WHERE student_id = sd.id) as total_achievements,
                        (SELECT COUNT(*) FROM achievements WHERE student_id = sd.id AND verified_by_mentor = TRUE) as verified_achievements,
                        (SELECT COUNT(*) FROM mentor_feedback WHERE student_id = sd.id) as total_feedback
                       FROM student_details sd 
                       JOIN users u ON sd.user_id = u.id 
                       WHERE sd.mentor_id = ? 
                       ORDER BY sd.class, sd.section, u.full_name");
$stmt->execute([$_SESSION['user_id']]);
$students = $stmt->fetchAll();

// Get class-wise statistics
$class_stats = [];
foreach($students as $student) {
    $class_key = $student['class'] ?: 'Unassigned';
    if(!isset($class_stats[$class_key])) {
        $class_stats[$class_key] = [
            'count' => 0,
            'total_achievements' => 0,
            'verified_achievements' => 0
        ];
    }
    $class_stats[$class_key]['count']++;
    $class_stats[$class_key]['total_achievements'] += $student['total_achievements'];
    $class_stats[$class_key]['verified_achievements'] += $student['verified_achievements'];
}

// Handle sending message to student (optional feature)
if($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['send_message'])) {
    $student_id = $_POST['student_id'];
    $message = trim($_POST['message']);
    
    // Here you can implement messaging system
    // For now, just show success message
    $success = "Message sent successfully!";
}

// Get search parameter
$search = isset($_GET['search']) ? $_GET['search'] : '';
$class_filter = isset($_GET['class']) ? $_GET['class'] : '';

// Filter students if search or class filter applied
$filtered_students = $students;
if($search) {
    $filtered_students = array_filter($filtered_students, function($student) use ($search) {
        return stripos($student['full_name'], $search) !== false || 
               stripos($student['roll_number'], $search) !== false ||
               stripos($student['email'], $search) !== false;
    });
}
if($class_filter) {
    $filtered_students = array_filter($filtered_students, function($student) use ($class_filter) {
        return $student['class'] == $class_filter;
    });
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Assigned Students - Mentor Dashboard</title>
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

        /* Stats Cards */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }

        .stat-card {
            background: white;
            padding: 20px;
            border-radius: 15px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.08);
            transition: transform 0.3s;
            text-align: center;
        }

        .stat-card:hover {
            transform: translateY(-5px);
        }

        .stat-card i {
            font-size: 2rem;
            margin-bottom: 10px;
            color: #667eea;
        }

        .stat-card .number {
            font-size: 2rem;
            font-weight: bold;
            color: #333;
        }

        .stat-card .label {
            color: #666;
            margin-top: 5px;
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

        .alert-info {
            background: #d1ecf1;
            color: #0c5460;
            border-left: 4px solid #17a2b8;
        }

        /* Search and Filter */
        .search-section {
            background: white;
            padding: 20px;
            border-radius: 15px;
            margin-bottom: 30px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.08);
        }

        .filter-bar {
            display: flex;
            gap: 15px;
            flex-wrap: wrap;
            margin-bottom: 15px;
        }

        .filter-group {
            flex: 1;
            min-width: 150px;
        }

        .filter-group input,
        .filter-group select {
            width: 100%;
            padding: 10px;
            border: 2px solid #e0e0e0;
            border-radius: 8px;
            font-size: 0.9rem;
        }

        .filter-group button {
            width: 100%;
            padding: 10px;
            background: #667eea;
            color: white;
            border: none;
            border-radius: 8px;
            cursor: pointer;
        }

        .class-badges {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
            margin-top: 15px;
        }

        .class-badge {
            padding: 5px 15px;
            background: #f8f9fa;
            border-radius: 20px;
            cursor: pointer;
            transition: all 0.3s;
            text-decoration: none;
            color: #666;
        }

        .class-badge:hover,
        .class-badge.active {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
        }

        /* Students Grid */
        .students-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(380px, 1fr));
            gap: 25px;
        }

        .student-card {
            background: white;
            border-radius: 15px;
            overflow: hidden;
            box-shadow: 0 5px 20px rgba(0,0,0,0.08);
            transition: all 0.3s;
        }

        .student-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 30px rgba(0,0,0,0.15);
        }

        .student-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 20px;
            position: relative;
        }

        .student-avatar {
            width: 60px;
            height: 60px;
            background: rgba(255,255,255,0.2);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
            margin-bottom: 10px;
        }

        .student-name {
            font-size: 1.3rem;
            font-weight: bold;
            margin-bottom: 5px;
        }

        .student-roll {
            font-size: 0.9rem;
            opacity: 0.9;
        }

        .student-body {
            padding: 20px;
        }

        .info-row {
            display: flex;
            padding: 8px 0;
            border-bottom: 1px solid #f0f0f0;
        }

        .info-label {
            width: 120px;
            font-weight: 600;
            color: #555;
        }

        .info-value {
            flex: 1;
            color: #333;
        }

        .stats-row {
            display: flex;
            justify-content: space-around;
            margin: 15px 0;
            padding: 15px 0;
            border-top: 1px solid #f0f0f0;
            border-bottom: 1px solid #f0f0f0;
        }

        .stat-item {
            text-align: center;
        }

        .stat-item .stat-number {
            font-size: 1.3rem;
            font-weight: bold;
            color: #667eea;
        }

        .stat-item .stat-label {
            font-size: 0.8rem;
            color: #666;
        }

        .badge {
            display: inline-block;
            padding: 4px 10px;
            border-radius: 20px;
            font-size: 0.75rem;
            font-weight: 600;
        }

        .badge-success {
            background: #d4edda;
            color: #155724;
        }

        .badge-warning {
            background: #fff3cd;
            color: #856404;
        }

        .badge-info {
            background: #d1ecf1;
            color: #0c5460;
        }

        .action-buttons {
            display: flex;
            gap: 10px;
            margin-top: 15px;
        }

        .btn {
            flex: 1;
            padding: 8px;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            text-decoration: none;
            text-align: center;
            font-size: 0.85rem;
            transition: all 0.3s;
        }

        .btn-primary {
            background: #667eea;
            color: white;
        }

        .btn-primary:hover {
            background: #5a67d8;
        }

        .btn-secondary {
            background: #6c757d;
            color: white;
        }

        .btn-secondary:hover {
            background: #5a6268;
        }

        .btn-info {
            background: #17a2b8;
            color: white;
        }

        .btn-info:hover {
            background: #138496;
        }

        /* Modal */
        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.5);
            z-index: 1000;
            justify-content: center;
            align-items: center;
        }

        .modal-content {
            background: white;
            border-radius: 15px;
            width: 90%;
            max-width: 500px;
            max-height: 90vh;
            overflow-y: auto;
            animation: slideUp 0.3s ease;
        }

        @keyframes slideUp {
            from {
                transform: translateY(50px);
                opacity: 0;
            }
            to {
                transform: translateY(0);
                opacity: 1;
            }
        }

        .modal-header {
            padding: 20px;
            border-bottom: 1px solid #e0e0e0;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .modal-header h3 {
            color: #333;
        }

        .close-modal {
            font-size: 1.5rem;
            cursor: pointer;
            color: #999;
        }

        .modal-body {
            padding: 20px;
        }

        .modal-body textarea {
            width: 100%;
            padding: 10px;
            border: 2px solid #e0e0e0;
            border-radius: 8px;
            resize: vertical;
            min-height: 100px;
        }

        .modal-footer {
            padding: 20px;
            border-top: 1px solid #e0e0e0;
            display: flex;
            justify-content: flex-end;
            gap: 10px;
        }

        /* No Data */
        .no-data {
            text-align: center;
            padding: 50px;
            background: white;
            border-radius: 15px;
            color: #666;
        }

        .no-data i {
            font-size: 3rem;
            margin-bottom: 15px;
            color: #ddd;
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
            
            .students-grid {
                grid-template-columns: 1fr;
            }
            
            .stats-grid {
                grid-template-columns: repeat(2, 1fr);
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
            <a href="assigned_students.php" class="active">
                <i class="fas fa-users"></i>
                <span>Assigned Students</span>
            </a>
            <a href="assign_class.php">
                <i class="fas fa-book"></i>
                <span>Allocate Class</span>
            </a>
            <a href="add_marks.php">
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
                    <i class="fas fa-users"></i> My Assigned Students
                </h2>
                <p>View and manage all students under your guidance</p>
            </div>

            <?php if($success): ?>
                <div class="alert alert-success">
                    <i class="fas fa-check-circle"></i>
                    <?php echo $success; ?>
                </div>
            <?php endif; ?>

            <!-- Statistics Cards -->
            <div class="stats-grid">
                <div class="stat-card">
                    <i class="fas fa-user-graduate"></i>
                    <div class="number"><?php echo count($students); ?></div>
                    <div class="label">Total Students</div>
                </div>
                <div class="stat-card">
                    <i class="fas fa-trophy"></i>
                    <div class="number">
                        <?php 
                        $total_achievements = array_sum(array_column($students, 'total_achievements'));
                        echo $total_achievements;
                        ?>
                    </div>
                    <div class="label">Total Achievements</div>
                </div>
                <div class="stat-card">
                    <i class="fas fa-check-circle"></i>
                    <div class="number">
                        <?php 
                        $verified_achievements = array_sum(array_column($students, 'verified_achievements'));
                        echo $verified_achievements;
                        ?>
                    </div>
                    <div class="label">Verified Achievements</div>
                </div>
                <div class="stat-card">
                    <i class="fas fa-chalkboard"></i>
                    <div class="number"><?php echo count($class_stats); ?></div>
                    <div class="label">Classes</div>
                </div>
            </div>

            <!-- Search and Filter Section -->
            <div class="search-section">
                <form method="GET" class="filter-bar">
                    <div class="filter-group">
                        <input type="text" name="search" placeholder="Search by name, roll number, email..." 
                               value="<?php echo htmlspecialchars($search); ?>">
                    </div>
                    <div class="filter-group">
                        <button type="submit"><i class="fas fa-search"></i> Search</button>
                    </div>
                    <?php if($search || $class_filter): ?>
                        <div class="filter-group">
                            <a href="assigned_students.php" class="btn-secondary" style="display: inline-block; text-align: center; line-height: 38px;">
                                <i class="fas fa-times"></i> Clear Filters
                            </a>
                        </div>
                    <?php endif; ?>
                </form>
                
                <div class="class-badges">
                    <a href="assigned_students.php" class="class-badge <?php echo !$class_filter ? 'active' : ''; ?>">
                        All Classes
                    </a>
                    <?php foreach(array_keys($class_stats) as $class): ?>
                        <a href="?class=<?php echo urlencode($class); ?>" 
                           class="class-badge <?php echo $class_filter == $class ? 'active' : ''; ?>">
                            <?php echo htmlspecialchars($class); ?> 
                            (<?php echo $class_stats[$class]['count']; ?>)
                        </a>
                    <?php endforeach; ?>
                </div>
            </div>

            <!-- Students Grid -->
            <?php if(count($filtered_students) > 0): ?>
                <div class="students-grid">
                    <?php foreach($filtered_students as $student): ?>
                        <div class="student-card">
                            <div class="student-header">
                                <div class="student-avatar">
                                    <i class="fas fa-user-graduate"></i>
                                </div>
                                <div class="student-name"><?php echo htmlspecialchars($student['full_name']); ?></div>
                                <div class="student-roll">
                                    <i class="fas fa-id-card"></i> Roll: <?php echo htmlspecialchars($student['roll_number']); ?>
                                </div>
                            </div>
                            
                            <div class="student-body">
                                <div class="info-row">
                                    <div class="info-label"><i class="fas fa-envelope"></i> Email:</div>
                                    <div class="info-value"><?php echo htmlspecialchars($student['email']); ?></div>
                                </div>
                                <div class="info-row">
                                    <div class="info-label"><i class="fas fa-graduation-cap"></i> Class:</div>
                                    <div class="info-value">
                                        <?php echo htmlspecialchars($student['class'] ?: 'Not assigned'); ?>
                                        <?php if($student['section']): ?>
                                            - Section <?php echo htmlspecialchars($student['section']); ?>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                <div class="info-row">
                                    <div class="info-label"><i class="fas fa-phone"></i> Parent Phone:</div>
                                    <div class="info-value"><?php echo htmlspecialchars($student['parent_phone'] ?: 'Not provided'); ?></div>
                                </div>
                                
                                <div class="stats-row">
                                    <div class="stat-item">
                                        <div class="stat-number"><?php echo $student['total_subjects']; ?></div>
                                        <div class="stat-label">Subjects</div>
                                    </div>
                                    <div class="stat-item">
                                        <div class="stat-number"><?php echo $student['total_achievements']; ?></div>
                                        <div class="stat-label">Achievements</div>
                                    </div>
                                    <div class="stat-item">
                                        <div class="stat-number"><?php echo $student['verified_achievements']; ?></div>
                                        <div class="stat-label">Verified</div>
                                    </div>
                                    <div class="stat-item">
                                        <div class="stat-number"><?php echo $student['total_feedback']; ?></div>
                                        <div class="stat-label">Feedbacks</div>
                                    </div>
                                </div>
                                
                                <?php if($student['total_achievements'] > 0): ?>
                                    <div class="info-row">
                                        <div class="info-label">Verification Rate:</div>
                                        <div class="info-value">
                                            <?php 
                                            $verify_rate = ($student['verified_achievements'] / $student['total_achievements']) * 100;
                                            ?>
                                            <div class="progress-bar" style="width: 100%; height: 6px; background: #e0e0e0; border-radius: 3px; overflow: hidden;">
                                                <div style="width: <?php echo $verify_rate; ?>%; height: 100%; background: linear-gradient(135deg, #28a745 0%, #20c997 100%);"></div>
                                            </div>
                                            <small><?php echo round($verify_rate); ?>% verified</small>
                                        </div>
                                    </div>
                                <?php endif; ?>
                                
                                <div class="action-buttons">
                                    <a href="add_marks.php?student=<?php echo $student['student_detail_id']; ?>" class="btn btn-primary">
                                        <i class="fas fa-edit"></i> Add Marks
                                    </a>
                                    <a href="give_feedback.php?student=<?php echo $student['student_detail_id']; ?>" class="btn btn-info">
                                        <i class="fas fa-comment"></i> Feedback
                                    </a>
                                    <button onclick="openMessageModal(<?php echo $student['user_id']; ?>, '<?php echo htmlspecialchars($student['full_name']); ?>')" class="btn btn-secondary">
                                        <i class="fas fa-envelope"></i> Message
                                    </button>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <div class="no-data">
                    <i class="fas fa-users"></i>
                    <h3>No Students Found</h3>
                    <p><?php echo $search ? 'No students match your search criteria.' : 'No students assigned to you yet. Please contact admin.'; ?></p>
                    <?php if($search): ?>
                        <a href="assigned_students.php" class="btn-primary" style="display: inline-block; margin-top: 15px; padding: 10px 20px;">
                            <i class="fas fa-arrow-left"></i> View All Students
                        </a>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Message Modal -->
    <div id="messageModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3><i class="fas fa-envelope"></i> Send Message to <span id="studentName"></span></h3>
                <span class="close-modal" onclick="closeMessageModal()">&times;</span>
            </div>
            <form method="POST">
                <input type="hidden" name="student_id" id="messageStudentId">
                <div class="modal-body">
                    <textarea name="message" placeholder="Type your message here..." required></textarea>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn-secondary" onclick="closeMessageModal()">Cancel</button>
                    <button type="submit" name="send_message" class="btn-primary">Send Message</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        function openMessageModal(studentId, studentName) {
            document.getElementById('messageStudentId').value = studentId;
            document.getElementById('studentName').textContent = studentName;
            document.getElementById('messageModal').style.display = 'flex';
        }
        
        function closeMessageModal() {
            document.getElementById('messageModal').style.display = 'none';
        }
        
        // Close modal on click outside
        window.onclick = function(event) {
            const modal = document.getElementById('messageModal');
            if (event.target == modal) {
                closeMessageModal();
            }
        }
        
        // Add animation to student cards on load
        document.addEventListener('DOMContentLoaded', function() {
            const cards = document.querySelectorAll('.student-card');
            cards.forEach((card, index) => {
                card.style.opacity = '0';
                card.style.transform = 'translateY(20px)';
                setTimeout(() => {
                    card.style.transition = 'all 0.5s ease';
                    card.style.opacity = '1';
                    card.style.transform = 'translateY(0)';
                }, index * 100);
            });
        });
    </script>
</body>
</html>