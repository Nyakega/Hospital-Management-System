<?php
/**
 * HMS PRO-CORE 
 * Logout Handler
 */

// 1. Start the session so we can access it to destroy it
session_start();

// 2. Unset all of the session variables
$_SESSION = array();

// 3. If it's desired to kill the session, also delete the session cookie.
// This is a "deep clean" that forces the browser to forget the session completely.
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params["path"], $params["domain"],
        $params["secure"], $params["httponly"]
    );
}

// 4. Finally, destroy the session on the server
session_destroy();

// 5. Redirect to the login page
// Note: Ensure your file is named 'login.php'. If it is 'index.php', change it below.
header("Location: login.php?msg=logged_out");
exit;
?>