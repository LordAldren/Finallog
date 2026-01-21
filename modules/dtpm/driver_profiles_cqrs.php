<?php
session_start();
if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true) {
    header("location: ../../auth/login.php");
    exit;
}
require_once '../../config/db_connect.php';
$message = '';

// --- EVENT HANDLER FUNCTIONS ---
function createEvent($aggregateId, $eventType, $payload, $conn) {
    $eventPayloadJson = json_encode($payload);
    $stmt = $conn->prepare("INSERT INTO events (aggregate_id, event_type, event_payload) VALUES (?, ?, ?)");
    $stmt->bind_param("iss", $aggregateId, $eventType, $eventPayloadJson);
    return $stmt->execute();
}

function rebuildReadModel($conn) {
    $conn->query("TRUNCATE TABLE drivers");
    $events_result = $conn->query("SELECT * FROM events ORDER BY created_at ASC");
    $drivers_state = [];

    while ($event = $events_result->fetch_assoc()) {
        $aggregateId = $event['aggregate_id'];
        $eventType = $event['event_type'];
        $payload = json_decode($event['event_payload'], true);

        if ($eventType === 'DriverCreated') {
            $drivers_state[$aggregateId] = $payload;
        } elseif ($eventType === 'DriverUpdated' || $eventType === 'DriverApproved' || $eventType === 'DriverRejected') {
            if (isset($drivers_state[$aggregateId])) {
                $drivers_state[$aggregateId] = array_merge($drivers_state[$aggregateId], $payload);
            }
        } elseif ($eventType === 'DriverDeleted') {
            unset($drivers_state[$aggregateId]);
        }
    }
    
    $stmt = $conn->prepare("INSERT INTO drivers (id, user_id, name, license_number, license_expiry_date, contact_number, date_joined, status, rating, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
    foreach ($drivers_state as $id => $driver_data) {
        $stmt->bind_param("iissssssds", 
            $id, $driver_data['user_id'] ?? null, $driver_data['name'] ?? '', $driver_data['license_number'] ?? '', 
            $driver_data['license_expiry_date'] ?? null, $driver_data['contact_number'] ?? null, $driver_data['date_joined'] ?? null, 
            $driver_data['status'] ?? 'Pending', $driver_data['rating'] ?? 0.0, $driver_data['created_at'] ?? date('Y-m-d H:i:s')
        );
        $stmt->execute();
    }
    $stmt->close();
}

if (isset($_GET['download_csv'])) {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename=driver_profiles_' . date('Y-m-d') . '.csv');
    $output = fopen('php://output', 'w');
    fputcsv($output, ['ID', 'Name', 'License Number', 'License Expiry', 'Contact Number', 'Date Joined', 'Status', 'Rating', 'Total Trips', 'Avg Adherence Score']);
    $result = $conn->query("SELECT d.*, COUNT(t.id) as total_trips, AVG(t.route_adherence_score) as avg_adherence_score FROM drivers d LEFT JOIN trips t ON d.id = t.driver_id AND t.status = 'Completed' WHERE d.status != 'Pending' GROUP BY d.id ORDER BY d.name ASC");
    if ($result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            fputcsv($output, [$row['id'], $row['name'], $row['license_number'], $row['license_expiry_date'], $row['contact_number'], $row['date_joined'], $row['status'], $row['rating'], $row['total_trips'], number_format((float)$row['avg_adherence_score'], 2)]);
        }
    }
    fclose($output);
    exit;
}

if ($_SESSION['role'] === 'admin' && $_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['update_driver_status'])) {
    $driver_id_to_update = $_POST['driver_id_to_update'];
    $new_status = $_POST['new_status'];
    if ($new_status === 'Active') {
        if (createEvent($driver_id_to_update, 'DriverApproved', ['status' => 'Active'], $conn)) {
             $message = "<div class='message-banner success'>Driver approved. Change will be reflected shortly.</div>";
        }
    } elseif ($new_status === 'Rejected') {
        if (createEvent($driver_id_to_update, 'DriverDeleted', [], $conn)) { // Treat rejection as deletion
             $message = "<div class='message-banner success'>Driver rejected. Change will be reflected shortly.</div>";
        }
    }
    rebuildReadModel($conn);
}

if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['save_driver'])) {
    $id = $_POST['driver_id']; 
    $payload = ['name' => $_POST['name'], 'license_number' => $_POST['license_number'], 'license_expiry_date' => !empty($_POST['license_expiry_date']) ? $_POST['license_expiry_date'] : NULL, 'contact_number' => $_POST['contact_number'], 'date_joined' => !empty($_POST['date_joined']) ? $_POST['date_joined'] : NULL, 'status' => $_POST['status'], 'rating' => $_POST['rating'], 'user_id' => !empty($_POST['user_id']) ? (int)$_POST['user_id'] : NULL];
    if (empty($id)) { 
        $new_id_query = $conn->query("SELECT MAX(id) + 1 AS new_id FROM drivers");
        $new_id = $new_id_query->fetch_assoc()['new_id'] ?? 1;
        $payload['id'] = $new_id;
        $payload['created_at'] = date('Y-m-d H:i:s');
        if (createEvent($new_id, 'DriverCreated', $payload, $conn)) { $message = "<div class='message-banner success'>Driver saved successfully!</div>"; }
    } else { 
        if (createEvent($id, 'DriverUpdated', $payload, $conn)) { $message = "<div class='message-banner success'>Driver saved successfully!</div>"; }
    }
    rebuildReadModel($conn);
}

if (isset($_GET['delete_driver'])) {
    $id = $_GET['delete_driver']; 
    if (createEvent($id, 'DriverDeleted', [], $conn)) { $message = "<div class='message-banner success'>Driver deleted successfully!</div>"; }
    rebuildReadModel($conn);
}

$pending_drivers = $conn->query("SELECT d.id, d.name, d.license_number, u.email FROM drivers d JOIN users u ON d.user_id = u.id WHERE d.status = 'Pending' ORDER BY d.created_at ASC");
$drivers_query = "SELECT d.*, v.type as vehicle_type, v.model as vehicle_model, v.tag_code, AVG(t.route_adherence_score) as avg_adherence_score, COUNT(t.id) as total_trips FROM drivers d LEFT JOIN vehicles v ON d.id = v.assigned_driver_id LEFT JOIN trips t ON d.id = t.driver_id AND t.status = 'Completed' WHERE d.status != 'Pending' GROUP BY d.id ORDER BY d.name ASC";
$drivers = $conn->query($drivers_query);
$users = $conn->query("SELECT id, username FROM users WHERE role = 'driver'");
$behavior_logs_query = $conn->query("SELECT dbl.*, d.name as driver_name FROM driver_behavior_logs dbl JOIN drivers d ON dbl.driver_id = d.id ORDER BY dbl.log_date DESC");
$behavior_logs = [];
while($row = $behavior_logs_query->fetch_assoc()) { $behavior_logs[$row['driver_id']][] = $row; }
$behavior_logs_json = json_encode($behavior_logs);
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Driver Profiles | CQRS</title>
  <link rel="stylesheet" href="../../assets/css/style.css">
</head>
<body>
  <?php include '../../includes/sidebar.php'; ?>
  <div class="content" id="mainContent">
    <div class="header">
      <div class="hamburger" id="hamburger">☰</div>
      <div><h1>Driver Profile Management (CQRS Version)</h1></div>
      <div class="theme-toggle-container">
        <span class="theme-label">Dark Mode</span>
        <label class="theme-switch"><input type="checkbox" id="themeToggle"><span class="slider"></span></label>
      </div>
    </div>
    <?php echo $message; ?>

    <?php if ($_SESSION['role'] === 'admin' && $pending_drivers->num_rows > 0): ?>
    <div class="card">
      <h3>Pending Driver Registrations</h3>
      <div class="table-section">
        <table>
          <thead><tr><th>Name</th><th>License No.</th><th>Email</th><th>Actions</th></tr></thead>
          <tbody>
              <?php while($row = $pending_drivers->fetch_assoc()): ?>
              <tr>
                  <td><?php echo htmlspecialchars($row['name']); ?></td>
                  <td><?php echo htmlspecialchars($row['license_number']); ?></td>
                  <td><?php echo htmlspecialchars($row['email']); ?></td>
                  <td class="action-buttons">
                      <form action="driver_profiles_cqrs.php" method="POST" style="display: inline-block;"><input type="hidden" name="driver_id_to_update" value="<?php echo $row['id']; ?>"><input type="hidden" name="new_status" value="Active"><button type="submit" name="update_driver_status" class="btn btn-success btn-sm">Approve</button></form>
                      <form action="driver_profiles_cqrs.php" method="POST" style="display: inline-block;"><input type="hidden" name="driver_id_to_update" value="<?php echo $row['id']; ?>"><input type="hidden" name="new_status" value="Rejected"><button type="submit" name="update_driver_status" class="btn btn-danger btn-sm" onclick="return confirm('Are you sure?');">Reject</button></form>
                  </td>
              </tr>
              <?php endwhile; ?>
          </tbody>
        </table>
      </div>
    </div>
    <?php endif; ?>

    <div class="card">
      <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1rem;">
          <h3>Driver Profiles</h3>
          <div>
            <button id="addDriverBtn" class="btn btn-primary">Add Driver</button>
            <a href="driver_profiles_cqrs.php?download_csv=true" class="btn btn-success">Download CSV</a>
          </div>
      </div>
      <div id="driverModal" class="modal"><div class="modal-content"><span class="close-button">&times;</span><h2 id="modalTitle">Add Driver</h2><form action="driver_profiles_cqrs.php" method="POST"><input type="hidden" id="driver_id" name="driver_id"><div class="form-group"><label>Full Name</label><input type="text" name="name" id="name" class="form-control" required></div><div
