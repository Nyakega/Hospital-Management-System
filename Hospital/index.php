<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>HMS PRO-CORE | Enterprise Dashboard</title>

<link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">

<style>

/* ===== COLOR SYSTEM ===== */
:root {
    --primary: #1e3a8a;
    --secondary: #1e293b;
    --accent: #2563eb;
    --bg: #f1f5f9;
    --white: #fff;
    --success: #10b981;
    --danger: #ef4444;
    --warning: #f59e0b;
    --sidebar-width: 270px;
}

/* ===== GLOBAL ===== */
* {
    margin:0;
    padding:0;
    box-sizing:border-box;
    font-family:'Segoe UI', sans-serif;
}

body {
    display:flex;
    background:var(--bg);
    color:var(--secondary);
}

/* ===== SIDEBAR ===== */
.sidebar {
    width:var(--sidebar-width);
    height:100vh;
    background:linear-gradient(180deg, #1e3a8a, #172554);
    color:white;
    position:fixed;
    display:flex;
    flex-direction:column;
    box-shadow:4px 0 15px rgba(0,0,0,0.1);
}

.sidebar-header {
    padding:25px;
    text-align:center;
    font-size:1.4rem;
    font-weight:bold;
    border-bottom:1px solid rgba(255,255,255,0.1);
}

.sidebar-scroll {
    overflow-y:auto;
    flex-grow:1;
}

/* SCROLLBAR */
.sidebar-scroll::-webkit-scrollbar {
    width:5px;
}
.sidebar-scroll::-webkit-scrollbar-thumb {
    background:#3b82f6;
}

/* SECTION */
.menu-section {
    padding:20px 25px 10px;
    font-size:0.7rem;
    letter-spacing:1px;
    text-transform:uppercase;
    color:rgba(255,255,255,0.5);
}

/* LINKS */
.sidebar a {
    display:flex;
    align-items:center;
    gap:12px;
    padding:14px 25px;
    color:rgba(255,255,255,0.85);
    text-decoration:none;
    transition:0.25s;
    border-left:4px solid transparent;
}

.sidebar a i {
    width:20px;
    text-align:center;
}

.sidebar a:hover {
    background:rgba(255,255,255,0.08);
    border-left-color:var(--accent);
}

.sidebar a.active {
    background:var(--accent);
    border-left-color:white;
}

/* ===== CONTENT ===== */
.content {
    margin-left:var(--sidebar-width);
    width:100%;
}

/* ===== TOPBAR ===== */
.topbar {
    height:70px;
    background:white;
    display:flex;
    justify-content:space-between;
    align-items:center;
    padding:0 30px;
    box-shadow:0 3px 10px rgba(0,0,0,0.05);
}

/* SEARCH */
.search-box {
    position:relative;
}

.search-box input {
    padding:10px 15px 10px 35px;
    border-radius:20px;
    border:1px solid #ddd;
    background:#f8fafc;
}

.search-box i {
    position:absolute;
    left:10px;
    top:11px;
    color:#94a3b8;
}

/* PROFILE */
.profile {
    display:flex;
    align-items:center;
    gap:15px;
}

.avatar {
    width:40px;
    height:40px;
    background:var(--accent);
    border-radius:50%;
    color:white;
    display:flex;
    align-items:center;
    justify-content:center;
    font-weight:bold;
}

/* ===== DASHBOARD ===== */
.container {
    padding:30px;
}

/* CARDS */
.cards {
    display:grid;
    grid-template-columns:repeat(auto-fit, minmax(230px,1fr));
    gap:25px;
    margin-bottom:30px;
}

.card {
    background:white;
    padding:25px;
    border-radius:15px;
    position:relative;
    box-shadow:0 4px 10px rgba(0,0,0,0.05);
    transition:0.3s;
}

.card:hover {
    transform:translateY(-5px);
}

.card h3 {
    font-size:1.8rem;
    color:var(--primary);
}

.card p {
    color:#64748b;
}

.card i {
    position:absolute;
    right:20px;
    top:20px;
    opacity:0.1;
    font-size:2.5rem;
}

/* TABLE */
.table-card {
    background:white;
    padding:25px;
    border-radius:15px;
    box-shadow:0 4px 10px rgba(0,0,0,0.05);
}

.table-header {
    display:flex;
    justify-content:space-between;
    margin-bottom:15px;
}

table {
    width:100%;
    border-collapse:collapse;
}

th {
    text-align:left;
    padding:12px;
    background:#f8fafc;
    font-size:0.8rem;
    text-transform:uppercase;
}

td {
    padding:12px;
    border-bottom:1px solid #eee;
}

.badge {
    padding:5px 12px;
    border-radius:20px;
    font-size:0.75rem;
}

.bg-success { background:#dcfce7; color:#166534; }
.bg-warning { background:#fef3c7; color:#92400e; }
.bg-danger { background:#fee2e2; color:#991b1b; }

</style>
</head>

<body>

<!-- ===== SIDEBAR ===== -->
<nav class="sidebar">
    <div class="sidebar-header">🏥 HMS PRO-CORE</div>

    <div class="sidebar-scroll">

        <div class="menu-section">System Overview</div>
        <a href="dashboard.php" class="active">
            <i class="fa-solid fa-chart-pie"></i> Executive Dashboard
        </a>

        <div class="menu-section">Clinical Modules</div>
        <a href="patient.php">
            <i class="fa-solid fa-hospital-user"></i> Patient Registry & EMR
        </a>
        <a href="doctors.php"><i class="fa-solid fa-user-doctor"></i> Doctor Mgmt</a>
        <a href="appointments.php"><i class="fa-solid fa-calendar-check"></i> Scheduling & Appointments</a>
        <a href="inpatient.php"><i class="fa-solid fa-bed"></i> Bed Management</a>

        <div class="menu-section">Diagnostics & Pharmacy</div>
        <a href="pharmacy.php"><i class="fa-solid fa-pills"></i> Pharmacy Stock</a>
        <a href="lab.php"><i class="fa-solid fa-microscope"></i> Laboratory</a>
        <a href="radiology.php"><i class="fa-solid fa-x-ray"></i> Radiology</a>

        <div class="menu-section">Admin & Finance</div>
        <a href="billing.php"><i class="fa-solid fa-file-invoice-dollar"></i> Billing & M-Pesa</a>
        <a href="insurance.php"><i class="fa-solid fa-shield-halved"></i> Claims Mgmt</a>
        <a href="documents.php"><i class="fa-solid fa-folder-open"></i> Documents</a>

        <div class="menu-section">Operations</div>
        <a href="ambulance.php"><i class="fa-solid fa-ambulance"></i> Emergency Ops</a>
        <a href="reports.php"><i class="fa-solid fa-chart-line"></i> Analytics Dashboard</a>
        <a href="admin.php"><i class="fa-solid fa-gears"></i> Admin Control</a>

    </div>
</nav>

<!-- ===== CONTENT ===== -->
<div class="content">

    <div class="topbar">
        <div class="search-box">
            <i class="fa fa-search"></i>
            <input type="text" placeholder="Search patients, reports...">
        </div>

        <div class="profile">
            <div>
                <strong>C. Nyakega</strong><br>
                <small>System Admin</small>
            </div>
            <div class="avatar">CN</div>
        </div>
    </div>

    <div class="container">

        <!-- CARDS -->
        <div class="cards">
            <div class="card">
                <h3>124</h3>
                <p>Total Patients</p>
                <i class="fa-solid fa-user-plus"></i>
            </div>

            <div class="card">
                <h3>45</h3>
                <p>Doctors</p>
                <i class="fa-solid fa-user-doctor"></i>
            </div>

            <div class="card">
                <h3>KES 450K</h3>
                <p>Revenue</p>
                <i class="fa-solid fa-money-bill"></i>
            </div>

            <div class="card">
                <h3>12</h3>
                <p>Emergencies</p>
                <i class="fa-solid fa-ambulance"></i>
            </div>
        </div>

        <!-- TABLE -->
        <div class="table-card">
            <div class="table-header">
                <h3>Recent Activities</h3>
                <button style="background:var(--accent);color:white;border:none;padding:8px 12px;border-radius:6px;">
                    Export
                </button>
            </div>

            <table>
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Name</th>
                        <th>Department</th>
                        <th>Status</th>
                    </tr>
                </thead>

                <tbody>
                    <tr>
                        <td>#001</td>
                        <td>John Doe</td>
                        <td>Cardiology</td>
                        <td><span class="badge bg-success">Admitted</span></td>
                    </tr>

                    <tr>
                        <td>#002</td>
                        <td>Jane Smith</td>
                        <td>Pharmacy</td>
                        <td><span class="badge bg-warning">Pending</span></td>
                    </tr>
                </tbody>
            </table>
        </div>

    </div>

</div>

</body>
</html>