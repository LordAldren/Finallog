<?php
session_start();
if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true) {
    header("location: ../../auth/login.php");
    exit;
}

// RBAC check - Admin at Staff lang ang pwedeng pumasok dito
if (!in_array($_SESSION['role'], ['admin', 'staff'])) {
    if ($_SESSION['role'] === 'driver') {
        header("location: ../mfc/mobile_app.php");
    } else {
        header("location: ../../landpage.php");
    }
    exit;
}

require_once '../../config/db_connect.php';
// ... existing code ...

$message = '';

// Handle Update Route Adherence
if ($_SESSION['role'] === 'admin' && $_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['update_adherence'])) {
    $trip_id = $_POST['trip_id'];
    $score = $_POST['adherence_score'];
    $deviations = $_POST['deviations'];

    $stmt = $conn->prepare("UPDATE trips SET route_adherence_score = ?, route_deviations = ? WHERE id = ?");
    $stmt->bind_param("dii", $score, $deviations, $trip_id);
    if ($stmt->execute()) {
        $message = "<div class='message-banner success'>Route adherence for Trip ID $trip_id updated successfully.</div>";
    } else {
        $message = "<div class='message-banner error'>Failed to update route adherence.</div>";
    }
    $stmt->close();
}


// Fetch trip data for display
$trips_result = $conn->query("
    SELECT t.id, t.trip_code, t.destination, t.status, t.route_adherence_score, t.route_deviations, d.name as driver_name
    FROM trips t
    JOIN drivers d ON t.driver_id = d.id
    WHERE t.status IN ('Completed', 'En Route')
    ORDER BY t.pickup_time DESC
");

?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Route Adherence | DTPM</title>
    <link rel="stylesheet" href="../../assets/css/style.css">
</head>

<body>
    <?php include '../../includes/sidebar.php'; ?>

    <div class="content" id="mainContent">
        <div class="header">
            <div class="hamburger" id="hamburger">☰</div>
            <div>
                <h1>Route Adherence Monitoring</h1>
            </div>
            <div class="theme-toggle-container">
                <span class="theme-label">Dark Mode</span>
                <label class="theme-switch"><input type="checkbox" id="themeToggle"><span class="slider"></span></label>
            </div>
        </div>

        <?php echo $message; ?>

        <div class="card">
            <h3>Trip Performance</h3>
            <p>Monitor how well drivers adhere to their planned routes. Scores can be updated manually by an
                administrator after reviewing trip logs.</p>
            <div class="table-section" style="margin-top: 1.5rem;">
                <table>
                    <thead>
                        <tr>
                            <th>Trip Code</th>
                            <th>Driver</th>
                            <th>Destination</th>
                            <th>Adherence Score</th>
                            <th>Deviations</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($trips_result->num_rows > 0): ?>
                            <?php while ($trip = $trips_result->fetch_assoc()):
                                $score = $trip['route_adherence_score'];
                                $score_class = 'score-medium';
                                if ($score >= 95)
                                    $score_class = 'score-high';
                                if ($score < 80)
                                    $score_class = 'score-low';
                                ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($trip['trip_code']); ?></td>
                                    <td><?php echo htmlspecialchars($trip['driver_name']); ?></td>
                                    <td><?php echo htmlspecialchars($trip['destination']); ?></td>
                                    <td><strong
                                            class="<?php echo $score_class; ?>"><?php echo number_format($score, 2); ?>%</strong>
                                    </td>
                                    <td><?php echo htmlspecialchars($trip['route_deviations']); ?></td>
                                    <td>
                                        <button class="btn btn-warning btn-sm editAdherenceBtn"
                                            data-trip-id="<?php echo $trip['id']; ?>"
                                            data-score="<?php echo $trip['route_adherence_score']; ?>"
                                            data-deviations="<?php echo $trip['route_deviations']; ?>">
                                            Update
                                        </button>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="6">No completed or en-route trips to display.</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div id="adherenceModal" class="modal">
        <div class="modal-content">
            <span class="close-button">&times;</span>
            <h2>Update Route Adherence</h2>
            <form action="route_adherence.php" method="POST">
                <input type="hidden" id="trip_id" name="trip_id">
                <div class="form-group">
                    <label for="adherence_score">Adherence Score (%)</label>
                    <input type="number" step="0.01" max="100" min="0" id="adherence_score" name="adherence_score"
                        class="form-control" required>
                </div>
                <div class="form-group">
                    <label for="deviations">Number of Deviations</label>
                    <input type="number" min="0" id="deviations" name="deviations" class="form-control" required>
                </div>
                <div class="form-actions">
                    <button type="button" class="btn btn-secondary cancelBtn">Cancel</button>
                    <button type="submit" name="update_adherence" class="btn btn-primary">Save Update</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function () {
            // Sidebar logic is now handled by central sidebar.js

            const adherenceModal = document.getElementById('adherenceModal');
            document.querySelectorAll('.editAdherenceBtn').forEach(btn => {
                btn.addEventListener('click', () => {
                    adherenceModal.querySelector('#trip_id').value = btn.dataset.tripId;
                    adherenceModal.querySelector('#adherence_score').value = btn.dataset.score;
                    adherenceModal.querySelector('#deviations').value = btn.dataset.deviations;
                    adherenceModal.style.display = 'block';
                });
            });
            adherenceModal.querySelector('.close-button').onclick = () => { adherenceModal.style.display = 'none'; };
            adherenceModal.querySelector('.cancelBtn').onclick = () => { adherenceModal.style.display = 'none'; };
        });
    </script>
    <script src="../../assets/js/dark_mode_handler.js" defer></script>
</body>

</html>