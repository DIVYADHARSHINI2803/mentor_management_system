<?php
require_once '../includes/session.php';
require_once '../includes/db_connect.php';

if($_SESSION['role'] != 'admin') {
    header("Location: ../login.php");
    exit();
}

$success = '';
$error = '';

// Handle user deletion
if(isset($_GET['delete']) && is_numeric($_GET['delete'])) {
    $user_id = $_GET['delete'];
    
    // Don't allow admin to delete themselves
    if($user_id == $_SESSION['user_id']) {
        $error = "You cannot delete your own account!";
    } else {
        // Check if user exists
        $stmt_check = $pdo->prepare("SELECT role FROM users WHERE id = ?");
        $stmt_check->execute([$user_id]);
        $user = $stmt_check->fetch();
        
        if($user) {
            // Delete user (cascade will handle related tables)
            $stmt_delete = $pdo->prepare("DELETE FROM users WHERE id = ?");
            if($stmt_delete->execute([$user_id])) {
                $success = "User deleted successfully!";
            } else {
                $error = "Failed to delete user.";
            }
        } else {
            $error = "User not found.";
        }
    }
}

// Handle user role update
if(isset($_POST['update_role'])) {
    $user_id = $_POST['user_id'];
    $new_role = $_POST['role'];
    
    // Don't allow admin to change their own role
    if($user_id == $_SESSION['user_id']) {
        $error = "You cannot change your own role!";
    } else {
        $stmt_update = $pdo->prepare("UPDATE users SET role = ? WHERE id = ?");
        if($stmt_update->execute([$new_role, $user_id])) {
            // If changing to student, ensure student_details exists
            if($new_role == 'student') {
                $stmt_check = $pdo->prepare("SELECT id FROM student_details WHERE user_id = ?");
                $stmt_check->execute([$user_id]);
                if(!$stmt_check->fetch()) {
                    $roll_number = 'ROLL' . date('Y') . $user_id;
                    $stmt_insert = $pdo->prepare("INSERT INTO student_details (user_id, roll_number) VALUES (?, ?)");
                    $stmt_insert->execute([$user_id, $roll_number]);
                }
            }
            $success = "User role updated successfully!";
        } else {
            $error = "Failed to update user role.";
        }
    }
}

// Handle add new user
if(isset($_POST['add_user'])) {
    $username = trim($_POST['username']);
    $password = md5(trim($_POST['password']));
    $full_name = trim($_POST['full_name']);
    $email = trim($_POST['email']);
    $role = $_POST['role'];
    
    // Check if username or email exists
    $stmt_check = $pdo->prepare("SELECT id FROM users WHERE username = ? OR email = ?");
    $stmt_check->execute([$username, $email]);
    
    if($stmt_check->fetch()) {
        $error = "Username or email already exists!";
    } else {
        $stmt_insert = $pdo->prepare("INSERT INTO users (username, password, full_name, email, role) VALUES (?, ?, ?, ?, ?)");
        if($stmt_insert->execute([$username, $password, $full_name, $email, $role])) {
            $user_id = $pdo->lastInsertId();
            
            // If student, create student_details entry
            if($role == 'student') {
                $roll_number = 'ROLL' . date('Y') . $user_id;
                $stmt_details = $pdo->prepare("INSERT INTO student_details (user_id, roll_number) VALUES (?, ?)");
                $stmt_details->execute([$user_id, $roll_number]);
            }
            
            $success = "User added successfully!";
        } else {
            $error = "Failed to add user.";
        }
    }
}

// Get filter parameters
$role_filter = isset($_GET['role']) ? $_GET['role'] : 'all';
$search = isset($_GET['search']) ? $_GET['search'] : '';

// Build query
$query = "SELECT u.*, 
          CASE 
              WHEN u.role = 'student' THEN sd.class 
              ELSE NULL 
          END as class,
          CASE 
              WHEN u.role = 'student' THEN sd.section 
              ELSE NULL 
          END as section,
          CASE 
              WHEN u.role = 'student' THEN sd.roll_number 
              ELSE NULL 
          END as roll_number
          FROM users u 
          LEFT JOIN student_details sd ON u.id = sd.user_id";

$where = [];
$params = [];

if($role_filter != 'all') {
    $where[] = "u.role = ?";
    $params[] = $role_filter;
}

if($search) {
    $where[] = "(u.username LIKE ? OR u.full_name LIKE ? OR u.email LIKE ?)";
    $search_term = "%$search%";
    $params[] = $search_term;
    $params[] = $search_term;
    $params[] = $search_term;
}

if(count($where) > 0) {
    $query .= " WHERE " . implode(" AND ", $where);
}

$query .= " ORDER BY u.created_at DESC";
$stmt = $pdo->prepare($query);
$stmt->execute($params);
$users = $stmt->fetchAll();

// Get statistics
$stmt_stats = $pdo->query("SELECT 
    COUNT(CASE WHEN role = 'admin' THEN 1 END) as total_admins,
    COUNT(CASE WHEN role = 'mentor' THEN 1 END) as total_mentors,
    COUNT(CASE WHEN role = 'student' THEN 1 END) as total_students,
    COUNT(*) as total_users
    FROM users");
$stats = $stmt_stats->fetch();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Users - Admin Dashboard</title>
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
            cursor: pointer;
        }

        .stat-card:hover {
            transform: translateY(-5px);
        }

        .stat-card i {
            font-size: 2rem;
            margin-bottom: 10px;
        }

        .stat-card h3 {
            font-size: 0.9rem;
            color: #666;
            margin-bottom: 5px;
        }

        .stat-card .number {
            font-size: 2rem;
            font-weight: bold;
            color: #333;
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

        /* Search and Filter Bar */
        .search-bar {
            background: white;
            padding: 20px;
            border-radius: 15px;
            margin-bottom: 20px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.08);
        }

        .filter-buttons {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
            margin-bottom: 20px;
        }

        .filter-btn {
            padding: 8px 20px;
            border: 2px solid #e0e0e0;
            background: white;
            border-radius: 25px;
            cursor: pointer;
            transition: all 0.3s;
            text-decoration: none;
            color: #666;
        }

        .filter-btn.active {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border-color: #667eea;
        }

        .filter-btn:hover:not(.active) {
            border-color: #667eea;
            background: #f8f9fa;
        }

        .search-input {
            display: flex;
            gap: 10px;
        }

        .search-input input {
            flex: 1;
            padding: 12px;
            border: 2px solid #e0e0e0;
            border-radius: 8px;
            font-size: 1rem;
        }

        .search-input button {
            padding: 12px 25px;
            background: #667eea;
            color: white;
            border: none;
            border-radius: 8px;
            cursor: pointer;
        }

        /* Add User Button */
        .add-user-btn {
            background: linear-gradient(135deg, #28a745 0%, #20c997 100%);
            color: white;
            border: none;
            padding: 12px 25px;
            border-radius: 8px;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            margin-bottom: 20px;
            transition: all 0.3s;
        }

        .add-user-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(40, 167, 69, 0.3);
        }

        /* Users Table */
        .users-table-container {
            background: white;
            border-radius: 15px;
            padding: 20px;
            overflow-x: auto;
            box-shadow: 0 5px 15px rgba(0,0,0,0.08);
        }

        .users-table {
            width: 100%;
            border-collapse: collapse;
        }

        .users-table th,
        .users-table td {
            padding: 12px;
            text-align: left;
            border-bottom: 1px solid #f0f0f0;
        }

        .users-table th {
            background: #f8f9fa;
            font-weight: 600;
            color: #555;
        }

        .users-table tr:hover {
            background: #f8f9fa;
        }

        /* Role Badges */
        .role-badge {
            display: inline-block;
            padding: 5px 12px;
            border-radius: 20px;
            font-size: 0.85rem;
            font-weight: 600;
        }

        .role-admin {
            background: #dc3545;
            color: white;
        }

        .role-mentor {
            background: #17a2b8;
            color: white;
        }

        .role-student {
            background: #28a745;
            color: white;
        }

        /* Action Buttons */
        .action-buttons {
            display: flex;
            gap: 8px;
        }

        .btn-edit, .btn-delete, .btn-view {
            padding: 6px 12px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-size: 0.85rem;
            transition: all 0.3s;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 5px;
        }

        .btn-edit {
            background: #ffc107;
            color: #333;
        }

        .btn-edit:hover {
            background: #e0a800;
        }

        .btn-delete {
            background: #dc3545;
            color: white;
        }

        .btn-delete:hover {
            background: #c82333;
        }

        .btn-view {
            background: #17a2b8;
            color: white;
        }

        .btn-view:hover {
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

        .form-group {
            margin-bottom: 20px;
        }

        .form-group label {
            display: block;
            margin-bottom: 8px;
            color: #555;
            font-weight: 500;
        }

        .form-group input,
        .form-group select,
        .form-group textarea {
            width: 100%;
            padding: 10px;
            border: 2px solid #e0e0e0;
            border-radius: 8px;
            font-size: 1rem;
        }

        .form-group input:focus,
        .form-group select:focus {
            outline: none;
            border-color: #667eea;
        }

        .modal-footer {
            padding: 20px;
            border-top: 1px solid #e0e0e0;
            display: flex;
            justify-content: flex-end;
            gap: 10px;
        }

        .btn-submit {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 10px 20px;
            border: none;
            border-radius: 8px;
            cursor: pointer;
        }

        .btn-cancel {
            background: #6c757d;
            color: white;
            padding: 10px 20px;
            border: none;
            border-radius: 8px;
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
            
            .action-buttons {
                flex-direction: column;
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
                <span>Admin Panel</span>
            </h3>
            <a href="dashboard.php">
                <i class="fas fa-home"></i>
                <span>Dashboard</span>
            </a>
            <a href="manage_users.php" class="active">
                <i class="fas fa-users"></i>
                <span>Manage Users</span>
            </a>
            <a href="assign_mentor.php">
                <i class="fas fa-user-plus"></i>
                <span>Assign Mentor</span>
            </a>
            <a href="view_reports.php">
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
                    <i class="fas fa-users"></i> Manage Users
                </h2>
                <p>Add, edit, and manage all users in the system</p>
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
                <div class="stat-card" onclick="filterByRole('all')">
                    <i class="fas fa-users" style="color: #667eea;"></i>
                    <h3>Total Users</h3>
                    <div class="number"><?php echo $stats['total_users']; ?></div>
                </div>
                <div class="stat-card" onclick="filterByRole('admin')">
                    <i class="fas fa-user-shield" style="color: #dc3545;"></i>
                    <h3>Admins</h3>
                    <div class="number"><?php echo $stats['total_admins']; ?></div>
                </div>
                <div class="stat-card" onclick="filterByRole('mentor')">
                    <i class="fas fa-chalkboard-user" style="color: #17a2b8;"></i>
                    <h3>Mentors</h3>
                    <div class="number"><?php echo $stats['total_mentors']; ?></div>
                </div>
                <div class="stat-card" onclick="filterByRole('student')">
                    <i class="fas fa-user-graduate" style="color: #28a745;"></i>
                    <h3>Students</h3>
                    <div class="number"><?php echo $stats['total_students']; ?></div>
                </div>
            </div>

            <!-- Search and Filter -->
            <div class="search-bar">
                <div class="filter-buttons">
                    <a href="?role=all" class="filter-btn <?php echo $role_filter == 'all' ? 'active' : ''; ?>">All Users</a>
                    <a href="?role=admin" class="filter-btn <?php echo $role_filter == 'admin' ? 'active' : ''; ?>">Admins</a>
                    <a href="?role=mentor" class="filter-btn <?php echo $role_filter == 'mentor' ? 'active' : ''; ?>">Mentors</a>
                    <a href="?role=student" class="filter-btn <?php echo $role_filter == 'student' ? 'active' : ''; ?>">Students</a>
                </div>
                <form method="GET" class="search-input">
                    <input type="hidden" name="role" value="<?php echo $role_filter; ?>">
                    <input type="text" name="search" placeholder="Search by name, username, or email..." value="<?php echo htmlspecialchars($search); ?>">
                    <button type="submit"><i class="fas fa-search"></i> Search</button>
                </form>
            </div>

            <!-- Add User Button -->
            <button class="add-user-btn" onclick="openAddModal()">
                <i class="fas fa-plus"></i> Add New User
            </button>

            <!-- Users Table -->
            <div class="users-table-container">
                <table class="users-table">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Full Name</th>
                            <th>Username</th>
                            <th>Email</th>
                            <th>Role</th>
                            <th>Details</th>
                            <th>Joined</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if(count($users) > 0): ?>
                            <?php foreach($users as $user): ?>
                                <tr>
                                    <td><?php echo $user['id']; ?></td>
                                    <td>
                                        <strong><?php echo htmlspecialchars($user['full_name']); ?></strong>
                                    </td>
                                    <td><?php echo htmlspecialchars($user['username']); ?></td>
                                    <td><?php echo htmlspecialchars($user['email']); ?></td>
                                    <td>
                                        <span class="role-badge role-<?php echo $user['role']; ?>">
                                            <?php echo ucfirst($user['role']); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <?php if($user['role'] == 'student'): ?>
                                            <small>
                                                Roll: <?php echo $user['roll_number'] ?: 'N/A'; ?><br>
                                                Class: <?php echo $user['class'] ?: 'Not assigned'; ?>
                                            </small>
                                        <?php else: ?>
                                            <small>—</small>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <small><?php echo date('d-m-Y', strtotime($user['created_at'])); ?></small>
                                    </td>
                                    <td>
                                        <div class="action-buttons">
                                            <button class="btn-edit" onclick="openEditModal(<?php echo $user['id']; ?>, '<?php echo $user['role']; ?>')">
                                                <i class="fas fa-edit"></i> Edit
                                            </button>
                                            <?php if($user['id'] != $_SESSION['user_id']): ?>
                                                <a href="?delete=<?php echo $user['id']; ?>" class="btn-delete" onclick="return confirm('Are you sure you want to delete this user? This action cannot be undone!')">
                                                    <i class="fas fa-trash"></i> Delete
                                                </a>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="8" style="text-align: center; padding: 50px;">
                                    <i class="fas fa-users" style="font-size: 3rem; color: #ddd; margin-bottom: 10px; display: block;"></i>
                                    No users found
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Add User Modal -->
    <div id="addModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3><i class="fas fa-user-plus"></i> Add New User</h3>
                <span class="close-modal" onclick="closeAddModal()">&times;</span>
            </div>
            <form method="POST">
                <div class="modal-body">
                    <div class="form-group">
                        <label>Full Name *</label>
                        <input type="text" name="full_name" required>
                    </div>
                    <div class="form-group">
                        <label>Username *</label>
                        <input type="text" name="username" required>
                    </div>
                    <div class="form-group">
                        <label>Email *</label>
                        <input type="email" name="email" required>
                    </div>
                    <div class="form-group">
                        <label>Password *</label>
                        <input type="password" name="password" required>
                    </div>
                    <div class="form-group">
                        <label>Role *</label>
                        <select name="role" required>
                            <option value="student">Student</option>
                            <option value="mentor">Mentor</option>
                            <option value="admin">Admin</option>
                        </select>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn-cancel" onclick="closeAddModal()">Cancel</button>
                    <button type="submit" name="add_user" class="btn-submit">Add User</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Edit Role Modal -->
    <div id="editModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3><i class="fas fa-edit"></i> Update User Role</h3>
                <span class="close-modal" onclick="closeEditModal()">&times;</span>
            </div>
            <form method="POST">
                <input type="hidden" name="user_id" id="edit_user_id">
                <div class="modal-body">
                    <div class="form-group">
                        <label>Select New Role</label>
                        <select name="role" id="edit_role" required>
                            <option value="student">Student</option>
                            <option value="mentor">Mentor</option>
                            <option value="admin">Admin</option>
                        </select>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn-cancel" onclick="closeEditModal()">Cancel</button>
                    <button type="submit" name="update_role" class="btn-submit">Update Role</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        // Filter by role function
        function filterByRole(role) {
            window.location.href = '?role=' + role;
        }

        // Add User Modal
        function openAddModal() {
            document.getElementById('addModal').style.display = 'flex';
        }

        function closeAddModal() {
            document.getElementById('addModal').style.display = 'none';
        }

        // Edit Modal
        function openEditModal(userId, currentRole) {
            document.getElementById('edit_user_id').value = userId;
            document.getElementById('edit_role').value = currentRole;
            document.getElementById('editModal').style.display = 'flex';
        }

        function closeEditModal() {
            document.getElementById('editModal').style.display = 'none';
        }

        // Close modals on click outside
        window.onclick = function(event) {
            const addModal = document.getElementById('addModal');
            const editModal = document.getElementById('editModal');
            if (event.target == addModal) {
                closeAddModal();
            }
            if (event.target == editModal) {
                closeEditModal();
            }
        }

        // Form validation for add user
        document.querySelector('#addModal form').addEventListener('submit', function(e) {
            const password = this.querySelector('input[name="password"]').value;
            if(password.length < 4) {
                e.preventDefault();
                alert('Password must be at least 4 characters long!');
            }
        });
    </script>
</body>
</html>