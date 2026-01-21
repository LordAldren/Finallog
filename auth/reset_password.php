<?php
// auth/reset_password.php
session_start();
require_once '../config/db_connect.php';

$message = '';
$msg_type = '';
$valid_token = false;

// Kuhanin ang token mula sa URL
$token = isset($_GET['token']) ? $_GET['token'] : '';

if (empty($token)) {
    // Kapag walang token, ibalik sa login
    header("Location: login.php");
    exit();
}

// 1. I-verify kung valid ang token sa database ('password_resets' table)
$user_id = null;
if ($stmt = $conn->prepare("SELECT user_id, expires_at FROM password_resets WHERE token = ?")) {
    $stmt->bind_param("s", $token);
    $stmt->execute();
    $stmt->store_result();

    if ($stmt->num_rows > 0) {
        $stmt->bind_result($uid, $expires_at);
        $stmt->fetch();

        $expiry_date = new DateTime($expires_at);
        $now = new DateTime();

        if ($now < $expiry_date) {
            $valid_token = true;
            $user_id = $uid;
        } else {
            $message = "This password reset link has expired.";
            $msg_type = "error";
        }
    } else {
        $message = "This password reset link is invalid.";
        $msg_type = "error";
    }
    $stmt->close();
}

// 2. Kapag nag-submit ng bagong password
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $password = $_POST['password'];
    $confirm_password = $_POST['confirm_password'];
    $token_post = $_POST['token']; // Kuhanin ang token mula sa hidden field

    // Validate inputs
    if (empty($token_post)) {
        $message = "Missing token.";
        $msg_type = "error";
    } elseif ($password !== $confirm_password) {
        $message = "Passwords do not match.";
        $msg_type = "error";
    } elseif (strlen($password) < 8) {
        $message = "Password must be at least 8 characters long.";
        $msg_type = "error";
    } else {
        // Validasyon ng Token uli
        $check_stmt = $conn->prepare("SELECT user_id, expires_at FROM password_resets WHERE token = ?");
        $check_stmt->bind_param("s", $token_post);
        $check_stmt->execute();
        $check_stmt->store_result();

        if ($check_stmt->num_rows > 0) {
            $check_stmt->bind_result($chk_uid, $chk_exp);
            $check_stmt->fetch();

            $chk_expiry = new DateTime($chk_exp);
            $chk_now = new DateTime();

            if ($chk_now < $chk_expiry) {
                // Hash the new password
                $hashed_password = password_hash($password, PASSWORD_DEFAULT);

                // Update users password
                $update_stmt = $conn->prepare("UPDATE users SET password = ? WHERE id = ?");
                $update_stmt->bind_param("si", $hashed_password, $chk_uid);

                if ($update_stmt->execute()) {
                    // Delete token
                    $del_stmt = $conn->prepare("DELETE FROM password_resets WHERE token = ?");
                    $del_stmt->bind_param("s", $token_post);
                    $del_stmt->execute();
                    $del_stmt->close();

                    $message = "Your password has been successfully updated!";
                    $msg_type = "success";
                    $valid_token = false;
                } else {
                    $message = "Error updating password. Please try again.";
                    $msg_type = "error";
                }
                $update_stmt->close();
            } else {
                $message = "Token expired. Please request a new link.";
                $msg_type = "error";
            }
        } else {
            $message = "Invalid token. Please request a new link.";
            $msg_type = "error";
        }
        $check_stmt->close();
    }
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reset Password | Logistics System</title>

    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap"
        rel="stylesheet">
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <!-- SweetAlert2 -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css">

    <style>
        /* Variables (Same theme as Forgot Password) */
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
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            width: 100%;
            max-width: 480px;
            padding: 50px 40px;
            border-radius: 20px;
            box-shadow: 0 20px 50px rgba(0, 0, 0, 0.2);
            text-align: center;
            transform: translateY(20px);
            opacity: 0;
            animation: slideUp 0.6s ease forwards;
            position: relative;
        }

        @keyframes slideUp {
            to {
                transform: translateY(0);
                opacity: 1;
            }
        }

        .logo-container {
            margin-bottom: 20px;
            animation: float 6s ease-in-out infinite;
        }

        @keyframes float {

            0%,
            100% {
                transform: translateY(0px);
            }

            50% {
                transform: translateY(-10px);
            }
        }

        .logo-container img {
            height: 70px;
            width: auto;
        }

        h2 {
            color: var(--text-dark);
            font-size: 26px;
            font-weight: 700;
            margin-bottom: 10px;
        }

        p.subtitle {
            color: var(--text-gray);
            font-size: 14px;
            margin-bottom: 30px;
        }

        /* Form Styles */
        .form-group {
            margin-bottom: 20px;
            text-align: left;
        }

        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-size: 13px;
            font-weight: 600;
            color: var(--text-dark);
            margin-left: 10px;
        }

        .input-wrapper {
            position: relative;
        }

        .form-control {
            width: 100%;
            padding: 14px 45px 14px 20px;
            /* Padding right for eye icon */
            border: 2px solid var(--input-border);
            border-radius: 50px;
            font-size: 15px;
            transition: all 0.3s ease;
            outline: none;
            background: #f7fafc;
        }

        .form-control:focus {
            border-color: var(--input-focus);
            background: var(--white);
            box-shadow: 0 0 0 4px rgba(102, 126, 234, 0.1);
        }

        /* Eye Icon for Password Toggle */
        .toggle-password {
            position: absolute;
            right: 20px;
            top: 50%;
            transform: translateY(-50%);
            color: #a0aec0;
            cursor: pointer;
            transition: color 0.3s;
        }

        .toggle-password:hover {
            color: var(--input-focus);
        }

        .btn-submit {
            width: 100%;
            padding: 16px;
            background: var(--btn-gradient);
            color: white;
            border: none;
            border-radius: 50px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.4s ease;
            box-shadow: 0 10px 20px rgba(118, 75, 162, 0.3);
            margin-top: 10px;
            text-transform: uppercase;
            letter-spacing: 1px;
        }

        .btn-submit:hover {
            background: var(--btn-hover);
            transform: translateY(-2px);
            box-shadow: 0 15px 25px rgba(118, 75, 162, 0.4);
        }

        .invalid-feedback {
            color: #e53e3e;
            font-size: 13px;
            margin-top: 5px;
            margin-left: 15px;
            display: none;
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
            transition: color 0.3s;
        }

        .back-link a:hover {
            color: #667eea;
        }
    </style>
</head>

<body>

    <div class="auth-card">
        <div class="logo-container">
            <img src="../assets/images/logo.png" alt="Logistics Logo" onerror="this.style.display='none'">
        </div>

        <h2>Set New Password</h2>
        <p class="subtitle">Please enter your new password below.</p>

        <?php if ($valid_token): ?>
            <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]) . '?token=' . htmlspecialchars($token); ?>"
                method="POST" id="resetForm">
                <input type="hidden" name="token" value="<?php echo htmlspecialchars($token); ?>">

                <div class="form-group">
                    <label>New Password</label>
                    <div class="input-wrapper">
                        <input type="password" name="password" id="password" class="form-control"
                            placeholder="At least 8 characters" required minlength="8">
                        <i class="fas fa-eye toggle-password" onclick="togglePassword('password', this)"></i>
                    </div>
                </div>

                <div class="form-group">
                    <label>Confirm Password</label>
                    <div class="input-wrapper">
                        <input type="password" name="confirm_password" id="confirm_password" class="form-control"
                            placeholder="Repeat new password" required>
                        <i class="fas fa-eye toggle-password" onclick="togglePassword('confirm_password', this)"></i>
                    </div>
                    <div id="match-error" class="invalid-feedback">Passwords do not match</div>
                </div>

                <button type="submit" class="btn-submit">Reset Password</button>
            </form>
        <?php else: ?>
            <div style="margin: 40px 0;">
                <i class="fas fa-link-slash" style="font-size: 50px; color: #cbd5e0; margin-bottom: 20px;"></i>
                <p style="color: #e53e3e; font-weight: 500;"><?php echo $message ?: "Invalid Link"; ?></p>
            </div>
            <a href="forgot_password.php" class="btn-submit"
                style="text-decoration: none; display: inline-block; width: auto; padding: 12px 30px;">Request New Link</a>
        <?php endif; ?>

        <div class="back-link">
            <a href="login.php">
                <i class="fas fa-arrow-left"></i> Back to Login
            </a>
        </div>
    </div>

    <!-- SweetAlert2 Script -->
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

    <script>
        // Toggle Password Visibility
        function togglePassword(inputId, icon) {
            const input = document.getElementById(inputId);
            const type = input.getAttribute('type') === 'password' ? 'text' : 'password';
            input.setAttribute('type', type);

            icon.classList.toggle('fa-eye');
            icon.classList.toggle('fa-eye-slash');
        }

        // Form Validation for Match
        const form = document.getElementById('resetForm');
        if (form) {
            form.addEventListener('submit', function (e) {
                const p1 = document.getElementById('password').value;
                const p2 = document.getElementById('confirm_password').value;
                const errorDiv = document.getElementById('match-error');

                if (p1 !== p2) {
                    e.preventDefault();
                    errorDiv.style.display = 'block';
                } else {
                    errorDiv.style.display = 'none';
                }
            });
        }

        // SweetAlert Messages from PHP
        <?php if (!empty($message) && !$valid_token && $msg_type == 'success'): ?>
            Swal.fire({
                icon: 'success',
                title: 'Success!',
                text: '<?php echo $message; ?>',
                confirmButtonColor: '#667eea',
                allowOutsideClick: false
            }).then((result) => {
                if (result.isConfirmed) {
                    window.location.href = 'login.php';
                }
            });
        <?php elseif (!empty($message) && $msg_type == 'error'): ?>
            Swal.fire({
                icon: 'error',
                title: 'Oops...',
                text: '<?php echo $message; ?>',
                confirmButtonColor: '#e53e3e'
            });
        <?php endif; ?>
    </script>

</body>

</html>