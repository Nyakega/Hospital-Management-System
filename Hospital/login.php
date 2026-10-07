<?php
// ================= ERROR REPORTING =================
error_reporting(E_ALL);
ini_set('display_errors', 1);

// ================= SECURITY & SESSION =================
header("Cache-Control: no-cache, no-store, must-revalidate");
header("Pragma: no-cache");
header("Expires: 0");

session_start();
include "db.php"; 

// ================= SYSTEM LOGGER =================
function logLogin($conn, $identifier, $action) {
    $stmt = $conn->prepare("INSERT INTO admin_logs (admin_username, action, target_user) VALUES (?,?,?)");
    $target = "login_system";
    if ($stmt) {
        $stmt->bind_param("sss", $identifier, $action, $target);
        $stmt->execute();
        $stmt->close();
    }
}

// ================= AUTO-CREATE SYSTEM ADMIN =================
$admin_username = "admin";
$admin_email = "admin@gmail.com";
$default_password = "admin123";

$stmt = $conn->prepare("SELECT id FROM users WHERE username=? OR email=?");
$stmt->bind_param("ss", $admin_username, $admin_email);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    $hash = password_hash($default_password, PASSWORD_DEFAULT);
    $role = "Admin";
    $full_name = "System Administrator";

    $insert = $conn->prepare("INSERT INTO users (username, email, password, role, full_name, account_status, failed_attempts) VALUES (?, ?, ?, ?, ?, 'Active', 0)");
    $insert->bind_param("sssss", $admin_username, $admin_email, $hash, $role, $full_name);
    $insert->execute();
    $insert->close();
}
$stmt->close();

// ================= LOGIN PROCESSING =================
$error = "";
$success = "";

if (isset($_POST['login'])) {
    $login_identity = trim($_POST['login_identity']); 
    $password = $_POST['password'];

    $stmt = $conn->prepare("SELECT * FROM users WHERE username=? OR email=?");
    $stmt->bind_param("ss", $login_identity, $login_identity);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        $user = $result->fetch_assoc();

        if ($user['account_status'] === "Locked") {
            $error = "🚫 Account locked. Contact Administrator.";
            logLogin($conn, $login_identity, "Blocked login attempt on locked account");
        } 
        else {
            if (password_verify($password, $user['password'])) {
                
                // Reset failed attempts
                $reset = $conn->prepare("UPDATE users SET failed_attempts=0 WHERE id=?");
                $reset->bind_param("i", $user['id']);
                $reset->execute();

                // Initialize Sessions
                $_SESSION['user_id']   = $user['id'];
                $_SESSION['username']  = $user['username'];
                $_SESSION['role']      = $user['role']; // Store original case for matching
                $_SESSION['full_name'] = $user['full_name'];

                // Record Login Timestamp
                $updateLogin = $conn->prepare("UPDATE users SET last_login=NOW() WHERE id=?");
                $updateLogin->bind_param("i", $user['id']);
                $updateLogin->execute();

                logLogin($conn, $user['username'], "Successful login");

                // ================= DIRECT PATHWAY REDIRECTION =================
                // This connects your users directly to the pages like patient.php
                $role_map = [
                    'Admin'        => 'admin.php',
                    'Doctor'       => 'doctors.php',
                    'Receptionist' => 'patient.php', // This opens your EMR page directly
                    'Nurse'        => 'inpatient.php',
                    'Pharmacist'   => 'pharmacy.php'
                ];

                $current_role = $_SESSION['role'];
                $target_page = isset($role_map[$current_role]) ? $role_map[$current_role] : 'dashboard.php';
                
                header("Location: /hospital/" . $target_page);
                exit();
            } 
            else {
                $new_attempts = $user['failed_attempts'] + 1;
                
                if ($new_attempts >= 3) {
                    $lock = $conn->prepare("UPDATE users SET account_status='Locked' WHERE id=?");
                    $lock->bind_param("i", $user['id']);
                    $lock->execute();
                    logLogin($conn, $login_identity, "Account locked due to 3 failed attempts");
                    $error = "🚫 Account locked after 3 failed attempts.";
                } else {
                    $fail = $conn->prepare("UPDATE users SET failed_attempts = ? WHERE id=?");
                    $fail->bind_param("ii", $new_attempts, $user['id']);
                    $fail->execute();
                    
                    logLogin($conn, $login_identity, "Invalid password attempt ($new_attempts)");
                    $remaining = 3 - $new_attempts;
                    $error = "❌ Invalid password. $remaining attempts remaining.";
                }
            }
        }
    } else {
        $error = "❌ Account not found.";
    }
    $stmt->close();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>HMS PRO-CORE | Login</title>
    <style>
        body { font-family:'Segoe UI', sans-serif; background:linear-gradient(135deg,#1e3a8a,#2563eb); display:flex; justify-content:center; align-items:center; height:100vh; margin:0; }
        .card { background:white; padding:40px; border-radius:15px; width:360px; box-shadow: 0 20px 40px rgba(0,0,0,0.3); }
        h2 { color: #1e3a8a; text-align: center; margin-bottom: 25px; }
        input { width:100%; padding:12px; margin:10px 0; border: 1px solid #ddd; border-radius: 6px; box-sizing: border-box; }
        button { width:100%; padding:12px; background:#2563eb; color:white; border:none; border-radius: 6px; cursor: pointer; font-weight: bold; }
        .error { color:#b91c1c; text-align:center; background: #fee2e2; padding: 10px; border-radius: 6px; margin-bottom: 15px; font-size: 14px; }
    </style>
</head>
<body>
    <div class="card">
        <h2>🏥 HMS PRO-CORE</h2>
        <?php if($error) echo "<p class='error'>$error</p>"; ?>
        <form method="POST">
            <input type="text" name="login_identity" placeholder="Username or Email" required>
            <input type="password" name="password" placeholder="Password" required>
            <button name="login">Login</button>
        </form>
        <button onclick="window.location.href='index.php'" style="margin-top:10px; background:#64748b; font-size:13px;">Back to Home Page</button>
    </div>
</body>
</html>