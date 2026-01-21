<?php
// sidebar.php
$current_page = basename($_SERVER['PHP_SELF']);
$user_role = $_SESSION['role'] ?? 'guest';

// --- UPDATED ROBUST PATH LOGIC ---
$script_dir = str_replace('\\', '/', __DIR__);
$doc_root = str_replace('\\', '/', $_SERVER['DOCUMENT_ROOT']);
$relative_path = str_replace($doc_root, '', $script_dir);
$project_root = dirname($relative_path);

if ($project_root === '/' || $project_root === '\\' || $project_root === '.') {
    $project_root = '';
}

// Access Control Arrays
$module_access = [
    'fvm' => ['admin', 'staff'],
    'vrds' => ['admin', 'staff'],
    'dtpm' => ['admin', 'staff'],
    'tcao' => ['admin'],
    'mfc' => ['admin'],
    'client' => ['client']
];

// Mga grupo ng pahina (Active State Logic)
$fvm_pages = ['vehicle_list.php', 'maintenance_approval.php', 'usage_logs.php'];
$vrds_pages = ['reservation_booking.php', 'dispatch_control.php'];
$dtpm_pages = ['live_tracking.php', 'driver_profiles.php', 'trip_history.php', 'route_adherence.php', 'driver_behavior.php', 'delivery_status.php'];
$tcao_pages = ['cost_analysis.php', 'trip_costs.php', 'budget_management.php'];
$mfc_pages = ['mobile_app.php', 'admin_alerts.php', 'admin_messaging.php'];
$client_pages = ['dashboard.php', 'book_trip.php', 'feedback.php'];
?>
<div class="sidebar" id="sidebar">
    <div class="logo"><img src="<?php echo $project_root; ?>/assets/images/logo.png" alt="SLATE Logo"></div>
    <div class="system-name">SLATE LOGISTICS</div>

    <!-- ADMIN & STAFF MENU -->
    <?php if ($user_role === 'admin' || $user_role === 'staff'): ?>
        <a href="<?php echo $project_root; ?>/landpage.php"
            class="<?php echo ($current_page == 'landpage.php') ? 'active' : ''; ?>">
            <span class="sidebar-icon"><svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24"
                    fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"></path>
                    <polyline points="9 22 9 12 15 12 15 22"></polyline>
                </svg></span>
            <span>Dashboard</span>
        </a>

        <!-- Fleet & Vehicle Management -->
        <?php if (in_array($user_role, $module_access['fvm'])): ?>
            <div class="dropdown <?php echo (in_array($current_page, $fvm_pages)) ? 'active' : ''; ?>">
                <a href="#" class="dropdown-toggle">
                    <div style="display: flex; align-items: center; width: 100%; justify-content: space-between;">
                        <div style="display: flex; align-items: center;">
                            <span class="sidebar-icon"><svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M14 17H4a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h10v14z"></path><path d="M20 17h-4v-7a1 1 0 0 1 1-1h2a1 1 0 0 1 1 1v7z"></path><path d="M12 5H9.5a2.5 2.5 0 0 1 0-5C10.9 0 12 1.1 12 2.5V5z"></path><path d="M18 5h-1.5a2.5 2.5 0 0 1 0-5C17.4 0 18 1.1 18 2.5V5z"></path></svg></span>
                            <span>Fleet & Vehicle Mgt.</span>
                        </div>
                        <svg class="chevron" xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="6 9 12 15 18 9"></polyline></svg>
                    </div>
                </a>
                <div class="dropdown-menu">
                    <a href="<?php echo $project_root; ?>/modules/fvm/vehicle_list.php" class="<?php echo ($current_page == 'vehicle_list.php') ? 'active-sub' : ''; ?>">Vehicle List</a>
                    <a href="<?php echo $project_root; ?>/modules/fvm/maintenance_approval.php" class="<?php echo ($current_page == 'maintenance_approval.php') ? 'active-sub' : ''; ?>">Maintenance Approval</a>
                    <a href="<?php echo $project_root; ?>/modules/fvm/usage_logs.php" class="<?php echo ($current_page == 'usage_logs.php') ? 'active-sub' : ''; ?>">Usage Logs</a>
                </div>
            </div>
        <?php endif; ?>

        <!-- Reservation & Dispatch -->
        <?php if (in_array($user_role, $module_access['vrds'])): ?>
            <div class="dropdown <?php echo (in_array($current_page, $vrds_pages)) ? 'active' : ''; ?>">
                <a href="#" class="dropdown-toggle">
                    <div style="display: flex; align-items: center; width: 100%; justify-content: space-between;">
                        <div style="display: flex; align-items: center;">
                            <span class="sidebar-icon"><svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M8 17.929H6c-1.105 0-2-.895-2-2V7c0-1.105.895-2 2-2h12c1.105 0 2 .895 2 2v2.828"></path><path d="M6 17h12"></path><circle cx="6" cy="17" r="2"></circle><circle cx="18" cy="17" r="2"></circle><path d="M12 12V5h4l3 3v2h-3"></path></svg></span>
                            <span>Reservation & Dispatch</span>
                        </div>
                        <svg class="chevron" xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="6 9 12 15 18 9"></polyline></svg>
                    </div>
                </a>
                <div class="dropdown-menu">
                    <a href="<?php echo $project_root; ?>/modules/vrds/reservation_booking.php" class="<?php echo ($current_page == 'reservation_booking.php') ? 'active-sub' : ''; ?>">Reservation Booking</a>
                    <a href="<?php echo $project_root; ?>/modules/vrds/dispatch_control.php" class="<?php echo ($current_page == 'dispatch_control.php') ? 'active-sub' : ''; ?>">Dispatch & Trips</a>
                </div>
            </div>
        <?php endif; ?>

        <!-- Driver & Trip Performance -->
        <?php if (in_array($user_role, $module_access['dtpm'])): ?>
            <div class="dropdown <?php echo (in_array($current_page, $dtpm_pages)) ? 'active' : ''; ?>">
                <a href="#" class="dropdown-toggle">
                    <div style="display: flex; align-items: center; width: 100%; justify-content: space-between;">
                        <div style="display: flex; align-items: center;">
                            <span class="sidebar-icon"><svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 22s-8-4.5-8-11.8A8 8 0 0 1 12 2a8 8 0 0 1 8 8.2c0 7.3-8 11.8-8 11.8z"></path><circle cx="12" cy="10" r="3"></circle></svg></span>
                            <span>Driver & Trip Perf.</span>
                        </div>
                        <svg class="chevron" xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="6 9 12 15 18 9"></polyline></svg>
                    </div>
                </a>
                <div class="dropdown-menu">
                    <a href="<?php echo $project_root; ?>/modules/dtpm/live_tracking.php" class="<?php echo ($current_page == 'live_tracking.php') ? 'active-sub' : ''; ?>">Live Tracking</a>
                    <a href="<?php echo $project_root; ?>/modules/dtpm/driver_profiles.php" class="<?php echo ($current_page == 'driver_profiles.php') ? 'active-sub' : ''; ?>">Driver Profiles</a>
                    <a href="<?php echo $project_root; ?>/modules/dtpm/trip_history.php" class="<?php echo ($current_page == 'trip_history.php') ? 'active-sub' : ''; ?>">Trip History</a>
                    <a href="<?php echo $project_root; ?>/modules/dtpm/route_adherence.php" class="<?php echo ($current_page == 'route_adherence.php') ? 'active-sub' : ''; ?>">Route Adherence</a>
                    <a href="<?php echo $project_root; ?>/modules/dtpm/driver_behavior.php" class="<?php echo ($current_page == 'driver_behavior.php') ? 'active-sub' : ''; ?>">Driver Behavior</a>
                    <a href="<?php echo $project_root; ?>/modules/dtpm/delivery_status.php" class="<?php echo ($current_page == 'delivery_status.php') ? 'active-sub' : ''; ?>">Delivery Status</a>
                </div>
            </div>
        <?php endif; ?>

        <!-- Transport Cost Analysis -->
        <?php if (in_array($user_role, $module_access['tcao'])): ?>
            <div class="dropdown <?php echo (in_array($current_page, $tcao_pages)) ? 'active' : ''; ?>">
                <a href="#" class="dropdown-toggle">
                    <div style="display: flex; align-items: center; width: 100%; justify-content: space-between;">
                        <div style="display: flex; align-items: center;">
                            <span class="sidebar-icon"><svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M18 20V6a2 2 0 0 0-2-2H4a2 2 0 0 0-2 2v14"></path><path d="M2 10h16"></path><path d="M2 14h16"></path><path d="M2 18h16"></path><path d="M21 20H18"></path></svg></span>
                            <span>Cost Analysis</span>
                        </div>
                        <svg class="chevron" xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="6 9 12 15 18 9"></polyline></svg>
                    </div>
                </a>
                <div class="dropdown-menu">
                    <a href="<?php echo $project_root; ?>/modules/tcao/trip_costs.php" class="<?php echo ($current_page == 'trip_costs.php') ? 'active-sub' : ''; ?>">Trip Costs</a>
                    <a href="<?php echo $project_root; ?>/modules/tcao/cost_analysis.php" class="<?php echo ($current_page == 'cost_analysis.php') ? 'active-sub' : ''; ?>">Cost Analysis</a>
                    <a href="<?php echo $project_root; ?>/modules/tcao/budget_management.php" class="<?php echo ($current_page == 'budget_management.php') ? 'active-sub' : ''; ?>">Budget Mgt</a>
                </div>
            </div>
        <?php endif; ?>

        <!-- MFC & App Sync -->
        <?php if (in_array($user_role, $module_access['mfc'])): ?>
            <div class="dropdown <?php echo (in_array($current_page, $mfc_pages)) ? 'active' : ''; ?>">
                <a href="#" class="dropdown-toggle">
                    <div style="display: flex; align-items: center; width: 100%; justify-content: space-between;">
                        <div style="display: flex; align-items: center;">
                            <span class="sidebar-icon"><svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"></circle><line x1="12" y1="16" x2="12" y2="12"></line><line x1="12" y1="8" x2="12.01" y2="8"></line></svg></span>
                            <span>MFC & App Sync</span>
                        </div>
                        <svg class="chevron" xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="6 9 12 15 18 9"></polyline></svg>
                    </div>
                </a>
                <div class="dropdown-menu">
                    <a href="<?php echo $project_root; ?>/modules/mfc/admin_alerts.php" class="<?php echo ($current_page == 'admin_alerts.php') ? 'active-sub' : ''; ?>">SOS Alerts</a>
                    <a href="<?php echo $project_root; ?>/modules/mfc/admin_messaging.php" class="<?php echo ($current_page == 'admin_messaging.php') ? 'active-sub' : ''; ?>">Messaging</a>
                    <a href="<?php echo $project_root; ?>/modules/mfc/mobile_app.php" class="<?php echo ($current_page == 'mobile_app.php') ? 'active-sub' : ''; ?>">Driver App Sim</a>
                </div>
            </div>
        <?php endif; ?>
    <?php endif; ?>

    <!-- CLIENT PORTAL MENU -->
    <?php if ($user_role === 'client'): ?>
        <div class="menu-label"
            style="padding: 10px 25px; color: var(--text-muted-dark); font-size: 0.75rem; text-transform: uppercase; font-weight: 600; margin-top: 1rem;">
            CLIENT PORTAL</div>

        <a href="<?php echo $project_root; ?>/modules/client/dashboard.php"
            class="<?php echo ($current_page == 'dashboard.php') ? 'active' : ''; ?>">
            <span class="sidebar-icon"><svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24"
                    fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <rect x="3" y="3" width="7" height="7"></rect>
                    <rect x="14" y="3" width="7" height="7"></rect>
                    <rect x="14" y="14" width="7" height="7"></rect>
                    <rect x="3" y="14" width="7" height="7"></rect>
                </svg></span>
            <span>My Dashboard</span>
        </a>

        <a href="<?php echo $project_root; ?>/modules/client/book_trip.php"
            class="<?php echo ($current_page == 'book_trip.php') ? 'active' : ''; ?>">
            <span class="sidebar-icon"><svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24"
                    fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path>
                    <polyline points="14 2 14 8 20 8"></polyline>
                    <line x1="12" y1="18" x2="12" y2="12"></line>
                    <line x1="9" y1="15" x2="15" y2="15"></line>
                </svg></span>
            <span>Book a Trip</span>
        </a>

        <a href="<?php echo $project_root; ?>/modules/client/feedback.php"
            class="<?php echo ($current_page == 'feedback.php') ? 'active' : ''; ?>">
            <span class="sidebar-icon"><svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24"
                    fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <polygon
                        points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2">
                    </polygon>
                </svg></span>
            <span>Trip Feedback</span>
        </a>
    <?php endif; ?>

    <a href="<?php echo $project_root; ?>/auth/logout.php" class="logout-link" id="logout-link">
        <span class="sidebar-icon"><svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24"
                fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"></path>
                <polyline points="16 17 21 12 16 7"></polyline>
                <line x1="21" y1="12" x2="9" y2="12"></line>
            </svg></span>
        <span>Logout</span>
    </a>
</div>

<!-- Link Central Sidebar Script -->
<script src="<?php echo $project_root; ?>/assets/js/sidebar.js"></script>