<?php
session_start();
require_once '../../config/db_connect.php';
require_once '../../includes/sidebar.php';

$message = "";

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $user_id = $_SESSION['user_id'];
    $vehicle_id = $_POST['vehicle_id']; // User might select, or it might be null if admin assigns
    $start_datetime = $_POST['start_datetime'];
    $end_datetime = $_POST['end_datetime'];
    $destination = $_POST['destination'];
    $purpose = $_POST['purpose'];

    // Validation
    if (empty($start_datetime) || empty($end_datetime) || empty($destination)) {
        $message = "Please fill in all required fields.";
    } else {
        // Prepare Statement - Default Status is 'Pending'
        // driver_acceptance defaults to 'Pending' via DB schema
        $sql = "INSERT INTO reservations (user_id, vehicle_id, start_datetime, end_datetime, destination, purpose, status) 
                VALUES (?, ?, ?, ?, ?, ?, 'Pending')";

        $stmt = $conn->prepare($sql);
        // Assuming vehicle_id can be NULL if user doesn't pick one
        $stmt->bind_param("iissss", $user_id, $vehicle_id, $start_datetime, $end_datetime, $destination, $purpose);

        if ($stmt->execute()) {
            $message = "<div class='alert alert-success'>Reservation submitted successfully! Waiting for dispatch approval.</div>";
        } else {
            $message = "<div class='alert alert-danger'>Error: " . $conn->error . "</div>";
        }
        $stmt->close();
    }
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Book a Trip | SLATE</title>
    <link rel="stylesheet" href="../../assets/css/style.css">
</head>

<body>
    <?php include '../../includes/sidebar.php'; ?>

    <div class="content" id="mainContent">
        <div class="header">
            <div class="hamburger" id="hamburger">☰</div>
            <div>
                <h1>Request a New Trip</h1>
            </div>
            <div class="theme-toggle-container">
                <span class="theme-label">Dark Mode</span>
                <label class="theme-switch"><input type="checkbox" id="themeToggle"><span class="slider"></span></label>
            </div>
        </div>

        <?php echo $message; ?>

        <div class="card" style="max-width: 600px; margin: 0 auto;">
            <form action="book_trip.php" method="POST">
                <!-- Vehicle Selection (Optional - Admin can override) -->
                <div class="form-group">
                    <label for="vehicle">Preferred Vehicle (Optional)</label>
                    <select name="vehicle_id" class="form-control">
                        <option value="">-- Let Dispatch Decide --</option>
                        <?php
                        // Fetch active vehicles from DB (using status 'Active' or 'Idle' as available)
                        $v_sql = "SELECT id, plate_number, model, type FROM vehicles WHERE status IN ('Active', 'Idle') ORDER BY type ASC";
                        $v_res = $conn->query($v_sql);
                        if ($v_res && $v_res->num_rows > 0) {
                            while ($row = $v_res->fetch_assoc()) {
                                echo "<option value='" . $row['id'] . "'>[" . $row['type'] . "] " . $row['model'] . " - " . $row['plate_number'] . "</option>";
                            }
                        }
                        ?>
                    </select>
                </div>

                <div class="form-group">
                    <label>Destination Address</label>
                    <input type="text" name="destination" class="form-control" required
                        placeholder="Enter complete delivery/destination address">
                </div>

                <div class="form-group">
                    <label>Purpose / Load Description</label>
                    <textarea name="purpose" class="form-control" rows="3"
                        placeholder="Description of goods or purpose of trip"></textarea>
                </div>

                <div class="form-group">
                    <label>Requested Pickup Date & Time</label>
                    <input type="datetime-local" name="start_datetime" class="form-control" required>
                </div>

                <div class="form-group">
                    <label>Expected Delivery Date & Time</label>
                    <input type="datetime-local" name="end_datetime" class="form-control" required>
                </div>

                <div class="form-actions">
                    <button type="submit" class="btn btn-primary" style="width: 100%;">Submit Reservation
                        Request</button>
                </div>
            </form>
        </div>
    </div>

    <script src="../../assets/js/dark_mode_handler.js" defer></script>
</body>

</html>