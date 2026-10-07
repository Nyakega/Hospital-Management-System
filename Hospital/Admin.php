<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

session_start();
include 'db.php';

$message = "";

// ================= LOGOUT =================
if (isset($_GET['logout'])) {
    session_destroy();
    header("Location: login.php");
    exit();
}

// ================= ACCESS CONTROL =================
if (!isset($_SESSION['user_id']) || strtolower($_SESSION['role']) !== 'admin') {
?>
<!DOCTYPE html>
<html>
<head>
<title>Access Denied</title>
<style>
body{margin:0;font-family:'Segoe UI';background:linear-gradient(135deg,#ef4444,#b91c1c);display:flex;justify-content:center;align-items:center;height:100vh;color:white;}
.card{background:rgba(0,0,0,0.6);padding:40px;border-radius:15px;text-align:center;}
.card a{display:inline-block;margin-top:15px;padding:10px 20px;background:#2563eb;color:white;border-radius:8px;text-decoration:none;}
</style>
</head>
<body>
<div class="card">
<h1>🚫 Access Denied</h1>
<p>Admin privileges required</p>
<a href="login.php">Login</a>
</div>
</body>
</html>
<?php exit(); }

// ================= ADMIN LOGGING FUNCTION =================
function logAction($conn, $admin, $action, $target='') {
    $stmt = $conn->prepare("INSERT INTO admin_logs (admin_username, action, target, created_at) VALUES (?,?,?,NOW())");
    $stmt->bind_param("sss", $admin, $action, $target);
    $stmt->execute();
}

// ================= ADD USER =================
if (isset($_POST['add_user'])) {
    $full_name = $_POST['full_name'];
    $username = $_POST['username'];
    $email = $_POST['email'];
    $role = $_POST['role'];
    $password = password_hash($_POST['password'], PASSWORD_DEFAULT);

    $check = $conn->prepare("SELECT id FROM users WHERE username=? OR email=?");
    $check->bind_param("ss", $username, $email);
    $check->execute();
    $check->store_result();

    if ($check->num_rows > 0) {
        echo "<script>alert('Username or Email already exists!');</script>";
    } else {
        $stmt = $conn->prepare("INSERT INTO users (full_name, username, email, password, role, account_status, failed_attempts) VALUES (?,?,?,?,?,'Active',0)");
        $stmt->bind_param("sssss", $full_name, $username, $email, $password, $role);
        $stmt->execute();
        logAction($conn, $_SESSION['username'], "Added new user", $username);
        echo "<script>alert('User added successfully');</script>";
    }
}

// ================= DELETE USER =================
if (isset($_GET['delete_user'])) {
    $stmt = $conn->prepare("DELETE FROM users WHERE id=?");
    $stmt->bind_param("i", $_GET['delete_user']);
    $stmt->execute();
    logAction($conn, $_SESSION['username'], "Deleted user", $_GET['delete_user']);
}

// ================= RESET PASSWORD =================
if (isset($_POST['reset_user_pass'])) {
    $new_pass = password_hash($_POST['new_pass'], PASSWORD_DEFAULT);
    $stmt = $conn->prepare("UPDATE users SET password=? WHERE id=?");
    $stmt->bind_param("si", $new_pass, $_POST['user_id']);
    $stmt->execute();
    logAction($conn, $_SESSION['username'], "Reset password", $_POST['user_id']);
}

// ================= UPDATE ROLE =================
if (isset($_POST['update_role'])) {
    $stmt = $conn->prepare("UPDATE users SET role=? WHERE id=?");
    $stmt->bind_param("si", $_POST['new_role'], $_POST['user_id']);
    $stmt->execute();
    logAction($conn, $_SESSION['username'], "Updated role to ".$_POST['new_role'], $_POST['user_id']);
}

// ================= LOCK / UNLOCK =================
if (isset($_GET['lock'])) {
    $stmt = $conn->prepare("UPDATE users SET account_status='Locked' WHERE id=?");
    $stmt->bind_param("i", $_GET['lock']);
    $stmt->execute();
    logAction($conn, $_SESSION['username'], "Locked account", $_GET['lock']);
}
if (isset($_GET['unlock'])) {
    $stmt = $conn->prepare("UPDATE users SET account_status='Active', failed_attempts=0 WHERE id=?");
    $stmt->bind_param("i", $_GET['unlock']);
    $stmt->execute();
    logAction($conn, $_SESSION['username'], "Unlocked account", $_GET['unlock']);
}

// ================= APPROVE RESET =================
if (isset($_GET['approve_reset'])) {
    $stmt = $conn->prepare("UPDATE users SET reset_approved=1 WHERE id=?");
    $stmt->bind_param("i", $_GET['approve_reset']);
    $stmt->execute();
    logAction($conn, $_SESSION['username'], "Approved password reset", $_GET['approve_reset']);
}

// ================= DELETE RESET REQUEST =================
if (isset($_GET['delete_reset'])) {
    $stmt = $conn->prepare("UPDATE users SET reset_token=NULL, reset_expires=NULL, reset_approved=0 WHERE id=?");
    $stmt->bind_param("i", $_GET['delete_reset']);
    $stmt->execute();
    logAction($conn, $_SESSION['username'], "Deleted password reset request", $_GET['delete_reset']);
}

// ================= CLEAR AI SECURITY ALERT =================
if (isset($_GET['delete_alert'])) {
    $stmt = $conn->prepare("UPDATE users SET failed_attempts=0 WHERE username=?");
    $stmt->bind_param("s", $_GET['delete_alert']);
    $stmt->execute();
    logAction($conn, $_SESSION['username'], "Cleared AI security alert", $_GET['delete_alert']);
}

// ================= DELETE SINGLE ADMIN LOG =================
if (isset($_GET['delete_log'])) {
    $stmt = $conn->prepare("DELETE FROM admin_logs WHERE id=?");
    $stmt->bind_param("i", $_GET['delete_log']);
    $stmt->execute();
}

// ================= FEATURE: MULTIPLE LOG DELETE =================
if (isset($_POST['bulk_delete_logs']) && !empty($_POST['log_ids'])) {
    $ids = $_POST['log_ids'];
    $placeholders = str_repeat('?,', count($ids) - 1) . '?';
    $types = str_repeat('i', count($ids));
    $stmt = $conn->prepare("DELETE FROM admin_logs WHERE id IN ($placeholders)");
    $stmt->bind_param($types, ...$ids);
    $stmt->execute();
    logAction($conn, $_SESSION['username'], "Bulk deleted logs", count($ids)." logs");
}

// ================= PAGINATION & SEARCH =================
$searchQuery = "";
if (isset($_GET['search']) && !empty($_GET['search'])) {
    $searchQuery = $_GET['search'];
}
$logsPerPage = 10;
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$start = ($page -1) * $logsPerPage;
$totalLogs = $conn->query("SELECT COUNT(*) as total FROM admin_logs")->fetch_assoc()['total'];
$totalPages = ceil($totalLogs / $logsPerPage);
?>

<!DOCTYPE html>
<html>
<head>
<title>HMS PRO | Admin</title>
<link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
<style>
body{margin:0;font-family:'Segoe UI';background:#f1f5f9;display:flex;}
.sidebar{width:260px;background:#1e3a8a;color:white;height:100vh;padding:20px;position:fixed;}
.sidebar h2{margin-bottom:20px;}
.sidebar a{display:block;color:white;padding:10px;text-decoration:none;border-radius:6px;margin:5px 0;}
.sidebar a:hover{background:#2563eb;}
.logout-btn{background:#ef4444;text-align:center;margin-top:20px;transition:0.3s;}
.logout-btn:hover{background:#dc2626;}
.content{flex:1;padding:20px;margin-left:260px;}
.topbar{background:white;padding:15px;border-radius:10px;display:flex;justify-content:space-between;box-shadow:0 3px 10px rgba(0,0,0,0.1);}
.card{background:white;padding:20px;margin-top:20px;border-radius:12px;box-shadow:0 4px 12px rgba(0,0,0,0.05);}
table{width:100%;border-collapse:collapse;}
td,th{padding:10px;border-bottom:1px solid #eee;text-align:center;}
.btn{padding:6px 10px;border:none;border-radius:6px;cursor:pointer;font-weight:bold;}
.red{background:#ef4444;color:white;}
.blue{background:#2563eb;color:white;}
.green{background:#16a34a;color:white;}
.badge{background:#dcfce7;padding:4px 8px;border-radius:8px;}
.locked{background:#fee2e2;color:#991b1b;}
.audit-log{padding:10px;margin:5px 0;border-left:4px solid #2563eb;background:#f8fafc;border-radius:6px;font-size:14px; display:flex; align-items:center; gap:10px;}
.alert{background:#fee2e2;padding:10px;margin:5px;border-radius:8px; display:flex; justify-content:space-between; align-items:center;}
.modal{display:none;position:fixed;z-index:999;left:0;top:0;width:100%;height:100%;overflow:auto;background:rgba(0,0,0,0.5);}
.modal-content{background:white;margin:15% auto;padding:20px;border-radius:12px;width:400px;text-align:center;}
.modal button{margin:5px;padding:6px 12px;border:none;border-radius:6px;cursor:pointer;}
.pagination{margin-top:15px;text-align:center;}
.pagination a{margin:0 5px;text-decoration:none;padding:5px 10px;border:1px solid #ccc;border-radius:6px;color:#1e3a8a;}
.pagination a.active{background:#1e3a8a;color:white;}
.search-bar input{padding:6px;width:200px;margin-right:10px;}
</style>
<script>
function confirmDelete(modalId, url){
    var modal = document.getElementById(modalId);
    modal.style.display='block';
    document.getElementById(modalId+'-yes').onclick = function(){ window.location = url; };
}
function closeModal(modalId){
    document.getElementById(modalId).style.display='none';
}
function toggleAllLogs(source) {
    checkboxes = document.getElementsByName('log_ids[]');
    for(var i=0, n=checkboxes.length; i<n; i++) {
        checkboxes[i].checked = source.checked;
    }
}
</script>
</head>

<body>
<div class="sidebar">
<h2>🏥 HMS PRO</h2>
<p><?php echo $_SESSION['username']; ?></p>
<a href="dashboard.php">Dashboard</a>
<a href="?logout=true" class="logout-btn">🚪 Logout</a>
</div>

<div class="content">
<div class="topbar"><h3>Admin Control Panel</h3></div>

<div class="card search-bar">
<form method="GET">
<input type="text" name="search" placeholder="Search users..." value="<?php echo htmlspecialchars($searchQuery); ?>">
<button type="submit" class="btn blue">Search</button>
</form>
</div>

<div class="card">
<h3>🔐 Password Reset Requests</h3>
<table>
<tr><th>User</th><th>Email</th><th>Status</th><th>Action</th></tr>
<?php
$resets = $conn->query("SELECT id, username, email, reset_approved FROM users WHERE reset_token IS NOT NULL");
while($r = $resets->fetch_assoc()):
?>
<tr>
<td><?php echo $r['username']; ?></td>
<td><?php echo $r['email']; ?></td>
<td><?php echo $r['reset_approved'] ? "<span class='badge'>Approved</span>" : "<span class='locked'>Pending</span>"; ?></td>
<td>
<?php if(!$r['reset_approved']): ?>
<a href="?approve_reset=<?php echo $r['id']; ?>"><button class="btn green">Approve</button></a>
<?php endif; ?>
<button class="btn red" onclick="confirmDelete('modalReset<?php echo $r['id']; ?>','?delete_reset=<?php echo $r['id']; ?>')">Delete</button>
<div id="modalReset<?php echo $r['id']; ?>" class="modal">
<div class="modal-content">
<p>Confirm delete reset request for <?php echo $r['username']; ?>?</p>
<button id="modalReset<?php echo $r['id']; ?>-yes" class="btn red">Yes</button>
<button onclick="closeModal('modalReset<?php echo $r['id']; ?>')" class="btn blue">No</button>
</div>
</div>
</td>
</tr>
<?php endwhile; ?>
</table>
</div>

<div class="card">
<h3>Add Staff</h3>
<form method="POST">
<input name="full_name" placeholder="Full Name" required>
<input name="username" placeholder="Username" required>
<input name="email" type="email" placeholder="Email" required>
<input type="password" name="password" placeholder="Password" required>
<select name="role">
<option>Admin</option><option>Doctor</option><option>Nurse</option><option>Lab Technician</option><option>Clinical Admin</option><option>Pharmacist</option><option>Receptionist</option>
</select>
<button name="add_user" class="btn blue">Create</button>
</form>
</div>

<div class="card">
<h3>User Management</h3>
<table>
<tr><th>Name</th><th>Role</th><th>Status</th><th>Attempts</th><th>Actions</th></tr>
<?php
$query = "SELECT * FROM users";
if($searchQuery!="") $query .= " WHERE username LIKE '%$searchQuery%' OR full_name LIKE '%$searchQuery%'";
$users = $conn->query($query);
while($u = $users->fetch_assoc()):
?>
<tr>
<td><?php echo $u['full_name']; ?></td>
<td>
<form method="POST" style="display:inline;">
<input type="hidden" name="user_id" value="<?php echo $u['id']; ?>">
<select name="new_role">
<option <?php if($u['role']=='Admin') echo 'selected';?>>Admin</option>
<option <?php if($u['role']=='Doctor') echo 'selected';?>>Doctor</option>
<option <?php if($u['role']=='Nurse') echo 'selected';?>>Nurse</option>
<option <?php if($u['role']=='Lab Technician') echo 'selected';?>>Lab Technician</option>
<option <?php if($u['role']=='Clinical Admin') echo 'selected';?>>Clinical Admin</option>
<option <?php if($u['role']=='Pharmacist') echo 'selected';?>>Pharmacist</option>
<option <?php if($u['role']=='Receptionist') echo 'selected';?>>Receptionist</option>
</select>
<button name="update_role" class="btn blue">Role</button>
</form>
</td>
<td><span class="<?php echo $u['account_status']=='Locked'?'locked':'badge'; ?>"><?php echo $u['account_status']; ?></span></td>
<td><?php echo $u['failed_attempts']; ?></td>
<td>
<form method="POST" style="display:inline;">
<input type="hidden" name="user_id" value="<?php echo $u['id']; ?>">
<input name="new_pass" placeholder="New Pass" required style="width:80px;">
<button name="reset_user_pass" class="btn green">Reset</button>
</form>
<?php if($u['account_status']=="Active"): ?>
<a href="?lock=<?php echo $u['id']; ?>"><button class="btn red">Lock</button></a>
<?php else: ?>
<a href="?unlock=<?php echo $u['id']; ?>"><button class="btn green">Unlock</button></a>
<?php endif; ?>
<button class="btn red" onclick="confirmDelete('modalUser<?php echo $u['id']; ?>','?delete_user=<?php echo $u['id']; ?>')">Delete</button>
<div id="modalUser<?php echo $u['id']; ?>" class="modal">
<div class="modal-content">
<p>Confirm delete user <?php echo $u['username']; ?>?</p>
<button id="modalUser<?php echo $u['id']; ?>-yes" class="btn red">Yes</button>
<button onclick="closeModal('modalUser<?php echo $u['id']; ?>')" class="btn blue">No</button>
</div>
</div>
</td>
</tr>
<?php endwhile; ?>
</table>
</div>

<div class="card">
<h3>🧠 AI Security Alerts</h3>
<?php
$alerts = $conn->query("SELECT username, failed_attempts, account_status FROM users WHERE failed_attempts >=2 OR account_status='Locked'");
if($alerts->num_rows == 0) echo "<p style='color:gray;'>No security alerts.</p>";
while($a = $alerts->fetch_assoc()):
?>
<div class="alert">
<span>⚠ <b><?php echo $a['username']; ?></b> - Attempts: <?php echo $a['failed_attempts']; ?> - Status: <?php echo $a['account_status']; ?></span>
<button class="btn red" onclick="confirmDelete('modalAlert<?php echo $a['username']; ?>','?delete_alert=<?php echo $a['username']; ?>')">Clear</button>
<div id="modalAlert<?php echo $a['username']; ?>" class="modal">
<div class="modal-content">
<p>Clear AI alert for <?php echo $a['username']; ?>?</p>
<button id="modalAlert<?php echo $a['username']; ?>-yes" class="btn red">Yes</button>
<button onclick="closeModal('modalAlert<?php echo $a['username']; ?>')" class="btn blue">No</button>
</div>
</div>
</div>
<?php endwhile; ?>
</div>

<div class="card">
<form method="POST">
<div style="display:flex; justify-content:space-between; align-items:center;">
    <h3>🔐 Admin Activity Logs</h3>
    <button type="submit" name="bulk_delete_logs" class="btn red" onclick="return confirm('Delete selected logs?')"><i class="fa fa-trash-sweep"></i> Delete Selected</button>
</div>
<div style="margin: 10px 0;">
    <input type="checkbox" onclick="toggleAllLogs(this)"> <b>Select All</b>
</div>
<?php
$logs = $conn->query("SELECT * FROM admin_logs ORDER BY created_at DESC LIMIT $start,$logsPerPage");
while($l = $logs->fetch_assoc()):
?>
<div class="audit-log">
<input type="checkbox" name="log_ids[]" value="<?php echo $l['id']; ?>">
<div style="flex:1; display:flex; justify-content:space-between;">
    <span>👤 <?php echo $l['admin_username']; ?> | ⚙ <?php echo $l['action']; ?> <?php if($l['target']): ?> → <?php echo $l['target']; ?><?php endif; ?> | 🕒 <?php echo $l['created_at']; ?></span>
    <button type="button" class="btn red" onclick="confirmDelete('modalLog<?php echo $l['id']; ?>','?delete_log=<?php echo $l['id']; ?>')">Delete</button>
</div>

<div id="modalLog<?php echo $l['id']; ?>" class="modal">
<div class="modal-content">
<p>Confirm delete this log?</p>
<button type="button" id="modalLog<?php echo $l['id']; ?>-yes" class="btn red">Yes</button>
<button type="button" onclick="closeModal('modalLog<?php echo $l['id']; ?>')" class="btn blue">No</button>
</div>
</div>
</div>
<?php endwhile; ?>
</form>

<div class="pagination">
<?php for($i=1;$i<=$totalPages;$i++): ?>
<a href="?page=<?php echo $i; ?>" class="<?php echo $i==$page?'active':''; ?>"><?php echo $i; ?></a>
<?php endfor; ?>
</div>
</div>

</div>
</body>
</html>