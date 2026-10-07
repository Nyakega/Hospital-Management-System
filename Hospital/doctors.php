<?php
// Move this to the VERY TOP to catch errors in auth.php or db.php
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once "auth.php";
requireRole(['Admin', 'Doctor', 'Nurse', 'Clinical Admin', 'Lab Technician', 'Pharmacist', 'Receptionist']);
include 'db.php';

// ─── 4. FETCH STAFF LIST FOR AJAX OR PAGE LOAD ───
if (isset($_GET['action']) && $_GET['action'] === 'list') {
    header('Content-Type: application/json');
    $rows = [];
    $res = $conn->query("
        SELECT s.*, u.account_status 
        FROM staff s 
        JOIN users u ON s.user_id = u.id 
        ORDER BY s.staff_id DESC
    ");
    while ($row = $res->fetch_assoc()) {
        $rows[] = $row;
    }
    echo json_encode(['success' => true, 'staff' => $rows]);
    exit;
}

// ─── 5. ADD NEW STAFF ─────────────────────────────
if (isset($_POST['add_staff_btn'])) {
    $name     = $conn->real_escape_string($_POST['full_name']);
    $username = $conn->real_escape_string($_POST['username']);
    $email    = $conn->real_escape_string($_POST['email']);
    $password = $_POST['password'];
    $spec     = $conn->real_escape_string($_POST['specialization']);
    $role     = $conn->real_escape_string($_POST['role']);
    $shift    = $conn->real_escape_string($_POST['shift']);

    $hashedPassword = password_hash($password, PASSWORD_DEFAULT);

    $prefix = ($role === 'Doctor') ? 'DOC' : 'STF';
    $uid    = $prefix . "-" . date("Y") . "-" . rand(1000, 9999);

    $sql_user = $conn->prepare("
        INSERT INTO users (full_name, username, email, password, role, account_status) 
        VALUES (?, ?, ?, ?, ?, 'Active')
    ");
    $sql_user->bind_param("sssss", $name, $username, $email, $hashedPassword, $role);

    if ($sql_user->execute()) {
        $new_user_id = $conn->insert_id;

        $sql_staff = $conn->prepare("
            INSERT INTO staff (user_id, unique_id, full_name, specialization, role, shift_hours, status) 
            VALUES (?, ?, ?, ?, ?, ?, 'Active')
        ");
        $sql_staff->bind_param("isssss", $new_user_id, $uid, $name, $spec, $role, $shift);

        if ($sql_staff->execute()) {
            $admin = $_SESSION['username'] ?? 'Admin';
            $log = $conn->prepare("INSERT INTO admin_logs (admin_username, action, target) VALUES (?, 'Added Staff Record', ?)");
            $log->bind_param("ss", $admin, $name);
            $log->execute();

            header("Location: doctors.php?msg=StaffAdded");
            exit;
        } else {
            die("❌ Error inserting staff: " . $sql_staff->error);
        }
    } else {
        die("❌ Error inserting user: " . $sql_user->error);
    }
}

// ─── 6. UPDATE STAFF SHIFT ───────────────────────
if (isset($_POST['update_shift_btn'])) {
    $id = (int)$_POST['staff_id'];
    $new_shift = $conn->real_escape_string($_POST['new_shift']);

    $sql = $conn->prepare("UPDATE staff SET shift_hours=? WHERE staff_id=?");
    $sql->bind_param("si", $new_shift, $id);

    if ($sql->execute()) {
        header("Location: doctors.php?msg=ShiftUpdated");
        exit;
    } else {
        die("❌ Error updating shift: " . $sql->error);
    }
}

// ─── 7. FETCH STAFF LIST FOR PAGE ────────────────
$all_staff = $conn->query("SELECT * FROM staff ORDER BY staff_id DESC");
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>HMS PRO-CORE | Staff Management</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        :root { --primary: #1e3a8a; --accent: #2563eb; --bg: #f1f5f9; --white: #fff; --success: #10b981; }
        body { font-family: 'Inter', sans-serif; background: var(--bg); padding: 30px; margin: 0; color: #1e293b; }
        .card { background: var(--white); padding: 25px; border-radius: 12px; box-shadow: 0 4px 10px rgba(0,0,0,0.05); margin-bottom: 25px; }
        .header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; }
        table { width: 100%; border-collapse: collapse; }
        th { background: #f8fafc; padding: 15px; text-align: left; color: #64748b; font-size: 0.75rem; text-transform: uppercase; border-bottom: 2px solid #edf2f7; }
        td { padding: 15px; border-bottom: 1px solid #edf2f7; font-size: 0.9rem; }
        .btn { background: var(--primary); color: white; border: none; padding: 10px 20px; border-radius: 8px; cursor: pointer; font-weight: 600; transition: 0.3s; }
        .btn:hover { background: var(--accent); }
        .badge { padding: 4px 10px; border-radius: 6px; font-size: 0.75rem; font-weight: bold; }
        .badge-role { background: #e0e7ff; color: #c515b6; }
        .badge-active { background: #dcfce7; color: #166534; }
        .input-group { display: flex; flex-direction: column; gap: 5px; margin-bottom: 15px; }
        input, select { padding: 12px; border: 1px solid #e2e8f0; border-radius: 8px; outline: none; }
        .modal { display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.6); z-index: 100; justify-content: center; align-items: center; }
        .modal-box { background: white; padding: 30px; border-radius: 15px; width: 450px; animation: slideIn 0.3s ease; }
        @keyframes slideIn { from { transform: translateY(-20px); opacity: 0; } to { transform: translateY(0); opacity: 1; } }
    </style>
</head>
<body>

<div class="header" style="display: flex; justify-content: space-between; align-items: center; width: 100%; margin-bottom: 25px; background: var(--white); padding: 15px 20px; border-radius: 12px; border: 1px solid var(--border);">
    
    <div class="nav-links" style="display: flex; gap: 12px; align-items: center;">
        
        <a href="javascript:history.back()" class="btn btn-outline" style="text-decoration:none; display: flex; align-items: center; gap: 8px; color: var(--text-main); font-weight: 600; font-size: 14px; transition: 0.2s;">
            <i class="fa fa-arrow-left" style="font-size: 12px;"></i> Back to Homepage
        </a>
        
        <a href="logout.php" class="btn btn-outline" style="text-decoration:none; display: flex; align-items: center; gap: 8px; color: var(--danger); border-color: rgba(226, 161, 172, 0.2); background: rgba(241, 32, 13, 0.97); font-weight: 600; font-size: 14px; transition: 0.2s;">
            <i class="fa-solid fa-right-from-bracket"></i> Logout
        </a>
        
    </div>

    <?php if(isset($_GET['msg'])): ?>
        <div style="display: flex; align-items: center; gap: 10px; background: #82efbc; color: #065f46; padding: 8px 16px; border-radius: 30px; border: 1px solid #d1fae5; font-size: 13px; font-weight: 600;">
            <i class="fa fa-check-circle" style="color: var(--success);"></i>
            <span>System: <?php echo htmlspecialchars($_GET['msg']); ?> Successful</span>
        </div>
    <?php endif; ?>

</div>
<div class="card">
    <div class="header">
        <h3><i class="fa fa-users-medical"></i> Staff & Doctor Directory</h3>
        <button class="btn" onclick="document.getElementById('addModal').style.display='flex'">+ Register New Staff</button>
    </div>
    <table>
        <thead>
            <tr>
                <th>Unique ID</th>
                <th>Full Name</th>
                <th>Specialization</th>
                <th>System Role</th>
                <th>Shift</th>
                <th>Status</th>
            </tr>
        </thead>
        <tbody>
            <?php if($all_staff && $all_staff->num_rows > 0): ?>
                <?php while($row = $all_staff->fetch_assoc()): ?>
                <tr>
                    <td><code style="color:var(--accent); font-weight:bold;"><?php echo $row['unique_id'] ?? 'PENDING'; ?></code></td>
                    <td><strong><?php echo htmlspecialchars($row['full_name']); ?></strong></td>
                    <td><?php echo htmlspecialchars($row['specialization']); ?></td>
                    <td><span class="badge badge-role"><?php echo $row['role']; ?></span></td>
                    <td><small><?php echo $row['shift_hours']; ?></small></td>
                    <td><span class="badge badge-active">● <?php echo $row['status']; ?></span></td>
                </tr>
                <?php endwhile; ?>
            <?php else: ?>
                <tr><td colspan="6" style="text-align:center; padding:30px; color:#94a3b8;">No records found in HMS PRO-CORE system.</td></tr>
            <?php endif; ?>
        </tbody>
    </table>
</div>

<div class="card">
    <h3>Quick Shift Re-assignment</h3>
    <form method="POST">
        <div style="display:grid; grid-template-columns: 1fr 1fr 150px; gap:20px; align-items:end;">
            <div class="input-group">
                <label>Select Staff Member</label>
                <select name="staff_id" required>
                    <option value="">-- Select Personnel --</option>
                    <?php 
                    $list = $conn->query("SELECT staff_id, full_name, role FROM staff WHERE status='Active'");
                    while($s = $list->fetch_assoc()) {
                        echo "<option value='".$s['staff_id']."'>".$s['full_name']." (".$s['role'].")</option>";
                    }
                    ?>
                </select>
            </div>
            <div class="input-group">
                <label>New Assignment</label>
                <select name="new_shift">
                    <option>Morning (08:00 - 16:00)</option>
                    <option>Evening (16:00 - 00:00)</option>
                    <option>Night (00:00 - 08:00)</option>
                    <option>On-Call / Emergency</option>
                </select>
            </div>
            <button type="submit" name="update_shift_btn" class="btn" style="height:48px; margin-bottom:15px;">Update</button>
        </div>
    </form>
</div>

<!-- MODAL -->
<div id="addModal" class="modal">
    <div class="modal-box">
        <h3>Register New Personnel</h3>
        <hr style="border:0; border-top:1px solid #eee; margin-bottom:20px;">
        <form method="POST">
            <div class="input-group">
                <label>Full Name</label>
                <input type="text" name="full_name" placeholder="Dr. Jane Doe" required>
            </div>
            <div class="input-group">
                <label>Username</label>
                <input type="text" name="username" placeholder="username" required>
            </div>
            <div class="input-group">
                <label>Email address</label>
                <input type="text" name="email" placeholder="example@gmail.com" required>
            </div>
            <div class="input-group">
                <label>Password</label>
                <input type="text" name="password" placeholder="Enter secure password" required>
            </div>
            <div class="input-group">
                <label>Specialization / Skill</label>
                <input type="text" name="specialization" placeholder="e.g. Pediatrics or Nursing" required>
            </div>
            <div class="input-group">
                <label>System Role (RBAC)</label>
                <select name="role">
                    <option value="Doctor">Doctor</option>
                    <option value="Nurse">Nurse</option>
                    <option value="Clinical Admin">Clinical Admin</option>
                    <option value="Lab Technician">Lab Technician</option>
                    <option value="Pharmacist">Pharmacist</option>
                    <option value="Receptionist">Receptionist</option>
                </select>
            </div>
            <div class="input-group">
                <label>Initial Shift</label>
                <select name="shift">
                    <option>Morning (08:00 - 16:00)</option>
                    <option>Evening (16:00 - 00:00)</option>
                    <option>Night (00:00 - 08:00)</option>
                </select>
            </div>
            <div style="display:flex; gap:10px; margin-top:20px;">
                <button type="submit" name="add_staff_btn" class="btn" style="flex:2;">Confirm Registration</button>
                <button type="button" class="btn" onclick="document.getElementById('addModal').style.display='none'" style="background:#cbd5e1; color:#475569; flex:1;">Cancel</button>
            </div>
        </form>
    </div>
</div>

</body>
</html>