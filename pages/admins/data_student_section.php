<?php
require_once './sessions/session_admin.php'; 
require_once '../../config/db_connection.php'; 
require_once './auto_logout.php';
require_once './check_status.php';

$academicSessions = ['2025 - 2026', '2026 - 2027', '2027 - 2028', '2028 - 2029', '2029 - 2030'];

$selectedSession = isset($_GET['academic_session']) ? $_GET['academic_session'] : '';
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
    <link rel="stylesheet" href="./utils/modules/kdakwd.css">
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
            --text-secondary: #4A5568;
            --shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06);
            --red: #dc3545;
            --red-dark: #c82333;
            --completed: #17a2b8;
            --completed-dark: #138496;
        }

        .session-selection {
            margin: 1.5rem;
            padding: 1rem;
            background: var(--light);
            border-radius: 8px;
            box-shadow: var(--shadow);
            display: flex;
            justify-content: center;
            align-items: center;
        }

        .session-selection select {
            padding: 1rem;
            font-size: clamp(1rem, 3vw, 1.3rem);
            background-color: var(--grey);
            color: var(--dark);
            border-radius: 5px;
            border: 1px solid #ccc;
            width: 100%;
            max-width: 300px;
            min-height: 40px;
        }

        .session-selection select:focus {
            border: 1px solid #0056b3;
            outline: none;
        }

        .table-wrapper {
            margin: 1.5rem;
            background: var(--light);
            border-radius: 8px;
            box-shadow: var(--shadow);
            overflow-x: auto;
            padding: 0.5rem;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            font-size: clamp(1rem, 3vw, 1.2rem);
            min-width: 600px;
        }

        th, td {
            padding: 0.75rem 1rem;
            text-align: left;
        }

        th {
            background: #0056b3;
            color: #fff;
            font-weight: bold;
        }

        td {
            color: var(--dark);
        }

        tr:hover {
            background: linear-gradient(135deg, var(--grey), #e2e8f0);
        }

        .btn {
            padding: 0.5rem 0.75rem;
            border: none;
            border-radius: 5px;
            font-weight: 500;
            cursor: pointer;
            text-decoration: none;
            display: inline-block;
            margin-right: 0.3rem;
            font-size: clamp(1rem, 3vw, 1.2rem);
            min-height: 36px;
            touch-action: manipulation;
        }

        .edit-btn {
            background: linear-gradient(135deg, #ffc107, #e0a800);
            color: #342E37;
        }

        .btn-archive {
            background: linear-gradient(135deg, var(--red), var(--red-dark));
            color: #ffffff;
        }

        .edit-btn:hover {
            background: linear-gradient(135deg, #e0a800, #c69500);
        }

        .btn-archive:hover {
            background: linear-gradient(135deg, var(--red-dark), #b21f2d);
        }

        .profile-img {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            object-fit: cover;
            vertical-align: middle;
        }

        .bulk-edit-form {
            margin: 1.5rem;
            padding: 1rem;
            background: var(--light);
            border-radius: 8px;
            box-shadow: var(--shadow);
            display: flex;
            gap: 0.5rem;
            align-items: center;
            justify-content: center;
            flex-wrap: wrap;
        }

        .bulk-edit-form select, .bulk-edit-form button {
            padding: 0.5rem;
            font-size: clamp(1rem, 3vw, 1.2rem);
            background-color: var(--grey);
            color: var(--dark);
            border-radius: 5px;
            border: 1px solid #ccc;
            flex: 1;
            min-height: 40px;
        }

        .bulk-edit-form select:focus {
            border: 1px solid #0056b3;
            outline: none;
        }

        .bulk-edit-form button {
            background: linear-gradient(135deg, #28a745, #218838);
            color: white;
            cursor: pointer;
            border: none;
        }

        .bulk-edit-form button:hover {
            background: linear-gradient(135deg, #218838, #1e7e34);
        }

        .status-legend-container {
            background-color: var(--light);
            padding: 1rem;
            width: fit-content;
            border-radius: 10px;
            margin: 1rem auto;
            box-shadow: var(--shadow);
        }

        .status-legend-container h2 {
            margin-bottom: 1rem;
            border-bottom: 1px solid #ccc;
            padding-bottom: 0.5rem;
            font-size: clamp(1.5rem, 3vw, 2rem);
            color: var(--dark);
            text-align: center;
        }

        .status-legend {
            display: flex;
            gap: 1rem;
            margin: 0.75rem;
            font-size: clamp(1.1rem, 3vw, 1.5rem);
            align-items: center;
            color: var(--dark);
            flex-wrap: wrap;
            justify-content: center;
        }

        .status-legend span {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
        }

        .status-legend .box {
            width: 25px;
            height: 25px;
            border-radius: 3px;
            display: inline-block;
        }

        .box-enrolled {
            background-color: #28a745;
        }

        .box-pending {
            background-color: #ffc107;
        }

        .box-dropped {
            background-color: var(--red);
        }

        .box-completed {
            background-color: var(--completed);
        }

        .status-enrolled {
            background-color: #28a745;
            color: #fff;
            padding: 0.4rem 0.6rem;
            border-radius: 5px;
            font-weight: 600;
        }

        .status-pending {
            background-color: #ffc107;
            color: #342E37;
            padding: 0.4rem 0.6rem;
            border-radius: 5px;
            font-weight: 600;
        }

        .status-dropped {
            background-color: var(--red);
            color: #fff;
            padding: 0.4rem 0.6rem;
            border-radius: 5px;
            font-weight: 600;
        }

        .status-completed {
            background-color: var(--completed);
            color: #fff;
            padding: 0.4rem 0.6rem;
            border-radius: 5px;
            font-weight: 600;
        }

        .sectionCheckbox, #selectAll {
            appearance: none;
            width: 30px;
            height: 30px;
            border: 2px solid #0056b3;
            border-radius: 4px;
            background-color: var(--light);
            cursor: pointer;
            position: relative;
            vertical-align: middle;
            transition: all 0.2s ease;
            min-height: 36px;
        }

        .sectionCheckbox:checked, #selectAll:checked {
            background-color: #0056b3;
            border-color: #0056b3;
        }

        .sectionCheckbox:checked::after, #selectAll:checked::after {
            content: '\2713';
            font-size: clamp(1rem, 3vw, 1.2rem);
            color: #fff;
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
        }

        .sectionCheckbox:hover, #selectAll:hover {
            border-color: #003d80;
            background-color: #e6f0fa;
        }

        .sectionCheckbox:focus, #selectAll:focus {
            outline: none;
            box-shadow: 0 0 0 3px rgba(0, 86, 179, 0.3);
        }

        .no-records {
            text-align: center;
            color: var(--text-secondary);
            font-size: clamp(1rem, 3vw, 1.2rem);
            background-color: var(--grey);
            padding: 1rem;
        }

        .pagination {
            display: flex;
            justify-content: center;
            align-items: center;
            gap: 0.5rem;
            margin: 1rem 0;
            border-top: 1px solid #ccc;
            border-width: 5px;
            padding-top: 0.5rem;
        }

        .pagination a {
            padding: 0.5rem 1rem;
            font-size: clamp(1rem, 2.5vw, 1.2rem);
            border: 1px solid #0056b3;
            border-radius: 5px;
            text-decoration: none;
            background-color: var(--light);
            color: #0056b3;
            transition: background-color 0.3s, color 0.3s;
            min-height: 36px;
        }

        .pagination a.active {
            background-color: #0056b3;
            color: white;
            border-color: #003d80;
        }

        .pagination a:hover:not(.disabled) {
            background-color: #0056b3;
            color: white;
        }

        .pagination a.disabled {
            color: #ccc;
            background-color: transparent;
            cursor: not-allowed;
        }

        .modal-ts {
            display: none;
            position: fixed;
            z-index: 1000000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0, 0, 0, 0.5);
        }

        .modal-content-ts {
            background-color: #F9F9F9;
            margin: 15% auto;
            padding: 1rem;
            border-radius: 8px;
            width: 90%;
            max-width: 400px;
            text-align: center;
        }

        .modal-content-ts h1 {
            font-size: clamp(1rem, 3vw, 1.2rem);
            color: #342E37;
            margin-bottom: 1rem;
        }

        .modal-content-ts h3 {
            font-size: clamp(1rem, 3vw, 1.2rem);
            color: #342E37;
            margin-bottom: 1rem;
        }

        .modal-buttons {
            display: flex;
            justify-content: center;
            gap: 0.5rem;
            flex-wrap: wrap;
        }

        .archive-btn-ts {
            background-color: var(--red);
            color: #ffffff;
            padding: 0.5rem 1rem;
            border: none;
            border-radius: 5px;
            font-size: clamp(0.85rem, 2.5vw, 0.95rem);
            cursor: pointer;
            min-height: 36px;
        }

        .archive-btn-ts:hover {
            background-color: var(--red-dark);
        }

        .close {
            color: #AAAAAA;
            float: right;
            font-size: 1.5rem;
            font-weight: bold;
        }

        .close:hover,
        .close:focus {
            color: #342E37;
            cursor: pointer;
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

        .success-notification, .error-notification {
            padding: 0.75rem;
            margin: 1rem auto;
            max-width: 90%;
            font-size: clamp(1rem, 2.5vw, 1.2rem);
            border-radius: 5px;
            color: white;
            text-align: center;
        }

        .success-notification {
            background: linear-gradient(135deg, #28a745, #218838);
        }

        .error-notification {
            background: linear-gradient(135deg, var(--red), var(--red-dark));
        }

        .section-stats-container {
            display: flex;
            flex-wrap: wrap;
            gap: 1rem;
            margin: 1.5rem;
            justify-content: center;
        }

        .section-box {
            background: var(--light);
            border-radius: 8px;
            box-shadow: var(--shadow);
            padding: 1.5rem;
            width: calc(50% - 0.5rem);
            min-width: 300px;
            transition: transform 0.2s ease;
        }

        .section-box:hover {
            transform: translateY(-4px);
            box-shadow: 0 6px 12px rgba(0, 0, 0, 0.15);
        }

        .section-box h3 {
            font-size: clamp(1.5rem, 3vw, 2rem);
            color: var(--dark);
            margin-bottom: 1rem;
            border-bottom: 2px solid #0056b3;
            padding-bottom: 0.5rem;
            text-align: left;
            font-weight: 600;
        }

        .section-box .total-count {
            font-size: clamp(1.5rem, 3vw, 2rem);
            font-weight: bold;
            color: #28a745;
            text-align: left;
            float: right;
        }

        .gender-container {
            display: flex;
            gap: 1rem;
            flex-wrap: wrap;
        }

        .gender-box {
            flex: 1;
            min-width: 150px;
            background: var(--grey);
            padding: 1rem;
            border-radius: 6px;
        }

        .gender-box h4 {
            font-size: clamp(1.1rem, 2.5vw, 1.5rem);
            color: var(--dark);
            margin-bottom: 0.5rem;
            font-weight: 600;
        }

        .gender-count {
            font-size: clamp(1rem, 2.5vw, 1.2rem);
            font-weight: bold;
            color: #0056b3;
            margin-bottom: 0.5rem;
            float: right;
        }

        .gender-list {
            max-height: 120px;
            overflow-y: auto;
            padding: 0.5rem;
            border-radius: 4px;
            background: var(--grey);
            border: 1px solid #e2e8f0;
        }

        .gender-list-item {
            padding: 0.3rem 0;
            font-size: clamp(1rem, 2.5vw, 1.2rem);
            color: var(--text-secondary);
            border-bottom: 1px solid #e2e8f0;
        }

        .gender-list-item:last-child {
            border-bottom: none;
        }

        .pdf-btn {
            background: linear-gradient(135deg, var(--red), var(--red-dark));
            color: #ffffff;
            padding: 0.6rem 1.2rem;
            border: none;
            border-radius: 5px;
            font-size: clamp(1rem, 2.5vw, 1.2rem);
            cursor: pointer;
            text-align: center;
            margin-top: 10px;
            width: fit-content;
            display: block;
            text-decoration: none;
            font-weight: 500;
            transition: background 0.3s ease;
        }

        .pdf-btn:hover {
            background: linear-gradient(135deg, var(--red-dark), #b21f2d);
        }

        .complete-btn {
            background: linear-gradient(135deg, var(--completed), var(--completed-dark));
            color: #ffffff;
            padding: 0.6rem 1.2rem;
            border: none;
            border-radius: 5px;
            font-size: clamp(1rem, 2.5vw, 1.2rem);
            cursor: pointer;
            text-align: center;
            margin-top: 10px;
            width: fit-content;
            display: block;
            text-decoration: none;
            font-weight: 500;
            transition: background 0.3s ease;
        }

        .complete-btn:hover {
            background: linear-gradient(135deg, var(--completed-dark), #117a8b);
        }

        @media screen and (max-width: 768px) {
            .table-wrapper {
                margin: 1rem;
                padding: 0.5rem;
            }

            table {
                min-width: 500px;
            }

            th, td {
                padding: 0.5rem 0.75rem;
                font-size: clamp(0.8rem, 2.5vw, 0.9rem);
            }

            .profile-img {
                width: 30px;
                height: 30px;
            }

            .btn {
                padding: 0.4rem 0.6rem;
                font-size: clamp(0.8rem, 2.5vw, 0.9rem);
                min-height: 32px;
            }

            .bulk-edit-form {
                flex-direction: column;
                margin: 1rem;
                padding: 0.75rem;
            }

            .bulk-edit-form select, .bulk-edit-form button {
                width: 100%;
                padding: 0.5rem;
                font-size: clamp(0.8rem, 2.5vw, 0.9rem);
                min-height: 36px;
            }

            .status-legend-container {
                padding: 0.75rem;
                width: 100%;
                max-width: 90%;
            }

            .status-legend {
                flex-direction: column;
                gap: 0.5rem;
                font-size: clamp(0.8rem, 2.5vw, 0.9rem);
            }

            .status-legend .box {
                width: 20px;
                height: 20px;
            }

            .sectionCheckbox, #selectAll {
                width: 18px;
                height: 18px;
                min-height: 32px;
            }

            .sectionCheckbox:checked::after, #selectAll:checked::after {
                font-size: 12px;
            }

            .pagination a {
                padding: 0.4rem 0.6rem;
                font-size: clamp(0.8rem, 2.5vw, 0.9rem);
                min-height: 32px;
            }

            .modal-content-ts {
                width: 95%;
                padding: 0.75rem;
            }

            .modal-content-ts h1 {
                font-size: clamp(0.9rem, 3vw, 1rem);
            }

            .modal-content-ts h3 {
                font-size: clamp(0.8rem, 3vw, 0.9rem);
            }

            .section-box {
                width: 100%;
                min-width: 100%;
            }

            .gender-container {
                flex-direction: column;
            }

            .gender-box {
                min-width: 100%;
            }
        }

        @media screen and (max-width: 460px) {
            .table-wrapper {
                margin: 0.5rem;
            }

            th, td {
                padding: 0.4rem 0.5rem;
                font-size: clamp(0.7rem, 2.5vw, 0.8rem);
            }

            .profile-img {
                width: 25px;
                height: 25px;
            }

            .btn {
                padding: 0.3rem 0.5rem;
                font-size: clamp(0.7rem, 2.5vw, 0.8rem);
                min-height: 28px;
            }

            .bulk-edit-form select, .bulk-edit-form button {
                font-size: clamp(0.7rem, 2.5vw, 0.8rem);
                padding: 0.4rem;
            }

            .status-legend-container h2 {
                font-size: clamp(1.2rem, 3vw, 1.4rem);
            }

            .status-legend {
                font-size: clamp(0.7rem, 2.5vw, 0.8rem);
            }

            .sectionCheckbox, #selectAll {
                width: 16px;
                height: 16px;
                min-height: 28px;
            }

            .sectionCheckbox:checked::after, #selectAll:checked::after {
                font-size: 10px;
            }

            .pagination a {
                padding: 0.3rem 0.5rem;
                font-size: clamp(0.7rem, 2.5vw, 0.8rem);
                min-height: 28px;
            }

            th:nth-child(4), td:nth-child(4),
            th:nth-child(5), td:nth-child(5) {
                display: none;
            }

            .section-box {
                width: 100%;
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
                    <h1>Enrolled Students</h1>
                    <ul class="breadcrumb">
                        <li><a href="#">Home</a></li>
                        <li><i class='bx bx-chevron-right'></i></li>
                        <li><a href="#">Enroll</a></li>
                        <li><i class='bx bx-chevron-right'></i></li>
                        <li><a class="active" href="data_student_section.php">Data</a></li>
                    </ul>
                </div>
                <a href="./enroll_student_section.php" class="back-button">
                    <i class='bx bx-arrow-back'></i> Back to Enroll
                </a>
            </div>

            <!-- Session Selection -->
            <div class="session-selection">
                <select name="academic_session" id="academicSession" onchange="filterBySession()">
                    <option value="">Select Academic Session</option>
                    <?php
                    foreach ($academicSessions as $session) {
                        $selected = ($selectedSession === $session) ? 'selected' : '';
                        echo "<option value='" . htmlspecialchars($session) . "' $selected>" . htmlspecialchars($session) . "</option>";
                    }
                    ?>
                </select>
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

            <?php if ($selectedSession): ?>

                <!-- Bulk Edit Form -->
                <div class="bulk-edit-form">
                    <select name="filter_section" id="filterSection" onchange="filterBySection()">
                        <option value="">Select Section to Filter</option>
                        <?php
                        $sectionSql = "SELECT sectionID, sectionName FROM sections WHERE archived = 0 ORDER BY sectionName";
                        $sectionStmt = $dbConnection->prepare($sectionSql);
                        $sectionStmt->execute();
                        $sections = $sectionStmt->fetchAll(PDO::FETCH_ASSOC);
                        foreach ($sections as $section) {
                            $selected = ($filterSection == $section['sectionID']) ? 'selected' : '';
                            echo "<option value='" . htmlspecialchars($section['sectionID']) . "' $selected>" . htmlspecialchars($section['sectionName']) . "</option>";
                        }
                        ?>
                    </select>
                    <select name="new_section" id="newSection">
                        <option value="">Select New Section</option>
                        <?php
                        foreach ($sections as $section) {
                            echo "<option value='" . htmlspecialchars($section['sectionID']) . "'>" . htmlspecialchars($section['sectionName']) . "</option>";
                        }
                        ?>
                    </select>
                    <select name="new_academic_session" id="newAcademicSession">
                        <option value="">Select New Academic Session</option>
                        <?php
                        foreach ($academicSessions as $session) {
                            echo "<option value='" . htmlspecialchars($session) . "'>" . htmlspecialchars($session) . "</option>";
                        }
                        ?>
                    </select>
                    <select name="new_status" id="newStatus">
                        <option value="">Select New Status</option>
                        <option value="Enrolled">Enrolled</option>
                        <option value="Pending">Pending</option>
                        <option value="Dropped">Dropped</option>
                        <option value="Completed">Completed</option>
                    </select>
                    <button type="button" onclick="bulkUpdateSections()">Update Selected</button>
                </div>

                <!-- Enrolled Students Table -->
                <div class="table-wrapper admin-table">
                    <table>
                        <thead>
                            <tr>
                                <th><input type="checkbox" id="selectAll"></th>
                                <th>#</th>
                                <th>Profile</th>
                                <th>Student Name</th>
                                <th>Section Name</th>
                                <th>Enrollment Date</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody style="color: #342E37;">
                            <?php
                            $recordsPerPage = 10;
                            $currentPage = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
                            $startLimit = ($currentPage - 1) * $recordsPerPage;
                            $filterSection = isset($_GET['filter_section']) ? (int)$_GET['filter_section'] : '';

                            $sqlCount = "SELECT COUNT(*) FROM student_section WHERE archived = 0 AND academicSession = :academicSession";
                            if ($filterSection) {
                                $sqlCount .= " AND sectionID = :sectionID";
                            }
                            $stmtCount = $dbConnection->prepare($sqlCount);
                            $stmtCount->bindValue(':academicSession', $selectedSession, PDO::PARAM_STR);
                            if ($filterSection) {
                                $stmtCount->bindValue(':sectionID', $filterSection, PDO::PARAM_INT);
                            }
                            $stmtCount->execute();
                            $totalRecords = $stmtCount->fetchColumn();
                            $totalPages = ceil($totalRecords / $recordsPerPage);

                            $sql = "SELECT ss.*, u.firstName, u.lastName, u.image, s.sectionID, s.sectionName 
                                    FROM student_section ss
                                    JOIN users u ON ss.userID = u.userID
                                    JOIN sections s ON ss.sectionID = s.sectionID
                                    WHERE ss.archived = 0 AND ss.academicSession = :academicSession";
                            if ($filterSection) {
                                $sql .= " AND ss.sectionID = :sectionID";
                            }
                            $sql .= " ORDER BY ss.enrollmentDate DESC
                                    LIMIT :startLimit, :recordsPerPage";
                            $stmt = $dbConnection->prepare($sql);
                            $stmt->bindValue(':academicSession', $selectedSession, PDO::PARAM_STR);
                            if ($filterSection) {
                                $stmt->bindValue(':sectionID', $filterSection, PDO::PARAM_INT);
                            }
                            $stmt->bindValue(':startLimit', $startLimit, PDO::PARAM_INT);
                            $stmt->bindValue(':recordsPerPage', $recordsPerPage, PDO::PARAM_INT);
                            $stmt->execute();
                            $enrollments = $stmt->fetchAll(PDO::FETCH_ASSOC);

                            if (empty($enrollments)) {
                                echo "<tr id='no-records-message' class='no-records'><td colspan='8'>No enrolled students found for this session.</td></tr>";
                            } else {
                                echo "<tr id='no-records-message' class='no-records' style='display: none;'><td colspan='8'>No matching records found.</td></tr>";
                                $rowNumber = $startLimit + 1; 
                                foreach ($enrollments as $row) {
                                    echo "<tr data-section-id='" . htmlspecialchars($row['sectionID']) . "'>";
                                    echo "<td><input type='checkbox' name='selectedSections[]' value='" . $row['studentSectionID'] . "' class='sectionCheckbox'></td>";
                                    echo "<td>" . $rowNumber . "</td>";
                                    echo "<td><img src='" . (isset($row['image']) && !empty($row['image']) ? htmlspecialchars($row['image']) : './img/noprofile.png') . "' alt='Student Image' class='profile-img'></td>";
                                    echo "<td>" . htmlspecialchars($row['firstName'] . ' ' . $row['lastName']) . "</td>";
                                    echo "<td>" . htmlspecialchars($row['sectionName']) . "</td>";
                                    echo "<td>" . htmlspecialchars(date("F d, Y", strtotime($row['enrollmentDate']))) . "</td>";
                                    $statusClass = '';
                                    if (strtolower($row['status']) === 'enrolled') {
                                        $statusClass = 'status-enrolled';
                                    } elseif (strtolower($row['status']) === 'pending') {
                                        $statusClass = 'status-pending';
                                    } elseif (strtolower($row['status']) === 'dropped') {
                                        $statusClass = 'status-dropped';
                                    } elseif (strtolower($row['status']) === 'completed') {
                                        $statusClass = 'status-completed';
                                    }
                                    echo "<td><span class='$statusClass'>" . htmlspecialchars($row['status']) . "</span></td>";
                                    echo "<td>
                                            <form action='edit_student_section.php' method='GET' style='display: inline;'>
                                                <button type='submit' name='id' value='" . $row['studentSectionID'] . "' class='btn edit-btn'><i class='bx bxs-edit'></i> Edit</button>
                                            </form>
                                            <form action='#' method='POST' style='display: inline;' id='archiveForm" . $row['studentSectionID'] . "'>
                                                <button type='button' onclick='confirmArchive(" . $row['studentSectionID'] . ")' class='btn archive-btn'><i class='bx bxs-archive-in'></i> Archive</button>
                                            </form>
                                        </td>";
                                    echo "</tr>";
                                    $rowNumber++;
                                }
                            }
                            ?>
                        </tbody>
                    </table>

                    <!-- Pagination -->
                    <?php if ($totalPages > 1): ?>
                        <div class="pagination">
                            <?php
                            $queryString = http_build_query(array_filter($_GET, fn($key) => $key !== 'page', ARRAY_FILTER_USE_KEY));
                            if ($currentPage > 1) {
                                $prevPage = $currentPage - 1;
                                echo "<a href='data_student_section.php?page=$prevPage&$queryString'>Previous</a>";
                            } else {
                                echo "<a class='disabled'>Previous</a>";
                            }

                            $range = 2;
                            $startPage = max(1, $currentPage - $range);
                            $endPage = min($totalPages, $currentPage + $range);
                            for ($i = $startPage; $i <= $endPage; $i++) {
                                $activeClass = $i == $currentPage ? 'active' : '';
                                echo "<a href='data_student_section.php?page=$i&$queryString' class='$activeClass'>$i</a>";
                            }

                            if ($currentPage < $totalPages) {
                                $nextPage = $currentPage + 1;
                                echo "<a href='data_student_section.php?page=$nextPage&$queryString'>Next</a>";
                            } else {
                                echo "<a class='disabled'>Next</a>";
                            }
                            ?>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- Status Legend -->
                <div class="status-legend-container">
                    <h2>Status Legend</h2>
                    <div class="status-legend">
                        <span><span class="box box-enrolled"></span> Enrolled</span>
                        <span><span class="box box-pending"></span> Pending</span>
                        <span><span class="box box-dropped"></span> Dropped</span>
                        <span><span class="box box-completed"></span> Completed</span>
                    </div>
                </div>

                <!-- Section Statistics -->
                <?php
                $sectionSql = "SELECT s.sectionID, s.sectionName, 
                                     SUM(CASE WHEN u.sex = 'Male' THEN 1 ELSE 0 END) as male_count,
                                     SUM(CASE WHEN u.sex = 'Female' THEN 1 ELSE 0 END) as female_count,
                                     GROUP_CONCAT(CASE WHEN u.sex = 'Male' THEN CONCAT(u.firstName, ' ', u.lastName) END) as male_names,
                                     GROUP_CONCAT(CASE WHEN u.sex = 'Female' THEN CONCAT(u.firstName, ' ', u.lastName) END) as female_names
                              FROM sections s
                              LEFT JOIN student_section ss ON s.sectionID = ss.sectionID AND ss.archived = 0 AND ss.status = 'Enrolled' AND ss.academicSession = :academicSession
                              LEFT JOIN users u ON ss.userID = u.userID
                              WHERE s.archived = 0";
                if ($filterSection) {
                    $sectionSql .= " AND s.sectionID = :sectionID";
                }
                $sectionSql .= " GROUP BY s.sectionID, s.sectionName ORDER BY s.sectionName";
                $sectionStmt = $dbConnection->prepare($sectionSql);
                $sectionStmt->bindValue(':academicSession', $selectedSession, PDO::PARAM_STR);
                if ($filterSection) {
                    $sectionStmt->bindValue(':sectionID', $filterSection, PDO::PARAM_INT);
                }
                $sectionStmt->execute();
                $sectionsData = $sectionStmt->fetchAll(PDO::FETCH_ASSOC);
                ?>
                <div class="section-stats-container">
                    <?php foreach ($sectionsData as $section): ?>
                        <div class="section-box">
                            <div class="total-count"><?php echo ($section['male_count'] ?: 0) + ($section['female_count'] ?: 0); ?> / 50</div>
                            <h3><?php echo htmlspecialchars($section['sectionName']); ?></h3>
                            <div class="gender-container">
                                <div class="gender-box">
                                    <div class="gender-count"><?php echo $section['male_count'] ?: 0; ?> / 25</div>
                                    <h4>Male Students</h4>
                                    <div class="gender-list">
                                        <?php
                                        $maleNames = array_filter(explode(',', $section['male_names'] ?? ''));
                                        if (empty($maleNames)) {
                                            echo "<div class='gender-list-item'>No male students enrolled</div>";
                                        } else {
                                            foreach ($maleNames as $name) {
                                                echo "<div class='gender-list-item'>" . htmlspecialchars(trim($name)) . "</div>";
                                            }
                                        }
                                        ?>
                                    </div>
                                </div>
                                <div class="gender-box">
                                    <div class="gender-count"><?php echo $section['female_count'] ?: 0; ?> / 25</div>
                                    <h4>Female Students</h4>
                                    <div class="gender-list">
                                        <?php
                                        $femaleNames = array_filter(explode(',', $section['female_names'] ?? ''));
                                        if (empty($femaleNames)) {
                                            echo "<div class='gender-list-item'>No female students enrolled</div>";
                                        } else {
                                            foreach ($femaleNames as $name) {
                                                echo "<div class='gender-list-item'>" . htmlspecialchars(trim($name)) . "</div>";
                                            }
                                        }
                                        ?>
                                    </div>
                                </div>
                            </div>
                            <a href="generate_section_report.php?sectionID=<?php echo htmlspecialchars($section['sectionID']); ?>&academic_session=<?php echo urlencode($selectedSession); ?>" class="pdf-btn">Download PDF</a>
                            <form action="mark_section_complete.php" method="POST" style="display: inline;">
                                <input type="hidden" name="sectionID" value="<?php echo htmlspecialchars($section['sectionID']); ?>">
                                <input type="hidden" name="academic_session" value="<?php echo htmlspecialchars($selectedSession); ?>">
                                <button type="submit" class="complete-btn">Mark as Complete</button>
                            </form>
                        </div>
                    <?php endforeach; ?>
                </div>

                <!-- Archive Modal -->
                <div id="archiveModal" class="modal-ts">
                    <div class="modal-content-ts">
                        <span class="close">×</span>
                        <h1>Archive Student Section?</h1>
                        <h3>Are you sure you want to archive this student section?</h3>
                        <form action="archive_student_section.php" method="POST" id="confirmArchiveForm">
                            <input type="hidden" name="id" id="strandID">
                            <div class="modal-buttons">
                                <button type="submit" class="btn edit-btn">Yes</button>
                                <button type="button" class="btn archive-btn-ts" onclick="closeModal()">Cancel</button>
                            </div>
                        </form>
                    </div>
                </div>
            <?php else: ?>
                <div class="no-records" style="margin: 1.5rem; text-align: center;">
                    Please select an academic session to view enrolled students.
                </div>
            <?php endif; ?>
        </main>
        <!-- MAIN -->
    </section>
    <!-- CONTENT -->

    <script src="./utils/script.js"></script>
    <script>
        var modal = document.getElementById('archiveModal');
        var span = document.getElementsByClassName('close')[0];

        function confirmArchive(id) {
            modal.style.display = "block";
            document.getElementById('strandID').value = id;
        }

        function closeModal() {
            modal.style.display = "none";
        }

        span.onclick = function() {
            modal.style.display = "none";
        }

        window.onclick = function(event) {
            if (event.target == modal) {
                modal.style.display = "none";
            }
        }

        function filterBySession() {
            const academicSession = document.getElementById('academicSession').value;
            const url = new URL(window.location);
            if (academicSession) {
                url.searchParams.set('academic_session', academicSession);
                url.searchParams.delete('page');
                url.searchParams.delete('filter_section');
            } else {
                url.searchParams.delete('academic_session');
                url.searchParams.delete('page');
                url.searchParams.delete('filter_section');
            }
            window.location.href = url;
        }

        function filterBySection() {
            const filterSection = document.getElementById('filterSection').value;
            const selectAllCheckbox = document.getElementById('selectAll');
            selectAllCheckbox.checked = false;
            const rows = document.querySelectorAll('.admin-table tbody tr:not(.no-records)');
            const noRecordsMessage = document.getElementById('no-records-message');
            let visibleRows = 0;

            rows.forEach(row => {
                const sectionId = row.getAttribute('data-section-id');
                if (filterSection === '') {
                    row.style.display = '';
                    row.querySelector('.sectionCheckbox').checked = false;
                    visibleRows++;
                } else {
                    if (sectionId === filterSection) {
                        row.style.display = '';
                        row.querySelector('.sectionCheckbox').checked = true;
                        visibleRows++;
                    } else {
                        row.style.display = 'none';
                        row.querySelector('.sectionCheckbox').checked = false;
                    }
                }
            });

            noRecordsMessage.style.display = visibleRows === 0 ? '' : 'none';

            const url = new URL(window.location);
            if (filterSection) {
                url.searchParams.set('filter_section', filterSection);
            } else {
                url.searchParams.delete('filter_section');
            }
            window.history.pushState({}, '', url);
        }

        document.getElementById('selectAll').addEventListener('change', function() {
            const filterSection = document.getElementById('filterSection').value;
            document.querySelectorAll('.sectionCheckbox').forEach(checkbox => {
                const row = checkbox.closest('tr');
                const sectionId = row.getAttribute('data-section-id');
                if (row.style.display !== 'none' && (!filterSection || sectionId === filterSection)) {
                    checkbox.checked = this.checked;
                }
            });
        });

        function bulkUpdateSections() {
            const newSection = document.getElementById('newSection').value;
            const newAcademicSession = document.getElementById('newAcademicSession').value;
            const newStatus = document.getElementById('newStatus').value;
            if (!newSection && !newAcademicSession && !newStatus) {
                alert('Please select a new section, academic session, or status.');
                return;
            }

            const selectedCheckboxes = document.querySelectorAll('.sectionCheckbox:checked');
            if (selectedCheckboxes.length === 0) {
                alert('Please select at least one student section.');
                return;
            }

            const form = document.createElement('form');
            form.method = 'POST';
            form.action = 'bulk_update_sections.php';

            if (newSection) {
                const sectionInput = document.createElement('input');
                sectionInput.type = 'hidden';
                sectionInput.name = 'new_section_id';
                sectionInput.value = newSection;
                form.appendChild(sectionInput);
            }

            if (newAcademicSession) {
                const sessionInput = document.createElement('input');
                sessionInput.type = 'hidden';
                sessionInput.name = 'new_academic_session';
                sessionInput.value = newAcademicSession;
                form.appendChild(sessionInput);
            }

            if (newStatus) {
                const statusInput = document.createElement('input');
                statusInput.type = 'hidden';
                statusInput.name = 'new_status';
                statusInput.value = newStatus;
                form.appendChild(statusInput);
            }

            const academicSessionInput = document.createElement('input');
            academicSessionInput.type = 'hidden';
            academicSessionInput.name = 'academic_session';
            academicSessionInput.value = '<?php echo htmlspecialchars($selectedSession); ?>';
            form.appendChild(academicSessionInput);

            selectedCheckboxes.forEach(checkbox => {
                const input = document.createElement('input');
                input.type = 'hidden';
                input.name = 'selected_sections[]';
                input.value = checkbox.value;
                form.appendChild(input);
            });

            document.body.appendChild(form);
            form.submit();
        }

        window.onload = function() {
            const urlParams = new URLSearchParams(window.location.search);
            const filterSection = urlParams.get('filter_section');
            if (filterSection) {
                document.getElementById('filterSection').value = filterSection;
                filterBySection();
            }
        };
    </script>
</body>
</html>