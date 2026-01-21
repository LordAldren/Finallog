<?php
session_start();
if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true) {
    header("location: ../../auth/login.php");
    exit;
}
require_once '../../config/db_connect.php';
$message = '';

// --- PANG-HANDLE NG CSV DOWNLOAD ---
if (isset($_GET['download_csv'])) {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename=trip_costs_' . date('Y-m-d') . '.csv');
    $output = fopen('php://output', 'w');
    fputcsv($output, ['Cost ID', 'Trip Code', 'Vehicle', 'Fuel Cost', 'Labor Cost', 'Tolls Cost', 'Other Cost', 'Total Cost']);

    $result = $conn->query("SELECT tc.*, t.trip_code, v.type, v.model 
                            FROM trip_costs tc 
                            JOIN trips t ON tc.trip_id = t.id
                            JOIN vehicles v ON t.vehicle_id = v.id
                            ORDER BY tc.id DESC");
    if ($result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            fputcsv($output, [
                $row['id'],
                $row['trip_code'],
                $row['type'] . ' ' . $row['model'],
                $row['fuel_cost'],
                $row['labor_cost'],
                $row['tolls_cost'],
                $row['other_cost'],
                $row['total_cost']
            ]);
        }
    }
    fclose($output);
    exit;
}
// --- WAKAS NG CSV DOWNLOAD LOGIC ---

// Fetch Data
$trip_costs = $conn->query("SELECT tc.*, t.trip_code, v.type, v.model 
                            FROM trip_costs tc 
                            JOIN trips t ON tc.trip_id = t.id
                            JOIN vehicles v ON t.vehicle_id = v.id
                            ORDER BY tc.id DESC");

?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Per-Trip Costs | TCAO</title>
    <link rel="stylesheet" href="../../assets/css/style.css">
</head>

<body>
    <?php include '../../includes/sidebar.php'; ?>

    <div class="content" id="mainContent">
        <div class="header">
            <div class="hamburger" id="hamburger">☰</div>
            <div>
                <h1>Per-Trip Cost Report</h1>
            </div>
            <div class="theme-toggle-container">
                <span class="theme-label">Dark Mode</span>
                <label class="theme-switch"><input type="checkbox" id="themeToggle"><span class="slider"></span></label>
            </div>
        </div>

        <?php echo $message; ?>

        <div class="card">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1rem;">
                <h3>Per-Trip Cost Breakdown</h3>
                <a href="trip_costs.php?download_csv=true" class="btn btn-success">Download CSV</a>
            </div>

            <div class="table-section">
                <table>
                    <thead>
                        <tr>
                            <th>Trip Code</th>
                            <th>Vehicle</th>
                            <th>Fuel Cost</th>
                            <th>Labor Cost</th>
                            <th>Tolls</th>
                            <th>Other</th>
                            <th>Total Cost</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($trip_costs->num_rows > 0):
                            mysqli_data_seek($trip_costs, 0); ?>
                            <?php while ($row = $trip_costs->fetch_assoc()): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($row['trip_code']); ?></td>
                                    <td><?php echo htmlspecialchars($row['type'] . ' ' . $row['model']); ?></td>
                                    <td>₱<?php echo number_format($row['fuel_cost'], 2); ?></td>
                                    <td>₱<?php echo number_format($row['labor_cost'], 2); ?></td>
                                    <td>₱<?php echo number_format($row['tolls_cost'], 2); ?></td>
                                    <td>₱<?php echo number_format($row['other_cost'], 2); ?></td>
                                    <td><strong>₱<?php echo number_format($row['total_cost'], 2); ?></strong></td>
                                    <td class="action-buttons">
                                        <a href="../dtpm/trip_history.php?search=<?php echo urlencode($row['trip_code']); ?>"
                                            class="btn btn-info btn-sm">View Trip</a>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="8">No per-trip cost data found.</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function () {
        });
    </script>
    <script src="../../assets/js/dark_mode_handler.js" defer></script>
</body>

</html>