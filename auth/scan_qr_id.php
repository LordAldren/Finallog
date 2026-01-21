<?php
session_start();
// This page requires a user to have passed the first step of login
if (!isset($_SESSION['verification_user_id'])) {
    header("location: login.php");
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
    <title>Scan Employee QR ID - SLATE System</title>
    <link rel="stylesheet" href="../assets/css/login-style.css">
</head>
<body class="login-page-body">
    <div class="main-container">
        <div class="login-container" style="max-width: 35rem;">
            <div class="login-panel" style="width: 100%;">
                <div class="login-box">
                    <img src="../assets/images/logo.png" alt="SLATE Logo">
                    <h2>Scan Employee ID</h2>
                    <p style="margin-bottom: 1rem; color: #ccc;">Please present your employee QR code to the camera.</p>
                    
                    <div id="qr-reader" style="width:100%;"></div>
                    <div id="qr-reader-status" style="margin-top: 1rem; font-weight: 500;"></div>

                    <div style="margin-top: 1.5rem;">
                        <a href="logout.php" class="back-link">&larr; Cancel and go back to Login</a>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- QR Code Scanning Library -->
    <script src="https://unpkg.com/html5-qrcode" type="text/javascript"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const statusDiv = document.getElementById('qr-reader-status');

            function onScanSuccess(decodedText, decodedResult) {
                console.log(`Code matched = ${decodedText}`, decodedResult);
                statusDiv.innerHTML = `<span style="color: #1cc88a;">QR Code detected. Verifying...</span>`;
                
                if (window.html5QrcodeScanner) {
                    window.html5QrcodeScanner.clear().catch(error => console.warn("QR scanner clear failed", error));
                }
                
                verifyScan(decodedText);
            }

            function verifyScan(employeeId) {
                const verificationUrl = 'verify_qr_scan.php';

                fetch(verificationUrl, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ employee_id: employeeId })
                })
                .then(response => {
                    if (!response.ok) {
                        throw new Error(`Network response was not ok: ${response.statusText} (Status: ${response.status})`);
                    }
                    return response.json();
                })
                .then(data => {
                    if (data.status === 'success') {
                        statusDiv.innerHTML = `<span style="color: #1cc88a;">Verification successful! Redirecting...</span>`;
                        window.location.href = data.role === 'driver' ? '../modules/mfc/mobile_app.php' : '../landpage.php';
                    } else {
                        statusDiv.innerHTML = `<span style="color: #e74a3b;">Error: ${data.message || 'Unknown error.'} Please try again.</span>`;
                        setTimeout(() => { window.location.reload(); }, 3000);
                    }
                })
                .catch(error => {
                    console.error('Verification fetch error:', error);
                    statusDiv.innerHTML = `<span style="color: #e74a3b;">An error occurred during verification. Please try again.</span>`;
                });
            }

            function onScanFailure(error) {
                if (!error.includes("No QR code found")) {
                    // console.warn(`QR scan error: ${error}`); // This can be noisy
                }
            }
            
            Html5Qrcode.getCameras().then(cameras => {
                if (cameras && cameras.length) {
                    statusDiv.innerHTML = `<span style="color: #00c6ff;">Initializing camera...</span>`;
                    window.html5QrcodeScanner = new Html5QrcodeScanner(
                        "qr-reader", 
                        { fps: 10, qrbox: {width: 250, height: 250} }, 
                        /* verbose= */ false
                    );
                    html5QrcodeScanner.render(onScanSuccess, onScanFailure);
                } else {
                    statusDiv.innerHTML = `<span style="color: #e74a3b;">No camera found or permission denied. Please allow camera access and refresh the page.</span>`;
                }
            }).catch(err => {
                console.error("Camera access error:", err);
                statusDiv.innerHTML = `<span style="color: #e74a3b;">Could not access camera. Please check permissions.</span>`;
            });
        });
    </script>
</body>
</html>

