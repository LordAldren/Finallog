<?php
// auth/forgot_password.php
session_start();

// Initialize variables
$message = '';
$msg_type = '';

require_once '../config/db_connect.php';

// PHPMailer Configuration
if (file_exists('../PHPMailer/Exception.php')) {
    require '../PHPMailer/Exception.php';
    require '../PHPMailer/PHPMailer.php';
    require '../PHPMailer/SMTP.php';
} else {
    error_log("PHPMailer files not found.");
}

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $email = trim($_POST['email']);

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $message = "Invalid email format.";
        $msg_type = "error";
    } else {
        // Check if email exists
        if ($stmt = $conn->prepare("SELECT id FROM users WHERE email = ?")) {
            $stmt->bind_param("s", $email);
            $stmt->execute();
            $stmt->store_result();

            if ($stmt->num_rows > 0) {
                $stmt->bind_result($user_id);
                $stmt->fetch();

                // Generate secure token
                $token = bin2hex(random_bytes(32));
                $expires_at = date("Y-m-d H:i:s", strtotime('+1 hour'));

                // Insert into password_resets table
                // First, delete any existing tokens for this user to prevent clutter
                $del_stmt = $conn->prepare("DELETE FROM password_resets WHERE user_id = ?");
                $del_stmt->bind_param("i", $user_id);
                $del_stmt->execute();
                $del_stmt->close();

                if ($insert_stmt = $conn->prepare("INSERT INTO password_resets (user_id, token, expires_at) VALUES (?, ?, ?)")) {
                    $insert_stmt->bind_param("iss", $user_id, $token, $expires_at);

                    if ($insert_stmt->execute()) {
                        // Send Email via PHPMailer
                        $mail = new PHPMailer(true);
                        try {
                            // SMTP Settings
                            $mail->isSMTP();
                            $mail->Host = 'smtp.gmail.com';
                            $mail->SMTPAuth = true;
                            $mail->Username = 'akoposirenel@gmail.com'; // Credentials
                            $mail->Password = 'qxwaxhtadsioxovp';    // Credentials
                            $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
                            $mail->Port = 587;

                            // Localhost Fix for SSL
                            $mail->SMTPOptions = array(
                                'ssl' => array(
                                    'verify_peer' => false,
                                    'verify_peer_name' => false,
                                    'allow_self_signed' => true
                                )
                            );

                            $mail->setFrom('no-reply@logistics.com', 'Logistics Security');
                            $mail->addAddress($email);

                            // Construct Link
                            $protocol = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') ? "https" : "http";
                            $resetLink = $protocol . "://" . $_SERVER['HTTP_HOST'] . dirname($_SERVER['PHP_SELF']) . "/reset_password.php?token=" . $token;

                            $mail->isHTML(true);
                            $mail->Subject = 'Reset Your Password';

                            // Modern Email Body
                            $mail->Body = "
                                <div style='font-family: \"Segoe UI\", Tahoma, Geneva, Verdana, sans-serif; padding: 40px; background-color: #f4f7f6;'>
                                    <div style='background: white; padding: 40px; border-radius: 12px; box-shadow: 0 4px 15px rgba(0,0,0,0.05); max-width: 500px; margin: 0 auto; text-align: center;'>
                                        <h2 style='color: #667eea; margin-bottom: 20px;'>Password Reset Request</h2>
                                        <p style='font-size: 16px; line-height: 1.6; color: #555;'>We received a request to reset your password. Don't worry, we got you covered! Click the button below to set a new password.</p>
                                        <div style='margin: 30px 0;'>
                                            <a href='$resetLink' style='background-color: #667eea; color: white; padding: 15px 30px; text-decoration: none; border-radius: 30px; font-weight: bold; font-size: 16px; box-shadow: 0 4px 10px rgba(102, 126, 234, 0.4);'>Reset Password</a>
                                        </div>
                                        <p style='color: #999; font-size: 14px;'>Link expires in 1 hour. If you did not request this, you can safely ignore this email.</p>
                                    </div>
                                </div>
                            ";

                            $mail->send();
                            $message = "A password reset link has been sent to your email address.";
                            $msg_type = "success";
                        } catch (Exception $e) {
                            $message = "Message could not be sent. Mailer Error: {$mail->ErrorInfo}";
                            $msg_type = "error";
                        }
                    } else {
                        $message = "Failed to update database.";
                        $msg_type = "error";
                    }
                    $insert_stmt->close();
                }
            } else {
                // Security: Generic success message
                $message = "If this email is registered, we have sent a reset link.";
                $msg_type = "success";
            }
            $stmt->close();
        } else {
            $message = "Database error: Unable to prepare statement.";
            $msg_type = "error";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Forgot Password | Logistics System</title>

    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap"
        rel="stylesheet">
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <!-- SweetAlert2 CSS -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css">

    <style>
        /* Modern Variables */
        :root {
            --primary-gradient: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            --btn-gradient: linear-gradient(to right, #667eea, #764ba2);
            --btn-hover: linear-gradient(to right, #764ba2, #667eea);
            --text-dark: #2d3748;
            --text-gray: #718096;
            --white: #ffffff;
            --input-border: #e2e8f0;
            --input-focus: #667eea;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Poppins', sans-serif;
        }

        body {
            /* Animated Gradient Background */
            background: linear-gradient(135deg, #0f2027, #203a43, #2c5364);
            background-size: 400% 400%;
            animation: gradientBG 15s ease infinite;
            height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }

        @keyframes gradientBG {
            0% {
                background-position: 0% 50%;
            }

            50% {
                background-position: 100% 50%;
            }

            100% {
                background-position: 0% 50%;
            }
        }

        .auth-card {
            background: rgba(22, 33, 49, 0.95);
            backdrop-filter: blur(10px);
            /* Glassmorphism effect */
            width: 100%;
            max-width: 480px;
            padding: 50px 40px;
            border-radius: 20px;
            box-shadow: 0 20px 50px rgba(0, 0, 0, 0.2);
            text-align: center;
            transform: translateY(20px);
            opacity: 0;
            animation: slideUp 0.6s ease forwards;
        }

        @keyframes slideUp {
            to {
                transform: translateY(0);
                opacity: 1;
            }
        }

        .logo-container {
            margin-bottom: 30px;
            animation: float 6s ease-in-out infinite;
        }

        @keyframes float {
            0% {
                transform: translateY(0px);
            }

            50% {
                transform: translateY(-10px);
            }

            100% {
                transform: translateY(0px);
            }
        }

        .logo-container img {
            height: 80px;
            width: auto;
            filter: drop-shadow(0 4px 6px rgba(0, 0, 0, 0.1));
        }

        .auth-header h2 {
            color: white;
            font-size: 28px;
            font-weight: 700;
            margin-bottom: 15px;
            letter-spacing: -0.5px;
        }

        .auth-header p {
            color: var(--text-gray);
            font-size: 15px;
            margin-bottom: 40px;
            line-height: 1.6;
        }

        /* Form Styles */
        .form-group {
            margin-bottom: 25px;
            text-align: left;
        }

        .input-group {
            position: relative;
        }

        .input-group i {
            position: absolute;
            left: 20px;
            top: 50%;
            transform: translateY(-50%);
            color: #a0aec0;
            transition: color 0.3s;
            font-size: 18px;
        }

        .form-control {
            width: 100%;
            padding: 16px 20px 16px 55px;
            /* Space for icon */
            border: 2px solid var(--input-border);
            border-radius: 50px;
            /* Pill shape */
            font-size: 16px;
            transition: all 0.3s ease;
            outline: none;
            background: #f7fafc;
            color: var(--text-dark);
        }

        .form-control:focus {
            border-color: var(--input-focus);
            background: var(--white);
            box-shadow: 0 0 0 4px rgba(102, 126, 234, 0.1);
        }

        .form-control:focus+i {
            color: var(--input-focus);
        }

        .btn-submit {
            width: 100%;
            padding: 16px;
            background: #3B82F6;
            color: white;
            border: none;
            border-radius: 50px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.4s ease;
            display: flex;
            justify-content: center;
            align-items: center;
            gap: 12px;
            box-shadow: rgba(22, 33, 49, 0.95);
            text-transform: uppercase;
            letter-spacing: 1px;
        }

        .btn-submit:hover {
            background: rgba(22, 33, 49, 0.95);
            transform: translateY(-2px);
            box-shadow: rgba(22, 33, 49, 0.95);
        }

        .btn-submit:active {
            transform: translateY(0);
        }

        .btn-submit:disabled {
            background: rgba(22, 33, 49, 0.95);
            cursor: not-allowed;
            transform: none;
            box-shadow: none;
        }

        .back-link {
            margin-top: 30px;
            display: block;
        }

        .back-link a {
            color: var(--text-gray);
            text-decoration: none;
            font-size: 14px;
            font-weight: 500;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            transition: all 0.3s;
            padding: 8px 16px;
            border-radius: 20px;
        }

        .back-link a:hover {
            color: #667eea;
            background: rgba(102, 126, 234, 0.1);
        }
    </style>
</head>

<body>

    <div class="auth-card">
        <div class="logo-container">
            <!-- Ensure path matches your folder structure -->
            <img src="../assets/images/logo.png" alt="Logistics Logo" onerror="this.style.display='none'">
        </div>

        <div class="auth-header">
            <h2>Forgot Password?</h2>
            <p>Enter your registered email address and we'll send you a link to reset your password.</p>
        </div>

        <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="POST" id="forgotForm">
            <div class="form-group">
                <div class="input-group">
                    <input type="email" name="email" class="form-control" placeholder="Email Address" required>
                    <i class="fas fa-envelope"></i> <!-- Icon moved after input for CSS selector -->
                </div>
            </div>

            <button type="submit" class="btn-submit" id="submitBtn">
                <span id="btnText">Send Reset Link</span>
                <i class="fas fa-paper-plane" id="btnIcon"></i>
            </button>
        </form>

        <div class="back-link">
            <a href="login.php">
                <i class="fas fa-arrow-left"></i> Back to Login
            </a>
        </div>
    </div>

    <!-- SweetAlert2 Script -->
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

    <script>
        // Check PHP variables for messages
        <?php if (!empty($message)): ?>
            Swal.fire({
                icon: '<?php echo $msg_type; ?>',
                title: '<?php echo ($msg_type == "success") ? "Check your Inbox!" : "Oops..."; ?>',
                text: '<?php echo $message; ?>',
                confirmButtonColor: '#667eea',
                confirmButtonText: 'OK',
                background: '#fff',
                borderRadius: '15px'
            });
        <?php endif; ?>

        // Loading State for Button
        const form = document.getElementById('forgotForm');
        const submitBtn = document.getElementById('submitBtn');
        const btnText = document.getElementById('btnText');
        const btnIcon = document.getElementById('btnIcon');

        if (form) {
            form.addEventListener('submit', function () {
                // Change button look to indicate loading
                submitBtn.disabled = true;
                btnText.textContent = 'Sending...';
                btnIcon.className = 'fas fa-spinner fa-spin'; // Spin icon
            });
        }
    </script>
</body>

</html>