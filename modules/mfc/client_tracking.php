<?php
session_start();
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
    header('Location: ../../auth/login.php');
    exit;
}

// RBAC check - Only drivers can access this page
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

// Get the view from the URL, default to 'dashboard'
$view = isset($_GET['view']) ? $_GET['view'] : 'dashboard';

// --- DATA FETCHING ---

// Get the driver ID to be used in other queries
$driver_id_result = $conn->query("SELECT id FROM drivers WHERE user_id = $user_id LIMIT 1");
$driver_id = $driver_id_result->num_rows > 0 ? $driver_id_result->fetch_assoc()['id'] : null;

// Get the full driver profile
$driver_profile = null;
if ($driver_id) {
    $profile_stmt = $conn->prepare("SELECT * FROM drivers WHERE id = ?");
    $profile_stmt->bind_param("i", $driver_id);
    $profile_stmt->execute();
    $driver_profile = $profile_stmt->get_result()->fetch_assoc();
    $profile_stmt->close();
}
$driver_name = $driver_profile ? $driver_profile['name'] : $_SESSION['username'];

// Get the driver's current trip
$current_trip = null;
$trip_id_for_js = 'null';
$driver_id_for_js = $driver_id ? $driver_id : 'null';

if ($driver_id) {
    // Prioritize showing an "in-progress" trip
    $trip_sql = "SELECT t.*, v.type as vehicle_type, v.model as vehicle_model 
                 FROM trips t 
                 JOIN vehicles v ON t.vehicle_id = v.id 
                 WHERE t.driver_id = ? AND t.status NOT IN ('Completed', 'Cancelled') 
                 ORDER BY 
                    CASE t.status
                        WHEN 'En Route' THEN 1
                        WHEN 'Arrived at Destination' THEN 2
                        WHEN 'Unloading' THEN 3
                        WHEN 'Breakdown' THEN 4
                        WHEN 'Idle' THEN 5
                        WHEN 'Scheduled' THEN 6
                        ELSE 7
                    END, 
                    t.pickup_time ASC 
                 LIMIT 1";
    $trip_stmt = $conn->prepare($trip_sql);
    $trip_stmt->bind_param("i", $driver_id);
    $trip_stmt->execute();
    $current_trip = $trip_stmt->get_result()->fetch_assoc();
    if ($current_trip) {
        $trip_id_for_js = $current_trip['id'];
    }
    $trip_stmt->close();
}
$destination_json = json_encode($current_trip ? $current_trip['destination'] : null);

// Get stats for the dashboard
$dashboard_stats = [
    'weekly_trips' => 0,
    'total_trips' => 0
];
if ($driver_id) {
    $weekly_trips_result = $conn->query("SELECT COUNT(*) as count FROM trips WHERE driver_id = $driver_id AND status = 'Completed' AND pickup_time >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)");
    $dashboard_stats['weekly_trips'] = $weekly_trips_result->fetch_assoc()['count'];
    
    $total_trips_result = $conn->query("SELECT COUNT(*) as count FROM trips WHERE driver_id = $driver_id AND status = 'Completed'");
    $dashboard_stats['total_trips'] = $total_trips_result->fetch_assoc()['count'];
}

// Get driver's behavior logs
$behavior_logs = [];
if ($driver_id) {
    $logs_stmt = $conn->prepare("
        SELECT log_date, overspeeding_count, harsh_braking_count, idle_duration_minutes, notes
        FROM driver_behavior_logs
        WHERE driver_id = ?
        ORDER BY log_date DESC
        LIMIT 10
    ");
    $logs_stmt->bind_param("i", $driver_id);
    $logs_stmt->execute();
    $logs_result = $logs_stmt->get_result();
    while ($row = $logs_result->fetch_assoc()) {
        $behavior_logs[] = $row;
    }
    $logs_stmt->close();
}

// Get completed trips for the expense view
$expense_view_trips = [];
if ($driver_id) {
    $sql = "SELECT t.id, t.trip_code, t.destination, t.pickup_time, tc.id as cost_id 
            FROM trips t 
            LEFT JOIN trip_costs tc ON t.id = tc.trip_id 
            WHERE t.driver_id = ? AND t.status = 'Completed'
            ORDER BY t.pickup_time DESC";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $driver_id);
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $expense_view_trips[] = $row;
    }
    $stmt->close();
}


// --- FORM & ACTION HANDLING ---
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Handle Status Update
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
            } catch (mysqli_sql_exception $exception) {
                $conn->rollback();
                $message = "<div class='message-banner error'>Database error: " . $exception->getMessage() . "</div>";
            }
        }
    }

    // Handle Proof of Delivery
    elseif (isset($_POST['submit_pod'])) {
        $trip_id = $_POST['trip_id'];
        $delivery_notes = $_POST['delivery_notes'];
        $upload_dir = 'uploads/pod/';
        if (!is_dir($upload_dir)) { mkdir($upload_dir, 0755, true); }

        if (isset($_FILES['pod_image']) && $_FILES['pod_image']['error'] == 0) {
            $file_name = time() . '_' . basename($_FILES['pod_image']['name']);
            $target_file = $upload_dir . $file_name;

            if (move_uploaded_file($_FILES['pod_image']['tmp_name'], $target_file)) {
                $db_path = 'modules/mfc/uploads/pod/' . $file_name; // Correct path for DB
                $stmt = $conn->prepare("UPDATE trips SET proof_of_delivery_path = ?, delivery_notes = ?, status = 'Completed', actual_arrival_time = NOW() WHERE id = ? AND driver_id = ?");
                $stmt->bind_param("ssii", $db_path, $delivery_notes, $trip_id, $driver_id);
                if($stmt->execute()){
                    $_SESSION['flash_message'] = "<div class='message-banner success'>Proof of Delivery submitted! Please file the expense report.</div>";
                    
                    // Remove firebase tracking data
                    echo '
                    <script src="https://www.gstatic.com/firebasejs/8.10.1/firebase-app.js"></script>
                    <script src="https://www.gstatic.com/firebasejs/8.10.1/firebase-database.js"></script>
                    <script>
                        const firebaseConfig = {
                            apiKey: "AIzaSyCB0_OYZXX3K-AxKeHnVlYMv2wZ_81FeYM",
                            authDomain: "slate49-cde60.firebaseapp.com",
                            databaseURL: "https://slate49-cde60-default-rtdb.firebaseio.com",
                            projectId: "slate49-cde60",
                            storageBucket: "slate49-cde60.firebasestorage.app",
                            messagingSenderId: "809390854040",
                            appId: "1:809390854040:web:f7f77333bb0ac7ab73e5ed"
                        };
                        if (!firebase.apps.length) {
                            firebase.initializeApp(firebaseConfig);
                        }
                        const database = firebase.database();
                        const tripIdToRemove = ' . $trip_id . ';
                        if(tripIdToRemove) {
                            database.ref("live_tracking/" + tripIdToRemove).remove()
                                .then(() => {
                                    window.location.href = "mobile_app.php?view=expenses&trip_id=' . $trip_id . '";
                                })
                                .catch((error) => {
                                    console.error("Error removing live tracking data: ", error);
                                    window.location.href = "mobile_app.php?view=expenses&trip_id=' . $trip_id . '";
                                });
                        } else {
                             window.location.href = "mobile_app.php?view=expenses&trip_id=' . $trip_id . '";
                        }
                    </script>';
                    exit(); 
                } else {
                    $message = "<div class='message-banner error'>Failed to update trip details in the database.</div>";
                }
            } else {
                $message = "<div class='message-banner error'>Failed to upload proof of delivery image.</div>";
            }
        } else {
            $message = "<div class='message-banner error'>Please select an image to upload as proof of delivery.</div>";
        }
    }
    
    // Handle SOS
    elseif (isset($_POST['send_sos'])) {
        $description = $_POST['description'];
        $trip_id = $_POST['trip_id'];
        if ($driver_id && $trip_id) {
            $sql = "INSERT INTO alerts (trip_id, driver_id, alert_type, description, status) VALUES (?, ?, 'SOS', ?, 'Pending')";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("iis", $trip_id, $driver_id, $description);
            $stmt->execute();
            $message = "<div class='message-banner success'>SOS Alert sent to admin! Help is on the way.</div>";
        } else {
            $message = "<div class='message-banner error'>Could not send SOS. You must be on an active trip.</div>";
        }
    }
    
    // Handle Expense Submission
    elseif (isset($_POST['submit_expenses'])) {
        $trip_id_cost = $_POST['trip_id'];
        $fuel_cost = !empty($_POST['fuel_cost']) ? (float)$_POST['fuel_cost'] : 0.00;
        $labor_cost = !empty($_POST['labor_cost']) ? (float)$_POST['labor_cost'] : 0.00;
        $tolls_cost = !empty($_POST['tolls_cost']) ? (float)$_POST['tolls_cost'] : 0.00;
        $other_cost = !empty($_POST['other_cost']) ? (float)$_POST['other_cost'] : 0.00;

        $vehicle_id_stmt = $conn->prepare("SELECT vehicle_id FROM trips WHERE id = ? AND driver_id = ?");
        $vehicle_id_stmt->bind_param("ii", $trip_id_cost, $driver_id);
        $vehicle_id_stmt->execute();
        $vehicle_id_result = $vehicle_id_stmt->get_result();

        if ($trip_info = $vehicle_id_result->fetch_assoc()) {
            $vehicle_id = $trip_info['vehicle_id'];

            $insert_cost_stmt = $conn->prepare("INSERT INTO trip_costs (trip_id, vehicle_id, fuel_cost, labor_cost, tolls_cost, other_cost) VALUES (?, ?, ?, ?, ?, ?)");
            $insert_cost_stmt->bind_param("iiddid", $trip_id_cost, $vehicle_id, $fuel_cost, $labor_cost, $tolls_cost, $other_cost);

            if ($insert_cost_stmt->execute()) {
                $_SESSION['flash_message'] = "<div class='message-banner success'>Expense report submitted successfully!</div>";
                header("Location: mobile_app.php?view=expenses");
                exit();
            } else {
                $message = "<div class='message-banner error'>Error submitting costs: " . $conn->error . "</div>";
            }
            $insert_cost_stmt->close();
        } else {
            $message = "<div class='message-banner error'>Could not find the specified trip to log costs.</div>";
        }
        $vehicle_id_stmt->close();
    }


    // --- BAGONG LOGIC: Handle Overspeeding AND Harsh Braking Alerts ---
    elseif (isset($_POST['behavior_alert'])) {
        $trip_id = $_POST['trip_id'];
        $alert_type = $_POST['alert_type']; // 'Overspeeding' or 'HarshBraking'
        $alert_message = $_POST['message'];
        $driver_id_alert = $_POST['driver_id'];

        if ($driver_id_alert && $trip_id) {
            // Check if a log for today already exists
            $check_stmt = $conn->prepare("SELECT id FROM driver_behavior_logs WHERE driver_id = ? AND log_date = CURDATE()");
            $check_stmt->bind_param("i", $driver_id_alert);
            $check_stmt->execute();
            $check_stmt->store_result();

            if ($check_stmt->num_rows > 0) {
                // Update existing log based on type
                if ($alert_type === 'Overspeeding') {
                    $update_sql = "UPDATE driver_behavior_logs SET overspeeding_count = overspeeding_count + 1 WHERE driver_id = ? AND log_date = CURDATE()";
                } else {
                    $update_sql = "UPDATE driver_behavior_logs SET harsh_braking_count = harsh_braking_count + 1 WHERE driver_id = ? AND log_date = CURDATE()";
                }
                $update_stmt = $conn->prepare($update_sql);
                $update_stmt->bind_param("i", $driver_id_alert);
                $update_stmt->execute();
                $update_stmt->close();
            } else {
                // Insert new log
                $log_date = date('Y-m-d');
                $overspeed_count = ($alert_type === 'Overspeeding') ? 1 : 0;
                $brake_count = ($alert_type === 'HarshBraking') ? 1 : 0;
                $notes = "Automated behavior detection initiated.";
                
                $sql = "INSERT INTO driver_behavior_logs (driver_id, trip_id, log_date, overspeeding_count, harsh_braking_count, notes) VALUES (?, ?, ?, ?, ?, ?)";
                $stmt = $conn->prepare($sql);
                $stmt->bind_param("iisiis", $driver_id_alert, $trip_id, $log_date, $overspeed_count, $brake_count, $notes);
                $stmt->execute();
                $stmt->close();
            }
             $check_stmt->close();
        }
        exit; // Stop execution after handling AJAX
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
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css"/>
</head>
<body>
    <div class="sidebar" id="driverSidebar">
        <div class="sidebar-header">
            <h3><?php echo htmlspecialchars($driver_name); ?></h3>
            <p>Driver Portal</p>
        </div>
        <nav class="sidebar-nav">
            <a href="mobile_app.php?view=dashboard" class="<?php if($view === 'dashboard') echo 'active'; ?>">
                <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"></path><polyline points="9 22 9 12 15 12 15 22"></polyline></svg>
                <span>Dashboard</span>
            </a>
            <a href="mobile_app.php?view=trip" class="<?php if($view === 'trip') echo 'active'; ?>">
                <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 22s-8-4.5-8-11.8A8 8 0 0 1 12 2a8 8 0 0 1 8 8.2c0 7.3-8 11.8-8 11.8z"></path><circle cx="12" cy="10" r="3"></circle></svg>
                <span>Current Trip</span>
            </a>
             <a href="mobile_app.php?view=expenses" class="<?php if($view === 'expenses' || $view === 'submit_expense') echo 'active'; ?>">
                <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="12" y1="1" x2="12" y2="23"></line><path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"></path></svg>
                <span>Expense Reports</span>
            </a>
             <a href="mobile_app.php?view=behavior" class="<?php if($view === 'behavior') echo 'active'; ?>">
                <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="22 12 18 12 15 21 9 3 6 12 2 12"></polyline></svg>
                <span>My Performance</span>
            </a>
            <a href="mobile_app.php?view=profile" class="<?php if($view === 'profile') echo 'active'; ?>">
                <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path><circle cx="12" cy="7" r="4"></circle></svg>
                <span>My Profile</span>
            </a>
        </nav>
        <div class="sidebar-footer">
            <a href="../../auth/logout.php">
                <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"></path><polyline points="16 17 21 12 16 7"></polyline><line x1="21" y1="12" x2="9" y2="12"></line></svg>
                <span>Logout</span>
            </a>
        </div>
    </div>
    <div class="overlay" id="overlay"></div>

    <div class="app-container" id="appContainer">
        <header class="app-header">
            <button id="hamburgerBtn">
                <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="3" y1="12" x2="21" y2="12"></line><line x1="3" y1="6" x2="21" y2="6"></line><line x1="3" y1="18" x2="21" y2="18"></line></svg>
            </button>
            <div class="logo">
                <img src="../../assets/images/logo.png" alt="SLATE Logo">
                <span>SLATE</span>
            </div>
        </header>

        <main class="app-main">
            <?php if (!empty($message)) echo $message; ?>

            <!-- DASHBOARD VIEW -->
            <div id="dashboard-view" class="view-section" style="display: <?php echo $view === 'dashboard' ? 'block' : 'none'; ?>;">
                <h2>Dashboard</h2>
                <div class="card">
                    <h3>Welcome back, <?php echo htmlspecialchars(explode(' ', $driver_name)[0]); ?>!</h3>
                    <p><?php echo $current_trip ? "You have an active trip." : "You are currently on standby."; ?></p>
                </div>
                
                <?php if ($current_trip): ?>
                <div class="card">
                    <h3>Trip Information</h3>
                    <p><strong>Trip ID:</strong> <?php echo htmlspecialchars($current_trip['trip_code']); ?></p>
                    <p><strong>Destination:</strong> <?php echo htmlspecialchars($current_trip['destination']); ?></p>
                    <p><strong>Status:</strong> <span class="status-badge status-<?php echo strtolower(str_replace(' ', '-', $current_trip['status'])); ?>"><?php echo htmlspecialchars($current_trip['status']); ?></span></p>
                    <a href="mobile_app.php?view=trip" class="btn btn-success" style="width: 100%; text-align:center; margin-top: 1rem;">View Trip Details</a>
                </div>
                <?php endif; ?>

                <div class="card">
                    <h3>Your Stats</h3>
                    <p><strong>Trips Completed (this week):</strong> <?php echo $dashboard_stats['weekly_trips']; ?></p>
                    <p><strong>Total Completed Trips:</strong> <?php echo $dashboard_stats['total_trips']; ?></p>
                     <p><strong>Rating:</strong> <?php echo $driver_profile ? number_format($driver_profile['rating'], 1) . ' ★' : 'N/A'; ?></p>
                </div>
            </div>

            <!-- TRIP VIEW -->
            <div id="trip-view" class="view-section" style="display: <?php echo $view === 'trip' ? 'block' : 'none'; ?>;">
                <?php if ($current_trip): ?>
                    <div class="card trip-card">
                        <div class="card-header">
                            <h2>Current Trip</h2>
                            <span class="status-badge status-<?php echo strtolower(str_replace(' ', '-', $current_trip['status'])); ?>"><?php echo htmlspecialchars($current_trip['status']); ?></span>
                        </div>
                        <div class="destination">
                            <p>DESTINATION</p>
                            <h3><?php echo htmlspecialchars($current_trip['destination']); ?></h3>
                        </div>
                        <div class="trip-details">
                            <p><strong>Trip ID:</strong> <?php echo htmlspecialchars($current_trip['trip_code']); ?></p>
                            <p><strong>Vehicle:</strong> <?php echo htmlspecialchars($current_trip['vehicle_type'] . ' ' . $current_trip['vehicle_model']); ?></p>
                            <p><strong>Pickup:</strong> <?php echo htmlspecialchars(date('M d, Y h:i A', strtotime($current_trip['pickup_time']))); ?></p>
                        </div>
                    </div>
                    
                    <div class="card">
                        <h3>Navigation</h3>
                        <div id="map"></div>
                        <div id="map-loader">Loading map...</div>
                        <div id="navigation-info" style="display:none; margin-top: 1rem; padding-top: 1rem; border-top: 1px solid var(--border-color); display: flex; justify-content: space-between; align-items: center;">
                            <div>
                                <p id="road-name" style="font-weight: 600;">...</p>
                                <p style="font-size: 0.9em; color: var(--text-muted);">Speed Limit: <span id="speed-limit">N/A</span> km/h</p>
                            </div>
                            <div style="text-align: right;">
                                 <p style="font-size: 0.9em; color: var(--text-muted);">Current Speed</p>
                                <div id="current-speed" style="font-size: 2rem; font-weight: 700;">
                                    0<span style="font-size: 0.6em; font-weight: 500;">km/h</span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="card">
                        <h3>Actions</h3>
                        <form action="mobile_app.php" method="POST" class="actions-grid">
                            <input type="hidden" name="trip_id" value="<?php echo $current_trip['id']; ?>">
                            <input type="hidden" name="new_status" id="new_status_field">
                            <input type="hidden" name="location_update" id="location_update_field">
                            
                            <button type="submit" name="update_trip_status" onclick="setStatus('En Route', 'Departed from origin')" class="action-btn depart">
                                <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24"><path d="M16.5 13.5L15 12 8 18 9.5 19.5 16.5 13.5zM15 12L16.5 10.5 9.5 4.5 8 6 15 12z"/></svg>
                                Depart
                            </button>
                            <button type="submit" name="update_trip_status" onclick="setStatus('Arrived at Destination', 'Arrived at Destination')" class="action-btn arrive">
                                <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24"><path d="M12 2C8.13 2 5 5.13 5 9c0 5.25 7 13 7 13s7-7.75 7-13c0-3.87-3.13-7-7-7zm0 9.5c-1.38 0-2.5-1.12-2.5-2.5s1.12-2.5 2.5-2.5 2.5 1.12 2.5 2.5-1.12 2.5-2.5 2.5z"/></svg>
                                Arrive
                            </button>
                            <button type="submit" name="update_trip_status" onclick="setStatus('Unloading', 'Unloading Cargo')" class="action-btn unload">
                               <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24"><path d="M19 15v-3h-2v3h-3v2h3v3h2v-3h3v-2h-3zM5.99 20.25l-.24-.25H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2h10l4 4v2.26c-.83-.62-1.87-.99-3-1.15V8h-4V4H4v14h3.75c.08-.66.31-1.28.69-1.84l.01.09H5.99z"/></svg>
                                Unload
                            </button>
                        </form>
                    </div>
                    
                    <?php if ($current_trip['status'] == 'Arrived at Destination' || $current_trip['status'] == 'Unloading'): ?>
                    <div class="card pod-card">
                        <h3>Proof of Delivery (POD)</h3>
                        <form action="mobile_app.php" method="POST" enctype="multipart/form-data">
                            <input type="hidden" name="trip_id" value="<?php echo $current_trip['id']; ?>">
                            <div class="form-group">
                                <label for="pod_image">Upload Photo (e.g., signed document)</label>
                                <input type="file" name="pod_image" id="pod_image" class="form-control" accept="image/*" required>
                            </div>
                            <div class="form-group">
                                <label for="delivery_notes">Notes (optional)</label>
                                <textarea name="delivery_notes" id="delivery_notes" class="form-control" rows="3"></textarea>
                            </div>
                            <button type="submit" name="submit_pod" class="btn btn-success" style="width: 100%;">Complete Delivery</button>
                        </form>
                    </div>
                    <?php endif; ?>

                <?php else: ?>
                    <div class="card no-trip-card">
                        <svg xmlns="http://www.w3.org/2000/svg" width="64" height="64" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"></circle><line x1="8" y1="12" x2="16" y2="12"></line></svg>
                        <h3>No Active Trip</h3>
                        <p>You do not have a trip at the moment. Go to the dashboard for a summary.</p>
                         <a href="mobile_app.php?view=dashboard" class="btn btn-success" style="width: 100%; text-align:center; margin-top: 1rem;">Go to Dashboard</a>
                    </div>
                <?php endif; ?>
            </div>
            
            <!-- EXPENSES VIEW -->
            <div id="expenses-view" class="view-section" style="display: <?php echo $view === 'expenses' ? 'block' : 'none'; ?>;">
                <h2>Expense Reports</h2>
                <div class="card">
                    <h3>Completed Trips</h3>
                    <?php if (!empty($expense_view_trips)): ?>
                        <ul class="trip-list">
                        <?php foreach ($expense_view_trips as $trip): ?>
                            <li>
                                <div class="trip-info">
                                    <strong><?php echo htmlspecialchars($trip['trip_code']); ?></strong>
                                    <small><?php echo htmlspecialchars(date('M d, Y', strtotime($trip['pickup_time']))); ?></small>
                                </div>
                                <div class="trip-action">
                                <?php if (is_null($trip['cost_id'])): ?>
                                    <a href="mobile_app.php?view=submit_expense&trip_id=<?php echo $trip['id']; ?>" class="btn btn-primary btn-sm">Submit Report</a>
                                <?php else: ?>
                                    <span class="report-submitted">Submitted</span>
                                <?php endif; ?>
                                </div>
                            </li>
                        <?php endforeach; ?>
                        </ul>
                    <?php else: ?>
                        <p>You have no completed trips to report.</p>
                    <?php endif; ?>
                </div>
            </div>

            <!-- SUBMIT EXPENSE VIEW -->
            <div id="submit-expense-view" class="view-section" style="display: <?php echo $view === 'submit_expense' ? 'block' : 'none'; ?>;">
                <h2>Submit Trip Costs</h2>
                <?php
                $expense_trip_id = isset($_GET['trip_id']) ? (int)$_GET['trip_id'] : 0;
                $expense_trip_details = null;
                if ($expense_trip_id > 0 && $driver_id) {
                    $et_stmt = $conn->prepare("SELECT trip_code, destination FROM trips WHERE id = ? AND driver_id = ?");
                    $et_stmt->bind_param("ii", $expense_trip_id, $driver_id);
                    $et_stmt->execute();
                    $expense_trip_details = $et_stmt->get_result()->fetch_assoc();
                    $et_stmt->close();
                }

                if ($expense_trip_details):
                ?>
                <div class="card">
                    <h3>Trip: <?php echo htmlspecialchars($expense_trip_details['trip_code']); ?></h3>
                    <p><strong>Destination:</strong> <?php echo htmlspecialchars($expense_trip_details['destination']); ?></p>
                    <form action="mobile_app.php?view=submit_expense&trip_id=<?php echo $expense_trip_id; ?>" method="POST" style="margin-top: 1rem;">
                        <input type="hidden" name="trip_id" value="<?php echo $expense_trip_id; ?>">
                        <div class="form-group">
                            <label for="fuel_cost">Fuel Cost (₱)</label>
                            <input type="number" name="fuel_cost" id="fuel_cost" class="form-control" step="0.01" placeholder="Enter total fuel cost" required>
                        </div>
                        <div class="form-group">
                            <label for="labor_cost">Labor Cost (₱)</label>
                            <input type="number" name="labor_cost" id="labor_cost" class="form-control" step="0.01" placeholder="e.g., meals, assistance">
                        </div>
                        <div class="form-group">
                            <label for="tolls_cost">Toll Fees (₱)</label>
                            <input type="number" name="tolls_cost" id="tolls_cost" class="form-control" step="0.01" placeholder="Enter total toll fees">
                        </div>
                        <div class="form-group">
                            <label for="other_cost">Other Costs (₱)</label>
                            <input type="number" name="other_cost" id="other_cost" class="form-control" step="0.01" placeholder="e.g., parking fees">
                        </div>
                        <button type="submit" name="submit_expenses" class="btn btn-success" style="width: 100%;">Submit Costs</button>
                    </form>
                </div>
                <?php else: ?>
                <div class="card no-trip-card">
                    <h3>Invalid Trip</h3>
                    <p>The selected trip was not found or is not assigned to you.</p>
                    <a href="mobile_app.php?view=dashboard" class="btn btn-success" style="width: 100%; text-align:center; margin-top: 1rem;">Back to Dashboard</a>
                </div>
                <?php endif; ?>
            </div>


            <!-- BEHAVIOR VIEW -->
            <div id="behavior-view" class="view-section" style="display: <?php echo $view === 'behavior' ? 'block' : 'none'; ?>;">
                <h2>My Performance Logs</h2>
                <p>This shows a record of recent driving incidents.</p>

                <?php if (!empty($behavior_logs)): ?>
                    <?php foreach ($behavior_logs as $log): ?>
                        <div class="card">
                            <h3><?php echo date('F j, Y', strtotime($log['log_date'])); ?></h3>
                            <ul>
                                <?php if ($log['overspeeding_count'] > 0): ?>
                                    <li><strong>Overspeeding:</strong> <?php echo $log['overspeeding_count']; ?> incident(s) recorded.</li>
                                <?php endif; ?>
                                <?php if ($log['harsh_braking_count'] > 0): ?>
                                    <li><strong>Harsh Braking:</strong> <?php echo $log['harsh_braking_count']; ?> incident(s) recorded.</li>
                                <?php endif; ?>
                                <?php if ($log['idle_duration_minutes'] > 0): ?>
                                    <li><strong>Excessive Idle Time:</strong> <?php echo $log['idle_duration_minutes']; ?> minutes recorded.</li>
                                <?php endif; ?>
                                <?php if (empty($log['overspeeding_count']) && empty($log['harsh_braking_count']) && empty($log['idle_duration_minutes'])): ?>
                                    <li>No major incidents recorded on this day.</li>
                                <?php endif; ?>
                                <?php if (!empty($log['notes'])): ?>
                                     <li style="margin-top: 0.5rem;"><strong>Admin Notes:</strong> <em><?php echo htmlspecialchars($log['notes']); ?></em></li>
                                <?php endif; ?>
                            </ul>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div class="card no-trip-card">
                         <svg xmlns="http://www.w3.org/2000/svg" width="64" height="64" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1" stroke-linecap="round" stroke-linejoin="round"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"></path><polyline points="22 4 12 14.01 9 11.01"></polyline></svg>
                         <h3>No Incidents Recorded</h3>
                         <p>Keep up the safe driving!</p>
                    </div>
                <?php endif; ?>
            </div>


            <!-- PROFILE VIEW -->
            <div id="profile-view" class="view-section" style="display: <?php echo $view === 'profile' ? 'block' : 'none'; ?>;">
                <h2>My Profile</h2>
                <?php if ($driver_profile): ?>
                    <div class="card profile-card">
                        <p>NAME</p>
                        <h3><?php echo htmlspecialchars($driver_profile['name']); ?></h3>
                        <ul>
                            <li><strong>Status:</strong> <span class="status-badge status-<?php echo strtolower($driver_profile['status']); ?>"><?php echo htmlspecialchars($driver_profile['status']); ?></span></li>
                            <li><strong>License Number:</strong> <?php echo htmlspecialchars($driver_profile['license_number']); ?></li>
                            <?php
                                $expiry_date = !empty($driver_profile['license_expiry_date']) ? new DateTime($driver_profile['license_expiry_date']) : null;
                                $is_expiring_soon = false;
                                if ($expiry_date) {
                                    $today = new DateTime();
                                    $diff = $today->diff($expiry_date);
                                    $is_expiring_soon = ($diff->days <= 30 && !$diff->invert);
                                }
                            ?>
                            <li class="<?php echo $is_expiring_soon ? 'expiring-soon' : ''; ?>">
                                <strong>License Expiry:</strong> <?php echo $expiry_date ? $expiry_date->format('M d, Y') : 'N/A'; ?>
                                <?php if($is_expiring_soon) echo " (Expiring soon!)"; ?>
                            </li>
                            <li><strong>Contact Number:</strong> <?php echo htmlspecialchars($driver_profile['contact_number']); ?></li>
                            <li><strong>Date Joined:</strong> <?php echo $driver_profile['date_joined'] ? date('M d, Y', strtotime($driver_profile['date_joined'])) : 'N/A'; ?></li>
                        </ul>
                    </div>
                <?php else: ?>
                    <p>Could not find your profile information.</p>
                <?php endif; ?>
            </div>

        </main>

        <?php if ($current_trip): ?>
            <button id="sendSosBtn" class="fab-sos">
                <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="white" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 22s-8-4.5-8-11.8A8 8 0 0 1 12 2a8 8 0 0 1 8 8.2c0 7.3-8 11.8-8 11.8z"></path><line x1="12" y1="8" x2="12" y2="12"></line><line x1="12" y1="16" x2="12.01" y2="16"></line></svg>
                <span>SOS</span>
            </button>
        <?php endif; ?>
    </div>

    <div id="actionModal" class="modal" style="display: none;">
      <div class="modal-content">
        <span class="close-button">&times;</span>
        <h2 id="modalTitle"></h2>
        <div id="modalBody"></div>
      </div>
    </div>
    
    <!-- Updated Alert Modal for Overspeeding AND Harsh Braking -->
    <div id="overspeedingAlert" class="modal" style="display: none;">
      <div class="modal-content" style="max-width: 400px; text-align: center;">
        <h2 style="color: var(--danger-color);" id="alertTitle">WARNING</h2>
        <p id="alertMessage">Please drive carefully.</p>
        <button id="closeOverspeedingAlert" class="btn" style="background-color: var(--danger-color); color: white; margin-top: 1rem;">I Understand</button>
      </div>
    </div>

    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
    <script src="https://www.gstatic.com/firebasejs/8.10.1/firebase-app.js"></script>
    <script src="https://www.gstatic.com/firebasejs/8.10.1/firebase-database.js"></script>
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        // --- Firebase Config & Init ---
        const firebaseConfig = {
            apiKey: "AIzaSyCB0_OYZXX3K-AxKeHnVlYMv2wZ_81FeYM",
            authDomain: "slate49-cde60.firebaseapp.com",
            databaseURL: "https://slate49-cde60-default-rtdb.firebaseio.com",
            projectId: "slate49-cde60",
            storageBucket: "slate49-cde60.firebasestorage.app",
            messagingSenderId: "809390854040",
            appId: "1:809390854040:web:f7f77333bb0ac7ab73e5ed"
        };
        let firebaseInitialized = false;
        try {
            if (!firebase.apps.length) { firebase.initializeApp(firebaseConfig); } else { firebase.app(); }
            firebaseInitialized = true;
        } catch(e) { console.error("Firebase init failed", e); }


        // --- Sidebar Logic ---
        const sidebar = document.getElementById('driverSidebar');
        const hamburgerBtn = document.getElementById('hamburgerBtn');
        const overlay = document.getElementById('overlay');
        const appContainer = document.getElementById('appContainer');

        function openSidebar() {
            sidebar.classList.add('open');
            overlay.classList.add('show');
        }

        function closeSidebar() {
            sidebar.classList.remove('open');
            overlay.classList.remove('show');
        }

        hamburgerBtn.addEventListener('click', openSidebar);
        overlay.addEventListener('click', closeSidebar);

        // --- Modal Logic ---
        const modal = document.getElementById("actionModal");
        if(modal) {
            const modalTitle = document.getElementById("modalTitle");
            const modalBody = document.getElementById("modalBody");
            const closeBtn = modal.querySelector(".close-button");
            if (closeBtn) { closeBtn.onclick = () => { modal.style.display = "none"; } }
            window.onclick = (event) => { if (event.target == modal) { modal.style.display = "none"; } }

            function showModal(title, content) {
                modalTitle.innerHTML = title;
                modalBody.innerHTML = content;
                modal.style.display = "flex";
            }
            
            const sendSosBtn = document.getElementById('sendSosBtn');
            if(sendSosBtn) {
                sendSosBtn.onclick = () => {
                    const formHtml = `
                        <form action="mobile_app.php" method="POST">
                            <input type="hidden" name="trip_id" value="<?php echo $current_trip ? $current_trip['id'] : ''; ?>">
                            <div class="form-group">
                                <label for="description">Describe the emergency:</label>
                                <textarea name="description" class="form-control" required placeholder="e.g., Flat tire, engine trouble..."></textarea>
                            </div>
                            <div class="form-actions" style="justify-content: flex-end; gap: 0.5rem;">
                                <button type="button" class="btn btn-secondary cancelBtn" style="background-color: #6c757d;">Cancel</button>
                                <button type="submit" name="send_sos" class="btn btn-danger">Confirm & Send SOS</button>
                            </div>
                        </form>`;
                    showModal("Confirm SOS Alert", formHtml);
                    modal.querySelector('.cancelBtn').onclick = () => { modal.style.display = "none"; };
                }
            }
        }

        // --- Map & Navigation Logic (Includes Speed & Harsh Braking) ---
        const destination = <?php echo $destination_json; ?>;
        const tripId = <?php echo $trip_id_for_js; ?>;
        const driverId = <?php echo $driver_id_for_js; ?>;
        const mapElement = document.getElementById('map');
        
        if (destination && mapElement) {
            const mapLoader = document.getElementById('map-loader');
            const navigationInfo = document.getElementById('navigation-info');
            const roadNameEl = document.getElementById('road-name');
            const speedLimitEl = document.getElementById('speed-limit');
            const currentSpeedEl = document.getElementById('current-speed');
            const overspeedingAlert = document.getElementById('overspeedingAlert');
            const alertTitle = document.getElementById('alertTitle');
            const alertMessage = document.getElementById('alertMessage');
            const closeOverspeedingAlertBtn = document.getElementById('closeOverspeedingAlert');

            let map;
            let vehicleMarker;
            let currentSpeed = 0;
            let speedLimit = 0;
            let lastOverspeedLogTime = 0;
            let lastHarshBrakingLogTime = 0;
            
            // Variables for Harsh Braking Detection
            let lastSpeed = null;
            let lastTimestamp = null;
            
            const maxSpeedLimit = 80; // Default max speed limit in km/h
            const harshBrakingThreshold = 12; // km/h drop per second considered "Harsh Braking"

            const startLocation = "Manila Port Area"; // Placeholder start

            const fetchRoadData = async (lat, lon) => {
                const url = `../dtpm/route_proxy.php?service=reverse&lat=${lat}&lon=${lon}`;
                try {
                    const response = await fetch(url);
                    const data = await response.json();
                    if (data && data.address) {
                        const road = data.address.road || 'Unknown Road';
                        roadNameEl.textContent = road;
                        const osmLimit = data.extratags && data.extratags.maxspeed ? parseInt(data.extratags.maxspeed, 10) : null;
                        speedLimit = osmLimit || maxSpeedLimit;
                        speedLimitEl.textContent = speedLimit;
                    } else {
                        roadNameEl.textContent = 'Acquiring road name...';
                        speedLimit = maxSpeedLimit;
                        speedLimitEl.textContent = speedLimit;
                    }
                } catch (error) {
                    console.error("Error fetching road data:", error);
                    speedLimit = maxSpeedLimit;
                    speedLimitEl.textContent = speedLimit;
                }
            };
            
            const updatePosition = (position) => {
                const lat = position.coords.latitude;
                const lon = position.coords.longitude;
                // Calculate speed in km/h (speed is in m/s from API)
                currentSpeed = position.coords.speed ? Math.round(position.coords.speed * 3.6) : 0;
                const currentTimestamp = position.timestamp;
                
                currentSpeedEl.innerHTML = `${currentSpeed}<span style="font-size: 0.6em; font-weight: 500;">km/h</span>`;

                // --- 1. OVERSPEEDING LOGIC ---
                if (currentSpeed > speedLimit && speedLimit > 0) {
                    currentSpeedEl.classList.add('speed-warning');
                    showAlert('SPEED WARNING', `You are exceeding the limit of ${speedLimit} km/h.`);
                    logBehavior('Overspeeding', `Exceeded limit of ${speedLimit} km/h, current speed is ${currentSpeed} km/h.`);
                } else {
                    currentSpeedEl.classList.remove('speed-warning');
                }

                // --- 2. HARSH BRAKING LOGIC ---
                if (lastSpeed !== null && lastTimestamp !== null) {
                    const timeDiffSeconds = (currentTimestamp - lastTimestamp) / 1000;
                    if (timeDiffSeconds > 0) {
                        const speedDiff = lastSpeed - currentSpeed; // Positive if slowing down
                        const deceleration = speedDiff / timeDiffSeconds; // km/h per second

                        // Check if deceleration exceeds threshold
                        if (deceleration > harshBrakingThreshold) {
                            showAlert('HARSH BRAKING', 'Sudden deceleration detected. Please brake smoothly.');
                            logBehavior('HarshBraking', `Rapid deceleration of ${Math.round(deceleration)} km/h/s detected.`);
                        }
                    }
                }
                
                // Update tracking variables
                lastSpeed = currentSpeed;
                lastTimestamp = currentTimestamp;

                // Update Map Marker
                if (!vehicleMarker) {
                    const vehicleIcon = L.divIcon({ html: '<svg xmlns="http://www.w3.org/2000/svg" width="40" height="40" viewBox="0 0 24 24" fill="#33ccff" stroke="#fff" stroke-width="1.5"><path d="M18.92 6.01C18.72 5.42 18.16 5 17.5 5h-11C5.84 5 5.28 5.42 5.08 6.01L3 12v8c0 .55.45 1 1 1h1c.55 0 1-.45 1-1v-1h12v1c0 .55.45 1 1 1h1c.55 0 1-.45 1-1v-8l-2.08-5.99zM6.5 16c-.83 0-1.5-.67-1.5-1.5S5.67 13 6.5 13s1.5.67 1.5 1.5S7.33 16 6.5 16zm11 0c-.83 0-1.5-.67-1.5-1.5s.67-1.5 1.5-1.5 1.5.67 1.5 1.5s-.67-1.5-1.5 1.5zM5 11l1.5-4.5h11L19 11H5z"/></svg>', className: 'vehicle-icon', iconSize: [40, 40], iconAnchor: [20, 40] });
                    vehicleMarker = L.marker([lat, lon], {icon: vehicleIcon}).addTo(map);
                    map.setView([lat, lon], 16);
                } else {
                    vehicleMarker.setLatLng([lat, lon]);
                    map.panTo([lat, lon]);
                }

                if (firebaseInitialized && tripId) {
                    const database = firebase.database();
                    database.ref('live_tracking/' + tripId).update({
                        lat: lat,
                        lng: lon,
                        speed: currentSpeed,
                        timestamp: new Date().toISOString()
                    });
                }
                
                fetchRoadData(lat, lon);
            };

            const showAlert = (title, msg) => {
                // Prevent alert spamming (show only if hidden)
                if (overspeedingAlert.style.display === 'none') {
                    alertTitle.textContent = title;
                    alertMessage.textContent = msg;
                    overspeedingAlert.style.display = 'flex';
                }
            };

            const logBehavior = (type, message) => {
                const now = Date.now();
                // Rate limiting to prevent database flooding (1 log per minute per type)
                if (type === 'Overspeeding') {
                    if (now - lastOverspeedLogTime < 60000) return;
                    lastOverspeedLogTime = now;
                } else if (type === 'HarshBraking') {
                    if (now - lastHarshBrakingLogTime < 60000) return;
                    lastHarshBrakingLogTime = now;
                }

                const xhr = new XMLHttpRequest();
                xhr.open('POST', 'mobile_app.php', true);
                xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
                // Sending alert_type specifically
                xhr.send(`behavior_alert=1&alert_type=${type}&trip_id=${tripId}&driver_id=${driverId}&message=${message}`);
            };

            const errorPosition = (error) => {
                console.warn(`ERROR(${error.code}): ${error.message}`);
                if(navigationInfo) navigationInfo.style.display = 'none';
                if(mapLoader) mapLoader.textContent = 'Unable to get location. Check device settings and permissions.';
            };
            
            Promise.all([
                fetch(`../dtpm/route_proxy.php?service=geocode&q=${encodeURIComponent(startLocation)}`).then(res => res.json()),
                fetch(`../dtpm/route_proxy.php?service=geocode&q=${encodeURIComponent(destination)}`).then(res => res.json())
            ]).then(([startData, endData]) => {
                if (!startData.length || !endData.length) throw new Error('Could not find start or end location coordinates.');
                
                const startCoords = [startData[0].lat, startData[0].lon];
                const endCoords = [endData[0].lat, endData[0].lon];

                // Waze-like map init for mobile
                map = L.map('map').setView(startCoords, 13);
                
                // Using CartoDB Voyager for clean look
                L.tileLayer('https://{s}.basemaps.cartocdn.com/rastertiles/voyager/{z}/{x}/{y}{r}.png', { 
                    attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors &copy; <a href="https://carto.com/attributions">CARTO</a>',
                    subdomains: 'abcd',
                    maxZoom: 20
                }).addTo(map);
                
                // Invalidate map size after it's been initialized and placed in the DOM
                setTimeout(() => { if (map) map.invalidateSize(); }, 100);

                L.marker(endCoords).addTo(map).bindPopup('Destination');

                if (navigationInfo) navigationInfo.style.display = 'flex';
                navigator.geolocation.watchPosition(updatePosition, errorPosition, { enableHighAccuracy: true, maximumAge: 0, timeout: 10000 });
                
                return fetch(`../dtpm/route_proxy.php?service=route&coords=${startCoords[1]},${startCoords[0]};${endCoords[1]},${endCoords[0]}`);
            }).then(res => res.json()).then(data => {
                if(data.routes && data.routes.length > 0) {
                    const routeCoordinates = data.routes[0].geometry.coordinates.map(coord => [coord[1], coord[0]]);
                    // Bright blue route line like navigation apps
                    const polyline = L.polyline(routeCoordinates, {color: '#33ccff', weight: 6, opacity: 0.9}).addTo(map);
                    map.fitBounds(polyline.getBounds().pad(0.1));
                    if(mapLoader) mapLoader.style.display = 'none';
                    // Invalidate again after fitting bounds to be safe
                    setTimeout(() => { if (map) map.invalidateSize(); }, 100);
                } else {
                     throw new Error('No route found from routing service.');
                }
            }).catch(error => {
                console.error("Map/Route Error:", error);
                if(mapLoader) mapLoader.textContent = `Error loading map/route: ${error.message}`;
            });

            if (closeOverspeedingAlertBtn) {
                closeOverspeedingAlertBtn.addEventListener('click', () => {
                    overspeedingAlert.style.display = 'none';
                });
            }
        }
    });

    function setStatus(newStatus, locationUpdate) {
        document.getElementById('new_status_field').value = newStatus;
        document.getElementById('location_update_field').value = locationUpdate;
    }
    </script>
</body>
</html>