<?php
require_once '../includes/session.php';
require_once '../includes/db_connect.php';

if($_SESSION['role'] != 'admin') {
    header("Location: ../login.php");
    exit();
}

$success = '';
$error = '';

// Get filter parameters
$report_type = isset($_GET['type']) ? $_GET['type'] : 'overview';
$date_from = isset($_GET['date_from']) ? $_GET['date_from'] : date('Y-m-01');
$date_to = isset($_GET['date_to']) ? $_GET['date_to'] : date('Y-m-d');
$class_filter = isset($_GET['class']) ? $_GET['class'] : '';
$mentor_filter = isset($_GET['mentor']) ? $_GET['mentor'] : '';

// Get all classes for filter
$stmt_classes = $pdo->query("SELECT DISTINCT class FROM student_details WHERE class IS NOT NULL AND class != ''");
$classes = $stmt_classes->fetchAll();

// Get all mentors for filter
$stmt_mentors = $pdo->query("SELECT id, full_name FROM users WHERE role = 'mentor'");
$mentors = $stmt_mentors->fetchAll();

// Fetch report data based on type
$report_data = [];
$report_title = '';

switch($report_type) {
    case 'students':
        $report_title = 'Student Report';
        $query = "SELECT u.id, u.full_name, u.username, u.email, u.created_at,
                         sd.roll_number, sd.class, sd.section, sd.parent_phone,
                         m.full_name as mentor_name
                  FROM users u 
                  LEFT JOIN student_details sd ON u.id = sd.user_id
                  LEFT JOIN users m ON sd.mentor_id = m.id
                  WHERE u.role = 'student'";
        $params = [];
        
        if($class_filter) {
            $query .= " AND sd.class = ?";
            $params[] = $class_filter;
        }
        
        if($mentor_filter) {
            $query .= " AND sd.mentor_id = ?";
            $params[] = $mentor_filter;
        }
        
        $query .= " ORDER BY sd.class, sd.section, u.full_name";
        $stmt = $pdo->prepare($query);
        $stmt->execute($params);
        $report_data = $stmt->fetchAll();
        break;
        
    case 'mentors':
        $report_title = 'Mentor Report';
        $query = "SELECT u.id, u.full_name, u.username, u.email, u.created_at,
                         COUNT(DISTINCT sd.id) as student_count
                  FROM users u 
                  LEFT JOIN student_details sd ON u.id = sd.mentor_id
                  WHERE u.role = 'mentor'
                  GROUP BY u.id
                  ORDER BY u.full_name";
        $stmt = $pdo->prepare($query);
        $stmt->execute();
        $report_data = $stmt->fetchAll();
        break;
        
    case 'achievements':
        $report_title = 'Achievements Report';
        $query = "SELECT a.*, u.full_name as student_name, sd.roll_number, sd.class, sd.section,
                         m.full_name as mentor_name
                  FROM achievements a
                  JOIN student_details sd ON a.student_id = sd.id
                  JOIN users u ON sd.user_id = u.id
                  LEFT JOIN users m ON sd.mentor_id = m.id
                  WHERE DATE(a.created_at) BETWEEN ? AND ?";
        $params = [$date_from, $date_to];
        
        if($class_filter) {
            $query .= " AND sd.class = ?";
            $params[] = $class_filter;
        }
        
        $query .= " ORDER BY a.created_at DESC";
        $stmt = $pdo->prepare($query);
        $stmt->execute($params);
        $report_data = $stmt->fetchAll();
        break;
        
    case 'performance':
        $report_title = 'Performance Report';
        $query = "SELECT u.id, u.full_name, sd.roll_number, sd.class, sd.section,
                         AVG((sm.marks_obtained / sm.total_marks) * 100) as avg_percentage,
                         COUNT(DISTINCT sm.semester) as semesters,
                         COUNT(sm.id) as subjects_count,
                         SUM(sm.marks_obtained) as total_obtained,
                         SUM(sm.total_marks) as total_marks
                  FROM users u
                  JOIN student_details sd ON u.id = sd.user_id
                  LEFT JOIN semester_marks sm ON sd.id = sm.student_id
                  WHERE u.role = 'student'";
        $params = [];
        
        if($class_filter) {
            $query .= " AND sd.class = ?";
            $params[] = $class_filter;
        }
        
        $query .= " GROUP BY u.id, sd.id
                    ORDER BY avg_percentage DESC";
        $stmt = $pdo->prepare($query);
        $stmt->execute($params);
        $report_data = $stmt->fetchAll();
        break;
        
    case 'feedback':
        $report_title = 'Mentor Feedback Report';
        $query = "SELECT mf.*, u.full_name as student_name, sd.roll_number, sd.class,
                         m.full_name as mentor_name
                  FROM mentor_feedback mf
                  JOIN student_details sd ON mf.student_id = sd.id
                  JOIN users u ON sd.user_id = u.id
                  JOIN users m ON mf.mentor_id = m.id
                  WHERE DATE(mf.given_at) BETWEEN ? AND ?";
        $params = [$date_from, $date_to];
        
        if($class_filter) {
            $query .= " AND sd.class = ?";
            $params[] = $class_filter;
        }
        
        $query .= " ORDER BY mf.given_at DESC";
        $stmt = $pdo->prepare($query);
        $stmt->execute($params);
        $report_data = $stmt->fetchAll();
        break;
        
    default: // overview
        $report_title = 'System Overview Report';
        
        // Get statistics
        $stmt_stats = $pdo->query("SELECT 
            COUNT(CASE WHEN role = 'student' THEN 1 END) as total_students,
            COUNT(CASE WHEN role = 'mentor' THEN 1 END) as total_mentors,
            COUNT(CASE WHEN role = 'admin' THEN 1 END) as total_admins
            FROM users");
        $stats = $stmt_stats->fetch();
        
        // Get class distribution
        $stmt_class_dist = $pdo->query("SELECT class, COUNT(*) as count FROM student_details WHERE class IS NOT NULL GROUP BY class");
        $class_distribution = $stmt_class_dist->fetchAll();
        
        // Get recent activities
        $stmt_recent = $pdo->query("SELECT 'achievement' as type, achievement_title as title, created_at as date FROM achievements ORDER BY created_at DESC LIMIT 5");
        $recent_achievements = $stmt_recent->fetchAll();
        
        $stmt_recent_feedback = $pdo->query("SELECT 'feedback' as type, feedback as title, given_at as date FROM mentor_feedback ORDER BY given_at DESC LIMIT 5");
        $recent_feedback = $stmt_recent_feedback->fetchAll();
        
        $recent_activities = array_merge($recent_achievements, $recent_feedback);
        usort($recent_activities, function($a, $b) {
            return strtotime($b['date']) - strtotime($a['date']);
        });
        $recent_activities = array_slice($recent_activities, 0, 10);
        
        $report_data = [
            'stats' => $stats,
            'class_distribution' => $class_distribution,
            'recent_activities' => $recent_activities
        ];
        break;
}

// Export to CSV
if(isset($_GET['export']) && $_GET['export'] == 'csv') {
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="' . $report_type . '_report_' . date('Y-m-d') . '.csv"');
    
    $output = fopen('php://output', 'w');
    
    switch($report_type) {
        case 'students':
            fputcsv($output, ['ID', 'Full Name', 'Username', 'Email', 'Roll Number', 'Class', 'Section', 'Parent Phone', 'Mentor', 'Join Date']);
            foreach($report_data as $row) {
                fputcsv($output, [
                    $row['id'],
                    $row['full_name'],
                    $row['username'],
                    $row['email'],
                    $row['roll_number'],
                    $row['class'],
                    $row['section'],
                    $row['parent_phone'],
                    $row['mentor_name'],
                    $row['created_at']
                ]);
            }
            break;
        case 'mentors':
            fputcsv($output, ['ID', 'Full Name', 'Username', 'Email', 'Students Assigned', 'Join Date']);
            foreach($report_data as $row) {
                fputcsv($output, [
                    $row['id'],
                    $row['full_name'],
                    $row['username'],
                    $row['email'],
                    $row['student_count'],
                    $row['created_at']
                ]);
            }
            break;
        case 'achievements':
            fputcsv($output, ['ID', 'Student', 'Roll Number', 'Class', 'Achievement', 'Verified', 'Mentor', 'Date']);
            foreach($report_data as $row) {
                fputcsv($output, [
                    $row['id'],
                    $row['student_name'],
                    $row['roll_number'],
                    $row['class'] . ' ' . $row['section'],
                    $row['achievement_title'],
                    $row['verified_by_mentor'] ? 'Yes' : 'No',
                    $row['mentor_name'],
                    $row['created_at']
                ]);
            }
            break;
    }
    
    fclose($output);
    exit();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>View Reports - Admin Dashboard</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
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

        /* Report Navigation */
        .report-nav {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
            margin-bottom: 30px;
        }

        .report-btn {
            padding: 12px 25px;
            background: white;
            border: 2px solid #e0e0e0;
            border-radius: 10px;
            text-decoration: none;
            color: #666;
            transition: all 0.3s;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }

        .report-btn:hover {
            border-color: #667eea;
            background: #f8f9fa;
        }

        .report-btn.active {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border-color: #667eea;
        }

        /* Filter Section */
        .filter-section {
            background: white;
            padding: 20px;
            border-radius: 15px;
            margin-bottom: 30px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.08);
        }

        .filter-form {
            display: flex;
            gap: 15px;
            flex-wrap: wrap;
            align-items: flex-end;
        }

        .filter-group {
            flex: 1;
            min-width: 150px;
        }

        .filter-group label {
            display: block;
            margin-bottom: 5px;
            color: #555;
            font-weight: 500;
            font-size: 0.9rem;
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
            padding: 10px 20px;
            background: #667eea;
            color: white;
            border: none;
            border-radius: 8px;
            cursor: pointer;
        }

        .export-btn {
            background: #28a745;
        }

        /* Stats Cards for Overview */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }

        .stat-card {
            background: white;
            padding: 25px;
            border-radius: 15px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.08);
            text-align: center;
            transition: transform 0.3s;
        }

        .stat-card:hover {
            transform: translateY(-5px);
        }

        .stat-card i {
            font-size: 2.5rem;
            margin-bottom: 10px;
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

        /* Tables */
        .report-table-container {
            background: white;
            border-radius: 15px;
            padding: 20px;
            overflow-x: auto;
            box-shadow: 0 5px 15px rgba(0,0,0,0.08);
        }

        .report-table {
            width: 100%;
            border-collapse: collapse;
        }

        .report-table th,
        .report-table td {
            padding: 12px;
            text-align: left;
            border-bottom: 1px solid #f0f0f0;
        }

        .report-table th {
            background: #f8f9fa;
            font-weight: 600;
            color: #555;
        }

        .report-table tr:hover {
            background: #f8f9fa;
        }

        /* Badges */
        .badge {
            display: inline-block;
            padding: 4px 10px;
            border-radius: 20px;
            font-size: 0.8rem;
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

        /* Charts */
        .charts-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(400px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }

        .chart-card {
            background: white;
            border-radius: 15px;
            padding: 20px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.08);
        }

        .chart-card h3 {
            margin-bottom: 20px;
            color: #333;
        }

        canvas {
            max-height: 300px;
        }

        /* Performance Indicators */
        .performance-bar {
            width: 100%;
            height: 8px;
            background: #e0e0e0;
            border-radius: 4px;
            overflow: hidden;
            margin-top: 5px;
        }

        .performance-fill {
            height: 100%;
            background: linear-gradient(135deg, #28a745 0%, #20c997 100%);
            border-radius: 4px;
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
            
            .filter-form {
                flex-direction: column;
            }
            
            .charts-grid {
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
                <span>Admin Panel</span>
            </h3>
            <a href="dashboard.php">
                <i class="fas fa-home"></i>
                <span>Dashboard</span>
            </a>
            <a href="manage_users.php">
                <i class="fas fa-users"></i>
                <span>Manage Users</span>
            </a>
            <a href="assign_mentor.php">
                <i class="fas fa-user-plus"></i>
                <span>Assign Mentor</span>
            </a>
            <a href="view_reports.php" class="active">
                <i class="fas fa-chart-bar"></i>
                <span>View Reports</span>
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
                    <i class="fas fa-chart-bar"></i> Reports & Analytics
                </h2>
                <p>Generate and view detailed reports about students, mentors, and achievements</p>
            </div>

            <!-- Report Navigation -->
            <div class="report-nav">
                <a href="?type=overview" class="report-btn <?php echo $report_type == 'overview' ? 'active' : ''; ?>">
                    <i class="fas fa-chart-pie"></i> Overview
                </a>
                <a href="?type=students" class="report-btn <?php echo $report_type == 'students' ? 'active' : ''; ?>">
                    <i class="fas fa-user-graduate"></i> Students
                </a>
                <a href="?type=mentors" class="report-btn <?php echo $report_type == 'mentors' ? 'active' : ''; ?>">
                    <i class="fas fa-chalkboard-user"></i> Mentors
                </a>
                <a href="?type=achievements" class="report-btn <?php echo $report_type == 'achievements' ? 'active' : ''; ?>">
                    <i class="fas fa-trophy"></i> Achievements
                </a>
                <a href="?type=performance" class="report-btn <?php echo $report_type == 'performance' ? 'active' : ''; ?>">
                    <i class="fas fa-chart-line"></i> Performance
                </a>
                <a href="?type=feedback" class="report-btn <?php echo $report_type == 'feedback' ? 'active' : ''; ?>">
                    <i class="fas fa-comments"></i> Feedback
                </a>
            </div>

            <!-- Filter Section (for reports that need filters) -->
            <?php if(in_array($report_type, ['students', 'achievements', 'performance', 'feedback'])): ?>
            <div class="filter-section">
                <form method="GET" class="filter-form">
                    <input type="hidden" name="type" value="<?php echo $report_type; ?>">
                    
                    <?php if(in_array($report_type, ['achievements', 'feedback'])): ?>
                    <div class="filter-group">
                        <label>From Date</label>
                        <input type="date" name="date_from" value="<?php echo $date_from; ?>">
                    </div>
                    <div class="filter-group">
                        <label>To Date</label>
                        <input type="date" name="date_to" value="<?php echo $date_to; ?>">
                    </div>
                    <?php endif; ?>
                    
                    <div class="filter-group">
                        <label>Class</label>
                        <select name="class">
                            <option value="">All Classes</option>
                            <?php foreach($classes as $class): ?>
                            <option value="<?php echo $class['class']; ?>" <?php echo $class_filter == $class['class'] ? 'selected' : ''; ?>>
                                <?php echo $class['class']; ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <?php if($report_type == 'students'): ?>
                    <div class="filter-group">
                        <label>Mentor</label>
                        <select name="mentor">
                            <option value="">All Mentors</option>
                            <?php foreach($mentors as $mentor): ?>
                            <option value="<?php echo $mentor['id']; ?>" <?php echo $mentor_filter == $mentor['id'] ? 'selected' : ''; ?>>
                                <?php echo $mentor['full_name']; ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <?php endif; ?>
                    
                    <div class="filter-group">
                        <button type="submit"><i class="fas fa-filter"></i> Apply Filters</button>
                    </div>
                    
                    <?php if($report_type != 'overview'): ?>
                    <div class="filter-group">
                        <a href="?type=<?php echo $report_type; ?>&export=csv&<?php echo http_build_query(array_filter(['class' => $class_filter, 'mentor' => $mentor_filter, 'date_from' => $date_from, 'date_to' => $date_to])); ?>" 
                           class="export-btn" style="display: inline-block; padding: 10px 20px; background: #28a745; color: white; text-decoration: none; border-radius: 8px;">
                            <i class="fas fa-download"></i> Export CSV
                        </a>
                    </div>
                    <?php endif; ?>
                </form>
            </div>
            <?php endif; ?>

            <!-- Report Content -->
            <?php if($report_type == 'overview'): ?>
                <!-- Overview Report -->
                <div class="stats-grid">
                    <div class="stat-card">
                        <i class="fas fa-user-graduate" style="color: #28a745;"></i>
                        <div class="number"><?php echo $report_data['stats']['total_students']; ?></div>
                        <div class="label">Total Students</div>
                    </div>
                    <div class="stat-card">
                        <i class="fas fa-chalkboard-user" style="color: #17a2b8;"></i>
                        <div class="number"><?php echo $report_data['stats']['total_mentors']; ?></div>
                        <div class="label">Total Mentors</div>
                    </div>
                    <div class="stat-card">
                        <i class="fas fa-user-shield" style="color: #dc3545;"></i>
                        <div class="number"><?php echo $report_data['stats']['total_admins']; ?></div>
                        <div class="label">Total Admins</div>
                    </div>
                </div>

                <div class="charts-grid">
                    <div class="chart-card">
                        <h3><i class="fas fa-chart-pie"></i> Class Distribution</h3>
                        <canvas id="classChart"></canvas>
                    </div>
                    <div class="chart-card">
                        <h3><i class="fas fa-clock"></i> Recent Activities</h3>
                        <div style="max-height: 300px; overflow-y: auto;">
                            <?php foreach($report_data['recent_activities'] as $activity): ?>
                            <div style="padding: 10px; border-bottom: 1px solid #f0f0f0;">
                                <i class="fas <?php echo $activity['type'] == 'achievement' ? 'fa-trophy' : 'fa-comment'; ?>" 
                                   style="color: #667eea; margin-right: 10px;"></i>
                                <strong><?php echo htmlspecialchars(substr($activity['title'], 0, 50)); ?></strong>
                                <br>
                                <small style="color: #999;"><?php echo date('d-m-Y H:i', strtotime($activity['date'])); ?></small>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>

                <script>
                    // Class Distribution Chart
                    const ctx = document.getElementById('classChart').getContext('2d');
                    const classes = <?php echo json_encode(array_column($report_data['class_distribution'], 'class')); ?>;
                    const counts = <?php echo json_encode(array_column($report_data['class_distribution'], 'count')); ?>;
                    
                    new Chart(ctx, {
                        type: 'pie',
                        data: {
                            labels: classes,
                            datasets: [{
                                data: counts,
                                backgroundColor: ['#667eea', '#764ba2', '#28a745', '#17a2b8', '#ffc107', '#dc3545']
                            }]
                        },
                        options: {
                            responsive: true,
                            plugins: {
                                legend: {
                                    position: 'bottom'
                                }
                            }
                        }
                    });
                </script>

            <?php elseif($report_type == 'students'): ?>
                <!-- Students Report -->
                <div class="report-table-container">
                    <h3 style="margin-bottom: 20px;">
                        <i class="fas fa-user-graduate"></i> Student List
                        <span style="font-size: 0.9rem; color: #666; margin-left: 10px;">Total: <?php echo count($report_data); ?> students</span>
                    </h3>
                    <table class="report-table">
                        <thead>
                            <tr>
                                <th>Roll Number</th>
                                <th>Full Name</th>
                                <th>Username</th>
                                <th>Email</th>
                                <th>Class</th>
                                <th>Section</th>
                                <th>Parent Phone</th>
                                <th>Mentor</th>
                                <th>Join Date</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach($report_data as $student): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($student['roll_number'] ?: 'N/A'); ?></td>
                                <td><strong><?php echo htmlspecialchars($student['full_name']); ?></strong></td>
                                <td><?php echo htmlspecialchars($student['username']); ?></td>
                                <td><?php echo htmlspecialchars($student['email']); ?></td>
                                <td><?php echo htmlspecialchars($student['class'] ?: 'Not assigned'); ?></td>
                                <td><?php echo htmlspecialchars($student['section'] ?: 'N/A'); ?></td>
                                <td><?php echo htmlspecialchars($student['parent_phone'] ?: 'N/A'); ?></td>
                                <td><?php echo htmlspecialchars($student['mentor_name'] ?: 'Not assigned'); ?></td>
                                <td><?php echo date('d-m-Y', strtotime($student['created_at'])); ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

            <?php elseif($report_type == 'mentors'): ?>
                <!-- Mentors Report -->
                <div class="report-table-container">
                    <h3 style="margin-bottom: 20px;">
                        <i class="fas fa-chalkboard-user"></i> Mentor List
                        <span style="font-size: 0.9rem; color: #666; margin-left: 10px;">Total: <?php echo count($report_data); ?> mentors</span>
                    </h3>
                    <table class="report-table">
                        <thead>
                            <tr>
                                <th>Full Name</th>
                                <th>Username</th>
                                <th>Email</th>
                                <th>Students Assigned</th>
                                <th>Join Date</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach($report_data as $mentor): ?>
                            <tr>
                                <td><strong><?php echo htmlspecialchars($mentor['full_name']); ?></strong></td>
                                <td><?php echo htmlspecialchars($mentor['username']); ?></td>
                                <td><?php echo htmlspecialchars($mentor['email']); ?></td>
                                <td>
                                    <span class="badge badge-info"><?php echo $mentor['student_count']; ?> students</span>
                                </td>
                                <td><?php echo date('d-m-Y', strtotime($mentor['created_at'])); ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

            <?php elseif($report_type == 'achievements'): ?>
                <!-- Achievements Report -->
                <div class="report-table-container">
                    <h3 style="margin-bottom: 20px;">
                        <i class="fas fa-trophy"></i> Achievements Report
                        <span style="font-size: 0.9rem; color: #666; margin-left: 10px;">Period: <?php echo date('d-m-Y', strtotime($date_from)); ?> to <?php echo date('d-m-Y', strtotime($date_to)); ?></span>
                    </h3>
                    <table class="report-table">
                        <thead>
                            <tr>
                                <th>Student</th>
                                <th>Roll Number</th>
                                <th>Class</th>
                                <th>Achievement</th>
                                <th>Status</th>
                                <th>Mentor</th>
                                <th>Date</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach($report_data as $achievement): ?>
                            <tr>
                                <td><strong><?php echo htmlspecialchars($achievement['student_name']); ?></strong></td>
                                <td><?php echo htmlspecialchars($achievement['roll_number']); ?></td>
                                <td><?php echo htmlspecialchars($achievement['class'] . ' ' . $achievement['section']); ?></td>
                                <td><?php echo htmlspecialchars($achievement['achievement_title']); ?></td>
                                <td>
                                    <?php if($achievement['verified_by_mentor']): ?>
                                        <span class="badge badge-success">Verified</span>
                                    <?php else: ?>
                                        <span class="badge badge-warning">Pending</span>
                                    <?php endif; ?>
                                </td>
                                <td><?php echo htmlspecialchars($achievement['mentor_name'] ?: 'N/A'); ?></td>
                                <td><?php echo date('d-m-Y', strtotime($achievement['created_at'])); ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

            <?php elseif($report_type == 'performance'): ?>
                <!-- Performance Report -->
                <div class="report-table-container">
                    <h3 style="margin-bottom: 20px;">
                        <i class="fas fa-chart-line"></i> Student Performance Report
                        <?php if($class_filter): ?>
                        <span style="font-size: 0.9rem; color: #666; margin-left: 10px;">Class: <?php echo $class_filter; ?></span>
                        <?php endif; ?>
                    </h3>
                    <table class="report-table">
                        <thead>
                            <tr>
                                <th>Rank</th>
                                <th>Student Name</th>
                                <th>Roll Number</th>
                                <th>Class</th>
                                <th>Avg Percentage</th>
                                <th>Performance</th>
                                <th>Subjects</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php $rank = 1; foreach($report_data as $student): ?>
                            <tr>
                                <td>
                                    <?php if($rank == 1): ?>
                                        🥇 <?php echo $rank++; ?>
                                    <?php elseif($rank == 2): ?>
                                        🥈 <?php echo $rank++; ?>
                                    <?php elseif($rank == 3): ?>
                                        🥉 <?php echo $rank++; ?>
                                    <?php else: ?>
                                        <?php echo $rank++; ?>
                                    <?php endif; ?>
                                </td>
                                <td><strong><?php echo htmlspecialchars($student['full_name']); ?></strong></td>
                                <td><?php echo htmlspecialchars($student['roll_number']); ?></td>
                                <td><?php echo htmlspecialchars($student['class'] . ' ' . $student['section']); ?></td>
                                <td>
                                    <strong><?php echo number_format($student['avg_percentage'], 1); ?>%</strong>
                                </td>
                                <td>
                                    <div class="performance-bar">
                                        <div class="performance-fill" style="width: <?php echo $student['avg_percentage']; ?>%"></div>
                                    </div>
                                </td>
                                <td><?php echo $student['subjects_count']; ?> subjects</td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

            <?php elseif($report_type == 'feedback'): ?>
                <!-- Feedback Report -->
                <div class="report-table-container">
                    <h3 style="margin-bottom: 20px;">
                        <i class="fas fa-comments"></i> Mentor Feedback Report
                        <span style="font-size: 0.9rem; color: #666; margin-left: 10px;">Period: <?php echo date('d-m-Y', strtotime($date_from)); ?> to <?php echo date('d-m-Y', strtotime($date_to)); ?></span>
                    </h3>
                    <table class="report-table">
                        <thead>
                            <tr>
                                <th>Student</th>
                                <th>Roll Number</th>
                                <th>Class</th>
                                <th>Mentor</th>
                                <th>Semester</th>
                                <th>Feedback</th>
                                <th>Date</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach($report_data as $feedback): ?>
                            <tr>
                                <td><strong><?php echo htmlspecialchars($feedback['student_name']); ?></strong></td>
                                <td><?php echo htmlspecialchars($feedback['roll_number']); ?></td>
                                <td><?php echo htmlspecialchars($feedback['class']); ?></td>
                                <td><?php echo htmlspecialchars($feedback['mentor_name']); ?></td>
                                <td>Semester <?php echo $feedback['semester']; ?></td>
                                <td><?php echo htmlspecialchars(substr($feedback['feedback'], 0, 100)) . (strlen($feedback['feedback']) > 100 ? '...' : ''); ?></td>
                                <td><?php echo date('d-m-Y', strtotime($feedback['given_at'])); ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>