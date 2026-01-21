<?php
// dev_hash_generator.php
// WARNING: This is a developer tool and should NOT be accessible in a production environment.
// Gamitin ang file na ito para manual na gumawa ng password hash para sa testing o initial admin setup.

// 1. Ilagay ang password na gusto mong i-hash sa loob ng single quotes.
$passwordToHash = 'driver123';

// 2. I-save ang file na ito.
// 3. Buksan ito sa iyong browser (e.g., http://localhost/slate/CODE FINAL 3/dev_hash_generator.php)
// 4. Kopyahin ang "Generated Hash" na lalabas.
// 5. Pumunta sa phpMyAdmin, hanapin ang user na gusto mong palitan ng password, at i-paste ang kinopya mong hash sa 'password' column.

// --- WALA NANG DAPAT BAGUHIN SA IBABA NITO ---

echo "<div style='font-family: sans-serif; padding: 2rem; background-color: #f8f9fa; border: 1px solid #dee2e6; margin: 2rem;'>";
echo "<h1 style='color: #dc3545;'>DEVELOPER TOOL - FOR ADMIN USE ONLY</h1>";
echo "<p style='border-left: 4px solid #ffc107; padding-left: 1rem; background-color: #fff3cd;'><b>Security Warning:</b> This script is for development purposes only. Ensure this file is deleted or made inaccessible on a live production server.</p>";

if (empty($passwordToHash) || $passwordToHash === 'ang-bago-mong-password') {
    echo "<p style='color:red;'><b>ACTION NEEDED:</b> Please open the `dev_hash_generator.php` file and change the value of the <b>\$passwordToHash</b> variable on line 7 to your desired new password.</p>";
} else {
    $hashedPassword = password_hash($passwordToHash, PASSWORD_DEFAULT);

    echo "<h2>Password Hash Generation</h2>";
    echo "<p><strong>Password to Hash:</strong> " . htmlspecialchars($passwordToHash) . "</p>";
    echo "<p><strong>Generated Hash (Copy this value):</strong></p>";
    echo "<textarea rows='4' cols='80' readonly style='font-size: 1rem; padding: 10px; border-radius: 5px; border: 1px solid #ced4da;'>" . htmlspecialchars($hashedPassword) . "</textarea>";
    echo "<p style='margin-top: 1rem;'>Now, go to your database, find the user, and paste this hash into their 'password' field.</p>";
}
echo "</div>";
?>