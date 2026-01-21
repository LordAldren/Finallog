<?php
session_start();
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
    header('Location: ../../auth/login.php');
    exit;
}

if ($_SESSION['role'] !== 'driver') {
    header("location: ../../landpage.php");
    exit;
}

require_once '../../config/db_connect.php';

// Flash message system
$message = '';
if (isset($_SESSION['flash_message'])) {
    $message = $_SESSION['flash_message'];
    unset($_SESSION['flash_message']);
}

$user_id = $_SESSION['id'];
$view = isset($_GET['view']) ? $_GET['view'] : 'dashboard';

// --- DATA FETCHING ---
$driver_id_result = $conn->query("SELECT id FROM drivers WHERE user_id = $user_id LIMIT 1");
$driver_id = $driver_id_result->num_rows > 0 ? $driver_id_result->fetch_assoc()['id'] : null;

// Get Profile
$driver_profile = null;
if ($driver_id) {
    $profile_stmt = $conn->prepare("SELECT * FROM drivers WHERE id = ?");
    $profile_stmt->bind_param("i", $driver_id);
    $profile_stmt->execute();
    $driver_profile = $profile_stmt->get_result()->fetch_assoc();
}
$driver_name = $driver_profile ? $driver_profile['name'] : $_SESSION['username'];

// --- UPDATED TRIP LOGIC FOR ADVANCE NOTIFICATION ---
$current_trip = null;
$trip_id_for_js = 'null';
$upcoming_trips = []; // List para sa advance bookings

if ($driver_id) {
    // 1. Check Active Trip First (Priority)
    $active_sql = "SELECT t.*, v.type as vehicle_type, v.model as vehicle_model 
                   FROM trips t JOIN vehicles v ON t.vehicle_id = v.id 
                   WHERE t.driver_id = ? AND t.status IN ('En Route', 'Arrived at Destination', 'Unloading') LIMIT 1";
    $stmt = $conn->prepare($active_sql);
    $stmt->bind_param("i", $driver_id);
    $stmt->execute();
    $current_trip = $stmt->get_result()->fetch_assoc();
    if ($current_trip)
        $trip_id_for_js = $current_trip['id'];
    $stmt->close();

    // 2. Get Upcoming / Advance Bookings (Scheduled) - Ito ang notification
    $sched_sql = "SELECT t.*, v.type as vehicle_type, v.model as vehicle_model 
                  FROM trips t JOIN vehicles v ON t.vehicle_id = v.id 
                  WHERE t.driver_id = ? AND t.status = 'Scheduled' 
                  ORDER BY t.pickup_time ASC";
    $stmt = $conn->prepare($sched_sql);
    $stmt->bind_param("i", $driver_id);
    $stmt->execute();
    $res = $stmt->get_result();
    while ($row = $res->fetch_assoc()) {
        $upcoming_trips[] = $row;
    }
    $stmt->close();
}
$destination_json = json_encode($current_trip ? $current_trip['destination'] : null);
$driver_id_for_js = $driver_id ? $driver_id : 'null';

// Get stats for the dashboard
$dashboard_stats = ['weekly_trips' => 0, 'total_trips' => 0];
if ($driver_id) {
    $weekly_trips_result = $conn->query("SELECT COUNT(*) as count FROM trips WHERE driver_id = $driver_id AND status = 'Completed' AND pickup_time >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)");
    $dashboard_stats['weekly_trips'] = $weekly_trips_result->fetch_assoc()['count'];
    $total_trips_result = $conn->query("SELECT COUNT(*) as count FROM trips WHERE driver_id = $driver_id AND status = 'Completed'");
    $dashboard_stats['total_trips'] = $total_trips_result->fetch_assoc()['count'];
}

// Get completed trips for expense view
$expense_view_trips = [];
if ($driver_id) {
    $sql = "SELECT t.id, t.trip_code, t.destination, t.pickup_time, tc.id as cost_id FROM trips t LEFT JOIN trip_costs tc ON t.id = tc.trip_id WHERE t.driver_id = ? AND t.status = 'Completed' ORDER BY t.pickup_time DESC";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $driver_id);
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $expense_view_trips[] = $row;
    }
    $stmt->close();
}

// --- FORM HANDLING (POST) ---
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    if (isset($_POST['update_trip_status'])) {
        $trip_id = $_POST['trip_id'];
        $new_status = $_POST['new_status'];
        $location_update = $_POST['location_update'];
        if ($trip_id && $new_status && $driver_id) {
            $conn->begin_transaction();
            try {
                $stmt1 = $conn->prepare("UPDATE trips SET status = ? WHERE id = ? AND driver_id = ?");
                $stmt1->bind_param("sii", $new_status, $trip_id, $driver_id);
                $stmt1->execute();
                $stmt2 = $conn->prepare("INSERT INTO tracking_log (trip_id, status_message) VALUES (?, ?)");
                $stmt2->bind_param("is", $trip_id, $location_update);
                $stmt2->execute();
                $conn->commit();
                $message = "<div class='message-banner success'>Trip status updated to '$new_status'.</div>";
            } catch (Exception $e) {
                $conn->rollback();
                $message = "<div class='message-banner error'>Error: " . $e->getMessage() . "</div>";
            }
        }
    } elseif (isset($_POST['submit_pod'])) {
        // ... (Existing POD logic)
        $trip_id = $_POST['trip_id'];
        $delivery_notes = $_POST['delivery_notes'];
        $upload_dir = 'uploads/pod/';
        if (!is_dir($upload_dir))
            mkdir($upload_dir, 0755, true);
        if (isset($_FILES['pod_image']) && $_FILES['pod_image']['error'] == 0) {
            $file_name = time() . '_' . basename($_FILES['pod_image']['name']);
            if (move_uploaded_file($_FILES['pod_image']['tmp_name'], $upload_dir . $file_name)) {
                $db_path = 'modules/mfc/uploads/pod/' . $file_name;
                $stmt = $conn->prepare("UPDATE trips SET proof_of_delivery_path = ?, delivery_notes = ?, status = 'Completed', actual_arrival_time = NOW() WHERE id = ? AND driver_id = ?");
                $stmt->bind_param("ssii", $db_path, $delivery_notes, $trip_id, $driver_id);
                if ($stmt->execute()) {
                    $_SESSION['flash_message'] = "<div class='message-banner success'>POD submitted!</div>";
                    header("Location: mobile_app.php?view=expenses&trip_id=$trip_id");
                    exit();
                }
            }
        }
    } elseif (isset($_POST['submit_expenses'])) {
        // ... (Existing Expense logic)
        $trip_id_cost = $_POST['trip_id'];
        $fuel = $_POST['fuel_cost'] ?? 0;
        $labor = $_POST['labor_cost'] ?? 0;
        $tolls = $_POST['tolls_cost'] ?? 0;
        $other = $_POST['other_cost'] ?? 0;
        $v_stmt = $conn->prepare("SELECT vehicle_id FROM trips WHERE id = ?");
        $v_stmt->bind_param("i", $trip_id_cost);
        $v_stmt->execute();
        $vid = $v_stmt->get_result()->fetch_assoc()['vehicle_id'];
        $stmt = $conn->prepare("INSERT INTO trip_costs (trip_id, vehicle_id, fuel_cost, labor_cost, tolls_cost, other_cost) VALUES (?, ?, ?, ?, ?, ?)");
        $stmt->bind_param("iiddid", $trip_id_cost, $vid, $fuel, $labor, $tolls, $other);
        if ($stmt->execute()) {
            $_SESSION['flash_message'] = "<div class='message-banner success'>Expenses submitted!</div>";
            header("Location: mobile_app.php?view=expenses");
            exit();
        }
    } elseif (isset($_POST['behavior_alert'])) {
        // ... (Existing Behavior Logic)
        $trip_id = $_POST['trip_id'];
        $type = $_POST['alert_type'];
        $driver_id_alert = $_POST['driver_id'];
        $log_date = date('Y-m-d');
        $check = $conn->query("SELECT id FROM driver_behavior_logs WHERE driver_id = $driver_id_alert AND log_date = '$log_date'");
        if ($check->num_rows > 0) {
            $col = ($type == 'Overspeeding') ? 'overspeeding_count' : 'harsh_braking_count';
            $conn->query("UPDATE driver_behavior_logs SET $col = $col + 1 WHERE driver_id = $driver_id_alert AND log_date = '$log_date'");
        } else {
            $os = ($type == 'Overspeeding') ? 1 : 0;
            $hb = ($type == 'HarshBraking') ? 1 : 0;
            $conn->query("INSERT INTO driver_behavior_logs (driver_id, trip_id, log_date, overspeeding_count, harsh_braking_count) VALUES ($driver_id_alert, $trip_id, '$log_date', $os, $hb)");
        }
        exit;
    }
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Driver App | SLATE</title>
    <link rel="stylesheet" href="../../assets/css/mobile-style.css">
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
    <style>
        /* Add status-scheduled style */
        .status-scheduled {
            background-color: #F59E0B;
            color: #fff;
            padding: 2px 8px;
            border-radius: 10px;
            font-size: 0.8rem;
            font-weight: bold;
        }
    </style>
</head>

<body>
    <div class="app-container" id="appContainer">
        <!-- Header -->
        <header class="app-header">
            <button id="hamburgerBtn"><svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24"
                    fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <line x1="3" y1="12" x2="21" y2="12"></line>
                    <line x1="3" y1="6" x2="21" y2="6"></line>
                    <line x1="3" y1="18" x2="21" y2="18"></line>
                </svg></button>
            <div class="logo"><span>SLATE DRIVER</span></div>
        </header>

        <!-- Sidebar Navigation -->
        <div class="sidebar" id="driverSidebar">
            <div class="sidebar-header">
                <h3><?php echo htmlspecialchars($driver_name); ?></h3>
            </div>
            <nav class="sidebar-nav">
                <a href="mobile_app.php?view=dashboard"
                    class="<?php echo $view == 'dashboard' ? 'active' : ''; ?>">Dashboard</a>
                <a href="mobile_app.php?view=trip" class="<?php echo $view == 'trip' ? 'active' : ''; ?>">Current Trip</a>
                <a href="mobile_app.php?view=expenses" class="<?php echo $view == 'expenses' ? 'active' : ''; ?>">Expenses</a>
                <a href="../../auth/logout.php">Logout</a>
            </nav>
        </div>
        <div class="overlay" id="overlay"></div>

        <main class="app-main">
            <?php if (!empty($message))
                echo $message; ?>

            <!-- DASHBOARD VIEW -->
            <div id="dashboard-view" style="display: <?php echo $view === 'dashboard' ? 'block' : 'none'; ?>;">
                <h2 style="margin-bottom: 20px; font-weight: 700;">Dashboard</h2>

                <!-- NEW SECTION: Advance Booking Notification -->
                <?php if (!empty($upcoming_trips)): ?>
                    <div class="card" style="border-left: 5px solid var(--warning-color); background-color: #fffbeb;">
                        <h3 style="color: var(--warning-color); margin-top:0;">
                            <span>📅 Advance Request</span>
                        </h3>
                        <p style="font-size:0.9rem; margin-bottom:1rem; opacity:0.8;">New scheduled trips from Client
                            Portal:</p>
                        <ul style="list-style: none; padding: 0;">
                            <?php foreach ($upcoming_trips as $sched): ?>
                                <li
                                    style="background: white; padding: 12px; border-radius: 8px; margin-bottom: 10px; border: 1px solid #e5e7eb;">
                                    <div
                                        style="display:flex; justify-content:space-between; align-items:center; margin-bottom: 4px;">
                                        <strong
                                            style="color:var(--primary-color);"><?php echo date('M d, h:i A', strtotime($sched['pickup_time'])); ?></strong>
                                        <span class="status-badge status-scheduled">Scheduled</span>
                                    </div>
                                    <div style="font-size: 0.9rem; color: var(--text-muted);">
                                        <div style="display:flex; gap: 6px; align-items:center;">📍
                                            <?php echo htmlspecialchars($sched['destination']); ?></div>
                                        <div style="display:flex; gap: 6px; align-items:center; margin-top:2px;">🚛
                                            <?php echo htmlspecialchars($sched['vehicle_model']); ?></div>
                                    </div>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                <?php endif; ?>

                <div class="card">
                    <h3>
                        <span>Status</span>
                        <span class="status-badge"
                            style="background: <?php echo $current_trip ? 'var(--success-color)' : 'var(--text-muted)'; ?>; color: white;">
                            <?php echo $current_trip ? "ON DUTY" : "STANDBY"; ?>
                        </span>
                    </h3>
                    <?php if ($current_trip): ?>
                        <div style="background: #f1f5f9; padding: 16px; border-radius: 12px; margin-bottom: 16px;">
                            <p
                                style="margin:0; font-size: 0.85rem; text-transform:uppercase; color: var(--text-muted); font-weight:600;">
                                Active Trip</p>
                            <p style="margin:4px 0 0 0; font-size: 1.1rem; font-weight: bold; color: var(--primary-color);">
                                <?php echo htmlspecialchars($current_trip['trip_code']); ?></p>
                        </div>
                        <a href="mobile_app.php?view=trip" class="btn btn-success"
                            style="width: 100%; text-align:center; display:flex; justify-content:center;">
                            Go to Navigation &rarr;
                        </a>
                    <?php else: ?>
                        <p style="color: var(--text-muted);">No active trip right now. You are online and ready to receive
                            trips.</p>
                    <?php endif; ?>
                </div>

                <div class="card">
                    <h3>Your Stats</h3>
                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 16px;">
                        <div
                            style="background: #f8fafc; padding: 16px; border-radius: 12px; text-align: center; border: 1px solid #e2e8f0;">
                            <div style="font-size: 2rem; font-weight: 700; color: var(--accent-color); line-height:1;">
                                <?php echo $dashboard_stats['weekly_trips']; ?></div>
                            <div style="font-size: 0.8rem; color: var(--text-muted); margin-top: 4px;">Trips this Week
                            </div>
                        </div>
                        <div
                            style="background: #f8fafc; padding: 16px; border-radius: 12px; text-align: center; border: 1px solid #e2e8f0;">
                            <div style="font-size: 2rem; font-weight: 700; color: var(--warning-color); line-height:1;">
                                <?php echo number_format($driver_profile['rating'] ?? 0, 1); ?></div>
                            <div style="font-size: 0.8rem; color: var(--text-muted); margin-top: 4px;">Driver Rating ★
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- TRIP VIEW (Navigation) -->
            <div id="trip-view" style="display: <?php echo $view === 'trip' ? 'block' : 'none'; ?>;">
                <?php if ($current_trip): ?>
                    <div class="card">
                        <h3>Destination: <?php echo htmlspecialchars($current_trip['destination']); ?></h3>
                        <div id="map" style="height:300px; background:#eee; margin-bottom:1rem;"></div>
                        <form action="mobile_app.php" method="POST" class="actions-grid">
                            <input type="hidden" name="trip_id" value="<?php echo $current_trip['id']; ?>">
                            <input type="hidden" name="new_status" id="new_status_field">
                            <input type="hidden" name="location_update" id="location_update_field">
                            <button type="submit" name="update_trip_status"
                                onclick="document.getElementById('new_status_field').value='En Route'; document.getElementById('location_update_field').value='Departed';"
                                class="action-btn depart" style="background:var(--primary-color);">Start Trip</button>
                            <button type="submit" name="update_trip_status"
                                onclick="document.getElementById('new_status_field').value='Arrived at Destination'; document.getElementById('location_update_field').value='Arrived';"
                                class="action-btn arrive" style="background:var(--info-color);">Arrive</button>
                        </form>
                    </div>
                    <?php if ($current_trip['status'] == 'Arrived at Destination'): ?>
                        <div class="card">
                            <h3>Proof of Delivery</h3>
                            <form action="mobile_app.php" method="POST" enctype="multipart/form-data">
                                <input type="hidden" name="trip_id" value="<?php echo $current_trip['id']; ?>">
                                <input type="file" name="pod_image" class="form-control" required style="margin-bottom:1rem;">
                                <textarea name="delivery_notes" class="form-control" placeholder="Notes..."
                                    style="margin-bottom:1rem;"></textarea>
                                <button type="submit" name="submit_pod" class="btn btn-success"
                                    style="width:100%;">Complete</button>
                            </form>
                        </div>
                    <?php endif; ?>
                <?php else: ?>
                    <div class="card">
                        <p>No active trip selected.</p><a href="mobile_app.php?view=dashboard"
                            class="btn btn-primary">Back</a>
                    </div>
                <?php endif; ?>
            </div>

            <!-- EXPENSES VIEW -->
            <div id="expenses-view" style="display: <?php echo $view === 'expenses' ? 'block' : 'none'; ?>;">
                <h2>Expenses</h2>
                <?php if (isset($_GET['trip_id'])):
                    $tid = $_GET['trip_id']; ?>
                    <div class="card">
                        <h3>Log Expenses for Trip #<?php echo $tid; ?></h3>
                        <form action="mobile_app.php" method="POST">
                            <input type="hidden" name="trip_id" value="<?php echo $tid; ?>">
                            <div class="form-group"><label>Fuel</label><input type="number" name="fuel_cost"
                                    class="form-control"></div>
                            <div class="form-group"><label>Labor</label><input type="number" name="labor_cost"
                                    class="form-control"></div>
                            <div class="form-group"><label>Tolls</label><input type="number" name="tolls_cost"
                                    class="form-control"></div>
                            <button type="submit" name="submit_expenses" class="btn btn-success"
                                style="width:100%;">Submit</button>
                        </form>
                    </div>
                <?php else: ?>
                    <div class="card">
                        <h3>Pending Reports</h3>
                        <?php foreach ($expense_view_trips as $t):
                            if (is_null($t['cost_id'])): ?>
                                <p>Trip: <?php echo $t['trip_code']; ?> <a
                                        href="mobile_app.php?view=expenses&trip_id=<?php echo $t['id']; ?>"
                                        class="btn btn-sm btn-primary" style="float:right;">Report</a></p>
                                <hr>
                            <?php endif; endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </main>
    </div>

    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
    <script>
        // Basic Scripts for Mobile App
        const sidebar = document.getElementById('driverSidebar');
        const overlay = document.getElementById('overlay');
        document.getElementById('hamburgerBtn').addEventListener('click', () => { sidebar.classList.add('open'); overlay.classList.add('show'); });
        overlay.addEventListener('click', () => { sidebar.classList.remove('open'); overlay.classList.remove('show'); });

        // Basic Map (if destination exists)
        const dest = <?php echo $destination_json; ?>;
        if (dest && document.getElementById('map')) {
            const map = L.map('map').setView([14.5995, 120.9842], 12);
            L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png').addTo(map);
            // In a real app, geocode 'dest' to get lat/lng
        }
    </script>
</body>

</html>