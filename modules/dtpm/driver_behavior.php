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
    header('Content-Disposition: attachment; filename=driver_behavior_logs_' . date('Y-m-d') . '.csv');
    $output = fopen('php://output', 'w');
    fputcsv($output, ['Log ID', 'Date', 'Driver Name', 'Trip Code', 'Overspeeding Incidents', 'Harsh Braking Incidents', 'Idle Time (Mins)', 'Notes']);

    $result = $conn->query("
        SELECT dbl.*, d.name as driver_name, t.trip_code 
        FROM driver_behavior_logs dbl
        JOIN drivers d ON dbl.driver_id = d.id
        LEFT JOIN trips t ON dbl.trip_id = t.id
        ORDER BY dbl.log_date DESC
    ");

    if ($result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            fputcsv($output, [
                $row['id'],
                $row['log_date'],
                $row['driver_name'],
                $row['trip_code'] ?? 'N/A',
                $row['overspeeding_count'],
                $row['harsh_braking_count'],
                $row['idle_duration_minutes'],
                $row['notes']
            ]);
        }
    }
    fclose($output);
    exit;
}
// --- WAKAS NG CSV DOWNLOAD LOGIC ---

// --- Fetch Data ---
$behavior_logs = $conn->query("
    SELECT dbl.*, d.name as driver_name, t.trip_code 
    FROM driver_behavior_logs dbl
    JOIN drivers d ON dbl.driver_id = d.id
    LEFT JOIN trips t ON dbl.trip_id = t.id
    ORDER BY dbl.log_date DESC
");

?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Driver Behavior | DTPM</title>
    <link rel="stylesheet" href="../../assets/css/style.css">
</head>

<body>
    <?php include '../../includes/sidebar.php'; ?>

    <div class="content" id="mainContent">
        <div class="header">
            <div class="hamburger" id="hamburger">☰</div>
            <div>
                <h1>Driver Behavior Report</h1>
            </div>
            <div class="theme-toggle-container">
                <span class="theme-label">Dark Mode</span>
                <label class="theme-switch"><input type="checkbox" id="themeToggle"><span class="slider"></span></label>
            </div>
        </div>

        <?php echo $message; ?>

        <div class="card">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1rem;">
                <h3>Behavior Log History</h3>
                <a href="driver_behavior.php?download_csv=true" class="btn btn-success">Download CSV</a>
            </div>
            <p style="margin-bottom: 1.5rem; color: var(--text-muted-dark);">This report shows all recorded incidents.
                Overspeeding is logged automatically from the driver's app, while other incidents are entered by an
                administrator based on verified reports.</p>
            <div class="table-section">
                <table>
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th>Driver</th>
                            <th>Trip Code</th>
                            <th>Overspeeding</th>
                            <th>Harsh Braking</th>
                            <th>Idle Time</th>
                            <th>Notes</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($behavior_logs->num_rows > 0):
                            mysqli_data_seek($behavior_logs, 0); ?>
                            <?php while ($row = $behavior_logs->fetch_assoc()): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($row['log_date']); ?></td>
                                    <td><?php echo htmlspecialchars($row['driver_name']); ?></td>
                                    <td><?php echo htmlspecialchars($row['trip_code'] ?? 'N/A'); ?></td>
                                    <td><?php echo htmlspecialchars($row['overspeeding_count']); ?></td>
                                    <td><?php echo htmlspecialchars($row['harsh_braking_count']); ?></td>
                                    <td><?php echo htmlspecialchars($row['idle_duration_minutes']); ?> mins</td>
                                    <td><?php echo htmlspecialchars($row['notes']); ?></td>
                                    <td class="action-buttons">
                                        <?php if (!empty($row['trip_code'])): ?>
                                            <a href="trip_history.php?search=<?php echo urlencode($row['trip_code']); ?>"
                                                class="btn btn-info btn-sm">View Trip</a>
                                        <?php else: ?>
                                            <span>N/A</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="8">No behavior logs found.</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <script src="../../assets/js/dark_mode_handler.js" defer></script>
</body>

</html>