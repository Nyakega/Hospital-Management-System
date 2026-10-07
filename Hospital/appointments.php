<?php
/**
 * HMS PRO-CORE | Global Scheduling Hub
 * Version: 3.1 (Stable - PHP 8.1+ Compatible)
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once "db.php";
session_start();

/**
 * 1. HELPERS
 * The Null Coalescing Operator (??) prevents the "Deprecated: htmlspecialchars()" error.
 */
function h($str) {
    return htmlspecialchars($str ?? '', ENT_QUOTES, 'UTF-8');
}

$message = null;
$error   = null;

// --- 2. CONTROLLER: HANDLE BOOKING ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['book_appointment'])) {
    
    $patient_id = filter_input(INPUT_POST, 'patient_id', FILTER_VALIDATE_INT);
    $staff_id   = filter_input(INPUT_POST, 'staff_id', FILTER_VALIDATE_INT);
    $raw_date   = $_POST['app_date'] ?? '';
    $symptoms   = trim($_POST['symptoms'] ?? '');

    if (!$patient_id || !$staff_id || empty($raw_date)) {
        $error = "Validation Error: Please select a patient, a staff member, and a valid date.";
    } else {
        // Normalize date to Y-m-d H:i:00
        $app_date = date('Y-m-d H:i:00', strtotime($raw_date));
        
        /**
         * CONFLICT VALIDATION
         * Checks if the specific staff member is already occupied at this time.
         */
        $check_sql = "SELECT appointment_id FROM appointments WHERE staff_id = ? AND appointment_date = ? LIMIT 1";
        $check_stmt = $conn->prepare($check_sql);
        $check_stmt->bind_param("is", $staff_id, $app_date);
        $check_stmt->execute();
        $check_stmt->store_result();

        if ($check_stmt->num_rows > 0) {
            $error = "Conflict Detected: The selected staff member is already booked for this time slot.";
        } else {
            $status = "Scheduled";
            $stmt = $conn->prepare("INSERT INTO appointments (patient_id, staff_id, appointment_date, symptoms, status) VALUES (?, ?, ?, ?, ?)");
            $stmt->bind_param("iisss", $patient_id, $staff_id, $app_date, $symptoms, $status);

            if ($stmt->execute()) {
                header("Location: " . $_SERVER['PHP_SELF'] . "?status=success");
                exit();
            } else {
                $error = "Database Error: Failed to save the appointment.";
            }
            $stmt->close();
        }
        $check_stmt->close();
    }
}

// --- 3. DATA FETCHING ---

// Fetch ALL Staff for selection (Filtered by your requested roles)
$staff_sql = "SELECT id, full_name, role FROM users 
              WHERE role IN ('Admin', 'Doctor', 'Nurse', 'Lab Technician', 'Clinical Admin', 'Pharmacist', 'Receptionist') 
              AND account_status='Active' 
              AND full_name IS NOT NULL
              ORDER BY role ASC, full_name ASC";
$staff_res = $conn->query($staff_sql);
$all_staff = $staff_res->fetch_all(MYSQLI_ASSOC);

// Fetch Active Doctors for the Status Sidebar
$doctors_res = $conn->query("SELECT full_name, account_status FROM users WHERE role='Doctor' AND full_name IS NOT NULL");
$doctors_status = $doctors_res->fetch_all(MYSQLI_ASSOC);

// Fetch Patients
$patients_res = $conn->query("SELECT patient_id, full_name, hms_unique_id FROM patients WHERE full_name IS NOT NULL ORDER BY full_name ASC");
$patients_list = $patients_res->fetch_all(MYSQLI_ASSOC);

// GLOBAL QUEUE: All roles see this list
$queue_sql = "SELECT a.appointment_id, p.full_name as p_name, s.full_name as s_name, s.role as s_role, a.appointment_date, a.status 
              FROM appointments a
              JOIN patients p ON a.patient_id = p.patient_id
              JOIN users s ON a.staff_id = s.id
              ORDER BY a.appointment_date ASC";
$queue_result = $conn->query($queue_sql);

if (isset($_GET['status']) && $_GET['status'] === 'success') {
    $message = "Success: Appointment has been successfully recorded.";
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Global Scheduling Hub | HMS PRO-CORE</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <style>
        :root {
            --primary: #0f172a; --accent: #2563eb; --bg: #f1f5f9;
            --white: #ffffff; --text: #334155; --border: #e2e8f0;
            --success: #10b981; --warning: #f59e0b;
        }
        body { font-family: 'Inter', system-ui, sans-serif; background: var(--bg); color: var(--text); margin: 0; padding: 25px; }
        .container { max-width: 1400px; margin: 0 auto; }
        
        .top-nav { display: flex; justify-content: space-between; margin-bottom: 2rem; align-items: center; background: var(--white); padding: 15px 25px; border-radius: 12px; border: 1px solid var(--border); box-shadow: 0 1px 2px rgba(0,0,0,0.05); }
        .logo { font-weight: 800; color: var(--primary); font-size: 1.1rem; display: flex; align-items: center; gap: 10px; }
        
        .main-layout { display: grid; grid-template-columns: 380px 1fr; gap: 25px; }
        
        .card { background: var(--white); border-radius: 12px; border: 1px solid var(--border); box-shadow: 0 4px 6px -1px rgba(0,0,0,0.05); margin-bottom: 25px; }
        .card-header { padding: 1.25rem; border-bottom: 1px solid var(--border); font-weight: 700; color: var(--primary); display: flex; align-items: center; gap: 10px; }
        .card-body { padding: 1.5rem; }
        
        .form-group { margin-bottom: 1.25rem; }
        label { display: block; font-size: 0.75rem; font-weight: 700; margin-bottom: 6px; color: #64748b; text-transform: uppercase; }
        .input-field { width: 100%; padding: 12px; border: 1px solid var(--border); border-radius: 8px; font-size: 0.95rem; background: #f8fafc; transition: 0.2s; box-sizing: border-box; }
        .input-field:focus { outline: none; border-color: var(--accent); background: #fff; box-shadow: 0 0 0 4px rgba(37,99,235,0.1); }
        
        .btn-book { width: 100%; padding: 14px; background: var(--accent); color: white; border: none; border-radius: 8px; font-weight: 700; cursor: pointer; transition: 0.3s; font-size: 1rem; }
        .btn-book:hover { background: #1d4ed8; transform: translateY(-1px); }
        
        table { width: 100%; border-collapse: collapse; }
        th { text-align: left; padding: 15px; background: #f8fafc; font-size: 0.75rem; color: #64748b; border-bottom: 2px solid var(--border); }
        td { padding: 15px; border-bottom: 1px solid var(--border); font-size: 0.9rem; }
        
        .status-badge { padding: 4px 12px; border-radius: 20px; font-size: 0.75rem; font-weight: 700; background: #e0f2fe; color: #0369a1; }
        .role-tag { font-size: 0.7rem; background: #fef3c7; color: #92400e; padding: 2px 8px; border-radius: 4px; font-weight: 600; margin-left: 5px; }
        .dot { height: 10px; width: 10px; border-radius: 50%; display: inline-block; margin-right: 8px; }
        
        .doc-item { display: flex; justify-content: space-between; padding: 10px; border-radius: 8px; background: #f8fafc; margin-bottom: 8px; border: 1px solid var(--border); }

        .alert { padding: 15px; border-radius: 10px; margin-bottom: 20px; font-weight: 600; border-left: 5px solid transparent; }
        .alert-success { background: #dcfce7; color: #15803d; border-color: var(--success); }
        .alert-danger { background: #fee2e2; color: #b91c1c; border-color: #ef4444; }
    </style>
</head>
<body>

<div class="container">
    <div class="top-nav">
        <div class="logo"><i class="fa-solid fa-calendar-check" style="color: var(--accent);"></i> HMS PRO-CORE | GLOBAL SCHEDULING</div>
        <a href="index.php" style="text-decoration: none; color: var(--accent); font-weight: bold;"><i class="fa-solid fa-arrow-left"></i> Back to Dashboard</a>
    </div>

    <?php if($message): ?> <div class="alert alert-success"><i class="fa-solid fa-circle-check"></i> <?php echo $message; ?></div> <?php endif; ?>
    <?php if($error): ?> <div class="alert alert-danger"><i class="fa-solid fa-circle-exclamation"></i> <?php echo $error; ?></div> <?php endif; ?>

    <div class="main-layout">
        <div class="sidebar">
            <div class="card">
                <div class="card-header"><i class="fa-solid fa-plus-circle"></i> Create Appointment</div>
                <div class="card-body">
                    <form method="POST">
                        <div class="form-group">
                            <label>Select Patient</label>
                            <select name="patient_id" class="input-field" required>
                                <option value="">-- Choose Patient --</option>
                                <?php foreach($patients_list as $p): ?>
                                    <option value="<?php echo $p['patient_id']; ?>"><?php echo h($p['full_name']); ?> (<?php echo h($p['hms_unique_id']); ?>)</option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="form-group">
                            <label>Assign Staff Member</label>
                            <select name="staff_id" class="input-field" required>
                                <option value="">-- Choose Personnel --</option>
                                <?php 
                                $last_role = '';
                                foreach($all_staff as $s): 
                                    if($last_role !== $s['role']): 
                                        if($last_role !== '') echo '</optgroup>';
                                        $last_role = $s['role'];
                                        echo '<optgroup label="'.h($last_role).'">';
                                    endif;
                                ?>
                                    <option value="<?php echo $s['id']; ?>"><?php echo h($s['full_name']); ?></option>
                                <?php endforeach; echo '</optgroup>'; ?>
                            </select>
                        </div>

                        <div class="form-group">
                            <label>Date & Time</label>
                            <input type="datetime-local" name="app_date" class="input-field" min="<?php echo date('Y-m-d\TH:i'); ?>" required>
                        </div>

                        <div class="form-group">
                            <label>Appointment Notes</label>
                            <textarea name="symptoms" class="input-field" rows="3" placeholder="Symptoms, Reason for visit..."></textarea>
                        </div>

                        <button type="submit" name="book_appointment" class="btn-book">Confirm Booking</button>
                    </form>
                </div>
            </div>

            <div class="card">
                <div class="card-header"><i class="fa-solid fa-user-doctor"></i> Doctor Status</div>
                <div class="card-body">
                    <?php if(!empty($doctors_status)): ?>
                        <?php foreach($doctors_status as $doc): ?>
                        <div class="doc-item">
                            <span><strong>Dr. <?php echo h($doc['full_name']); ?></strong></span>
                            <span>
                                <span class="dot" style="background: <?php echo ($doc['account_status'] == 'Active') ? 'var(--success)' : 'var(--warning)'; ?>;"></span>
                                <?php echo h($doc['account_status']); ?>
                            </span>
                        </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <p style="font-size: 0.85rem; color: #94a3b8; text-align: center;">No doctors found.</p>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <div class="content">
            <div class="card">
                <div class="card-header">
                    <span><i class="fa-solid fa-list-ul"></i> Master Appointment Queue</span>
                    <span style="font-size: 0.75rem; font-weight: normal; background: #f1f5f9; padding: 4px 10px; border-radius: 6px;">Today: <?php echo date('M d, Y'); ?></span>
                </div>
                <div class="card-body" style="padding:0;">
                    <table>
                        <thead>
                            <tr>
                                <th>Ref #</th>
                                <th>Patient Name</th>
                                <th>Staff Member</th>
                                <th>Schedule</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if($queue_result->num_rows > 0): ?>
                                <?php while($row = $queue_result->fetch_assoc()): ?>
                                <tr>
                                    <td><small>#<?php echo h($row['appointment_id']); ?></small></td>
                                    <td><strong><?php echo h($row['p_name']); ?></strong></td>
                                    <td>
                                        <?php echo h($row['s_name']); ?>
                                        <span class="role-tag"><?php echo h($row['s_role']); ?></span>
                                    </td>
                                    <td><?php echo date('M d, h:i A', strtotime($row['appointment_date'])); ?></td>
                                    <td><span class="status-badge"><?php echo h($row['status']); ?></span></td>
                                </tr>
                                <?php endwhile; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="5" style="text-align:center; padding: 50px; color: #94a3b8;">
                                        <i class="fa-solid fa-calendar-xmark" style="font-size: 2rem; display: block; margin-bottom: 10px; opacity: 0.5;"></i>
                                        The schedule is currently empty.
                                    </td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

</body>
</html>