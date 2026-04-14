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

$success = '';
$error = '';

// Create upload directory if not exists
$upload_dir = '../uploads/certificates/';
if (!file_exists($upload_dir)) {
    mkdir($upload_dir, 0777, true);
}

// Handle file upload
if($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['upload_certificate'])) {
    $achievement_id = $_POST['achievement_id'];
    $file = $_FILES['certificate'];
    
    // Validate file
    $allowed_types = ['image/jpeg', 'image/png', 'image/jpg', 'application/pdf'];
    $max_size = 5 * 1024 * 1024; // 5MB
    
    if($file['error'] == 0) {
        if(!in_array($file['type'], $allowed_types)) {
            $error = "Invalid file type. Only JPG, PNG, and PDF files are allowed!";
        } elseif($file['size'] > $max_size) {
            $error = "File size too large. Maximum 5MB allowed!";
        } else {
            // Generate unique filename
            $file_extension = pathinfo($file['name'], PATHINFO_EXTENSION);
            $filename = 'cert_' . $student['id'] . '_' . $achievement_id . '_' . time() . '.' . $file_extension;
            $filepath = $upload_dir . $filename;
            
            if(move_uploaded_file($file['tmp_name'], $filepath)) {
                // Update achievement with certificate path
                $stmt_update = $pdo->prepare("UPDATE achievements SET certificate_path = ? WHERE id = ? AND student_id = ?");
                if($stmt_update->execute([$filename, $achievement_id, $student['id']])) {
                    $success = "Certificate uploaded successfully!";
                } else {
                    $error = "Failed to update database.";
                }
            } else {
                $error = "Failed to upload file.";
            }
        }
    } else {
        $error = "Please select a file to upload.";
    }
}

// Handle delete certificate
if(isset($_GET['delete'])) {
    $achievement_id = $_GET['delete'];
    
    // Get certificate path
    $stmt_get = $pdo->prepare("SELECT certificate_path FROM achievements WHERE id = ? AND student_id = ?");
    $stmt_get->execute([$achievement_id, $student['id']]);
    $cert = $stmt_get->fetch();
    
    if($cert && $cert['certificate_path']) {
        // Delete file from server
        $file_path = $upload_dir . $cert['certificate_path'];
        if(file_exists($file_path)) {
            unlink($file_path);
        }
        
        // Update database
        $stmt_delete = $pdo->prepare("UPDATE achievements SET certificate_path = NULL WHERE id = ?");
        $stmt_delete->execute([$achievement_id]);
        
        $success = "Certificate deleted successfully!";
        header("Refresh:0");
    }
}

// Get all achievements for this student
$stmt_achievements = $pdo->prepare("SELECT * FROM achievements WHERE student_id = ? ORDER BY created_at DESC");
$stmt_achievements->execute([$student['id']]);
$achievements = $stmt_achievements->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Upload Certificate - Student Dashboard</title>
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

        /* Upload Card */
        .upload-card {
            background: white;
            border-radius: 15px;
            padding: 30px;
            margin-bottom: 30px;
            box-shadow: 0 5px 20px rgba(0,0,0,0.08);
        }

        .upload-card h3 {
            margin-bottom: 20px;
            color: #333;
        }

        .upload-area {
            border: 2px dashed #ddd;
            border-radius: 10px;
            padding: 40px;
            text-align: center;
            cursor: pointer;
            transition: all 0.3s;
            margin-bottom: 20px;
        }

        .upload-area:hover {
            border-color: #667eea;
            background: #f8f9fa;
        }

        .upload-area i {
            font-size: 3rem;
            color: #667eea;
            margin-bottom: 10px;
        }

        .upload-area p {
            color: #666;
        }

        .file-input {
            display: none;
        }

        /* Achievements Grid */
        .achievements-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(350px, 1fr));
            gap: 20px;
        }

        .achievement-card {
            background: white;
            border-radius: 15px;
            overflow: hidden;
            box-shadow: 0 5px 20px rgba(0,0,0,0.08);
            transition: transform 0.3s;
        }

        .achievement-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 30px rgba(0,0,0,0.12);
        }

        .achievement-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 15px 20px;
        }

        .achievement-header h4 {
            font-size: 1.1rem;
            margin-bottom: 5px;
        }

        .achievement-date {
            font-size: 0.85rem;
            opacity: 0.9;
        }

        .achievement-body {
            padding: 20px;
        }

        .achievement-description {
            color: #666;
            line-height: 1.6;
            margin-bottom: 15px;
        }

        .certificate-info {
            background: #f8f9fa;
            padding: 15px;
            border-radius: 10px;
            margin-bottom: 15px;
        }

        .certificate-status {
            display: flex;
            align-items: center;
            gap: 10px;
            margin-bottom: 10px;
        }

        .status-badge {
            display: inline-block;
            padding: 4px 10px;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 600;
        }

        .status-verified {
            background: #d4edda;
            color: #155724;
        }

        .status-pending {
            background: #fff3cd;
            color: #856404;
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
            transition: all 0.3s;
            font-size: 0.9rem;
        }

        .certificate-link:hover {
            background: #5a67d8;
            transform: translateY(-2px);
        }

        .btn-delete {
            background: #dc3545;
            color: white;
            border: none;
            padding: 8px 15px;
            border-radius: 5px;
            cursor: pointer;
            transition: all 0.3s;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            font-size: 0.9rem;
        }

        .btn-delete:hover {
            background: #c82333;
        }

        .btn-upload {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border: none;
            padding: 12px 25px;
            border-radius: 8px;
            cursor: pointer;
            transition: all 0.3s;
            font-size: 1rem;
            width: 100%;
        }

        .btn-upload:hover:not(:disabled) {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(102, 126, 234, 0.4);
        }

        .btn-upload:disabled {
            opacity: 0.6;
            cursor: not-allowed;
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

        .form-group select {
            width: 100%;
            padding: 12px;
            border: 2px solid #e0e0e0;
            border-radius: 8px;
            font-size: 1rem;
        }

        .file-info {
            font-size: 0.85rem;
            color: #666;
            margin-top: 10px;
        }

        .no-achievements {
            text-align: center;
            padding: 50px;
            background: white;
            border-radius: 15px;
            color: #666;
        }

        .no-achievements i {
            font-size: 3rem;
            margin-bottom: 15px;
            color: #ddd;
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
            <a href="semester_marks.php">
                <i class="fas fa-chart-line"></i>
                <span>Semester Marks</span>
            </a>
            <a href="add_achievement.php">
                <i class="fas fa-trophy"></i>
                <span>Add Achievement</span>
            </a>
            <a href="upload_certificate.php" class="active">
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
                    <i class="fas fa-cloud-upload-alt"></i> Upload Certificates
                </h2>
                <p>Upload certificates for your achievements</p>
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

            <!-- Upload Section -->
            <?php if(count($achievements) > 0): ?>
                <div class="upload-card">
                    <h3><i class="fas fa-upload"></i> Upload Certificate</h3>
                    <form method="POST" enctype="multipart/form-data" id="uploadForm">
                        <div class="form-group">
                            <label><i class="fas fa-trophy"></i> Select Achievement</label>
                            <select name="achievement_id" id="achievement_id" required>
                                <option value="">-- Select Achievement --</option>
                                <?php foreach($achievements as $ach): ?>
                                    <option value="<?php echo $ach['id']; ?>">
                                        <?php echo htmlspecialchars($ach['achievement_title']); ?>
                                        <?php echo $ach['certificate_path'] ? '(Certificate Uploaded)' : '(No Certificate)'; ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="upload-area" id="uploadArea">
                            <i class="fas fa-cloud-upload-alt"></i>
                            <p>Click or drag & drop certificate file here</p>
                            <p style="font-size: 0.85rem; margin-top: 5px;">Supported formats: JPG, PNG, PDF (Max 5MB)</p>
                        </div>
                        <input type="file" name="certificate" id="certificate" class="file-input" accept=".jpg,.jpeg,.png,.pdf">
                        <div class="file-info" id="fileInfo"></div>
                        <button type="submit" name="upload_certificate" class="btn-upload" id="uploadBtn" disabled>
                            <i class="fas fa-cloud-upload-alt"></i> Upload Certificate
                        </button>
                    </form>
                </div>
            <?php endif; ?>

            <!-- Achievements List -->
            <h3 style="margin-bottom: 20px;">
                <i class="fas fa-list"></i> My Achievements
            </h3>
            
            <?php if(count($achievements) > 0): ?>
                <div class="achievements-grid">
                    <?php foreach($achievements as $ach): ?>
                        <div class="achievement-card">
                            <div class="achievement-header">
                                <h4><?php echo htmlspecialchars($ach['achievement_title']); ?></h4>
                                <div class="achievement-date">
                                    <i class="fas fa-calendar-alt"></i> 
                                    <?php echo date('F d, Y', strtotime($ach['created_at'])); ?>
                                </div>
                            </div>
                            <div class="achievement-body">
                                <div class="achievement-description">
                                    <?php echo nl2br(htmlspecialchars($ach['description'])); ?>
                                </div>
                                
                                <div class="certificate-info">
                                    <div class="certificate-status">
                                        <i class="fas fa-certificate"></i>
                                        <strong>Certificate:</strong>
                                        <?php if($ach['certificate_path']): ?>
                                            <span class="status-badge status-verified">
                                                <i class="fas fa-check"></i> Uploaded
                                            </span>
                                        <?php else: ?>
                                            <span class="status-badge status-pending">
                                                <i class="fas fa-clock"></i> Not Uploaded
                                            </span>
                                        <?php endif; ?>
                                        
                                        <strong style="margin-left: auto;">Verification:</strong>
                                        <?php if($ach['verified_by_mentor']): ?>
                                            <span class="status-badge status-verified">
                                                <i class="fas fa-check-circle"></i> Verified
                                            </span>
                                        <?php else: ?>
                                            <span class="status-badge status-pending">
                                                <i class="fas fa-hourglass-half"></i> Pending
                                            </span>
                                        <?php endif; ?>
                                    </div>
                                    
                                    <?php if($ach['certificate_path']): ?>
                                        <div class="certificate-preview">
                                            <?php 
                                            $file_ext = pathinfo($ach['certificate_path'], PATHINFO_EXTENSION);
                                            $file_url = "../uploads/certificates/" . $ach['certificate_path'];
                                            ?>
                                            <?php if(in_array(strtolower($file_ext), ['jpg', 'jpeg', 'png'])): ?>
                                                <a href="#" class="certificate-link" onclick="viewCertificate('<?php echo $file_url; ?>', 'image')">
                                                    <i class="fas fa-eye"></i> View Certificate
                                                </a>
                                            <?php elseif(strtolower($file_ext) == 'pdf'): ?>
                                                <a href="#" class="certificate-link" onclick="viewCertificate('<?php echo $file_url; ?>', 'pdf')">
                                                    <i class="fas fa-file-pdf"></i> View PDF
                                                </a>
                                            <?php endif; ?>
                                            
                                            <a href="?delete=<?php echo $ach['id']; ?>" 
                                               class="btn-delete" 
                                               onclick="return confirm('Are you sure you want to delete this certificate?')">
                                                <i class="fas fa-trash"></i> Delete
                                            </a>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <div class="no-achievements">
                    <i class="fas fa-trophy"></i>
                    <h3>No Achievements Found</h3>
                    <p>You haven't added any achievements yet. Go to "Add Achievement" to get started.</p>
                    <a href="add_achievement.php" style="display: inline-block; margin-top: 15px; padding: 10px 20px; background: #667eea; color: white; text-decoration: none; border-radius: 8px;">
                        <i class="fas fa-plus"></i> Add Achievement
                    </a>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Modal for Certificate Preview -->
    <div id="certificateModal" class="modal">
        <span class="close-modal" onclick="closeModal()">&times;</span>
        <div class="modal-content" id="modalContent">
        </div>
    </div>

    <script>
        // Drag and drop functionality
        const uploadArea = document.getElementById('uploadArea');
        const fileInput = document.getElementById('certificate');
        const fileInfo = document.getElementById('fileInfo');
        const uploadBtn = document.getElementById('uploadBtn');
        const achievementSelect = document.getElementById('achievement_id');

        uploadArea.addEventListener('click', () => {
            fileInput.click();
        });

        uploadArea.addEventListener('dragover', (e) => {
            e.preventDefault();
            uploadArea.style.borderColor = '#667eea';
            uploadArea.style.background = '#f8f9fa';
        });

        uploadArea.addEventListener('dragleave', () => {
            uploadArea.style.borderColor = '#ddd';
            uploadArea.style.background = 'white';
        });

        uploadArea.addEventListener('drop', (e) => {
            e.preventDefault();
            uploadArea.style.borderColor = '#ddd';
            uploadArea.style.background = 'white';
            
            const files = e.dataTransfer.files;
            if(files.length > 0) {
                fileInput.files = files;
                handleFileSelect(files[0]);
            }
        });

        fileInput.addEventListener('change', (e) => {
            if(e.target.files.length > 0) {
                handleFileSelect(e.target.files[0]);
            }
        });

        function handleFileSelect(file) {
            const allowedTypes = ['image/jpeg', 'image/png', 'image/jpg', 'application/pdf'];
            const maxSize = 5 * 1024 * 1024;
            
            if(!allowedTypes.includes(file.type)) {
                fileInfo.innerHTML = '<span style="color: #dc3545;">❌ Invalid file type. Only JPG, PNG, and PDF are allowed!</span>';
                fileInfo.style.color = '#dc3545';
                uploadBtn.disabled = true;
                return;
            }
            
            if(file.size > maxSize) {
                fileInfo.innerHTML = '<span style="color: #dc3545;">❌ File too large. Maximum 5MB allowed!</span>';
                fileInfo.style.color = '#dc3545';
                uploadBtn.disabled = true;
                return;
            }
            
            const fileSizeKB = (file.size / 1024).toFixed(2);
            fileInfo.innerHTML = `<span style="color: #28a745;">✅ Selected: ${file.name} (${fileSizeKB} KB)</span>`;
            fileInfo.style.color = '#28a745';
            uploadBtn.disabled = false;
        }

        achievementSelect.addEventListener('change', () => {
            if(achievementSelect.value && fileInput.files.length > 0) {
                uploadBtn.disabled = false;
            } else {
                uploadBtn.disabled = true;
            }
        });

        // Form validation
        document.getElementById('uploadForm').addEventListener('submit', (e) => {
            if(!achievementSelect.value) {
                e.preventDefault();
                alert('Please select an achievement!');
                return false;
            }
            if(!fileInput.files.length) {
                e.preventDefault();
                alert('Please select a file to upload!');
                return false;
            }
        });

        // Certificate viewing
        function viewCertificate(fileUrl, type) {
            const modal = document.getElementById('certificateModal');
            const modalContent = document.getElementById('modalContent');
            
            modal.style.display = 'flex';
            
            if(type === 'image') {
                modalContent.innerHTML = `<img src="${fileUrl}" alt="Certificate">`;
            } else if(type === 'pdf') {
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
        
        // Close modal on click outside
        window.onclick = (event) => {
            const modal = document.getElementById('certificateModal');
            if(event.target === modal) {
                closeModal();
            }
        }
    </script>
</body>
</html>