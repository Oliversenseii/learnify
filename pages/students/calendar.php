<?php
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

// Get user details
try {
    $stmt = $dbConnection->prepare("SELECT firstName, userType, image FROM users WHERE userID = :userID");
    $stmt->bindParam(':userID', $userID, PDO::PARAM_INT);
    $stmt->execute();
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($user) {
        $_SESSION['firstName'] = htmlspecialchars($user['firstName']);
        $_SESSION['userType'] = htmlspecialchars($user['userType']);
        $_SESSION['image'] = $user['image'] ? htmlspecialchars($user['image']) : './img/noprofile.png';
    } else {
        error_log("User not found for userID: " . $userID);
        session_destroy();
        header("Location: ../../index.php");
        exit;
    }

    // Fetch all quizzes for the student across all their enrolled sections, only those released
    $quizStmt = $dbConnection->prepare("
        SELECT q.quizID, q.title, q.dueDate, q.teacherSectionID, s.sectionName, sub.subjectName
        FROM quizzes q
        JOIN teacher_section ts ON q.teacherSectionID = ts.teacherSectionID
        JOIN sections s ON ts.sectionID = s.sectionID
        JOIN subjects sub ON ts.subjectID = sub.subjectID
        JOIN student_section ss ON ts.sectionID = ss.sectionID
        WHERE ss.userID = :userID 
        AND q.archived = 0 
        AND ts.archived = 0 
        AND ss.archived = 0 
        AND s.archived = 0 
        AND sub.archived = 0 
        AND ss.status = 'Enrolled'
        AND q.releaseDate <= NOW()
        ORDER BY q.dueDate
    ");
    $quizStmt->bindParam(':userID', $userID, PDO::PARAM_INT);
    $quizStmt->execute();
    $quizzes = $quizStmt->fetchAll(PDO::FETCH_ASSOC);

    // Fetch all assignments for the student across all their enrolled sections
    $assignmentStmt = $dbConnection->prepare("
        SELECT a.assignmentID, a.title, a.dueDate, a.teacherSectionID, s.sectionName, sub.subjectName
        FROM assignments a
        JOIN teacher_section ts ON a.teacherSectionID = ts.teacherSectionID
        JOIN sections s ON ts.sectionID = s.sectionID
        JOIN subjects sub ON ts.subjectID = sub.subjectID
        JOIN student_section ss ON ts.sectionID = ss.sectionID
        WHERE ss.userID = :userID 
        AND a.archived = 0 
        AND ts.archived = 0 
        AND ss.archived = 0 
        AND s.archived = 0 
        AND sub.archived = 0 
        AND ss.status = 'Enrolled'
        ORDER BY a.dueDate
    ");
    $assignmentStmt->bindParam(':userID', $userID, PDO::PARAM_INT);
    $assignmentStmt->execute();
    $assignments = $assignmentStmt->fetchAll(PDO::FETCH_ASSOC);

    // Fetch academic events
    $eventStmt = $dbConnection->prepare("
        SELECT eventID, title, description, eventDate, eventType
        FROM academic_events
        WHERE archived = 0
        ORDER BY eventDate
    ");
    $eventStmt->execute();
    $events = $eventStmt->fetchAll(PDO::FETCH_ASSOC);

    // Organize quizzes, assignments, and academic events by due date
    $itemsByDate = [];
    foreach ($quizzes as &$quiz) {
        if ($quiz['dueDate']) {
            $date = date('Y-m-d', strtotime($quiz['dueDate']));
            // Check if quiz has been submitted
            $submissionStmt = $dbConnection->prepare("
                SELECT answerID FROM quiz_answers 
                WHERE quizID = :quizID AND studentID = :studentID AND archived = 0
            ");
            $submissionStmt->bindParam(':quizID', $quiz['quizID'], PDO::PARAM_INT);
            $submissionStmt->bindParam(':studentID', $userID, PDO::PARAM_INT);
            $submissionStmt->execute();
            $quiz['hasSubmitted'] = $submissionStmt->rowCount() > 0;
            $quiz['type'] = 'quiz';
            $itemsByDate[$date][] = $quiz;
        }
    }

    foreach ($assignments as &$assignment) {
        if ($assignment['dueDate']) {
            $date = date('Y-m-d', strtotime($assignment['dueDate']));
            // Check if assignment has been submitted
            $submissionStmt = $dbConnection->prepare("
                SELECT submissionID FROM assignment_submissions 
                WHERE assignmentID = :assignmentID AND studentID = :studentID AND archived = 0
            ");
            $submissionStmt->bindParam(':assignmentID', $assignment['assignmentID'], PDO::PARAM_INT);
            $submissionStmt->bindParam(':studentID', $userID, PDO::PARAM_INT);
            $submissionStmt->execute();
            $assignment['hasSubmitted'] = $submissionStmt->rowCount() > 0;
            $assignment['type'] = 'assignment';
            $itemsByDate[$date][] = $assignment;
        }
    }

    foreach ($events as $event) {
        if ($event['eventDate']) {
            $date = date('Y-m-d', strtotime($event['eventDate']));
            $event['type'] = $event['eventType'] === 'Holiday' ? 'holiday' : 'event';
            // Initialize hasSubmitted as false for events (to avoid undefined key error)
            $event['hasSubmitted'] = false;
            $itemsByDate[$date][] = $event;
        }
    }

    // Determine submission status for each date (for quizzes and assignments only)
    $dateSubmissionStatus = [];
    foreach ($itemsByDate as $date => $items) {
        $allSubmitted = true;
        foreach ($items as $item) {
            if (in_array($item['type'], ['quiz', 'assignment']) && !$item['hasSubmitted']) {
                $allSubmitted = false;
                break;
            }
        }
        $dateSubmissionStatus[$date] = $allSubmitted;
    }

} catch (PDOException $e) {
    error_log("Database Error: " . $e->getMessage() . " in " . $e->getFile() . " on line " . $e->getLine());
    $_SESSION['error_message'] = "Database error: " . htmlspecialchars($e->getMessage());
}

// Include notification count
require_once './get_notification_count.php';
$unreadCount = getUnreadAnnouncementCount($dbConnection, $userID);

// Ensure announcements_viewed session flag is set
if (!isset($_SESSION['announcements_viewed'])) {
    $_SESSION['announcements_viewed'] = false;
}

// Calendar setup
$month = isset($_GET['month']) ? (int)$_GET['month'] : date('n');
$year = isset($_GET['year']) ? (int)$_GET['year'] : date('Y');
$firstDay = mktime(0, 0, 0, $month, 1, $year);
$daysInMonth = cal_days_in_month(CAL_GREGORIAN, $month, $year);
$dayOfWeek = date('w', $firstDay);
$monthName = date('F', $firstDay);
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
    <title>Learnify - Student Calendar</title>
    <style> 
        :root {
            --light: #F9F9F9;
            --blue: #3C91E6;
            --grey: #f3f4f6;
            --dark-grey: #9ca3af;
            --dark: #1f2937;
            --green: #10b981;
            --red: #ef4444;
            --purple: #8b5cf6;
            --orange: #f97316;
            --yellow: #eab308;
        }

        @import url('https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap');

        .calendar-container {
            margin-top: 10px;
            padding: 1rem;
            background: var(--light);
            border-radius: 0.5rem;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
            overflow-x: auto;
        }

        .calendar-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1rem;
        }

        .calendar-header h2 {
            font-size: clamp(1.5rem, 3vw, 2rem);
            color: var(--dark);
        }

        .calendar-nav a {
            text-decoration: none;
            color: var(--blue);
            font-size: clamp(1.1rem, 3vw, 1.5rem);
            padding: 0.5rem;
            transition: color 0.2s ease;
        }

        .calendar-nav a:hover {
            color: #2563eb;
        }

        .calendar-table {
            width: 100%;
            border-collapse: collapse;
            font-size: clamp(1.1rem, 3vw, 1.4rem);
        }

        .calendar-table th,
        .calendar-table td {
            padding: 0.75rem;
            text-align: center;
            border: 1px solid #0056b3;
            position: relative;
            font-size: clamp(1.1rem, 3vw, 1.2rem);
        }

        .calendar-table th {
            background: #0056b3;
            color: white;
            font-weight: 600;
        }

        .calendar-table td {
            background: var(--light);
            color: var(--dark);
            padding: 20px;
            cursor: pointer;
            transition: background-color 0.2s ease;
            min-height: 100px;
        }

        .calendar-table td:hover {
            background: var(--grey);
            box-shadow: 0 6px 40px rgba(0, 0, 0, 0.15);
            z-index: 10;
        }

        .calendar-table td.empty {
            background: transparent;
            cursor: default;
            border: none;
        }

        .item-list {
            list-style: none;
            padding: 0;
            margin: 0.5rem 0 0;
            text-align: left;
            font-size: 2rem;
        }

        .item-list li {
            margin-bottom: 0.3rem;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
            cursor: pointer;
            padding: 0.2rem 0.5rem;
            border-radius: 0.25rem;
            margin: 0 auto;
            width: fit-content;
            margin-bottom: 5px;
            font-size: clamp(1.1rem, 3vw, 1.2rem);
        }

        .item-list li.quiz-submitted, .item-list li.assignment-submitted {
            background: linear-gradient(135deg, #28a745, #218838);
            color: white;
        }

        .item-list li.quiz-not-submitted, .item-list li.assignment-not-submitted {
            background: linear-gradient(135deg, #dc3545, #c82333);
            color: white;
        }

        .item-list li.holiday {
            background: linear-gradient(135deg, #f97316, #d97706);
            color: white;
        }

        .item-list li.event {
            background: linear-gradient(135deg, #eab308, #ca8a04);
            color: white;
        }

        @keyframes fadeIn {
            from { opacity: 0; }
            to { opacity: 1; }
        }

        .modal-content-calendar {
            background: var(--light);
            border-radius: 0.75rem;
            max-width: 90%;
            width: 500px;
            max-height: 80vh;
            overflow-y: auto;
            box-shadow: 0 8px 24px rgba(0, 0, 0, 0.2);
            position: relative;
            animation: slideIn 0.3s ease-in-out;
        }

        @keyframes slideIn {
            from { transform: translateY(-20px); opacity: 0; }
            to { transform: translateY(0); opacity: 1; }
        }

        .modal-content-calendar .modal-header {
            background: linear-gradient(135deg, #007bff, #0056b3);
            padding: 1.5rem;
            border-top-left-radius: 0.75rem;
            border-top-right-radius: 0.75rem;
            color: white;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .modal-content-calendar .modal-header h3 {
            margin: 0;
            font-size: clamp(1.5rem, 3vw, 2rem);
            font-weight: 600;
        }

        .modal-header .close-btn {
            background: transparent;
            border: none;
            color: white;
            font-size: clamp(1.5rem, 3vw, 2rem);
            cursor: pointer;
            transition: color 0.2s ease;
        }

        .modal-header .close-btn:hover {
            color: var(--red);
        }

        .modal-body {
            padding: 1.5rem;
            background-color: var(--light);
        }

        .item-info {
            padding: 1rem;
            background: var(--grey);
            border-radius: 0.5rem;
            margin-bottom: 1rem;
            border-left: 4px solid #0056b3;
        }

        .item-info p {
            margin: 0.5rem 0;
            font-size: clamp(1.1rem, 3vw, 1.2rem);
            color: var(--dark);
        }

        .status-submitted {
            color: var(--green);
            font-weight: 600;
        }

        .status-not-submitted {
            color: var(--red);
            font-weight: 600;
        }

        .modal-footer {
            padding: 1rem 1.5rem;
            border-top: 1px solid var(--grey);
            display: flex;
            justify-content: flex-end;
            background: var(--light);
            border-bottom-left-radius: 0.75rem;
            border-bottom-right-radius: 0.75rem;
        }

        .modal-footer .close-btn {
            background: linear-gradient(135deg, #dc3545, #c82333);
            color: white;
            padding: 0.75rem 1.5rem;
            border: none;
            border-radius: 0.375rem;
            font-size: clamp(1.1rem, 3vw, 1.2rem);
            font-weight: 500;
            cursor: pointer;
            transition: background-color 0.2s ease;
        }

        .modal-footer .close-btn:hover {
            background: linear-gradient(135deg, #c82333, #b21f2d);
        }

        .take-btn, .submit-btn {
            background: linear-gradient(135deg, #28a745, #218838);
            color: white;
            padding: 0.5rem 1rem;
            border-radius: 0.375rem;
            text-decoration: none;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            transition: background-color 0.3s ease;
            margin: 0.25rem;
        }

        .take-btn:hover, .submit-btn:hover {
            background: linear-gradient(135deg, #218838, #1e7e34);
        }

        .success-notification, .error-notification {
            background: #10b981;
            color: white;
            padding: 1rem;
            margin: 1.5rem;
            border-radius: 0.375rem;
            text-align: center;
            font-weight: 600;
        }

        .error-notification {
            background: linear-gradient(135deg, #dc3545, #c82333);
        }

        .legend-container {
            margin: 1.5rem;
            padding: 1rem;
            background: var(--light);
            border-radius: 0.75rem;
            border: 1px solid var(--grey);
        }

        .legend-container h3 {
            font-size: clamp(1.5rem, 3vw, 2.5rem);
            color: var(--dark);
            margin-bottom: 1rem;
        }

        .legend-item {
            display: flex;
            align-items: center;
            margin-bottom: 0.5rem;
            font-size: clamp(1.1rem, 3vw, 1.2rem);
            color: var(--dark);
        }

        .legend-color {
            width: 20px;
            height: 20px;
            margin-right: 0.5rem;
            border-radius: 0.25rem;
        }

        @media screen and (max-width: 768px) {
            .calendar-container {
                padding: 0;
            }
            .calendar-header {
                padding-top: 10px;
                display: flex;
                flex-direction: column;
            }
        }

        @media screen and (max-width: 480px) {
            .calendar-container {
                padding: 0;
            }
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
            <li class="active">
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
                    <h1>Academic Calendar</h1>
                    <ul class="breadcrumb">
                        <li><a href="./studentDash.php">Home</a></li>
                        <li><i class='bx bx-chevron-right'></i></li>
                        <li><a class="active" href="./calendar.php">Academic Calendar</a></li>
                    </ul>
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

            <div class="calendar-container">
                <div class="calendar-header">
                    <h2><?php echo $monthName . ' ' . $year; ?></h2>
                    <div class="calendar-nav">
                        <a href="?month=<?php echo $month == 1 ? 12 : $month - 1; ?>&year=<?php echo $month == 1 ? $year - 1 : $year; ?>">« Previous</a>
                        <a href="?month=<?php echo $month == 12 ? 1 : $month + 1; ?>&year=<?php echo $month == 12 ? $year + 1 : $year; ?>">Next »</a>
                    </div>
                </div>
                <table class="calendar-table">
                    <thead>
                        <tr>
                            <th>Sun</th>
                            <th>Mon</th>
                            <th>Tue</th>
                            <th>Wed</th>
                            <th>Thu</th>
                            <th>Fri</th>
                            <th>Sat</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php
                    $currentDay = 1;
                    while ($currentDay <= $daysInMonth) {
                        echo '<tr>';
                        for ($i = 0; $i < 7; $i++) {
                            if (($currentDay == 1 && $i < $dayOfWeek) || $currentDay > $daysInMonth) {
                                echo '<td class="empty"></td>';
                            } else {
                                $currentDate = sprintf('%04d-%02d-%02d', $year, $month, $currentDay);
                                $hasItems = isset($itemsByDate[$currentDate]);
                                $class = '';

                                if ($hasItems) {
                                    $hasQuizzesOrAssignments = false;
                                    foreach ($itemsByDate[$currentDate] as $item) {
                                        if (in_array($item['type'], ['quiz', 'assignment'])) {
                                            $hasQuizzesOrAssignments = true;
                                            break;
                                        }
                                    }
                                    $class = $hasQuizzesOrAssignments
                                        ? ($dateSubmissionStatus[$currentDate] ? 'has-items' : 'has-items-not-submitted')
                                        : '';
                                }

                                echo '<td class="' . $class . '" ' . ($hasItems ? 'onclick="openModal(\'item-modal-' . $currentDate . '\')"' : '') . '>';
                                echo $currentDay;

                                if ($hasItems) {
                                    // Items list inside cell
                                    echo '<ul class="item-list">';
                                    foreach ($itemsByDate[$currentDate] as $item) {
                                        $itemClass = $item['type'] === 'holiday' || $item['type'] === 'event'
                                            ? $item['type']
                                            : ($item['type'] . ((isset($item['hasSubmitted']) && $item['hasSubmitted']) ? '-submitted' : '-not-submitted'));
                                        echo '<li class="' . $itemClass . '">' . htmlspecialchars($item['title']) . '</li>';
                                    }
                                    echo '</ul>';
                                }

                                echo '</td>';
                                $currentDay++;
                            }
                        }
                        echo '</tr>';
                    }
                    ?>
                    </tbody>
                </table>
            </div>

            <div class="legend-container">
                <h3>Legend</h3>
                <div class="legend-item">
                    <div class="legend-color" style="background: linear-gradient(135deg, #f97316, #d97706);"></div>
                    <span>Holiday</span>
                </div>
                <div class="legend-item">
                    <div class="legend-color" style="background: linear-gradient(135deg, #eab308, #ca8a04);"></div>
                    <span>Event</span>
                </div>
                <div class="legend-item">
                    <div class="legend-color" style="background: linear-gradient(135deg, #28a745, #218838);"></div>
                    <span>Submitted Quiz/Assignment</span>
                </div>
                <div class="legend-item">
                    <div class="legend-color" style="background: linear-gradient(135deg, #dc3545, #c82333);"></div>
                    <span>Not Submitted Quiz/Assignment</span>
                </div>
            </div>

            <!-- Item Modals for Each Date -->
            <?php foreach ($itemsByDate as $date => $dateItems): ?>
                <div id="item-modal-<?php echo $date; ?>" class="modal" role="dialog" aria-labelledby="modal-title-<?php echo $date; ?>">
                    <div class="modal-content-calendar">
                        <div class="modal-header">
                            <h3 id="modal-title-<?php echo $date; ?>">Items on <?php echo date('F j, Y', strtotime($date)); ?></h3>
                            <button class="close-btn" onclick="closeModal('item-modal-<?php echo $date; ?>')" aria-label="Close modal">
                                <i class='bx bx-x'></i>
                            </button>
                        </div>
                        <div class="modal-body">
                            <?php foreach ($dateItems as $item): ?>
                                <div class="item-info">
                                    <p><strong><?php echo htmlspecialchars($item['title']); ?> (<?php echo ucfirst($item['type']); ?>)</strong></p>
                                    <?php if (in_array($item['type'], ['quiz', 'assignment'])): ?>
                                        <p><?php echo htmlspecialchars($item['subjectName'] . ' (' . $item['sectionName'] . ')'); ?></p>
                                        <p>Due: <?php echo date('F j, Y, g:i A', strtotime($item['dueDate'])); ?></p>
                                        <p>Status: <span class="<?php echo (isset($item['hasSubmitted']) && $item['hasSubmitted']) ? 'status-submitted' : 'status-not-submitted'; ?>">
                                            <?php echo (isset($item['hasSubmitted']) && $item['hasSubmitted']) ? 'Submitted' : 'Not Submitted'; ?>
                                        </span></p>
                                        <?php if (!(isset($item['hasSubmitted']) && $item['hasSubmitted'])): ?>
                                            <?php if ($item['type'] === 'quiz'): ?>
                                                <a href="studentQuiz.php?teacherSectionID=<?php echo $item['teacherSectionID']; ?>&quizID=<?php echo $item['quizID']; ?>" class="take-btn">
                                                    <i class='bx bx-pencil'></i> Take Quiz
                                                </a>
                                            <?php else: ?>
                                                <a href="studentAssignment.php?teacherSectionID=<?php echo $item['teacherSectionID']; ?>" class="submit-btn">
                                                    <i class='bx bx-upload'></i> Submit Assignment
                                                </a>
                                            <?php endif; ?>
                                        <?php endif; ?>
                                    <?php else: ?>
                                        <p>Description: <?php echo htmlspecialchars($item['description'] ?? 'No description'); ?></p>
                                        <p>Date: <?php echo date('F j, Y', strtotime($item['eventDate'])); ?></p>
                                    <?php endif; ?>
                                </div>
                            <?php endforeach; ?>
                        </div>
                        <div class="modal-footer">
                            <button class="close-btn" onclick="closeModal('item-modal-<?php echo $date; ?>')">Close</button>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </main>
    </section>

    <script>
        function openModal(modalId) {
            const modal = document.getElementById(modalId);
            modal.style.display = 'flex';
        }

        function closeModal(modalId) {
            const modal = document.getElementById(modalId);
            modal.style.display = 'none';
        }

        window.onclick = function(event) {
            if (event.target.classList.contains('modal')) {
                closeModal(event.target.id);
            }
        }

    </script>
    <script src="./utils/script.js"></script>
</body>
</html>