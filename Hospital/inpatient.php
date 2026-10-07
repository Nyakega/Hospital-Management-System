<?php
require_once "auth.php";
requireRole('admin,doctor,nurse,clinical admin,lab technician,pharmacist,receptionist');
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Database connection
$servername = "localhost";
$username   = "root";
$password   = "";
$dbname     = "hospital";

$conn = new mysqli($servername, $username, $password, $dbname);
if ($conn->connect_error) die("Connection failed: " . $conn->connect_error);

// ================= MESSAGE HANDLER =================
$message = "";

// ================= WARDS =================
$wards = ["General Ward (Male)","General Ward (Female)","Maternity Wing","ICU / Critical Care","Private Suites"];
$selectedWard = $_GET['ward'] ?? "General Ward (Male)";

// ================= ACTIONS =================

// ADD BED (prevent duplicate)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_bed'])) {
    $bed_number = trim($_POST['bed_number']);
    $ward       = $_POST['ward'];
    $status     = $_POST['status'];
    $patient    = $_POST['patient_name'] ?: NULL;

    // Check duplicate
    $check = $conn->prepare("SELECT bed_id FROM beds WHERE bed_number=? AND ward=?");
    $check->bind_param("ss", $bed_number, $ward);
    $check->execute();
    $check->store_result();
    if($check->num_rows > 0){
        $message = "❌ Bed number '$bed_number' already exists in '$ward'.";
    } else {
        $stmt = $conn->prepare("INSERT INTO beds (bed_number, ward, status, patient_name) VALUES (?, ?, ?, ?)");
        $stmt->bind_param("ssss", $bed_number, $ward, $status, $patient);
        $stmt->execute();
        $message = "✅ Bed '$bed_number' added successfully to '$ward'.";
        $stmt->close();
    }
    $check->close();
}

// ASSIGN
if (isset($_POST['action']) && $_POST['action'] == 'assign') {
    $stmt = $conn->prepare("UPDATE beds SET patient_name=?, status='Occupied' WHERE bed_id=?");
    $stmt->bind_param("si", $_POST['patient_name'], $_POST['bed_id']);
    $stmt->execute();
    $stmt->close();
}

// DISCHARGE
if (isset($_POST['action']) && $_POST['action'] == 'discharge') {
    $stmt = $conn->prepare("UPDATE beds SET patient_name=NULL, status='Cleaning' WHERE bed_id=?");
    $stmt->bind_param("i", $_POST['bed_id']);
    $stmt->execute();
    $stmt->close();
}

// TRANSFER
if (isset($_POST['action']) && $_POST['action'] == 'transfer') {
    $stmt = $conn->prepare("UPDATE beds SET patient_name=NULL, status='Available' WHERE bed_id=?");
    $stmt->bind_param("i", $_POST['bed_id']);
    $stmt->execute();
    $stmt->close();
}

// UPDATE STATUS
if (isset($_POST['action']) && $_POST['action'] == 'update') {
    $stmt = $conn->prepare("UPDATE beds SET status=? WHERE bed_id=?");
    $stmt->bind_param("si", $_POST['status'], $_POST['bed_id']);
    $stmt->execute();
    $stmt->close();
}

// ================= FETCH BEDS =================
$stmt = $conn->prepare("SELECT * FROM beds WHERE ward=? ORDER BY bed_number ASC");
$stmt->bind_param("s", $selectedWard);
$stmt->execute();
$result = $stmt->get_result();

// ================= WARD OCCUPANCY STATS =================
$wardStats = [];
foreach($wards as $wardName){
    $totalStmt = $conn->prepare("SELECT COUNT(*) FROM beds WHERE ward=?");
    $totalStmt->bind_param("s",$wardName);
    $totalStmt->execute();
    $totalStmt->bind_result($totalBeds);
    $totalStmt->fetch();
    $totalStmt->close();

    $occStmt = $conn->prepare("SELECT COUNT(*) FROM beds WHERE ward=? AND status='Occupied'");
    $occStmt->bind_param("s",$wardName);
    $occStmt->execute();
    $occStmt->bind_result($occupiedBeds);
    $occStmt->fetch();
    $occStmt->close();

    $wardStats[$wardName] = [
        'total'=>$totalBeds,
        'occupied'=>$occupiedBeds,
        'percent'=>$totalBeds>0?($occupiedBeds/$totalBeds)*100:0
    ];
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>HMS PRO-CORE | Bed & Ward Management</title>
<link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
<style>
:root {
    --primary:#2563eb; --primary-hover:#1d4ed8;
    --bg:#f8fafc; --card:#ffffff; --text:#1e293b; --muted:#64748b;
    --available:#10b981; --occupied:#ef4444; --maintenance:#f59e0b; --cleaning:#06b6d4;
}
body{font-family:'Segoe UI',sans-serif; background:var(--bg); margin:0;padding:30px;color:var(--text);}
.header-flex{display:flex;justify-content:space-between;align-items:center;margin-bottom:25px;}
.back-btn{text-decoration:none;color:var(--primary);font-weight:600;}
.message{margin-bottom:15px;padding:12px 15px;border-radius:8px;font-weight:600;}
.message-success{background:#dcfce7;color:#166534;}
.message-error{background:#fee2e2;color:#7f1d1d;}
.add-bed-form{background:var(--card);padding:25px;border-radius:15px;box-shadow:0 8px 20px rgba(0,0,0,0.05);margin-bottom:30px;}
.add-bed-form input,.add-bed-form select{width:100%;padding:10px;margin-top:10px;border-radius:10px;border:1px solid #e2e8f0;font-size:14px;}
.add-bed-form button{margin-top:15px;padding:12px;width:100%;background:var(--primary);color:white;border:none;border-radius:10px;font-size:14px;cursor:pointer;transition:0.3s;}
.add-bed-form button:hover{background:var(--primary-hover);}
.ward-tabs{display:flex;flex-wrap:wrap;gap:15px;margin-bottom:25px;}
.tab{display:flex;align-items:center;gap:8px;padding:12px 18px;background:#fff;border-radius:12px;text-decoration:none;color:var(--text);font-weight:600;border:1px solid #e2e8f0;font-size:14px;transition:0.3s;position:relative;}
.tab:hover{background:var(--primary);color:white;border-color:var(--primary);}
.tab.active{background:var(--primary);color:white;border-color:var(--primary);}
.tab .badge{position:absolute;top:-8px;right:-8px;padding:3px 8px;font-size:10px;border-radius:12px;color:white;}
.bed-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(220px,1fr));gap:20px;}
.bed-card{background:var(--card);padding:20px;border-radius:15px;border-left:5px solid #ccc;box-shadow:0 10px 25px rgba(0,0,0,0.05);transition:0.3s;}
.bed-card:hover{transform:translateY(-5px);}
.bed-title{font-weight:700;font-size:16px;color:var(--primary);}
.patient{font-size:13px;color:var(--muted);margin-top:5px;}
.badge-status{display:inline-block;padding:5px 12px;border-radius:20px;font-size:12px;margin-top:10px;}
.available{background:#dcfce7;color:#166534;}
.occupied{background:#fee2e2;color:#7f1d1d;}
.cleaning{background:#cffafe;color:#155e75;}
.maintenance{background:#fef3c7;color:#92400e;}
.action-btns{margin-top:15px;display:flex;flex-wrap:wrap;gap:5px;}
.btn-sm{padding:6px 12px;font-size:12px;border:none;border-radius:6px;cursor:pointer;transition:0.3s;}
.btn-primary{background:var(--primary);color:white;}
.btn-primary:hover{background:var(--primary-hover);}
.btn-danger{background:var(--occupied);color:white;}
.btn-danger:hover{opacity:0.85;}
</style>
</head>
<body>

<div class="header-flex">
  <a href="index.php" class="back-btn"><i class="fa fa-arrow-left"></i> Dashboard</a>
  <h3><?php echo $selectedWard; ?></h3>
</div>

<?php if($message): ?>
<div class="message <?php echo strpos($message,'❌')!==false?'message-error':'message-success'; ?>">
    <?php echo $message; ?>
</div>
<?php endif; ?>

<div class="add-bed-form">
<h3>Add New Bed</h3>
<form method="POST">
<input type="text" name="bed_number" placeholder="Bed Number" required>
<select name="ward" required>
<?php foreach($wards as $ward): ?>
<option <?php if($ward==$selectedWard) echo 'selected'; ?>><?php echo $ward; ?></option>
<?php endforeach; ?>
</select>
<select name="status">
<option>Available</option>
<option>Occupied</option>
<option>Cleaning</option>
<option>Maintenance</option>
</select>
<input type="text" name="patient_name" placeholder="Patient Name (optional)">
<button name="add_bed">Add Bed</button>
</form>
</div>

<div class="ward-tabs">
<?php foreach($wards as $ward): 
$stat = $wardStats[$ward];
$percent = $stat['percent'];
$color = $percent>70?'red':($percent>40?'orange':'green');
?>
<a href="?ward=<?php echo urlencode($ward); ?>" class="tab <?php if($ward==$selectedWard) echo 'active'; ?>" style="border-color:<?php echo $color; ?>;color:<?php echo $color; ?>;">
<i class="fa fa-bed"></i> <?php echo $ward; ?>
<span class="badge" style="background:<?php echo $color; ?>;"><?php echo $stat['occupied'].'/'.$stat['total']; ?></span>
</a>
<?php endforeach; ?>
</div>

<div class="bed-grid">
<?php while($row = $result->fetch_assoc()):
$status = strtolower($row['status']); ?>
<div class="bed-card bed-<?php echo $status; ?>">
<div class="bed-title">Bed <?php echo $row['bed_number']; ?></div>
<div class="patient"><?php echo $row['patient_name'] ?: "No Patient"; ?></div>
<div class="badge-status <?php echo $status; ?>"><?php echo $row['status']; ?></div>

<div class="action-btns">
<?php if($row['status']=="Available"): ?>
<form method="POST"><input type="hidden" name="bed_id" value="<?php echo $row['bed_id']; ?>">
<input type="hidden" name="action" value="assign">
<input type="text" name="patient_name" placeholder="Patient" required>
<button class="btn-sm btn-primary">Assign</button></form>

<?php elseif($row['status']=="Occupied"): ?>
<form method="POST"><input type="hidden" name="bed_id" value="<?php echo $row['bed_id']; ?>">
<input type="hidden" name="action" value="transfer">
<button class="btn-sm btn-primary">Transfer</button></form>
<form method="POST"><input type="hidden" name="bed_id" value="<?php echo $row['bed_id']; ?>">
<input type="hidden" name="action" value="discharge">
<button class="btn-sm btn-danger">Discharge</button></form>

<?php else: ?>
<form method="POST"><input type="hidden" name="bed_id" value="<?php echo $row['bed_id']; ?>">
<input type="hidden" name="action" value="update">
<select name="status">
<option>Available</option>
<option>Cleaning</option>
<option>Maintenance</option>
</select>
<button class="btn-sm btn-primary">Update</button></form>
<?php endif; ?>
</div>
</div>
<?php endwhile; ?>
</div>
</body>
</html>