<?php
require_once '../includes/session.php';
require_once '../includes/db_connect.php';

if($_SESSION['role'] != 'student') {
    header("Location: ../login.php");
    exit();
}

$success = '';
$error = '';

// Get student details
$stmt = $pdo->prepare("SELECT u.id, u.username, u.full_name, u.email, u.created_at,
                       sd.roll_number, sd.class, sd.section, sd.parent_phone, sd.address, sd.mentor_id
                       FROM users u 
                       LEFT JOIN student_details sd ON u.id = sd.user_id 
                       WHERE u.id = ?");
$stmt->execute([$_SESSION['user_id']]);
$student = $stmt->fetch();

// Get mentor information if assigned
$mentor_name = '';
if($student['mentor_id']) {
    $stmt2 = $pdo->prepare("SELECT full_name FROM users WHERE id = ?");
    $stmt2->execute([$student['mentor_id']]);
    $mentor = $stmt2->fetch();
    $mentor_name = $mentor['full_name'];
}

// Handle profile update
if($_SERVER['REQUEST_METHOD'] == 'POST') {
    if(isset($_POST['update_profile'])) {
        $full_name = trim($_POST['full_name']);
        $email = trim($_POST['email']);
        $parent_phone = trim($_POST['parent_phone']);
        $address = trim($_POST['address']);
        
        // Update users table
        $stmt3 = $pdo->prepare("UPDATE users SET full_name = ?, email = ? WHERE id = ?");
        if($stmt3->execute([$full_name, $email, $_SESSION['user_id']])) {
            $_SESSION['full_name'] = $full_name;
            $_SESSION['email'] = $email;
            
            // Update student_details table
            $stmt4 = $pdo->prepare("UPDATE student_details SET parent_phone = ?, address = ? WHERE user_id = ?");
            $stmt4->execute([$parent_phone, $address, $_SESSION['user_id']]);
            
            $success = "Profile updated successfully!";
            
            // Refresh data
            header("Refresh:0");
        } else {
            $error = "Failed to update profile.";
        }
    }
    
    // Handle password change
    if(isset($_POST['change_password'])) {
        $current_password = md5(trim($_POST['current_password']));
        $new_password = trim($_POST['new_password']);
        $confirm_password = trim($_POST['confirm_password']);
        
        // Verify current password
        $stmt5 = $pdo->prepare("SELECT password FROM users WHERE id = ?");
        $stmt5->execute([$_SESSION['user_id']]);
        $user_pass = $stmt5->fetch();
        
        if($user_pass['password'] != $current_password) {
            $error = "Current password is incorrect!";
        } elseif(strlen($new_password) < 6) {
            $error = "New password must be at least 6 characters long!";
        } elseif($new_password != $confirm_password) {
            $error = "New password and confirm password do not match!";
        } else {
            $new_password_hashed = md5($new_password);
            $stmt6 = $pdo->prepare("UPDATE users SET password = ? WHERE id = ?");
            if($stmt6->execute([$new_password_hashed, $_SESSION['user_id']])) {
                $success = "Password changed successfully!";
            } else {
                $error = "Failed to change password.";
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Profile - Student Dashboard</title>
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

        /* Profile Cards */
        .profile-container {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(450px, 1fr));
            gap: 25px;
        }

        .profile-card {
            background: white;
            border-radius: 15px;
            padding: 25px;
            box-shadow: 0 5px 20px rgba(0,0,0,0.08);
            transition: transform 0.3s, box-shadow 0.3s;
        }

        .profile-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 30px rgba(0,0,0,0.12);
        }

        .card-header {
            display: flex;
            align-items: center;
            gap: 15px;
            margin-bottom: 25px;
            padding-bottom: 15px;
            border-bottom: 2px solid #f0f0f0;
        }

        .card-header i {
            font-size: 2rem;
            color: #667eea;
        }

        .card-header h3 {
            font-size: 1.5rem;
            color: #333;
        }

        /* Profile Info */
        .profile-info {
            margin-bottom: 20px;
        }

        .info-row {
            display: flex;
            padding: 12px 0;
            border-bottom: 1px solid #f0f0f0;
        }

        .info-label {
            width: 140px;
            font-weight: 600;
            color: #555;
        }

        .info-value {
            flex: 1;
            color: #333;
        }

        .info-value i {
            margin-right: 5px;
            color: #667eea;
        }

        /* Form Styles */
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
        .form-group textarea {
            width: 100%;
            padding: 12px;
            border: 2px solid #e0e0e0;
            border-radius: 8px;
            font-size: 1rem;
            transition: all 0.3s;
            font-family: inherit;
        }

        .form-group input:focus,
        .form-group textarea:focus {
            outline: none;
            border-color: #667eea;
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
        }

        textarea {
            resize: vertical;
            min-height: 80px;
        }

        /* Button Styles */
        .btn {
            padding: 12px 25px;
            border: none;
            border-radius: 8px;
            font-size: 1rem;
            cursor: pointer;
            transition: all 0.3s;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }

        .btn-primary {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(102, 126, 234, 0.4);
        }

        .btn-secondary {
            background: #6c757d;
            color: white;
        }

        .btn-secondary:hover {
            background: #5a6268;
        }

        .btn-danger {
            background: #dc3545;
            color: white;
        }

        .btn-danger:hover {
            background: #c82333;
        }

        /* Badge */
        .badge {
            display: inline-block;
            padding: 5px 12px;
            border-radius: 20px;
            font-size: 0.85rem;
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
            
            .profile-container {
                grid-template-columns: 1fr;
            }
            
            .info-row {
                flex-direction: column;
            }
            
            .info-label {
                width: 100%;
                margin-bottom: 5px;
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
            <a href="profile.php" class="active">
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
                    <i class="fas fa-user-circle"></i> My Profile
                </h2>
                <p>View and manage your personal information</p>
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

            <div class="profile-container">
                <!-- Profile Information Card -->
                <div class="profile-card">
                    <div class="card-header">
                        <i class="fas fa-id-card"></i>
                        <h3>Profile Information</h3>
                    </div>
                    
                    <div class="profile-info">
                        <div class="info-row">
                            <div class="info-label">Full Name:</div>
                            <div class="info-value">
                                <i class="fas fa-user"></i> <?php echo htmlspecialchars($student['full_name']); ?>
                            </div>
                        </div>
                        <div class="info-row">
                            <div class="info-label">Username:</div>
                            <div class="info-value">
                                <i class="fas fa-at"></i> <?php echo htmlspecialchars($student['username']); ?>
                            </div>
                        </div>
                        <div class="info-row">
                            <div class="info-label">Email:</div>
                            <div class="info-value">
                                <i class="fas fa-envelope"></i> <?php echo htmlspecialchars($student['email']); ?>
                            </div>
                        </div>
                        <div class="info-row">
                            <div class="info-label">Roll Number:</div>
                            <div class="info-value">
                                <i class="fas fa-hashtag"></i> <?php echo htmlspecialchars($student['roll_number']); ?>
                            </div>
                        </div>
                        <div class="info-row">
                            <div class="info-label">Class & Section:</div>
                            <div class="info-value">
                                <i class="fas fa-graduation-cap"></i> 
                                <?php echo $student['class'] ? htmlspecialchars($student['class']) : 'Not assigned'; ?>
                                <?php echo $student['section'] ? ' - Section ' . htmlspecialchars($student['section']) : ''; ?>
                            </div>
                        </div>
                        <div class="info-row">
                            <div class="info-label">Mentor:</div>
                            <div class="info-value">
                                <i class="fas fa-chalkboard-user"></i> 
                                <?php echo $mentor_name ? htmlspecialchars($mentor_name) : 'Not assigned yet'; ?>
                                <?php if(!$mentor_name): ?>
                                    <span class="badge badge-warning">Pending</span>
                                <?php else: ?>
                                    <span class="badge badge-success">Assigned</span>
                                <?php endif; ?>
                            </div>
                        </div>
                        <div class="info-row">
                            <div class="info-label">Parent Phone:</div>
                            <div class="info-value">
                                <i class="fas fa-phone"></i> 
                                <?php echo $student['parent_phone'] ? htmlspecialchars($student['parent_phone']) : 'Not provided'; ?>
                            </div>
                        </div>
                        <div class="info-row">
                            <div class="info-label">Address:</div>
                            <div class="info-value">
                                <i class="fas fa-map-marker-alt"></i> 
                                <?php echo $student['address'] ? htmlspecialchars($student['address']) : 'Not provided'; ?>
                            </div>
                        </div>
                        <div class="info-row">
                            <div class="info-label">Member Since:</div>
                            <div class="info-value">
                                <i class="fas fa-calendar-alt"></i> 
                                <?php echo date('F d, Y', strtotime($student['created_at'])); ?>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Edit Profile Card -->
                <div class="profile-card">
                    <div class="card-header">
                        <i class="fas fa-edit"></i>
                        <h3>Edit Profile</h3>
                    </div>
                    
                    <form method="POST">
                        <div class="form-group">
                            <label><i class="fas fa-user"></i> Full Name</label>
                            <input type="text" name="full_name" value="<?php echo htmlspecialchars($student['full_name']); ?>" required>
                        </div>
                        
                        <div class="form-group">
                            <label><i class="fas fa-envelope"></i> Email</label>
                            <input type="email" name="email" value="<?php echo htmlspecialchars($student['email']); ?>" required>
                        </div>
                        
                        <div class="form-group">
                            <label><i class="fas fa-phone"></i> Parent Phone</label>
                            <input type="tel" name="parent_phone" value="<?php echo htmlspecialchars($student['parent_phone']); ?>" placeholder="Enter parent's phone number">
                        </div>
                        
                        <div class="form-group">
                            <label><i class="fas fa-map-marker-alt"></i> Address</label>
                            <textarea name="address" placeholder="Enter your address"><?php echo htmlspecialchars($student['address']); ?></textarea>
                        </div>
                        
                        <button type="submit" name="update_profile" class="btn btn-primary">
                            <i class="fas fa-save"></i> Update Profile
                        </button>
                    </form>
                </div>

                <!-- Change Password Card -->
                <div class="profile-card">
                    <div class="card-header">
                        <i class="fas fa-lock"></i>
                        <h3>Change Password</h3>
                    </div>
                    
                    <form method="POST">
                        <div class="form-group">
                            <label><i class="fas fa-key"></i> Current Password</label>
                            <input type="password" name="current_password" placeholder="Enter current password" required>
                        </div>
                        
                        <div class="form-group">
                            <label><i class="fas fa-lock"></i> New Password</label>
                            <input type="password" name="new_password" placeholder="Enter new password (min 6 characters)" required>
                        </div>
                        
                        <div class="form-group">
                            <label><i class="fas fa-check-circle"></i> Confirm New Password</label>
                            <input type="password" name="confirm_password" placeholder="Confirm new password" required>
                        </div>
                        
                        <button type="submit" name="change_password" class="btn btn-secondary">
                            <i class="fas fa-exchange-alt"></i> Change Password
                        </button>
                    </form>
                </div>

                <!-- Quick Stats Card -->
                <div class="profile-card">
                    <div class="card-header">
                        <i class="fas fa-chart-simple"></i>
                        <h3>Quick Statistics</h3>
                    </div>
                    
                    <?php
                    // Get total achievements
                    $stmt7 = $pdo->prepare("SELECT COUNT(*) as total FROM achievements WHERE student_id = ?");
                    $stmt7->execute([$student['id']]);
                    $total_achievements = $stmt7->fetch()['total'];
                    
                    // Get verified achievements
                    $stmt8 = $pdo->prepare("SELECT COUNT(*) as total FROM achievements WHERE student_id = ? AND verified_by_mentor = TRUE");
                    $stmt8->execute([$student['id']]);
                    $verified_achievements = $stmt8->fetch()['total'];
                    
                    // Get total marks entries
                    $stmt9 = $pdo->prepare("SELECT COUNT(*) as total FROM semester_marks WHERE student_id = ?");
                    $stmt9->execute([$student['id']]);
                    $total_marks = $stmt9->fetch()['total'];
                    ?>
                    
                    <div class="profile-info">
                        <div class="info-row">
                            <div class="info-label">Total Achievements:</div>
                            <div class="info-value">
                                <strong><?php echo $total_achievements; ?></strong>
                            </div>
                        </div>
                        <div class="info-row">
                            <div class="info-label">Verified Achievements:</div>
                            <div class="info-value">
                                <strong><?php echo $verified_achievements; ?></strong>
                                <?php if($total_achievements > 0): ?>
                                    <span class="badge badge-success">
                                        <?php echo round(($verified_achievements/$total_achievements)*100); ?>% Verified
                                    </span>
                                <?php endif; ?>
                            </div>
                        </div>
                        <div class="info-row">
                            <div class="info-label">Subjects Enrolled:</div>
                            <div class="info-value">
                                <strong><?php echo $total_marks; ?></strong>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        // Add active class to current nav item
        const currentLocation = window.location.pathname;
        const navLinks = document.querySelectorAll('.sidebar a');
        navLinks.forEach(link => {
            if(link.getAttribute('href') === 'profile.php') {
                link.classList.add('active');
            }
        });

        // Form validation for password change
        const passwordForm = document.querySelector('form[name="change_password"]');
        if(passwordForm) {
            passwordForm.addEventListener('submit', function(e) {
                const newPassword = this.querySelector('input[name="new_password"]').value;
                const confirmPassword = this.querySelector('input[name="confirm_password"]').value;
                
                if(newPassword.length < 6) {
                    e.preventDefault();
                    alert('New password must be at least 6 characters long!');
                } else if(newPassword !== confirmPassword) {
                    e.preventDefault();
                    alert('New password and confirm password do not match!');
                }
            });
        }
    </script>
</body>
</html>