<?php
session_start();
if (!isset($_SESSION["loggedin"]) || $_SESSION["role"] !== 'client') {
    header("location: ../../auth/login.php");
    exit;
}

require_once '../../config/db_connect.php';
$client_user_id = $_SESSION['id'];
$message = '';

// Handle Submission
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['submit_feedback'])) {
    $trip_id = $_POST['trip_id'];
    $rating = $_POST['rating'];
    $comments = $_POST['comments'];

    $stmt = $conn->prepare("INSERT INTO client_feedback (trip_id, client_user_id, rating, comments) VALUES (?, ?, ?, ?)");
    $stmt->bind_param("iiis", $trip_id, $client_user_id, $rating, $comments);

    if ($stmt->execute()) {
        $message = "<div class='message-banner success'>Thank you for your feedback!</div>";
    } else {
        $message = "<div class='message-banner error'>Error: " . $conn->error . "</div>";
    }
}

// Get completed trips with no feedback
$pending_feedback = $conn->query("
    SELECT t.id as trip_id, t.trip_code, t.destination, t.actual_arrival_time, d.name as driver_name
    FROM trips t
    JOIN reservations r ON t.reservation_id = r.id
    JOIN drivers d ON t.driver_id = d.id
    LEFT JOIN client_feedback cf ON t.id = cf.trip_id
    WHERE r.reserved_by_user_id = $client_user_id 
    AND t.status = 'Completed'
    AND cf.id IS NULL
");
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Client Feedback | SLATE</title>
    <link rel="stylesheet" href="../../assets/css/style.css">
</head>

<body>
    <?php include '../../includes/sidebar.php'; ?>

    <div class="content" id="mainContent">
        <div class="header">
            <div class="hamburger" id="hamburger">☰</div>
            <div>
                <h1>Give Feedback</h1>
            </div>
            <div class="theme-toggle-container">
                <span class="theme-label">Dark Mode</span>
                <label class="theme-switch"><input type="checkbox" id="themeToggle"><span class="slider"></span></label>
            </div>
        </div>

        <?php echo $message; ?>

        <div class="card">
            <h3>Trips Needing Rating</h3>
            <div class="table-section">
                <table>
                    <thead>
                        <tr>
                            <th>Trip Code</th>
                            <th>Driver</th>
                            <th>Completed On</th>
                            <th>Destination</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($pending_feedback->num_rows > 0):
                            while ($row = $pending_feedback->fetch_assoc()): ?>
                                <tr>
                                    <td><?php echo $row['trip_code']; ?></td>
                                    <td><?php echo $row['driver_name']; ?></td>
                                    <td><?php echo date('M d, Y h:i A', strtotime($row['actual_arrival_time'])); ?></td>
                                    <td><?php echo $row['destination']; ?></td>
                                    <td>
                                        <button class="btn btn-warning btn-sm"
                                            onclick="openFeedbackModal(<?php echo $row['trip_id']; ?>, '<?php echo $row['trip_code']; ?>')">Rate</button>
                                    </td>
                                </tr>
                            <?php endwhile; else: ?>
                            <tr>
                                <td colspan="5">No pending feedback.</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Feedback Modal -->
        <div id="feedbackModal" class="modal">
            <div class="modal-content">
                <span class="close-button"
                    onclick="document.getElementById('feedbackModal').style.display='none'">&times;</span>
                <h2>Feedback for Trip <span id="modalTripCode"></span></h2>
                <form action="feedback.php" method="POST">
                    <input type="hidden" name="trip_id" id="modalTripId">
                    <div class="form-group">
                        <label>Rating (1 - Poor, 5 - Excellent)</label>
                        <select name="rating" class="form-control">
                            <option value="5">⭐⭐⭐⭐⭐ (5 - Excellent)</option>
                            <option value="4">⭐⭐⭐⭐ (4 - Good)</option>
                            <option value="3">⭐⭐⭐ (3 - Average)</option>
                            <option value="2">⭐⭐ (2 - Poor)</option>
                            <option value="1">⭐ (1 - Terrible)</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Comments</label>
                        <textarea name="comments" class="form-control" rows="3"
                            placeholder="How was the driver? Was the trip safe?"></textarea>
                    </div>
                    <div class="form-actions">
                        <button type="submit" name="submit_feedback" class="btn btn-primary">Submit</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script>
        function openFeedbackModal(id, code) {
            document.getElementById('modalTripId').value = id;
            document.getElementById('modalTripCode').innerText = code;
            document.getElementById('feedbackModal').style.display = 'block';
        }
    </script>
    <script src="../../assets/js/dark_mode_handler.js"></script>
</body>

</html>