<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Terms of Service | Logistics System</title>
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;600;700&display=swap" rel="stylesheet">
    
    <style>
        body {
            font-family: 'Poppins', sans-serif;
            background-color: #f4f7f6;
            color: #333;
            line-height: 1.6;
        }

        .hero-section {
            background: linear-gradient(135deg, #1e3c72 0%, #2a5298 100%);
            color: white;
            padding: 60px 0;
            margin-bottom: 40px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
        }

        .hero-title {
            font-weight: 700;
            font-size: 2.5rem;
            margin-bottom: 10px;
        }

        .hero-subtitle {
            font-weight: 300;
            font-size: 1.1rem;
            opacity: 0.9;
        }

        .content-card {
            background: white;
            border-radius: 15px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.05);
            padding: 40px;
            margin-bottom: 40px;
            border-left: 5px solid #1e3c72;
        }

        .section-title {
            color: #1e3c72;
            font-weight: 600;
            margin-top: 30px;
            margin-bottom: 15px;
            font-size: 1.5rem;
            border-bottom: 1px solid #eee;
            padding-bottom: 10px;
        }

        .section-title i {
            margin-right: 10px;
            color: #2a5298;
        }

        .last-updated {
            font-size: 0.9rem;
            color: #777;
            margin-bottom: 20px;
            font-style: italic;
        }

        .btn-back {
            background-color: white;
            color: #1e3c72;
            border: 2px solid white;
            font-weight: 600;
            padding: 10px 25px;
            border-radius: 30px;
            transition: all 0.3s ease;
        }

        .btn-back:hover {
            background-color: transparent;
            color: white;
            text-decoration: none;
        }

        ul.terms-list {
            padding-left: 20px;
        }

        ul.terms-list li {
            margin-bottom: 10px;
            position: relative;
            list-style-type: none;
        }

        ul.terms-list li::before {
            content: "\f00c";
            font-family: "Font Awesome 6 Free";
            font-weight: 900;
            color: #28a745;
            position: absolute;
            left: -25px;
            top: 2px;
        }

        footer {
            background-color: #1e3c72;
            color: white;
            padding: 20px 0;
            text-align: center;
            margin-top: auto;
        }

        /* Scrollbar styling */
        ::-webkit-scrollbar {
            width: 10px;
        }
        ::-webkit-scrollbar-track {
            background: #f1f1f1;
        }
        ::-webkit-scrollbar-thumb {
            background: #888;
            border-radius: 5px;
        }
        ::-webkit-scrollbar-thumb:hover {
            background: #555;
        }
    </style>
</head>
<body>

    <!-- Hero Header -->
    <header class="hero-section">
        <div class="container">
            <div class="row align-items-center">
                <div class="col-md-8">
                    <h1 class="hero-title">Terms of Service</h1>
                    <p class="hero-subtitle">Please read these terms carefully before using our Logistics & Dispatch System.</p>
                </div>
                <div class="col-md-4 text-md-end text-center mt-3 mt-md-0">
                    <a href="auth/login.php" class="btn btn-back"><i class="fas fa-arrow-left me-2"></i> Back to Login</a>
                </div>
            </div>
        </div>
    </header>

    <!-- Main Content -->
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-lg-10">
                <div class="content-card">
                    <p class="last-updated">Last Updated: January 2025</p>

                    <div class="terms-content">
                        <p>Welcome to our Vehicle Reservation and Dispatch System. By accessing or using our platform, you agree to be bound by these Terms of Service and our Privacy Policy.</p>

                        <h2 class="section-title"><i class="fas fa-gavel"></i> 1. Acceptance of Terms</h2>
                        <p>By accessing this system, you confirm that you have read, understood, and agree to be bound by these terms. If you do not agree with any part of these terms, you may not use our services.</p>

                        <h2 class="section-title"><i class="fas fa-user-shield"></i> 2. User Accounts & Security</h2>
                        <p>To access certain features of the platform, you may be required to register for an account. You are responsible for:</p>
                        <ul class="terms-list">
                            <li>Maintaining the confidentiality of your account credentials (username, password, and QR codes).</li>
                            <li>All activities that occur under your account.</li>
                            <li>Notifying the administrators immediately of any unauthorized use of your account.</li>
                            <li>Ensuring that your account information is accurate and up-to-date.</li>
                        </ul>

                        <h2 class="section-title"><i class="fas fa-truck-moving"></i> 3. Vehicle Usage & Reservations</h2>
                        <p>For users authorized to book vehicles:</p>
                        <ul class="terms-list">
                            <li>All vehicle reservations are subject to availability and approval by the dispatch team.</li>
                            <li>Vehicles must be used solely for official business purposes unless explicitly authorized otherwise.</li>
                            <li>Users must adhere to the scheduled pick-up and return times. Delays must be reported immediately.</li>
                            <li>Proper care of the vehicle is required. Any damage or issues must be reported instantly via the system.</li>
                        </ul>

                        <h2 class="section-title"><i class="fas fa-map-marked-alt"></i> 4. Tracking & Monitoring</h2>
                        <p>Our system employs GPS tracking and telematics to monitor vehicle location, driver behavior, and route adherence. By using a company vehicle or the driver application:</p>
                        <ul class="terms-list">
                            <li>You consent to the real-time tracking of your location while on duty.</li>
                            <li>You acknowledge that data regarding speed, route, and stops will be recorded for operational efficiency and safety.</li>
                        </ul>

                        <h2 class="section-title"><i class="fas fa-ban"></i> 5. Prohibited Conduct</h2>
                        <p>Users agree not to:</p>
                        <ul>
                            <li>Use the system for any illegal or unauthorized purpose.</li>
                            <li>Attempt to hack, destabilize, or adapt the system's code.</li>
                            <li>Harass, abuse, or harm another person through the platform.</li>
                            <li>Submit false information regarding trip logs, expenses, or vehicle status.</li>
                        </ul>

                        <h2 class="section-title"><i class="fas fa-exclamation-circle"></i> 6. Limitation of Liability</h2>
                        <p>The system administrators and the organization shall not be liable for any indirect, incidental, special, consequential, or punitive damages resulting from your access to or use of, or inability to access or use, the services.</p>

                        <h2 class="section-title"><i class="fas fa-sync-alt"></i> 7. Changes to Terms</h2>
                        <p>We reserve the right to modify these terms at any time. We will provide notice of significant changes by posting the new terms on this site. Your continued use of the system constitutes acceptance of those changes.</p>
                        
                        <div class="mt-5 text-center">
                            <p class="text-muted">If you have any questions about these Terms, please contact the IT Support Department.</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Footer -->
    <footer>
        <div class="container">
            <p class="mb-0">&copy; 2025 Logistics & Dispatch System. All Rights Reserved.</p>
            <small><a href="privacy.php" class="text-white text-decoration-none">Privacy Policy</a> | <a href="terms.php" class="text-white text-decoration-none">Terms of Service</a></small>
        </div>
    </footer>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>