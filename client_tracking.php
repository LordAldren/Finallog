<?php
require_once 'config/db_connect.php';
$trip_details = null;
$error_message = '';

if ($_SERVER["REQUEST_METHOD"] == "GET" && isset($_GET['trip_code'])) {
    $trip_code = trim($_GET['trip_code']);
    
    if (!empty($trip_code)) {
        $stmt = $conn->prepare("
            SELECT t.trip_code, t.client_name, t.destination, t.status, t.pickup_time, t.arrival_status, v.type as vehicle_type, d.name as driver_name, t.current_location 
            FROM trips t 
            LEFT JOIN vehicles v ON t.vehicle_id = v.id 
            LEFT JOIN drivers d ON t.driver_id = d.id 
            WHERE t.trip_code = ?
        ");
        $stmt->bind_param("s", $trip_code);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows > 0) {
            $trip_details = $result->fetch_assoc();
        } else {
            $error_message = "Trip Code not found. Please check and try again.";
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
    <title>Track Your Shipment | SLATE</title>
    <link rel="stylesheet" href="assets/css/login-style.css">
    <style>
        .tracking-container {
            max-width: 600px;
            margin: 2rem auto;
            padding: 2rem;
            background: rgba(31, 42, 56, 0.95);
            border-radius: 0.75rem;
            box-shadow: 0 0.625rem 1.875rem rgba(0, 0, 0, 0.3);
            border: 1px solid rgba(255, 255, 255, 0.1);
            color: white;
        }
        .tracking-header { text-align: center; margin-bottom: 2rem; }
        .tracking-header img { width: 80px; margin-bottom: 1rem; }
        .tracking-form { display: flex; gap: 0.5rem; margin-bottom: 2rem; }
        .tracking-result { background: rgba(255, 255, 255, 0.05); padding: 1.5rem; border-radius: 0.5rem; border: 1px solid rgba(255, 255, 255, 0.1); }
        .status-timeline { margin-top: 1.5rem; padding-top: 1.5rem; border-top: 1px solid rgba(255, 255, 255, 0.1); }
        .detail-row { display: flex; justify-content: space-between; margin-bottom: 0.5rem; font-size: 0.95rem; }
        .detail-label { color: #AAB3C2; }
        .status-badge-large {
            display: inline-block;
            padding: 0.5rem 1rem;
            border-radius: 2rem;
            font-weight: 600;
            font-size: 1.1rem;
            margin-bottom: 1rem;
            text-transform: uppercase;
            width: 100%;
            text-align: center;
        }
        .status-en-route { background-color: #3B82F6; color: white; }
        .status-completed { background-color: #10B981; color: white; }
        .status-scheduled { background-color: #F59E0B; color: white; }
        .status-cancelled { background-color: #EF4444; color: white; }
        
        .back-home { text-align: center; margin-top: 2rem; }
        .back-home a { color: #4A6CF7; text-decoration: none; }
    </style>
</head>
<body class="login-page-body" style="justify-content: flex-start; padding-top: 5vh;">

    <div class="tracking-container">
        <div class="tracking-header">
            <img src="assets/images/logo.png" alt="SLATE Logo">
            <h2>Track Your Shipment</h2>
            <p style="color: #AAB3C2;">Enter your Trip Code to see the current status.</p>
        </div>

        <form action="client_tracking.php" method="GET" class="tracking-form">
            <input type="text" name="trip_code" placeholder="e.g., T20251028001" class="form-control" style="flex-grow: 1; background: rgba(255,255,255,0.1); border: 1px solid rgba(255,255,255,0.2); color: white; padding: 0.75rem; border-radius: 0.375rem;" value="<?php echo isset($_GET['trip_code']) ? htmlspecialchars($_GET['trip_code']) : ''; ?>" required>
            <button type="submit" style="background: #4A6CF7; color: white; border: none; padding: 0 1.5rem; border-radius: 0.375rem; cursor: pointer; font-weight: 600;">Track</button>
        </form>

        <?php if ($error_message): ?>
            <div style="background: rgba(239, 68, 68, 0.2); border: 1px solid #EF4444; color: #FECACA; padding: 1rem; border-radius: 0.375rem; text-align: center; margin-bottom: 1rem;">
                <?php echo $error_message; ?>
            </div>
        <?php endif; ?>

        <?php if ($trip_details): ?>
            <div class="tracking-result">
                <div class="status-badge-large status-<?php echo strtolower(str_replace(' ', '-', $trip_details['status'])); ?>">
                    <?php echo htmlspecialchars($trip_details['status']); ?>
                </div>

                <div class="detail-row">
                    <span class="detail-label">Trip Code:</span>
                    <strong><?php echo htmlspecialchars($trip_details['trip_code']); ?></strong>
                </div>
                <div class="detail-row">
                    <span class="detail-label">Client:</span>
                    <span><?php echo htmlspecialchars($trip_details['client_name']); ?></span>
                </div>
                <div class="detail-row">
                    <span class="detail-label">Destination:</span>
                    <span><?php echo htmlspecialchars($trip_details['destination']); ?></span>
                </div>
                <div class="detail-row">
                    <span class="detail-label">Pickup Date:</span>
                    <span><?php echo date('F d, Y h:i A', strtotime($trip_details['pickup_time'])); ?></span>
                </div>
                <hr style="border-color: rgba(255,255,255,0.1); margin: 1rem 0;">
                <div class="detail-row">
                    <span class="detail-label">Vehicle Type:</span>
                    <span><?php echo htmlspecialchars($trip_details['vehicle_type']); ?></span>
                </div>
                <div class="detail-row">
                    <span class="detail-label">Driver:</span>
                    <span><?php echo htmlspecialchars($trip_details['driver_name']); ?></span>
                </div>
                
                <?php if ($trip_details['status'] == 'En Route'): ?>
                <div style="margin-top: 1rem; background: rgba(59, 130, 246, 0.2); padding: 0.75rem; border-radius: 0.375rem; border: 1px solid rgba(59, 130, 246, 0.4);">
                    <small style="color: #93C5FD; display: block; margin-bottom: 0.25rem;">Current Status Update:</small>
                    <?php 
                        // Simple simulation if no live location data
                        echo !empty($trip_details['current_location']) ? htmlspecialchars($trip_details['current_location']) : "Driver is currently on the way to the destination."; 
                    ?>
                </div>
                <?php endif; ?>

                <?php if ($trip_details['status'] == 'Completed'): ?>
                <div class="status-timeline">
                    <div class="detail-row">
                        <span class="detail-label">Delivery Status:</span>
                        <span style="color: <?php echo ($trip_details['arrival_status'] == 'Late') ? '#F87171' : '#34D399'; ?>; font-weight: bold;">
                            <?php echo htmlspecialchars($trip_details['arrival_status'] ?? 'Completed'); ?>
                        </span>
                    </div>
                    <p style="text-align: center; color: #AAB3C2; margin-top: 1rem; font-size: 0.9rem;">Thank you for trusting SLATE Logistics!</p>
                </div>
                <?php endif; ?>
            </div>
        <?php endif; ?>

        <div class="back-home">
            <a href="auth/login.php">&larr; Back to Employee Login</a>
        </div>
    </div>

</body>
</html>