<?php
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Restricts access based on allowed system roles
function verifyAccess(array $allowedRoles) {
    if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'], $allowedRoles)) {
        header("Location: /online-exam-portal/index.php?error=unauthorized");
        exit();
    }
}
?>