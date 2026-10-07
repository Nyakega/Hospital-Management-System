<?php
// HMS PRO-CORE | Central Database Connection
$host = "localhost";
$user = "root";
$pass = ""; 
$db   = "hospital"; // Based on your error, your DB name is 'hospital'

// Create connection
$conn = new mysqli($host, $user, $pass, $db);

// Check if the connection worked
if ($conn->connect_error) {
    // This will display a clean error message if MySQL is turned off in XAMPP
    die("<div style='color:red; font-family:sans-serif; padding:20px; border:1px solid red;'>
            <strong>❌ Database Connection Failed:</strong> " . $conn->connect_error . " <br>
            <em>Check if MySQL is started in your XAMPP Control Panel.</em>
         </div>");
}

// Set charset to avoid errors with special characters
$conn->set_charset("utf8mb4");

// The $conn variable is now ready for doctors.php to use!
?>