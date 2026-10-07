<?php
// 1. Database Connection Details
$host = "localhost";
$user = "root";
$pass = "";
$dbname = "hospital";

$conn = new mysqli($host, $user, $pass);

if ($conn->connect_error) {
    die("<div style='color:red;'>Connection failed: " . $conn->connect_error . "</div>");
}

// 2. Create Database if it doesn't exist
$conn->query("CREATE DATABASE IF NOT EXISTS $dbname");
$conn->select_db($dbname);

echo "<h3>HMS PRO-CORE | System Initialization</h3>";

// 3. Create Users Table
$table_sql = "CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    role ENUM('Admin', 'Doctor', 'Nurse') DEFAULT 'Doctor',
    full_name VARCHAR(100),
    last_login DATETIME,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)";

if ($conn->query($table_sql)) {
    echo "<p style='color:green;'>✅ Users table is ready.</p>";
} else {
    die("Error creating table: " . $conn->error);
}

// 4. Create the Default Admin Account
$admin_user = 'admin';
$admin_pass = 'admin123';
$hashed_pass = password_hash($admin_pass, PASSWORD_DEFAULT);
$admin_fullname = 'System Administrator';

// Check if admin already exists
$check = $conn->query("SELECT id FROM users WHERE username = '$admin_user'");

if ($check->num_rows == 0) {
    $insert_sql = "INSERT INTO users (username, password, role, full_name) 
                   VALUES ('$admin_user', '$hashed_pass', 'Admin', '$admin_fullname')";
    
    if ($conn->query($insert_sql)) {
        echo "<p style='color:green;'>✅ <strong>Admin Created!</strong><br>
              User: <b>admin</b><br>
              Pass: <b>admin123</b></p>";
    }
} else {
    echo "<p style='color:orange;'>⚠️ Admin account already exists. No changes made.</p>";
}

echo "<hr><p style='color:red;'><strong>CRITICAL:</strong> Please delete <u>setup.php</u> from your folder now for security!</p>";
?>