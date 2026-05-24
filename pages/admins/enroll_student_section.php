<?php
require_once './sessions/session_admin.php'; 
require_once '../../config/db_connection.php'; 
require_once './auto_logout.php';
require_once './check_status.php';

// Fetch available academic sessions (hardcoded or from DB if dynamic)
$academicSessions = [
    '2025 - 2026',
    '2026 - 2027',
    '2027 - 2028',
    '2028 - 2029',
    '2029 - 2030'
];

// Handle search (server-side for name filtering only)
$students = [];
$search_term = '';
$search_note = '';

if (isset($_POST['search_students'])) {
    $search_term = trim($_POST['search_term']);
    if (!empty($search_term)) {
        try {
            // Search by name only
            $sql = "SELECT u.*, 
                    (SELECT COUNT(*) FROM student_section ss WHERE ss.userID = u.userID 
                     AND ss.status IN ('Enrolled','Pending','Dropped','Completed') AND ss.archived = 0) as enrolled_count
                    FROM users u 
                    WHERE u.userType = 'Student' AND u.archived = 0 
                    AND (u.firstName LIKE ? OR u.lastName LIKE ?)
                    HAVING enrolled_count = 0";
            $stmt = $dbConnection->prepare($sql);
            $search_param = "%" . $search_term . "%";
            $stmt->bindParam(1, $search_param, PDO::PARAM_STR);
            $stmt->bindParam(2, $search_param, PDO::PARAM_STR);
            $stmt->execute();
            $students = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            $search_note = "Showing " . count($students) . " filtered students for: " . htmlspecialchars($search_term);
        } catch (PDOException $e) {
            $_SESSION['error_message'] = "Search error: " . $e->getMessage();
            $search_note = "Search failed.";
        }
    } else {
        // Clear search - show all unenrolled
        $search_note = '';
    }
}

// If no search, fetch default unenrolled students
if (empty($students)) {
    $sql = "SELECT u.*, 
            (SELECT COUNT(*) FROM student_section ss WHERE ss.userID = u.userID 
             AND ss.status IN ('Enrolled','Pending','Dropped','Completed') AND ss.archived = 0) as enrolled_count
            FROM users u 
            WHERE u.userType = 'Student' AND u.archived = 0 
            HAVING enrolled_count = 0";
    $stmt = $dbConnection->prepare($sql);
    $stmt->execute();
    $students = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $search_note = "Showing " . count($students) . " available unenrolled students";
}

if (isset($_POST['enrollStudent'])) {
    $userIDs = $_POST['userIDs'] ?? []; 
    $sectionID = $_POST['sectionID'] ?? '';
    $academicSession = $_POST['academicSession'] ?? '';

    if (empty($userIDs) || empty($sectionID) || empty($academicSession)) {
        $_SESSION['error_message'] = "Please select at least one student, a section, and an academic session.";
        header("Location: enroll_student_section.php");
        exit;
    }

    try {
        // Check current gender counts for the section in the specific academic session
        $countSql = "SELECT u.sex, COUNT(*) as count 
                    FROM student_section ss 
                    JOIN users u ON ss.userID = u.userID 
                    WHERE ss.sectionID = ? AND ss.academicSession = ? AND ss.status = 'Enrolled' AND ss.archived = 0 
                    GROUP BY u.sex";
        $countStmt = $dbConnection->prepare($countSql);
        $countStmt->bindParam(1, $sectionID, PDO::PARAM_INT);
        $countStmt->bindParam(2, $academicSession, PDO::PARAM_STR);
        $countStmt->execute();
        $genderCounts = $countStmt->fetchAll(PDO::FETCH_ASSOC);

        $maleCount = 0;
        $femaleCount = 0;
        foreach ($genderCounts as $row) {
            if ($row['sex'] === 'Male') $maleCount = $row['count'];
            if ($row['sex'] === 'Female') $femaleCount = $row['count'];
        }

        // Count new males and females being enrolled
        $newMales = 0;
        $newFemales = 0;
        $checkUserSql = "SELECT sex FROM users WHERE userID = ? AND userType = 'Student' AND archived = 0";
        $checkUserStmt = $dbConnection->prepare($checkUserSql);

        foreach ($userIDs as $userID) {
            $checkUserStmt->bindParam(1, $userID, PDO::PARAM_INT);
            $checkUserStmt->execute();
            $user = $checkUserStmt->fetch(PDO::FETCH_ASSOC);
            
            if ($user) {
                if ($user['sex'] === 'Male') $newMales++;
                if ($user['sex'] === 'Female') $newFemales++;
            }
        }

        // Validate gender limits
        if ($maleCount + $newMales > 25 || $femaleCount + $newFemales > 25) {
            $_SESSION['error_message'] = "Cannot enroll: Would exceed limit of 25 males or 25 females per section.";
            header("Location: enroll_student_section.php");
            exit;
        }

        $success = true;
        foreach ($userIDs as $userID) {
            // Verify student
            $checkStudent = "SELECT * FROM users WHERE userID = ? AND userType = 'Student' AND archived = 0";
            $stmt = $dbConnection->prepare($checkStudent);
            $stmt->bindParam(1, $userID, PDO::PARAM_INT);
            $stmt->execute();

            if ($stmt->rowCount() == 0) {
                $_SESSION['error_message'] = "One or more selected users are not students or are archived.";
                $success = false;
                continue;
            }

            // Check for existing enrollment
            $checkSql = "SELECT * FROM student_section WHERE userID = ? AND sectionID = ? AND academicSession = ? AND status = 'Enrolled' AND archived = 0";
            $stmt = $dbConnection->prepare($checkSql);
            $stmt->bindParam(1, $userID, PDO::PARAM_INT);
            $stmt->bindParam(2, $sectionID, PDO::PARAM_INT);
            $stmt->bindParam(3, $academicSession, PDO::PARAM_STR);
            $stmt->execute();

            if ($stmt->rowCount() > 0) {
                $_SESSION['error_message'] = "One or more students are already enrolled in this section for the selected academic session.";
                $success = false;
                continue;
            }

            // Enroll student
            $sql = "INSERT INTO student_section (userID, sectionID, academicSession, status, enrollmentDate) 
                    VALUES (?, ?, ?, 'Enrolled', NOW())";
            $stmt = $dbConnection->prepare($sql);
            $stmt->bindParam(1, $userID, PDO::PARAM_INT);
            $stmt->bindParam(2, $sectionID, PDO::PARAM_INT);
            $stmt->bindParam(3, $academicSession, PDO::PARAM_STR);

            if (!$stmt->execute()) {
                $success = false;
            }
        }

        if ($success) {
            $_SESSION['success_message'] = "Students enrolled successfully.";
        } else {
            if (!isset($_SESSION['error_message'])) {
                $_SESSION['error_message'] = "Error enrolling some students. Please try again.";
            }
        }
    } catch (PDOException $e) {
        $_SESSION['error_message'] = "Database error: " . $e->getMessage();
    }

    header("Location: enroll_student_section.php");
    exit;
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
    <link rel="stylesheet" href="./utils/track_strand.css">
    <link rel="stylesheet" href="./utils/logout.css">
    <link rel="stylesheet" href="./utils/animation_slide.css">
    <link rel="stylesheet" href="./utils/logo.css">
    <link rel="stylesheet" href="./css/scrollbar.css">
    <script src="./logout.js"></script>
    <title>Learnify</title>
    <style>
        :root {
            --primary: #2B6CB0;
            --primary-dark: #1E4A7A;
            --transition: all 0.3s ease;
            --white: #FFFFFF;
            --shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06);
            --grey: #F7F7F7;
            --dark: #333333;
            --text-secondary: #555555;
            --light: #FFFFFF;
            --error: #c82333;
            --success: #28a745;
        }

        #content main .head-title .left h1 {
            font-size: clamp(1.5rem, 5vw, 2rem);
            font-weight: 600;
            margin-bottom: 8px;
            color: var(--dark);
        }

        .success-notification, .error-notification {
            padding: 8px;
            margin: 15px auto;
            max-width: 90%;
            font-size: clamp(1rem, 3vw, 1.3rem) !important;
            border-radius: 5px;
            color: var(--white);
            text-align: center;
        }
        .success-notification {
            background: linear-gradient(135deg, var(--success), #218838);
        }
        .error-notification {
            background: linear-gradient(135deg, var(--error), #b21f2d);
        }

        .btn-download {
            background: linear-gradient(135deg, #007bff, #0056b3);
            color: var(--white);
            padding: 6px 12px;
            border-radius: 4px;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 5px;
            font-size: clamp(1rem, 3vw, 1.2rem);
            transition: var(--transition);
        }
        .btn-download:hover {
            background: linear-gradient(135deg, #0056b3, #003d80);
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

        .session-container {
            display: flex;
            flex-wrap: wrap;
            gap: 20px;
            justify-content: center;
            margin: 20px auto;
            max-width: 90%;
        }

        .session-box {
            background: var(--light);
            border-radius: 8px;
            box-shadow: var(--shadow);
            padding: 15px;
            width: 300px;
            text-align: center;
            cursor: pointer;
            transition: var(--transition);
        }

        .session-box:hover {
            transform: translateY(-5px);
            box-shadow: 0 6px 12px rgba(0, 0, 0, 0.15);
            border:1px solid #003d80;
        }

        .session-title {
            font-size: clamp(1.2rem, 4vw, 1.5rem);
            color: var(--dark);
            margin-bottom: 10px;
            font-weight: 600;
        }

        /* Full-Screen Modal Styles */
        .fullscreen-enroll {
            display: none;
            position: fixed;
            left: 0;
            top: 0;
            width: 100vw;
            height: 100vh;
            background-color: rgba(0, 0, 0, 0.7);
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 0;
            margin: 0;
            animation: fadeIn 0.3s ease;
            z-index: 10000000000000000000000000000000;
        }

        .enroll-container {
            background: var(--light);
            width: 100vw;
            height: 100vh;
            overflow-y: auto;
            padding: 20px;
            position: relative;
            display: flex;
            flex-direction: column;
            animation: slideUp 0.3s ease;
        }

        @keyframes fadeIn {
            from { opacity: 0; }
            to { opacity: 1; }
        }

        @keyframes slideUp {
            from { transform: translateY(100px); opacity: 0; }
            to { transform: translateY(0); opacity: 1; }
        }

        .enroll-close {
            position: absolute;
            top: 20px;
            right: 20px;
            color: var(--dark);
            font-size: 32px;
            font-weight: bold;
            cursor: pointer;
            width: 48px;
            height: 48px;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 50%;
            transition: var(--transition);
            background: rgba(0, 0, 0, 0.1);
        }

        .enroll-close:hover {
            color: var(--dark);
            background: rgba(0, 0, 0, 0.2);
        }

        .enroll-form {
            max-width: 1200px;
            margin: 0 auto;
            padding: 20px;
            flex-grow: 1;
        }

        .modal-header {
            font-size: clamp(1.8rem, 5vw, 2.2rem);
            color: var(--dark);
            margin-bottom: 30px;
            text-align: center;
            font-weight: 700;
        }

        .form-container {
            display: flex;
            gap: 30px;
            flex-wrap: wrap;
            align-items: flex-start;
        }

        .left-section, .right-section {
            flex: 1;
            min-width: 350px;
        }

        .form-label {
            display: block;
            margin-bottom: 10px;
            font-weight: bold;
            color: var(--dark);
            font-size: clamp(1.2rem, 3vw, 1.4rem);
        }

        .form-select {
            width: 100%;
            padding: 12px;
            border-radius: 6px;
            font-size: clamp(1.1rem, 3vw, 1.3rem);
            background-color: var(--grey);
            border: 1px solid var(--dark);
            color: var(--dark);
            box-sizing: border-box;
            transition: border-color 0.3s ease;
        }
        .form-select:focus {
            border-color: var(--primary);
            outline: none;
            box-shadow: 0 0 6px rgba(43, 108, 176, 0.3);
        }

        .student-list {
            overflow-y: auto;
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            padding: 12px;
            background: var(--grey);
            border-radius: 6px;
            margin-bottom: 20px;
            border: 1px solid #e2e8f0;
        }

        .student-item {
            display: flex;
            align-items: center;
            margin-bottom: 12px;
            padding: 12px;
            background: var(--light);
            border: 1px solid #e2e8f0;
            border-radius: 6px;
            transition: var(--transition);
        }
        .student-item:hover {
            background-color: #edf2f7;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
        }

        .student-img {
            width: 48px;
            height: 48px;
            border-radius: 50%;
            margin-right: 12px;
            object-fit: cover;
            border: 2px solid var(--grey);
        }

        .student-name {
            font-size: clamp(1.2rem, 3vw, 1.4rem);
            color: var(--dark);
            flex: 1;
        }

        .checkbox-input {
            margin-right: 12px;
            width: 24px;
            height: 24px;
            cursor: pointer;
        }

        .select-all-container {
            margin-bottom: 12px;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .select-all-container label {
            color: var(--dark);
            font-size: clamp(1.2rem, 3vw, 1.4rem);
            cursor: pointer;
        }

        .submit-btn {
            background: linear-gradient(135deg, var(--success), #218838);
            color: var(--white);
            padding: 12px 24px;
            margin-top: 20px;
            border: none;
            border-radius: 6px;
            font-size: clamp(1.2rem, 3vw, 1.4rem);
            cursor: pointer;
            transition: var(--transition);
            width: 100%;
            text-align: center;
        }
        .submit-btn:hover {
            background: linear-gradient(135deg, #218838, #1e7e34);
            transform: translateY(-2px);
            box-shadow: var(--shadow);
        }

        .no-enroll, .span_req {
            color: var(--error);
            text-align: center;
            font-size: clamp(1.2rem, 3vw, 1.4rem);
        }

        .span_req {
            margin-top: -30px;
            margin-bottom: 10px;
        }

        .search-container {
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .search-input {
            flex: 1;
            padding: 12px;
            border-radius: 6px;
            font-size: clamp(1.2rem, 3vw, 1.4rem);
            background-color: var(--grey);
            border: 1px solid var(--dark);
            color: var(--dark);
            box-sizing: border-box;
            transition: border-color 0.3s ease;
        }
        .search-input:focus {
            border-color: var(--primary);
            outline: none;
            box-shadow: 0 0 6px rgba(43, 108, 176, 0.3);
        }

        .search-btn {
            background: linear-gradient(135deg, var(--primary), var(--primary-dark));
            color: var(--white);
            padding: 12px 18px;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            font-size: clamp(1.1rem, 3vw, 1.3rem);
            transition: var(--transition);
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .search-btn:hover {
            background: linear-gradient(135deg, var(--primary-dark), #1A4971);
            transform: translateY(-2px);
        }

        .search-note {
            font-size: clamp(1rem, 3vw, 1.2rem);
            color: var(--text-secondary);
            margin-bottom: 20px;
            padding: 10px;
            background: rgba(43, 108, 176, 0.1);
            border-radius: 6px;
        }

        .section-stats {
            margin-top: 15px;
            font-size: clamp(1.1rem, 3vw, 1.3rem);
            color: var(--text-secondary);
            background: rgba(43, 108, 176, 0.1);
            padding: 10px;
            border-radius: 6px;
        }

        /* Responsive Adjustments */
        @media screen and (max-width: 768px) {
            .form-container {
                flex-direction: column;
                gap: 20px;
            }
            .left-section, .right-section {
                min-width: 100%;
            }
            .student-list {
                max-height: 50vh;
            }
            .search-container {
                flex-direction: column;
                gap: 10px;
            }
            .search-btn {
                width: 100%;
                justify-content: center;
            }
            .enroll-container {
                padding: 15px;
            }
            .modal-header {
                font-size: clamp(1.5rem, 4vw, 1.8rem);
            }
        }

        @media screen and (max-width: 480px) {
            .session-box {
                width: 100%;
            }
            .head-title {
                flex-direction: column;
                align-items: flex-start;
                gap: 10px;
            }
            .head-title .left h1 {
                font-size: clamp(1.2rem, 5vw, 1.5rem);
            }
            .btn-download, .back-button {
                padding: 0.4rem 0.8rem;
                font-size: clamp(0.9rem, 3vw, 1rem);
            }
            .student-img {
                width: 40px;
                height: 40px;
            }
            .student-item {
                padding: 10px;
            }
            .form-select, .submit-btn, .search-input {
                padding: 10px;
            }
            .success-notification, .error-notification {
                padding: 8px;
                margin: 10px auto;
            }
            .enroll-close {
                font-size: 28px;
                width: 40px;
                height: 40px;
            }
            .student-list {
                grid-template-columns: repeat(1, 1fr);
            }
        }

        @media screen and (max-width: 360px) {
            .form-label, .student-name, .select-all-container label {
                font-size: clamp(0.9rem, 3.5vw, 1rem);
            }
            .checkbox-input {
                width: 20px;
                height: 20px;
            }
            .student-img {
                width: 36px;
                height: 36px;
            }
            .student-item {
                padding: 8px;
            }
        }
    </style>
</head>
<body>
    <!-- SIDEBAR -->
    <section id="sidebar">
        <?php require_once './brand.php' ?>
        <ul class="side-menu top">
            <li>
                <a href="./adminDash.php">
                    <i class='bx bxs-dashboard'></i>
                    <span class="text">Dashboard</span>
                </a>
            </li>
            <li>
                <a href="./message_professor.php">
                    <i class='bx bxs-message'></i>
                    <span class="text">Message Teachers</span>
                </a>
            </li>
            <li>
                <a href="./registration.php">
                    <i class='bx bx-user-plus'></i>
                    <span class="text">Registration</span>
                </a>
            </li>
            <li>
                <a href="./modules.php">
                    <i class='bx bxs-bookmark'></i>
                    <span class="text">Modules</span>
                </a>
            </li>
            <li class="active">
                <a href="./enroll_student_section.php">
                    <i class='bx bxs-user-check'></i>
                    <span class="text">Enroll Student Section</span>
                </a>
            </li>
            <li>
                <a href="./enroll_teacher_section.php">
                    <i class='bx bxs-user-check'></i>
                    <span class="text">Assign Teacher Schedule</span>
                </a>
            </li>
            <li>
                <a href="./game.php">
                    <i class='bx bxs-game'></i>
                    <span class="text">Games</span>
                </a>
            </li>
            <li>
                <a href="./admin_calendar.php">
                    <i class='bx bxs-calendar'></i>
                    <span class="text">Academic Calendar</span>
                </a>
            </li>
            <li>
                <a href="./grading.php">
                    <i class='bx bxs-book-content'></i>
                    <span class="text">Student Grades</span>
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
                    <h1>Enroll Student Section</h1>
                    <ul class="breadcrumb">
                        <li><a href="#">Home</a></li>
                        <li><i class='bx bx-chevron-right'></i></li>
                        <li><a class="active" href="enroll_student_section.php">Enroll</a></li>
                    </ul>
                </div>
                <a href="./data_student_section.php" class="back-button">
                    <i class="bx bxs-show"></i> View Records
                </a>
            </div>

            <!-- Success or Error Message -->
            <?php if (isset($_SESSION['success_message'])): ?>
                <div class="success-notification">
                    <?php
                    echo $_SESSION['success_message'];
                    unset($_SESSION['success_message']); 
                    ?>
                </div>
            <?php endif; ?>
            <?php if (isset($_SESSION['error_message'])): ?>
                <div class="error-notification">
                    <?php
                    echo $_SESSION['error_message'];
                    unset($_SESSION['error_message']); 
                    ?>
                </div>
            <?php endif; ?>

            <!-- Academic Sessions Boxes -->
            <div class="session-container">
                <?php foreach ($academicSessions as $session): ?>
                    <div class="session-box" onclick="openModal('<?php echo $session; ?>')">
                        <div class="session-title"><?php echo $session; ?></div>
                    </div>
                <?php endforeach; ?>
            </div>

            <!-- Full-Screen Enrollment Modal -->
            <div id="enrollModal" class="fullscreen-enroll">
                <div class="enroll-container">
                    <span class="enroll-close" onclick="closeModal()"><i class='bx bx-x'></i></span>
                    <h2 class="modal-header" id="modalSessionTitle"></h2>
                    <p class="span_req"><span>(Select at least 1, max 25 males and 25 females per section)*</span></p>
                    <form action="enroll_student_section.php" method="POST" class="enroll-form">
                        <input type="hidden" name="academicSession" id="academicSessionInput">
                        <div class="form-container">
                            <div class="left-section">
                                <div class="search-container">
                                    <input type="text" id="search_term" name="search_term" class="search-input" placeholder="Search by student name..." value="<?php echo htmlspecialchars($search_term); ?>">
                                    <!-- <button type="submit" name="search_students" class="search-btn">
                                        <i class='bx bx-search'></i> Search
                                    </button> -->
                                </div>
                                <?php if (!empty($search_note)): ?>
                                    <div class="search-note"><?php echo htmlspecialchars($search_note); ?></div>
                                <?php endif; ?>
                                <div class="select-all-container">
                                    <input type="checkbox" id="selectAll" class="checkbox-input" onclick="toggleSelectAll()">
                                    <label for="selectAll" class="checkbox-label">Select All</label>
                                </div>
                                <div class="student-list">
                                    <?php if (empty($students)): ?>
                                        <p class='no-enroll'>No students available for enrollment.</p>
                                    <?php else: ?>
                                        <?php foreach ($students as $row): ?>
                                            <?php $imagePath = !empty($row['image']) ? $row['image'] : 'default-image.png'; ?>
                                            <div class='student-item' data-name="<?php echo strtolower($row['firstName'] . ' ' . $row['lastName']); ?>">
                                                <input type='checkbox' name='userIDs[]' value='<?php echo $row['userID']; ?>' id='user_<?php echo $row['userID']; ?>' class='checkbox-input'>
                                                <label for='user_<?php echo $row['userID']; ?>' class='student-label'>
                                                    <img src='<?php echo $imagePath; ?>' alt='Student Image' class='student-img'>
                                                    <span class='student-name'><?php echo htmlspecialchars($row['firstName'] . ' ' . $row['lastName']); ?></span>
                                                </label>
                                            </div>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <div class="right-section">
                                <div class="form-group">
                                    <label for="sectionID" class="form-label section">Select Section <span class="span_req">*</span></label>
                                    <select name="sectionID" id="sectionID" class="form-select" required onchange="updateSectionStats()">
                                        <option value="" disabled selected>- Select Section -</option>
                                        <?php
                                        $sql = "SELECT * FROM sections WHERE archived = 0 ORDER BY sectionName";
                                        $stmt = $dbConnection->prepare($sql);
                                        $stmt->execute();
                                        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                                            echo "<option value='" . $row['sectionID'] . "'>" . htmlspecialchars($row['sectionName'] . ' ' . $row['sectionCode']) . "</option>";
                                        }
                                        ?>
                                    </select>
                                    <div id="sectionStats" class="section-stats"></div>
                                </div>
                                <button type="submit" name="enrollStudent" class="submit-btn">Enroll Students</button>
                            </div>
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
        // Ensure modal is hidden on page load
        document.addEventListener('DOMContentLoaded', function() {
            document.getElementById('enrollModal').style.display = 'none';
        });

        function openModal(session) {
            document.getElementById('enrollModal').style.display = 'flex';
            document.getElementById('modalSessionTitle').textContent = 'Enroll Students for ' + session;
            document.getElementById('academicSessionInput').value = session;
            document.getElementById('sectionID').value = ''; // Reset section dropdown
            document.getElementById('sectionStats').innerHTML = ''; // Clear stats
            document.getElementById('search_term').value = ''; // Clear search
            document.querySelectorAll('.student-item').forEach(item => item.style.display = 'flex'); // Reset student list
            document.getElementById('selectAll').checked = false; // Uncheck select all
        }

        function closeModal() {
            document.getElementById('enrollModal').style.display = 'none';
        }

        // Close modal if clicked outside the container
        window.onclick = function(event) {
            var modal = document.getElementById('enrollModal');
            if (event.target == modal) {
                closeModal();
            }
        }

        function toggleSelectAll() {
            var selectAllCheckbox = document.getElementById('selectAll');
            var checkboxes = document.querySelectorAll('input[name="userIDs[]"]');
            for (var checkbox of checkboxes) {
                if (checkbox.closest('.student-item').style.display !== 'none') {
                    checkbox.checked = selectAllCheckbox.checked;
                }
            }
        }

        // Client-side filtering for name only
        document.getElementById('search_term').addEventListener('input', function() {
            var searchValue = this.value.toLowerCase();
            var studentItems = document.querySelectorAll('.student-item');
            
            studentItems.forEach(function(item) {
                var studentName = item.getAttribute('data-name') || item.querySelector('.student-name').textContent.toLowerCase();
                if (studentName.includes(searchValue)) {
                    item.style.display = 'flex';
                } else {
                    item.style.display = 'none';
                    item.querySelector('input[type="checkbox"]').checked = false;
                }
            });

            // Uncheck "Select All" if any student is hidden
            document.getElementById('selectAll').checked = false;
        });

        // Update section stats when section is selected
        function updateSectionStats() {
            var sectionID = document.getElementById('sectionID').value;
            var academicSession = document.getElementById('academicSessionInput').value;
            if (sectionID && academicSession) {
                // AJAX call to fetch current male/female count for the section and session
                var xhr = new XMLHttpRequest();
                xhr.open('POST', 'get_section_stats.php', true);
                xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
                xhr.onload = function() {
                    if (xhr.status === 200) {
                        document.getElementById('sectionStats').innerHTML = xhr.responseText;
                    } else {
                        document.getElementById('sectionStats').innerHTML = 'Error fetching stats.';
                    }
                };
                xhr.send('sectionID=' + sectionID + '&academicSession=' + encodeURIComponent(academicSession));
            } else {
                document.getElementById('sectionStats').innerHTML = '';
            }
        }
    </script>
</body>
</html>
