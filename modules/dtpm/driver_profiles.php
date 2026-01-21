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
    header('Content-Disposition: attachment; filename=driver_profiles_' . date('Y-m-d') . '.csv');
    $output = fopen('php://output', 'w');
    fputcsv($output, ['ID', 'Name', 'License Number', 'License Expiry', 'Contact Number', 'Date Joined', 'Status', 'Rating', 'Total Trips', 'Avg Adherence Score']);

    $result = $conn->query("
        SELECT d.*, COUNT(t.id) as total_trips, AVG(t.route_adherence_score) as avg_adherence_score
        FROM drivers d
        LEFT JOIN trips t ON d.id = t.driver_id AND t.status = 'Completed'
        WHERE d.status != 'Pending'
        GROUP BY d.id
        ORDER BY d.name ASC
    ");

    if ($result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            fputcsv($output, [
                $row['id'],
                $row['name'],
                $row['license_number'],
                $row['license_expiry_date'],
                $row['contact_number'],
                $row['date_joined'],
                $row['status'],
                $row['rating'],
                $row['total_trips'],
                number_format((float) $row['avg_adherence_score'], 2)
            ]);
        }
    }
    fclose($output);
    exit;
}
// --- WAKAS NG CSV DOWNLOAD LOGIC ---


// Handle Driver Approval/Rejection
if ($_SESSION['role'] === 'admin' && $_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['update_driver_status'])) {
    $driver_id_to_update = $_POST['driver_id_to_update'];
    $new_status = $_POST['new_status']; // 'Active' or 'Rejected'

    if ($new_status === 'Active') {
        $stmt = $conn->prepare("UPDATE drivers SET status = 'Active' WHERE id = ?");
        $stmt->bind_param("i", $driver_id_to_update);
        if ($stmt->execute()) {
            $message = "<div class='message-banner success'>Driver approved and is now active.</div>";
        } else {
            $message = "<div class='message-banner error'>Error approving driver.</div>";
        }
        $stmt->close();
    } elseif ($new_status === 'Rejected') {
        // Instead of deleting, we now set the status to Rejected
        $stmt = $conn->prepare("UPDATE drivers SET status = 'Rejected' WHERE id = ?");
        $stmt->bind_param("i", $driver_id_to_update);
        if ($stmt->execute()) {
            $message = "<div class='message-banner success'>Driver registration has been rejected.</div>";
        } else {
            $message = "<div class='message-banner error'>Error rejecting driver.</div>";
        }
        $stmt->close();
    }
}


// Handle Add/Edit Driver
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['save_driver'])) {
    $id = $_POST['driver_id'];
    $name = $_POST['name'];
    $license_number = $_POST['license_number'];
    $license_expiry = !empty($_POST['license_expiry_date']) ? $_POST['license_expiry_date'] : NULL;
    $contact_number = $_POST['contact_number'];
    $date_joined = !empty($_POST['date_joined']) ? $_POST['date_joined'] : NULL;
    $status = $_POST['status'];
    $rating = $_POST['rating'];
    $user_id = !empty($_POST['user_id']) ? (int) $_POST['user_id'] : NULL;

    if (empty($id)) {
        $sql = "INSERT INTO drivers (name, license_number, license_expiry_date, contact_number, date_joined, status, rating, user_id) VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("ssssssdi", $name, $license_number, $license_expiry, $contact_number, $date_joined, $status, $rating, $user_id);
    } else {
        $sql = "UPDATE drivers SET name=?, license_number=?, license_expiry_date=?, contact_number=?, date_joined=?, status=?, rating=?, user_id=? WHERE id=?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("ssssssdii", $name, $license_number, $license_expiry, $contact_number, $date_joined, $status, $rating, $user_id, $id);
    }

    if ($stmt->execute()) {
        $message = "<div class='message-banner success'>Driver saved successfully!</div>";
    } else {
        $message = "<div class='message-banner error'>Error saving driver: " . $stmt->error . "</div>";
    }
    $stmt->close();
}


// Fetch Pending Drivers
$pending_drivers = $conn->query("SELECT d.id, d.name, d.license_number, u.email FROM drivers d JOIN users u ON d.user_id = u.id WHERE d.status = 'Pending' ORDER BY d.created_at ASC");

// Fetch Active Driver Data with all new details
$drivers_query = "
    SELECT
        d.*,
        v.type as vehicle_type,
        v.model as vehicle_model,
        v.tag_code,
        AVG(t.route_adherence_score) as avg_adherence_score,
        COUNT(t.id) as total_trips
    FROM
        drivers d
    LEFT JOIN
        vehicles v ON d.id = v.assigned_driver_id
    LEFT JOIN
        trips t ON d.id = t.driver_id AND t.status = 'Completed'
    WHERE
        d.status != 'Pending'
    GROUP BY
        d.id
    ORDER BY
        d.name ASC
";
$drivers = $conn->query($drivers_query);
$users = $conn->query("SELECT id, username FROM users WHERE role = 'driver'");

// Fetch Driver Behavior Data
$behavior_logs_query = $conn->query("
    SELECT dbl.*, d.name as driver_name
    FROM driver_behavior_logs dbl
    JOIN drivers d ON dbl.driver_id = d.id
    ORDER BY dbl.log_date DESC
");
$behavior_logs = [];
while ($row = $behavior_logs_query->fetch_assoc()) {
    $behavior_logs[$row['driver_id']][] = $row;
}
$behavior_logs_json = json_encode($behavior_logs);
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Driver Profiles | DTPM</title>
    <link rel="stylesheet" href="../../assets/css/style.css">
</head>

<body>
    <?php include '../../includes/sidebar.php'; ?>

    <div class="content" id="mainContent">
        <div class="header">
            <div class="hamburger" id="hamburger">☰</div>
            <div>
                <h1>Driver Profile Management</h1>
            </div>
            <div class="theme-toggle-container">
                <span class="theme-label">Dark Mode</span>
                <label class="theme-switch"><input type="checkbox" id="themeToggle"><span class="slider"></span></label>
            </div>
        </div>

        <?php echo $message; ?>

        <?php if ($_SESSION['role'] === 'admin' && $pending_drivers->num_rows > 0): ?>
            <div class="card">
                <h3>Pending Driver Registrations</h3>
                <div class="table-section">
                    <table>
                        <thead>
                            <tr>
                                <th>Name</th>
                                <th>License No.</th>
                                <th>Email</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php while ($row = $pending_drivers->fetch_assoc()): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($row['name']); ?></td>
                                    <td><?php echo htmlspecialchars($row['license_number']); ?></td>
                                    <td><?php echo htmlspecialchars($row['email']); ?></td>
                                    <td class="action-buttons">
                                        <form action="driver_profiles.php" method="POST" style="display: inline-block;">
                                            <input type="hidden" name="driver_id_to_update" value="<?php echo $row['id']; ?>">
                                            <input type="hidden" name="new_status" value="Active">
                                            <button type="submit" name="update_driver_status"
                                                class="btn btn-success btn-sm">Approve</button>
                                        </form>
                                        <form action="driver_profiles.php" method="POST" style="display: inline-block;">
                                            <input type="hidden" name="driver_id_to_update" value="<?php echo $row['id']; ?>">
                                            <input type="hidden" name="new_status" value="Rejected">
                                            <button type="submit" name="update_driver_status" class="btn btn-danger btn-sm"
                                                onclick="return confirm('Are you sure you want to reject this registration?');">Reject</button>
                                        </form>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        <?php endif; ?>

        <div class="card">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1rem;">
                <h3>Driver Profiles</h3>
                <div>
                    <button id="addDriverBtn" class="btn btn-primary">Add Driver</button>
                    <a href="driver_profiles.php?download_csv=true" class="btn btn-success">Download CSV</a>
                </div>
            </div>

            <div id="driverModal" class="modal">
                <div class="modal-content">
                    <span class="close-button">&times;</span>
                    <h2 id="modalTitle">Add Driver</h2>
                    <form action="driver_profiles.php" method="POST">
                        <input type="hidden" id="driver_id" name="driver_id">
                        <div class="form-group"><label>Full Name</label><input type="text" name="name" id="name"
                                class="form-control" required></div>
                        <div class="form-group"><label>Contact Number</label><input type="text" name="contact_number"
                                id="contact_number" class="form-control"></div>
                        <div class="form-group"><label>Date Joined</label><input type="date" name="date_joined"
                                id="date_joined" class="form-control"></div>
                        <div class="form-group"><label>License Number</label><input type="text" name="license_number"
                                id="license_number" class="form-control" required></div>
                        <div class="form-group"><label>License Expiry</label><input type="date"
                                name="license_expiry_date" id="license_expiry_date" class="form-control"></div>
                        <div class="form-group"><label>Rating (1.0 - 5.0)</label><input type="number" step="0.1" min="1"
                                max="5" name="rating" id="rating" class="form-control" required></div>
                        <div class="form-group"><label>Status</label><select name="status" id="status"
                                class="form-control" required>
                                <option value="Active">Active</option>
                                <option value="Suspended">Suspended</option>
                                <option value="Inactive">Inactive</option>
                            </select></div>
                        <div class="form-group"><label>Link to User Account</label><select name="user_id" id="user_id"
                                class="form-control">
                                <option value="">-- None --</option>
                                <?php mysqli_data_seek($users, 0);
                                while ($user = $users->fetch_assoc()): ?>
                                    <option value="<?php echo $user['id']; ?>">
                                        <?php echo htmlspecialchars($user['username']); ?></option><?php endwhile; ?>
                            </select></div>
                        <div class="form-actions"><button type="button"
                                class="btn btn-secondary cancelBtn">Cancel</button><button type="submit"
                                name="save_driver" class="btn btn-primary">Save Driver</button></div>
                    </form>
                </div>
            </div>

            <div class="table-section">
                <table>
                    <thead>
                        <tr>
                            <th>Name</th>
                            <th>Contact</th>
                            <th>License Expiry</th>
                            <th>Date Joined</th>
                            <th>Trips</th>
                            <th>Status</th>
                            <th>Avg. Adherence</th>
                            <th>Rating</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($drivers->num_rows > 0):
                            mysqli_data_seek($drivers, 0); ?>
                            <?php while ($row = $drivers->fetch_assoc()):
                                $expiry_date = !empty($row['license_expiry_date']) ? new DateTime($row['license_expiry_date']) : null;
                                $today = new DateTime();
                                $is_expiring_soon = false;
                                if ($expiry_date) {
                                    $diff = $today->diff($expiry_date);
                                    $is_expiring_soon = ($diff->days <= 30 && !$diff->invert);
                                }
                                ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($row['name']); ?></td>
                                    <td><?php echo htmlspecialchars($row['contact_number'] ?? 'N/A'); ?></td>
                                    <td class="<?php echo $is_expiring_soon ? 'expiring-soon' : ''; ?>">
                                        <?php echo !empty($row['license_expiry_date']) ? htmlspecialchars($row['license_expiry_date']) : 'N/A'; ?>
                                    </td>
                                    <td><?php echo !empty($row['date_joined']) ? htmlspecialchars($row['date_joined']) : 'N/A'; ?>
                                    </td>
                                    <td><?php echo htmlspecialchars($row['total_trips']); ?></td>
                                    <td><span
                                            class="status-badge status-<?php echo strtolower($row['status']); ?>"><?php echo htmlspecialchars($row['status']); ?></span>
                                    </td>
                                    <td>
                                        <?php
                                        if ($row['avg_adherence_score'] !== null) {
                                            $score = $row['avg_adherence_score'];
                                            $score_class = 'score-medium';
                                            if ($score >= 95)
                                                $score_class = 'score-high';
                                            if ($score < 80)
                                                $score_class = 'score-low';
                                            echo '<strong class="' . $score_class . '">' . number_format($score, 2) . '%</strong>';
                                        } else {
                                            echo '<span style="color: #888;">N/A</span>';
                                        }
                                        ?>
                                    </td>
                                    <td><?php echo htmlspecialchars($row['rating']); ?> ★</td>
                                    <td class="action-buttons">
                                        <button class="btn btn-warning btn-sm editDriverBtn"
                                            data-details='<?php echo htmlspecialchars(json_encode($row), ENT_QUOTES, 'UTF-8'); ?>'>Update</button>
                                        <button class="btn btn-info btn-sm viewBehaviorBtn"
                                            data-driver-id="<?php echo $row['id']; ?>"
                                            data-driver-name="<?php echo htmlspecialchars($row['name']); ?>">Behavior</button>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="9">No active drivers found.</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <div class="card" id="behaviorCard" style="display: none;">
            <div style="display: flex; justify-content: space-between; align-items: center;">
                <h3 id="behaviorCardTitle">Driver Behavior Logs</h3>
                <span class="close-button" id="closeBehaviorCard">&times;</span>
            </div>
            <div class="table-section" id="behaviorCardBody" style="margin-top: 1rem;">
                <!-- Content will be inserted by JavaScript -->
            </div>
        </div>

    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function () {
            // Sidebar logic is now handled by central sidebar.js

            document.querySelectorAll('.modal').forEach(modal => {
                const closeBtn = modal.querySelector('.close-button');
                const cancelBtn = modal.querySelector('.cancelBtn');
                if (closeBtn) { closeBtn.addEventListener('click', () => modal.style.display = 'none'); }
                if (cancelBtn) { cancelBtn.addEventListener('click', () => modal.style.display = 'none'); }
            });
            const driverModal = document.getElementById("driverModal");
            document.getElementById("addDriverBtn").addEventListener("click", () => {
                driverModal.querySelector('form').reset();
                driverModal.querySelector('#driver_id').value = '';
                driverModal.querySelector('#modalTitle').textContent = 'Add Driver';
                driverModal.style.display = 'block';
            });
            document.querySelectorAll('.editDriverBtn').forEach(btn => {
                btn.addEventListener('click', () => {
                    const data = JSON.parse(btn.dataset.details);
                    driverModal.querySelector('form').reset();
                    driverModal.querySelector('#modalTitle').textContent = 'Edit Driver';
                    driverModal.querySelector('#driver_id').value = data.id;
                    driverModal.querySelector('#name').value = data.name;
                    driverModal.querySelector('#license_number').value = data.license_number;
                    driverModal.querySelector('#license_expiry_date').value = data.license_expiry_date;
                    driverModal.querySelector('#contact_number').value = data.contact_number;
                    driverModal.querySelector('#date_joined').value = data.date_joined;
                    driverModal.querySelector('#rating').value = data.rating;
                    driverModal.querySelector('#status').value = data.status;
                    driverModal.querySelector('#user_id').value = data.user_id;
                    driverModal.style.display = 'block';
                });
            });

            const behaviorLogs = <?php echo $behavior_logs_json; ?>;
            const behaviorCard = document.getElementById('behaviorCard');
            const behaviorCardTitle = document.getElementById('behaviorCardTitle');
            const behaviorCardBody = document.getElementById('behaviorCardBody');
            const closeBehaviorCard = document.getElementById('closeBehaviorCard');

            document.querySelectorAll('.viewBehaviorBtn').forEach(btn => {
                btn.addEventListener('click', () => {
                    const driverId = btn.dataset.driverId;
                    const driverName = btn.dataset.driverName;
                    const driverLogs = behaviorLogs[driverId] || [];

                    behaviorCardTitle.textContent = `Behavior Logs for ${driverName}`;
                    let tableHtml = `
                    <table>
                        <thead>
                            <tr>
                                <th>Date</th>
                                <th>Overspeeding</th>
                                <th>Harsh Braking</th>
                                <th>Idle Time (Mins)</th>
                                <th>Notes</th>
                            </tr>
                        </thead>
                        <tbody>
                `;

                    if (driverLogs.length > 0) {
                        driverLogs.forEach(log => {
                            tableHtml += `
                            <tr>
                                <td>${log.log_date}</td>
                                <td>${log.overspeeding_count} incidents</td>
                                <td>${log.harsh_braking_count} incidents</td>
                                <td>${log.idle_duration_minutes}</td>
                                <td>${log.notes || ''}</td>
                            </tr>
                        `;
                        });
                    } else {
                        tableHtml += '<tr><td colspan="5">No behavior logs found for this driver.</td></tr>';
                    }

                    tableHtml += '</tbody></table>';
                    behaviorCardBody.innerHTML = tableHtml;
                    behaviorCard.style.display = 'block';
                    behaviorCard.scrollIntoView({ behavior: 'smooth', block: 'start' });
                });
            });

            if (closeBehaviorCard) {
                closeBehaviorCard.addEventListener('click', () => {
                    behaviorCard.style.display = 'none';
                });
            }
        });
    </script>
    <script src="../../assets/js/dark_mode_handler.js" defer></script>
</body>

</html>