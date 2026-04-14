<?php
function isAdmin() {
    return isset($_SESSION['role']) && $_SESSION['role'] == 'admin';
}

function isMentor() {
    return isset($_SESSION['role']) && $_SESSION['role'] == 'mentor';
}

function isStudent() {
    return isset($_SESSION['role']) && $_SESSION['role'] == 'student';
}

function redirectIfNotAuthorized($allowed_roles) {
    if(!in_array($_SESSION['role'], $allowed_roles)) {
        header("Location: ../dashboard.php");
        exit();
    }
}

function getStudentClass($student_id, $pdo) {
    $stmt = $pdo->prepare("SELECT class, section FROM student_details WHERE user_id = ?");
    $stmt->execute([$student_id]);
    return $stmt->fetch();
}
?>