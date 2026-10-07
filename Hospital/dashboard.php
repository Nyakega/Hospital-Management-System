<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

session_start();

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

require_once "db.php";

// USER INFO
$username = $_SESSION['username'];
$role = $_SESSION['role'];

// ================= LIVE DATABASE COUNTS =================
$users_count = $conn->query("SELECT COUNT(*) as total FROM users")->fetch_assoc()['total'];
$staff_count = $conn->query("SELECT COUNT(*) as total FROM staff")->fetch_assoc()['total'];
$patients_count = $conn->query("SELECT COUNT(*) as total FROM patients")->fetch_assoc()['total'];
$appointments_count = $conn->query("SELECT COUNT(*) as total FROM appointments")->fetch_assoc()['total'];

// RECENT APPOINTMENTS
$recent = $conn->query("
    SELECT a.appointment_id, p.full_name AS patient, s.full_name AS doctor, a.appointment_date
    FROM appointments a
    JOIN patients p ON a.patient_id = p.patient_id
    JOIN staff s ON a.staff_id = s.staff_id
    ORDER BY a.appointment_date DESC
    LIMIT 5
");
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>HMS PRO-CORE | Dashboard</title>
<link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">

<style>
body{
    margin:0;
    font-family:'Segoe UI', sans-serif;
    background:#eef2f7;
    display:flex;
}

/* SIDEBAR */
.sidebar{
    width:260px;
    background:linear-gradient(180deg,#1e3a8a,#2563eb);
    color:white;
    height:100vh;
    padding:20px;
}
.sidebar a{
    display:block;
    color:white;
    padding:12px;
    margin:10px 0;
    border-radius:10px;
    text-decoration:none;
}
.sidebar a:hover{background:rgba(255,255,255,0.2);}

/* CONTENT */
.content{flex:1;padding:25px;}

/* TOPBAR */
.topbar{
    background:white;
    padding:18px;
    border-radius:12px;
    display:flex;
    justify-content:space-between;
    box-shadow:0 5px 15px rgba(0,0,0,0.05);
}

/* STATS */
.stats{
    display:grid;
    grid-template-columns:repeat(auto-fit,minmax(200px,1fr));
    gap:20px;
    margin-top:20px;
}
.stat{
    padding:20px;
    border-radius:15px;
    color:white;
    box-shadow:0 5px 15px rgba(0,0,0,0.1);
}
.stat h2{margin:0;font-size:28px;}
.stat p{margin:5px 0;font-size:14px;}

.blue{background:linear-gradient(135deg,#3b82f6,#1e40af);}
.green{background:linear-gradient(135deg,#10b981,#047857);}
.orange{background:linear-gradient(135deg,#f59e0b,#b45309);}
.purple{background:linear-gradient(135deg,#8b5cf6,#5b21b6);}

/* GRID */
.grid{
    display:grid;
    grid-template-columns:2fr 1fr;
    gap:20px;
    margin-top:25px;
}

/* CARD */
.card{
    background:white;
    padding:20px;
    border-radius:15px;
    box-shadow:0 5px 15px rgba(0,0,0,0.05);
}

/* TABLE */
table{
    width:100%;
    border-collapse:collapse;
}
th,td{
    padding:12px;
    border-bottom:1px solid #eee;
    font-size:14px;
}

/* BUTTONS */
.btn{
    display:block;
    padding:12px;
    margin:10px 0;
    border-radius:10px;
    text-decoration:none;
    color:white;
    text-align:center;
}
.btn-blue{background:#2563eb;}
.btn-green{background:#10b981;}
.btn-orange{background:#f59e0b;}

.logout{background:#ef4444;}
</style>
</head>

<body>

<!-- SIDEBAR -->
<div class="sidebar">
    <h2>🏥 HMS PRO</h2>
    <p><?php echo htmlspecialchars($username); ?></p>
    <p style="font-size:12px;"><?php echo htmlspecialchars($role); ?></p>

    <a href="dashboard.php">Dashboard</a>
    <?php if(strtolower($role)==='admin'): ?>
    <a href="admin.php">Admin Panel</a>
    <?php endif; ?>
    <a href="doctors.php">Staff</a>
    <a href="appointments.php">Appointments</a>
    <a href="login.php?logout=true" class="logout">Logout</a>
</div>

<!-- CONTENT -->
<div class="content">

<div class="topbar">
    <h3>Welcome, <?php echo htmlspecialchars($username); ?></h3>
    <span>Live Hospital Overview</span>
</div>

<!-- STATS -->
<div class="stats">
    <div class="stat blue">
        <h2><?php echo $users_count; ?></h2>
        <p>Total Users</p>
    </div>

    <div class="stat green">
        <h2><?php echo $staff_count; ?></h2>
        <p>Total Staff</p>
    </div>

    <div class="stat orange">
        <h2><?php echo $patients_count; ?></h2>
        <p>Total Patients</p>
    </div>

    <div class="stat purple">
        <h2><?php echo $appointments_count; ?></h2>
        <p>Total Appointments</p>
    </div>
</div>

<!-- MAIN GRID -->
<div class="grid">

<!-- RECENT ACTIVITY -->
<div class="card">
    <h3>Recent Appointments</h3>
    <table>
        <tr>
            <th>ID</th>
            <th>Patient</th>
            <th>Doctor</th>
            <th>Date</th>
        </tr>
        <?php while($r = $recent->fetch_assoc()): ?>
        <tr>
            <td>#<?php echo $r['appointment_id']; ?></td>
            <td><?php echo $r['patient']; ?></td>
            <td><?php echo $r['doctor']; ?></td>
            <td><?php echo date('M d, H:i', strtotime($r['appointment_date'])); ?></td>
        </tr>
        <?php endwhile; ?>
    </table>
</div>

<!-- QUICK ACTIONS -->
<div class="card">
    <h3>Quick Actions</h3>

    <?php if(strtolower($role)==='admin'): ?>
    <a href="admin.php" class="btn btn-blue">Admin Panel</a>
    <?php endif; ?>

    <a href="doctors.php" class="btn btn-green">Manage Staff</a>
    <a href="appointments.php" class="btn btn-orange">Schedule Appointment</a>

</div>

</div>

</div>

</body>
</html>