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
$message = '';
$scripts_to_run = ''; // Variable to hold javascript to be run at the end of the body

// --- FORM & ACTION HANDLING ---
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Handle Trip Logic (EDIT ONLY)
    if (isset($_POST['save_trip'])) {
        $trip_id = $_POST['trip_id'];
        $vehicle_id = $_POST['vehicle_id'];
        $driver_id = $_POST['driver_id'];
        $destination = $_POST['destination'];
        $pickup_time = $_POST['pickup_time'];
        $status = $_POST['status'];
        if (!empty($trip_id)) {
            $sql = "UPDATE trips SET vehicle_id=?, driver_id=?, destination=?, pickup_time=?, status=? WHERE id=?";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("iisssi", $vehicle_id, $driver_id, $destination, $pickup_time, $status, $trip_id);
            if ($stmt->execute()) {
                $message = "<div class='message-banner success'>Trip updated successfully!</div>";
            } else {
                $message = "<div class='message-banner error'>Error: " . $conn->error . "</div>";
            }
            $stmt->close();
        } else {
            $message = "<div class='message-banner error'>Trip creation is not allowed from this page. Please create from a reservation.</div>";
        }
    } elseif (isset($_POST['start_trip_log'])) {
        $trip_id = $_POST['log_trip_id'];
        $location_name = $_POST['location_name'];
        $status_message = $_POST['status_message'];
        $latitude = null;
        $longitude = null;
        $apiUrl = "https://nominatim.openstreetmap.org/search?format=json&q=" . urlencode($location_name) . "&countrycodes=PH&limit=1";
        $ch = curl_init();
        curl_setopt_array($ch, [CURLOPT_URL => $apiUrl, CURLOPT_RETURNTRANSFER => 1, CURLOPT_USERAGENT => 'SLATE Logistics/1.0', CURLOPT_CONNECTTIMEOUT => 10, CURLOPT_SSL_VERIFYPEER => false, CURLOPT_SSL_VERIFYHOST => false]);
        $geo_response_json = curl_exec($ch);
        $curl_error = curl_error($ch);
        curl_close($ch);
        $geocoding_successful = false;
        if ($curl_error) {
            $message = "<div class='message-banner error'>cURL Error: " . htmlspecialchars($curl_error) . "</div>";
        } elseif ($geo_response_json) {
            $geo_response = json_decode($geo_response_json, true);
            if (isset($geo_response[0]['lat'])) {
                $latitude = $geo_response[0]['lat'];
                $longitude = $geo_response[0]['lon'];
                $geocoding_successful = true;
            }
        }
        if ($geocoding_successful) {
            $conn->begin_transaction();
            try {
                $stmt1 = $conn->prepare("UPDATE trips SET status = 'En Route' WHERE id = ?");
                $stmt1->bind_param("i", $trip_id);
                $stmt1->execute();
                $stmt1->close();
                $stmt2 = $conn->prepare("INSERT INTO tracking_log (trip_id, latitude, longitude, status_message) VALUES (?, ?, ?, ?)");
                $stmt2->bind_param("idds", $trip_id, $latitude, $longitude, $status_message);
                $stmt2->execute();
                $stmt2->close();
                $conn->commit();
                $message = "<div class='message-banner success'>Trip started from '$location_name' and is now live on all maps.</div>";
                $trip_details_stmt = $conn->prepare("SELECT v.type, v.model, d.name as driver_name FROM trips t JOIN vehicles v ON t.vehicle_id = v.id JOIN drivers d ON t.driver_id = d.id WHERE t.id = ?");
                $trip_details_stmt->bind_param("i", $trip_id);
                $trip_details_stmt->execute();
                $trip_details_result = $trip_details_stmt->get_result();
                if ($trip_details = $trip_details_result->fetch_assoc()) {
                    $firebase_data = ['lat' => (float) $latitude, 'lng' => (float) $longitude, 'speed' => 0, 'timestamp' => date('c'), 'vehicle_info' => $trip_details['type'] . ' ' . $trip_details['model'], 'driver_name' => $trip_details['driver_name']];
                    $firebase_url = "https://slate49-cde60-default-rtdb.firebaseio.com/live_tracking/" . $trip_id . ".json";
                    $json_data = json_encode($firebase_data);
                    $ch_firebase = curl_init();
                    curl_setopt_array($ch_firebase, [CURLOPT_URL => $firebase_url, CURLOPT_RETURNTRANSFER => 1, CURLOPT_CUSTOMREQUEST => "PUT", CURLOPT_POSTFIELDS => $json_data, CURLOPT_HTTPHEADER => ['Content-Type: application/json'], CURLOPT_SSL_VERIFYPEER => false]);
                    curl_exec($ch_firebase);
                    if (curl_errno($ch_firebase)) {
                        $message .= " <br><span style='color:orange; font-size:0.9em;'>Warning: Could not push initial state to Firebase: " . curl_error($ch_firebase) . "</span>";
                    }
                    curl_close($ch_firebase);
                }
                $trip_details_stmt->close();
            } catch (mysqli_sql_exception $exception) {
                $conn->rollback();
                $message = "<div class='message-banner error'>Error: " . $exception->getMessage() . "</div>";
            }
        } else {
            if (empty($message)) {
                $message = "<div class='message-banner error'>Could not find coordinates for: '" . htmlspecialchars($location_name) . "'.</div>";
            }
        }
    } elseif (isset($_POST['end_trip'])) {
        $trip_id = $_POST['trip_id_to_end'];
        $stmt = $conn->prepare("UPDATE trips SET status = 'Completed', actual_arrival_time = NOW() WHERE id = ?");
        $stmt->bind_param("i", $trip_id);
        if ($stmt->execute()) {
            $message = "<div class='message-banner success'>Trip #$trip_id marked as Completed. Vehicle will be removed from live map shortly.</div>";
            // --- START: SCRIPT PARA BURAHIN ANG DATA SA FIREBASE ---
            $scripts_to_run .= '
            <script src="https://www.gstatic.com/firebasejs/8.10.1/firebase-app.js"></script>
            <script src="https://www.gstatic.com/firebasejs/8.10.1/firebase-database.js"></script>
            <script>
                const firebaseConfig = {
                    apiKey: "AIzaSyCB0_OYZXX3K-AxKeHnVlYMv2wZ_81FeYM",
                    authDomain: "slate49-cde60.firebaseapp.com",
                    databaseURL: "https://slate49-cde60-default-rtdb.firebaseio.com",
                    projectId: "slate49-cde60",
                    storageBucket: "slate49-cde60.firebasestorage.app",
                    messagingSenderId: "809390854040",
                    appId: "1:809390854040:web:f7f77333bb0ac7ab73e5ed"
                };
                if (!firebase.apps.length) {
                    firebase.initializeApp(firebaseConfig);
                }
                const database = firebase.database();
                const tripIdToRemove = ' . $trip_id . ';
                if(tripIdToRemove) {
                    database.ref("live_tracking/" + tripIdToRemove).remove()
                        .then(() => {
                            console.log("Live tracking data for trip " + tripIdToRemove + " removed by admin.");
                        })
                        .catch((error) => {
                            console.error("Error removing live tracking data: ", error);
                        });
                }
            </script>';
            // --- END: SCRIPT ---
        } else {
            $message = "<div class='message-banner error'>Error updating trip status in database.</div>";
        }
    }
}

// --- DATA FETCHING ---
$trips_query = "SELECT t.*, v.type AS vehicle_type, v.model AS vehicle_model, d.name AS driver_name, r.reservation_code FROM trips t JOIN vehicles v ON t.vehicle_id = v.id JOIN drivers d ON t.driver_id = d.id LEFT JOIN reservations r ON t.reservation_id = r.id WHERE t.status IN ('Scheduled', 'En Route') ORDER BY t.pickup_time DESC";
$trips = $conn->query($trips_query);
$available_vehicles_for_form = $conn->query("SELECT id, type, model FROM vehicles WHERE status IN ('Active', 'Idle')");
$available_drivers = $conn->query("SELECT id, name FROM drivers WHERE status = 'Active'");
$tracking_data_query = $conn->query("SELECT t.id as trip_id, v.type, v.model, d.name as driver_name, tl.latitude, tl.longitude FROM tracking_log tl JOIN trips t ON tl.trip_id = t.id AND t.status = 'En Route' JOIN vehicles v ON t.vehicle_id = v.id JOIN drivers d ON t.driver_id = d.id INNER JOIN ( SELECT trip_id, MAX(log_time) AS max_log_time FROM tracking_log GROUP BY trip_id) latest_log ON tl.trip_id = latest_log.trip_id AND tl.log_time = latest_log.max_log_time");
$locations = [];
if ($tracking_data_query) {
    while ($row = $tracking_data_query->fetch_assoc()) {
        $locations[] = $row;
    }
}
$locations_json = json_encode($locations);
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dispatch & Trips | VRDS</title>
    <link rel="stylesheet" href="../../assets/css/style.css">
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
    <script src="https://www.gstatic.com/firebasejs/8.10.1/firebase-app.js"></script>
    <script src="https://www.gstatic.com/firebasejs/8.10.1/firebase-database.js"></script>
    <style>
        /* --- PREMIUM FORM STYLES --- */
        .form-group {
            margin-bottom: 1.5rem;
        }

        .form-group label {
            display: block;
            font-size: 0.85rem;
            text-transform: uppercase;
            letter-spacing: 1px;
            color: var(--text-muted);
            margin-bottom: 0.5rem;
            font-weight: 600;
        }

        .form-control {
            width: 100%;
            padding: 0.75rem 1rem;
            background: rgba(255, 255, 255, 0.05);
            border: 1px solid rgba(255, 255, 255, 0.1);
            color: var(--text-main);
            border-radius: 4px;
            font-family: var(--font-data);
            transition: all 0.3s ease;
        }

        .form-control:focus {
            background: rgba(0, 114, 255, 0.05);
            border-color: var(--primary-color);
            box-shadow: 0 0 10px rgba(0, 114, 255, 0.2);
            outline: none;
        }

        /* Ensure select options have a dark background so they are readable */
        .form-control option {
            background-color: var(--bg-panel);
            color: var(--text-main);
        }

        /* --- CENTERED MODAL STYLES --- */
        .modal {
            display: none;
            position: fixed;
            z-index: 2000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            overflow: auto;
            background-color: rgba(0, 0, 0, 0.7);
            backdrop-filter: blur(5px);
        }

        .modal-content {
            background-color: var(--bg-panel);
            margin: 0;
            padding: 2rem;
            border: var(--border-tech);
            border-radius: 8px;
            width: 90%;
            max-width: 600px;
            box-shadow: 0 0 50px rgba(0, 0, 0, 0.5);
            
            /* Center positioning */
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            animation: modalFadeIn 0.3s ease-out;
        }

        @keyframes modalFadeIn {
            from { opacity: 0; transform: translate(-50%, -60%); }
            to { opacity: 1; transform: translate(-50%, -50%); }
        }

        .modal h2 {
            margin-top: 0;
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
            padding-bottom: 10px;
            margin-bottom: 20px;
            font-size: 1.5rem;
            color: var(--primary-color);
        }

        .close-button {
            position: absolute;
            top: 15px;
            right: 20px;
            color: var(--text-muted);
            font-size: 28px;
            font-weight: bold;
            cursor: pointer;
            transition: color 0.3s;
            line-height: 1;
            z-index: 10;
        }

        .close-button:hover {
            color: var(--primary-color);
        }
    </style>

<body>
    <?php include '../../includes/sidebar.php'; ?>

    <div class="content" id="mainContent">
        <div class="header">
            <div class="hamburger" id="hamburger">☰</div>
            <div>
                <h1>Dispatch Control & Scheduled Trips</h1>
            </div>
            <div class="theme-toggle-container">
                <span class="theme-label">Dark Mode</span>
                <label class="theme-switch"><input type="checkbox" id="themeToggle"><span class="slider"></span></label>
            </div>
        </div>

        <?php echo $message; ?>

        <div class="card">
            <h3>Scheduled & On-going Trips</h3>
            <div style="margin-bottom: 1rem;"><button id="viewMapBtn" class="btn btn-info">View Live Map</button></div>

            <!-- START: Forms na dating modal -->
            <div id="viewTripModal" class="modal">
                <div class="modal-content"><span class="close-button">&times;</span>
                    <h2>Trip Details</h2>
                    <div id="viewTripBody"></div>
                </div>
            </div>
            <div id="tripFormModal" class="modal">
                <div class="modal-content"><span class="close-button">&times;</span>
                    <h2 id="tripFormTitle">Edit Trip</h2>
                    <form action="dispatch_control.php" method="POST"><input type="hidden" name="trip_id" id="trip_id">
                        <div class="form-group"><label>Vehicle</label><select name="vehicle_id" class="form-control"
                                required>
                                <option value="">-- Choose --</option>
                                <?php mysqli_data_seek($available_vehicles_for_form, 0);
                                while ($v = $available_vehicles_for_form->fetch_assoc()) {
                                    echo "<option value='{$v['id']}'>" . htmlspecialchars($v['type'] . ' - ' . $v['model']) . "</option>";
                                } ?>
                            </select></div>
                        <div class="form-group"><label>Driver</label><select name="driver_id" class="form-control"
                                required>
                                <option value="">-- Choose --</option>
                                <?php mysqli_data_seek($available_drivers, 0);
                                while ($d = $available_drivers->fetch_assoc()) {
                                    echo "<option value='{$d['id']}'>" . htmlspecialchars($d['name']) . "</option>";
                                } ?>
                            </select></div>
                        <div class="form-group"><label>Destination</label><input type="text" name="destination"
                                class="form-control" required></div>
                        <div class="form-group"><label>Pickup Time</label><input type="datetime-local"
                                name="pickup_time" class="form-control" required></div>
                        <div class="form-group"><label>Status</label><select name="status" id="trip_status_select"
                                class="form-control" required>
                                <option value="Scheduled">Scheduled</option>
                                <option value="En Route">En Route</option>
                                <option value="Completed">Completed</option>
                                <option value="Cancelled">Cancelled</option>
                            </select></div>
                        <div class="form-actions"><button type="button"
                                class="btn btn-secondary cancelBtn">Cancel</button><button type="submit"
                                name="save_trip" class="btn btn-primary">Save Changes</button></div>
                    </form>
                </div>
            </div>
            <div id="startTripModal" class="modal">
                <div class="modal-content"><span class="close-button">&times;</span>
                    <h2>Start Trip & Log Initial Location</h2>
                    <form action="dispatch_control.php" method="POST"><input type="hidden" name="log_trip_id"
                            id="log_trip_id">
                        <div class="form-group"><label>Starting Location</label><input type="text" name="location_name"
                                id="location_name" class="form-control" placeholder="e.g., SM North EDSA, Quezon City"
                                required></div>
                        <div class="form-group"><label>Status Message</label><input type="text" name="status_message"
                                id="status_message" class="form-control" value="Trip Started" required></div>
                        <div class="form-actions"><button type="button"
                                class="btn btn-secondary cancelBtn">Cancel</button><button type="submit"
                                name="start_trip_log" class="btn btn-success">Confirm & Start</button></div>
                    </form>
                </div>
            </div>
            <div id="confirmModal" class="modal">
                <div class="modal-content" style="max-width: 400px;"><span class="close-button">&times;</span>
                    <h2 id="confirmModalTitle">Are you sure?</h2>
                    <p id="confirmModalText"></p>
                    <div class="form-actions"><button type="button" class="btn btn-secondary"
                            id="confirmCancelBtn">Cancel</button><button type="button" class="btn btn-danger"
                            id="confirmOkBtn">Confirm</button></div>
                </div>
            </div>
            <!-- END: Forms na dating modal -->

            <div class="table-section">
                <table>
                    <thead>
                        <tr>
                            <th>Trip Code</th>
                            <th>Vehicle</th>
                            <th>Driver</th>
                            <th>Pickup Time</th>
                            <th>Destination</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($trips && $trips->num_rows > 0):
                            mysqli_data_seek($trips, 0);
                            while ($row = $trips->fetch_assoc()): ?>
                                <tr>
                                    <td>
                                        <?php echo htmlspecialchars($row['trip_code']); ?>
                                        <?php if (!empty($row['reservation_id'])): ?>
                                            <br><a
                                                href="reservation_booking.php?search=<?php echo urlencode($row['reservation_code']); ?>"
                                                style="font-size: 0.8em; color: var(--info-color);">(from
                                                <?php echo htmlspecialchars($row['reservation_code']); ?>)</a>
                                        <?php endif; ?>
                                    </td>
                                    <td><?php echo htmlspecialchars($row['vehicle_type'] . ' ' . $row['vehicle_model']); ?></td>
                                    <td><?php echo htmlspecialchars($row['driver_name']); ?></td>
                                    <td><?php echo htmlspecialchars(date('M d, Y h:i A', strtotime($row['pickup_time']))); ?>
                                    </td>
                                    <td><?php echo htmlspecialchars($row['destination']); ?></td>
                                    <td><span
                                            class="status-badge status-<?php echo strtolower(str_replace(' ', '-', $row['status'])); ?>"><?php echo htmlspecialchars($row['status']); ?></span>
                                    </td>
                                    <td class="action-buttons">
                                        <button class="btn btn-info btn-sm viewTripBtn"
                                            data-details='<?php echo htmlspecialchars(json_encode($row), ENT_QUOTES, 'UTF-8'); ?>'>View</button>
                                        <button class="btn btn-warning btn-sm editTripBtn" data-id="<?php echo $row['id']; ?>"
                                            data-vehicle_id="<?php echo $row['vehicle_id']; ?>"
                                            data-driver_id="<?php echo $row['driver_id']; ?>"
                                            data-destination="<?php echo htmlspecialchars($row['destination']); ?>"
                                            data-pickup_time="<?php echo substr(str_replace(' ', 'T', $row['pickup_time']), 0, 16); ?>"
                                            data-status="<?php echo $row['status']; ?>">Edit</button>
                                        <?php if ($row['status'] == 'Scheduled'): ?><button
                                                class="btn btn-success btn-sm startTripBtn"
                                                data-trip_id="<?php echo $row['id']; ?>">Start</button><?php endif; ?>
                                        <?php if ($row['status'] == 'En Route'): ?><button
                                                class="btn btn-danger btn-sm endTripBtn"
                                                data-trip_id="<?php echo $row['id']; ?>">End</button><?php endif; ?>
                                    </td>
                                </tr>
                            <?php endwhile; else: ?>
                            <tr>
                                <td colspan="7">No active or scheduled trips found.</td>
                            </tr><?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <div id="mapModal" class="modal">
            <div class="modal-content" style="max-width: 900px;"><span class="close-button">&times;</span>
                <h2>Live Dispatch Map</h2>
                <div id="dispatchMap" style="height: 500px; width: 100%; border-radius: 0.35rem;"></div>
            </div>
        </div>
    </div>

    <form id="endTripForm" action="dispatch_control.php" method="POST" style="display: none;"><input type="hidden"
            name="trip_id_to_end" id="trip_id_to_end"><input type="hidden" name="end_trip" value="1"></form>

    <script>
        document.addEventListener('DOMContentLoaded', function () {

            const viewTripModal = document.getElementById("viewTripModal");
            document.querySelectorAll('.viewTripBtn').forEach(button => {
                button.addEventListener('click', () => {
                    const details = JSON.parse(button.dataset.details);
                    const pickupTime = new Date(details.pickup_time).toLocaleString('en-US', { month: 'short', day: 'numeric', year: 'numeric', hour: '2-digit', minute: '2-digit', hour12: true });

                    document.getElementById("viewTripBody").innerHTML = `
                <p><strong>Trip Code:</strong> ${details.trip_code}</p>
                <p><strong>Reservation Code:</strong> ${details.reservation_code || 'N/A'}</p>
                <p><strong>Client:</strong> ${details.client_name}</p>
                <hr style="margin: 0.8rem 0;">
                <p><strong>Vehicle:</strong> ${details.vehicle_type} ${details.vehicle_model}</p>
                <p><strong>Driver:</strong> ${details.driver_name}</p>
                <hr style="margin: 0.8rem 0;">
                <p><strong>Pickup Time:</strong> ${pickupTime}</p>
                <p><strong>Destination:</strong> ${details.destination}</p>
                <p><strong>Status:</strong> <span class="status-badge status-${details.status.toLowerCase().replace(' ', '-')}">${details.status}</span></p>
            `;
                    viewTripModal.style.display = "block";
                });
            });

            document.querySelectorAll('.editTripBtn').forEach(button => {
                button.addEventListener('click', () => {
                    const tripFormModal = document.getElementById("tripFormModal");
                    const tripFormTitle = document.getElementById("tripFormTitle");
                    const tripForm = tripFormModal.querySelector('form'); tripForm.reset();
                    tripForm.querySelector('#trip_id').value = button.dataset.id;
                    tripForm.querySelector('select[name="vehicle_id"]').value = button.dataset.vehicle_id;
                    tripForm.querySelector('select[name="driver_id"]').value = button.dataset.driver_id;
                    tripForm.querySelector('input[name="destination"]').value = button.dataset.destination;
                    tripForm.querySelector('input[name="pickup_time"]').value = button.dataset.pickup_time;
                    tripForm.querySelector('#trip_status_select').value = button.dataset.status;
                    tripFormTitle.textContent = "Edit Trip Details";
                    tripFormModal.style.display = "block";
                });
            });

            const startTripModal = document.getElementById("startTripModal");
            document.querySelectorAll('.startTripBtn').forEach(button => {
                button.addEventListener('click', () => {
                    startTripModal.querySelector('#log_trip_id').value = button.dataset.trip_id;
                    startTripModal.style.display = "block";
                });
            });

            const mapModal = document.getElementById("mapModal");
            let map; let markers = {}; let firebaseInitialized = false;
            const firebaseConfig = {
                apiKey: "AIzaSyCB0_OYZXX3K-AxKeHnVlYMv2wZ_81FeYM",
                authDomain: "slate49-cde60.firebaseapp.com",
                databaseURL: "https://slate49-cde60-default-rtdb.firebaseio.com",
                projectId: "slate49-cde60",
                storageBucket: "slate49-cde60.firebasestorage.app",
                messagingSenderId: "809390854040",
                appId: "1:809390854040:web:f7f77333bb0ac7ab73e5ed"
            };
            function getVehicleIcon(vehicleInfo) {
                let svgIcon = `<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="#858796"><path d="M18.92 6.01C18.72 5.42 18.16 5 17.5 5h-11C5.84 5 5.28 5.42 5.08 6.01L3 12v8c0 .55.45 1 1 1h1c.55 0 1-.45 1-1v-1h12v1c0 .55.45 1 1 1h1c.55 0 1-.45 1-1v-8l-2.08-5.99zM6.5 16c-.83 0-1.5-.67-1.5-1.5S5.67 13 6.5 13s1.5.67 1.5 1.5S7.33 16 6.5 16zm11 0c-.83 0-1.5-.67-1.5-1.5s.67-1.5 1.5-1.5 1.5.67 1.5 1.5s-.67-1.5-1.5 1.5zM5 11l1.5-4.5h11L19 11H5z"/></svg>`;
                if (vehicleInfo.toLowerCase().includes('truck')) { svgIcon = `<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="#4e73df"><path d="M20 8h-3V4H3c-1.1 0-2 .9-2 2v11h2c0 1.66 1.34 3 3 3s3-1.34 3-3h6c0 1.66 1.34 3 3 3s3-1.34 3-3h2v-5l-3-4zM6 18c-.55 0-1-.45-1-1s.45-1 1-1 1 .45 1 1-.45 1-1 1zm13.5-1.5c-.83 0-1.5.67-1.5 1.5s.67 1.5 1.5 1.5 1.5-.67 1.5-1.5-.67-1.5-1.5 1.5zM18 10h1.5v3H18v-3zM3 6h12v7H3V6z"/></svg>`; }
                else if (vehicleInfo.toLowerCase().includes('van')) { svgIcon = `<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="#1cc88a"><path d="M20 8H4V6h16v2zm-2.17-3.24L15.21 2.14A1 1 0 0014.4 2H5a2 2 0 00-2 2v13c0 1.1.9 2 2 2h14c1.1 0 2-.9 2-2V7c0-.98-.71-1.8-1.65-1.97l-2.52-.27zM6.5 18c-.83 0-1.5-.67-1.5-1.5S5.67 15 6.5 15s1.5.67 1.5 1.5S7.33 18 6.5 18zm11 0c-.83 0-1.5-.67-1.5-1.5s.67-1.5 1.5-1.5 1.5.67 1.5 1.5s-.67-1.5-1.5 1.5zM4 13h16V9H4v4z"/></svg>`; }
                return L.divIcon({ html: svgIcon, className: 'vehicle-icon', iconSize: [40, 40], iconAnchor: [20, 40] });
            }
            function initializeFirebaseListener() {
                if (firebaseInitialized) return;
                try {
                    if (!firebase.apps.length) firebase.initializeApp(firebaseConfig); else firebase.app();
                    const database = firebase.database(); const trackingRef = database.ref('live_tracking');
                    trackingRef.on('child_added', (s) => handleDataChange(s.key, s.val()));
                    trackingRef.on('child_changed', (s) => handleDataChange(s.key, s.val()));
                    trackingRef.on('child_removed', (s) => { if (map && markers[s.key]) { map.removeLayer(markers[s.key]); delete markers[s.key]; } });
                    firebaseInitialized = true;
                } catch (e) { console.error("Firebase init error:", e); }
            }
            function handleDataChange(tripId, data) {
                if (!map || !data.vehicle_info) return;
                const newLatLng = [data.lat, data.lng];
                if (!markers[tripId]) {
                    const popupContent = `<b>Vehicle:</b> ${data.vehicle_info}<br><b>Driver:</b> ${data.driver_name}`;
                    markers[tripId] = L.marker(newLatLng, { icon: getVehicleIcon(data.vehicle_info) }).addTo(map).bindPopup(popupContent);
                } else {
                    markers[tripId].setLatLng(newLatLng);
                }
            }
            document.getElementById("viewMapBtn").addEventListener("click", function () {
                mapModal.style.display = "block";
                if (!map) {
                    map = L.map('dispatchMap').setView([12.8797, 121.7740], 5);
                    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png').addTo(map);
                    initializeFirebaseListener();
                }
                setTimeout(() => map.invalidateSize(), 10);
            });

            const confirmModal = document.getElementById('confirmModal');
            const confirmOkBtn = document.getElementById('confirmOkBtn');
            const confirmCancelBtn = document.getElementById('confirmCancelBtn');
            const confirmModalText = document.getElementById('confirmModalText');
            const confirmModalTitle = document.getElementById('confirmModalTitle');
            confirmModal.querySelector('.close-button').onclick = () => confirmModal.style.display = 'none';
            confirmCancelBtn.onclick = () => confirmModal.style.display = 'none';

            document.querySelectorAll('.endTripBtn').forEach(button => {
                button.addEventListener('click', (e) => {
                    e.preventDefault();
                    const tripId = button.dataset.trip_id;
                    confirmModalTitle.textContent = 'End Trip?';
                    confirmModalText.textContent = `Are you sure you want to end Trip #${tripId}?`;
                    confirmModal.style.display = "block";

                    confirmOkBtn.onclick = () => {
                        document.getElementById('trip_id_to_end').value = tripId;
                        document.getElementById('endTripForm').submit();
                    };
                });
            });

            document.querySelectorAll('.modal .close-button, .modal .cancelBtn').forEach(el => {
                el.addEventListener('click', () => {
                    el.closest('.modal').style.display = 'none';
                });
            });

            // Close modal when clicking outside
            window.addEventListener('click', (e) => {
                if (e.target.classList.contains('modal')) {
                    e.target.style.display = 'none';
                }
            });
        });
    </script>
    <script src="../../assets/js/dark_mode_handler.js" defer></script>
    <?php echo $scripts_to_run; ?>
</body>

</html>