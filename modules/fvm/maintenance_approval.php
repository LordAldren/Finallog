<?php
session_start();
if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true) {
    header("location: ../../auth/login.php");
    exit;
}
if ($_SESSION['role'] === 'driver') {
    header("location: ../mfc/mobile_app.php");
    exit;
}

require_once '../../config/db_connect.php';
$message = '';

// --- PANG-HANDLE NG CSV DOWNLOAD ---
if (isset($_GET['download_csv'])) {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename=maintenance_requests_' . date('Y-m-d') . '.csv');
    $output = fopen('php://output', 'w');
    fputcsv($output, ['ID', 'Vehicle', 'Arrival Date', 'Date of Return', 'Status']);

    $result = $conn->query("SELECT m.*, v.type, v.model FROM maintenance_approvals m JOIN vehicles v ON m.vehicle_id = v.id ORDER BY m.arrival_date DESC");
    if ($result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            fputcsv($output, [
                $row['id'],
                $row['type'] . ' ' . $row['model'],
                $row['arrival_date'],
                $row['date_of_return'] ?? 'N/A',
                $row['status']
            ]);
        }
    }
    fclose($output);
    exit;
}
// --- WAKAS NG CSV DOWNLOAD LOGIC ---


if ($_SERVER["REQUEST_METHOD"] == "POST") {
    if (isset($_POST['update_maintenance_status'])) {
        $maintenance_id = $_POST['maintenance_id_status'];
        $new_status = $_POST['new_status'];
        $allowed_statuses = ['Approved', 'On-Queue', 'In Progress', 'Completed', 'Rejected'];

        if (in_array($new_status, $allowed_statuses)) {
            // Get vehicle_id from the maintenance request to update vehicle status as well
            $vehicle_id_query = $conn->prepare("SELECT vehicle_id FROM maintenance_approvals WHERE id = ?");
            $vehicle_id_query->bind_param("i", $maintenance_id);
            $vehicle_id_query->execute();
            $vehicle_id_result = $vehicle_id_query->get_result();

            if ($row = $vehicle_id_result->fetch_assoc()) {
                $vehicle_id = $row['vehicle_id'];

                $conn->begin_transaction();
                try {
                    // 1. Update maintenance approval status
                    $stmt = $conn->prepare("UPDATE maintenance_approvals SET status = ? WHERE id = ?");
                    $stmt->bind_param("si", $new_status, $maintenance_id);
                    $stmt->execute();

                    // 2. Update vehicle status based on maintenance status
                    $vehicle_status_update_stmt = null;
                    if (in_array($new_status, ['Approved', 'On-Queue', 'In Progress'])) {
                        // Vehicle is now unavailable
                        $vehicle_status_update_stmt = $conn->prepare("UPDATE vehicles SET status = 'Maintenance' WHERE id = ?");
                    } elseif ($new_status === 'Completed' || $new_status === 'Rejected') {
                        // When maintenance is done or rejected, vehicle becomes available again (set to Active/Idle)
                        $vehicle_status_update_stmt = $conn->prepare("UPDATE vehicles SET status = 'Active' WHERE id = ?");
                    }

                    if ($vehicle_status_update_stmt) {
                        $vehicle_status_update_stmt->bind_param("i", $vehicle_id);
                        $vehicle_status_update_stmt->execute();
                        $vehicle_status_update_stmt->close();
                    }

                    $conn->commit();
                    $message = "<div class='message-banner success'>Maintenance and vehicle status updated to '$new_status'.</div>";

                } catch (mysqli_sql_exception $exception) {
                    $conn->rollback();
                    $message = "<div class='message-banner error'>Transaction Failed: " . $exception->getMessage() . "</div>";
                }
                $stmt->close();
            } else {
                $message = "<div class='message-banner error'>Could not find the associated vehicle.</div>";
            }
            $vehicle_id_query->close();
        } else {
            $message = "<div class='message-banner error'>Invalid status update attempted.</div>";
        }
    }
}


$maintenance_result = $conn->query("SELECT m.*, v.type, v.model FROM maintenance_approvals m JOIN vehicles v ON m.vehicle_id = v.id ORDER BY m.arrival_date DESC");
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Maintenance Approval | FVM</title>
    <link rel="stylesheet" href="../../assets/css/style.css">
</head>

<body>
    <?php include '../../includes/sidebar.php'; ?>

    <div class="content" id="mainContent">
        <div class="header">
            <div class="hamburger" id="hamburger">☰</div>
            <div>
                <h1>Maintenance Approval</h1>
            </div>
            <div class="theme-toggle-container">
                <span class="theme-label">Dark Mode</span>
                <label class="theme-switch"><input type="checkbox" id="themeToggle"><span class="slider"></span></label>
            </div>
        </div>

        <?php echo $message; ?>

        <div class="card" id="maintenance-approval">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1rem;">
                <h3>Pending and Ongoing Maintenance</h3>
                <a href="maintenance_approval.php?download_csv=true" class="btn btn-success">Download CSV</a>
            </div>
            <div class="table-section">
                <table>
                    <thead>
                        <tr>
                            <th>VEHICLE NAME</th>
                            <th>ARRIVAL DATE</th>
                            <th>DATE OF RETURN</th>
                            <th>STATUS</th>
                            <th>ACTIONS</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($maintenance_result->num_rows > 0): ?>
                            <?php while ($row = $maintenance_result->fetch_assoc()): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($row['type'] . ' ' . $row['model']); ?></td>
                                    <td><?php echo htmlspecialchars($row['arrival_date']); ?></td>
                                    <td><?php echo htmlspecialchars($row['date_of_return'] ?? 'N/A'); ?></td>
                                    <td><span
                                            class="status-badge status-<?php echo strtolower(str_replace(' ', '-', $row['status'])); ?>"><?php echo htmlspecialchars($row['status']); ?></span>
                                    </td>
                                    <td class="action-buttons">
                                        <?php if ($row['status'] == 'Pending'): ?>
                                            <form action="maintenance_approval.php" method="POST" style="display: inline;">
                                                <input type="hidden" name="maintenance_id_status" value="<?php echo $row['id']; ?>">
                                                <input type="hidden" name="new_status" value="Approved">
                                                <button type="submit" name="update_maintenance_status"
                                                    class="btn btn-success btn-sm">Approve</button>
                                            </form>
                                            <form action="maintenance_approval.php" method="POST" style="display: inline;">
                                                <input type="hidden" name="maintenance_id_status" value="<?php echo $row['id']; ?>">
                                                <input type="hidden" name="new_status" value="On-Queue">
                                                <button type="submit" name="update_maintenance_status"
                                                    class="btn btn-warning btn-sm">On-Queue</button>
                                            </form>
                                        <?php elseif (in_array($row['status'], ['Approved', 'In Progress', 'On-Queue'])): ?>
                                            <form action="maintenance_approval.php" method="POST" style="display: inline;">
                                                <input type="hidden" name="maintenance_id_status" value="<?php echo $row['id']; ?>">
                                                <input type="hidden" name="new_status" value="Completed">
                                                <button type="submit" name="update_maintenance_status"
                                                    class="btn btn-primary btn-sm">Mark as Done</button>
                                            </form>
                                        <?php else: ?>
                                            <span>No actions available</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="5">No maintenance requests found.</td>
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