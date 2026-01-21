<?php
session_start();
if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true) {
    header("location: ../../auth/login.php");
    exit;
}
require_once '../../config/db_connect.php';
$message = '';

// Calculate project root for correct asset paths
$web_root_path = str_replace($_SERVER['DOCUMENT_ROOT'], '', str_replace('\\', '/', __DIR__));
$project_root = dirname($web_root_path, 2); // Go up two levels from /modules/dtpm
if ($project_root === '/' || $project_root === '\\') {
    $project_root = ''; // Avoid double slashes if project is in the web root
}


// --- PANG-HANDLE NG CSV DOWNLOAD ---
if (isset($_GET['download_csv'])) {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename=delivery_status_' . date('Y-m-d') . '.csv');
    $output = fopen('php://output', 'w');
    fputcsv($output, ['Trip Code', 'Driver', 'ETA', 'Actual Arrival', 'Delivery Status', 'Notes', 'POD Path']);

    // Ulitin ang filter logic para sa CSV
    $where_clauses_csv = ["t.status IN ('Completed', 'En Route', 'Arrived at Destination')"];
    $params_csv = [];
    $types_csv = '';
    // I-apply ang mga filter dito kung kinakailangan para sa export...

    $sql_csv = "SELECT t.trip_code, d.name as driver_name, t.eta, t.actual_arrival_time, t.arrival_status, t.delivery_notes, t.proof_of_delivery_path FROM trips t JOIN drivers d ON t.driver_id = d.id WHERE " . implode(' AND ', $where_clauses_csv) . " ORDER BY t.pickup_time DESC";

    $stmt_csv = $conn->prepare($sql_csv);
    if (!empty($params_csv)) {
        $stmt_csv->bind_param($types_csv, ...$params_csv);
    }
    $stmt_csv->execute();
    $result = $stmt_csv->get_result();

    if ($result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            fputcsv($output, [$row['trip_code'], $row['driver_name'], $row['eta'], $row['actual_arrival_time'], $row['arrival_status'], $row['delivery_notes'], $row['proof_of_delivery_path']]);
        }
    }
    fclose($output);
    exit;
}
// --- WAKAS NG CSV DOWNLOAD LOGIC ---


// Handle Update Delivery Status and POD Upload
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['update_delivery'])) {
    $trip_id = $_POST['trip_id'];
    $arrival_status = $_POST['arrival_status'];
    $actual_arrival_time = !empty($_POST['actual_arrival_time']) ? $_POST['actual_arrival_time'] : NULL;
    $notes = $_POST['delivery_notes'];

    $pod_path_update = "";
    if (isset($_FILES['pod_image']) && $_FILES['pod_image']['error'] == 0) {
        $upload_dir_relative = '/modules/mfc/uploads/pod/';
        $upload_dir_absolute = $_SERVER['DOCUMENT_ROOT'] . $project_root . $upload_dir_relative;

        if (!is_dir($upload_dir_absolute)) {
            mkdir($upload_dir_absolute, 0755, true);
        }
        $file_name = time() . '_' . basename($_FILES['pod_image']['name']);
        $target_file = $upload_dir_absolute . $file_name;

        if (move_uploaded_file($_FILES['pod_image']['tmp_name'], $target_file)) {
            $pod_path_update = ", proof_of_delivery_path = ?";
            $db_target_file = 'modules/mfc/uploads/pod/' . $file_name;
        } else {
            $message = "<div class='message-banner error'>Failed to upload new Proof of Delivery image.</div>";
        }
    }

    $sql = "UPDATE trips SET arrival_status = ?, actual_arrival_time = ?, delivery_notes = ? $pod_path_update WHERE id = ?";
    $stmt = $conn->prepare($sql);

    if (!empty($pod_path_update)) {
        $stmt->bind_param("ssssi", $arrival_status, $actual_arrival_time, $notes, $db_target_file, $trip_id);
    } else {
        $stmt->bind_param("sssi", $arrival_status, $actual_arrival_time, $notes, $trip_id);
    }

    if ($stmt->execute()) {
        $message = "<div class='message-banner success'>Delivery status for Trip #$trip_id updated successfully.</div>";
    } else {
        $message = "<div class='message-banner error'>Error updating delivery status: " . $stmt->error . "</div>";
    }
    $stmt->close();
}


// --- Fetch Data with Filtering ---
$where_clauses = ["t.status IN ('Completed', 'En Route', 'Arrived at Destination')"];
$params = [];
$types = '';

$search_query = isset($_GET['search']) ? $_GET['search'] : '';
if (!empty($search_query)) {
    $where_clauses[] = "(t.trip_code LIKE ? OR d.name LIKE ?)";
    $search_term = "%" . $search_query . "%";
    array_push($params, $search_term, $search_term);
    $types .= 'ss';
}
$arrival_status_filter = isset($_GET['arrival_status']) ? $_GET['arrival_status'] : '';
if (!empty($arrival_status_filter)) {
    $where_clauses[] = "t.arrival_status = ?";
    $params[] = $arrival_status_filter;
    $types .= 's';
}

$sql = "SELECT t.id, t.trip_code, t.destination, t.eta, t.actual_arrival_time, t.arrival_status, t.proof_of_delivery_path, t.delivery_notes, d.name as driver_name FROM trips t JOIN drivers d ON t.driver_id = d.id";
if (!empty($where_clauses)) {
    $sql .= " WHERE " . implode(" AND ", $where_clauses);
}
$sql .= " ORDER BY t.pickup_time DESC";

$stmt = $conn->prepare($sql);
if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$trips_result = $stmt->get_result();
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Delivery Status | DTPM</title>
    <link rel="stylesheet" href="../../assets/css/style.css">
</head>

<body>
    <?php include '../../includes/sidebar.php'; ?>

    <div class="content" id="mainContent">
        <div class="header">
            <div class="hamburger" id="hamburger">☰</div>
            <div>
                <h1>Delivery Status & Proof of Delivery</h1>
            </div>
            <div class="theme-toggle-container">
                <span class="theme-label">Dark Mode</span>
                <label class="theme-switch"><input type="checkbox" id="themeToggle"><span class="slider"></span></label>
            </div>
        </div>

        <?php echo $message; ?>

        <div class="card">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1rem;">
                <h3>Monitor and Update Deliveries</h3>
                <a href="delivery_status.php?download_csv=true&<?php echo http_build_query($_GET); ?>"
                    class="btn btn-success">Download CSV</a>
            </div>

            <form action="delivery_status.php" method="GET" class="filter-form" style="margin-bottom: 1.5rem;">
                <div class="form-group">
                    <label>Search</label>
                    <input type="text" name="search" class="form-control" placeholder="Trip Code or Driver Name"
                        value="<?php echo htmlspecialchars($search_query); ?>">
                </div>
                <div class="form-group">
                    <label>Delivery Status</label>
                    <select name="arrival_status" class="form-control">
                        <option value="">All</option>
                        <option value="Pending" <?php if ($arrival_status_filter == 'Pending')
                            echo 'selected'; ?>>Pending
                        </option>
                        <option value="On-Time" <?php if ($arrival_status_filter == 'On-Time')
                            echo 'selected'; ?>>On-Time
                        </option>
                        <option value="Early" <?php if ($arrival_status_filter == 'Early')
                            echo 'selected'; ?>>Early
                        </option>
                        <option value="Late" <?php if ($arrival_status_filter == 'Late')
                            echo 'selected'; ?>>Late</option>
                    </select>
                </div>
                <div class="form-actions" style="grid-column: 1 / -1;">
                    <button type="submit" class="btn btn-primary">Filter</button>
                    <a href="delivery_status.php" class="btn btn-secondary">Reset</a>
                </div>
            </form>

            <div class="table-section">
                <table>
                    <thead>
                        <tr>
                            <th>Trip Code</th>
                            <th>Driver</th>
                            <th>Destination</th>
                            <th>ETA</th>
                            <th>Actual Arrival</th>
                            <th>Delivery Status</th>
                            <th>POD</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($trips_result->num_rows > 0): ?>
                            <?php while ($row = $trips_result->fetch_assoc()): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($row['trip_code']); ?></td>
                                    <td><?php echo htmlspecialchars($row['driver_name']); ?></td>
                                    <td><?php echo htmlspecialchars($row['destination']); ?></td>
                                    <td><?php echo !empty($row['eta']) ? date('M d, Y h:i A', strtotime($row['eta'])) : 'N/A'; ?>
                                    </td>
                                    <td><?php echo !empty($row['actual_arrival_time']) ? date('M d, Y h:i A', strtotime($row['actual_arrival_time'])) : 'N/A'; ?>
                                    </td>
                                    <td>
                                        <span
                                            class="status-badge status-<?php echo strtolower(str_replace(' ', '-', $row['arrival_status'])); ?>">
                                            <?php echo htmlspecialchars($row['arrival_status']); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <?php if (!empty($row['proof_of_delivery_path'])): ?>
                                            <a href="<?php echo $project_root; ?>/<?php echo htmlspecialchars($row['proof_of_delivery_path']); ?>"
                                                target="_blank" class="btn btn-info btn-sm">View</a>
                                        <?php else: ?>
                                            <span style="color: #888;">None</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="action-buttons">
                                        <button class="btn btn-warning btn-sm updateDeliveryBtn"
                                            data-details='<?php echo htmlspecialchars(json_encode($row), ENT_QUOTES, 'UTF-8'); ?>'>Update</button>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="8">No trips found matching your criteria.</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <div id="updateDeliveryModal" class="modal" style="display: none;">
            <div class="modal-content">
                <span class="close-button">&times;</span>
                <h3 id="updateDeliveryModalTitle">Update Delivery Status</h3>
                <form action="delivery_status.php" method="POST" enctype="multipart/form-data"
                    style="margin-top: 1rem;">
                    <input type="hidden" name="trip_id" id="update_trip_id">
                    <div class="form-group">
                        <label for="actual_arrival_time">Actual Arrival Time</label>
                        <input type="datetime-local" name="actual_arrival_time" id="actual_arrival_time"
                            class="form-control">
                    </div>
                    <div class="form-group">
                        <label for="arrival_status">Delivery Status</label>
                        <select name="arrival_status" id="arrival_status" class="form-control" required>
                            <option value="Pending">Pending</option>
                            <option value="On-Time">On-Time</option>
                            <option value="Early">Early</option>
                            <option value="Late">Late</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="delivery_notes">Delivery Notes</label>
                        <textarea name="delivery_notes" id="delivery_notes" class="form-control" rows="3"></textarea>
                    </div>
                    <div class="form-group">
                        <label>Current Proof of Delivery</label>
                        <div id="current_pod_view"></div>
                    </div>
                    <div class="form-group">
                        <label for="pod_image">Upload New/Replacement POD</label>
                        <input type="file" name="pod_image" id="pod_image" class="form-control" accept="image/*">
                    </div>
                    <div class="form-actions">
                        <button type="button" class="btn btn-secondary cancelBtn">Cancel</button>
                        <button type="submit" name="update_delivery" class="btn btn-primary">Save Changes</button>
                    </div>
                </form>
            </div>
        </div>

    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const projectRoot = '<?php echo $project_root; ?>';

            // Update Delivery Modal Logic
            const updateModal = document.getElementById('updateDeliveryModal');
            const closeBtn = updateModal.querySelector('.close-button');
            const cancelBtn = updateModal.querySelector('.cancelBtn');

            closeBtn.addEventListener('click', () => { updateModal.style.display = 'none'; });
            cancelBtn.addEventListener('click', () => { updateModal.style.display = 'none'; });
            window.addEventListener('click', (event) => {
                if (event.target == updateModal) {
                    updateModal.style.display = 'none';
                }
            });

            document.querySelectorAll('.updateDeliveryBtn').forEach(btn => {
                btn.addEventListener('click', () => {
                    const data = JSON.parse(btn.dataset.details);

                    updateModal.querySelector('#update_trip_id').value = data.id;
                    updateModal.querySelector('#updateDeliveryModalTitle').textContent = `Update Trip: ${data.trip_code}`;

                    if (data.actual_arrival_time) {
                        const date = new Date(data.actual_arrival_time);
                        date.setMinutes(date.getMinutes() - date.getTimezoneOffset());
                        updateModal.querySelector('#actual_arrival_time').value = date.toISOString().slice(0, 16);
                    } else {
                        updateModal.querySelector('#actual_arrival_time').value = '';
                    }

                    updateModal.querySelector('#arrival_status').value = data.arrival_status;
                    updateModal.querySelector('#delivery_notes').value = data.delivery_notes;

                    const podView = updateModal.querySelector('#current_pod_view');
                    if (data.proof_of_delivery_path) {
                        podView.innerHTML = `<a href="${projectRoot}/${data.proof_of_delivery_path}" target="_blank"><img src="${projectRoot}/${data.proof_of_delivery_path}" alt="POD" style="max-width: 100px; max-height: 100px; border-radius: 5px;"></a>`;
                    } else {
                        podView.innerHTML = `<span>No POD uploaded yet.</span>`;
                    }

                    updateModal.style.display = 'block';
                });
            });
        });
    </script>
    <script src="../../assets/js/dark_mode_handler.js" defer></script>
</body>

</html>