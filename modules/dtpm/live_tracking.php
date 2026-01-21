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

?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Live Tracking | DTPM</title>
    <link rel="stylesheet" href="../../assets/css/style.css">
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
</head>

<body>
    <?php include '../../includes/sidebar.php'; ?>

    <div class="content" id="mainContent">
        <div class="header">
            <div class="hamburger" id="hamburger">☰</div>
            <div>
                <h1>Live Tracking Module</h1>
            </div>
            <div class="theme-toggle-container">
                <span class="theme-label">Dark Mode</span>
                <label class="theme-switch"><input type="checkbox" id="themeToggle"><span class="slider"></span></label>
            </div>
        </div>

        <div class="card">
            <h3>Live Vehicle Map</h3>
            <div style="margin-bottom: 1rem;">
                <a href="trip_history.php" class="btn btn-info">View Full Trip History</a>
            </div>
            <div id="liveTrackingMap"
                style="height: 550px; width: 100%; border-radius: var(--border-radius); margin-bottom: 1.5rem;"></div>
            <div class="table-section">
                <table>
                    <thead>
                        <tr>
                            <th>Vehicle</th>
                            <th>Driver</th>
                            <th>Location (Lat, Lng)</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody id="tracking-table-body">
                        <tr id="no-tracking-placeholder">
                            <td colspan="5">No live tracking data available. Waiting for live feed...</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
    <script src="https://www.gstatic.com/firebasejs/8.10.1/firebase-app.js"></script>
    <script src="https://www.gstatic.com/firebasejs/8.10.1/firebase-database.js"></script>

    <script>
        document.addEventListener('DOMContentLoaded', function () {

            const firebaseConfig = {
                apiKey: "AIzaSyCB0_OYZXX3K-AxKeHnVlYMv2wZ_81FeYM",
                authDomain: "slate49-cde60.firebaseapp.com",
                databaseURL: "https://slate49-cde60-default-rtdb.firebaseio.com",
                projectId: "slate49-cde60",
                storageBucket: "slate49-cde60.firebasestorage.app",
                messagingSenderId: "809390854040",
                appId: "1:809390854040:web:f7f77333bb0ac7ab73e5ed"
            };
            try {
                if (!firebase.apps.length) { firebase.initializeApp(firebaseConfig); } else { firebase.app(); }
            } catch (e) { console.error("Firebase init failed. Check config.", e); }
            const database = firebase.database();

            // --- MAP UPDATE FOR WAZE STYLE & BOUNDED VIEW ---
            const map = L.map('liveTrackingMap', {
                center: [12.8797, 121.7740], // Philippines Center
                zoom: 6,
                minZoom: 5,
                maxBounds: [[4.0, 116.0], [22.0, 128.0]], // Bound to Philippines
                maxBoundsViscosity: 1.0, // Bouncing effect when hitting edge
                zoomControl: false // Cleaner look
            });

            // Waze-like (CartoDB Voyager) Tile Layer
            L.tileLayer('https://{s}.basemaps.cartocdn.com/rastertiles/voyager/{z}/{x}/{y}{r}.png', {
                attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors &copy; <a href="https://carto.com/attributions">CARTO</a>'
            }).addTo(map);

            const markers = {};
            const trackingTableBody = document.getElementById('tracking-table-body');
            const noTrackingPlaceholder = document.getElementById('no-tracking-placeholder');

            function getVehicleIcon(vehicleInfo) {
                let svgIcon = `<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="#33ccff" stroke="#fff" stroke-width="1.5" width="40px" height="40px"><path d="M0 0h24v24H0z" fill="none"/><path d="M20 8h-3V4H3c-1.1 0-2 .9-2 2v11h2c0 1.66 1.34 3 3 3s3-1.34 3-3h6c0 1.66 1.34 3 3 3s3-1.34 3-3h2v-5l-3-4zM6 18c-.55 0-1-.45-1-1s.45-1 1-1 1 .45 1 1-.45 1-1 1zm13.5-1.5c-.83 0-1.5.67-1.5 1.5s.67 1.5 1.5 1.5 1.5-.67 1.5-1.5-.67-1.5-1.5-1.5zM18 10h1.5v3H18v-3zM3 6h12v7H3V6z"/></svg>`;
                return L.divIcon({ html: svgIcon, className: 'vehicle-icon', iconSize: [40, 40], iconAnchor: [20, 40], popupAnchor: [0, -40] });
            }

            function createOrUpdateMarkerAndRow(tripId, data) {
                if (!data.vehicle_info || !data.driver_name) return;
                noTrackingPlaceholder.style.display = 'none';

                const vehicle = data.vehicle_info;
                const driver = data.driver_name;
                const newLatLng = [data.lat, data.lng];
                let tripRow = document.getElementById(`trip-row-${tripId}`);

                if (!markers[tripId]) {
                    const popupContent = `<b>Vehicle:</b> ${vehicle}<br><b>Driver:</b> ${driver}`;
                    const icon = getVehicleIcon(vehicle);
                    markers[tripId] = L.marker(newLatLng, { icon: icon }).addTo(map).bindPopup(popupContent);

                    if (!tripRow) {
                        const newRow = document.createElement('tr');
                        newRow.id = `trip-row-${tripId}`; newRow.className = 'clickable-row'; newRow.dataset.tripid = tripId;
                        newRow.innerHTML = `<td>${vehicle}</td><td>${driver}</td><td class="location-cell">${data.lat.toFixed(6)}, ${data.lng.toFixed(6)}</td><td><span class="status-badge status-en-route">En Route</span></td><td><a href="../vrds/dispatch_control.php" class="btn btn-info btn-sm">View Dispatch</a></td>`;
                        newRow.addEventListener('click', () => {
                            // Move map to marker but respect bounds
                            map.panTo(newLatLng);
                            markers[tripId].openPopup();
                        });
                        trackingTableBody.appendChild(newRow);
                    }
                } else {
                    markers[tripId].setLatLng(newLatLng);

                    if (tripRow) {
                        tripRow.querySelector('.location-cell').textContent = `${data.lat.toFixed(6)}, ${data.lng.toFixed(6)}`;
                    }
                }
            }

            function removeMarkerAndRow(tripId) {
                if (markers[tripId]) { map.removeLayer(markers[tripId]); delete markers[tripId]; }
                const tripRow = document.getElementById(`trip-row-${tripId}`);
                if (tripRow) { tripRow.remove(); }
                if (Object.keys(markers).length === 0) { noTrackingPlaceholder.style.display = ''; }
            }

            const trackingRef = database.ref('live_tracking');
            trackingRef.on('child_added', (snapshot) => createOrUpdateMarkerAndRow(snapshot.key, snapshot.val()));
            trackingRef.on('child_changed', (snapshot) => createOrUpdateMarkerAndRow(snapshot.key, snapshot.val()));
            trackingRef.on('child_removed', (snapshot) => removeMarkerAndRow(snapshot.key));
        });
    </script>
    <script src="../../assets/js/dark_mode_handler.js" defer></script>
</body>

</html>