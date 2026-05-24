<?php
date_default_timezone_set('Asia/Manila');
require_once './sessions/session_student.php';
require_once '../../config/db_connection.php';
require_once './auto_logout.php';
require_once './check_status.php';
require_once './academic_events.php';
require_once './check_enrollment_status.php';

// Validate session
if (!isset($_SESSION['userID'])) {
    header("Location: ../../index.php");
    exit;
}

$userID = filter_var($_SESSION['userID'], FILTER_VALIDATE_INT);
if ($userID === false) {
    session_destroy();
    header("Location: ../../index.php");
    exit;
}

// Validate teacherSectionID
$teacherSectionID = isset($_GET['teacherSectionID']) ? filter_var($_GET['teacherSectionID'], FILTER_VALIDATE_INT) : null;
if (!$teacherSectionID) {
    $_SESSION['error_message'] = "Invalid class ID.";
    header("Location: studentDash.php");
    exit;
}

try {
    // Get user details
    $stmt = $dbConnection->prepare("SELECT firstName, userType, image FROM users WHERE userID = :userID");
    $stmt->bindParam(':userID', $userID, PDO::PARAM_INT);
    $stmt->execute();
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($user) {
        $_SESSION['firstName'] = htmlspecialchars($user['firstName']);
        $_SESSION['userType'] = htmlspecialchars($user['userType']);
        $_SESSION['image'] = $user['image'] ? htmlspecialchars($user['image']) : './img/noprofile.png';
    } else {
        header("Location: ../../index.php");
        exit;
    }

    // Fetch class details including token
    $sql = "
        SELECT 
            ts.teacherSectionID,
            ts.sectionID,
            s.sectionName,
            ts.subjectID,
            sub.subjectName,
            ts.teacherID,
            CONCAT(u.firstName, ' ', u.lastName) AS teacherName,
            ts.startTime,
            ts.endTime,
            ts.day,
            ts.token
        FROM teacher_section ts
        JOIN users u ON ts.teacherID = u.userID
        JOIN sections s ON ts.sectionID = s.sectionID
        JOIN subjects sub ON ts.subjectID = sub.subjectID
        JOIN student_section ss ON ts.sectionID = ss.sectionID
        WHERE ts.teacherSectionID = :teacherSectionID 
        AND ss.userID = :userID 
        AND ss.status = 'Enrolled' 
        AND ts.archived = 0 
        AND u.archived = 0 
        AND s.archived = 0 
        AND sub.archived = 0 
        AND ss.archived = 0";
    $stmt = $dbConnection->prepare($sql);
    $stmt->bindParam(':teacherSectionID', $teacherSectionID, PDO::PARAM_INT);
    $stmt->bindParam(':userID', $userID, PDO::PARAM_INT);
    $stmt->execute();
    $class = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$class) {
        $_SESSION['error_message'] = "Class not found or you are not enrolled in this class.";
        header("Location: studentDash.php");
        exit;
    }

    // Fetch counts for additional information
    // Module count
    $moduleStmt = $dbConnection->prepare("
        SELECT COUNT(*) as moduleCount
        FROM modules
        WHERE teacherSectionID = :teacherSectionID AND archived = 0
    ");
    $moduleStmt->bindParam(':teacherSectionID', $teacherSectionID, PDO::PARAM_INT);
    $moduleStmt->execute();
    $moduleCount = $moduleStmt->fetch(PDO::FETCH_ASSOC)['moduleCount'];

    // Quiz count (only count quizzes where releaseDate <= NOW())
    $dbConnection->exec("SET time_zone = '+08:00';");

    $quizStmt = $dbConnection->prepare("
        SELECT COUNT(*) as quizCount
        FROM quizzes
        WHERE teacherSectionID = :teacherSectionID 
          AND archived = 0 
          AND (releaseDate IS NULL OR releaseDate <= NOW())
    ");
    $quizStmt->bindParam(':teacherSectionID', $teacherSectionID, PDO::PARAM_INT);
    $quizStmt->execute();
    $quizCount = $quizStmt->fetch(PDO::FETCH_ASSOC)['quizCount'];

    // Assignment count
    $assignmentStmt = $dbConnection->prepare("
        SELECT COUNT(*) as assignmentCount
        FROM assignments
        WHERE teacherSectionID = :teacherSectionID 
          AND archived = 0
          AND (dueDate IS NULL OR dueDate >= NOW())
    ");
    $assignmentStmt->bindParam(':teacherSectionID', $teacherSectionID, PDO::PARAM_INT);
    $assignmentStmt->execute();
    $assignmentCount = $assignmentStmt->fetch(PDO::FETCH_ASSOC)['assignmentCount'];

    // Classmates count
    $classmateStmt = $dbConnection->prepare("
        SELECT COUNT(*) as classmateCount
        FROM student_section ss
        JOIN users u ON ss.userID = u.userID
        WHERE ss.sectionID = :sectionID AND ss.status = 'Enrolled' AND ss.archived = 0 AND u.archived = 0
    ");
    $classmateStmt->bindParam(':sectionID', $class['sectionID'], PDO::PARAM_INT);
    $classmateStmt->execute();
    $classmateCount = $classmateStmt->fetch(PDO::FETCH_ASSOC)['classmateCount'];

    // Fetch classmates list for the Classmates tab with email
    $classmatesStmt = $dbConnection->prepare("
        SELECT u.userID, u.firstName, u.lastName, u.email, u.image
        FROM student_section ss
        JOIN users u ON ss.userID = u.userID
        WHERE ss.sectionID = :sectionID AND ss.status = 'Enrolled' AND ss.archived = 0 AND u.archived = 0
        ORDER BY u.lastName, u.firstName
    ");
    $classmatesStmt->bindParam(':sectionID', $class['sectionID'], PDO::PARAM_INT);
    $classmatesStmt->execute();
    $classmates = $classmatesStmt->fetchAll(PDO::FETCH_ASSOC);

    // Format time
    $startTime = date("g:ia", strtotime($class['startTime']));
    $endTime = date("g:ia", strtotime($class['endTime']));
    $formattedTime = "$startTime - $endTime";

    // Construct the token URL
    $tokenUrl = $class['token'] ? 'http://localhost/capstone-lms/publicModules.php?token=' . urlencode($class['token']) : '#';

} catch (PDOException $e) {
    error_log("Database Error: " . $e->getMessage() . " in " . $e->getFile() . " on line " . $e->getLine());
    $_SESSION['error_message'] = "Database error: " . htmlspecialchars($e->getMessage());
    header("Location: studentDash.php");
    exit;
}

require_once './get_notification_count.php';

// Ensure userID is set and validated
$userID = isset($_SESSION['userID']) ? filter_var($_SESSION['userID'], FILTER_VALIDATE_INT) : null;
$unreadCount = $userID ? getUnreadAnnouncementCount($dbConnection, $userID) : 0;

// Ensure announcements_viewed session flag is set
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
    <link rel="stylesheet" href="./utils/semi-dash.css">
    <link rel="stylesheet" href="./utils/logout.css">
    <link rel="stylesheet" href="./utils/animation_slide.css">
    <link rel="stylesheet" href="./utils/logo.css">
    <link rel="stylesheet" href="./css/scrollbar.css">
    <script src="./logout.js"></script>
    <title>Learnify - Class Details</title>
    <style>
        :root {
            --primary: #1a73e8;
            --primary-light: #e8f0fe;
            --secondary: #f1f3f4;
            --text: #202124;
            --text-light: #5f6368;
            --border: #dadce0;
            --background: #ffffff;
            --success: #34a853;
            --error: #d93025;
            --card-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
            --hover: #d5d5d5;
            --secondary-hover: #e6f0ff;
            --secondary-grey: #f8faff;
           
        }
        body.dark {
            --hover: #1a1e2e;
            --secondary-hover: #3b5998;
            --secondary-grey: #1a2332;

        }
        .success-notification, .error-notification {
            padding: 12px;
            margin: 10px 0;
            border-radius: 4px;
            color: white;
            font-size: clamp(1.1rem, 3vw, 1.3rem);
            z-index: 1000;
            transition: opacity 0.3s ease;
        }
        .success-notification {
            position: fixed;
            bottom: 20px;
            right: 20px;
            background: linear-gradient(135deg, #28a745, #218838);
        }
        .error-notification {
            background: var(--error);
            color: var(--background);
            text-align: center;
        }
        .card-container {
            display: grid;
            gap: 20px;
            margin-top: 20px;
        }
        .card {
            background: var(--light);
            border-radius: 8px;
            box-shadow: var(--card-shadow);
            overflow: hidden;
        }
        .card-header {
            padding: 16px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            background: #1557b0;
        }
        .card-header h3 {
            margin: 0;
            font-size: clamp(1.5rem, 3vw, 2rem);
            font-weight: 600;
            color: white;
        }
        .section-id {
            font-size: clamp(0.9rem, 2vw, 1rem);
            background: rgba(0, 0, 0, 0.2);
            padding: 0.25rem 0.5rem;
            border-radius: 0.25rem;
            color: white;
            display: none;
        }
        .card-body {
            padding: 1.4rem;
        }
        .details-table {
            width: 100%;
            border-collapse: collapse;
            font-size: clamp(1rem, 3vw, 1.3rem);
        }
        .details-table th, .details-table td {
            padding: 12px;
            text-align: left;
        }
        .details-table th {
            background: var(--primary);
            color: var(--background);
            font-weight: 600;
        }
        .details-table td {
            color: var(--dark);
            font-weight: 500;
            background-color: var(--secondary);
            border-bottom: 1px solid var(--border);
        }
        .card-actions {
            display: flex;
            flex-wrap: wrap;
            gap: 15px;
            padding: 16px;
            border-radius: 15px;
        }
        .action-group {
            max-width: 260px;
            width: 100%;
        }
        .action-box {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 12px;
            border-radius: 10px !important;
            background-color: var(--grey);
            padding: 12px 16px;
            box-shadow: 0px 3px 2px rgba(0, 0, 0, 0.5);
            border-top: 4px solid var(--primary);
            text-align: left;
            max-height: 50vh;
            height: 16vh;
            text-decoration: none;
            color: inherit;
            transition: all 0.3s ease;
        }
        .action-box:hover {
            transform: translateY(-5px);
            text-decoration: none;
            color: inherit;
        }
        .action-box i {
            font-size: clamp(3rem, 3vw, 5rem);
            flex-shrink: 0;
            color: var(--primary);
            padding: 12px 16px;
            border-radius: 10px;
        }
        .action-box .bx-folder-open {
            background-color: #CFE8FF;
            color: #3C91E6;
        }
        .action-box .bx-group {
            background-color: #A9D08D;
            color: #28A745;
        }
        .action-box .bx-book {
            color: #6F42C1;
            background-color: #D8A8E4;
        }
        .action-box .bx-task {
            background-color: #F9D1E3;
            color: #FF4F89;
        }
        .action-box .action-text {
            display: flex;
            flex-direction: column;
            align-items: flex-start;
            flex: 1;
        }
        .action-box .action-text span {
            font-size: clamp(1.2rem, 3vw, 1.6rem);
            font-weight: 600;
            color: var(--dark);
        }
        .action-box .action-text small {
            font-size: clamp(2rem, 3vw, 2.5rem);
            font-weight: 600;
            color: var(--dark);
        }
        .tab-container {
            padding: 16px;
        }
        .tab-buttons {
            display: flex;
            margin-bottom: 16px;
            overflow-x: auto;
            white-space: nowrap;
            background: var(--grey);
            padding: 8px 0;
        }
        .tab-button {
            padding: 12px 24px;
            cursor: pointer;
            background: transparent;
            border: none;
            border-bottom: 2px solid transparent;
            margin-right: 8px;
            font-size: clamp(1.1rem, 3vw, 1.3rem);
            font-weight: 500;
            color: var(--dark);
            transition: all 0.3s ease;
        }
        .tab-button.active {
            color: var(--primary);
            border-bottom: 2px solid var(--primary);
            font-weight: 600;
        }
        .tab-button:hover {
            color: var(--primary);
            border-bottom: 2px solid #1a73e8;
        }
        .tab-content {
            display: none;
            padding: 16px;
            background: var(--light);
            border-radius: 4px;
            box-shadow: var(--card-shadow);
            overflow-x: auto;

        }
        .tab-content.active {
            display: block;
        }
        .classmate-table {
            width: 100%;
            border-collapse: collapse;
            font-size: clamp(1rem, 3vw, 1.2rem);
        }
        .classmate-table th, .classmate-table td {
            border: none;
            padding: 12px;
            text-align: left;
        }
        .classmate-table tr:hover,
        .classmate-table tr:nth-child(even):hover {
            background: linear-gradient(135deg, var(--secondary-hover), var(--hover));
        }
        .classmate-table th {
            background: var(--primary);
            color: var(--background);
            font-weight: 600;
        }
        .classmate-table td {
            color: var(--dark);
            vertical-align: middle;
        }

        .classmate-table tr:nth-child(even) {
            background: linear-gradient(135deg, var(--secondary-grey), var(--grey));
            transition: background 0.3s ease; 
        }

        /* Profile and Name styling */
        .profile-name-container {
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .profile-img {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            object-fit: cover;
            flex-shrink: 0;
        }

        .student-name {
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            color: var(--dark);
            font-size: clamp(0.95rem, 2.5vw, 1.1rem);
        }

        /* Email styling - single link with icon and text */
        .email-icon {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            text-decoration: none;
            color: var(--primary);
            font-size: clamp(1rem, 2vw, 1.2rem);
            font-weight: 500;
            transition: all 0.2s ease;
            padding: 4px 8px;
            border-radius: 6px;
            background: rgba(26, 115, 232, 0.05);
            position: relative;
        }

        .email-icon:hover {
            color: #1557b0;
            background: rgba(26, 115, 232, 0.1);
            text-decoration: none;
        }

        .email-icon i {
            color: var(--primary);
            font-size: clamp(1rem, 2vw, 1.2rem);
            transition: color 0.2s ease;
        }

        .email-icon:hover i {
            color: #1557b0;
        }

        .email-text {
            color: var(--text-light);
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
            max-width: 180px;
        }

        .email-icon:hover .email-text {
            color: var(--primary);
            text-decoration: underline;
        }

        /* Email tooltip */
        .email-tooltip {
            position: absolute;
            background: var(--text);
            color: white;
            padding: 6px 10px;
            border-radius: 4px;
            font-size: 0.8rem;
            white-space: nowrap;
            z-index: 1000;
            top: -35px;
            left: 50%;
            transform: translateX(-50%);
            opacity: 0;
            visibility: hidden;
            transition: all 0.2s ease;
            pointer-events: none;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.15);
        }

        .email-tooltip::after {
            content: '';
            position: absolute;
            bottom: -5px;
            left: 50%;
            transform: translateX(-50%);
            border: 5px solid transparent;
            border-top-color: var(--text);
        }

        .email-icon:hover .email-tooltip {
            opacity: 1;
            visibility: visible;
        }

        .token-box {
            margin-top: 1.5rem;
            padding: 1rem;
            background: var(--background);
            border-radius: 0.375rem;
            box-shadow: var(--card-shadow);
            text-align: center;
            max-width: 500px;
            margin-left: auto;
            margin-right: auto;
        }
        .token-box p {
            margin: 0 0 1rem;
            font-size: clamp(0.9rem, 2vw, 1.1rem);
            color: var(--text-light);
        }
        .token-buttons {
            display: flex;
            justify-content: center;
            gap: 1rem;
        }
        .access-btn {
            padding: 8px 16px;
            border: none;
            border-radius: 4px;
            font-size: clamp(1rem, 3vw, 1.2rem);
            cursor: pointer;
            transition: background 0.3s;
            background: var(--primary);
            color: var(--background);
        }
        .access-btn:hover {
            background: #1557b0;
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

        /* Responsive adjustments */
        @media screen and (max-width: 768px) {
            #content {
                margin-left: 0;
            }
            .card-actions {
                flex-direction: column;
            }
            .action-box {
                min-width: 100%;
            }
            .tab-buttons {
                flex-direction: row;
                flex-wrap: nowrap;
                overflow-x: auto;
            }
            .tab-button {
                margin-right: 4px;
                margin-bottom: 0;
            }
            .success-notification, .error-notification {
                bottom: 10px;
                right: 10px;
                font-size: 0.8rem;
                padding: 0.5rem;
            }
            .profile-img {
                width: 35px;
                height: 35px;
            }
            .email-text {
                max-width: 150px;
            }
        }
        @media screen and (max-width: 480px) {
            .action-box {
                width: 100%;
                flex-direction: column; 
                height: auto;
            }
            .success-notification, .error-notification {
                bottom: 10px;
                right: 10px;
                font-size: 0.8rem;
                padding: 0.5rem;
            }
            .profile-img {
                width: 30px;
                height: 30px;
            }
            .action-box .action-text small {
                text-align: center;
            }
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

    <?php require_once './view/modal.php' ?>

    <section id="content">
        <nav>
            <i class='bx bx-menu' aria-label="Toggle Sidebar"></i>
            <form action="search.php" method="get">
                <div class="form-input">
                    <input type="search" name="query" placeholder="Search Teachers and Students" required aria-label="Search">
                    <button type="submit" class="search-btn" aria-label="Search"><i class='bx bx-search'></i></button>
                </div>
            </form>
            <input type="checkbox" id="switch-mode" hidden aria-label="Toggle Dark Mode">
            <label for="switch-mode" class="switch-mode"></label>
            <a href="./main_acc.php" class="profile" aria-label="Profile">
                <img src="<?php echo $_SESSION['image']; ?>" alt="Profile Image">
                <div>
                    <p><?php echo htmlspecialchars($_SESSION['firstName']); ?></p>
                    <small><?php echo htmlspecialchars($_SESSION['userType']); ?></small>
                </div>
            </a>
        </nav>

        <main>
            <div class="head-title">
                <div class="left">
                    <h1>Class Details</h1>
                    <ul class="breadcrumb">
                        <li><a href="./studentDash.php">Home</a></li>
                        <li><i class='bx bx-chevron-right'></i></li>
                        <li><a class="active" href="#">Class Details</a></li>
                    </ul>
                </div>
                <a href="./studentDash.php" class="back-button">
                    <i class='bx bx-arrow-back'></i> Back to Dashboard
                </a>
            </div>

            <?php if (isset($_SESSION['success_message'])): ?>
                <div class="success-notification" id="success-notification">
                    <?php
                        echo htmlspecialchars($_SESSION['success_message']);
                        unset($_SESSION['success_message']);
                    ?>
                </div>
            <?php endif; ?>
            <?php if (isset($_SESSION['error_message'])): ?>
                <div class="error-notification">
                    <?php
                        echo htmlspecialchars($_SESSION['error_message']);
                        unset($_SESSION['error_message']);
                    ?>
                </div>
            <?php endif; ?>

            <div class="card-container">
                <div class="card">
                    <div class="card-header">
                        <h3><?php echo htmlspecialchars($class['subjectName']); ?> - <?php echo htmlspecialchars($class['sectionName']); ?></h3>
                        <span class="section-id">ID: <?php echo htmlspecialchars($class['teacherSectionID']); ?></span>
                    </div>
                    <div class="tab-container">
                        <div class="tab-buttons">
                            <button class="tab-button active" data-tab="overview" aria-label="Overview Tab"><i class='bx bx-list-ul'></i> Overview</button>
                            <button class="tab-button" data-tab="classmates" aria-label="Classmates Tab"><i class='bx bxs-group'></i> People (<?php echo $classmateCount; ?>)</button>
                        </div>
                        <div id="overview" class="tab-content active">
                            <div class="card-actions">
                                <div class="action-group">
                                    <a href="studentModules.php?teacherSectionID=<?php echo htmlspecialchars($class['teacherSectionID']); ?>" class="action-box" aria-label="View Modules">
                                        <i class='bx bx-folder-open'></i>
                                        <div class="action-text">
                                            <span>Modules</span>
                                            <small><?php echo htmlspecialchars($moduleCount); ?></small>
                                        </div>
                                    </a>
                                </div>
                                <div class="action-group">
                                    <a href="studentQuiz.php?teacherSectionID=<?php echo htmlspecialchars($class['teacherSectionID']); ?>" class="action-box" aria-label="View Quizzes">
                                        <i class='bx bx-book'></i>
                                        <div class="action-text">
                                            <span>Quizzes</span>
                                            <small><?php echo htmlspecialchars($quizCount); ?></small>
                                        </div>
                                    </a>
                                </div>
                                <div class="action-group">
                                    <a href="studentAssignment.php?teacherSectionID=<?php echo htmlspecialchars($class['teacherSectionID']); ?>" class="action-box" aria-label="View Assignments">
                                        <i class='bx bx-task'></i>
                                        <div class="action-text">
                                            <span>Assignments</span>
                                            <small><?php echo htmlspecialchars($assignmentCount); ?></small>
                                        </div>
                                    </a>
                                </div>
                            </div>
                        </div>
                        <div id="classmates" class="tab-content">
                            <?php if (!empty($classmates)): ?>
                                <table class="classmate-table">
                                    <thead>
                                        <tr>
                                            <th>#</th>
                                            <th>Profile & Name</th>
                                            <th>Email</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($classmates as $index => $classmate): ?>
                                            <?php 
                                                // Format name as LastName, FirstName in uppercase
                                                $formattedName = strtoupper($classmate['lastName'] . ', ' . $classmate['firstName']);
                                                $profileImage = $classmate['image'] ? htmlspecialchars($classmate['image']) : './img/noprofile.png';
                                                $email = htmlspecialchars($classmate['email']);
                                            ?>
                                            <tr>
                                                <td><?php echo $index + 1; ?></td>
                                                <td>
                                                    <div class="profile-name-container">
                                                        <img src="<?php echo $profileImage; ?>" alt="Profile Image" class="profile-img">
                                                        <span class="student-name"><?php echo $formattedName; ?></span>
                                                    </div>
                                                </td>
                                                <td>
                                                    <a href="mailto:<?php echo $email; ?>" class="email-icon" data-email="<?php echo $email; ?>" aria-label="Send email to <?php echo $email; ?>" title="Click to email <?php echo $formattedName; ?>">
                                                        <i class='bx bx-envelope'></i>
                                                        <span class="email-text"><?php echo $email; ?></span>
                                                    </a>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            <?php else: ?>
                                <p>No classmates enrolled in this class.</p>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </main>
    </section>

    <script src="./utils/script.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            function switchTab(tabId, container) {
                const tabButtons = container.querySelectorAll('.tab-button');
                const tabContents = container.querySelectorAll('.tab-content');
                tabButtons.forEach(btn => btn.classList.remove('active'));
                tabContents.forEach(content => content.classList.remove('active'));
                container.querySelector(`[data-tab="${tabId}"]`).classList.add('active');
                container.querySelector(`#${tabId}`).classList.add('active');
            }

            document.querySelectorAll('.tab-container > .tab-buttons .tab-button').forEach(button => {
                button.addEventListener('click', function() {
                    const tabId = this.getAttribute('data-tab');
                    const card = this.closest('.card');
                    switchTab(tabId, card);
                });
            });

            const successNotification = document.getElementById('success-notification');
            if (successNotification) {
                setTimeout(() => {
                    successNotification.style.opacity = '0';
                    setTimeout(() => {
                        successNotification.remove();
                    }, 300);
                }, 10000);
            }

            // Email functionality - the entire link is clickable
            document.querySelectorAll('.email-icon').forEach(link => {
                // Pre-fill email with subject and body
                const email = link.getAttribute('href').replace('mailto:', '');
                const studentName = link.closest('tr').querySelector('.student-name').textContent.trim();
                const subject = encodeURIComponent(`Hello ${studentName} from Learnify`);
                const body = encodeURIComponent(`Hi ${studentName.split(',')[1].trim()},\n\nI hope this email finds you well. I'm reaching out regarding our class.\n\nBest regards,\n${<?php echo json_encode($_SESSION['firstName']); ?>}`);
                
                // Update the href with pre-filled content
                link.href = `mailto:${email}?subject=${subject}&body=${body}`;

                // Double-click to copy email (on the icon)
                link.addEventListener('dblclick', function(e) {
                    e.stopPropagation();
                    const emailText = this.getAttribute('data-email');
                    
                    if (navigator.clipboard && navigator.clipboard.writeText) {
                        navigator.clipboard.writeText(emailText).then(() => {
                            // Visual feedback on the icon
                            const icon = this.querySelector('i');
                            const originalIcon = icon.innerHTML;
                            icon.innerHTML = '<i class="bx bx-check"></i>';
                            this.style.color = 'var(--success)';
                            
                            setTimeout(() => {
                                icon.innerHTML = originalIcon;
                                this.style.color = '';
                            }, 1000);
                            
                            showCopyNotification(`Copied: ${emailText}`);
                        }).catch(err => {
                            console.error('Failed to copy email: ', err);
                            fallbackCopyTextToClipboard(emailText);
                        });
                    } else {
                        fallbackCopyTextToClipboard(emailText);
                    }
                });

                // Keyboard support
                link.addEventListener('keydown', function(e) {
                    if (e.key === 'Enter' || e.key === ' ') {
                        e.preventDefault();
                        window.location.href = this.href;
                    }
                });
            });

            // Fallback copy function
            function fallbackCopyTextToClipboard(text) {
                const textArea = document.createElement("textarea");
                textArea.value = text;
                textArea.style.top = "0";
                textArea.style.left = "0";
                textArea.style.position = "fixed";
                
                document.body.appendChild(textArea);
                textArea.focus();
                textArea.select();
                
                try {
                    const successful = document.execCommand('copy');
                    if (successful) {
                        showCopyNotification(`Copied: ${text}`);
                    }
                } catch (err) {
                    console.error('Fallback: Oops, unable to copy', err);
                }
                
                document.body.removeChild(textArea);
            }

            // Copy notification
            function showCopyNotification(message) {
                const notification = document.createElement('div');
                notification.className = 'success-notification';
                notification.textContent = message;
                notification.style.cssText = `
                    position: fixed; top: 20px; right: 20px; 
                    z-index: 10000; font-size: 0.9rem; 
                    padding: 8px 16px; border-radius: 4px;
                    background: linear-gradient(135deg, var(--success), #218838);
                `;
                document.body.appendChild(notification);
                
                setTimeout(() => {
                    notification.style.opacity = '0';
                    setTimeout(() => {
                        if (notification.parentNode) {
                            notification.remove();
                        }
                    }, 300);
                }, 2000);
            }

            // Responsive tooltip handling
            function handleResponsiveTooltips() {
                const tooltips = document.querySelectorAll('.email-tooltip');
                if (window.innerWidth <= 480) {
                    tooltips.forEach(tooltip => {
                        tooltip.style.display = 'none';
                    });
                } else {
                    tooltips.forEach(tooltip => {
                        tooltip.style.display = 'block';
                    });
                }
            }

            // Initial call and resize listener
            handleResponsiveTooltips();
            window.addEventListener('resize', handleResponsiveTooltips);
        });
    </script>
</body>
</html>
<?php
$dbConnection = null;
?>