<?php
require_once './sessions/session_student.php';
require_once '../../config/db_connection.php';
require_once './auto_logout.php';
require_once './check_status.php';
require_once './academic_events.php';
require_once './check_enrollment_status.php';

$userID = $_SESSION['userID'];

try {
    $stmt = $dbConnection->prepare("SELECT firstName, middleName, lastName, birthday, sex, address, email, contactNumber, nationality, image, status FROM users WHERE userID = :userID");
    $stmt->bindParam(':userID', $userID, PDO::PARAM_INT);
    $stmt->execute();

    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($user) {
        $_SESSION['firstName'] = $user['firstName'];
        $_SESSION['userType'] = 'Student';
        $_SESSION['image'] = $user['image'] ? $user['image'] : './img/noprofile.png';

        // Calculate age from birthday
        $birthday = new DateTime($user['birthday']);
        $today = new DateTime();
        $age = $today->diff($birthday)->y;
    } else {
        header("Location: ../../index.php");
        exit;
    }
} catch (PDOException $e) {
    echo "Error: " . $e->getMessage();
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['saveChanges'])) {
        $firstName = $_POST['firstName'] ?? '';
        $middleName = $_POST['middleName'] ?? '';
        $lastName = $_POST['lastName'] ?? '';
        $birthday = $_POST['birthday'] ?? '';
        $sex = $_POST['sex'] ?? '';
        $address = $_POST['address'] ?? '';
        $email = $_POST['email'] ?? '';
        $contactNumber = $_POST['contactNumber'] ?? '';
        $nationality = $_POST['nationality'] ?? '';
        $status = $_POST['status'] ?? '';

        $imagePath = $user['image'];
        if (isset($_FILES['profileImage']) && $_FILES['profileImage']['error'] == UPLOAD_ERR_OK) {
            $imageName = $_FILES['profileImage']['name'];
            $imageTmpName = $_FILES['profileImage']['tmp_name'];
            $imageExtension = strtolower(pathinfo($imageName, PATHINFO_EXTENSION));
            $allowedExtensions = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
            if (in_array($imageExtension, $allowedExtensions)) {
                $imageNewName = uniqid('', true) . '.' . $imageExtension;
                $imagePath = '../../lib/file_uploads/' . $imageNewName;

                if (!move_uploaded_file($imageTmpName, $imagePath)) {
                    echo "Error uploading image.";
                    exit;
                }
            } else {
                echo "Invalid image format.";
                exit;
            }
        }

        try {
            $stmt = $dbConnection->prepare("UPDATE users SET firstName = :firstName, middleName = :middleName, lastName = :lastName, birthday = :birthday, sex = :sex, address = :address, email = :email, contactNumber = :contactNumber, nationality = :nationality, image = :image, status = :status WHERE userID = :userID");
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
            $stmt->bindParam(':userID', $_SESSION['userID']);
            $stmt->execute();

            $_SESSION['success_messages'] = [
                "Your account has been successfully updated.",
                "Profile details saved successfully!",
                "Changes applied to your account."
            ];
            header("Location: userAcc.php");
            exit;
        } catch (PDOException $e) {
            echo "Error updating user: " . $e->getMessage();
            exit;
        }
    }

    if (isset($_POST['archiveAccount'])) {
        try {
            $stmt = $dbConnection->prepare("UPDATE users SET status = 'Archived' WHERE userID = :userID");
            $stmt->bindParam(':userID', $_SESSION['userID']);
            $stmt->execute();

            session_destroy();
            header("Location: ../../index.php");
            exit;
        } catch (PDOException $e) {
            echo "Error archiving account: " . $e->getMessage();
            exit;
        }
    }
}

require_once './get_notification_count.php';

$userID = isset($_SESSION['userID']) ? filter_var($_SESSION['userID'], FILTER_VALIDATE_INT) : null;
$unreadCount = $userID ? getUnreadAnnouncementCount($dbConnection, $userID) : 0;

if (!isset($_SESSION['announcements_viewed'])) {
    $_SESSION['announcements_viewed'] = false;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link href='https://unpkg.com/boxicons@2.0.9/css/boxicons.min.css' rel='stylesheet'>
    <link rel="icon" href="../../img/learnify-logo.png" type="image/x-icon">
    <link rel="stylesheet" href="./utils/style.css">
    <link rel="stylesheet" href="./utils/userAcc.css">
    <link rel="stylesheet" href="./utils/logout.css">
    <link rel="stylesheet" href="./utils/feedback.css">
    <link rel="stylesheet" href="./utils/animation_slide.css">
    <link rel="stylesheet" href="./utils/subjects_sidebar.css">
    <link rel="stylesheet" href="./css/scrollbar.css">
    <link rel="stylesheet" href="./utils/logo.css">
    <script src="./logout.js"></script>
    <title>Learnify</title>
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
            --font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
        }

        .modal {
            z-index: 10000;
        }

        .notification-badge {
            background: linear-gradient(135deg, #dc3545, #c82333);
            color: white;
            border-radius: 50%;
            padding: 0.3rem 0.6rem;
            font-size: 0.8rem;
            margin-left: 0.5rem;
            display: inline-block;
        }

        .form-wrapper {
            max-width: 1000px;
            margin: 0 auto;
            padding: 30px;
            background-color: var(--light);
            margin-top: 20px;
            border-radius: 12px;
            box-shadow: var(--shadow);
            transition: transform var(--transition);
        }

        .form-container {
            width: 100%;
        }

        .form-group {
            margin-bottom: 20px;
            flex: 1;
        }

        .form-row {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 20px;
            margin-bottom: 20px;
        }

        .form-row.profile-row {
            grid-template-columns: repeat(2, 1fr);
        }

        @media (max-width: 600px) {
            .form-row,
            .form-row.profile-row {
                grid-template-columns: 1fr;
            }
        }

        .form-group label {
            display: block;
            font-weight: 700;
            margin-bottom: 8px;
            color: var(--dark);
            font-size: clamp(1rem, 3vw, 1.3rem);
        }

        .form-group input,
        .form-group select,
        .form-group textarea {
            width: 100%;
            padding: 12px;
            border: 1px solid var(--border);
            background-color: var(--grey);
            border-radius: 8px;
            font-size: clamp(1rem, 3vw, 1.2rem);
            box-sizing: border-box;
            transition: border-color var(--transition), box-shadow var(--transition);
        }

        .form-group input:focus,
        .form-group select:focus,
        .form-group textarea:focus {
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(43, 108, 176, 0.2);
            outline: none;
        }

        .form-group textarea {
            resize: vertical;
            min-height: 80px;
        }

        .form-group input[readonly] {
            background-color: var(--grey);
            color: var(--success);
            cursor: not-allowed;
            opacity: 0.8;
        }

        .profile-image-container {
            margin-bottom: 10px;
        }

        .current-image {
            width: 80px;
            height: 80px;
            object-fit: cover;
            border: 1px solid var(--border);
            transition: transform var(--transition);
        }

        .current-image:hover {
            transform: scale(1.05);
        }

        .form-actions {
            display: flex;
            justify-content: flex-end;
            margin-top: 30px;
        }

        .btn-download,
        .btn-archive {
            padding: 12px 24px;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            font-size: 1rem;
            font-weight: 600;
            transition: background-color var(--transition), transform var(--transition);
        }

        .btn-download {
            background: linear-gradient(135deg, var(--primary), var(--primary-dark));
            color: var(--white);
        }

        .btn-download:hover {
            background: linear-gradient(135deg, var(--primary-dark), #003d80);
            transform: translateY(-2px);
        }

        .btn-archive {
            background-color: var(--error);
            color: var(--white);
        }

        .btn-archive:hover {
            background-color: #b02a37;
            transform: translateY(-2px);
        }

        .form-container h3 {
            font-size: clamp(1.2rem, 3vw, 2rem);
            font-weight: 700;
            border-bottom: 2px solid var(--primary);
            color: var(--dark);
            margin-bottom: 25px;
            padding-bottom: 10px;
        }

        @media (max-width: 600px) {
            .form-wrapper {
                padding: 20px;
            }

            .form-group input,
            .form-group select,
            .form-group textarea {
                font-size: 0.9rem;
                padding: 10px;
            }

            .form-actions {
                flex-direction: column;
                gap: 10px;
                align-items: stretch;
            }

            .btn-download,
            .btn-archive {
                width: 100%;
            }
        }

        #span {
            color: var(--text-secondary);
            font-weight: 400;
            font-size: clamp(0.9rem, 3vw, 1.1rem);
        }

        #birthday::-webkit-calendar-picker-indicator {
            background-color: white;
            color: var(--accent);
            padding: 6px;
            border-radius: 50%;
            border: 1px solid var(--primary-dark);
            cursor: pointer;
            filter: brightness(1.2);
        }

        .notification {
            position: fixed;
            top: 20px;
            right: 20px;
            background-color: var(--success);
            color: var(--white);
            padding: 15px 25px;
            border-radius: 8px;
            box-shadow: var(--shadow);
            z-index: 10001;
            display: none;
            opacity: 0;
            transform: translateY(-20px);
            transition: opacity 0.3s ease, transform 0.3s ease;
            max-width: 320px;
            font-size: 1rem;
            font-weight: 500;
            line-height: 1.4;
        }

        .notification.show {
            display: block;
            opacity: 1;
            transform: translateY(0);
        }

        .notification-exit {
            opacity: 0;
            transform: translateY(-20px);
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

         /* Pending Tasks Badge in Header */
        .dashboard-header-badge {
            background: #ED8936;
            color: white;
            border-radius: 50%;
            padding: 0.25rem 0.55rem;
            font-size: 0.9rem;
            font-weight: 600;
            min-width: 15px;
            text-align: center;
            margin-left: 0.5rem;
            display: inline-flex;
            align-items: center;
            justify-content: center;
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
            <?php require_once './dashboard_nav_item.php' ?>
            <li>
                <a href="./announcements.php?viewed=true">
                    <i class='bx bxs-bell'></i>
                    <span class="text">Announcements</span>
                    <!-- <?php if ($unreadCount > 0 && !$_SESSION['announcements_viewed']): ?>
                        <span class="notification-badge"><?php echo htmlspecialchars($unreadCount); ?></span>
                    <?php endif; ?> -->
                </a>
            </li>
            <li>
                <a href="./calendar.php">
                    <i class='bx bxs-calendar'></i>
                    <span class="text">Calendar</span>
                </a>
            </li>
            <li>
                <a href="./game.php">
                    <i class='bx bxs-game'></i>
                    <span class="text">Games</span>
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
                    <input type="search" name="query" placeholder="Search Teachers and Students" required>
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
                    <h1>Edit Account</h1>
                    <ul class="breadcrumb">
                        <li><a href="./studentDash.php">Home</a></li>
                        <li><i class='bx bx-chevron-right'></i></li>
                        <li><a href="./settings.php">Settings</a></li>
                        <li><i class='bx bx-chevron-right'></i></li>
                        <li><a class="active" href="./userAcc.php">Edit Account</a></li>
                    </ul>
                </div>
                <a href="./settings.php" class="back-button">
                    <i class='bx bx-arrow-back'></i> Back to Settings
                </a>
            </div>

            <!-- Notification Popups -->
            <?php if (isset($_SESSION['success_messages'])): ?>
                <?php foreach ($_SESSION['success_messages'] as $index => $message): ?>
                    <div class="notification" id="notification-<?php echo $index; ?>">
                        <?php echo $message; ?>
                    </div>
                <?php endforeach; ?>
                <?php unset($_SESSION['success_messages']); ?>
            <?php endif; ?>

            <div class="form-wrapper">
                <div class="form-container">
                    <form action="userAcc.php" method="POST" enctype="multipart/form-data">
                        <h3><i class='bx bxs-id-card' style="margin-right: 5px; background-color: var(--primary); color: white; padding: 7px; border-radius: 50%;"></i> Personal Details</h3>
                        <div class="form-row">
                            <div class="form-group">
                                <label for="firstName">First Name</label>
                                <input type="text" name="firstName" id="firstName" value="<?php echo $user['firstName']; ?>" required>
                            </div>
                            <div class="form-group">
                                <label for="middleName">Middle Name <span id="span">(Optional)</span></label>
                                <input type="text" name="middleName" id="middleName" value="<?php echo $user['middleName']; ?>">
                            </div>
                            <div class="form-group">
                                <label for="lastName">Last Name</label>
                                <input type="text" name="lastName" id="lastName" value="<?php echo $user['lastName']; ?>" required>
                            </div>
                        </div>
                        <div class="form-row">
                            <div class="form-group">
                                <label for="birthday">Birthday</label>
                                <input type="date" name="birthday" id="birthday" value="<?php echo $user['birthday']; ?>" required>
                            </div>
                            <div class="form-group">
                                <label for="sex">Sex</label>
                                <select name="sex" id="sex">
                                    <option value="Male" <?php echo $user['sex'] == 'Male' ? 'selected' : ''; ?>>Male</option>
                                    <option value="Female" <?php echo $user['sex'] == 'Female' ? 'selected' : ''; ?>>Female</option>
                                </select>
                            </div>
                            <div class="form-group">
                                <label for="nationality">Nationality <span id="span">(Optional)</span></label>
                                <input type="text" name="nationality" id="nationality" value="<?php echo $user['nationality']; ?>">
                            </div>
                        </div>

                        <h3><i class='bx bxs-contact' style="margin-right: 5px; background-color: var(--primary); color: white; padding: 7px; border-radius: 50%;"></i> Contact Information</h3>
                        <div class="form-row">
                            <div class="form-group">
                                <label for="email">Email</label>
                                <input type="email" name="email" id="email" value="<?php echo $user['email']; ?>" required>
                            </div>
                            <div class="form-group">
                                <label for="contactNumber">Contact Number</label>
                                <input type="text" name="contactNumber" id="contactNumber" value="<?php echo $user['contactNumber']; ?>">
                            </div>
                            <div class="form-group">
                                <label for="address">Address <span id="span">(Optional)</span></label>
                                <textarea name="address" id="address"><?php echo $user['address']; ?></textarea>
                            </div>
                        </div>

                        <h3><i class='bx bxs-user-detail' style="margin-right: 5px; background-color: var(--primary); color: white; padding: 7px; border-radius: 50%;"></i> Profile</h3>
                        <div class="form-row profile-row">
                            <div class="form-group">
                                <label for="profileImage">Profile Image</label>
                                <div class="profile-image-container">
                                    <img src="<?php echo $user['image']; ?>" alt="Profile Image" id="currentImage" class="current-image">
                                </div>
                                <input type="file" name="profileImage" id="profileImage">
                            </div>
                            <div class="form-group">
                                <label for="status">Status <span id="span">(Read Only)</span></label>
                                <input type="text" name="status" id="status" value="<?php echo $user['status']; ?>" readonly>
                            </div>
                        </div>

                        <div class="form-actions">
                            <button class="btn-download" type="submit" name="saveChanges">Save Changes</button>
                        </div>
                    </form>
                </div>
            </div>
        </main>
        <!-- MAIN -->
    </section>
    <!-- CONTENT -->

    <script src="./utils/script.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const notifications = document.querySelectorAll('.notification');
            if (notifications.length > 0) {
                notifications.forEach((notification, index) => {
                    setTimeout(() => {
                        notification.classList.add('show');
                        setTimeout(() => {
                            notification.classList.add('notification-exit');
                            setTimeout(() => {
                                notification.style.display = 'none';
                            }, 300);
                        }, 3000);
                    }, index * 3500);
                });
            }
        });
    </script>
</body>
</html>