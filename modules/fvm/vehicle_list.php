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
    header('Content-Disposition: attachment; filename=vehicle_list_' . date('Y-m-d') . '.csv');
    $output = fopen('php://output', 'w');
    fputcsv($output, ['ID', 'Type', 'Model', 'Tag Type', 'Tag Code', 'Capacity (KG)', 'Plate No', 'Status', 'Assigned Driver']);

    $search_query_csv = isset($_GET['query']) ? $conn->real_escape_string($_GET['query']) : '';
    $status_filter_csv = isset($_GET['status']) ? $conn->real_escape_string($_GET['status']) : '';

    $where_clauses_csv = [];
    if (!empty($search_query_csv)) {
        $where_clauses_csv[] = "(v.type LIKE '%$search_query_csv%' OR v.model LIKE '%$search_query_csv%' OR v.tag_code LIKE '%$search_query_csv%' OR v.plate_no LIKE '%$search_query_csv%' OR v.status LIKE '%$search_query_csv%')";
    }
    if (!empty($status_filter_csv)) {
        if ($status_filter_csv === 'Available') {
            $where_clauses_csv[] = "v.status IN ('Active', 'Idle')";
        } elseif ($status_filter_csv === 'Unavailable') {
            $where_clauses_csv[] = "v.status IN ('En Route', 'Maintenance', 'Breakdown', 'Inactive')";
        } else {
            $where_clauses_csv[] = "v.status = '$status_filter_csv'";
        }
    }
    $where_sql_csv = !empty($where_clauses_csv) ? "WHERE " . implode(" AND ", $where_clauses_csv) : "";

    $result = $conn->query("SELECT v.*, d.name as driver_name FROM vehicles v LEFT JOIN drivers d ON v.assigned_driver_id = d.id $where_sql_csv ORDER BY v.id DESC");
    if ($result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            fputcsv($output, [
                $row['id'],
                $row['type'],
                $row['model'],
                $row['tag_type'],
                $row['tag_code'],
                $row['load_capacity_kg'],
                $row['plate_no'],
                $row['status'],
                $row['driver_name'] ?? 'N/A'
            ]);
        }
    }
    fclose($output);
    exit;
}
// --- WAKAS NG CSV DOWNLOAD LOGIC ---

// --- Calculate Statistics ---
$stats_query = $conn->query("
    SELECT 
        COUNT(*) as total,
        SUM(CASE WHEN status IN ('Active', 'Idle') THEN 1 ELSE 0 END) as available,
        SUM(CASE WHEN status IN ('En Route', 'Maintenance', 'Breakdown', 'Inactive') THEN 1 ELSE 0 END) as unavailable
    FROM vehicles
");
$stats = $stats_query->fetch_assoc();


// --- View Mode Handling ---
$view_mode = isset($_GET['view']) ? $_GET['view'] : 'list'; // 'list' or 'grid'

$search_query = isset($_GET['query']) ? $conn->real_escape_string($_GET['query']) : '';
$status_filter = isset($_GET['status']) ? $conn->real_escape_string($_GET['status']) : '';

$where_clauses = [];
if (!empty($search_query)) {
    $where_clauses[] = "(v.type LIKE '%$search_query%' OR v.model LIKE '%$search_query%' OR v.tag_code LIKE '%$search_query%' OR v.plate_no LIKE '%$search_query%' OR v.status LIKE '%$search_query%')";
}
if (!empty($status_filter)) {
    if ($status_filter === 'Available') {
        $where_clauses[] = "v.status IN ('Active', 'Idle')";
    } elseif ($status_filter === 'Unavailable') {
        $where_clauses[] = "v.status IN ('En Route', 'Maintenance', 'Breakdown', 'Inactive')";
    } else {
        $where_clauses[] = "v.status = '$status_filter'";
    }
}

$where_sql = !empty($where_clauses) ? "WHERE " . implode(" AND ", $where_clauses) : "";

$vehicles_result = $conn->query("SELECT v.*, d.name as driver_name FROM vehicles v LEFT JOIN drivers d ON v.assigned_driver_id = d.id $where_sql ORDER BY v.id DESC");

// Prepare query params for view toggles
$params_list = $_GET;
$params_list['view'] = 'list';
$params_grid = $_GET;
$params_grid['view'] = 'grid';
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Vehicle List & Fleet | FVM</title>
    <link rel="stylesheet" href="../../assets/css/style.css">
    <style>
        .view-toggles {
            display: flex;
            gap: 0.5rem;
        }

        .view-btn {
            padding: 0.5rem 0.75rem;
            border: var(--border-tech);
            background: var(--bg-panel);
            cursor: pointer;
            border-radius: 0.35rem;
            color: var(--text-muted);
        }

        .view-btn.active {
            background: var(--primary-color);
            color: #000;
            border-color: var(--primary-color);
        }

        .view-btn svg {
            width: 18px;
            height: 18px;
            display: block;
        }

        .vehicle-thumbnail {
            width: 60px;
            height: 45px;
            object-fit: cover;
            border-radius: 0.25rem;
            background-color: rgba(0, 0, 0, 0.05);
            display: block;
        }

        /* Stats Cards for Vehicle Availability */
        .status-summary {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1rem;
            margin-bottom: 1.5rem;
        }

        .summary-card {
            background: var(--bg-panel);
            padding: 1.5rem;
            border-radius: 4px;
            border: var(--border-tech);
            border-left: 4px solid var(--primary-color);
            display: flex;
            justify-content: space-between;
            align-items: center;
            box-shadow: var(--shadow-glow);
            backdrop-filter: var(--glass-blur);
        }

        .summary-card.available {
            border-left-color: var(--success-color);
        }

        .summary-card.unavailable {
            border-left-color: var(--danger-color);
        }

        .summary-label {
            font-size: 0.9rem;
            color: var(--text-muted);
            font-weight: 500;
            text-transform: uppercase;
            letter-spacing: 1px;
        }

        .summary-value {
            font-size: 1.8rem;
            font-weight: 700;
            color: var(--text-main);
            font-family: var(--font-data);
        }
    </style>
</head>

<body>
    <?php include '../../includes/sidebar.php'; ?>

    <div class="content" id="mainContent">
        <div class="header">
            <div class="hamburger" id="hamburger">☰</div>
            <div>
                <h1>Vehicle Fleet Management</h1>
            </div>
            <div class="theme-toggle-container">
                <span class="theme-label">Dark Mode</span>
                <label class="theme-switch"><input type="checkbox" id="themeToggle"><span class="slider"></span></label>
            </div>
        </div>

        <?php echo $message; ?>

        <!-- Status Summary Cards -->
        <div class="status-summary">
            <div class="summary-card">
                <div>
                    <div class="summary-label">Total Fleet</div>
                    <div class="summary-value"><?php echo $stats['total']; ?></div>
                </div>
                <svg xmlns="http://www.w3.org/2000/svg" width="32" height="32" viewBox="0 0 24 24" fill="none"
                    stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"
                    style="color: var(--primary-color); opacity: 0.7;">
                    <path
                        d="M19 17h2c.6 0 1-.4 1-1v-3c0-.9-.7-1.7-1.5-1.9C18.7 10.6 16 10 16 10s-1.3-1.4-2.2-2.3c-.5-.4-1.1-.7-1.8-.7H5c-.6 0-1.1.4-1.4.9l-1.4 2.9A3.7 3.7 0 0 0 2 12v4c0 .6.4 1 1 1h2" />
                    <circle cx="7" cy="17" r="2" />
                    <circle cx="17" cy="17" r="2" />
                    <path d="M5 17h9" />
                </svg>
            </div>
            <div class="summary-card available">
                <div>
                    <div class="summary-label">Available</div>
                    <div class="summary-value"><?php echo $stats['available']; ?></div>
                </div>
                <svg xmlns="http://www.w3.org/2000/svg" width="32" height="32" viewBox="0 0 24 24" fill="none"
                    stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"
                    style="color: var(--success-color); opacity: 0.7;">
                    <path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"></path>
                    <polyline points="22 4 12 14.01 9 11.01"></polyline>
                </svg>
            </div>
            <div class="summary-card unavailable">
                <div>
                    <div class="summary-label">Unavailable</div>
                    <div class="summary-value"><?php echo $stats['unavailable']; ?></div>
                </div>
                <svg xmlns="http://www.w3.org/2000/svg" width="32" height="32" viewBox="0 0 24 24" fill="none"
                    stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"
                    style="color: var(--danger-color); opacity: 0.7;">
                    <circle cx="12" cy="12" r="10"></circle>
                    <line x1="12" y1="8" x2="12" y2="12"></line>
                    <line x1="12" y1="16" x2="12.01" y2="16"></line>
                </svg>
            </div>
        </div>

        <div class="card" id="vehicle-list">
            <div
                style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem; flex-wrap: wrap; gap: 1rem;">
                <div style="display: flex; align-items: center; gap: 1rem;">
                    <h3>Fleet Inventory</h3>
                    <div class="view-toggles">
                        <a href="vehicle_list.php?<?php echo http_build_query($params_list); ?>"
                            class="view-btn <?php echo $view_mode === 'list' ? 'active' : ''; ?>" title="List View">
                            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none"
                                stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <line x1="8" y1="6" x2="21" y2="6"></line>
                                <line x1="8" y1="12" x2="21" y2="12"></line>
                                <line x1="8" y1="18" x2="21" y2="18"></line>
                                <line x1="3" y1="6" x2="3.01" y2="6"></line>
                                <line x1="3" y1="12" x2="3.01" y2="12"></line>
                                <line x1="3" y1="18" x2="3.01" y2="18"></line>
                            </svg>
                        </a>
                        <a href="vehicle_list.php?<?php echo http_build_query($params_grid); ?>"
                            class="view-btn <?php echo $view_mode === 'grid' ? 'active' : ''; ?>" title="Grid View">
                            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none"
                                stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <rect x="3" y="3" width="7" height="7"></rect>
                                <rect x="14" y="3" width="7" height="7"></rect>
                                <rect x="14" y="14" width="7" height="7"></rect>
                                <rect x="3" y="14" width="7" height="7"></rect>
                            </svg>
                        </a>
                    </div>
                </div>

                <div style="display: flex; gap: 0.5rem; align-items: center; flex-wrap: wrap;">
                    <form action="vehicle_list.php" method="GET" style="display: flex; gap: 0.5rem;">
                        <input type="hidden" name="view" value="<?php echo $view_mode; ?>">
                        <select name="status" class="form-control" onchange="this.form.submit()" style="width: auto;">
                            <option value="">All Statuses</option>
                            <option value="Available" <?php if ($status_filter == 'Available')
                                echo 'selected'; ?>>
                                Available (Active/Idle)</option>
                            <option value="Unavailable" <?php if ($status_filter == 'Unavailable')
                                echo 'selected'; ?>>
                                Unavailable (Busy/Maint)</option>
                            <option value="En Route" <?php if ($status_filter == 'En Route')
                                echo 'selected'; ?>>En Route
                            </option>
                            <option value="Maintenance" <?php if ($status_filter == 'Maintenance')
                                echo 'selected'; ?>>
                                Maintenance</option>
                        </select>
                        <input type="text" name="query" class="form-control" placeholder="Search vehicle..."
                            value="<?php echo htmlspecialchars($search_query); ?>">
                        <button type="submit" class="btn btn-primary">Search</button>
                    </form>
                    <a href="vehicle_list.php?download_csv=true&<?php echo http_build_query($_GET); ?>"
                        class="btn btn-success">Download CSV</a>
                </div>
            </div>

            <div id="viewVehicleModal" class="modal">
                <div class="modal-content">
<h2>Vehicle Details</h2>
                    <div id="viewVehicleBody" style="line-height: 1.8;"></div>

                </div>
            </div>

            <?php if ($view_mode === 'list'): ?>
                <!-- LIST VIEW (TABLE) -->
                <div class="table-section">
                    <table>
                        <thead>
                            <tr>
                                <th>Image</th>
                                <th>ID</th>
                                <th>Vehicle Type</th>
                                <th>Model</th>
                                <th>Tag Code</th>
                                <th>Capacity</th>
                                <th>Plate No</th>
                                <th>Status</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if ($vehicles_result->num_rows > 0): ?>
                                <?php while ($row = $vehicles_result->fetch_assoc()):
                                    $image_path = '../../assets/images/';
                                    $image = 'https://placehold.co/400x300/e2e8f0/e2e8f0?text=No+Image';
                                    if (stripos($row['model'], 'elf') !== false)
                                        $image = $image_path . 'elf.PNG';
                                    else if (stripos($row['model'], 'hiace') !== false)
                                        $image = $image_path . 'hiace.PNG';
                                    else if (stripos($row['model'], 'canter') !== false)
                                        $image = $image_path . 'canter.PNG';
                                    ?>
                                    <tr>
                                        <td><img src="<?php echo htmlspecialchars($image); ?>" alt="Vehicle"
                                                class="vehicle-thumbnail"></td>
                                        <td><?php echo $row['id']; ?></td>
                                        <td><?php echo htmlspecialchars($row['type']); ?></td>
                                        <td><?php echo htmlspecialchars($row['model']); ?></td>
                                        <td><?php echo htmlspecialchars($row['tag_code']); ?></td>
                                        <td><?php echo htmlspecialchars($row['load_capacity_kg']); ?> kg</td>
                                        <td><?php echo htmlspecialchars($row['plate_no']); ?></td>
                                        <td><span
                                                class="status-badge status-<?php echo strtolower(str_replace(' ', '-', $row['status'])); ?>"><?php echo htmlspecialchars($row['status']); ?></span>
                                        </td>
                                        <td>
                                            <button class="btn btn-info btn-sm viewVehicleBtn" data-id="<?php echo $row['id']; ?>"
                                                data-type="<?php echo htmlspecialchars($row['type']); ?>"
                                                data-model="<?php echo htmlspecialchars($row['model']); ?>"
                                                data-tag_type="<?php echo htmlspecialchars($row['tag_type']); ?>"
                                                data-tag_code="<?php echo htmlspecialchars($row['tag_code']); ?>"
                                                data-load_capacity_kg="<?php echo htmlspecialchars($row['load_capacity_kg']); ?>"
                                                data-plate_no="<?php echo htmlspecialchars($row['plate_no']); ?>"
                                                data-status="<?php echo htmlspecialchars($row['status']); ?>"
                                                data-driver="<?php echo htmlspecialchars($row['driver_name'] ?? 'None'); ?>">View</button>
                                        </td>
                                    </tr>
                                <?php endwhile; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="9">No vehicles found matching your criteria.</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>

            <?php else: ?>
                <!-- GRID VIEW (GALLERY) -->
                <div class="vehicle-gallery">
                    <?php if ($vehicles_result->num_rows > 0): ?>
                        <?php mysqli_data_seek($vehicles_result, 0); // Reset pointer for grid view reuse if needed
                                while ($row = $vehicles_result->fetch_assoc()):
                                    $image_path = '../../assets/images/';
                                    $image = 'https://placehold.co/400x300/e2e8f0/e2e8f0?text=No+Image';
                                    if (stripos($row['model'], 'elf') !== false)
                                        $image = $image_path . 'elf.PNG';
                                    if (stripos($row['model'], 'hiace') !== false)
                                        $image = $image_path . 'hiace.PNG';
                                    if (stripos($row['model'], 'canter') !== false)
                                        $image = $image_path . 'canter.PNG';
                                    ?>
                            <div class="vehicle-card">
                                <img src="<?php echo htmlspecialchars($image); ?>"
                                    alt="<?php echo htmlspecialchars($row['type']); ?>" class="vehicle-image">
                                <div class="vehicle-details">
                                    <div>
                                        <div style="display:flex; justify-content:space-between; align-items:flex-start;">
                                            <div class="vehicle-title"><?php echo htmlspecialchars($row['model']); ?></div>
                                            <span
                                                class="status-badge status-<?php echo strtolower(str_replace(' ', '-', $row['status'])); ?>"><?php echo htmlspecialchars($row['status']); ?></span>
                                        </div>
                                        <div class="vehicle-info" style="margin-bottom: 0.5rem;">
                                            <strong>Type:</strong> <?php echo htmlspecialchars($row['type']); ?><br>
                                            <strong>Plate:</strong> <?php echo htmlspecialchars($row['plate_no']); ?><br>
                                            <strong>Capacity:</strong> <?php echo htmlspecialchars($row['load_capacity_kg']); ?>
                                            kg<br>
                                            <strong>Driver:</strong>
                                            <?php echo htmlspecialchars($row['driver_name'] ?? 'Unassigned'); ?>
                                        </div>
                                    </div>
                                    <button class="btn btn-info viewVehicleBtn" style="margin-top: 0.5rem;"
                                        data-id="<?php echo $row['id']; ?>"
                                        data-type="<?php echo htmlspecialchars($row['type']); ?>"
                                        data-model="<?php echo htmlspecialchars($row['model']); ?>"
                                        data-tag_type="<?php echo htmlspecialchars($row['tag_type']); ?>"
                                        data-tag_code="<?php echo htmlspecialchars($row['tag_code']); ?>"
                                        data-load_capacity_kg="<?php echo htmlspecialchars($row['load_capacity_kg']); ?>"
                                        data-plate_no="<?php echo htmlspecialchars($row['plate_no']); ?>"
                                        data-status="<?php echo htmlspecialchars($row['status']); ?>"
                                        data-driver="<?php echo htmlspecialchars($row['driver_name'] ?? 'None'); ?>">View
                                        Details</button>
                                </div>
                            </div>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <p>No vehicles found matching your criteria.</p>
                    <?php endif; ?>
                </div>
            <?php endif; ?>

        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function () {
            // Sidebar logic is now handled by central sidebar.js

            // --- Modal Logic ---
            const viewVehicleModal = document.getElementById('viewVehicleModal');
            const viewVehicleBody = document.getElementById('viewVehicleBody');

            // Close modal handlers
            document.querySelectorAll('.modal').forEach(modal => {
                const closeBtn = modal.querySelector('.close-button');
                const cancelBtn = modal.querySelector('.cancelBtn');
                if (closeBtn) { closeBtn.addEventListener('click', () => modal.style.display = 'none'); }
                if (cancelBtn) { cancelBtn.addEventListener('click', () => modal.style.display = 'none'); }
            });

            // View Button Handler
            document.querySelectorAll('.viewVehicleBtn').forEach(button => {
                button.addEventListener('click', () => {
                    const model = button.dataset.model.toLowerCase();
                    const type = button.dataset.type.toLowerCase();
                    let imageUrl;
                    const imagePath = '../../assets/images/';

                    if (model.includes('elf')) { imageUrl = imagePath + `elf.PNG`; }
                    else if (model.includes('hiace')) { imageUrl = imagePath + `hiace.PNG`; }
                    else if (model.includes('canter')) { imageUrl = imagePath + `canter.PNG`; }
                    else { imageUrl = 'https://placehold.co/400x300/e2e8f0/e2e8f0?text=No+Image'; }

                    const status = button.dataset.status;
                    const statusClass = status.toLowerCase().replace(' ', '-');
                    const driver = button.dataset.driver;

                    const detailsHtml = `
                <img src="${imageUrl}" alt="${button.dataset.type}" style="width: 100%; height: auto; max-height: 250px; object-fit: cover; border-radius: 0.35rem; margin-bottom: 1rem;">
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
                    <div>
                        <p><strong>ID:</strong> ${button.dataset.id}</p>
                        <p><strong>Type:</strong> ${button.dataset.type}</p>
                        <p><strong>Model:</strong> ${button.dataset.model}</p>
                        <p><strong>Plate No.:</strong> ${button.dataset.plate_no}</p>
                    </div>
                    <div>
                        <p><strong>Tag Type:</strong> ${button.dataset.tag_type}</p>
                        <p><strong>Tag Code:</strong> ${button.dataset.tag_code}</p>
                        <p><strong>Capacity:</strong> ${button.dataset.load_capacity_kg} kg</p>
                        <p><strong>Assigned Driver:</strong> ${driver}</p>
                    </div>
                </div>
                <p style="margin-top: 1rem;"><strong>Status:</strong> <span class="status-badge status-${statusClass}">${status}</span></p>
            `;
                    viewVehicleBody.innerHTML = detailsHtml;
                    viewVehicleModal.style.display = 'block';
                });
            });
        });
    </script>
    <script src="../../assets/js/dark_mode_handler.js" defer></script>
</body>

</html>