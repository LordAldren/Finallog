<?php
session_start();
// Admin-only access
if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true || $_SESSION['role'] !== 'admin') {
    header("location: auth/login.php");
    exit;
}
require_once 'config/db_connect.php';

// Fetch all users with their employee IDs
$users_result = $conn->query("SELECT id, username, role, employee_id FROM users ORDER BY username ASC");

?>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Generate Employee QR IDs | Admin</title>
  <link rel="stylesheet" href="assets/css/style.css">
  <script src="https://cdn.jsdelivr.net/npm/qrcodejs@1.0.0/qrcode.min.js"></script>
  <style>
    @media print {
        body * { visibility: hidden; }
        #qr-print-area, #qr-print-area * { visibility: visible; }
        #qr-print-area { position: absolute; left: 0; top: 0; width: 100%; }
        .sidebar, .header, .card:not(#qr-print-area), .action-buttons, .btn { display: none !important; }
        .content { margin-left: 0 !important; width: 100% !important; padding: 0 !important; }
    }
    #qr-code-display {
        text-align: center;
        padding: 2rem;
        border: 1px dashed #ccc;
        border-radius: var(--border-radius);
    }
    #qr-code-container {
        display: inline-block;
        padding: 1rem;
        background: white;
        border-radius: 8px;
        margin-top: 1rem;
    }
  </style>
</head>
<body>
  <?php include 'includes/sidebar.php'; ?>

  <div class="content" id="mainContent">
    <div class="header">
      <div class="hamburger" id="hamburger">☰</div>
      <div><h1>Generate Employee QR IDs</h1></div>
      <div class="theme-toggle-container">
        <span class="theme-label">Dark Mode</span>
        <label class="theme-switch"><input type="checkbox" id="themeToggle"><span class="slider"></span></label>
      </div>
    </div>
    
    <div class="card">
        <h3>User List</h3>
        <p>Select a user to generate their permanent QR code for login verification. The QR code contains their unique Employee ID.</p>
        <div class="table-section">
            <table>
                <thead>
                    <tr><th>Username</th><th>Role</th><th>Employee ID</th><th>Action</th></tr>
                </thead>
                <tbody>
                    <?php if ($users_result->num_rows > 0): ?>
                        <?php while($user = $users_result->fetch_assoc()): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($user['username']); ?></td>
                            <td><?php echo htmlspecialchars($user['role']); ?></td>
                            <td><?php echo htmlspecialchars($user['employee_id'] ?? 'Not Set'); ?></td>
                            <td class="action-buttons">
                                <?php if (!empty($user['employee_id'])): ?>
                                    <button class="btn btn-primary btn-sm generate-qr-btn" data-username="<?php echo htmlspecialchars($user['username']); ?>" data-employeeid="<?php echo htmlspecialchars($user['employee_id']); ?>">Generate QR</button>
                                <?php else: ?>
                                    <span style="color: var(--text-muted-dark);">Set ID First</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr><td colspan="4">No users found.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <div id="qrModal" class="modal">
      <div class="modal-content" style="max-width: 400px;">
        <span class="close-button">&times;</span>
        <div id="qr-print-area">
            <h2 id="qr-modal-title" style="text-align: center; margin-bottom: 0.5rem;">Employee ID</h2>
            <p id="qr-modal-employeeid" style="text-align: center; font-family: monospace; font-size: 1.1rem; color: #64748B;"></p>
            <div id="qr-code-display">
                <div id="qr-code-container"></div>
            </div>
        </div>
        <div class="form-actions" style="justify-content: center;">
            <button type="button" class="btn btn-info" onclick="window.print();">Print ID</button>
        </div>
      </div>
    </div>

  </div>
  
  <script src="assets/js/dark_mode_handler.js" defer></script>
  <script>
    document.addEventListener('DOMContentLoaded', function() {
        // Sidebar and dark mode scripts
        document.getElementById('hamburger').addEventListener('click', function() {
          const sidebar = document.getElementById('sidebar'); const mainContent = document.getElementById('mainContent');
          if (window.innerWidth <= 992) { sidebar.classList.toggle('show'); } 
          else { sidebar.classList.toggle('collapsed'); mainContent.classList.toggle('expanded'); }
        });
        const activeDropdown = document.querySelector('.sidebar .dropdown.active');
        if (activeDropdown) {
            activeDropdown.classList.add('open');
            const menu = activeDropdown.querySelector('.dropdown-menu');
            if (menu) menu.style.maxHeight = menu.scrollHeight + 'px';
        }
        document.querySelectorAll('.sidebar .dropdown-toggle').forEach(function(toggle) {
            toggle.addEventListener('click', function(e) {
                e.preventDefault(); let parent = this.closest('.dropdown'); let menu = parent.querySelector('.dropdown-menu');
                document.querySelectorAll('.sidebar .dropdown.open').forEach(function(otherDropdown) {
                    if (otherDropdown !== parent) { otherDropdown.classList.remove('open'); otherDropdown.querySelector('.dropdown-menu').style.maxHeight = '0'; }
                });
                parent.classList.toggle('open');
                if (parent.classList.contains('open')) { menu.style.maxHeight = menu.scrollHeight + 'px'; } else { menu.style.maxHeight = '0'; }
            });
        });
        
        // Modal logic
        const qrModal = document.getElementById('qrModal');
        const qrCodeContainer = document.getElementById('qr-code-container');
        const qrTitle = document.getElementById('qr-modal-title');
        const qrEmployeeId = document.getElementById('qr-modal-employeeid');
        let qrcode = null;

        document.querySelectorAll('.generate-qr-btn').forEach(button => {
            button.addEventListener('click', function() {
                const username = this.dataset.username;
                const employeeId = this.dataset.employeeid;

                qrTitle.textContent = username;
                qrEmployeeId.textContent = employeeId;
                qrCodeContainer.innerHTML = ""; // Clear previous QR code

                qrcode = new QRCode(qrCodeContainer, {
                    text: employeeId,
                    width: 256,
                    height: 256,
                    colorDark : "#000000",
                    colorLight : "#ffffff",
                    correctLevel : QRCode.CorrectLevel.H
                });

                qrModal.style.display = 'block';
            });
        });
        qrModal.querySelector('.close-button').addEventListener('click', () => {
            qrModal.style.display = 'none';
        });

    });
  </script>
</body>
</html>
