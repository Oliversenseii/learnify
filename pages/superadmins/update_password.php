<?php
require_once './sessions/session_superAdmin.php';
require_once '../../config/db_connection.php';
require_once './auto_logout.php';

$userID = $_SESSION['userID'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['changePassword'])) {
        $currentPassword = $_POST['currentPassword'] ?? '';
        $newPassword = $_POST['newPassword'] ?? '';
        $confirmPassword = $_POST['confirmPassword'] ?? '';

        // Check if 15 days have passed since last password change
        $stmt = $dbConnection->prepare("SELECT password, password_last_changed FROM users WHERE userID = :userID");
        $stmt->bindParam(':userID', $userID, PDO::PARAM_INT);
        $stmt->execute();
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($user && $user['password_last_changed']) {
            $last_changed = new DateTime($user['password_last_changed']);
            $now = new DateTime();
            $days_since_change = $now->diff($last_changed)->days;

            if ($days_since_change < 15) {
                $error = "You can only change your password every 15 days. Please try again after " . (15 - $days_since_change) . " days.";
            }
        }

        // Password validation
        if (!isset($error)) {
            if (empty($currentPassword) || empty($newPassword) || empty($confirmPassword)) {
                $error = "All fields are required.";
            } elseif ($newPassword !== $confirmPassword) {
                $error = "Passwords do not match.";
            } elseif (!password_verify($currentPassword, $user['password'])) {
                $error = "Current password is incorrect.";
            } elseif ($newPassword === $currentPassword) {
                $error = "New password cannot be the same as the current password.";
            } elseif (strlen($newPassword) < 8) {
                $error = "Password must be at least 8 characters long.";
            } elseif (preg_match('/[\*\>\<]/', $newPassword)) {
                $error = "Password cannot contain *, <, or > characters.";
            } elseif (preg_match('/[\p{So}]/u', $newPassword)) {
                $error = "Password cannot contain emojis or special symbols.";
            } elseif (!preg_match('/[A-Za-z].*[0-9]|[0-9].*[A-Za-z]/', $newPassword)) {
                $error = "Password must contain both letters and numbers.";
            }
        }

        if (!isset($error)) {
            try {
                $hashedPassword = password_hash($newPassword, PASSWORD_BCRYPT);

                $stmt = $dbConnection->prepare("UPDATE users SET password = :password, password_last_changed = NOW() WHERE userID = :userID");
                $stmt->bindParam(':password', $hashedPassword);
                $stmt->bindParam(':userID', $userID, PDO::PARAM_INT);
                $stmt->execute();

                $_SESSION['success_message'] = "Password changed successfully.";
                header("Location: update_password.php");
                exit;
            } catch (PDOException $e) {
                $error = "Error: " . $e->getMessage();
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
    <link href='https://unpkg.com/boxicons@2.0.9/css/boxicons.min.css' rel='stylesheet'>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <link rel="icon" href="../../img/learnify-logo.png" type="image/x-icon">
    <link rel="stylesheet" href="./utils/style.css">
    <link rel="stylesheet" href="./utils/logout.css">
    <link rel="stylesheet" href="./utils/animation_slide.css">
    <link rel="stylesheet" href="./utils/logo.css">
    <link rel="stylesheet" href="./css/scrollbar.css">
    <script src="./logout.js"></script>
    <title>Learnify - Change Password</title>
    <style>
        :root {
            --primary: #2B6CB0;
            --primary-dark: #1E4A7A;
            --background: #F7FAFC;
            --text: #1A202C;
            --text-secondary: #4A5568;
            --border: #E2E8F0;
            --success: #38A169;
            --error: #E53E3E;
            --white: #FFFFFF;
            --accent: #EDF2F7;
            --transition: all 0.3s ease;
            --shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06);
        }

        .modal {
            z-index: 100000;
        }

        .form-container {
            max-width: 600px;
            margin: 2rem auto;
            padding: 2rem;
            background: var(--light);
            border-radius: 10px;
            box-shadow: var(--shadow);
            transition: var(--transition);
        }

        .form-container:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 12px -1px rgba(0, 0, 0, 0.15);
        }

        .form-group {
            margin-bottom: 1.5rem;
            position: relative;
        }

        .form-group label {
            display: block;
            font-size: clamp(1rem, 3vw, 1.3rem);
            font-weight: 600;
            color: var(--dark);
            margin-bottom: 0.5rem;
        }

        .form-group input {
            width: 100%;
            padding: 0.75rem 1rem;
            font-size: clamp(1rem, 3vw, 1.2rem);
            border: 1px solid #ccc;
            border-radius: 6px;
            background: var(--grey);
            color: var(--text);
            transition: var(--transition);
            box-sizing: border-box;
        }

        .form-group input:focus {
            outline: none;
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(43, 108, 176, 0.2);
        }

        .form-group input:valid {
            border-color: var(--success);
        }

        .form-actions {
            text-align: center;
        }

        .btn-download {
            background: linear-gradient(135deg, var(--primary), var(--primary-dark));
            color: var(--white);
            padding: 0.75rem 1.5rem;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            font-size: clamp(1rem, 3vw, 1.2rem);
            font-weight: 500;
            transition: var(--transition);
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
        }

        .btn-download:hover {
            background: linear-gradient(135deg, var(--primary-dark), #1A4971);
            transform: translateY(-2px);
            box-shadow: var(--shadow);
        }

        .btn-download i {
            font-size: 1.1rem;
        }

        .error-notification, .success-message {
            padding: 1rem;
            border-radius: 6px;
            margin-bottom: 1.5rem;
            font-size: clamp(1rem, 3vw, 1.2rem);
            font-weight: 500;
            text-align: center;
            box-shadow: var(--shadow);
        }

        .error-notification {
            background: var(--error);
            color: var(--white);
            border: 1px solid darken(var(--error), 10%);
        }

        .success-message {
            background: var(--success);
            color: var(--white);
            border: 1px solid darken(var(--success), 10%);
        }

        .error-notification ul, .success-message ul {
            list-style: none;
            padding: 0;
            margin: 0;
        }

        .password-requirements {
            background: var(--grey);
            padding: 1rem;
            margin-bottom: 1.5rem;
            border-radius: 6px;
            border-left: 4px solid var(--primary);
            box-shadow: var(--shadow);
        }

        .password-requirements h4 {
            font-size: clamp(1.2rem, 3vw, 2rem);
            font-weight: 600;
            color: var(--dark);
            margin: 0 0 0.75rem 0;
        }

        .password-requirements ul {
            list-style: none;
            padding: 0;
            margin: 0;
        }

        .password-requirements li {
            position: relative;
            padding-left: 2rem;
            margin-bottom: 0.5rem;
            font-size: clamp(1rem, 3vw, 1.2rem);
            color: var(--text-secondary);
            line-height: 1.5;
        }

        .password-requirements li::before {
            content: '\f058';
            font-family: 'Font Awesome 5 Free';
            font-weight: 900;
            color: var(--primary);
            position: absolute;
            left: 0;
            top: 0.1rem;
        }

        #content main .head-title .left h1 {
            font-size: clamp(1.5rem, 3vw, 2rem);
            font-weight: 600;
            margin-bottom: 10px;
            color: var(--dark);
        }

        #content main .head-title .left .breadcrumb li a {
            color: var(--text-secondary);
            pointer-events: none;
        }

        .back-button {
            background: linear-gradient(135deg, var(--primary), var(--primary-dark));
            color: var(--white);
            padding: 0.6rem 1.5rem;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            font-size: clamp(1.1rem, 3vw, 1.3rem);
            font-weight: 500;
            transition: var(--transition);
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            text-decoration: none;
        }

        .back-button:hover {
            background: linear-gradient(135deg, var(--primary-dark), #1A4971);
            transform: translateY(-2px);
            box-shadow: var(--shadow);
        }

        .back-button i {
            font-size: clamp(1.1rem, 3vw, 1.3rem);
        }
    </style>
</head>
<body>
    <!-- SIDEBAR -->
    <section id="sidebar">
        <?php require_once './brand.php' ?>
        <ul class="side-menu top">
            <li>
                <a href="./superAdminDash.php">
                    <i class='bx bxs-dashboard'></i>
                    <span class="text">Dashboard</span>
                </a>
            </li>
            <li>
                <a href="./registration.php">
                    <i class="bx bx-user-plus"></i>
                    <span class="text">Registration</span>
                </a>
            </li>
            <li>
                <a href="./backup_data.php">
                    <i class='bx bxs-data'></i>
                    <span class="text">Backup Data</span>
                </a>
            </li>
            <li>
                <a href="./branding.php">
                    <i class='bx bxs-image'></i>
                    <span class="text">Branding</span>
                </a>
            </li>
        </ul>
        <ul class="side-menu">
            <li>
                <a href="./settings.php">
                    <i class='bx bxs-cog'></i>
                    <span class="text">Settings</span>
                </a>
            </li>
            <li>
                <a href="javascript:void(0);" class="logout" onclick="showLogoutModal()">
                    <i class='bx bxs-log-out-circle'></i>
                    <span class="text">Logout</span>
                </a>
            </li>
        </ul>
    </section>
    <!-- SIDEBAR -->

    <?php require_once './view/modal.php' ?>

    <!-- CONTENT -->
    <section id="content">
        <!-- NAVBAR -->
        <nav>
            <i class='bx bx-menu' aria-label="Toggle Sidebar"></i>
            <form action="search.php" method="get">
                <div class="form-input">
                    <input type="search" name="query" placeholder="Search users, subjects, tracks, sections..." required aria-label="Search">
                    <button type="submit" class="search-btn" aria-label="Search"><i class='bx bx-search'></i></button>
                </div>
            </form>
            <input type="checkbox" id="switch-mode" hidden aria-label="Toggle Dark Mode">
            <label for="switch-mode" class="switch-mode"></label>
            <a href="./main_acc.php" class="profile" aria-label="Profile">
                <img src="<?php echo isset($_SESSION['image']) ? $_SESSION['image'] : './img/noprofile.png'; ?>" alt="Profile Image">
                <div>
                    <p><?php echo htmlspecialchars($_SESSION['firstName']); ?></p>
                    <small><?php echo htmlspecialchars($_SESSION['userType']); ?></small>
                </div>
            </a>
        </nav>
        <!-- NAVBAR -->

        <!-- MAIN -->
        <main>
            <div class="head-title">
                <div class="left">
                    <h1>Change Password</h1>
                    <ul class="breadcrumb">
                        <li><a href="./superAdminDash.php">Home</a></li>
                        <li><i class='bx bx-chevron-right'></i></li>
                        <li><a href="./userAcc.php">Settings</a></li>
                        <li><i class='bx bx-chevron-right'></i></li>
                        <li><a class="active" href="./update_password.php">Change Password</a></li>
                    </ul>
                </div>
                <!-- <a href="./userAcc.php" class="btn-download">
                    <i class='bx bxs-left-arrow'></i>
                    <span class="text">Back</span>
                </a> -->
                <a href="./settings.php" class="back-button"><i class='bx bx-arrow-back'></i> Back to Settings</a>
            </div>

            <div class="form-container">
                <?php if (isset($error)): ?>
                    <div class="error-notification">
                        <ul>
                            <li><?php echo htmlspecialchars($error); ?></li>
                        </ul>
                    </div>
                <?php elseif (isset($_SESSION['success_message'])): ?>
                    <div class="success-message">
                        <ul>
                            <li><?php echo htmlspecialchars($_SESSION['success_message']); unset($_SESSION['success_message']); ?></li>
                        </ul>
                    </div>
                <?php endif; ?>
                <div class="password-requirements">
                    <h4>Password Requirements</h4>
                    <ul>
                        <li>Be at least 8 characters long</li>
                        <li>Contain both letters and numbers</li>
                        <li>Not contain *, <, >, or emojis</li>
                    </ul>
                </div>
                <form method="POST" action="">
                    <div class="form-group">
                        <label for="currentPassword">Current Password</label>
                        <input type="password" id="currentPassword" name="currentPassword" required placeholder="Enter your current password">
                    </div>
                    <div class="form-group">
                        <label for="newPassword">New Password</label>
                        <input type="password" id="newPassword" name="newPassword" required placeholder="Enter your new password">
                    </div>
                    <div class="form-group">
                        <label for="confirmPassword">Confirm Password</label>
                        <input type="password" id="confirmPassword" name="confirmPassword" required placeholder="Confirm your password">
                    </div>
                    <div class="form-actions">
                        <button class="btn-download" type="submit" name="changePassword"><i class='bx bx-lock-alt'></i> Change Password</button>
                    </div>
                </form>
            </div>
        </main>
        <!-- MAIN -->
    </section>
    <!-- CONTENT -->

    <script src="./utils/script.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', () => {
            const successMessage = document.querySelector('.success-message');
            if (successMessage) {
                setTimeout(() => {
                    successMessage.style.opacity = '0';
                    setTimeout(() => {
                        successMessage.remove();
                    }, 300);
                }, 5000);
            }
        });
    </script>
</body>
</html>