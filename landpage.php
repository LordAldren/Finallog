<?php
session_start();
if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true) {
    header("location: auth/login.php");
    exit;
}

// RBAC check - Admin at Staff lang ang pwedeng pumasok dito
if ($_SESSION['role'] === 'driver') {
    // Path corrected to point to the correct module folder
    header("location: modules/mfc/mobile_app.php");
    exit;
}

require_once 'config/db_connect.php';


// Fetch Dashboard Stats
$successful_deliveries = $conn->query("SELECT COUNT(*) as count FROM trips WHERE status = 'Completed'")->fetch_assoc()['count'];
$pending_maintenance = $conn->query("SELECT COUNT(*) as count FROM maintenance_approvals WHERE status = 'Pending'")->fetch_assoc()['count'];
$recent_trips = $conn->query("SELECT t.trip_code, t.destination, t.status, v.type FROM trips t JOIN vehicles v ON t.vehicle_id = v.id ORDER BY t.pickup_time DESC LIMIT 5");
$current_month_cost = $conn->query("SELECT SUM(tc.total_cost) as monthly_cost FROM trip_costs tc JOIN trips t ON tc.trip_id = t.id WHERE MONTH(t.pickup_time) = MONTH(CURDATE()) AND YEAR(t.pickup_time) = YEAR(CURDATE())")->fetch_assoc()['monthly_cost'] ?? 0;

// Fetch Recent Behavior Logs for Dashboard
$recent_behavior_logs = $conn->query("
    SELECT dbl.log_date, dbl.overspeeding_count, dbl.harsh_braking_count, dbl.idle_duration_minutes, d.name as driver_name
    FROM driver_behavior_logs dbl
    JOIN drivers d ON dbl.driver_id = d.id
    WHERE dbl.overspeeding_count > 0 OR dbl.harsh_braking_count > 0 OR dbl.idle_duration_minutes > 0
    ORDER BY dbl.id DESC
    LIMIT 5
");

// Fetch Vehicle Status Counts
$vehicle_status_query = $conn->query("SELECT status, COUNT(id) as count FROM vehicles GROUP BY status");
$vehicle_statuses = [];
if ($vehicle_status_query) {
    while ($row = $vehicle_status_query->fetch_assoc()) {
        $vehicle_statuses[$row['status']] = $row['count'];
    }
}

// Fetch a featured vehicle that is currently "En Route"
$featured_vehicle_query = $conn->query("
    SELECT v.model, v.tag_code, v.load_capacity_kg, d.name as driver_name
    FROM vehicles v 
    JOIN trips t ON v.id = t.vehicle_id
    JOIN drivers d ON t.driver_id = d.id
    WHERE t.status = 'En Route' 
    LIMIT 1
");
$featured_vehicle = $featured_vehicle_query->fetch_assoc();


// Fetch data for AI and charts
$daily_costs_query = $conn->query("SELECT DATE(t.pickup_time) as trip_date, SUM(tc.total_cost) as grand_total FROM trip_costs tc JOIN trips t ON tc.trip_id = t.id GROUP BY DATE(t.pickup_time) ORDER BY trip_date ASC");
$daily_costs_for_ai = [];
$daily_chart_data = [];
if ($daily_costs_query) {
    $day_index = 1;
    while ($row = $daily_costs_query->fetch_assoc()) {
        $daily_costs_for_ai[] = ["day" => $day_index++, "total" => (float) $row['grand_total']];
        $daily_chart_data[] = ["label" => date("M d", strtotime($row['trip_date'])), "cost" => (float) $row['grand_total']];
    }
}
$daily_costs_json = json_encode($daily_costs_for_ai);
$daily_chart_json = json_encode($daily_chart_data);

// Fetch initial locations for the map
$tracking_data_query = $conn->query("SELECT t.id as trip_id, v.type, v.model, d.name as driver_name, tl.latitude, tl.longitude FROM tracking_log tl JOIN trips t ON tl.trip_id = t.id AND t.status = 'En Route' JOIN vehicles v ON t.vehicle_id = v.id JOIN drivers d ON t.driver_id = d.id INNER JOIN ( SELECT trip_id, MAX(log_time) AS max_log_time FROM tracking_log GROUP BY trip_id) latest_log ON tl.trip_id = latest_log.trip_id AND tl.log_time = latest_log.max_log_time");
$initial_locations = [];
if ($tracking_data_query) {
    while ($row = $tracking_data_query->fetch_assoc()) {
        $initial_locations[] = $row;
    }
}
$initial_locations_json = json_encode($initial_locations);

?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard | SLATE Logistics</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="stylesheet" href="assets/css/loader.css">
    <script src="https://cdn.jsdelivr.net/npm/@tensorflow/tfjs@latest/dist/tf.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
    <script src="https://www.gstatic.com/firebasejs/8.10.1/firebase-app.js"></script>
    <script src="https://www.gstatic.com/firebasejs/8.10.1/firebase-database.js"></script>
    <style>
        /* Page-specific styles */
        .dashboard-main-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            grid-template-rows: auto auto 1fr;
            gap: 1.5rem;
        }

        .dashboard-stats {
            grid-column: 1 / -1;
        }

        .dashboard-cards {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
            gap: 1.5rem;
        }

        .dashboard-map {
            grid-column: 1 / 3;
            grid-row: 2 / 4;
        }

        #map {
            height: 100%;
            min-height: 600px;
            border-radius: var(--border-radius);
            width: 100%;
            z-index: 1;
        }

        .dashboard-cards-sidebar {
            grid-column: 3 / 4;
            grid-row: 2 / 4;
            display: flex;
            flex-direction: column;
            gap: 1.5rem;
        }

        .stat-icon {
            width: 50px;
            height: 50px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-right: 1rem;
        }

        .icon-deliveries {
            background-color: rgba(16, 185, 129, 0.1);
            color: var(--success-color);
        }

        .icon-maintenance {
            background-color: rgba(239, 68, 68, 0.1);
            color: var(--danger-color);
        }

        .icon-cost {
            background-color: rgba(245, 158, 11, 0.1);
            color: var(--warning-color);
        }

        .icon-ai {
            background-color: rgba(139, 92, 246, 0.1);
            color: #8B5CF6;
        }

        .stat-details h3 {
            font-size: 0.9rem;
            font-weight: 500;
            color: var(--text-muted);
        }

        .stat-value {
            font-size: 1.75rem;
            font-weight: 700;
        }

        .stat-label {
            font-size: 1rem;
            font-weight: 500;
        }

        .dashboard-cards .card {
            display: flex;
            align-items: center;
        }

        .vehicle-status-list {
            display: flex;
            flex-direction: column;
            gap: 0.75rem;
            margin-top: 1rem;
        }

        .status-item {
            display: flex;
            align-items: center;
            font-size: 0.9rem;
        }

        .status-indicator {
            width: 12px;
            height: 12px;
            border-radius: 50%;
            margin-right: 0.75rem;
        }

        .status-indicator.active {
            background-color: var(--success-color);
        }

        .status-indicator.en-route {
            background-color: var(--primary-color);
        }

        .status-indicator.maintenance {
            background-color: var(--warning-color);
        }

        .status-indicator.breakdown {
            background-color: var(--danger-color);
        }

        .status-count {
            margin-left: auto;
            font-weight: 600;
            background-color: rgba(0, 0, 0, 0.05);
            color: var(--text-main);
            padding: 0.1rem 0.5rem;
            border-radius: 5px;
            font-size: 0.8rem;
        }

        .dark-mode .status-count {
            background-color: rgba(255, 255, 255, 0.1);
        }

        /* Featured Vehicle Card */
        .featured-vehicle-card {
            display: grid;
            grid-template-columns: 1fr 1fr;
            align-items: center;
            gap: 1.5rem;
        }

        .featured-vehicle-card img {
            width: 100%;
            max-width: 200px;
            height: auto;
            object-fit: contain;
        }

        .featured-vehicle-info h4 {
            font-size: 1.1rem;
            margin-bottom: 0.25rem;
        }

        .featured-vehicle-info .tag-code {
            font-size: 0.8rem;
            color: var(--text-muted-dark);
            margin-bottom: 1rem;
        }

        .featured-vehicle-details {
            display: flex;
            flex-direction: column;
            gap: 0.5rem;
        }

        .featured-vehicle-details .detail-item {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            font-size: 0.9rem;
        }

        .featured-vehicle-details .detail-item svg {
            width: 20px;
            height: 20px;
            color: var(--primary-color);
        }

        @media (max-width: 1200px) {
            .dashboard-main-grid {
                grid-template-columns: 1fr;
            }

            .dashboard-map,
            .dashboard-cards-sidebar {
                grid-column: 1 / -1;
                grid-row: auto;
            }
        }
    </style>
</head>

<body>
    <div id="loading-overlay">
        <div class="loader-content">
            <img src="assets/images/logo.png" alt="SLATE Logo" class="loader-logo-main">
            <p id="loader-text">Initializing System...</p>
            <div class="road">
                <div class="vehicle-container vehicle-1">
                    <svg viewBox="0 0 512 512" xmlns="http://www.w3.org/2000/svg">
                        <path
                            d="M503.3 337.2c-7.2-21.6-21.6-36-43.2-43.2l-43.2-14.4V232c0-23.9-19.4-43.2-43.2-43.2H256V96c0-12.7-5.1-24.9-14.1-33.9L208 28.3c-9-9-21.2-14.1-33.9-14.1H48C21.5 14.2 0 35.7 0 62.2V337c0 23.9 19.4 43.2 43.2 43.2H64c0 35.3 28.7 64 64 64s64-28.7 64-64h128c0 35.3 28.7 64 64 64s64-28.7 64-64h17.3c23.9 0 43.2-19.4 43.2-43.2V337.2zM128 401c-17.7 0-32-14.3-32-32s14.3-32 32-32 32 14.3 32 32-14.3 32-32 32zm256 0c-17.7 0-32-14.3-32-32s14.3-32 32-32 32 14.3 32 32-14.3 32-32 32zm0-192h-88.8v-48H384v48z" />
                    </svg>
                </div>
                <div class="vehicle-container vehicle-2">
                    <svg viewBox="0 0 640 512" xmlns="http://www.w3.org/2000/svg">
                        <path
                            d="M624 352h-16V243.9c0-12.7-5.1-24.9-14.1-33.9L494 110.1c-9-9-21.2-14.1-33.9-14.1H416V48c0-26.5-21.5-48-48-48H112C85.5 0 64 21.5 64 48v48H48c-26.5 0-48 21.5-48 48v192c0 26.5 21.5 48 48 48h16c0 35.3 28.7 64 64 64s64-28.7 64-64h192c0 35.3 28.7 64 64 64s64-28.7 64-64h16c26.5 0 48-21.5 48-48V368c0-8.8-7.2-16-16-16zM128 400c-17.7 0-32-14.3-32-32s14.3-32 32-32 32 14.3 32 32-14.3 32-32 32zm384 0c-17.7 0-32-14.3-32-32s14.3-32 32-32 32 14.3 32 32-14.3 32-32 32zM480 224H128V144h288v48c0 26.5 21.5 48 48 48h16v-16z" />
                    </svg>
                </div>
                <div class="vehicle-container vehicle-3">
                    <svg viewBox="0 0 512 512" xmlns="http://www.w3.org/2000/svg">
                        <path
                            d="M503.3 337.2c-7.2-21.6-21.6-36-43.2-43.2l-43.2-14.4V232c0-23.9-19.4-43.2-43.2-43.2H256V96c0-12.7-5.1-24.9-14.1-33.9L208 28.3c-9-9-21.2-14.1-33.9-14.1H48C21.5 14.2 0 35.7 0 62.2V337c0 23.9 19.4 43.2 43.2 43.2H64c0 35.3 28.7 64 64 64s64-28.7 64-64h128c0 35.3 28.7 64 64 64s64-28.7 64-64h17.3c23.9 0 43.2-19.4 43.2-43.2V337.2zM128 401c-17.7 0-32-14.3-32-32s14.3-32 32-32 32 14.3 32 32-14.3 32-32 32zm256 0c-17.7 0-32-14.3-32-32s14.3-32 32-32 32 14.3 32 32-14.3 32-32 32zm0-192h-88.8v-48H384v48z" />
                    </svg>
                </div>
            </div>
        </div>
    </div>

    <?php include 'includes/sidebar.php'; ?>

    <div class="content" id="mainContent">
        <div class="header">
            <div class="hamburger" id="hamburger">☰</div>
            <div>
                <h1>Dashboard</h1>
            </div>
            <div class="theme-toggle-container">
                <span class="theme-label">Dark Mode</span>
                <label class="theme-switch"><input type="checkbox" id="themeToggle"><span class="slider"></span></label>
            </div>
        </div>

        <div class="dashboard-main-grid">
            <div class="dashboard-stats">
                <div class="dashboard-cards">
                    <div class="card">
                        <div class="stat-icon icon-deliveries"><svg xmlns="http://www.w3.org/2000/svg" width="24"
                                height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
                                stroke-linecap="round" stroke-linejoin="round">
                                <path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"></path>
                                <polyline points="22 4 12 14.01 9 11.01"></polyline>
                            </svg></div>
                        <div class="stat-details">
                            <h3>Successful Deliveries</h3>
                            <div class="stat-value"><?php echo $successful_deliveries; ?></div>
                        </div>
                    </div>
                    <div class="card">
                        <div class="stat-icon icon-maintenance"><svg xmlns="http://www.w3.org/2000/svg" width="24"
                                height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
                                stroke-linecap="round" stroke-linejoin="round">
                                <path
                                    d="M14.7 6.3a1 1 0 0 0 0 1.4l1.6 1.6a1 1 0 0 0 1.4 0l3.77-3.77a6 6 0 0 1-7.94 7.94l-6.91 6.91a2.12 2.12 0 0 1-3-3l6.91-6.91a6 6 0 0 1 7.94-7.94l-3.76 3.76z">
                                </path>
                            </svg></div>
                        <div class="stat-details">
                            <h3>Pending Maintenance</h3>
                            <div class="stat-value"><?php echo $pending_maintenance; ?></div>
                        </div>
                    </div>
                    <div class="card">
                        <div class="stat-icon icon-cost"><svg xmlns="http://www.w3.org/2000/svg" width="24" height="24"
                                viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
                                stroke-linecap="round" stroke-linejoin="round">
                                <line x1="12" y1="1" x2="12" y2="23"></line>
                                <path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"></path>
                            </svg></div>
                        <div class="stat-details">
                            <h3>Cost This Month</h3>
                            <div class="stat-value">₱<?php echo number_format($current_month_cost, 2); ?></div>
                        </div>
                    </div>
                    <div class="card">
                        <div class="stat-icon icon-ai"><svg xmlns="http://www.w3.org/2000/svg" width="24" height="24"
                                viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
                                stroke-linecap="round" stroke-linejoin="round">
                                <path d="M12 20V10"></path>
                                <path d="M18 20V4"></path>
                                <path d="M6 20V16"></path>
                            </svg></div>
                        <div class="stat-details">
                            <h3>AI Forecast (Tomorrow)</h3>
                            <div id="daily-prediction-loader-card" class="stat-label">Training...</div>
                            <div id="daily-prediction-result-card" class="stat-value" style="display:none;"></div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="dashboard-map card" style="height: 100%;">
                <div id="map"></div>
            </div>

            <div class="dashboard-cards-sidebar">

                <?php if ($featured_vehicle):
                    $image_path = 'assets/images/';
                    $image = 'https://placehold.co/400x300/e2e8f0/e2e8f0?text=No+Image';
                    if (stripos($featured_vehicle['model'], 'elf') !== false)
                        $image = $image_path . 'elf.PNG';
                    if (stripos($featured_vehicle['model'], 'hiace') !== false)
                        $image = $image_path . 'hiace.PNG';
                    if (stripos($featured_vehicle['model'], 'canter') !== false)
                        $image = $image_path . 'canter.PNG';
                    ?>
                    <div class="card">
                        <h3>Featured: En Route</h3>
                        <div class="featured-vehicle-card">
                            <img src="<?php echo $image; ?>"
                                alt="<?php echo htmlspecialchars($featured_vehicle['model']); ?>">
                            <div class="featured-vehicle-info">
                                <h4><?php echo htmlspecialchars($featured_vehicle['model']); ?></h4>
                                <p class="tag-code"><?php echo htmlspecialchars($featured_vehicle['tag_code']); ?></p>
                                <div class="featured-vehicle-details">
                                    <div class="detail-item">
                                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none"
                                            stroke="currentColor" stroke-width="2" stroke-linecap="round"
                                            stroke-linejoin="round">
                                            <path d="M12 2L2 7l10 5 10-5-10-5zM2 17l10 5 10-5M2 12l10 5 10-5"></path>
                                        </svg>
                                        <span><?php echo htmlspecialchars($featured_vehicle['load_capacity_kg']); ?>
                                            kg</span>
                                    </div>
                                    <div class="detail-item">
                                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none"
                                            stroke="currentColor" stroke-width="2" stroke-linecap="round"
                                            stroke-linejoin="round">
                                            <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path>
                                            <circle cx="12" cy="7" r="4"></circle>
                                        </svg>
                                        <span><?php echo htmlspecialchars($featured_vehicle['driver_name']); ?></span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>

                <div class="table-section card">
                    <h3>Recent Trips</h3>
                    <table>
                        <thead>
                            <tr>
                                <th>Code</th>
                                <th>Vehicle</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if ($recent_trips->num_rows > 0): ?>
                                <?php while ($trip = $recent_trips->fetch_assoc()): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($trip['trip_code']); ?></td>
                                        <td><?php echo htmlspecialchars($trip['type']); ?></td>
                                        <td><span
                                                class="status-badge status-<?php echo strtolower(str_replace(' ', '.', $trip['status'])); ?>"><?php echo htmlspecialchars($trip['status']); ?></span>
                                        </td>
                                    </tr>
                                <?php endwhile; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="3">No recent trips found.</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>

                <div class="table-section card">
                    <h3>Recent Behavior Incidents</h3>
                    <table>
                        <thead>
                            <tr>
                                <th>Driver</th>
                                <th>Incident Details</th>
                                <th>Date</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if ($recent_behavior_logs->num_rows > 0): ?>
                                <?php while ($log = $recent_behavior_logs->fetch_assoc()): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($log['driver_name']); ?></td>
                                        <td>
                                            <?php
                                            $incidents = [];
                                            if ($log['overspeeding_count'] > 0) {
                                                $incidents[] = "Overspeeding (" . $log['overspeeding_count'] . ")";
                                            }
                                            if ($log['harsh_braking_count'] > 0) {
                                                $incidents[] = "Harsh Braking (" . $log['harsh_braking_count'] . ")";
                                            }
                                            if ($log['idle_duration_minutes'] > 0) {
                                                $incidents[] = "Idle (" . $log['idle_duration_minutes'] . " mins)";
                                            }
                                            echo implode(', ', $incidents);
                                            ?>
                                        </td>
                                        <td><?php echo htmlspecialchars(date('M d', strtotime($log['log_date']))); ?></td>
                                    </tr>
                                <?php endwhile; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="3">No recent incidents recorded.</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                    <a href="modules/dtpm/driver_behavior.php"
                        style="display:block; text-align:right; margin-top:1rem; font-size:0.9em;">View All Logs</a>
                </div>

                <div class="card">
                    <h3>Vehicle Status</h3>
                    <div class="vehicle-status-list">
                        <div class="status-item">
                            <span class="status-indicator active"></span>
                            <span>Active/Idle</span>
                            <span
                                class="status-count"><?php echo ($vehicle_statuses['Active'] ?? 0) + ($vehicle_statuses['Idle'] ?? 0); ?></span>
                        </div>
                        <div class="status-item">
                            <span class="status-indicator en-route"></span>
                            <span>En Route</span>
                            <span class="status-count"><?php echo $vehicle_statuses['En Route'] ?? 0; ?></span>
                        </div>
                        <div class="status-item">
                            <span class="status-indicator maintenance"></span>
                            <span>Maintenance</span>
                            <span class="status-count"><?php echo $vehicle_statuses['Maintenance'] ?? 0; ?></span>
                        </div>
                        <div class="status-item">
                            <span class="status-indicator breakdown"></span>
                            <span>Breakdown</span>
                            <span class="status-count"><?php echo $vehicle_statuses['Breakdown'] ?? 0; ?></span>
                        </div>
                        <a href="modules/fvm/vehicle_list.php"
                            style="display:block; text-align:right; margin-top:1rem; font-size:0.9em;">View All
                            Vehicles</a>
                    </div>
                </div>

                <div class="card">
                    <h3>Trip Cost Trend</h3>
                    <div style="height: 250px;"><canvas id="costChart"></canvas></div>
                </div>
            </div>
        </div>
    </div>
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            // --- Standard page scripts ---
            // Sidebar and Dropdowns are now handled by assets/js/sidebar.js

            // --- AI & Chart Logic ---
            const dailyCostDataForAI = <?php echo $daily_costs_json; ?>;
            async function trainAndPredictDaily() {
                const loaderEl = document.getElementById('daily-prediction-loader-card');
                const resultEl = document.getElementById('daily-prediction-result-card');
                if (dailyCostDataForAI.length < 2) {
                    loaderEl.textContent = 'Not enough data.';
                    return;
                }
                const days = dailyCostDataForAI.map(d => d.day);
                const totals = dailyCostDataForAI.map(d => d.total);
                const xs = tf.tensor1d(days);
                const ys = tf.tensor1d(totals);
                const model = tf.sequential();
                model.add(tf.layers.dense({ units: 1, inputShape: [1] }));
                model.compile({ loss: 'meanSquaredError', optimizer: tf.train.adam(0.1) });
                await model.fit(xs, ys, { epochs: 200 });
                const nextDayIndex = days.length + 1;
                const prediction = model.predict(tf.tensor1d([nextDayIndex]));
                const predictedCost = prediction.dataSync()[0];
                loaderEl.style.display = 'none';
                resultEl.textContent = '₱' + parseFloat(predictedCost).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
                resultEl.style.display = 'block';
            }
            if (typeof tf !== 'undefined') { trainAndPredictDaily(); }

            const ctx = document.getElementById('costChart');
            if (ctx) {
                const dailyChartData = <?php echo $daily_chart_json; ?>;
                new Chart(ctx.getContext('2d'), {
                    type: 'line',
                    data: {
                        labels: dailyChartData.map(d => d.label),
                        datasets: [{
                            label: 'Total Daily Cost',
                            data: dailyChartData.map(d => d.cost),
                            borderColor: 'rgba(74, 108, 247, 1)',
                            backgroundColor: 'rgba(74, 108, 247, 0.1)',
                            fill: true,
                            tension: 0.3
                        }]
                    },
                    options: { responsive: true, maintainAspectRatio: false, scales: { y: { beginAtZero: true } } }
                });
            }

            // --- START: MAP LOGIC (UPDATED WITH MAXBOUNDS FOR PH) ---
            // Gumagalaw (pannable) pero hanggang PH lang (bounded)
            const map = L.map('map', {
                center: [12.8797, 121.7740], // Philippines Coordinates
                zoom: 6,
                minZoom: 5, // Para hindi masyadong lumayo
                maxBounds: [[4.0, 116.0], [22.0, 128.0]], // Restrict pan to PH area
                maxBoundsViscosity: 1.0, // Solid bounce pag lumampas
                zoomControl: false // Optional: kung gusto mo malinis tingnan
            });

            // Gamit ang CartoDB Voyager tiles para sa Waze-like aesthetic
            L.tileLayer('https://{s}.basemaps.cartocdn.com/rastertiles/voyager/{z}/{x}/{y}{r}.png', {
                attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors &copy; <a href="https://carto.com/attributions">CARTO</a>',
                subdomains: 'abcd',
                maxZoom: 20
            }).addTo(map);

            let markers = {};
            let firebaseInitialized = false;
            const firebaseConfig = {
                apiKey: "AIzaSyCB0_OYZXX3K-AxKeHnVlYMv2wZ_81FeYM",
                authDomain: "slate49-cde60.firebaseapp.com",
                databaseURL: "https://slate49-cde60-default-rtdb.firebaseio.com",
                projectId: "slate49-cde60",
                storageBucket: "slate49-cde60.firebasestorage.app",
                messagingSenderId: "809390854040",
                appId: "1:809390854040:web:f7f77333bb0ac7ab73e5ed"
            };

            function getVehicleIcon(vehicleInfo) {
                // Updated icon color to match Waze-like bright blue
                let svgIcon = `<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="#33ccff" stroke="#fff" stroke-width="1.5" width="40px" height="40px"><path d="M0 0h24v24H0z" fill="none"/><path d="M20 8h-3V4H3c-1.1 0-2 .9-2 2v11h2c0 1.66 1.34 3 3 3s3-1.34 3-3h6c0 1.66 1.34 3 3 3s3-1.34 3-3h2v-5l-3-4zM6 18c-.55 0-1-.45-1-1s.45-1 1-1 1 .45 1 1-.45 1-1 1zm13.5-1.5c-.83 0-1.5.67-1.5 1.5s.67 1.5 1.5 1.5 1.5-.67 1.5-1.5-.67-1.5-1.5-1.5zM18 10h1.5v3H18v-3zM3 6h12v7H3V6z"/></svg>`;
                return L.divIcon({ html: svgIcon, className: 'vehicle-icon', iconSize: [40, 40], iconAnchor: [20, 40], popupAnchor: [0, -40] });
            }

            function handleDataChange(tripId, data) {
                if (!map || !data.vehicle_info) return;
                const newLatLng = [data.lat, data.lng];
                if (!markers[tripId]) {
                    const popupContent = `<b>Vehicle:</b> ${data.vehicle_info}<br><b>Driver:</b> ${data.driver_name}`;
                    markers[tripId] = L.marker(newLatLng, { icon: getVehicleIcon(data.vehicle_info) }).addTo(map).bindPopup(popupContent);
                } else {
                    markers[tripId].setLatLng(newLatLng);
                }
            }

            function initializeFirebaseListener() {
                if (firebaseInitialized) return;
                try {
                    if (!firebase.apps.length) firebase.initializeApp(firebaseConfig); else firebase.app();
                    const database = firebase.database();
                    const trackingRef = database.ref('live_tracking');
                    trackingRef.on('child_added', (snapshot) => handleDataChange(snapshot.key, snapshot.val()));
                    trackingRef.on('child_changed', (snapshot) => handleDataChange(snapshot.key, snapshot.val()));
                    trackingRef.on('child_removed', (snapshot) => {
                        if (map && markers[snapshot.key]) {
                            map.removeLayer(markers[snapshot.key]);
                            delete markers[snapshot.key];
                        }
                    });
                    firebaseInitialized = true;
                } catch (e) { console.error("Firebase init error:", e); }
            }

            // Load initial data from PHP then start listening
            const initialLocations = <?php echo $initial_locations_json; ?>;
            initialLocations.forEach(loc => {
                handleDataChange(loc.trip_id, {
                    lat: loc.latitude,
                    lng: loc.longitude,
                    vehicle_info: `${loc.type} ${loc.model}`,
                    driver_name: loc.driver_name
                });
            });
            initializeFirebaseListener();
            // --- END: MAP LOGIC ---

        });
    </script>
    <script src="assets/js/dark_mode_handler.js" defer></script>
    <script src="assets/js/loader.js"></script>
</body>

</html>