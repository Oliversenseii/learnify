<?php
require_once './sessions/session_superAdmin.php';
require_once '../../config/db_connection.php';
require_once './auto_logout.php';

if (!isset($_GET['userID'])) {
    header('Location: data_admin.php');
    exit();
}

function decryptID($encryptedID) {
    $key = "b7e1a8f9c3d4e2b69a7f5d8e1c4b3a62"; 
    return openssl_decrypt(base64_decode(urldecode($encryptedID)), 'AES-128-ECB', $key, 0, "");
}

$userID = decryptID($_GET['userID']);

$sql = "SELECT userID, firstName, lastName, email, contactNumber, status, birthday, image, password, password_last_changed 
        FROM users 
        WHERE userID = :userID";
$stmt = $dbConnection->prepare($sql);
$stmt->bindParam(':userID', $userID, PDO::PARAM_INT);
$stmt->execute();
$admin = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$admin) {
    header('Location: data_admin.php');
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $error = null;
    $success_message = null;

    $firstName = $_POST['firstName'] ?? '';
    $lastName = $_POST['lastName'] ?? '';
    $email = $_POST['email'] ?? '';
    $contactNumber = $_POST['contactNumber'] ?? '';
    $status = $_POST['status'] ?? '';
    $birthday = $_POST['birthday'] ?? '';
    $password_last_changed = $_POST['password_last_changed'] ?? null;

    // Password fields (optional)
    $currentPassword = $_POST['currentPassword'] ?? '';
    $newPassword = $_POST['newPassword'] ?? '';
    $confirmPassword = $_POST['confirmPassword'] ?? '';

    // Validate admin details
    if (empty($firstName) || empty($lastName) || empty($email) || empty($contactNumber) || empty($status) || empty($birthday)) {
        $error = "All admin details fields are required.";
    }

    // Validate password_last_changed
    if (!empty($password_last_changed)) {
        try {
            $input_date = new DateTime($password_last_changed);
            $now = new DateTime();
            if ($input_date > $now) {
                $error = "Last password change date cannot be in the future.";
            }
        } catch (Exception $e) {
            $error = "Invalid last password change date format.";
        }
    }

    // Validate password fields if provided
    $updatePassword = !empty($currentPassword) || !empty($newPassword) || !empty($confirmPassword);
    if ($updatePassword && !isset($error)) {
        // Check 15-day restriction
        if ($admin['password_last_changed']) {
            $last_changed = new DateTime($admin['password_last_changed']);
            $now = new DateTime();
            $days_since_change = $now->diff($last_changed)->days;

            if ($days_since_change < 15) {
                $error = "You can only change the password every 15 days. Please try again after " . (15 - $days_since_change) . " days.";
            }
        }

        // Password validation
        if (!isset($error)) {
            if (empty($currentPassword) || empty($newPassword) || empty($confirmPassword)) {
                $error = "All password fields are required when changing the password.";
            } elseif ($newPassword !== $confirmPassword) {
                $error = "New passwords do not match.";
            } elseif (!password_verify($currentPassword, $admin['password'])) {
                $error = "Current password is incorrect.";
            } elseif ($newPassword === $currentPassword) {
                $error = "New password cannot be the same as the current password.";
            } elseif (strlen($newPassword) < 8) {
                $error = "New password must be at least 8 characters long.";
            } elseif (preg_match('/[\*\>\<]/', $newPassword)) {
                $error = "New password cannot contain *, <, or > characters.";
            } elseif (preg_match('/[\p{So}]/u', $newPassword)) {
                $error = "New password cannot contain emojis or special symbols.";
            } elseif (!preg_match('/[A-Za-z].*[0-9]|[0-9].*[A-Za-z]/', $newPassword)) {
                $error = "New password must contain both letters and numbers.";
            }
        }
    }

    // Update database if no errors
    if (!isset($error)) {
        try {
            $dbConnection->beginTransaction();

            $updateSql = "UPDATE users 
                          SET firstName = :firstName, lastName = :lastName, email = :email, 
                              contactNumber = :contactNumber, status = :status, birthday = :birthday,
                              password_last_changed = :password_last_changed 
                          WHERE userID = :userID";
            $updateStmt = $dbConnection->prepare($updateSql);
            $updateStmt->bindParam(':firstName', $firstName, PDO::PARAM_STR);
            $updateStmt->bindParam(':lastName', $lastName, PDO::PARAM_STR);
            $updateStmt->bindParam(':email', $email, PDO::PARAM_STR);
            $updateStmt->bindParam(':contactNumber', $contactNumber, PDO::PARAM_STR);
            $updateStmt->bindParam(':status', $status, PDO::PARAM_STR);
            $updateStmt->bindParam(':birthday', $birthday, PDO::PARAM_STR);
            $updateStmt->bindParam(':password_last_changed', $password_last_changed, PDO::PARAM_STR);
            $updateStmt->bindParam(':userID', $userID, PDO::PARAM_INT);
            $updateStmt->execute();

            // Update password if provided
            if ($updatePassword) {
                $hashedPassword = password_hash($newPassword, PASSWORD_BCRYPT);
                $stmt = $dbConnection->prepare("UPDATE users SET password = :password, password_last_changed = NOW() WHERE userID = :userID");
                $stmt->bindParam(':password', $hashedPassword);
                $stmt->bindParam(':userID', $userID, PDO::PARAM_INT);
                $stmt->execute();
                $success_message = "Admin user details, password, and last password change date updated successfully!";
            } else {
                $success_message = "Admin user details" . ($password_last_changed ? " and last password change date" : "") . " updated successfully!";
            }

            // Commit transaction
            $dbConnection->commit();
            $_SESSION['success_message'] = $success_message;
            header('Location: data_admin.php');
            exit();
        } catch (PDOException $e) {
            $dbConnection->rollBack();
            $error = "Error updating admin user: " . $e->getMessage();
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
    <link rel="stylesheet" href="./utils/data_admin.css">
    <link rel="stylesheet" href="./utils/edit_data.css">
    <link rel="stylesheet" href="./utils/animation_slide.css">
    <link rel="stylesheet" href="./utils/logo.css">
    <link rel="stylesheet" href="./css/scrollbar.css">
    <script src="./logout.js"></script>
    <title>Learnify - Edit Administrator</title>
    <style>
        @media screen and (max-width: 460px) {
            #content main .head-title .btn-download {
                height: 30px;
                font-size: 0.9rem;
                padding: 0 10px;
            }
        }
        :root {
            --primary: #4CAF50;
            --danger: #e74c3c;
        }

        .form-container {
            background: var(--light);
            padding: 20px;
            border-radius: 12px;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
            max-width: 700px;
            margin: 20px auto;
        }

        .form-section {
            margin-bottom: 30px;
        }

        .form-section h2 {
            font-size: 1.5rem;
            font-weight: 600;
            color: var(--dark);
            margin-bottom: 20px;
            border-bottom: 2px solid var(--primary);
            padding-bottom: 5px;
        }

        .form-group {
            display: flex;
            align-items: center;
            margin-bottom: 20px;
            gap: 20px;
        }

        .form-group label {
            flex: 0 0 200px;
            font-size: 1.2rem;
            font-weight: 600;
            color: var(--dark);
            text-align: right;
        }

        .form-group input,
        .form-group select {
            flex: 1;
            padding: 10px;
            font-size: 15px;
            border: 1px solid #dcdcdc;
            background-color: var(--grey);
            color: var(--dark);
            border-radius: 6px;
            box-sizing: border-box;
            transition: border-color 0.3s ease, box-shadow 0.3s ease;
        }

        .form-group input:focus,
        .form-group select:focus {
            outline: none;
            border-color: var(--primary);
            box-shadow: 0 0 5px rgba(76, 175, 80, 0.3);
        }

        .form-actions {
            text-align: center;
            margin-top: 20px;
        }

        .btn-save {
            padding: 10px 20px;
            font-size: clamp(1rem, 3vw, 1.2rem);
            font-weight: 600;
            color: #fff;
            background: linear-gradient(135deg, #007bff, #0056b3);
            border: none;
            border-radius: 6px;
            cursor: pointer;
            transition: background-color 0.3s ease;
            text-decoration: none;
        }

        .btn-save:hover {
            background: linear-gradient(135deg, #0056b3, #003d80);
        }

        .btn-save i {
            margin-right: 8px;
        }

        .error-notification,
        .success-message {
            background: linear-gradient(135deg, #dc3545, #c82333);
            color: #fff;
            padding: 12px;
            border-radius: 6px;
            margin: 20px 0;
            font-size: clamp(1.1rem, 3vw, 1.2rem);
            font-weight: 600;
            text-align: center;
            border: 1px solid #c0392b;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
        }

        .success-message {
            background: linear-gradient(135deg, #28a745, #218838);
            border-color: #45a049;
        }

        .error-notification ul,
        .success-message ul {
            list-style: none;
            padding: 0;
            margin: 0;
        }

        .password-requirements {
            background: rgba(255, 255, 255, 0.1);
            border-left: 4px solid var(--primary);
            padding: 15px;
            margin-bottom: 20px;
            border-radius: 6px;
            text-align: left;
            font-size: 14px;
            color: var(--dark);
        }

        .password-requirements h4 {
            margin: 0 0 10px 0;
            font-size: 16px;
            font-weight: 600;
            color: var(--dark);
        }

        .password-requirements ul {
            list-style: none;
            padding: 0;
            margin: 0;
        }

        .password-requirements li {
            position: relative;
            padding-left: 25px;
            margin-bottom: 8px;
            font-size: 14px;
            line-height: 1.5;
        }

        .password-requirements li::before {
            content: '\f058';
            font-family: 'Font Awesome 5 Free';
            font-weight: 900;
            color: var(--primary);
            position: absolute;
            left: 0;
            top: 2px;
        }

        @import url('https://fonts.googleapis.com/css2?family=Caveat:wght@700&display=swap');
        @import url('https://fonts.googleapis.com/css2?family=Poppins:wght@600&display=swap');

        @media (max-width: 768px) {
            .form-container {
                padding: 15px;
                width: 90%;
            }

            .form-group {
                flex-direction: column;
                align-items: flex-start;
            }

            .form-group label {
                text-align: left;
                flex: 0 0 auto;
                margin-bottom: 8px;
            }

            .form-group input,
            .form-group select {
                width: 100%;
            }

            .btn-save {
                font-size: 14px;
                padding: 10px 20px;
            }

            .password-requirements {
                padding: 12px;
                font-size: 13px;
            }

            .password-requirements h4 {
                font-size: 15px;
            }
        }

        /* calendar */
        #birthday::-webkit-calendar-picker-indicator,
        #password_last_changed::-webkit-calendar-picker-indicator {
            background-color: #FFCE26;
            padding: 6px;
            border-radius: 50%;
            cursor: pointer;
        }
        #birthday::-webkit-calendar-picker-indicator:hover,
        #password_last_changed::-webkit-calendar-picker-indicator:hover {
            background-color: #FFA500;
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

        :root {
            --primary: #2B6CB0;
            --primary-dark: #1E4A7A;
             --transition: all 0.3s ease;
             --white: #FFFFFF;
             --shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06);
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
            <i class='bx bx-menu'></i>
            <form action="search.php" method="get">
                <div class="form-input">
                    <input type="search" name="query" placeholder="Search users, subjects, tracks, sections..." required>
                    <button type="submit" class="search-btn"><i class='bx bx-search'></i></button>
                </div>
            </form>
            <input type="checkbox" id="switch-mode" hidden>
            <label for="switch-mode" class="switch-mode"></label>
            <a href="./main_acc.php" class="profile">
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
                    <h1>Edit Administrator</h1>
                    <ul class="breadcrumb">
                        <li><a href="#">Home</a></li>
                        <li><i class='bx bx-chevron-right'></i></li>
                        <li><a href="#">Administrators</a></li>
                        <li><i class='bx bx-chevron-right'></i></li>
                        <li><a class="active" href="edit_admin.php?userID=<?php echo urlencode(base64_encode(openssl_encrypt($admin['userID'], 'AES-128-ECB', "b7e1a8f9c3d4e2b69a7f5d8e1c4b3a62", 0, ""))); ?>">Edit</a></li>
                    </ul>
                </div>
                <!-- <a href="./data_admin.php" class="btn-download">
                    <i class='bx bxs-left-arrow'></i>
                    <span class="text">Back</span>
                </a> -->
                <a href="./data_admin.php" class="back-button"><i class='bx bx-arrow-back'></i> Back to Data</a>
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
                <form method="POST" class="edit-form">
                    <!-- Part A: Personal Details -->
                    <div class="form-section">
                        <h2>Part A: Personal Details</h2>
                        <div class="form-group">
                            <label for="firstName">First Name</label>
                            <input type="text" name="firstName" id="firstName" value="<?php echo htmlspecialchars($admin['firstName']); ?>" required>
                        </div>
                        <div class="form-group">
                            <label for="lastName">Last Name</label>
                            <input type="text" name="lastName" id="lastName" value="<?php echo htmlspecialchars($admin['lastName']); ?>" required>
                        </div>
                        <div class="form-group">
                            <label for="birthday">Birthday</label>
                            <input type="date" name="birthday" id="birthday" value="<?php echo htmlspecialchars($admin['birthday']); ?>" required>
                        </div>
                        <div class="form-group">
                            <label for="status">Status</label>
                            <select name="status" id="status" required>
                                <option value="Active" <?php echo $admin['status'] === 'Active' ? 'selected' : ''; ?>>Active</option>
                                <option value="Inactive" <?php echo $admin['status'] === 'Inactive' ? 'selected' : ''; ?>>Inactive</option>
                            </select>
                        </div>
                    </div>

                    <!-- Part B: Contact Details -->
                    <div class="form-section">
                        <h2>Part B: Contact Details</h2>
                        <div class="form-group">
                            <label for="email">Email</label>
                            <input type="email" name="email" id="email" value="<?php echo htmlspecialchars($admin['email']); ?>" required>
                        </div>
                        <div class="form-group">
                            <label for="contactNumber">Contact Number</label>
                            <input type="text" name="contactNumber" id="contactNumber" value="<?php echo htmlspecialchars($admin['contactNumber']); ?>" required>
                        </div>
                    </div>

                    <!-- Part C: Update Password -->
                    <div class="form-section">
                        <h2>Part C: Update Password</h2>
                        <div class="form-group">
                            <label for="password_last_changed">Last Password Change (Optional)</label>
                            <input type="date" name="password_last_changed" id="password_last_changed" value="<?php echo $admin['password_last_changed'] ? htmlspecialchars(date('Y-m-d', strtotime($admin['password_last_changed']))) : ''; ?>">
                        </div>
                        <div class="password-requirements">
                            <h4>Password Requirements (Optional)</h4>
                            <ul>
                                <li>Be at least 8 characters long</li>
                                <li>Contain both letters and numbers</li>
                                <li>Not contain *, <, or >, or emojis</li>
                            </ul>
                        </div>
                        <div class="form-group">
                            <label for="currentPassword">Current Password (Optional)</label>
                            <input type="password" id="currentPassword" name="currentPassword">
                        </div>
                        <div class="form-group">
                            <label for="newPassword">New Password (Optional)</label>
                            <input type="password" id="newPassword" name="newPassword">
                        </div>
                        <div class="form-group">
                            <label for="confirmPassword">Confirm Password (Optional)</label>
                            <input type="password" id="confirmPassword" name="confirmPassword">
                        </div>
                    </div>

                    <div class="form-actions">
                        <button class="btn-save" type="submit" name="saveChanges"><i class='bx bxs-save'></i>Save Changes</button>
                    </div>
                </form>
            </div>
        </main>
        <!-- MAIN -->
    </section>
    <!-- CONTENT -->

    <script src="./utils/script.js"></script>
</body>
</html>