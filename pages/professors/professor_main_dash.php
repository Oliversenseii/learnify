<?php
require_once './sessions/session_professor.php';
require_once '../../config/db_connection.php';
require_once './auto_logout.php';
require_once './check_status.php';
require_once './academic_events.php';

try {
    // Check modal status
    $stmt = $dbConnection->prepare("
        SELECT has_seen_welcome_modals 
        FROM user_modal_status 
        WHERE userID = :userID
    ");
    $stmt->bindParam(':userID', $userID, PDO::PARAM_INT);
    $stmt->execute();
    $modalStatus = $stmt->fetch(PDO::FETCH_ASSOC);

    // If no record, create one
    if (!$modalStatus) {
        $stmt = $dbConnection->prepare("
            INSERT INTO user_modal_status (userID, has_seen_welcome_modals) 
            VALUES (:userID, FALSE)
        ");
        $stmt->bindParam(':userID', $userID, PDO::PARAM_INT);
        $stmt->execute();
        $hasSeenModals = false;
    } else {
        $hasSeenModals = $modalStatus['has_seen_welcome_modals'];
    }

    // Handle modal completion
    if (isset($_POST['complete_modals'])) {
        $stmt = $dbConnection->prepare("
            UPDATE user_modal_status 
            SET has_seen_welcome_modals = TRUE, last_seen_timestamp = NOW() 
            WHERE userID = :userID
        ");
        $stmt->bindParam(':userID', $userID, PDO::PARAM_INT);
        $stmt->execute();
        $hasSeenModals = true;
        if ($_POST['complete_modals'] === 'yes') {
            header("Location: ./update_password.php");
            exit;
        }
    }

    // Get user details
    $stmt = $dbConnection->prepare("SELECT firstName, userType, image, dashboard_view FROM users WHERE userID = :userID");
    $stmt->bindParam(':userID', $_SESSION['userID'], PDO::PARAM_INT);
    $stmt->execute();
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($user) {
        $_SESSION['firstName'] = htmlspecialchars($user['firstName']);
        $_SESSION['userType'] = htmlspecialchars($user['userType']);
        $_SESSION['image'] = $user['image'] ? htmlspecialchars($user['image']) : './img/noprofile.png';
        $_SESSION['dashboard_view'] = isset($user['dashboard_view']) && in_array($user['dashboard_view'], ['table', 'card']) ? $user['dashboard_view'] : null;
    } else {
        header("Location: ../../index.php");
        exit;
    }

    // Debug: Log dashboard_view and userID
    error_log("UserID: {$_SESSION['userID']}, Dashboard View: " . ($_SESSION['dashboard_view'] ?? 'null'));

    // Pagination
    $currentPage = isset($_GET['page']) ? (int)$_GET['page'] : 1;
    $recordsPerPage = 15;
    $offset = ($currentPage - 1) * $recordsPerPage;
    $totalPages = 0;

    // Count total classes for pagination
    $countSQL = "
        SELECT COUNT(DISTINCT ts.teacherSectionID) as total 
        FROM teacher_section ts
        JOIN users u ON ts.teacherID = u.userID
        JOIN sections s ON ts.sectionID = s.sectionID
        JOIN subjects sub ON ts.subjectID = sub.subjectID
        WHERE ts.teacherID = :userID AND ts.archived = 0 AND u.archived = 0 AND s.archived = 0 AND sub.archived = 0
    ";
    $countStmt = $dbConnection->prepare($countSQL);
    $countStmt->bindParam(':userID', $_SESSION['userID'], PDO::PARAM_INT);
    $countStmt->execute();
    $totalRecords = $countStmt->fetch(PDO::FETCH_ASSOC)['total'];
    $totalPages = ceil($totalRecords / $recordsPerPage);

    // Fetch classes with card_order
    $sql = "
        SELECT 
            ts.teacherSectionID,
            ts.sectionID,
            s.sectionName,
            ts.subjectID,
            sub.subjectName,
            ts.startTime,
            ts.endTime,
            ts.day,
            ts.card_order
        FROM teacher_section ts
        JOIN users u ON ts.teacherID = u.userID
        JOIN sections s ON ts.sectionID = s.sectionID
        JOIN subjects sub ON ts.subjectID = sub.subjectID
        WHERE ts.teacherID = :userID AND ts.archived = 0 AND u.archived = 0 AND s.archived = 0 AND sub.archived = 0
        GROUP BY ts.teacherSectionID
        ORDER BY ts.card_order IS NULL, ts.card_order, ts.teacherSectionID
        LIMIT :offset, :recordsPerPage";
    $stmt = $dbConnection->prepare($sql);
    $stmt->bindParam(':userID', $_SESSION['userID'], PDO::PARAM_INT);
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    $stmt->bindValue(':recordsPerPage', $recordsPerPage, PDO::PARAM_INT);
    $stmt->execute();
    $classes = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Debug: Log number of classes fetched
    error_log("Classes fetched: " . count($classes));

} catch (PDOException $e) {
    error_log("Database Error: " . $e->getMessage() . " in " . $e->getFile() . " on line " . $e->getLine());
    $_SESSION['error_message'] = "Database error: " . htmlspecialchars($e->getMessage());
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
    <link rel="stylesheet" href="./utils/logout.css">
    <link rel="stylesheet" href="./utils/animation_slide.css">
    <link rel="stylesheet" href="./utils/logo.css">
    <link rel="stylesheet" href="./css/scrollbar.css">
    <script src="./logout.js"></script>
    <title>Learnify - Professor Dashboard</title>
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
            --hover: #d5d5d5;
            --secondary-hover: #e6f0ff;
            --secondary-grey: #f8faff;
        }

        body.dark {
            --hover: #1a1e2e;
            --secondary-hover: #3b5998;
            --secondary-grey: #1a2332;
        }

        .modal {
            z-index: 1000000;
        }

        /* Tab Container */
        .tab-container {
            margin: 2rem;
            margin-bottom: 0;
        }

        .tab-buttons {
            display: flex;
            border-bottom: 2px solid var(--border);
            margin-bottom: 1rem;
        }

        .tab-button {
            padding: 0.75rem 1.5rem;
            background: none;
            border: none;
            font-size: 1rem;
            font-weight: 500;
            color: var(--text-secondary);
            cursor: pointer;
            transition: var(--transition);
        }

        .tab-button.active {
            color: var(--primary);
            border-bottom: 2px solid var(--primary);
            font-weight: 600;
        }

        .tab-button:hover {
            color: var(--primary);
        }

        .tab-content {
            display: none;
        }

        .tab-content.active {
            display: block;
        }

        /* Table Container */
        .table-container {
            margin: 2rem;
            padding: 2rem;
            border-radius: 12px;
            box-shadow: var(--shadow);
            overflow-x: auto;
            background-color: var(--light);
        }

        table {
            width: 100%;
            border-collapse: collapse;
            min-width: 600px;
        }

        th, td {
            padding: 1rem;
            font-size: clamp(1rem, 3vw, 1.2rem);
            text-align: left;
            color: var(--dark);
        }

        th {
            background-color: var(--primary);
            color: var(--white);
            font-weight: 600;
        }

        tr:nth-child(even) {
            background: linear-gradient(135deg, var(--secondary-grey), var(--grey));
            transition: background 0.3s ease; 
        }

        tr:hover {
            background: linear-gradient(135deg, var(--secondary-hover), var(--hover));
        }

        .btn-view{
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

        .btn-view:hover {
            background: linear-gradient(135deg, var(--primary-dark), #1A4971);
            transform: translateY(-2px);
            box-shadow: var(--shadow);
        }

        .back-button i {
            font-size: clamp(1.1rem, 3vw, 1.3rem);
        }

        /* Card View  */
        .card-container {
            margin: 2rem;
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 1.5rem;
        }

        .class-card {
            background: var(--light);
            border-radius: 8px;
            box-shadow: var(--shadow);
            overflow: hidden;
            transition: var(--transition);
            position: relative;
            cursor: pointer; 
            user-select: none; 
        }

        .class-card.dragging {
            opacity: 0.5;
            transform: scale(0.95);
        }

        .class-card:hover {
            transform: translateY(-4px);
            box-shadow: 0 6px 12px -2px rgba(0, 0, 0, 0.15);
        }

        .card-header {
            background: linear-gradient(135deg, var(--primary), var(--primary-dark));
            padding: 1rem;
            color: var(--white);
            font-size: clamp(1.1rem, 3vw, 1.3rem);
            font-weight: 600;
        }

        .card-body {
            max-width: 400px;
            padding: 20px;
            background-color: var(--light);
            color: var(--text-secondary); 
        }

        .table-row {
            display: flex;
            justify-content: space-between;
            padding: 8px 0;
        }

        .table-row:last-child {
            border-bottom: none;
        }

        .table-label {
            font-weight: bold;
            width: 30%;
            color: var(--dark);
            font-size: clamp(1rem, 3vw, 1.3rem);
        }

        .table-value {
            width: 70%;
            text-align: left;
            font-size: clamp(1rem, 3vw, 1.3rem);
        }

        .card-initials {
            position: absolute;
            top: 2.30rem;
            right: 1rem;
            width: 40px;
            height: 40px;
            background-color: #2B6CB0;
            color: white;
            border-radius: 50%;
            border: 2px solid white;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: clamp(1.1rem, 3vw, 1.3rem);
            font-weight: 600;
            text-transform: uppercase;
        }

        #content main .head-title {
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 1rem;
        }

        .success-notification, .error-notification {
            background: var(--success);
            color: var(--white);
            padding: 1rem 1.5rem;
            margin: 1.5rem 2rem;
            border-radius: 8px;
            text-align: center;
            font-weight: 500;
            box-shadow: var(--shadow);
            max-width: 500px;
            margin-left: auto;
            margin-right: auto;
        }

        .error-notification {
            background: var(--error);
        }

        .pagination {
            display: flex;
            justify-content: center;
            gap: 0.5rem;
            margin: 2rem;
        }

        .pagination a {
            padding: 0.5rem 1rem;
            text-decoration: none;
            color: var(--text);
            background: var(--white);
            border: 1px solid var(--border);
            border-radius: 8px;
            transition: var(--transition);
        }

        .pagination a:hover {
            background: var(--primary);
            color: var(--white);
        }

        .pagination a.active {
            background: var(--primary);
            color: var(--white);
            font-weight: 600;
        }

        .pagination a.disabled {
            color: var(--text-secondary);
            cursor: not-allowed;
            background: var(--accent);
        }

        .no-classes {
            text-align: left;
            color: red;
            font-style: italic;
            font-size: clamp(1.5rem, 3vw, 2rem);
            padding: 2rem;
        }

        /* Modal Styles */
        .schedule-container .btn-view {
            font-size: clamp(1rem, 3vw, 1.3rem);
            border: none;
            cursor: pointer;
        }

        .close-btn {
            position: absolute;
            top: 1.2rem;
            right: 1.2rem;
            font-size: clamp(1rem, 3vw, 3rem);
            color: var(--text-secondary);
            cursor: pointer;
            transition: var(--transition);
        }

        .close-btn:hover {
            color: #c82333;
            transition: 0.6s ease;
        }

        .modal-content h2 {
            border-bottom: 1px solid #ccc;
        }

        .modal .table-container {
            margin: 0;
            padding: 0;
            box-shadow: none;
            background: var(--light);
            max-width: 1400px;
            width: 100%;
            max-height: 80vh;
            height: 70vh;
        }

        .modal table {
            min-width: 1100px;
            table-layout: auto;
        }

        .modal th, .modal td {
            padding: 1.2rem;
            font-size: clamp(1rem, 3vw, 1.4rem);
            text-align: center;
            vertical-align: middle;
            min-width: 120px;
        }

        .modal th {
            background: var(--primary);
            color: var(--white);
        }

        .modal td {
            border: 1px solid var(--dark);
        }

        .modal td.break {
            background: linear-gradient(135deg, var(--secondary-grey), var(--grey));
            font-weight: 600;
            color: var(--dark);
        }

        .subject-link {
            color: var(--dark);
            text-decoration: none;
            transition: var(--transition);
            text-transform: uppercase;
        }

        .subject-link:hover {
            color: var(--primary-dark);
            text-decoration: underline;
        }

        .modal tr:nth-child(even) {
            background: linear-gradient(135deg, var(--secondary-grey), var(--grey));
            transition: background 0.3s ease; 
        }

        .modal tr:hover {
            background: transparent;
        }

        .schedule-section-name {
            display: block;
            font-size: clamp(0.8rem, 2vw, 0.9rem);
            color: var(--text-secondary);
            margin-top: 0.5rem;
        }

        @media (max-width: 1024px) {
            .card-container {
                grid-template-columns: repeat(2, 1fr);
            }

            .modal-content {
                width: 95%;
                max-width: 800px;
            }

            .modal th, .modal td {
                min-width: 100px;
                font-size: clamp(0.8rem, 2vw, 0.9rem);
            }

            .schedule-section-name {
                font-size: clamp(0.7rem, 1.8vw, 0.8rem);
            }
        }

        @media screen and (max-width: 768px) {
            .tab-container, .table-container, .card-container {
                margin: 1rem;
                padding: 1rem;
            }

            .tab-buttons {
                flex-direction: row;
                gap: 0.5rem;
            }

            .tab-button {
                padding: 0.5rem 1rem;
                border-bottom: 1px solid var(--border);
            }

            .tab-button.active {
                border-bottom: 1px solid var(--primary);
            }

            th, td {
                padding: 0.75rem;
                font-size: 0.9rem;
            }

            .btn-view {
                padding: 0.4rem 0.8rem;
                font-size: 0.85rem;
            }

            .success-notification, .error-notification {
                margin: 1rem;
                padding: 0.75rem;
                font-size: 0.9rem;
            }

            .pagination {
                margin: 1rem;
                gap: 0.3rem;
            }

            .pagination a {
                padding: 0.4rem 0.8rem;
                font-size: 0.9rem;
            }

            .card-container {
                grid-template-columns: 1fr;
            }

            .modal-content {
                width: 95%;
                padding: 1rem;
            }

            .close-btn {
                font-size: 1.2rem;
                top: 0.5rem;
                right: 0.5rem;
            }

        }

        @media screen and (max-width: 480px) {
            .table-container, .card-container {
                margin: 0;
                padding: 0;
                margin-top: 10px;
            }

            .btn-view {
                padding: 0.3rem 0.6rem;
            }

            .success-notification, .error-notification {
                margin: 0.5rem;
                padding: 0.5rem;
            }

            .card-header {
                font-size: 1rem;
                padding: 0.75rem;
            }

            .class-card-header span {
                white-space: nowrap; 
                overflow: hidden; 
                text-overflow: ellipsis;
                max-width: 80%; 
            }

            .card-body {
                padding: 0.75rem;
            }

            .card-initials {
                width: 32px;
                height: 32px;
                font-size: 0.9rem;
                top: 1.5rem;
                right: 1rem;
            }

            .modal-content {
                width: 98%;
                padding: 0.75rem;
            }
        }
    </style>
</head>
<body>
    <section id="sidebar">
        <?php require_once './brand.php' ?>
        <ul class="side-menu top">
            <li class="active">
                <a href="./professor_main_dash.php">
                    <i class='bx bxs-dashboard'></i>
                    <span class="text">Dashboard</span>
                </a>
            </li>
            <li>
                <a href="./modules.php">
                    <i class='bx bxs-bookmark'></i>
                    <span class="text">Modules</span>
                </a>
            </li>
            <li>
                <a href="./calendar.php">
                    <i class='bx bxs-calendar'></i>
                    <span class="text">Calendar</span>
                </a>
            </li>
            <li>
                <a href="./message_admin.php">
                    <i class='bx bxs-message'></i>
                    <span class="text">Message Admin</span>
                </a>
            </li>
            <li>
                <a href="./game_controller.php">
                    <i class='bx bxs-game'></i>
                    <span class="text">Game</span>
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
    <?php require_once './welcome_modals.php'; ?>

    <section id="content">
        <div id="scheduleModal" class="modal" style="display: none;">
            <div class="modal-content">
                <span class="close-btn" onclick="closeScheduleModal()" aria-label="Close Schedule Modal">&times;</span>
                <h2>Weekly Schedule</h2>
                <div class="table-container">
                    <table>
                        <thead>
                            <tr>
                                <th>Time</th>
                                <th>Monday</th>
                                <th>Tuesday</th>
                                <th>Wednesday</th>
                                <th>Thursday</th>
                                <th>Friday</th>
                                <th>Saturday</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php
                            $timeSlots = [
                                ['start' => '06:00:00', 'end' => '08:00:00', 'label' => '6:00am - 8:00am'],
                                ['start' => '08:00:00', 'end' => '10:00:00', 'label' => '8:00am - 10:00am'],
                                ['start' => '10:00:00', 'end' => '12:00:00', 'label' => '10:00am - 12:00pm'],
                                ['start' => '12:00:00', 'end' => '13:00:00', 'label' => '12:00pm - 1:00pm', 'break' => true],
                                ['start' => '13:00:00', 'end' => '15:00:00', 'label' => '1:00pm - 3:00pm'],
                                ['start' => '15:00:00', 'end' => '17:00:00', 'label' => '3:00pm - 5:00pm'],
                                ['start' => '17:00:00', 'end' => '19:00:00', 'label' => '5:00pm - 7:00pm'],
                            ];

                            $daysOfWeek = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'];

                            $schedule = [];
                            foreach ($timeSlots as $slot) {
                                $schedule[$slot['label']] = array_fill_keys($daysOfWeek, [
                                    'subject' => '',
                                    'subjectID' => null,
                                    'sectionName' => ''
                                ]);
                            }

                            foreach ($classes as $class) {
                                $day = htmlspecialchars($class['day']);
                                if (!in_array($day, $daysOfWeek)) {
                                    continue;
                                }
                                $classStart = strtotime($class['startTime']);
                                $classEnd = strtotime($class['endTime']);
                                $subject = htmlspecialchars($class['subjectName']);
                                $subjectID = htmlspecialchars($class['subjectID']);
                                $sectionName = htmlspecialchars($class['sectionName']);

                                foreach ($timeSlots as $slot) {
                                    if (isset($slot['break'])) {
                                        continue; 
                                    }
                                    $slotStart = strtotime($slot['start']);
                                    $slotEnd = strtotime($slot['end']);

                                    if ($classStart >= $slotStart && $classEnd <= $slotEnd) {
                                        $schedule[$slot['label']][$day] = [
                                            'subject' => $subject,
                                            'subjectID' => $subjectID,
                                            'sectionName' => $sectionName
                                        ];
                                    }
                                }
                            }

                            foreach ($timeSlots as $slot) {
                                echo "<tr>";
                                echo "<td" . (isset($slot['break']) ? ' class=\"break\"' : '') . ">" . $slot['label'] . "</td>";
                                if (isset($slot['break'])) {
                                    echo "<td class='break' colspan='6'>BREAK</td>";
                                } else {
                                    foreach ($daysOfWeek as $day) {
                                        $subjectData = $schedule[$slot['label']][$day];
                                        if ($subjectData['subject'] && $subjectData['subjectID']) {
                                            echo "<td>";
                                            echo "<a href='professorDash.php?subjectID=" . $subjectData['subjectID'] . "' class='subject-link' aria-label='View " . $subjectData['subject'] . " Details'>" . $subjectData['subject'] . "</a>";
                                            echo "<span class='schedule-section-name'>" . $subjectData['sectionName'] . "</span>";
                                            echo "</td>";
                                        } else {
                                            echo "<td>" . ($subjectData['subject'] ?: '') . "</td>";
                                        }
                                    }
                                }
                                echo "</tr>";
                            }
                            ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        <nav>
            <i class='bx bx-menu' aria-label="Toggle Sidebar"></i>
            <form action="search.php" method="get">
                <div class="form-input">
                    <input type="search" name="query" placeholder="Search users..." required aria-label="Search">
                    <button type="submit" class="search-btn" aria-label="Search"><i class='bx bx-search'></i></button>
                </div>
            </form>
            <input type="checkbox" id="switch-mode" hidden aria-label="Toggle Dark Mode">
            <label for="switch-mode" class="switch-mode"></label>
            <a href="./main_acc.php" class="profile" aria-label="Profile">
                <img src="<?php echo $_SESSION['image']; ?>" alt="Profile Image">
                <div>
                    <p><?php echo htmlspecialchars($_SESSION['firstName']); ?></p>
                    <small>Teacher</small>
                </div>
            </a>
        </nav>

        <main>
            <div class="head-title">
                <div class="left">
                    <h1>Teacher Dashboard</h1>
                    <ul class="breadcrumb">
                        <li><a href="#">Home</a></li>
                        <li><i class='bx bx-chevron-right'></i></li>
                        <li><a class="active" href="./professor_main_dash.php">Dashboard</a></li>
                    </ul>
                </div>
                <div class="schedule-container">
                    <button class="btn-view" onclick="showScheduleModal()" aria-label="View Time Schedule">View Time Schedule</button>
                </div>
            </div>

            <?php if (isset($_SESSION['success_message'])): ?>
                <div class="success-notification">
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

            <?php if (is_null($_SESSION['dashboard_view'])): ?>
                <div class="tab-container">
                    <div class="tab-buttons">
                        <button class="tab-button active" data-tab="table">Table View</button>
                        <button class="tab-button" data-tab="card">Card View</button>
                    </div>
                </div>
                <div class="table-container tab-content active" id="table">
                    <?php if (!empty($classes)): ?>
                        <table>
                            <thead>
                                <tr>
                                    <th>#</th>
                                    <th>Subject</th>
                                    <th>Section</th>
                                    <th>Day</th>
                                    <th>Time</th>
                                    <th>Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php $rowNumber = $offset + 1; ?>
                                <?php foreach ($classes as $class): ?>
                                    <?php
                                    $startTime = date("g:ia", strtotime($class['startTime']));
                                    $endTime = date("g:ia", strtotime($class['endTime']));
                                    $formattedTime = "$startTime - $endTime";
                                    ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($rowNumber++); ?></td>
                                        <td><?php echo htmlspecialchars($class['subjectName']); ?></td>
                                        <td><?php echo htmlspecialchars($class['sectionName']); ?></td>
                                        <td><?php echo htmlspecialchars($class['day']); ?></td>
                                        <td><?php echo htmlspecialchars($formattedTime); ?></td>
                                        <td>
                                            <a href="professorDash.php?subjectID=<?php echo htmlspecialchars($class['subjectID']); ?>" 
                                               class="btn-view" 
                                               aria-label="View Class Details">View</a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php else: ?>
                        <p class="no-classes">No classes found.</p>
                    <?php endif; ?>
                </div>
                <div class="card-container tab-content" id="card">
                    <?php if (!empty($classes)): ?>
                        <?php foreach ($classes as $class): ?>
                            <?php
                            $startTime = date("g:ia", strtotime($class['startTime']));
                            $endTime = date("g:ia", strtotime($class['endTime']));
                            $formattedTime = "$startTime - $endTime";
                            $initials = strtoupper(substr($class['subjectName'], 0, 2));
                            ?>
                            <a href="professorDash.php?subjectID=<?php echo htmlspecialchars($class['subjectID']); ?>" 
                               class="class-card" 
                               draggable="true" 
                               data-id="<?php echo htmlspecialchars($class['teacherSectionID']); ?>"
                               aria-label="View <?php echo htmlspecialchars($class['subjectName']); ?> Details">
                                <div class="card-header">
                                    <?php echo htmlspecialchars($class['subjectName']); ?>
                                    <span class="card-initials"><?php echo htmlspecialchars($initials); ?></span>
                                </div>
                                <div class="card-body">
                                    <div class="table-row">
                                        <span class="table-label">Section:</span>
                                        <span class="table-value"><?php echo htmlspecialchars($class['sectionName']); ?></span>
                                    </div>
                                    <div class="table-row">
                                        <span class="table-label">Day:</span>
                                        <span class="table-value"><?php echo htmlspecialchars($class['day']); ?></span>
                                    </div>
                                    <div class="table-row">
                                        <span class="table-label">Time:</span>
                                        <span class="table-value"><?php echo htmlspecialchars($formattedTime); ?></span>
                                    </div>
                                </div>
                            </a>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <p class="no-classes">No classes found.</p>
                    <?php endif; ?>
                </div>
            <?php elseif ($_SESSION['dashboard_view'] === 'table'): ?>
                <div class="table-container">
                    <?php if (!empty($classes)): ?>
                        <table>
                            <thead>
                                <tr>
                                    <th>#</th>
                                    <th>Subject</th>
                                    <th>Section</th>
                                    <th>Day</th>
                                    <th>Time</th>
                                    <th>Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php $rowNumber = $offset + 1; ?>
                                <?php foreach ($classes as $class): ?>
                                    <?php
                                    $startTime = date("g:ia", strtotime($class['startTime']));
                                    $endTime = date("g:ia", strtotime($class['endTime']));
                                    $formattedTime = "$startTime - $endTime";
                                    ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($rowNumber++); ?></td>
                                        <td><?php echo htmlspecialchars($class['subjectName']); ?></td>
                                        <td><?php echo htmlspecialchars($class['sectionName']); ?></td>
                                        <td><?php echo htmlspecialchars($class['day']); ?></td>
                                        <td><?php echo htmlspecialchars($formattedTime); ?></td>
                                        <td>
                                            <a href="professorDash.php?subjectID=<?php echo htmlspecialchars($class['subjectID']); ?>" 
                                               class="btn-view" 
                                               aria-label="View Class Details">View</a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php else: ?>
                        <p class="no-classes">No classes found.</p>
                    <?php endif; ?>
                </div>
            <?php elseif ($_SESSION['dashboard_view'] === 'card'): ?>
                <div class="card-container">
                    <?php if (!empty($classes)): ?>
                        <?php foreach ($classes as $class): ?>
                            <?php
                            $startTime = date("g:ia", strtotime($class['startTime']));
                            $endTime = date("g:ia", strtotime($class['endTime']));
                            $formattedTime = "$startTime - $endTime";
                            $initials = strtoupper(substr($class['subjectName'], 0, 2));
                            ?>
                            <a href="professorDash.php?subjectID=<?php echo htmlspecialchars($class['subjectID']); ?>" 
                               class="class-card" 
                               draggable="true" 
                               data-id="<?php echo htmlspecialchars($class['teacherSectionID']); ?>"
                               aria-label="View <?php echo htmlspecialchars($class['subjectName']); ?> Details">
                                <div class="card-header">
                                    <?php echo htmlspecialchars($class['subjectName']); ?>
                                    <span class="card-initials"><?php echo htmlspecialchars($initials); ?></span>
                                </div>
                                <div class="card-body">
                                    <div class="table-row">
                                        <span class="table-label">Section:</span>
                                        <span class="table-value"><?php echo htmlspecialchars($class['sectionName']); ?></span>
                                    </div>
                                    <div class="table-row">
                                        <span class="table-label">Day:</span>
                                        <span class="table-value"><?php echo htmlspecialchars($class['day']); ?></span>
                                    </div>
                                    <div class="table-row">
                                        <span class="table-label">Time:</span>
                                        <span class="table-value"><?php echo htmlspecialchars($formattedTime); ?></span>
                                    </div>
                                </div>
                            </a>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <p class="no-classes">No classes found.</p>
                    <?php endif; ?>
                </div>
            <?php endif; ?>

            <?php if ($totalPages > 0): ?>
                <div class="pagination">
                    <?php
                    if ($currentPage > 1) {
                        $prevPage = $currentPage - 1;
                        echo "<a href='professor_main_dash.php?page=$prevPage' aria-label='Previous Page'>Previous</a>";
                    } else {
                        echo "<a class='disabled' aria-disabled='true'>Previous</a>";
                    }

                    for ($i = 1; $i <= $totalPages; $i++) {
                        $activeClass = $i == $currentPage ? 'active' : '';
                        echo "<a href='professor_main_dash.php?page=$i' class='$activeClass' aria-label='Page $i'>$i</a>";
                    }

                    if ($currentPage < $totalPages) {
                        $nextPage = $currentPage + 1;
                        echo "<a href='professor_main_dash.php?page=$nextPage' aria-label='Next Page'>Next</a>";
                    } else {
                        echo "<a class='disabled' aria-disabled='true'>Next</a>";
                    }
                    ?>
                </div>
            <?php endif; ?>
        </main>
    </section>

    <script src="./utils/script.js"></script>
    <?php if (is_null($_SESSION['dashboard_view'])): ?>
        <script>
            document.addEventListener('DOMContentLoaded', function () {
                const tabButtons = document.querySelectorAll('.tab-button');
                const tabContents = document.querySelectorAll('.tab-content');

                tabButtons.forEach(button => {
                    button.addEventListener('click', () => {
                        tabButtons.forEach(btn => btn.classList.remove('active'));
                        tabContents.forEach(content => content.classList.remove('active'));

                        button.classList.add('active');
                        const tabId = button.getAttribute('data-tab');
                        document.getElementById(tabId).classList.add('active');
                    });
                });
            });
        </script>
    <?php endif; ?>
    <?php if ($_SESSION['dashboard_view'] === 'card' || is_null($_SESSION['dashboard_view'])): ?>
        <script>
            document.addEventListener('DOMContentLoaded', function () {
                const cardContainer = document.querySelector('.card-container');
                if (!cardContainer) return;

                const cards = cardContainer.querySelectorAll('.class-card');

                cards.forEach(card => {
                    card.addEventListener('dragstart', (e) => {
                        card.classList.add('dragging');
                        e.dataTransfer.setData('text/plain', card.dataset.id);
                    });

                    card.addEventListener('dragend', () => {
                        card.classList.remove('dragging');
                    });

                    card.addEventListener('dragover', (e) => {
                        e.preventDefault();
                    });

                    card.addEventListener('drop', (e) => {
                        e.preventDefault();
                        const draggedId = e.dataTransfer.getData('text/plain');
                        const draggedCard = cardContainer.querySelector(`[data-id="${draggedId}"]`);
                        const dropCard = card;

                        if (draggedCard !== dropCard) {
                            const allCards = [...cardContainer.querySelectorAll('.class-card')];
                            const draggedIndex = allCards.indexOf(draggedCard);
                            const dropIndex = allCards.indexOf(dropCard);

                            if (draggedIndex < dropIndex) {
                                cardContainer.insertBefore(draggedCard, dropCard.nextSibling);
                            } else {
                                cardContainer.insertBefore(draggedCard, dropCard);
                            }

                            const updatedOrder = [...cardContainer.querySelectorAll('.class-card')].map(c => c.dataset.id);
                            fetch('save_card_order.php', {
                                method: 'POST',
                                headers: {
                                    'Content-Type': 'application/json',
                                },
                                body: JSON.stringify({ order: updatedOrder }),
                            })
                            .then(response => response.json())
                            .then(data => {
                                if (!data.success) {
                                    console.error('Failed to save card order:', data.error);
                                }
                            })
                            .catch(error => console.error('Error saving card order:', error));
                        }
                    });
                });
            });
        </script>
    <?php endif; ?>
    <script>
        function showScheduleModal() {
            const modal = document.getElementById('scheduleModal');
            modal.style.display = 'flex';
        }

        function closeScheduleModal() {
            const modal = document.getElementById('scheduleModal');
            modal.style.display = 'none';
        }

        window.addEventListener('click', function (event) {
            const modal = document.getElementById('scheduleModal');
            if (event.target === modal) {
                closeScheduleModal();
            }
        });

        document.addEventListener('keydown', function (event) {
            if (event.key === 'Escape') {
                closeScheduleModal();
            }
        });
    </script>
</body>
</html>
<?php
// Close database connection
$dbConnection = null;
?>