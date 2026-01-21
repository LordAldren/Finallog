<?php
session_start();
// Security Check: Client lang ang pwede dito
if (!isset($_SESSION["loggedin"]) || $_SESSION["role"] !== 'client') {
    header("location: ../../auth/login.php");
    exit;
}

require_once '../../config/db_connect.php';
$client_id = $_SESSION['id'];

// Kunin ang statistics
$pending_reqs = $conn->query("SELECT COUNT(*) as cnt FROM reservations WHERE reserved_by_user_id = $client_id AND status = 'Pending'")->fetch_assoc()['cnt'];
$active_trips = $conn->query("SELECT COUNT(*) as cnt FROM trips t JOIN reservations r ON t.reservation_id = r.id WHERE r.reserved_by_user_id = $client_id AND t.status IN ('En Route', 'Scheduled')")->fetch_assoc()['cnt'];
$completed_trips = $conn->query("SELECT COUNT(*) as cnt FROM trips t JOIN reservations r ON t.reservation_id = r.id WHERE r.reserved_by_user_id = $client_id AND t.status = 'Completed'")->fetch_assoc()['cnt'];

// Kunin ang latest bookings
$bookings = $conn->query("SELECT * FROM reservations WHERE reserved_by_user_id = $client_id ORDER BY created_at DESC LIMIT 5");
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Client Portal | SLATE</title>
    <link rel="stylesheet" href="../../assets/css/style.css">
</head>

<body>
    <?php include '../../includes/sidebar.php'; ?>

    <!-- UPDATE: Changed class 'content' to 'main-content' to match CSS conventions -->
    <div class="main-content" id="mainContent">
        <div class="header">
            <div class="hamburger" id="hamburger">☰</div>
            <div>
                <h1>Client Portal Dashboard</h1>
            </div>
            <div class="theme-toggle-container">
                <span class="theme-label">Dark Mode</span>
                <label class="theme-switch"><input type="checkbox" id="themeToggle"><span class="slider"></span></label>
            </div>
        </div>

        <div class="dashboard-stats"
            style="display: grid; grid-template-columns: repeat(3, 1fr); gap: 1rem; margin-bottom: 2rem;">
            <div class="card" style="text-align: center;">
                <h3>Pending Requests</h3>
                <h1 style="color: var(--warning-color);"><?php echo $pending_reqs; ?></h1>
            </div>
            <div class="card" style="text-align: center;">
                <h3>Scheduled / En Route</h3>
                <h1 style="color: var(--primary-color);"><?php echo $active_trips; ?></h1>
            </div>
            <div class="card" style="text-align: center;">
                <h3>Completed Trips</h3>
                <h1 style="color: var(--success-color);"><?php echo $completed_trips; ?></h1>
            </div>
        </div>

        <div class="card">
            <div style="display:flex; justify-content:space-between; align-items:center;">
                <h3>Your Bookings</h3>
                <a href="book_trip.php" class="btn btn-primary">+ New Booking</a>
            </div>
            <div class="table-section" style="margin-top: 1rem;">
                <table>
                    <thead>
                        <tr>
                            <th>Code</th>
                            <th>Trip Date</th>
                            <th>Purpose</th>
                            <th>Status</th>
                            <th>Location</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($bookings->num_rows > 0):
                            while ($row = $bookings->fetch_assoc()): ?>
                                <tr>
                                    <td><?php echo $row['reservation_code']; ?></td>
                                    <td><?php echo date('M d, Y', strtotime($row['reservation_date'])); ?></td>
                                    <td><?php echo htmlspecialchars($row['purpose']); ?></td>
                                    <td><span
                                            class="status-badge status-<?php echo strtolower($row['status']); ?>"><?php echo $row['status']; ?></span>
                                    </td>
                                    <td><?php echo htmlspecialchars($row['destination_address']); ?></td>
                                </tr>
                            <?php endwhile; else: ?>
                            <tr>
                                <td colspan="5">No bookings recorded yet.</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    <script src="../../assets/js/dark_mode_handler.js"></script>
</body>

</html>