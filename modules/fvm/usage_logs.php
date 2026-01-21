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
  header('Content-Disposition: attachment; filename=usage_logs_' . date('Y-m-d') . '.csv');
  $output = fopen('php://output', 'w');
  fputcsv($output, ['Log ID', 'Vehicle', 'Date', 'Metrics', 'Fuel Usage (L)', 'Mileage (km)']);

  $result = $conn->query("SELECT u.*, v.type, v.model FROM usage_logs u JOIN vehicles v ON u.vehicle_id = v.id ORDER BY u.log_date DESC");
  if ($result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
      fputcsv($output, [
        $row['id'],
        $row['type'] . ' ' . $row['model'],
        $row['log_date'],
        $row['metrics'],
        $row['fuel_usage'],
        $row['mileage']
      ]);
    }
  }
  fclose($output);
  exit;
}
// --- WAKAS NG CSV DOWNLOAD LOGIC ---


$usage_logs_result = $conn->query("SELECT u.*, v.type, v.model FROM usage_logs u JOIN vehicles v ON u.vehicle_id = v.id ORDER BY u.log_date DESC");
?>
<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Usage Logs | FVM</title>
  <link rel="stylesheet" href="../../assets/css/style.css">
</head>

<body>
  <?php include '../../includes/sidebar.php'; ?>

  <div class="content" id="mainContent">
    <div class="header">
      <div class="hamburger" id="hamburger">☰</div>
      <div>
        <h1>Usage Logs</h1>
      </div>
      <div class="theme-toggle-container">
        <span class="theme-label">Dark Mode</span>
        <label class="theme-switch"><input type="checkbox" id="themeToggle"><span class="slider"></span></label>
      </div>
    </div>

    <?php echo $message; ?>

    <div class="card" id="usage-logs">
      <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1rem;">
        <h3>Vehicle Usage History</h3>
        <a href="usage_logs.php?download_csv=true" class="btn btn-success">Download CSV</a>
      </div>
      <div class="table-section">
        <table>
          <thead>
            <tr>
              <th>VEHICLE NAME</th>
              <th>DATE</th>
              <th>METRICS</th>
              <th>FUEL (L)</th>
              <th>MILEAGE (km)</th>
            </tr>
          </thead>
          <tbody>
            <?php if ($usage_logs_result->num_rows > 0): ?>
              <?php while ($row = $usage_logs_result->fetch_assoc()): ?>
                <tr>
                  <td><?php echo htmlspecialchars($row['type'] . ' ' . $row['model']); ?></td>
                  <td><?php echo htmlspecialchars($row['log_date']); ?></td>
                  <td><?php echo htmlspecialchars($row['metrics']); ?></td>
                  <td><?php echo htmlspecialchars($row['fuel_usage']); ?></td>
                  <td><?php echo htmlspecialchars($row['mileage']); ?></td>
                </tr>
              <?php endwhile; ?>
            <?php else: ?>
              <tr>
                <td colspan="5">No usage logs found.</td>
              </tr>
            <?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>

  <script src="../../assets/js/dark_mode_handler.js" defer></script>
</body>

</html>