<?php
session_start();
require_once '../config/db_connect.php';

// Set header to return JSON
header('Content-Type: application/json');

// Get the posted data
$data = json_decode(file_get_contents("php://input"));

// Check if user is authenticated for the second step
if (!isset($_SESSION['verification_user_id'])) {
    echo json_encode(['status' => 'error', 'message' => 'Not authenticated for QR scan. Please log in again.']);
    exit;
}

$user_id = $_SESSION['verification_user_id'];
$employee_id = isset($data->employee_id) ? $data->employee_id : null;

if (empty($employee_id)) {
    echo json_encode(['status' => 'error', 'message' => 'No QR code data received.']);
    exit;
}

// Check if the scanned employee ID matches the logged-in user's ID
$sql = "SELECT id, username, role FROM users WHERE employee_id = ? AND id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("si", $employee_id, $user_id);
$stmt->execute();
$result = $stmt->get_result();

if ($user = $result->fetch_assoc()) {
    // If the QR code matches the user, complete the login
    unset($_SESSION['verification_user_id']); // Clear the temporary session
    session_regenerate_id(true); // Secure the session

    $_SESSION["loggedin"] = true;
    $_SESSION["id"] = $user['id'];
    $_SESSION["username"] = $user['username'];
    $_SESSION["role"] = $user['role'];

    echo json_encode(['status' => 'success', 'role' => $user['role']]);
} else {
    // The scanned QR code does not belong to the user who entered the password
    echo json_encode(['status' => 'error', 'message' => 'Invalid Employee ID or QR code mismatch.']);
}

$stmt->close();
$conn->close();
?>
