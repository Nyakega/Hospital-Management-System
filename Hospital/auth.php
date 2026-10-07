<?php
function requireRole($allowedRoles) {
    // 1. Ensure session is started
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }

    // 2. Check if user is even logged in
    if (!isset($_SESSION['role'])) {
        header("Location: login.php?error=not_logged_in");
        exit();
    }

    // 3. Convert input to an array if it was passed as a single string
    $rolesArray = is_array($allowedRoles) ? $allowedRoles : [$allowedRoles];

    // 4. Case-insensitive check: Convert everything to lowercase for comparison
    $userRole = strtolower($_SESSION['role']);
    $allowedRolesLower = array_map('strtolower', $rolesArray);

    if (!in_array($userRole, $allowedRolesLower)) {
        // Redirect if they don't have the right permission
        header("Location: dashboard.php?error=unauthorized");
        exit();
    }
}
?>