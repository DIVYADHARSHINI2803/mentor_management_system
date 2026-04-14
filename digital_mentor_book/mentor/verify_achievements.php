<?php
require_once '../includes/session.php';
require_once '../includes/db_connect.php';

if($_SESSION['role'] != 'mentor') {
    header("Location: ../login.php");
    exit();
}

$success = '';
$error = '';

// Handle single achievement verification
if(isset($_GET['verify']) && is_numeric($_GET['verify'])) {
    $achievement_id = $_GET['verify'];
    
    $stmt_verify = $pdo->prepare("UPDATE achievements SET verified_by_mentor = TRUE, verified_at = NOW() WHERE id = ?");
    if($stmt_verify->execute([$achievement_id])) {
        $success = "Achievement verified successfully!";
    } else {
        $error = "Failed to verify achievement.";
    }
}

// Handle bulk verification
if(isset($_POST['bulk_verify']) && isset($_POST['achievement_ids'])) {
    $achievement_ids = $_POST['achievement_ids'];
    
    if(count($achievement_ids) > 0) {
        $placeholders = implode(',', array_fill(0, count($achievement_ids), '?'));
        $stmt_bulk = $pdo->prepare("UPDATE achievements SET verified_by_mentor = TRUE, verified_at = NOW() WHERE id IN ($placeholders)");
        if($stmt_bulk->execute($achievement_ids)) {
            $success = count($achievement_ids) . " achievements verified successfully!";
        } else {
            $error = "Failed to verify achievements.";
        }
    } else {
        $error = "No achievements selected for verification.";
    }
}

// Handle rejection
if(isset($_GET['reject']) && is_numeric($_GET['reject'])) {
    $achievement_id = $_GET['reject'];
    
    $stmt_reject = $pdo->prepare("DELETE FROM achievements WHERE id = ?");
    if($stmt_reject->execute([$achievement_id])) {
        $success = "Achievement rejected and removed successfully!";
    } else {
        $error = "Failed to reject achievement.";
    }
}

// Get filter parameters
$status_filter = isset($_GET['status']) ? $_GET['status'] : 'pending';
$class_filter = isset($_GET['class']) ? $_GET['class'] : '';
$search = isset($_GET['search']) ? $_GET['search'] : '';

// Get mentor's students
$stmt_students = $pdo->prepare("SELECT sd.id as student_detail_id, sd.roll_number, sd.class, sd.section, 
                                        u.id as user_id, u.full_name
                                 FROM student_details sd 
                                 JOIN users u ON sd.user_id = u.id 
                                 WHERE sd.mentor_id = ?");
$stmt_students->execute([$_SESSION['user_id']]);
$mentor_students = $stmt_students->fetchAll();

$student_ids = array_column($mentor_students, 'student_detail_id');
$student_map = [];
foreach($mentor_students as $s) {
    $student_map[$s['student_detail_id']] = $s;
}

// Build query for achievements
$query = "SELECT a.*, sd.roll_number, sd.class, sd.section, u.full_name as student_name
          FROM achievements a
          JOIN student_details sd ON a.student_id = sd.id
          JOIN users u ON sd.user_id = u.id
          WHERE sd.mentor_id = ?";
$params = [$_SESSION['user_id']];

if($status_filter == 'pending') {
    $query .= " AND a.verified_by_mentor = FALSE";
} elseif($status_filter == 'verified') {
    $query .= " AND a.verified_by_mentor = TRUE";
}

if($class_filter) {
    $query .= " AND sd.class = ?";
    $params[] = $class_filter;
}

if($search) {
    $query .= " AND (a.achievement_title LIKE ? OR u.full_name LIKE ? OR sd.roll_number LIKE ?)";
    $search_term = "%$search%";
    $params[] = $search_term;
    $params[] = $search_term;
    $params[] = $search_term;
}

$query .= " ORDER BY a.created_at DESC";
$stmt_achievements = $pdo->prepare($query);
$stmt_achievements->execute($params);
$achievements = $stmt_achievements->fetchAll();

// Get unique classes for filter
$stmt_classes = $pdo->prepare("SELECT DISTINCT sd.class 
                               FROM student_details sd 
                               WHERE sd.mentor_id = ? AND sd.class IS NOT NULL");
$stmt_classes->execute([$_SESSION['user_id']]);
$classes = $stmt_classes->fetchAll();

// Statistics
$stmt_stats = $pdo->prepare("SELECT 
                              COUNT(CASE WHEN verified_by_mentor = FALSE THEN 1 END) as pending,
                              COUNT(CASE WHEN verified_by_mentor = TRUE THEN 1 END) as verified,
                              COUNT(*) as total
                             FROM achievements a
                             JOIN student_details sd ON a.student_id = sd.id
                             WHERE sd.mentor_id = ?");
$stmt_stats->execute([$_SESSION['user_id']]);
$stats = $stmt_stats->fetch();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Verify Achievements - Mentor Dashboard</title>
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

        .alert-info {
            background: #d1ecf1;
            color: #0c5460;
            border-left: 4px solid #17a2b8;
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
            text-align: center;
            cursor: pointer;
            transition: all 0.3s;
        }

        .stat-card:hover {
            transform: translateY(-5px);
        }

        .stat-card i {
            font-size: 2rem;
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

        /* Filter Bar */
        .filter-bar {
            background: white;
            padding: 20px;
            border-radius: 15px;
            margin-bottom: 20px;
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
            width: 100%;
            padding: 10px;
            background: #667eea;
            color: white;
            border: none;
            border-radius: 8px;
            cursor: pointer;
        }

        /* Bulk Actions */
        .bulk-actions {
            background: white;
            padding: 15px 20px;
            border-radius: 15px;
            margin-bottom: 20px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 15px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.08);
            display: none;
        }

        .bulk-actions.show {
            display: flex;
        }

        .selected-count {
            font-weight: 600;
            color: #667eea;
        }

        .btn-bulk {
            padding: 8px 20px;
            background: #28a745;
            color: white;
            border: none;
            border-radius: 8px;
            cursor: pointer;
        }

        .btn-bulk-danger {
            background: #dc3545;
        }

        /* Achievements Grid */
        .achievements-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(380px, 1fr));
            gap: 25px;
        }

        .achievement-card {
            background: white;
            border-radius: 15px;
            overflow: hidden;
            box-shadow: 0 5px 20px rgba(0,0,0,0.08);
            transition: all 0.3s;
            position: relative;
        }

        .achievement-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 30px rgba(0,0,0,0.15);
        }

        .achievement-card.selected {
            border: 2px solid #667eea;
        }

        .checkbox-wrapper {
            position: absolute;
            top: 15px;
            right: 15px;
            z-index: 10;
        }

        .checkbox-wrapper input {
            width: 20px;
            height: 20px;
            cursor: pointer;
        }

        .achievement-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 15px 20px;
        }

        .student-info {
            font-size: 0.9rem;
            margin-bottom: 5px;
        }

        .achievement-title {
            font-size: 1.1rem;
            font-weight: bold;
        }

        .achievement-body {
            padding: 20px;
        }

        .achievement-description {
            color: #666;
            line-height: 1.6;
            margin-bottom: 15px;
        }

        .achievement-meta {
            display: flex;
            justify-content: space-between;
            margin-bottom: 15px;
            font-size: 0.85rem;
            color: #999;
        }

        .certificate-section {
            background: #f8f9fa;
            padding: 15px;
            border-radius: 10px;
            margin-bottom: 15px;
        }

        .certificate-label {
            font-weight: 600;
            margin-bottom: 10px;
            color: #555;
        }

        .certificate-preview {
            margin-top: 10px;
        }

        .certificate-link {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 8px 15px;
            background: #667eea;
            color: white;
            text-decoration: none;
            border-radius: 5px;
            font-size: 0.85rem;
        }

        .status-badge {
            display: inline-block;
            padding: 4px 10px;
            border-radius: 20px;
            font-size: 0.75rem;
            font-weight: 600;
        }

        .status-pending {
            background: #fff3cd;
            color: #856404;
        }

        .status-verified {
            background: #d4edda;
            color: #155724;
        }

        .action-buttons {
            display: flex;
            gap: 10px;
            margin-top: 15px;
        }

        .btn-verify {
            flex: 1;
            padding: 8px;
            background: #28a745;
            color: white;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            text-decoration: none;
            text-align: center;
        }

        .btn-reject {
            flex: 1;
            padding: 8px;
            background: #dc3545;
            color: white;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            text-decoration: none;
            text-align: center;
        }

        .btn-view {
            flex: 1;
            padding: 8px;
            background: #17a2b8;
            color: white;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            text-decoration: none;
            text-align: center;
        }

        /* Modal */
        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.8);
            z-index: 1000;
            justify-content: center;
            align-items: center;
        }

        .modal-content {
            max-width: 90%;
            max-height: 90%;
        }

        .modal-content img,
        .modal-content iframe {
            max-width: 100%;
            max-height: 90vh;
        }

        .close-modal {
            position: absolute;
            top: 20px;
            right: 40px;
            color: white;
            font-size: 40px;
            cursor: pointer;
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
            
            .achievements-grid {
                grid-template-columns: 1fr;
            }
            
            .filter-form {
                flex-direction: column;
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
            <a href="verify_achievements.php" class="active">
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
                    <i class="fas fa-check-circle"></i> Verify Student Achievements
                </h2>
                <p>Review and verify achievements submitted by your students</p>
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

            <!-- Statistics Cards -->
            <div class="stats-grid">
                <div class="stat-card" onclick="filterStatus('pending')">
                    <i class="fas fa-clock" style="color: #ffc107;"></i>
                    <div class="number"><?php echo $stats['pending']; ?></div>
                    <div class="label">Pending Verification</div>
                </div>
                <div class="stat-card" onclick="filterStatus('verified')">
                    <i class="fas fa-check-circle" style="color: #28a745;"></i>
                    <div class="number"><?php echo $stats['verified']; ?></div>
                    <div class="label">Verified</div>
                </div>
                <div class="stat-card" onclick="filterStatus('all')">
                    <i class="fas fa-trophy" style="color: #667eea;"></i>
                    <div class="number"><?php echo $stats['total']; ?></div>
                    <div class="label">Total Achievements</div>
                </div>
            </div>

            <!-- Filter Bar -->
            <div class="filter-bar">
                <form method="GET" class="filter-form">
                    <input type="hidden" name="status" value="<?php echo $status_filter; ?>">
                    <div class="filter-group">
                        <label><i class="fas fa-filter"></i> Class</label>
                        <select name="class">
                            <option value="">All Classes</option>
                            <?php foreach($classes as $class): ?>
                                <option value="<?php echo $class['class']; ?>" <?php echo $class_filter == $class['class'] ? 'selected' : ''; ?>>
                                    <?php echo $class['class']; ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="filter-group">
                        <label><i class="fas fa-search"></i> Search</label>
                        <input type="text" name="search" placeholder="Search by student or achievement..." value="<?php echo htmlspecialchars($search); ?>">
                    </div>
                    <div class="filter-group">
                        <button type="submit"><i class="fas fa-filter"></i> Apply Filters</button>
                    </div>
                    <?php if($class_filter || $search): ?>
                        <div class="filter-group">
                            <a href="?status=<?php echo $status_filter; ?>" class="btn-bulk" style="background: #6c757d; text-decoration: none; display: inline-block; text-align: center; line-height: 38px; width: 100%;">
                                <i class="fas fa-times"></i> Clear Filters
                            </a>
                        </div>
                    <?php endif; ?>
                </form>
            </div>

            <!-- Bulk Actions Bar -->
            <div class="bulk-actions" id="bulkActions">
                <div>
                    <i class="fas fa-check-square"></i>
                    <span class="selected-count" id="selectedCount">0</span> achievements selected
                </div>
                <div>
                    <button type="button" class="btn-bulk" onclick="submitBulkVerify()">
                        <i class="fas fa-check-double"></i> Verify Selected
                    </button>
                    <button type="button" class="btn-bulk btn-bulk-danger" onclick="clearSelection()">
                        <i class="fas fa-times"></i> Clear Selection
                    </button>
                </div>
            </div>

            <!-- Achievements Grid -->
            <?php if(count($achievements) > 0): ?>
                <form method="POST" id="bulkForm">
                    <input type="hidden" name="bulk_verify" value="1">
                    <div class="achievements-grid">
                        <?php foreach($achievements as $achievement): ?>
                            <div class="achievement-card" data-id="<?php echo $achievement['id']; ?>">
                                <div class="checkbox-wrapper">
                                    <input type="checkbox" name="achievement_ids[]" value="<?php echo $achievement['id']; ?>" class="achievement-checkbox" onchange="updateBulkActions()">
                                </div>
                                <div class="achievement-header">
                                    <div class="student-info">
                                        <i class="fas fa-user-graduate"></i> <?php echo htmlspecialchars($achievement['student_name']); ?>
                                        <span style="margin-left: 10px;">
                                            <i class="fas fa-id-card"></i> <?php echo htmlspecialchars($achievement['roll_number']); ?>
                                        </span>
                                    </div>
                                    <div class="student-info">
                                        <i class="fas fa-graduation-cap"></i> Class: <?php echo htmlspecialchars($achievement['class'] ?: 'Not assigned'); ?>
                                        <?php if($achievement['section']): ?>
                                            - Section <?php echo htmlspecialchars($achievement['section']); ?>
                                        <?php endif; ?>
                                    </div>
                                    <div class="achievement-title">
                                        <?php echo htmlspecialchars($achievement['achievement_title']); ?>
                                    </div>
                                </div>
                                <div class="achievement-body">
                                    <div class="achievement-meta">
                                        <span>
                                            <i class="fas fa-calendar-alt"></i> 
                                            <?php echo date('d-m-Y', strtotime($achievement['created_at'])); ?>
                                        </span>
                                        <span class="status-badge <?php echo $achievement['verified_by_mentor'] ? 'status-verified' : 'status-pending'; ?>">
                                            <?php echo $achievement['verified_by_mentor'] ? '✓ Verified' : '⏳ Pending'; ?>
                                        </span>
                                    </div>
                                    
                                    <div class="achievement-description">
                                        <?php echo nl2br(htmlspecialchars($achievement['description'])); ?>
                                    </div>
                                    
                                    <?php if($achievement['certificate_path']): ?>
                                        <div class="certificate-section">
                                            <div class="certificate-label">
                                                <i class="fas fa-certificate"></i> Certificate Attached:
                                            </div>
                                            <div class="certificate-preview">
                                                <?php 
                                                $file_ext = pathinfo($achievement['certificate_path'], PATHINFO_EXTENSION);
                                                $file_url = "../uploads/certificates/" . $achievement['certificate_path'];
                                                ?>
                                                <button type="button" class="certificate-link" onclick="viewCertificate('<?php echo $file_url; ?>', '<?php echo strtolower($file_ext); ?>')">
                                                    <i class="fas fa-eye"></i> View Certificate
                                                </button>
                                            </div>
                                        </div>
                                    <?php endif; ?>
                                    
                                    <?php if(!$achievement['verified_by_mentor']): ?>
                                        <div class="action-buttons">
                                            <a href="?verify=<?php echo $achievement['id']; ?>&status=<?php echo $status_filter; ?>&class=<?php echo urlencode($class_filter); ?>&search=<?php echo urlencode($search); ?>" 
                                               class="btn-verify" 
                                               onclick="return confirm('Verify this achievement?')">
                                                <i class="fas fa-check"></i> Verify
                                            </a>
                                            <a href="?reject=<?php echo $achievement['id']; ?>&status=<?php echo $status_filter; ?>&class=<?php echo urlencode($class_filter); ?>&search=<?php echo urlencode($search); ?>" 
                                               class="btn-reject" 
                                               onclick="return confirm('Reject and delete this achievement? This action cannot be undone!')">
                                                <i class="fas fa-times"></i> Reject
                                            </a>
                                        </div>
                                    <?php else: ?>
                                        <div class="action-buttons">
                                            <div class="btn-view" style="background: #6c757d;">
                                                <i class="fas fa-check-circle"></i> Verified on <?php echo date('d-m-Y', strtotime($achievement['verified_at'])); ?>
                                            </div>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </form>
            <?php else: ?>
                <div class="no-data">
                    <i class="fas fa-trophy"></i>
                    <h3>No Achievements Found</h3>
                    <p>
                        <?php if($status_filter == 'pending'): ?>
                            No pending achievements to verify.
                        <?php elseif($status_filter == 'verified'): ?>
                            No verified achievements yet.
                        <?php else: ?>
                            No achievements submitted by your students.
                        <?php endif; ?>
                    </p>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Modal for Certificate Preview -->
    <div id="certificateModal" class="modal">
        <span class="close-modal" onclick="closeModal()">&times;</span>
        <div class="modal-content" id="modalContent"></div>
    </div>

    <script>
        // Filter by status
        function filterStatus(status) {
            window.location.href = '?status=' + status + '<?php echo $class_filter ? "&class=" . urlencode($class_filter) : ""; ?><?php echo $search ? "&search=" . urlencode($search) : ""; ?>';
        }
        
        // Certificate viewing
        function viewCertificate(fileUrl, fileType) {
            const modal = document.getElementById('certificateModal');
            const modalContent = document.getElementById('modalContent');
            
            modal.style.display = 'flex';
            
            if(fileType === 'jpg' || fileType === 'jpeg' || fileType === 'png') {
                modalContent.innerHTML = `<img src="${fileUrl}" alt="Certificate">`;
            } else if(fileType === 'pdf') {
                modalContent.innerHTML = `<iframe src="${fileUrl}" width="100%" height="100%"></iframe>`;
            }
        }
        
        function closeModal() {
            const modal = document.getElementById('certificateModal');
            modal.style.display = 'none';
            document.getElementById('modalContent').innerHTML = '';
        }
        
        // Close modal on escape key
        document.addEventListener('keydown', (e) => {
            if(e.key === 'Escape') {
                closeModal();
            }
        });
        
        // Bulk actions
        let selectedCount = 0;
        
        function updateBulkActions() {
            const checkboxes = document.querySelectorAll('.achievement-checkbox:checked');
            selectedCount = checkboxes.length;
            
            const bulkActions = document.getElementById('bulkActions');
            const selectedCountSpan = document.getElementById('selectedCount');
            
            if(selectedCount > 0) {
                bulkActions.classList.add('show');
                selectedCountSpan.textContent = selectedCount;
            } else {
                bulkActions.classList.remove('show');
            }
            
            // Highlight selected cards
            document.querySelectorAll('.achievement-card').forEach(card => {
                const checkbox = card.querySelector('.achievement-checkbox');
                if(checkbox.checked) {
                    card.classList.add('selected');
                } else {
                    card.classList.remove('selected');
                }
            });
        }
        
        function submitBulkVerify() {
            const checkboxes = document.querySelectorAll('.achievement-checkbox:checked');
            if(checkboxes.length === 0) {
                alert('Please select achievements to verify.');
                return;
            }
            
            if(confirm(`Are you sure you want to verify ${checkboxes.length} achievement(s)?`)) {
                document.getElementById('bulkForm').submit();
            }
        }
        
        function clearSelection() {
            document.querySelectorAll('.achievement-checkbox').forEach(checkbox => {
                checkbox.checked = false;
            });
            updateBulkActions();
        }
        
        // Select all functionality (optional - can add a "Select All" button)
        function selectAll() {
            const checkboxes = document.querySelectorAll('.achievement-checkbox');
            const allChecked = Array.from(checkboxes).every(cb => cb.checked);
            
            checkboxes.forEach(checkbox => {
                checkbox.checked = !allChecked;
            });
            updateBulkActions();
        }
        
        // Initialize bulk actions on page load
        document.addEventListener('DOMContentLoaded', function() {
            updateBulkActions();
        });
    </script>
</body>
</html>