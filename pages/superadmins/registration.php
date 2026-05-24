<?php
require_once './sessions/session_superAdmin.php';
require_once '../../config/db_connection.php';
require_once './auto_logout.php';

// Initialize variables
$errors = [];
$firstName = $middleName = $lastName = $birthday = $sex = $address = $email = $contactNumber = $nationality = $lrn = $userType = $generatedCode = '';
$imagePath = './img/noprofile.png';

// Restore form data from POST or session
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['registerUser'])) {
    $firstName = $_POST['firstName'] ?? '';
    $middleName = $_POST['middleName'] ?? '';
    $lastName = $_POST['lastName'] ?? '';
    $birthday = $_POST['birthday'] ?? '';
    $sex = $_POST['sex'] ?? '';
    $address = $_POST['address'] ?? '';
    $email = $_POST['email'] ?? '';
    $contactNumber = $_POST['contactNumber'] ?? '';
    $nationality = $_POST['nationality'] ?? '';
    $lrn = $_POST['lrn'] ?? '';
    $userType = $_POST['userType'] ?? '';
    $generatedCode = $_POST['generatedCode'] ?? '';

    // Store generatedCode in session for persistence
    if (!empty($generatedCode)) {
        $_SESSION['temp_generated_code'] = $generatedCode;
    }
} elseif (isset($_SESSION['temp_generated_code'])) {
    $generatedCode = $_SESSION['temp_generated_code'];
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['registerUser'])) {
    // Default password and status
    $password = 'DIHS_12345';
    $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
    $status = 'Active';

    // Validation
    if (empty($firstName)) {
        $errors['firstName'] = '⚠️ First Name is required';
    }
    if (empty($lastName)) {
        $errors['lastName'] = '⚠️ Last Name is required';
    }
    if (empty($birthday)) {
        $errors['birthday'] = '⚠️ Birthday is required';
    } else {
        // Validate birthday (must be at least 15 years old)
        $today = new DateTime('2025-09-25');
        $birthDate = new DateTime($birthday);
        $age = $today->diff($birthDate)->y;
        if ($age < 15) {
            $errors['birthday'] = '⚠️ User must be at least 15 years old';
        }
    }
    if (empty($sex)) {
        $errors['sex'] = '⚠️ Sex is required';
    }
    if (empty($email)) {
        $errors['email'] = '⚠️ Email is required';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors['email'] = '⚠️ Please enter a valid email';
    } elseif (!preg_match('/@(gmail\.com|yahoo\.com)$/', $email)) {
        $errors['email'] = '⚠️ Email must be from @gmail.com or @yahoo.com';
    } else {
        $stmt = $dbConnection->prepare("SELECT COUNT(*) FROM users WHERE email = :email");
        $stmt->bindParam(':email', $email);
        $stmt->execute();
        if ($stmt->fetchColumn() > 0) {
            $errors['email'] = '⚠️ Email is already in use';
        }
    }
    if (empty($address)) {
        $errors['address'] = '⚠️ Address is required';
    }
    if (empty($nationality)) {
        $errors['nationality'] = '⚠️ Nationality is required';
    }
    if (empty($contactNumber)) {
        $errors['contactNumber'] = '⚠️ Contact Number is required';
    } elseif (!preg_match('/^09\d{9}$/', $contactNumber)) {
        $errors['contactNumber'] = '⚠️ Contact number must be 11 digits and start with 09';
    } else {
        $stmt = $dbConnection->prepare("SELECT COUNT(*) FROM users WHERE contactNumber = :contactNumber");
        $stmt->bindParam(':contactNumber', $contactNumber);
        $stmt->execute();
        if ($stmt->fetchColumn() > 0) {
            $errors['contactNumber'] = '⚠️ Contact number is already in use';
        }
    }
    // Validation for LRN/Employee ID/Teacher ID based on userType
    if (empty($lrn) && !empty($userType)) {
        if ($userType === 'SuperAdmin' || $userType === 'Admin') {
            $errors['lrn'] = '⚠️ Employee ID is required';
        } elseif ($userType === 'Professor') {
            $errors['lrn'] = '⚠️ Teacher ID is required';
        } else {
            $errors['lrn'] = '⚠️ LRN is required';
        }
    } elseif (!empty($lrn)) {
        if (($userType === 'SuperAdmin' || $userType === 'Admin') && !preg_match('/^\d{5}$/', $lrn)) {
            $errors['lrn'] = '⚠️ Employee ID must be 5 digits';
        } elseif ($userType === 'Professor' && !preg_match('/^\d{5}$/', $lrn)) {
            $errors['lrn'] = '⚠️ Teacher ID must be 5 digits';
        } elseif ($userType === 'Student' && !preg_match('/^\d{12}$/', $lrn)) {
            $errors['lrn'] = '⚠️ LRN must be 12 digits';
        } else {
            // Check for uniqueness of LRN/Employee ID/Teacher ID
            $stmt = $dbConnection->prepare("SELECT COUNT(*) FROM users WHERE lrn = :lrn");
            $stmt->bindParam(':lrn', $lrn);
            $stmt->execute();
            if ($stmt->fetchColumn() > 0) {
                if ($userType === 'SuperAdmin' || $userType === 'Admin') {
                    $errors['lrn'] = '⚠️ Employee ID is already in use';
                } elseif ($userType === 'Professor') {
                    $errors['lrn'] = '⚠️ Teacher ID is already in use';
                } else {
                    $errors['lrn'] = '⚠️ LRN is already in use';
                }
            }
        }
    }
    if (isset($_FILES['profileImage']) && $_FILES['profileImage']['error'] == UPLOAD_ERR_OK) {
        $imageName = $_FILES['profileImage']['name'];
        $imageTmpName = $_FILES['profileImage']['tmp_name'];
        $imageExtension = strtolower(pathinfo($imageName, PATHINFO_EXTENSION));
        $allowedExtensions = ['jpg', 'jpeg', 'png', 'gif'];
        if (in_array($imageExtension, $allowedExtensions)) {
            $imageNewName = uniqid('', true) . '.' . $imageExtension;
            $imagePath = './Uploads/' . $imageNewName;
            if (!move_uploaded_file($imageTmpName, $imagePath)) {
                $errors['profileImage'] = '⚠️ Error uploading image';
            }
        } else {
            $errors['profileImage'] = '⚠️ Invalid image format';
        }
    }
    if (empty($userType)) {
        $errors['userType'] = '⚠️ User Type is required';
    }
    if (empty($generatedCode)) {
        $errors['generatedCode'] = '⚠️ QR Code is required';
    }

    // If no errors, insert into database
    if (empty($errors)) {
        try {
            $stmt = $dbConnection->prepare("
                INSERT INTO users (firstName, middleName, lastName, birthday, sex, address, email, contactNumber, nationality, image, status, password, lrn, userType, generated_code)
                VALUES (:firstName, :middleName, :lastName, :birthday, :sex, :address, :email, :contactNumber, :nationality, :image, :status, :password, :lrn, :userType, :generated_code)
            ");
            $stmt->bindParam(':firstName', $firstName);
            $stmt->bindParam(':middleName', $middleName);
            $stmt->bindParam(':lastName', $lastName);
            $stmt->bindParam(':birthday', $birthday);
            $stmt->bindParam(':sex', $sex);
            $stmt->bindParam(':address', $address);
            $stmt->bindParam(':email', $email);
            $stmt->bindParam(':contactNumber', $contactNumber);
            $stmt->bindParam(':nationality', $nationality);
            $stmt->bindParam(':image', $imagePath);
            $stmt->bindParam(':status', $status);
            $stmt->bindParam(':password', $hashedPassword);
            $stmt->bindParam(':lrn', $lrn);
            $stmt->bindParam(':userType', $userType);
            $stmt->bindParam(':generated_code', $generatedCode);
            $stmt->execute();

            // Save to CSV
            $file = fopen('users_data.csv', 'a');
            if ($file) {
                fputcsv($file, [$firstName, $middleName, $lastName, $birthday, $sex, $address, $email, $contactNumber, $nationality, $imagePath, $status, 'DIHS_12345', $lrn, $userType, $generatedCode]);
                fclose($file);
            }

            // Save to JSON
            $userData = [
                'firstName' => $firstName,
                'middleName' => $middleName,
                'lastName' => $lastName,
                'birthday' => $birthday,
                'sex' => $sex,
                'address' => $address,
                'email' => $email,
                'contactNumber' => $contactNumber,
                'nationality' => $nationality,
                'image' => $imagePath,
                'status' => $status,
                'password' => $hashedPassword,
                ($userType === 'SuperAdmin' || $userType === 'Admin') ? 'employeeId' : ($userType === 'Professor' ? 'teacherId' : 'lrn') => $lrn,
                'userType' => $userType,
                'generated_code' => $generatedCode
            ];
            $jsonFilePath = 'users_data.json';
            $jsonData = file_exists($jsonFilePath) ? json_decode(file_get_contents($jsonFilePath), true) : [];
            $jsonData[] = $userData;
            file_put_contents($jsonFilePath, json_encode($jsonData, JSON_PRETTY_PRINT));

            // Send to Google Sheets
            $webhookURL = 'https://script.google.com/macros/s/AKfycbzGr9HQSDGsgrAHbqIwiWWLbkT7qEan-hGiaNVKcfbGku_XgDveVd9QFmdoan3W5GTa/exec';
            $postData = json_encode([
                'firstName' => $firstName,
                'middleName' => $middleName,
                'lastName' => $lastName,
                'birthday' => $birthday,
                'sex' => $sex,
                'address' => $address,
                'email' => $email,
                'contactNumber' => $contactNumber,
                'nationality' => $nationality,
                'lrn' => $lrn,
                ($userType === 'SuperAdmin' || $userType === 'Admin') ? 'employeeId' : ($userType === 'Professor' ? 'teacherId' : 'lrn') => $lrn,
                'userType' => $userType,
                'generated_code' => $generatedCode
            ]);
            $ch = curl_init($webhookURL);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $postData);
            curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
            $response = curl_exec($ch);
            curl_close($ch);

            // Clear temporary session data
            unset($_SESSION['temp_generated_code']);
            $_SESSION['success_message'] = 'User has been successfully registered.';
            header('Location: registration.php');
            exit;
        } catch (PDOException $e) {
            $errors['database'] = 'Error registering user: ' . $e->getMessage();
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link href="https://unpkg.com/boxicons@2.0.9/css/boxicons.min.css" rel="stylesheet">
    <link rel="icon" href="../../img/learnify-logo.png" type="image/x-icon">
    <link rel="stylesheet" href="./utils/style.css">
    <link rel="stylesheet" href="./utils/userAcc.css">
    <link rel="stylesheet" href="./utils/logout.css">
    <link rel="stylesheet" href="./utils/animation_slide.css">
    <link rel="stylesheet" href="./utils/logo.css">
    <link rel="stylesheet" href="./css/scrollbar.css">
    <script src="./logout.js"></script>
    <style>
        :root {
            --light: #F9F9F9;
            --blue: #3C91E6;
            --light-blue: #CFE8FF;
            --grey: #eee;
            --dark-grey: #AAAAAA;
            --dark: #342E37;
            --red: #a90c0c;
            --yellow: #FFCE26;
            --light-yellow: #FFF2C6;
            --orange: #FD7238;
            --light-orange: #FFE0D3;
        }

        .form-container {
            margin-top: 14px !important;
            max-width: 600px;
            margin: 0 auto;
            background: var(--light);
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
        }

        .form-section {
            margin-bottom: 30px;
        }

        .form-section h4 {
            font-size: clamp(1.5rem, 3vw, 1.6rem);
            color: red;
            font-weight: 500;
            border-bottom: 1px solid #ccc;
            padding-bottom: 10px;
            margin-bottom: 20px;
            margin-top: 20px;
        }

        .form-group {
            display: grid;
            grid-template-columns: 40px 150px 1fr auto;
            align-items: center;
            gap: 10px;
            margin-bottom: 20px;
            position: relative;
        }

        .form-group .icon-container {
            display: flex;
            align-items: center;
            justify-content: center;
            background-color: var(--light-blue);
            border-radius: 4px;
            width: 30px;
            height: 30px;
        }

        .form-group .icon-container i {
            color: var(--blue);
            font-size: 1.2rem;
        }

        .form-group label {
            font-size: clamp(1.1rem, 3vw, 1.2rem);
            color: var(--dark);
            font-weight: 600;
            text-align: left;
        }

        .form-group label span {
            color: var(--red);
        }

        .form-group input,
        .form-group select,
        .form-group textarea {
            width: 100%;
            padding: 10px;
            font-size: clamp(1.1rem, 3vw, 1.2rem);
            border: 1px solid var(--dark-grey);
            border-radius: 4px;
            box-sizing: border-box;
            background-color: var(--grey);
        }

        .form-group input:focus,
        .form-group select:focus,
        .form-group input:not(:placeholder-shown),
        .form-group select:not(:placeholder-shown) {
            border-color: var(--blue);
            outline: none;
        }

        .radio-group {
            display: flex;
            gap: 20px;
        }

        .radio-group label {
            display: flex;
            align-items: center;
            font-size: clamp(1.1rem, 3vw, 1.2rem);
            color: var(--dark);
        }

        .radio-group input[type="radio"] {
            margin-right: 5px;
        }

        textarea {
            resize: vertical;
        }

        .form-actions {
            display: flex;
            justify-content: flex-end;
            gap: 10px;
            margin-top: 20px;
        }

        .title-user {
            text-align: left;
            font-size: clamp(1.9rem, 3vw, 2rem);
            border-bottom: 1px solid #ccc;
            color: var(--dark);
            padding-bottom: 10px;
        }

        .form-actions button {
            height: 36px;
            padding: 0 16px;
            font-size: clamp(1.1rem, 3vw, 1.2rem);
            border-radius: 36px;
            color: var(--light);
            display: flex;
            justify-content: center;
            align-items: center;
            grid-gap: 10px;
            font-weight: 500;
            border: none;
            outline: none;
        }

        .form-actions .btn-archive {
            background-color: var(--red);
            color: #ffffff;
        }

        .form-actions .btn-archive:hover {
            background-color: #820a0a;
        }

        .form-actions .btn-download {
            background: linear-gradient(135deg, #28a745, #218838);
            color: #ffffff;
            width: auto;
            border-radius: 10px;
        }

        .form-actions .btn-download:hover {
            background: linear-gradient(135deg, #218838, #1e7e34);
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.3);
        }

        input[readonly], select[readonly] {
            background-color: var(--light);
            color: var(--dark);
            border: 1px solid #ccc;
        }

        .form-group input[type="date"] {
            padding: 10px;
        }

        .form-group input[type="file"] {
            padding: 10px;
            border: none;
            background: none;
        }

        .form-group input[type="file"]::file-selector-button {
            background: linear-gradient(135deg, #007bff, #0056b3);
            color: white;
            padding: 5px 10px;
            border: none;
            border-radius: 4px;
            font-size: clamp(1.1rem, 3vw, 1.2rem);
            cursor: pointer;
        }

        .form-group input[type="file"]::file-selector-button:hover {
            background: linear-gradient(135deg, #0056b3, #003d80);
        }

        .mail-merge {
            text-align: center;
        }

        .btn-merge {
            gap: 8px;
            background: linear-gradient(135deg, #28a745, #218838);
            color: white;
            padding: 8px 12px;
            float: right;
            text-decoration: none;
            border-radius: 8px;
            font-size: 1rem;
            transition: background-color 0.3s, transform 0.2s;
        }

        .btn-merge i {
            font-size: 1rem;
            margin-right: 5px;
        }

        .btn-merge:hover {
            background: linear-gradient(135deg, #218838, #1e7e34);
            transform: scale(1.05);
        }

        @media (max-width: 768px) {
            .form-group {
                grid-template-columns: 1fr;
                text-align: left;
            }
            .form-group .icon-container {
                display: none;
            }
            .form-group label {
                margin-bottom: 5px;
            }
            .form-group button {
                width: 100%;
                text-align: center;
            }
            .radio-group {
                flex-direction: column;
                gap: 10px;
            }
        }

        @media (max-width: 460px) {
            .btn-merge {
                margin-top: -35px;
                margin-right: 0px;
            }
            .form-actions .btn-download {
                height: 30px;
                font-size: 0.9rem;
                padding: 0 10px;
            }
        }

        .success-notification {
            background: linear-gradient(135deg, #28a745, #218838);
            color: white;
            padding: 15px;
            margin-bottom: 20px;
            font-size: clamp(1.1rem, 3vw, 1.2rem);
            border-radius: 5px;
            text-align: center;
            font-weight: bold;
        }

        .error {
            color: var(--red);
            font-size: clamp(1.1rem, 3vw, 1.2rem);
            display: block;
            grid-column: 3 / 4;
        }

        #birthday::-webkit-calendar-picker-indicator {
            background-color: var(--yellow);
            padding: 6px;
            border-radius: 50%;
            cursor: pointer;
        }

        #birthday::-webkit-calendar-picker-indicator:hover {
            background-color: #FFA500;
        }

        .profile-image-container {
            max-width: 100px;
            max-height: 100px;
            border-radius: 50%;
            overflow: hidden;
        }

        .qr_btn, #downloadQrBtn {
            background: linear-gradient(135deg, #007bff, #0056b3);
            color: white;
            border: none;
            padding: 5px 10px;
            border-radius: 4px;
            cursor: pointer;
            font-size: clamp(1.1rem, 3vw, 1.2rem);
            transition: 0.3s;
        }

        .qr_btn:hover, #downloadQrBtn:hover {
            background: linear-gradient(135deg, #0056b3, #003d80);
        }

        .qr-code-container {
            text-align: center;
            margin-top: 20px;
        }

        #qrBox {
            display: none;
            padding: 15px;
            background: white;
            border: 1px solid #ddd;
            border-radius: 10px;
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.2);
            margin-bottom: 10px;
            margin-left: auto;
            margin-right: auto;
        }

        #qrImg {
            max-width: 200px;
            height: auto;
        }

        #lrnGroup {
            display: none;
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
    <title>Learnify</title>
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
            <li class="active">
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
            <i class="bx bx-menu"></i>
            <form action="search.php" method="get">
                <div class="form-input">
                    <input type="search" name="query" placeholder="Search users, subjects, tracks, sections..." required>
                    <button type="submit" class="search-btn"><i class="bx bx-search"></i></button>
                </div>
            </form>
            <input type="checkbox" id="switch-mode" hidden>
            <label for="switch-mode" class="switch-mode"></label>
            <a href="./main_acc.php" class="profile">
                <img src="<?php echo isset($_SESSION['image']) ? $_SESSION['image'] : './img/noprofile.png'; ?>" alt="Profile Image">
                <div>
                    <p><?php echo $_SESSION['firstName']; ?></p>
                    <small><?php echo $_SESSION['userType']; ?></small>
                </div>
            </a>
        </nav>
        <!-- NAVBAR -->

        <!-- MAIN -->
        <main>
            <div class="head-title">
                <div class="left">
                    <h1>Register Admin</h1>
                    <ul class="breadcrumb">
                        <li><a href="#">Home</a></li>
                        <li><i class="bx bx-chevron-right"></i></li>
                        <li><a class="active" href="./registration.php">User Registration</a></li>
                    </ul>
                </div>
                 <a href="./register-csv.php" class="back-button">
                    <i class="bx bxs-cloud-upload"></i> CSV Upload
                </a>
            </div>

            <?php if (isset($_SESSION['success_message'])): ?>
                <div class="success-notification">
                    <?php
                    echo $_SESSION['success_message'];
                    unset($_SESSION['success_message']);
                    ?>
                </div>
            <?php endif; ?>
            <?php if (isset($errors['database'])): ?>
                <div class="error-notification" style="background-color: #dc3545; color: white; padding: 10px; margin-bottom: 20px; border-radius: 5px;">
                    <?php echo $errors['database']; ?>
                </div>
            <?php endif; ?>

            <div class="form-container">
                <h3 class="title-user">Register Admin</h3>
                <form action="registration.php" method="POST" enctype="multipart/form-data" onsubmit="return validateForm()">
                    <div class="form-section">
                        <h4>Part A: Profile Information</h4>
                        <div class="form-group">
                            <div class="icon-container"><i class='bx bx-user'></i></div>
                            <label for="firstName">First Name <span>*</span></label>
                            <input type="text" name="firstName" id="firstName" value="<?php echo htmlspecialchars($firstName); ?>" placeholder=" ">
                            <?php if (isset($errors['firstName'])): ?>
                                <span class="error"><?php echo $errors['firstName']; ?></span>
                            <?php endif; ?>
                        </div>
                        <div class="form-group">
                            <div class="icon-container"><i class='bx bx-user'></i></div>
                            <label for="middleName">Middle Name</label>
                            <input type="text" name="middleName" id="middleName" value="<?php echo htmlspecialchars($middleName); ?>" placeholder=" ">
                        </div>
                        <div class="form-group">
                            <div class="icon-container"><i class='bx bx-user'></i></div>
                            <label for="lastName">Last Name <span>*</span></label>
                            <input type="text" name="lastName" id="lastName" value="<?php echo htmlspecialchars($lastName); ?>" placeholder=" ">
                            <?php if (isset($errors['lastName'])): ?>
                                <span class="error"><?php echo $errors['lastName']; ?></span>
                            <?php endif; ?>
                        </div>
                        <div class="form-group">
                            <div class="icon-container"><i class='bx bx-group'></i></div>
                            <label for="userType">User Type <span>*</span></label>
                            <select name="userType" id="userType" placeholder=" ">
                                <option value="" disabled selected></option>
                                <option value="SuperAdmin" <?php echo $userType == 'SuperAdmin' ? 'selected' : ''; ?>>SuperAdmin</option>
                                <option value="Admin" <?php echo $userType == 'Admin' ? 'selected' : ''; ?>>Admin</option>
                            </select>
                            <?php if (isset($errors['userType'])): ?>
                                <span class="error"><?php echo $errors['userType']; ?></span>
                            <?php endif; ?>
                        </div>
                        <div class="form-group" id="lrnGroup">
                            <div class="icon-container"><i class='bx bx-id-card'></i></div>
                            <label for="lrn" id="lrnLabel">Employee ID <span>*</span></label>
                            <input type="text" name="lrn" id="lrn" value="<?php echo htmlspecialchars($lrn); ?>" placeholder=" ">
                            <?php if (isset($errors['lrn'])): ?>
                                <span class="error"><?php echo $errors['lrn']; ?></span>
                            <?php endif; ?>
                        </div>
                        <div class="form-group">
                            <div class="icon-container"><i class='bx bx-calendar'></i></div>
                            <label for="birthday">Birthday <span>*</span></label>
                            <input type="date" name="birthday" id="birthday" value="<?php echo htmlspecialchars($birthday); ?>" placeholder=" ">
                            <?php if (isset($errors['birthday'])): ?>
                                <span class="error"><?php echo $errors['birthday']; ?></span>
                            <?php endif; ?>
                        </div>
                        <div class="form-group">
                            <div class="icon-container"><i class='bx bx-male-female'></i></div>
                            <label>Sex <span>*</span></label>
                            <div class="radio-group">
                                <label><input type="radio" name="sex" value="Male" <?php echo $sex == 'Male' ? 'checked' : ''; ?>> Male</label>
                                <label><input type="radio" name="sex" value="Female" <?php echo $sex == 'Female' ? 'checked' : ''; ?>> Female</label>
                            </div>
                            <?php if (isset($errors['sex'])): ?>
                                <span class="error"><?php echo $errors['sex']; ?></span>
                            <?php endif; ?>
                        </div>
                        <div class="form-group">
                            <div class="icon-container"><i class='bx bx-image'></i></div>
                            <label for="profileImage">Profile Image</label>
                            <input type="file" name="profileImage" id="profileImage" accept="image/*">
                            <?php if (isset($errors['profileImage'])): ?>
                                <span class="error"><?php echo $errors['profileImage']; ?></span>
                            <?php endif; ?>
                        </div>
                    </div>
                    <div class="form-section">
                        <h4>Part B: Contact Information</h4>
                        <div class="form-group">
                            <div class="icon-container"><i class='bx bx-envelope'></i></div>
                            <label for="email">Email <span>*</span></label>
                            <input type="email" name="email" id="email" value="<?php echo htmlspecialchars($email); ?>" placeholder=" ">
                            <?php if (isset($errors['email'])): ?>
                                <span class="error"><?php echo $errors['email']; ?></span>
                            <?php endif; ?>
                        </div>
                        <div class="form-group">
                            <div class="icon-container"><i class='bx bx-home'></i></div>
                            <label for="address">Address <span>*</span></label>
                            <input type="text" name="address" id="address" value="<?php echo htmlspecialchars($address); ?>" placeholder=" ">
                            <?php if (isset($errors['address'])): ?>
                                <span class="error"><?php echo $errors['address']; ?></span>
                            <?php endif; ?>
                        </div>
                        <div class="form-group">
                            <div class="icon-container"><i class='bx bx-flag'></i></div>
                            <label for="nationality">Nationality <span>*</span></label>
                            <select name="nationality" id="nationality" placeholder=" ">
                                <option value="" disabled <?php echo empty($nationality) ? 'selected' : ''; ?>></option>
                                <option value="Filipino" <?php echo $nationality == 'Filipino' ? 'selected' : ''; ?>>Filipino</option>
                                <option value="American" <?php echo $nationality == 'American' ? 'selected' : ''; ?>>American</option>
                                <option value="Others" <?php echo $nationality == 'Others' ? 'selected' : ''; ?>>Others</option>
                            </select>
                            <?php if (isset($errors['nationality'])): ?>
                                <span class="error"><?php echo $errors['nationality']; ?></span>
                            <?php endif; ?>
                        </div>
                        <div class="form-group">
                            <div class="icon-container"><i class='bx bx-phone'></i></div>
                            <label for="contactNumber">Contact Number <span>*</span></label>
                            <input type="text" name="contactNumber" id="contactNumber" value="<?php echo htmlspecialchars($contactNumber); ?>" placeholder=" ">
                            <?php if (isset($errors['contactNumber'])): ?>
                                <span class="error"><?php echo $errors['contactNumber']; ?></span>
                            <?php endif; ?>
                        </div>
                    </div>
                    <div class="form-section">
                        <h4>QR Code</h4>
                        <div class="form-group">
                            <div class="icon-container"><i class='bx bx-qr'></i></div>
                            <label for="generatedCode">QR Code <span>*</span></label>
                            <input type="text" id="generatedCode" name="generatedCode" value="<?php echo htmlspecialchars($generatedCode); ?>" readonly required placeholder=" ">
                            <button class="qr_btn" type="button" onclick="generateQrCode()">Generate QR Code</button>
                            <?php if (isset($errors['generatedCode'])): ?>
                                <span class="error"><?php echo $errors['generatedCode']; ?></span>
                            <?php endif; ?>
                        </div>
                        <!-- QR Code Display -->
                        <div class="qr-code-container">
                            <div id="qrBox">
                                <img id="qrImg" src="" alt="QR Code">
                            </div>
                            <button type="button" id="downloadQrBtn" onclick="downloadQrCode()">Download QR Code</button>
                        </div>
                    </div>
                    <hr>
                    <div class="form-actions">
                        <button class="btn-download" type="submit" name="registerUser">Register</button>
                    </div>
                </form>
            </div>
        </main>
    </section>
    <!-- CONTENT -->

    <script src="./utils/script.js"></script>
    <script>
        // QR Code Generation Logic
        function generateRandomCode(length) {
            const characters = '0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz';
            let randomString = '';
            for (let i = 0; i < length; i++) {
                const randomIndex = Math.floor(Math.random() * characters.length);
                randomString += characters.charAt(randomIndex);
            }
            return randomString;
        }

        function generateQrCode() {
            const qrImg = document.getElementById('qrImg');
            const qrBox = document.getElementById('qrBox');
            const downloadBtn = document.getElementById('downloadQrBtn');
            const generatedCodeInput = document.getElementById('generatedCode');

            const text = generateRandomCode(10);
            generatedCodeInput.value = text;

            if (!text) {
                alert('Failed to generate QR code. Please try again.');
                return;
            }

            const apiUrl = `https://api.qrserver.com/v1/create-qr-code/?size=150x150&data=${encodeURIComponent(text)}`;
            qrImg.src = apiUrl;
            qrBox.style.display = 'inline-block';
            downloadBtn.style.display = 'inline-block';
        }

        function downloadQrCode() {
            const qrImg = document.getElementById('qrImg');
            const generatedCode = document.getElementById('generatedCode').value;

            if (!qrImg.src || !generatedCode) {
                alert('No QR code generated yet.');
                return;
            }

            const link = document.createElement('a');
            link.href = qrImg.src;
            link.download = `QR_Code_${generatedCode}.png`;
            document.body.appendChild(link);
            link.click();
            document.body.removeChild(link);
        }

        document.addEventListener('DOMContentLoaded', function() {
            const userTypeSelect = document.getElementById('userType');
            const lrnGroup = document.getElementById('lrnGroup');
            const lrnLabel = document.getElementById('lrnLabel');
            const lrnInput = document.getElementById('lrn');

            if (!lrnLabel || !lrnInput || !userTypeSelect || !lrnGroup) {
                console.error('One or more elements (lrnLabel, lrnInput, userType, lrnGroup) not found in DOM');
                return;
            }

            function updateLabelAndPlaceholder() {
                const userType = userTypeSelect.value;
                if (userType) {
                    lrnGroup.style.display = 'grid';
                    if (userType === 'SuperAdmin' || userType === 'Admin') {
                        lrnLabel.textContent = 'Employee ID';
                        lrnInput.placeholder = ' ';
                    } else if (userType === 'Professor') {
                        lrnLabel.textContent = 'Teacher ID';
                        lrnInput.placeholder = ' ';
                    } else if (userType === 'Student') {
                        lrnLabel.textContent = 'LRN';
                        lrnInput.placeholder = ' ';
                    }
                } else {
                    lrnGroup.style.display = 'none';
                    lrnLabel.textContent = 'Employee ID';
                    lrnInput.placeholder = ' ';
                }
            }

            updateLabelAndPlaceholder();

            userTypeSelect.addEventListener('change', updateLabelAndPlaceholder);
        });
    </script>
</body>
</html>