<?php
require_once './config/db_connection.php';

session_start(); // Start session at the top

$error = null; // Initialize error variable

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // First step: QR Code validation
    if (isset($_POST['qr-code']) && !isset($_POST['password'])) {
        $qrCode = $_POST['qr-code'];

        $stmt = $dbConnection->prepare("SELECT `generated_code`, `firstName`, `userID`, `userType`, `password`, `archived` FROM `users` WHERE `generated_code` = :generated_code");
        $stmt->bindParam(':generated_code', $qrCode);
        $stmt->execute();

        $accountExist = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($accountExist) {
            if ($accountExist['archived'] == 1) {
                $error = "Your account has been archived.";
            } else {
                // Store user data in session temporarily
                $_SESSION['temp_user'] = [
                    'userID' => $accountExist['userID'],
                    'firstName' => $accountExist['firstName'],
                    'userType' => $accountExist['userType'],
                    'password_hash' => $accountExist['password'] // Assuming password is stored hashed
                ];
                // Show password form (handled in HTML below)
            }
        } else {
            $error = "QR Code account doesn't exist!";
        }
    }
    // Second step: Password validation
    elseif (isset($_POST['password']) && isset($_SESSION['temp_user'])) {
        // Check if terms are agreed
        if (!isset($_POST['terms_agreed']) || $_POST['terms_agreed'] !== 'on') {
            $error = "You must accept the Terms and Conditions to log in.";
        } else {
            $enteredPassword = $_POST['password'];
            $storedPasswordHash = $_SESSION['temp_user']['password_hash'];

            // Verify password (assuming it's hashed with password_hash())
            if (password_verify($enteredPassword, $storedPasswordHash)) {
                // Move temp user data to main session
                $_SESSION['userID'] = $_SESSION['temp_user']['userID'];
                $_SESSION['firstName'] = $_SESSION['temp_user']['firstName'];
                $_SESSION['userType'] = $_SESSION['temp_user']['userType'];

                // Set cookie
                $cookieName = "rememberMe";
                $cookieValue = $_SESSION['userID'];
                $cookieExpiry = time() + (30 * 24 * 60 * 60);
                setcookie($cookieName, $cookieValue, $cookieExpiry, "/");

                // Log login history
                $loginTime = date('Y-m-d H:i:s');
                $ipAddress = $_SERVER['REMOTE_ADDR'];
                $deviceInfo = $_SERVER['HTTP_USER_AGENT'];
                $loginMethod = 'qr';

                $insertHistoryQuery = "INSERT INTO login_history (userID, login_time, ip_address, device_info, login_method) 
                                      VALUES (:userID, :loginTime, :ipAddress, :deviceInfo, :loginMethod)";
                $historyStmt = $dbConnection->prepare($insertHistoryQuery);
                $historyStmt->bindParam(':userID', $_SESSION['userID'], PDO::PARAM_INT);
                $historyStmt->bindParam(':loginTime', $loginTime, PDO::PARAM_STR);
                $historyStmt->bindParam(':ipAddress', $ipAddress, PDO::PARAM_STR);
                $historyStmt->bindParam(':deviceInfo', $deviceInfo, PDO::PARAM_STR);
                $historyStmt->bindParam(':loginMethod', $loginMethod, PDO::PARAM_STR);
                $historyStmt->execute();

                // Redirect based on userType
                switch ($_SESSION['userType']) {
                    case 'Student':
                        header("Location: ./pages/students/studentDash.php");
                        break;
                    case 'Professor':
                        header("Location: ./pages/professors/professor_main_dash.php");
                        break;
                    case 'Admin':
                        header("Location: ./pages/admins/adminDash.php");
                        break;
                    case 'SuperAdmin':
                        header("Location: ./pages/superadmins/superAdminDash.php");
                        break;
                    default:
                        $error = "Invalid user type.";
                        unset($_SESSION['temp_user']);
                        break;
                }
                if (!isset($error)) {
                    unset($_SESSION['temp_user']); // Clean up on success
                    exit;
                }
            } else {
                $error = "Incorrect password!";
                unset($_SESSION['temp_user']); // Clean up on failure
            }
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>DIHS - Login</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
    <link rel="icon" href="./img/learnify-logo.png" type="image/x-icon">
    <style>
        :root {
            --primary: #2B6CB0;
            --primary-dark: #1E4A7A;
            --background: #F7FAFC;
            --text: #1A202C;
            --text-secondary: #4A5568;
            --border: #E2E8F0;
            --white: #FFFFFF;
            --shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06);
            --transition: all 0.3s ease;
        }

        ::selection {
            color: #fff;
            background-color: #1877F2;
        }
        body {
            background-color: #eee;
            background-image: url("https://www.transparenttextures.com/patterns/white-wall-3.png");
            font-family: Arial, sans-serif;
            margin: 0;
            padding: 0;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            align-items: center;
            min-height: 100vh;
        }
        .main-container {
            display: flex;
            flex-direction: row;
            border-radius: 10px;
            width: 90%;
            max-width: 1200px;
            overflow: hidden;
            margin: 90px auto;
        }
         .image-container {
            padding: 60px 50px;
            display: flex;
            flex: 1.2;
            align-items: flex-start;
            justify-content: flex-start;
        }

        .fb-style-left h1 {
            font-size: clamp(4rem, 10vw, 6.5rem);
            font-weight: 900;
            color: var(--primary);
            margin: 0 0 20px 0;
            letter-spacing: 1px;
            line-height: 1;
        }

        .fb-style-left p {
            font-size: clamp(1.5rem, 4vw, 2.2rem);
            color: var(--text-secondary);
            margin: 0;
            max-width: 520px;
            line-height: 1.4;
            font-weight: 400;
            opacity: 0.95;
        }

        @media (max-width: 768px) {
            .image-container {
                padding: 40px 30px;
            }
            .fb-style-left h1 {
                font-size: clamp(3rem, 9vw, 5rem);
            }
            .fb-style-left p {
                font-size: clamp(1.2rem, 4vw, 1.6rem);
            }
        }

        .login-form-wrapper {
            flex: 1;
            text-align: center;
            display: flex;
            flex-direction: column;
            justify-content: center;
            height: auto;
        }

        .login-form-container {
            background-color: #F9F9F9;
            padding: 20px;
            border-radius: 10px;
        }
        .title-container {
            display: flex;
            justify-content: space-between;
            align-items: center;
            width: 100%;
            margin-bottom: 20px;
            border-bottom: 1px solid #ccc;
        }

        .title-left {
            text-align: left;
            color: var(--text-secondary);
        }

        .title-right img {
            max-width: 90px;
            height: auto;
        }

        .title-left h2 {
            margin-bottom: 10px;
            font-size: clamp(2rem, 3vw, 2.5rem);
            text-transform: uppercase;
            color: var(--primary);
            font-weight: 900;
        }

        .title-left h3 {
            color: var(--text-secondary);
            font-size: clamp(1.1rem, 3vw, 1.3rem);
        }

        .input-group {
            margin-bottom: 20px;
            text-align: left;
            position: relative;
        }

        .input-wrapper {
            position: relative;
            width: 100%;
        }

        .input-group label {
            position: absolute;
            top: 50%;
            left: 12px;
            transform: translateY(-50%);
            font-size: clamp(1.1rem, 3vw, 1.2rem);
            color: var(--text);
            pointer-events: none;
            transition: all 0.2s ease;
            font-weight: normal;
        }

        .input-group label span {
            color: red;
        }

        .input-group input {
            width: 100%;
            padding: 12px;
            font-size: clamp(1.1rem, 3vw, 1.3rem);
            border: 1px solid #ccc;
            border-radius: 5px;
            box-sizing: border-box;
            background: transparent;
        }

        .input-group input:focus,
        .input-group input:not(:placeholder-shown) {
            border-color: var(--primary);
            outline: none;
        }

        .input-group input:focus + label,
        .input-group input:not(:placeholder-shown) + label {
            top: 0;
            left: 12px;
            font-size: clamp(1rem, 2vw, 1.1rem);
            color: var(--primary);
            background: #F9F9F9;
            padding: 0 5px;
            font-weight: bold;
            transform: translateY(-50%);
        }

        .password-container {
            position: relative;
        }

        .password-container input {
            padding-right: 40px;
        }

        .toggle-password {
            position: absolute;
            right: 10px;
            top: 50%;
            transform: translateY(-50%);
            cursor: pointer;
            color: var(--text);
            font-size: 1.2rem;
        }

        .error-message {
            color: #fff;
            font-size: clamp(1.1rem, 3vw, 1.2rem);
            background: linear-gradient(135deg, #dc3545, #c82333);
            padding: 10px 15px;
            border-radius: 5px;
            margin-bottom: 20px;
            border: 1px solid #e53935;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
            font-weight: bold;
        }

        .login-btn {
            width: 70%;
            padding: 12px;
            font-size: clamp(1.1rem, 3vw, 1.2rem);
            color: white;
            background: linear-gradient(135deg, #007bff, #0056b3);
            border: none;
            border-radius: 20px;
            cursor: pointer;
            opacity: 0.6;
            pointer-events: none;
        }

        .login-btn.enabled {
            opacity: 1;
            pointer-events: auto;
        }

        .login-btn:hover.enabled {
            background: linear-gradient(135deg, #0056b3, #003d80);
        }

        .qr-btn {
            width: 70%;
            padding: 12px;
            font-size: clamp(1.1rem, 3vw, 1.2rem);
            color: white;
            background: linear-gradient(135deg, #28a745, #1e7e34);
            border: none;
            border-radius: 20px;
            cursor: pointer;
        }

        .qr-btn:hover {
            background: linear-gradient(135deg, #1e7e34, #155724);
        }

        .or-divider {
            display: flex;
            align-items: center;
            text-align: center;
            width: 70%;
            padding-top: 10px;
            padding-bottom: 10px;
            margin: 0 auto;
            color: var(--text-secondary);
            font-size: clamp(1.1rem, 3vw, 1.2rem);
        }

        .or-divider::before,
        .or-divider::after {
            content: '';
            flex: 1;
            border-bottom: 1px solid #ccc;
        }

        .or-divider span {
            padding: 0 10px;
            font-weight: bold;
        }

        input:valid {
            border-color: #342E37;
        }

        .footer-left {
            display: flex;
            justify-content: center;
        }
        .footer-left h3 a {
            color: #1877F2;
            font-weight: 200;
            font-size: clamp(1.1rem, 3vw, 1.2rem);
            text-decoration: none;
        }
        .footer-left h3 a:hover {
            text-decoration: underline;
        }

        .new-footer {
            background-color: #F9F9F9;
            width: 100%;
            text-align: center;
            border-top: 1px solid #ccc;
        }
        .new-footer-container {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            padding: 40px;
            max-width: 1400px;
            margin: 0 auto;
        }

        .new-footer-section {
            text-align: left;
        }

        .new-footer-section h3 {
            color: var(--primary);
            font-size: clamp(1.5rem, 3vw, 2rem);
            margin-bottom: 15px;
        }

        .new-footer-section p,
        .new-footer-section a {
            color: var(--text);
            font-size: clamp(1.1rem, 3vw, 1.3rem);
            text-decoration: none;
            margin: 10px 0;
            display: block;
        }

        .new-footer-section a:hover {
            color: #003d80;
            transform: translateY(-2px);
        }

        .new-footer-section img {
            max-width: 100px;
            height: auto;
            margin-bottom: 10px;
        }

        .copyright {
            margin-top: 20px;
            font-size: clamp(1.1rem, 3vw, 1.3rem);
            color: var(--text-secondary);
            border-top: 1px solid #ccc;
            padding-top: 20px;
        }

        #interactive {
            width: 100%;
            height: auto;
            border: 2px solid #ccc;
            border-radius: 10px;
            margin-top: 10px;
            display: none; 
        }

        #canvas {
            width: 100%;
            height: auto;
            border: 2px solid #ccc;
            border-radius: 10px;
            margin-top: 10px;
        }

        .qr-detected-container {
            display: none;
            margin-top: 20px;
        }

        .instructions {
            margin-top: 5px;
            font-size: clamp(1rem, 3vw, 1.2rem);
            color: white;
            width: 80%;
            background: #0056b3;
            border-radius: 10px;
            margin: 0 auto;
            box-shadow: 0 4px 30px rgba(0, 0, 0, 0.1);
            padding: 10px;
        }

        #qr-code-image-container {
            margin-top: 20px;
            text-align: center;
        }

        #qr-code-image {
            max-width: 100%;
            height: auto;
            border: 2px solid #ddd;
            border-radius: 10px;
            padding: 10px;
            background-color: #f9f9f9;
            box-shadow: 0 0 5px rgba(0, 0, 0, 0.1);
        }

        .password-container {
            margin-top: 20px;
            text-align: center;
        }

        .password-container input[type="password"] {
            width: 100%;
            padding: 12px;
            font-size: clamp(1.1rem, 3vw, 1.3rem);
            border: 1px solid #ccc;
            border-radius: 5px;
            box-sizing: border-box;
        }

        .password-container input[type="password"]:focus {
            border-color: var(--primary);
            outline: none;
        }

        .password-container input[type="password"]:valid {
            border-color: #342E37;
        }

        .password-container .btn {
            width: 100%;
            padding: 12px;
            font-size: clamp(1.1rem, 3vw, 1.2rem);
            color: white;
            background: linear-gradient(135deg, #007bff, #0056b3);
            border: none;
            border-radius: 5px;
            cursor: pointer;
        }

        .password-container .btn:hover {
            background: linear-gradient(135deg, #0056b3, #003d80);
        }

        .password-container label {
            font-size: clamp(1.1rem, 3vw, 1.2rem);
            color: var(--text);
            margin-bottom: 5px;
            display: block;
            font-weight: bold;
            text-align: left;
        }

        .password-container label span {
            color: red;
        }

        input[type="file"] {
            font-size: clamp(1.1rem, 3vw, 1.3rem);
            color: #333;
            padding: 10px;
            border-radius: 8px;
            background-color: transparent;
            width: 100%;
            cursor: pointer;
            transition: all 0.3s ease;
        }

        input[type="file"]:hover {
            border-color: #666;
        }

        input[type="file"]:focus {
            outline: none;
            border-color: green;
        }

        .file-name {
            font-size: clamp(0.9rem, 2vw, 1rem);
            color: #666;
            margin-top: 8px;
            font-style: italic;
        }

        .qr-code-preview {
            position: relative;
            display: none;
            margin: 0 auto;
        }

        .qr-code-preview img {
            max-width: 350px;
            max-height: 30vh;
            margin-bottom: 15px;
        }

        .qr-container-btn {
            display: flex;
            justify-content: center;
        }

        .terms-checkbox-container {
            display: flex;
            align-items: center;
            justify-content: center;
            margin-bottom: 20px;
            font-size: clamp(1.1rem, 3vw, 1.2rem);
            color: var(--text-secondary) !important;
        }
        .terms_agreed {
            color: var(--text-secondary) !important;
        }
        .terms-checkbox-container input {
            margin-right: 8px;
            cursor: pointer;
        }
        .terms-checkbox-container a {
            color: var(--text-secondary);
            font-size: clamp(1.1rem, 3vw, 1.2rem);
            text-decoration: none;
        }
        .terms-checkbox-container a:hover {
            text-decoration: underline;
            color: #1877F2;
        }
        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0, 0, 0, 0.6);
            z-index: 1000;
            justify-content: center;
            align-items: center;
            opacity: 0;
            transition: opacity 0.3s ease;
            overflow-y: auto;
        }
        .modal.show {
            display: flex;
            opacity: 1;
        }
        .modal-content {
            background-color: #F9F9F9;
            border-radius: 12px;
            box-shadow: var(--shadow);
            max-width: 800px;
            width: 90%;
            padding: 30px;
            position: relative;
            max-height: 80vh;
            overflow-y: auto;
            transform: scale(0.8);
            transition: transform 0.3s ease;
        }
        .modal.show .modal-content {
            transform: scale(1);
        }
        .modal-content h2 {
            color: var(--primary);
            font-size: clamp(1.8rem, 3vw, 2.5rem);
            margin-bottom: 20px;
            text-align: center;
        }
        .modal-content h3 {
            color: var(--text);
            font-size: clamp(1.4rem, 2.5vw, 2rem);
            margin-top: 25px;
            margin-bottom: 12px;
        }
        .modal-content p, .modal-content li {
            color: var(--text-secondary);
            font-size: clamp(1rem, 2vw, 1.5rem);
            line-height: 1.6;
            margin-bottom: 12px;
        }
        .modal-content ul {
            list-style-type: disc;
            margin-left: 20px;
        }
        .close-btn {
            position: absolute;
            top: 15px;
            right: 20px;
            font-size: 2.5rem;
            color: var(--text);
            cursor: pointer;
            transition: var(--transition);
        }
        .close-btn:hover {
            color: var(--primary-dark);
            transform: scale(1.2);
        }

        @media screen and (max-width: 768px) {
            .image-container {
                display: none;
            }
            .title-right img {
                display: none;
            }
            .login-btn, .qr-btn {
                width: 100%;
            }
            .input-group input {
                padding: 15px;
            }
            .login-form-container {
                padding: 30px;
            }
            .terms-checkbox-container {
                font-size: clamp(0.9rem, 2vw, 1rem);
            }
        }

    </style>
</head>
<body>
    <div class="main-container">
        <div class="image-container">
            <div class="fb-style-left">
                <h1>Learnify</h1>
                <p>Scan your QR code to access your quizzes, assignments, and learning resources in the DIHS Learning Management System.</p>
            </div>
        </div>
        <div class="login-form-wrapper">
            <div class="login-form-container">

                <?php if (isset($error)): ?>
                    <div class="error-message"><?php echo htmlspecialchars($error); ?></div>
                <?php endif; ?>

                <?php if (!isset($_SESSION['temp_user'])): ?>
                    <div class="scanner-container">
                        <p class="instructions">Position the QR code within the frame to scan.</p>
                        <video id="interactive" class="viewport" autoplay playsinline></video>
                        <canvas id="canvas" style="display: none;"></canvas>
                        <div class="qr-detected-container">
                            <form action="qr_code.php" method="POST">
                                <h4>QR Code Detected!</h4>
                                <div id="qr-code-image-container">
                                    <img id="qr-code-image" src="" alt="QR Code">
                                </div>
                                <input type="hidden" id="detected-qr-code" name="qr-code">
                                <button type="submit" class="login-btn">Continue</button>
                            </form>
                        </div>
                    </div>
                <?php else: ?>
                    <div class="password-container">
                        <form action="qr_code.php" method="POST">
                            <div class="input-group">
                                <div class="input-wrapper password-container">
                                    <input type="password" name="password" id="password" placeholder=" " autocomplete="current-password">
                                    <label for="password">Password <span>*</span></label>
                                    <i class="fas fa-eye toggle-password" id="togglePassword"></i>
                                </div>
                            </div>
                            <div class="terms-checkbox-container">
                                <input type="checkbox" id="terms_agreed" name="terms_agreed">
                                <label class="terms_agreed" for="terms_agreed">I accept the <a href="#" onclick="openModal('terms-modal')">Terms and Conditions</a></label>
                            </div>
                            <button type="submit" class="login-btn" id="loginBtn">Log In</button>
                        </form>
                    </div>
                <?php endif; ?>
                <div class="footer-left">
                    <h3><a href="forgot_password.php">Forgot Password?</a></h3>
                </div>
                <div class="or-divider">
                    <span>Or</span>
                </div>
                <div class="qr-container-btn">
                    <button class="qr-btn" onclick="window.location.href='./index.php'">Login with LRN or Email</button>
                </div>
            </div>
        </div>
    </div>

    <footer class="new-footer">
        <div class="new-footer-container">
            <div class="new-footer-section">
                <img src="./img/learnify-logo.png" alt="Learnify Logo">
                <h3>Learnify</h3>
                <p>Your trusted platform for online learning and academic success.</p>
            </div>
            <div class="new-footer-section">
                <h3>Quick Links</h3>
                <a href="./portal.php">Home</a>
                <a href="./portal.php#help">Helpdesk</a>
                <a href="./portal.php#contact">Feedback</a>
                <a href="./portal.php#games">Games</a>
                <a href="./portal.php#users">Roles</a>
            </div>
            <div class="new-footer-section">
                <h3>Contact Us</h3>
                <p><a href="mailto:support@learnify.com">admindihs@gmail.com</a></p>
                <p><a href="tel:+1234567890">+63 9103 833 991</a></p>
            </div>
            <div class="new-footer-section">
                <h3>Office Hours</h3>
                <p>Monday - Friday: 6:00 AM - 7:00 PM</p>
                <p>Saturday: 10:00 AM - 2:00 PM</p>
                <p>Sunday: <span style="color: red;">Closed</span></p>
            </div>
            <div class="new-footer-section">
                <h3>Legal</h3>
                <a href="#" onclick="openModal('privacy-modal')">Data Privacy Notice</a>
                <a href="#" onclick="openModal('terms-modal')">Terms and Conditions</a>
            </div>
        </div>
        <div class="copyright">
            <p>&copy; <?php echo date('Y'); ?> Learnify. All rights reserved.</p>
        </div>
    </footer>
    <div id="privacy-modal" class="modal">
        <div class="modal-content">
            <span class="close-btn" onclick="closeModal('privacy-modal')">&times;</span>
            <h2>Data Privacy Notice</h2>
            <p><strong>Learnify Learning Management System</strong> is committed to protecting the privacy and security of your personal data in compliance with Republic Act No. 10173, the Data Privacy Act of 2012 of the Philippines, and its implementing rules and regulations. This notice details how we collect, use, store, and protect your personal information when you interact with our platform.</p>
            <h3>1. Information We Collect</h3>
            <p>We collect data to provide a personalized and efficient learning experience. The categories of data include:</p>
            <ul>
                <li><strong>Personal Identifiable Information (PII):</strong> Full name (first, middle, last), Learner Reference Number (LRN), email address, contact number, date of birth, sex, address, nationality, and optional profile image.</li>
                <li><strong>Account Information:</strong> User type (Student, Professor, Admin, SuperAdmin), account status, hashed passwords, one-time passwords (OTPs), and authentication codes.</li>
                <li><strong>Usage Data:</strong> Platform interactions, such as quiz results, assignment submissions, gamified activities (coins earned), login history.</li>
                <li><strong>Educational Data:</strong> Enrollment details, academic progress, grades, and other academic records.</li>
            </ul>
            <h3>2. Purpose of Data Collection</h3>
            <p>We process personal data for the following purposes:</p>
            <ul>
                <li>User authentication and account management.</li>
                <li>Delivering educational content, tracking academic progress, and managing quizzes, assignments, and gamified features.</li>
                <li>Communicating updates, notifications, and support services.</li>
                <li>Enhancing platform performance, security, and user experience.</li>
                <li>Complying with legal obligations under the Data Privacy Act of 2012.</li>
            </ul>
            <h3>3. Data Storage and Security</h3>
            <p>We implement robust security measures, including:</p>
            <ul>
                <li><strong>Encryption:</strong> Passwords are hashed using bcrypt, and data is encrypted during transmission and storage.</li>
                <li><strong>Access Controls:</strong> Restricted to authorized personnel based on roles (Admin, SuperAdmin).</li>
                <li><strong>Audits:</strong> Regular security assessments to ensure system integrity.</li>
                <li><strong>Retention:</strong> Data is retained only as long as necessary or required by law, with archived accounts securely stored or anonymized.</li>
            </ul>
            <h3>4. Data Sharing and Disclosure</h3>
            <p>Data may be shared with:</p>
            <ul>
                <li><strong>Educational Institutions:</strong> For academic purposes like enrollment or grading.</li>
                <li><strong>Service Providers:</strong> Third parties, such as InfinityFree for hosting the system, under strict data protection agreements.</li>
                <li><strong>Legal Authorities:</strong> When required by law or to protect Learnify’s rights, safety, or users.</li>
            </ul>
            <h3>5. Your Rights</h3>
            <p>Under the Data Privacy Act, you have the right to:</p>
            <ul>
                <li><strong>Access:</strong> View your personal data.</li>
                <li><strong>Rectification:</strong> Correct inaccuracies.</li>
                <li><strong>Erasure/Blocking:</strong> Request deletion or restriction, subject to legal limits.</li>
                <li><strong>Object:</strong> Oppose specific data processing.</li>
                <li><strong>Data Portability:</strong> Obtain data in a structured format.</li>
                <li><strong>Complain:</strong> File complaints with the National Privacy Commission.</li>
            </ul>
            <p>Contact our Data Protection Officer at <a href="mailto:dpo@learnify.com">dpo@learnify.com</a> to exercise these rights.</p>
            <h3>6. Cookies and Tracking</h3>
            <p>We use cookies to maintain sessions and track usage. Manage preferences via browser settings, noting that disabling cookies may affect functionality.</p>
            <h3>7. Updates to This Notice</h3>
            <p>Updates will be posted on the platform, with significant changes notified via email or in-platform alerts.</p>
            <h3>8. Contact Us</h3>
            <p>For inquiries, contact our Data Protection Officer:</p>
            <ul>
                <li><strong>Email:</strong> <a href="mailto:dpo@learnify.com">dpo@learnify.com</a></li>
                <li><strong>Phone:</strong> +63 9103 833 991</li>
                <li><strong>Address:</strong> Learnify Data Protection Office, DIHS Building, Manila, Philippines</li>
            </ul>
            <p>By using Learnify, you acknowledge and consent to this Data Privacy Notice.</p>
        </div>
    </div>
    <div id="terms-modal" class="modal">
        <div class="modal-content">
            <span class="close-btn" onclick="closeModal('terms-modal')">&times;</span>
            <h2>Terms and Conditions</h2>
            <p>Welcome to Learnify Learning Management System. By using our platform, you agree to these Terms and Conditions. Please read carefully.</p>
            <h3>1. Acceptance of Terms</h3>
            <p>By accessing or registering on Learnify, you agree to these Terms, our Data Privacy Notice, and applicable laws, including the Data Privacy Act of 2012. Non-agreement requires you to cease using the platform.</p>
            <h3>2. User Accounts</h3>
            <p>Provide accurate registration details (LRN, email). You are responsible for securing your account credentials and all activities under your account.</p>
            <h3>3. Platform Usage</h3>
            <p>Learnify supports educational activities like quizzes, assignments, and gamified features. You agree not to:</p>
            <ul>
                <li>Engage in unlawful or unauthorized activities.</li>
                <li>Attempt unauthorized access to systems or data.</li>
                <li>Share inappropriate or infringing content.</li>
            </ul>
            <h3>4. Intellectual Property</h3>
            <p>Platform content (text, quizzes, images) is owned by Learnify or its licensors and protected by law. Reproduction or modification requires written consent.</p>
            <h3>5. User Conduct</h3>
            <p>Users must act respectfully, avoiding:</p>
            <ul>
                <li>Harassment or inappropriate behavior.</li>
                <li>Cheating on academic activities.</li>
                <li>Misusing features like the coin system.</li>
            </ul>
            <h3>6. Termination</h3>
            <p>Learnify may suspend or terminate accounts for violations, including unauthorized access or non-compliance with these Terms.</p>
            <h3>7. Limitation of Liability</h3>
            <p>Learnify is provided “as is” without warranties. We are not liable for damages from platform use, data loss, or technical issues, except as required by law.</p>
            <h3>8. Changes to Terms</h3>
            <p>Terms may be updated, with continued use indicating acceptance of changes.</p>
            <h3>9. Governing Law</h3>
            <p>These Terms are governed by Philippine law, with disputes resolved in Manila courts.</p>
            <h3>10. Contact Us</h3>
            <p>For questions, contact:</p>
            <ul>
                <li><strong>Email:</strong> <a href="mailto:support@learnify.com">support@learnify.com</a></li>
                <li><strong>Phone:</strong> +63 9103 833 991</li>
            </ul>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/jsqr@1.4.0/dist/jsQR.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/jquery@3.5.1/dist/jquery.slim.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/popper.js@1.16.1/dist/umd/popper.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/js/bootstrap.min.js"></script>
    <script>
        <?php if (!isset($_SESSION['temp_user'])): ?>
        let video = document.getElementById('interactive');
        let canvas = document.getElementById('canvas');
        let context = canvas.getContext('2d');
        let scanning = false;

        function startScanner() {
            navigator.mediaDevices.getUserMedia({ video: { facingMode: 'environment' } })
                .then(function(stream) {
                    video.srcObject = stream;
                    video.setAttribute('playsinline', true);
                    video.play();
                    scanning = true;
                    requestAnimationFrame(tick);
                })
                .catch(function(err) {
                    console.error('Camera access error:', err);
                    alert('Camera access error: ' + err.message);
                });
        }

        function tick() {
            if (!scanning) return;

            if (video.readyState === video.HAVE_ENOUGH_DATA) {
                canvas.height = video.videoHeight;
                canvas.width = video.videoWidth;
                context.drawImage(video, 0, 0, canvas.width, canvas.height);
                let imageData = context.getImageData(0, 0, canvas.width, canvas.height);
                let code = jsQR(imageData.data, imageData.width, imageData.height, {
                    inversionAttempts: 'dontInvert',
                });

                if (code) {
                    console.log('QR Code Scanned:', code.data);
                    document.getElementById('detected-qr-code').value = code.data;

                    // Stop the video stream
                    let stream = video.srcObject;
                    let tracks = stream.getTracks();
                    tracks.forEach(track => track.stop());
                    video.srcObject = null;
                    scanning = false;
                    video.style.display = 'none';
                    document.querySelector('.qr-detected-container').style.display = 'block';

                    // Auto-submit the form
                    let form = document.createElement('form');
                    form.method = 'POST';
                    form.action = 'qr_code.php';

                    let input = document.createElement('input');
                    input.type = 'hidden';
                    input.name = 'qr-code';
                    input.value = code.data;

                    form.appendChild(input);
                    document.body.appendChild(form);
                    form.submit();
                }
            }
            if (scanning) {
                requestAnimationFrame(tick);
            }
        }

        document.addEventListener('DOMContentLoaded', function() {
            video.style.display = 'block';
            startScanner();
        });
        <?php endif; ?>

        const togglePassword = document.getElementById('togglePassword');
        const passwordInput = document.getElementById('password');
        if (togglePassword && passwordInput) {
            togglePassword.addEventListener('click', function() {
                const type = passwordInput.getAttribute('type') === 'password' ? 'text' : 'password';
                passwordInput.setAttribute('type', type);
                this.classList.toggle('fa-eye');
                this.classList.toggle('fa-eye-slash');
            });
        }

        let scrollPosition = 0;
        function openModal(modalId) {
            scrollPosition = window.pageYOffset || document.documentElement.scrollTop;
            const modal = document.getElementById(modalId);
            modal.style.display = 'flex';
            setTimeout(() => modal.classList.add('show'), 10);
            document.body.style.overflow = 'hidden';
            document.body.style.position = 'fixed';
            document.body.style.top = `-${scrollPosition}px`;
            document.body.style.width = '100%';
        }

        function closeModal(modalId) {
            const modal = document.getElementById(modalId);
            modal.classList.remove('show');
            setTimeout(() => {
                modal.style.display = 'none';
                document.body.style.overflow = 'auto';
                document.body.style.position = '';
                document.body.style.top = '';
                document.body.style.width = '';
                window.scrollTo(0, scrollPosition);
            }, 300);
        }

        window.onclick = function(event) {
            if (event.target.classList.contains('modal')) {
                closeModal(event.target.id);
            }
        };

        document.addEventListener('keydown', function(event) {
            if (event.key === 'Escape') {
                const modals = document.getElementsByClassName('modal');
                for (let modal of modals) {
                    if (modal.classList.contains('show')) {
                        closeModal(modal.id);
                    }
                }
            }
        });

        const termsCheckbox = document.getElementById('terms_agreed');
        const loginBtn = document.getElementById('loginBtn');
        if (termsCheckbox && loginBtn) {
            // Disable login button by default
            loginBtn.disabled = true;
            loginBtn.classList.add('disabled');
            termsCheckbox.addEventListener('change', function() {
                loginBtn.disabled = !this.checked;
                loginBtn.classList.toggle('enabled', this.checked);
            });
        }
    </script>
</body>
</html>