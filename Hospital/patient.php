<?php
require_once "auth.php";       
requireRole('Receptionist'); 
include 'db.php';

// Enable error reporting for debugging during development
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

$message = "";

// --- FORM PROCESSING ---
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST['save_patient'])) {
    
    // 1. Collect and Basic Validation
    $full_name        = trim($_POST['full_name']);
    $national_id      = trim($_POST['national_id']);
    $phone_number     = trim($_POST['phone_number']);
    $age              = intval($_POST['age']);
    $insurance_no     = trim($_POST['insurance_no']);
    $medical_history  = trim($_POST['medical_history']);
    $allergies        = trim($_POST['allergies']);
    $admission_status = $_POST['admission_status'];
    $reg_date         = !empty($_POST['reg_date']) ? $_POST['reg_date'] : date('Y-m-d');

    // 2. Professional Unique ID Generation
    // We use a loop to ensure the generated ID doesn't already exist in the database
    $is_unique = false;
    while (!$is_unique) {
        $hms_unique_id = "HMS-" . date("Y") . "-" . str_pad(rand(1, 9999), 4, '0', STR_PAD_LEFT);
        $check_stmt = $conn->prepare("SELECT hms_unique_id FROM patients WHERE hms_unique_id = ?");
        $check_stmt->bind_param("s", $hms_unique_id);
        $check_stmt->execute();
        $check_stmt->store_result();
        if ($check_stmt->num_rows === 0) {
            $is_unique = true;
        }
        $check_stmt->close();
    }

    // 3. Prepared Statement (Security: Prevents SQL Injection)
    try {
        $stmt = $conn->prepare("INSERT INTO patients (hms_unique_id, full_name, national_id, phone_number, age, insurance_no, medical_history, allergies, admission_status, reg_date) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        
        $stmt->bind_param("ssssisssss", 
            $hms_unique_id, 
            $full_name, 
            $national_id, 
            $phone_number, 
            $age, 
            $insurance_no, 
            $medical_history, 
            $allergies, 
            $admission_status, 
            $reg_date
        );

        if ($stmt->execute()) {
            $message = "<div class='alert success'><i class='fas fa-check-circle'></i> Patient <strong>$hms_unique_id</strong> Registered Successfully!</div>";
        }
        $stmt->close();
    } catch (Exception $e) {
        $message = "<div class='alert danger'><i class='fas fa-exclamation-triangle'></i> Error: " . $e->getMessage() . "</div>";
    }
}

// Fetch records safely
$patients_list = $conn->query("SELECT * FROM patients ORDER BY reg_date DESC, hms_unique_id DESC LIMIT 50");
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Patient Registry | HMS PRO-CORE</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        :root {
            --primary: #1e3a8a; --secondary: #1e293b; --accent: #2563eb;
            --bg-body: #f1f5f9; --white: #ffffff; --success: #10b981;
            --danger: #ef4444; --warning: #f59e0b; --text: #334155;
        }

        body { margin: 0; font-family: 'Inter', 'Segoe UI', sans-serif; background: var(--bg-body); color: var(--text); }
        .topbar { background: var(--white); padding: 15px 30px; display: flex; justify-content: space-between; align-items: center; box-shadow: 0 2px 10px rgba(0,0,0,0.05); }
        
        .nav-brand { display: flex; align-items: center; gap: 10px; color: var(--primary); }
        .nav-actions { display: flex; gap: 10px; }
        
        .btn { padding: 10px 20px; border-radius: 6px; text-decoration: none; font-weight: 600; border: none; cursor: pointer; transition: 0.2s; display: inline-flex; align-items: center; gap: 8px; }
        .btn-primary { background: var(--primary); color: white; }
        .btn-primary:hover { background: var(--accent); }
        .btn-outline { border: 1px solid #ddd; color: var(--secondary); background: white; }
        .btn-outline:hover { background: #f8fafc; }

        .container { max-width: 1200px; margin: 30px auto; padding: 0 20px; }
        .card { background: var(--white); padding: 25px; border-radius: 12px; margin-bottom: 25px; box-shadow: 0 4px 20px rgba(0,0,0,0.04); border: 1px solid rgba(0,0,0,0.05); }
        .card-title { margin: 0 0 20px 0; display: flex; align-items: center; gap: 10px; font-size: 1.25rem; color: var(--secondary); }

        .alert { padding: 15px; border-radius: 8px; margin-bottom: 20px; font-weight: 500; }
        .success { background: #dcfce7; color: #166534; border: 1px solid #bbf7d0; }
        .danger { background: #fee2e2; color: #991b1b; border: 1px solid #fecaca; }

        .form-grid { display: grid; grid-template-columns: repeat(2, 1fr); gap: 20px; }
        .form-group { display: flex; flex-direction: column; gap: 5px; }
        label { font-size: 13px; font-weight: 600; color: #64748b; }
        
        input, select, textarea { padding: 12px; border: 1px solid #cbd5e1; border-radius: 8px; font-size: 14px; transition: 0.2s; outline: none; }
        input:focus, select:focus, textarea:focus { border-color: var(--accent); box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.1); }
        
        .full-width { grid-column: span 2; }
        
        table { width: 100%; border-collapse: collapse; margin-top: 10px; }
        th { text-align: left; background: #f8fafc; color: #64748b; font-size: 12px; text-transform: uppercase; padding: 15px; border-bottom: 2px solid #edf2f7; }
        td { padding: 15px; border-bottom: 1px solid #edf2f7; font-size: 14px; }
        tr:hover { background-color: #fcfdfe; }

        .badge { padding: 4px 10px; border-radius: 99px; font-size: 12px; font-weight: 600; }
        .bg-danger { background: #fee2e2; color: #dc2626; }
        .bg-warning { background: #fef3c7; color: #d97706; }
        .bg-success { background: #dcfce7; color: #16a34a; }
        .bg-info { background: #e0f2fe; color: #0284c7; }
    </style>
</head>
<body>

<div class="topbar">
    <div class="nav-brand">
        <i class="fa-solid fa-hospital-user fa-2x"></i>
        <h2 style="margin:0">HMS PRO-CORE <span style="font-weight:300; font-size: 14px; color: #64748b;">| Patient EMR</span></h2>
    </div>
    <div class="nav-actions">
        <a href="index.php" class="btn btn-outline"><i class="fa-solid fa-arrow-left"></i> Dashboard</a>
        <a href="logout.php" class="btn btn-outline" style="color: var(--danger)"><i class="fa-solid fa-right-from-bracket"></i> Logout</a>
    </div>
</div>

<div class="container">

    <?php echo $message; ?>

    <div class="card">
        <h3 class="card-title"><i class="fa-solid fa-user-plus" style="color: var(--accent)"></i> Register New Patient</h3>
        
        <form method="POST" action="patient.php" class="form-grid">
            <div class="form-group">
                <label>Patient Full Name</label>
                <input type="text" name="full_name" placeholder="Enter name" required>
            </div>
            <div class="form-group">
                <label>National ID / Passport</label>
                <input type="text" name="national_id" placeholder="Enter ID" required>
            </div>
            <div class="form-group">
                <label>Primary Phone</label>
                <input type="text" name="phone_number" placeholder="07XXXXXXXX" required>
            </div>
            <div class="form-group">
                <label>Age</label>
                <input type="number" name="age" placeholder="Years" required>
            </div>
            <div class="form-group full-width">
                <label>Insurance Number</label>
                <input type="text" name="insurance_no" placeholder="Policy number (leave blank if private)">
            </div>
            <div class="form-group">
                <label>Medical History</label>
                <textarea name="medical_history" placeholder="Previous surgeries, chronic conditions..."></textarea>
            </div>
            <div class="form-group">
                <label>Allergies</label>
                <textarea name="allergies" placeholder="Drug or food allergies..."></textarea>
            </div>
            <div class="form-group">
                <label>Admission Status</label>
                <select name="admission_status" required>
                    <option value="Outpatient">Outpatient</option>
                    <option value="Inpatient">Inpatient</option>
                    <option value="Emergency">Emergency</option>
                </select>
            </div>
            <div class="form-group">
                <label>Registration Date</label>
                <input type="date" name="reg_date" value="<?php echo date('Y-m-d'); ?>" required>
            </div>

            <button type="submit" name="save_patient" class="btn btn-primary full-width" style="justify-content: center; padding: 15px;">
                <i class="fa-solid fa-save"></i> Save Patient Record
            </button>
        </form>
    </div>

    <div class="card" style="overflow-x:auto;">
        <h3 class="card-title"><i class="fa-solid fa-database" style="color: var(--primary)"></i> Recent Patient Records</h3>

        <table>
            <thead>
                <tr>
                    <th>HMS ID</th>
                    <th>Patient Name</th>
                    <th>Contact</th>
                    <th>Status</th>
                    <th>Critical Info</th>
                    <th>Reg Date</th>
                </tr>
            </thead>
            <tbody>
                <?php if ($patients_list->num_rows > 0): ?>
                    <?php while($row = $patients_list->fetch_assoc()): ?>
                        <?php 
                            $status_class = match($row['admission_status']) {
                                'Emergency' => 'bg-danger',
                                'Inpatient' => 'bg-warning',
                                default => 'bg-success'
                            };
                            
                            // Security: Sanitize output to prevent XSS
                            $safe_name = htmlspecialchars($row['full_name']);
                            $safe_allergies = $row['allergies'] ? htmlspecialchars($row['allergies']) : 'None';
                        ?>
                        <tr>
                            <td style="font-weight:700; color:var(--primary); font-family: monospace;"><?php echo $row['hms_unique_id']; ?></td>
                            <td><?php echo $safe_name; ?> <br><small style="color: #64748b">Age: <?php echo $row['age']; ?></small></td>
                            <td><?php echo htmlspecialchars($row['phone_number']); ?></td>
                            <td><span class="badge <?php echo $status_class; ?>"><?php echo $row['admission_status']; ?></span></td>
                            <td>
                                <?php if($row['allergies']): ?>
                                    <span class="badge bg-danger" style="font-size: 10px;">ALLERGIES: <?php echo $safe_allergies; ?></span>
                                <?php else: ?>
                                    <span style="color: #cbd5e1; font-size: 12px;">No Allergies</span>
                                <?php endif; ?>
                            </td>
                            <td><?php echo date('M d, Y', strtotime($row['reg_date'])); ?></td>
                        </tr>
                    <?php endwhile; ?>
                <?php else: ?>
                    <tr><td colspan="6" style="text-align:center; padding: 40px; color: #94a3b8;">No patient records found in system.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

</div>

</body>
</html>