<?php
require_once '../includes/session.php';
require_once '../includes/db_connect.php';

if($_SESSION['role'] != 'student') {
    header("Location: ../login.php");
    exit();
}

// Get student details
$stmt = $pdo->prepare("SELECT id, roll_number, class, section FROM student_details WHERE user_id = ?");
$stmt->execute([$_SESSION['user_id']]);
$student = $stmt->fetch();

if(!$student) {
    die("Student record not found. Please contact administrator.");
}

// Get all semesters for which marks exist
$stmt_semesters = $pdo->prepare("SELECT DISTINCT semester FROM semester_marks WHERE student_id = ? ORDER BY semester DESC");
$stmt_semesters->execute([$student['id']]);
$semesters = $stmt_semesters->fetchAll();

// Get marks for selected semester
$selected_semester = isset($_GET['semester']) ? (int)$_GET['semester'] : ($semesters ? $semesters[0]['semester'] : 1);

$stmt_marks = $pdo->prepare("SELECT * FROM semester_marks WHERE student_id = ? AND semester = ? ORDER BY subject");
$stmt_marks->execute([$student['id'], $selected_semester]);
$marks = $stmt_marks->fetchAll();

// Calculate statistics
$total_marks = 0;
$total_obtained = 0;
$subject_count = count($marks);

foreach($marks as $mark) {
    $total_marks += $mark['total_marks'];
    $total_obtained += $mark['marks_obtained'];
}

$overall_percentage = $total_marks > 0 ? ($total_obtained / $total_marks) * 100 : 0;

// Function to get grade based on percentage
function getGradeFromPercentage($percentage) {
    if($percentage >= 90) return 'A+';
    if($percentage >= 80) return 'A';
    if($percentage >= 70) return 'B+';
    if($percentage >= 60) return 'B';
    if($percentage >= 50) return 'C+';
    if($percentage >= 40) return 'C';
    return 'F';
}

// Get grade color
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
    <title>Semester Marks - Student Dashboard</title>
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
        }

        .stat-card:hover {
            transform: translateY(-5px);
        }

        .stat-card h3 {
            font-size: 0.9rem;
            color: #666;
            margin-bottom: 10px;
        }

        .stat-card .value {
            font-size: 2rem;
            font-weight: bold;
            color: #333;
        }

        .stat-card .unit {
            font-size: 0.9rem;
            color: #666;
        }

        /* Semester Selector */
        .semester-selector {
            background: white;
            padding: 20px;
            border-radius: 15px;
            margin-bottom: 30px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.08);
        }

        .semester-buttons {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
            margin-top: 15px;
        }

        .semester-btn {
            padding: 10px 20px;
            border: 2px solid #e0e0e0;
            background: white;
            border-radius: 8px;
            cursor: pointer;
            transition: all 0.3s;
            font-size: 1rem;
        }

        .semester-btn:hover {
            border-color: #667eea;
            background: #f8f9fa;
        }

        .semester-btn.active {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border-color: #667eea;
        }

        /* Marks Table */
        .marks-table-container {
            background: white;
            border-radius: 15px;
            padding: 20px;
            margin-bottom: 30px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.08);
            overflow-x: auto;
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

        /* Charts Container */
        .charts-container {
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

        /* Performance Summary */
        .performance-summary {
            background: white;
            border-radius: 15px;
            padding: 20px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.08);
        }

        .performance-summary h3 {
            margin-bottom: 20px;
            color: #333;
        }

        .summary-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
        }

        .summary-item {
            padding: 15px;
            background: #f8f9fa;
            border-radius: 10px;
            text-align: center;
        }

        .summary-item .label {
            font-size: 0.9rem;
            color: #666;
            margin-bottom: 5px;
        }

        .summary-item .value {
            font-size: 1.3rem;
            font-weight: bold;
            color: #333;
        }

        .progress-bar {
            width: 100%;
            height: 8px;
            background: #e0e0e0;
            border-radius: 4px;
            overflow: hidden;
            margin-top: 10px;
        }

        .progress-fill {
            height: 100%;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border-radius: 4px;
            transition: width 0.5s ease;
        }

        /* No Data Message */
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
            
            .charts-container {
                grid-template-columns: 1fr;
            }
            
            .stats-grid {
                grid-template-columns: repeat(2, 1fr);
            }
        }

        @media (max-width: 480px) {
            .stats-grid {
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
                <span>Mentor Book</span>
            </h3>
            <a href="dashboard.php">
                <i class="fas fa-home"></i>
                <span>Dashboard</span>
            </a>
            <a href="profile.php">
                <i class="fas fa-user-circle"></i>
                <span>My Profile</span>
            </a>
            <a href="semester_marks.php" class="active">
                <i class="fas fa-chart-line"></i>
                <span>Semester Marks</span>
            </a>
            <a href="add_achievement.php">
                <i class="fas fa-trophy"></i>
                <span>Add Achievement</span>
            </a>
            <a href="upload_certificate.php">
                <i class="fas fa-cloud-upload-alt"></i>
                <span>Upload Certificate</span>
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
                    <i class="fas fa-chart-line"></i> Semester Marks
                </h2>
                <p>View your academic performance semester-wise</p>
            </div>

            <!-- Student Info -->
            <div class="stats-grid">
                <div class="stat-card">
                    <h3><i class="fas fa-id-card"></i> Roll Number</h3>
                    <div class="value"><?php echo htmlspecialchars($student['roll_number']); ?></div>
                </div>
                <div class="stat-card">
                    <h3><i class="fas fa-graduation-cap"></i> Class</h3>
                    <div class="value"><?php echo htmlspecialchars($student['class'] ?: 'Not assigned'); ?></div>
                </div>
                <div class="stat-card">
                    <h3><i class="fas fa-layer-group"></i> Section</h3>
                    <div class="value"><?php echo htmlspecialchars($student['section'] ?: 'Not assigned'); ?></div>
                </div>
            </div>

            <?php if(count($semesters) > 0): ?>
                <!-- Semester Selector -->
                <div class="semester-selector">
                    <h3><i class="fas fa-calendar-alt"></i> Select Semester</h3>
                    <div class="semester-buttons">
                        <?php foreach($semesters as $sem): ?>
                            <a href="?semester=<?php echo $sem['semester']; ?>" 
                               class="semester-btn <?php echo $selected_semester == $sem['semester'] ? 'active' : ''; ?>">
                                Semester <?php echo $sem['semester']; ?>
                            </a>
                        <?php endforeach; ?>
                    </div>
                </div>

                <?php if(count($marks) > 0): ?>
                    <!-- Statistics Cards -->
                    <div class="stats-grid">
                        <div class="stat-card">
                            <h3><i class="fas fa-book"></i> Total Subjects</h3>
                            <div class="value"><?php echo $subject_count; ?></div>
                        </div>
                        <div class="stat-card">
                            <h3><i class="fas fa-star"></i> Overall Percentage</h3>
                            <div class="value"><?php echo number_format($overall_percentage, 1); ?>%</div>
                        </div>
                        <div class="stat-card">
                            <h3><i class="fas fa-trophy"></i> Overall Grade</h3>
                            <div class="value"><?php echo getGradeFromPercentage($overall_percentage); ?></div>
                        </div>
                        <div class="stat-card">
                            <h3><i class="fas fa-chart-line"></i> Total Marks</h3>
                            <div class="value"><?php echo $total_obtained; ?><span class="unit"> / <?php echo $total_marks; ?></span></div>
                        </div>
                    </div>

                    <!-- Marks Table -->
                    <div class="marks-table-container">
                        <h3 style="margin-bottom: 20px;">
                            <i class="fas fa-table"></i> Semester <?php echo $selected_semester; ?> - Subject-wise Marks
                        </h3>
                        <table class="marks-table">
                            <thead>
                                <tr>
                                    <th>S.No</th>
                                    <th>Subject</th>
                                    <th>Marks Obtained</th>
                                    <th>Total Marks</th>
                                    <th>Percentage</th>
                                    <th>Grade</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php $counter = 1; foreach($marks as $mark): 
                                    $percentage = ($mark['marks_obtained'] / $mark['total_marks']) * 100;
                                    $grade_color = getGradeColor($mark['grade']);
                                ?>
                                <tr>
                                    <td><?php echo $counter++; ?></td>
                                    <td><strong><?php echo htmlspecialchars($mark['subject']); ?></strong></td>
                                    <td><?php echo $mark['marks_obtained']; ?></td>
                                    <td><?php echo $mark['total_marks']; ?></td>
                                    <td><?php echo number_format($percentage, 1); ?>%</td>
                                    <td>
                                        <span class="grade-badge" style="background: <?php echo $grade_color; ?>20; color: <?php echo $grade_color; ?>;">
                                            <?php echo $mark['grade']; ?>
                                        </span>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                            <tfoot>
                                <tr style="background: #f8f9fa; font-weight: bold;">
                                    <td colspan="2"><strong>Total</strong></td>
                                    <td><strong><?php echo $total_obtained; ?></strong></td>
                                    <td><strong><?php echo $total_marks; ?></strong></td>
                                    <td><strong><?php echo number_format($overall_percentage, 1); ?>%</strong></td>
                                    <td><strong><?php echo getGradeFromPercentage($overall_percentage); ?></strong></td>
                                </tr>
                            </tfoot>
                        </table>
                    </div>

                    <!-- Charts -->
                    <div class="charts-container">
                        <div class="chart-card">
                            <h3><i class="fas fa-chart-bar"></i> Subject-wise Performance</h3>
                            <canvas id="marksChart"></canvas>
                        </div>
                        <div class="chart-card">
                            <h3><i class="fas fa-chart-pie"></i> Performance Distribution</h3>
                            <canvas id="gradeDistributionChart"></canvas>
                        </div>
                    </div>

                    <!-- Performance Summary -->
                    <div class="performance-summary">
                        <h3><i class="fas fa-clipboard-list"></i> Performance Summary</h3>
                        <div class="summary-grid">
                            <div class="summary-item">
                                <div class="label">Best Subject</div>
                                <?php 
                                $best_subject = null;
                                $best_percentage = 0;
                                foreach($marks as $mark) {
                                    $perc = ($mark['marks_obtained'] / $mark['total_marks']) * 100;
                                    if($perc > $best_percentage) {
                                        $best_percentage = $perc;
                                        $best_subject = $mark['subject'];
                                    }
                                }
                                ?>
                                <div class="value"><?php echo htmlspecialchars($best_subject); ?></div>
                                <div class="progress-bar">
                                    <div class="progress-fill" style="width: <?php echo $best_percentage; ?>%"></div>
                                </div>
                                <div><?php echo number_format($best_percentage, 1); ?>%</div>
                            </div>
                            <div class="summary-item">
                                <div class="label">Need Improvement</div>
                                <?php 
                                $worst_subject = null;
                                $worst_percentage = 100;
                                foreach($marks as $mark) {
                                    $perc = ($mark['marks_obtained'] / $mark['total_marks']) * 100;
                                    if($perc < $worst_percentage) {
                                        $worst_percentage = $perc;
                                        $worst_subject = $mark['subject'];
                                    }
                                }
                                ?>
                                <div class="value"><?php echo htmlspecialchars($worst_subject); ?></div>
                                <div class="progress-bar">
                                    <div class="progress-fill" style="width: <?php echo $worst_percentage; ?>%; background: #ffc107;"></div>
                                </div>
                                <div><?php echo number_format($worst_percentage, 1); ?>%</div>
                            </div>
                            <div class="summary-item">
                                <div class="label">Average Percentage</div>
                                <div class="value"><?php echo number_format($overall_percentage, 1); ?>%</div>
                                <div class="progress-bar">
                                    <div class="progress-fill" style="width: <?php echo $overall_percentage; ?>%"></div>
                                </div>
                            </div>
                            <div class="summary-item">
                                <div class="label">Performance Status</div>
                                <div class="value">
                                    <?php 
                                    if($overall_percentage >= 75) echo 'Excellent 🎉';
                                    elseif($overall_percentage >= 60) echo 'Good 👍';
                                    elseif($overall_percentage >= 45) echo 'Average 📚';
                                    else echo 'Need Improvement ⚠️';
                                    ?>
                                </div>
                            </div>
                        </div>
                    </div>

                <?php else: ?>
                    <div class="no-data">
                        <i class="fas fa-info-circle"></i>
                        <h3>No marks available for Semester <?php echo $selected_semester; ?></h3>
                        <p>Your mentor hasn't added marks for this semester yet.</p>
                    </div>
                <?php endif; ?>
            <?php else: ?>
                <div class="no-data">
                    <i class="fas fa-chart-line"></i>
                    <h3>No Semester Data Available</h3>
                    <p>Your mentor hasn't added any marks yet. Please check back later.</p>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <script>
        <?php if(count($marks) > 0): ?>
        // Subject-wise Marks Chart
        const ctx1 = document.getElementById('marksChart').getContext('2d');
        const subjects = <?php echo json_encode(array_column($marks, 'subject')); ?>;
        const percentages = <?php echo json_encode(array_map(function($mark) {
            return round(($mark['marks_obtained'] / $mark['total_marks']) * 100, 1);
        }, $marks)); ?>;

        new Chart(ctx1, {
            type: 'bar',
            data: {
                labels: subjects,
                datasets: [{
                    label: 'Percentage (%)',
                    data: percentages,
                    backgroundColor: 'rgba(102, 126, 234, 0.7)',
                    borderColor: 'rgba(102, 126, 234, 1)',
                    borderWidth: 2,
                    borderRadius: 5
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: true,
                scales: {
                    y: {
                        beginAtZero: true,
                        max: 100,
                        title: {
                            display: true,
                            text: 'Percentage (%)'
                        }
                    },
                    x: {
                        title: {
                            display: true,
                            text: 'Subjects'
                        }
                    }
                },
                plugins: {
                    legend: {
                        display: false
                    },
                    tooltip: {
                        callbacks: {
                            label: function(context) {
                                return context.raw + '%';
                            }
                        }
                    }
                }
            }
        });

        // Grade Distribution Chart
        const ctx2 = document.getElementById('gradeDistributionChart').getContext('2d');
        const grades = <?php echo json_encode(array_column($marks, 'grade')); ?>;
        const gradeCount = {};
        grades.forEach(grade => {
            gradeCount[grade] = (gradeCount[grade] || 0) + 1;
        });

        new Chart(ctx2, {
            type: 'pie',
            data: {
                labels: Object.keys(gradeCount),
                datasets: [{
                    data: Object.values(gradeCount),
                    backgroundColor: [
                        '#28a745',
                        '#34ce57',
                        '#5bc0de',
                        '#5cb85c',
                        '#f0ad4e',
                        '#ffc107',
                        '#dc3545'
                    ]
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: true,
                plugins: {
                    legend: {
                        position: 'bottom'
                    },
                    tooltip: {
                        callbacks: {
                            label: function(context) {
                                const label = context.label || '';
                                const value = context.raw || 0;
                                const total = context.dataset.data.reduce((a, b) => a + b, 0);
                                const percentage = ((value / total) * 100).toFixed(1);
                                return `${label}: ${value} subject (${percentage}%)`;
                            }
                        }
                    }
                }
            }
        });
        <?php endif; ?>
    </script>
</body>
</html>