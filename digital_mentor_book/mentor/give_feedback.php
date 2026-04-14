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
$stmt_students = $pdo->prepare("SELECT sd.id as student_detail_id, sd.roll_number, sd.class, sd.section, 
                                        u.id as user_id, u.full_name, u.email
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
        if($s['student_detail_id'] == $selected_student_id) {
            $selected_student = $s;
            break;
        }
    }
}

// Handle feedback submission
if($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['submit_feedback'])) {
    $student_id = $_POST['student_id'];
    $semester = (int)$_POST['semester'];
    $feedback = trim($_POST['feedback']);
    
    if(empty($feedback)) {
        $error = "Please enter feedback message.";
    } else {
        $stmt_insert = $pdo->prepare("INSERT INTO mentor_feedback (student_id, mentor_id, feedback, semester) VALUES (?, ?, ?, ?)");
        if($stmt_insert->execute([$student_id, $_SESSION['user_id'], $feedback, $semester])) {
            $success = "Feedback submitted successfully!";
            // Clear form
            $_POST = array();
        } else {
            $error = "Failed to submit feedback.";
        }
    }
}

// Get feedback history for selected student
$feedback_history = [];
if($selected_student_id) {
    $stmt_history = $pdo->prepare("SELECT mf.*, u.full_name as mentor_name 
                                   FROM mentor_feedback mf 
                                   JOIN users u ON mf.mentor_id = u.id 
                                   WHERE mf.student_id = ? 
                                   ORDER BY mf.given_at DESC");
    $stmt_history->execute([$selected_student_id]);
    $feedback_history = $stmt_history->fetchAll();
}

// Get student performance summary
$student_performance = null;
if($selected_student_id) {
    $stmt_perf = $pdo->prepare("SELECT 
                                    COUNT(DISTINCT semester) as semesters,
                                    COUNT(*) as total_subjects,
                                    SUM(marks_obtained) as total_obtained,
                                    SUM(total_marks) as total_marks,
                                    AVG((marks_obtained / total_marks) * 100) as avg_percentage
                                 FROM semester_marks 
                                 WHERE student_id = ?");
    $stmt_perf->execute([$selected_student_id]);
    $student_performance = $stmt_perf->fetch();
}

// Get achievement summary
$achievement_summary = null;
if($selected_student_id) {
    $stmt_ach = $pdo->prepare("SELECT 
                                    COUNT(*) as total,
                                    SUM(CASE WHEN verified_by_mentor = TRUE THEN 1 ELSE 0 END) as verified
                                 FROM achievements 
                                 WHERE student_id = ?");
    $stmt_ach->execute([$selected_student_id]);
    $achievement_summary = $stmt_ach->fetch();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Give Feedback - Mentor Dashboard</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="https://cdn.quilljs.com/1.3.6/quill.snow.css">
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

        .alert-info {
            background: #d1ecf1;
            color: #0c5460;
            border-left: 4px solid #17a2b8;
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

        /* Two Column Layout */
        .two-columns {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 25px;
        }

        /* Feedback Form */
        .feedback-form-container {
            background: white;
            border-radius: 15px;
            padding: 25px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.08);
        }

        .feedback-form-container h3 {
            margin-bottom: 20px;
            color: #333;
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

        .form-group select {
            width: 100%;
            padding: 12px;
            border: 2px solid #e0e0e0;
            border-radius: 8px;
            font-size: 1rem;
        }

        /* Rich Text Editor */
        .editor-container {
            border: 2px solid #e0e0e0;
            border-radius: 8px;
            overflow: hidden;
        }

        #editor {
            height: 250px;
            background: white;
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

        /* Feedback History */
        .feedback-history {
            background: white;
            border-radius: 15px;
            padding: 25px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.08);
            max-height: 600px;
            overflow-y: auto;
        }

        .feedback-history h3 {
            margin-bottom: 20px;
            color: #333;
        }

        .feedback-item {
            background: #f8f9fa;
            border-radius: 10px;
            padding: 15px;
            margin-bottom: 15px;
            border-left: 4px solid #667eea;
        }

        .feedback-header {
            display: flex;
            justify-content: space-between;
            margin-bottom: 10px;
            padding-bottom: 10px;
            border-bottom: 1px solid #e0e0e0;
        }

        .feedback-mentor {
            font-weight: 600;
            color: #667eea;
        }

        .feedback-semester {
            background: #e0e0e0;
            padding: 2px 8px;
            border-radius: 20px;
            font-size: 0.8rem;
        }

        .feedback-date {
            color: #999;
            font-size: 0.8rem;
        }

        .feedback-content {
            color: #555;
            line-height: 1.6;
        }

        .feedback-content p {
            margin-bottom: 5px;
        }

        .no-feedback {
            text-align: center;
            padding: 40px;
            color: #999;
        }

        /* Performance Cards */
        .performance-cards {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 15px;
            margin-bottom: 20px;
        }

        .perf-card {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 15px;
            border-radius: 10px;
            text-align: center;
        }

        .perf-card .number {
            font-size: 1.8rem;
            font-weight: bold;
        }

        .perf-card .label {
            font-size: 0.85rem;
            opacity: 0.9;
        }

        /* Responsive */
        @media (max-width: 968px) {
            .two-columns {
                grid-template-columns: 1fr;
            }
        }

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
            
            .performance-cards {
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
            <a href="add_marks.php">
                <i class="fas fa-edit"></i>
                <span>Add/Edit Marks</span>
            </a>
            <a href="verify_achievements.php">
                <i class="fas fa-check-circle"></i>
                <span>Verify Achievements</span>
            </a>
            <a href="give_feedback.php" class="active">
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
                    <i class="fas fa-comment"></i> Give Feedback to Students
                </h2>
                <p>Provide constructive feedback and guidance to your students</p>
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
                        <div class="student-card <?php echo ($selected_student_id == $student['student_detail_id']) ? 'selected' : ''; ?>" 
                             onclick="selectStudent(<?php echo $student['student_detail_id']; ?>)">
                            <h4><?php echo htmlspecialchars($student['full_name']); ?></h4>
                            <p><i class="fas fa-id-card"></i> Roll: <?php echo htmlspecialchars($student['roll_number']); ?></p>
                            <p><i class="fas fa-graduation-cap"></i> Class: <?php echo htmlspecialchars($student['class'] ?: 'Not assigned'); ?> - Section <?php echo htmlspecialchars($student['section'] ?: 'N/A'); ?></p>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>

            <?php if($selected_student): ?>
                <!-- Two Column Layout -->
                <div class="two-columns">
                    <!-- Left Column: Feedback Form -->
                    <div class="feedback-form-container">
                        <h3>
                            <i class="fas fa-pen-alt"></i> 
                            Write Feedback for <?php echo htmlspecialchars($selected_student['full_name']); ?>
                        </h3>
                        
                        <!-- Performance Summary -->
                        <div class="performance-cards">
                            <div class="perf-card">
                                <div class="number"><?php echo $student_performance['total_subjects'] ?? 0; ?></div>
                                <div class="label">Subjects</div>
                            </div>
                            <div class="perf-card">
                                <div class="number"><?php echo round($student_performance['avg_percentage'] ?? 0); ?>%</div>
                                <div class="label">Avg. Percentage</div>
                            </div>
                            <div class="perf-card">
                                <div class="number"><?php echo $achievement_summary['total'] ?? 0; ?></div>
                                <div class="label">Achievements</div>
                            </div>
                            <div class="perf-card">
                                <div class="number"><?php echo $achievement_summary['verified'] ?? 0; ?></div>
                                <div class="label">Verified</div>
                            </div>
                        </div>
                        
                        <form method="POST" id="feedbackForm">
                            <input type="hidden" name="student_id" value="<?php echo $selected_student['student_detail_id']; ?>">
                            
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
                                <label><i class="fas fa-comment-dots"></i> Feedback Message</label>
                                <div class="editor-container">
                                    <div id="editor"></div>
                                </div>
                                <textarea name="feedback" id="feedbackTextarea" style="display: none;"></textarea>
                            </div>
                            
                            <button type="submit" name="submit_feedback" class="btn-primary">
                                <i class="fas fa-paper-plane"></i> Submit Feedback
                            </button>
                        </form>
                    </div>
                    
                    <!-- Right Column: Feedback History -->
                    <div class="feedback-history">
                        <h3>
                            <i class="fas fa-history"></i> 
                            Feedback History
                        </h3>
                        
                        <?php if(count($feedback_history) > 0): ?>
                            <?php foreach($feedback_history as $feedback): ?>
                                <div class="feedback-item">
                                    <div class="feedback-header">
                                        <span class="feedback-mentor">
                                            <i class="fas fa-chalkboard-user"></i> <?php echo htmlspecialchars($feedback['mentor_name']); ?>
                                        </span>
                                        <span class="feedback-semester">
                                            Semester <?php echo $feedback['semester']; ?>
                                        </span>
                                    </div>
                                    <div class="feedback-date">
                                        <i class="fas fa-calendar-alt"></i> 
                                        <?php echo date('F d, Y \a\t h:i A', strtotime($feedback['given_at'])); ?>
                                    </div>
                                    <div class="feedback-content">
                                        <?php echo $feedback['feedback']; ?>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <div class="no-feedback">
                                <i class="fas fa-comments" style="font-size: 3rem; margin-bottom: 10px; display: block;"></i>
                                <p>No feedback given yet for this student.</p>
                                <p style="font-size: 0.85rem;">Use the form on the left to provide feedback.</p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            <?php elseif(count($students) > 0): ?>
                <div class="alert alert-info">
                    <i class="fas fa-info-circle"></i>
                    Please select a student from above to give feedback.
                </div>
            <?php else: ?>
                <div class="alert alert-info">
                    <i class="fas fa-info-circle"></i>
                    No students assigned to you yet. Please contact admin.
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Quill Rich Text Editor -->
    <script src="https://cdn.quilljs.com/1.3.6/quill.min.js"></script>
    
    <script>
        // Initialize Quill editor
        var quill = new Quill('#editor', {
            theme: 'snow',
            placeholder: 'Write your feedback here... Use formatting to make it more readable.',
            modules: {
                toolbar: [
                    [{ 'header': [1, 2, 3, 4, 5, 6, false] }],
                    ['bold', 'italic', 'underline', 'strike'],
                    [{ 'list': 'ordered'}, { 'list': 'bullet' }],
                    ['link', 'clean']
                ]
            }
        });
        
        // Sync quill content to textarea before form submission
        const form = document.getElementById('feedbackForm');
        const textarea = document.getElementById('feedbackTextarea');
        
        form.addEventListener('submit', function() {
            textarea.value = quill.root.innerHTML;
        });
        
        function selectStudent(studentId) {
            window.location.href = '?student=' + studentId;
        }
        
        // Auto-save feedback draft to localStorage
        let autoSaveTimer;
        const STORAGE_KEY = 'feedback_draft_' + <?php echo $selected_student_id ?: 0; ?>;
        
        function autoSaveDraft() {
            const content = quill.root.innerHTML;
            if (content && content !== '<p><br></p>') {
                localStorage.setItem(STORAGE_KEY, content);
                showAutoSaveNotification();
            }
        }
        
        function showAutoSaveNotification() {
            let notification = document.querySelector('.auto-save-notification');
            if (!notification) {
                notification = document.createElement('div');
                notification.className = 'auto-save-notification';
                notification.style.cssText = 'position: fixed; bottom: 20px; right: 20px; background: #28a745; color: white; padding: 8px 15px; border-radius: 20px; font-size: 0.85rem; z-index: 999;';
                document.body.appendChild(notification);
            }
            notification.innerHTML = '<i class="fas fa-save"></i> Draft saved';
            notification.style.display = 'block';
            setTimeout(() => {
                notification.style.display = 'none';
            }, 2000);
        }
        
        quill.on('text-change', function() {
            if (autoSaveTimer) clearTimeout(autoSaveTimer);
            autoSaveTimer = setTimeout(autoSaveDraft, 2000);
        });
        
        // Load saved draft
        const savedDraft = localStorage.getItem(STORAGE_KEY);
        if (savedDraft && savedDraft !== '<p><br></p>') {
            if (confirm('You have a saved draft. Would you like to restore it?')) {
                quill.root.innerHTML = savedDraft;
            } else {
                localStorage.removeItem(STORAGE_KEY);
            }
        }
        
        // Clear draft on successful submission
        <?php if($success): ?>
        localStorage.removeItem(STORAGE_KEY);
        <?php endif; ?>
        
        // Feedback templates
        const feedbackTemplates = {
            academic: "Dear Student,\n\nI have reviewed your academic performance. Here are my observations:\n\nStrengths:\n• \n\nAreas for Improvement:\n• \n\nSuggestions:\n• \n\nKeep up the good work!\n\nBest regards,\nMentor",
            achievement: "Congratulations on your achievements! 🎉\n\nI'm impressed with your progress in extracurricular activities. Keep participating and building your portfolio.\n\nLet's discuss how we can build on these successes.\n\nBest regards,\nMentor",
            general: "Dear Student,\n\nThis is regarding your overall progress. I appreciate your efforts and dedication.\n\nPlease continue to focus on:\n• Regular studies\n• Assignment submissions\n• Active participation\n\nFeel free to reach out if you need any guidance.\n\nBest regards,\nMentor"
        };
        
        // Optional: Add template buttons (can be added to UI)
        function loadTemplate(type) {
            if (confirm('Load template? This will replace your current feedback.')) {
                quill.root.innerHTML = '<p>' + feedbackTemplates[type].replace(/\n/g, '<br>') + '</p>';
            }
        }
    </script>
</body>
</html>