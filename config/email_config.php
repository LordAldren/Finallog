<?php
// email_config.php

// === GMAIL SMTP CONFIGURATION ===
// Palitan ang mga value sa ibaba ng iyong sariling credentials.
// TANDAAN: Gamitin ang "App Password" na galing sa iyong Google Account, HINDI ang iyong regular na password.
// Paano kumuha ng App Password: https://support.google.com/accounts/answer/185833

define('MAIL_HOST', 'smtp.gmail.com');
define('MAIL_PORT', 587);
define('MAIL_USERNAME', 'akoposirenel@gmail.com');      // <--- PALITAN ITO
define('MAIL_PASSWORD', 'yuqkncnrajnvbsvd'); // <--- PALITAN ITO (gamitin ang App Password)
define('MAIL_ENCRYPTION', 'tls');

// --- Sender Details ---
// Ito ang lalabas na pangalan at email address sa "From" field ng email.
define('MAIL_FROM_ADDRESS', 'akoposirenel@gmail.com'); // <--- PALITAN ITO
define('MAIL_FROM_NAME', 'SLATE Logistics System');
?>

