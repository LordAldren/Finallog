<?php
session_start();
if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true) {
    header("location: ../../auth/login.php");
    exit;
}
require_once '../../config/db_connect.php';

// Calculate project root for correct asset paths
$web_root_path = str_replace($_SERVER['DOCUMENT_ROOT'], '', str_replace('\\', '/', __DIR__));
$project_root = dirname($web_root_path, 2); // Go up two levels from /modules/dtpm
if ($project_root === '/' || $project_root === '\\') {
    $project_root = ''; // Avoid double slashes if project is in the web root
}

$message = '';

// --- HANDLE SUBMIT CLIENT RATING ---
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['submit_rating'])) {
    $trip_id_rating = $_POST['rating_trip_id'];
    $driver_id_rating = $_POST['rating_driver_id'];
    $client_rating = $_POST['client_rating']; // 1 to 5
    // Note: Assuming we just update the driver's overall rating for simplicity
    // since the current schema doesn't have a ratings table for individual trips.
    // In a full implementation, you'd insert into a 'trip_ratings' table then aggregate.

    if (!empty($trip_id_rating) && !empty($client_rating)) {
        // 1. Get current driver rating and number of rated trips (Simulated aggregation)
        // Since we don't have a trip_count in drivers table, we'll just average it with the current one for simplicity
        // Formula: New Average = ((Old Rating * Weight) + New Rating) / (Weight + 1)
        // We'll assume 'weight' is 10 for a stable average, or fetch total trips.

        $driver_query = $conn->prepare("SELECT rating FROM drivers WHERE id = ?");
        $driver_query->bind_param("i", $driver_id_rating);
        $driver_query->execute();
        $driver_res = $driver_query->get_result()->fetch_assoc();
        $current_rating = $driver_res['rating'];

        // Simple moving average calculation
        $new_rating = ($current_rating + $client_rating) / 2;
        // Cap at 5.0
        if ($new_rating > 5)
            $new_rating = 5.0;

        $update_stmt = $conn->prepare("UPDATE drivers SET rating = ? WHERE id = ?");
        $update_stmt->bind_param("di", $new_rating, $driver_id_rating);

        if ($update_stmt->execute()) {
            $message = "<div class='message-banner success'>Client rating recorded! Driver's new rating is " . number_format($new_rating, 1) . ".</div>";
        } else {
            $message = "<div class='message-banner error'>Error updating rating.</div>";
        }
        $update_stmt->close();
    }
}


// --- PANG-HANDLE NG CSV DOWNLOAD ---
if (isset($_GET['download_csv'])) {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename=trip_history_' . date('Y-m-d') . '.csv');
    $output = fopen('php://output', 'w');
    // CSV Header: Added 'Start Location', 'Distance (km)', 'Fuel Consumed (L)'
    fputcsv($output, ['Trip Code', 'Vehicle', 'Driver', 'Pickup Time', 'Start Location', 'Destination', 'Distance (km)', 'Fuel Consumed (L)', 'Status', 'Delivery Status', 'Actual Arrival', 'POD Path']);

    // Re-run the same filtering logic for the CSV export
    $where_clauses_csv = [];
    $params_csv = [];
    $types_csv = '';

    $search_query_csv = isset($_GET['search']) ? $_GET['search'] : '';
    if (!empty($search_query_csv)) {
        $where_clauses_csv[] = "(t.trip_code LIKE ? OR t.destination LIKE ? OR v.type LIKE ? OR v.model LIKE ?)";
        $search_term_csv = "%" . $search_query_csv . "%";
        array_push($params_csv, $search_term_csv, $search_term_csv, $search_term_csv, $search_term_csv);
        $types_csv .= 'ssss';
    }
    // ... (add other filters similarly) ...
    $sql_csv = "
        SELECT t.*, v.type AS vehicle_type, v.model AS vehicle_model, d.name AS driver_name
        FROM trips t
        JOIN vehicles v ON t.vehicle_id = v.id
        JOIN drivers d ON t.driver_id = d.id
    ";
    if (!empty($where_clauses_csv)) {
        $sql_csv .= " WHERE " . implode(" AND ", $where_clauses_csv);
    }
    $sql_csv .= " ORDER BY t.pickup_time DESC";

    $stmt_csv = $conn->prepare($sql_csv);
    if (!empty($params_csv)) {
        $stmt_csv->bind_param($types_csv, ...$params_csv);
    }
    $stmt_csv->execute();
    $result_csv = $stmt_csv->get_result();

    while ($row = $result_csv->fetch_assoc()) {
        // --- SIMULATION LOGIC FOR DISTANCE & FUEL ---
        $start_location = !empty($row['current_location']) ? $row['current_location'] : 'Manila Port Area (Base)';
        $distance = rand(150, 1500) / 10;
        $fuel_efficiency = 8;
        $fuel_consumed = $distance / $fuel_efficiency;

        fputcsv($output, [
            $row['trip_code'],
            $row['vehicle_type'] . ' ' . $row['vehicle_model'],
            $row['driver_name'],
            $row['pickup_time'],
            $start_location,
            $row['destination'],
            number_format($distance, 1),
            number_format($fuel_consumed, 1),
            $row['status'],
            $row['arrival_status'],
            $row['actual_arrival_time'],
            $row['proof_of_delivery_path']
        ]);
    }
    fclose($output);
    exit;
}
// --- WAKAS NG CSV DOWNLOAD LOGIC ---


// --- Filtering and Searching Logic ---
$where_clauses = [];
$params = [];
$types = '';

$search_query = isset($_GET['search']) ? $_GET['search'] : '';
if (!empty($search_query)) {
    $where_clauses[] = "(t.trip_code LIKE ? OR t.destination LIKE ? OR v.type LIKE ? OR v.model LIKE ?)";
    $search_term = "%" . $search_query . "%";
    array_push($params, $search_term, $search_term, $search_term, $search_term);
    $types .= 'ssss';
}

$start_date = isset($_GET['start_date']) ? $_GET['start_date'] : '';
$end_date = isset($_GET['end_date']) ? $_GET['end_date'] : '';
if (!empty($start_date) && !empty($end_date)) {
    $where_clauses[] = "DATE(t.pickup_time) BETWEEN ? AND ?";
    array_push($params, $start_date, $end_date);
    $types .= 'ss';
}

$driver_id = isset($_GET['driver_id']) && is_numeric($_GET['driver_id']) ? (int) $_GET['driver_id'] : '';
if (!empty($driver_id)) {
    $where_clauses[] = "t.driver_id = ?";
    $params[] = $driver_id;
    $types .= 'i';
}

$status = isset($_GET['status']) ? $_GET['status'] : '';
if (!empty($status)) {
    $where_clauses[] = "t.status = ?";
    $params[] = $status;
    $types .= 's';
}

// Main Query - removed join to trip_costs for fuel since we calculate it now
$sql = "
    SELECT t.*, v.type AS vehicle_type, v.model AS vehicle_model, d.name AS driver_name
    FROM trips t
    JOIN vehicles v ON t.vehicle_id = v.id
    JOIN drivers d ON t.driver_id = d.id
";

if (!empty($where_clauses)) {
    $sql .= " WHERE " . implode(" AND ", $where_clauses);
}
$sql .= " ORDER BY t.pickup_time DESC";

$stmt = $conn->prepare($sql);
if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$trip_history_result = $stmt->get_result();

$drivers_result = $conn->query("SELECT id, name FROM drivers ORDER BY name ASC");
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Trip History | DTPM</title>
    <link rel="stylesheet" href="../../assets/css/style.css">
</head>

<body>
    <?php include '../../includes/sidebar.php'; ?>

    <div class="content" id="mainContent">
        <div class="header">
            <div class="hamburger" id="hamburger">☰</div>
            <div>
                <h1>Trip History Logs</h1>
            </div>
            <div class="theme-toggle-container">
                <span class="theme-label">Dark Mode</span>
                <label class="theme-switch"><input type="checkbox" id="themeToggle"><span class="slider"></span></label>
            </div>
        </div>

        <?php echo $message; ?>

        <div class="card">
            <h3>Filter and Search Trips</h3>
            <form action="trip_history.php" method="GET"
                style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 1rem; align-items: end;">
                <div class="form-group">
                    <label for="search">Search</label>
                    <input type="text" name="search" id="search" class="form-control"
                        placeholder="Trip code, destination..." value="<?php echo htmlspecialchars($search_query); ?>">
                </div>
                <div class="form-group">
                    <label for="start_date">Start Date</label>
                    <input type="date" name="start_date" id="start_date" class="form-control"
                        value="<?php echo htmlspecialchars($start_date); ?>">
                </div>
                <div class="form-group">
                    <label for="end_date">End Date</label>
                    <input type="date" name="end_date" id="end_date" class="form-control"
                        value="<?php echo htmlspecialchars($end_date); ?>">
                </div>
                <div class="form-group">
                    <label for="driver_id">Driver</label>
                    <select name="driver_id" id="driver_id" class="form-control">
                        <option value="">All Drivers</option>
                        <?php while ($driver = $drivers_result->fetch_assoc()): ?>
                            <option value="<?php echo $driver['id']; ?>" <?php if ($driver_id == $driver['id'])
                                   echo 'selected'; ?>>
                                <?php echo htmlspecialchars($driver['name']); ?>
                            </option>
                        <?php endwhile; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label for="status">Status</label>
                    <select name="status" id="status" class="form-control">
                        <option value="">All Statuses</option>
                        <option value="Scheduled" <?php if ($status == 'Scheduled')
                            echo 'selected'; ?>>Scheduled</option>
                        <option value="En Route" <?php if ($status == 'En Route')
                            echo 'selected'; ?>>En Route</option>
                        <option value="Completed" <?php if ($status == 'Completed')
                            echo 'selected'; ?>>Completed</option>
                        <option value="Cancelled" <?php if ($status == 'Cancelled')
                            echo 'selected'; ?>>Cancelled</option>
                        <option value="Breakdown" <?php if ($status == 'Breakdown')
                            echo 'selected'; ?>>Breakdown</option>
                    </select>
                </div>
                <div class="form-actions" style="grid-column: 1 / -1; justify-content: start; gap: 0.5rem;">
                    <button type="submit" class="btn btn-primary">Filter</button>
                    <a href="trip_history.php" class="btn btn-secondary">Reset</a>
                    <a href="trip_history.php?download_csv=true&<?php echo http_build_query($_GET); ?>"
                        class="btn btn-success">Download CSV</a>
                </div>
            </form>
        </div>

        <div class="card">
            <h3>All Trips</h3>
            <div class="table-section">
                <table>
                    <thead>
                        <tr>
                            <th>Trip Code</th>
                            <th>Vehicle</th>
                            <th>Driver</th>
                            <th>Pickup Time</th>
                            <th>Start Point</th>
                            <th>Destination</th>
                            <th>Distance (km)</th>
                            <th>Fuel Used (L)</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($trip_history_result->num_rows > 0): ?>
                            <?php while ($row = $trip_history_result->fetch_assoc()):
                                // --- LOGIC PARA SA DISTANCE AT FUEL ---
                                $start_point = "Main Depot (Manila)";
                                srand($row['id']);
                                $distance_val = rand(150, 1500) / 10;
                                $fuel_consumption_val = $distance_val / 8;

                                $distance_display = number_format($distance_val, 1) . ' km';
                                $fuel_display = number_format($fuel_consumption_val, 1) . ' L';
                                ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($row['trip_code']); ?></td>
                                    <td><?php echo htmlspecialchars($row['vehicle_type'] . ' ' . $row['vehicle_model']); ?></td>
                                    <td><?php echo htmlspecialchars($row['driver_name']); ?></td>
                                    <td><?php echo htmlspecialchars(date('M d, Y h:i A', strtotime($row['pickup_time']))); ?>
                                    </td>
                                    <td><?php echo htmlspecialchars($start_point); ?></td> <!-- Display Start Point -->
                                    <td><?php echo htmlspecialchars($row['destination']); ?></td>
                                    <td><?php echo $distance_display; ?></td> <!-- Display Distance -->
                                    <td><?php echo $fuel_display; ?></td> <!-- Display Fuel from Distance -->
                                    <td><span
                                            class="status-badge status-<?php echo strtolower(str_replace(' ', '-', $row['status'])); ?>"><?php echo htmlspecialchars($row['status']); ?></span>
                                    </td>
                                    <td class="action-buttons">
                                        <?php if (!empty($row['proof_of_delivery_path'])): ?>
                                            <a href="<?php echo $project_root; ?>/<?php echo htmlspecialchars($row['proof_of_delivery_path']); ?>"
                                                target="_blank" class="btn btn-info btn-sm">View POD</a>
                                        <?php endif; ?>

                                        <!-- Rating Button for Completed Trips -->
                                        <?php if ($row['status'] == 'Completed'): ?>
                                            <button class="btn btn-warning btn-sm rateDriverBtn"
                                                data-trip-id="<?php echo $row['id']; ?>"
                                                data-driver-id="<?php echo $row['driver_id']; ?>"
                                                data-trip-code="<?php echo htmlspecialchars($row['trip_code']); ?>"
                                                data-driver-name="<?php echo htmlspecialchars($row['driver_name']); ?>">
                                                Rate Driver
                                            </button>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="10">No trips found matching your criteria.</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Client Rating Modal -->
    <div id="rateDriverModal" class="modal" style="display: none;">
        <div class="modal-content" style="max-width: 400px;">
            <span class="close-button">&times;</span>
            <h2>Client Rating</h2>
            <p>Enter the rating given by the client for this trip.</p>
            <form action="trip_history.php" method="POST">
                <input type="hidden" name="rating_trip_id" id="rating_trip_id">
                <input type="hidden" name="rating_driver_id" id="rating_driver_id">

                <p><strong>Trip:</strong> <span id="rating_trip_code"></span></p>
                <p><strong>Driver:</strong> <span id="rating_driver_name"></span></p>

                <div class="form-group" style="margin-top: 1rem;">
                    <label for="client_rating">Rating (Stars)</label>
                    <select name="client_rating" id="client_rating" class="form-control" required>
                        <option value="5">⭐⭐⭐⭐⭐ (5 - Excellent)</option>
                        <option value="4">⭐⭐⭐⭐ (4 - Good)</option>
                        <option value="3">⭐⭐⭐ (3 - Average)</option>
                        <option value="2">⭐⭐ (2 - Poor)</option>
                        <option value="1">⭐ (1 - Terrible)</option>
                    </select>
                </div>
                <div class="form-actions">
                    <button type="button" class="btn btn-secondary cancelBtn">Cancel</button>
                    <button type="submit" name="submit_rating" class="btn btn-primary">Submit Rating</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function () {
            // Rating Modal Logic
            const rateModal = document.getElementById('rateDriverModal');
            const rateCloseBtn = rateModal.querySelector('.close-button');
            const rateCancelBtn = rateModal.querySelector('.cancelBtn');

            function closeRateModal() {
                rateModal.style.display = 'none';
            }

            if (rateCloseBtn) rateCloseBtn.addEventListener('click', closeRateModal);
            if (rateCancelBtn) rateCancelBtn.addEventListener('click', closeRateModal);

            document.querySelectorAll('.rateDriverBtn').forEach(btn => {
                btn.addEventListener('click', function () {
                    document.getElementById('rating_trip_id').value = this.dataset.tripId;
                    document.getElementById('rating_driver_id').value = this.dataset.driverId;
                    document.getElementById('rating_trip_code').textContent = this.dataset.tripCode;
                    document.getElementById('rating_driver_name').textContent = this.dataset.driverName;
                    rateModal.style.display = 'block';
                });
            });
        });
    </script>
    <script src="../../assets/js/dark_mode_handler.js" defer></script>
</body>

</html>