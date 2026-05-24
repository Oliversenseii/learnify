<?php
require_once './sessions/session_admin.php';
require_once '../../config/db_connection.php';
require_once './auto_logout.php';

// Set timezone to Asia/Manila and verify
date_default_timezone_set('Asia/Manila');
$timezone = date_default_timezone_get();
if ($timezone !== 'Asia/Manila') {
    error_log("Warning: Timezone set to $timezone instead of Asia/Manila");
}
// Debug: Output current date and timezone for verification
// echo "Current Date: " . date('Y-m-d H:i:s T') . "<br>";

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
        header("Location: ../../index.php");
        exit;
    }

    // Fetch academic calendar events
    $eventStmt = $dbConnection->prepare("
        SELECT eventID, title, description, eventDate, eventType
        FROM academic_events
        WHERE archived = 0
        ORDER BY eventDate
    ");
    $eventStmt->execute();
    $events = $eventStmt->fetchAll(PDO::FETCH_ASSOC);

    // Organize events by date
    $eventsByDate = [];
    foreach ($events as $event) {
        if ($event['eventDate']) {
            $date = date('Y-m-d', strtotime($event['eventDate']));
            $event['type'] = $event['eventType'];
            $eventsByDate[$date][] = $event;
        }
    }

} catch (PDOException $e) {
    error_log("Database Error: " . $e->getMessage());
    $_SESSION['error_message'] = "Database error: " . htmlspecialchars($e->getMessage());
}

// Handle event creation
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_event'])) {
    $title = trim($_POST['title'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $eventDate = $_POST['event_date'] ?? '';
    $eventType = $_POST['event_type'] ?? '';

    $errors = [];
    if (empty($title)) {
        $errors[] = "Event title is required.";
    }
    if (empty($eventDate)) {
        $errors[] = "Event date is required.";
    } elseif (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $eventDate)) {
        $errors[] = "Invalid date format.";
    }
    if (empty($eventType)) {
        $errors[] = "Event type is required.";
    }

    if (empty($errors)) {
        try {
            $userCheck = $dbConnection->prepare("SELECT userID FROM users WHERE userID = :userID");
            $userCheck->bindParam(':userID', $_SESSION['userID'], PDO::PARAM_INT);
            $userCheck->execute();
            if ($userCheck->rowCount() == 0) {
                $_SESSION['error_message'] = "User does not exist in the database. Please contact support.";
                header("Location: admin_calendar.php?month=" . (int)$_GET['month'] . "&year=" . (int)$_GET['year']);
                exit;
            }

            $stmt = $dbConnection->prepare("
                INSERT INTO academic_events (title, description, eventDate, eventType, created_by)
                VALUES (:title, :description, :eventDate, :eventType, :created_by)
            ");
            $stmt->bindParam(':title', $title, PDO::PARAM_STR);
            $stmt->bindParam(':description', $description, PDO::PARAM_STR);
            $stmt->bindParam(':eventDate', $eventDate, PDO::PARAM_STR);
            $stmt->bindParam(':eventType', $eventType, PDO::PARAM_STR);
            $stmt->bindParam(':created_by', $_SESSION['userID'], PDO::PARAM_INT);
            $stmt->execute();
            $_SESSION['success_message'] = "Event added successfully.";
            header("Location: admin_calendar.php?month=" . (int)$_GET['month'] . "&year=" . (int)$_GET['year']);
            exit;
        } catch (PDOException $e) {
            $errors[] = "Error adding event: " . htmlspecialchars($e->getMessage());
        }
    }

    if (!empty($errors)) {
        $_SESSION['error_message'] = implode(" ", $errors);
        header("Location: admin_calendar.php?month=" . (int)$_GET['month'] . "&year=" . (int)$_GET['year'] . "&show_add_modal=true");
        exit;
    }
}

// Handle event update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_event'])) {
    $eventID = filter_var($_POST['eventID'], FILTER_VALIDATE_INT);
    $title = trim($_POST['title'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $eventDate = $_POST['event_date'] ?? '';
    $eventType = $_POST['event_type'] ?? '';

    $errors = [];
    if (empty($title)) {
        $errors[] = "Event title is required.";
    }
    if (empty($eventDate)) {
        $errors[] = "Event date is required.";
    } elseif (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $eventDate)) {
        $errors[] = "Invalid date format.";
    }
    if (empty($eventType)) {
        $errors[] = "Event type is required.";
    }
    if (!$eventID) {
        $errors[] = "Invalid event ID.";
    }

    if (empty($errors)) {
        try {
            $stmt = $dbConnection->prepare("
                UPDATE academic_events 
                SET title = :title, description = :description, eventDate = :eventDate, eventType = :eventType
                WHERE eventID = :eventID
            ");
            $stmt->bindParam(':title', $title, PDO::PARAM_STR);
            $stmt->bindParam(':description', $description, PDO::PARAM_STR);
            $stmt->bindParam(':eventDate', $eventDate, PDO::PARAM_STR);
            $stmt->bindParam(':eventType', $eventType, PDO::PARAM_STR);
            $stmt->bindParam(':eventID', $eventID, PDO::PARAM_INT);
            $stmt->execute();
            $_SESSION['success_message'] = "Event updated successfully.";
            header("Location: admin_calendar.php?month=" . (int)$_GET['month'] . "&year=" . (int)$_GET['year']);
            exit;
        } catch (PDOException $e) {
            $errors[] = "Error updating event: " . htmlspecialchars($e->getMessage());
        }
    }

    if (!empty($errors)) {
        $_SESSION['error_message'] = implode(" ", $errors);
        header("Location: admin_calendar.php?month=" . (int)$_GET['month'] . "&year=" . (int)$_GET['year'] . "&show_edit_modal=true&event_id=$eventID");
        exit;
    }
}

// Handle event deletion
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_event'])) {
    $eventID = filter_var($_POST['eventID'], FILTER_VALIDATE_INT);
    try {
        $stmt = $dbConnection->prepare("
            UPDATE academic_events SET archived = 1
            WHERE eventID = :eventID
        ");
        $stmt->bindParam(':eventID', $eventID, PDO::PARAM_INT);
        $stmt->execute();
        $_SESSION['success_message'] = "Event archived successfully.";
        header("Location: admin_calendar.php?month=" . (int)$_GET['month'] . "&year=" . (int)$_GET['year']);
        exit;
    } catch (PDOException $e) {
        $_SESSION['error_message'] = "Error deleting event: " . htmlspecialchars($e->getMessage());
    }
}

// Calendar setup
$month = isset($_GET['month']) ? (int)$_GET['month'] : date('n');
$year = isset($_GET['year']) ? (int)$_GET['year'] : date('Y');

if ($month < 1 || $month > 12) {
    $month = date('n');
}
if ($year < 1970 || $year > 9999) {
    $year = date('Y');
}

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
    <link rel="stylesheet" href="./utils/notification.css">
    <link rel="stylesheet" href="./utils/animation_slide.css">
    <link rel="stylesheet" href="./utils/logo.css">
    <link rel="stylesheet" href="./css/scrollbar.css">
    <script src="./logout.js"></script>
    <title>Learnify - Admin Calendar</title>
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
            margin: 1.5rem;
            padding: 1.5rem;
            background: var(--light);
            border-radius: 0.75rem;
            box-shadow: 0 6px 16px rgba(0, 0, 0, 0.15);
            border: 1px solid var(--grey);
            overflow-x: auto;
        }

        .calendar-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1.5rem;
        }

        .calendar-header h2 {
            font-size: clamp(2rem, 3vw, 2.5rem);
            color: var(--dark);
            font-weight: 600;
        }

        .calendar-nav a {
            text-decoration: none;
            color: var(--blue);
            font-size: clamp(1.1rem, 3vw, 1.5rem);
            padding: 0.5rem 1rem;
            transition: color 0.2s ease, background-color 0.2s ease;
        }

        .calendar-nav a:hover {
            color: white;
            background-color: var(--blue);
            border-radius: 0.25rem;
        }

        .calendar-table {
            width: 100%;
            border-collapse: collapse;
            font-size: clamp(1.4rem, 3vw, 1.5rem);
        }

        .calendar-table th,
        .calendar-table td {
            padding: 1rem;
            text-align: center;
            vertical-align: top;
            border: 2px solid #007bff;
        }

        .calendar-table th {
            background: #007bff;
            color: white;
            font-weight: 600;
        }

        .calendar-table td {
            background: var(--grey);
            color: var(--dark);
            cursor: pointer;
            padding: 10px;
            transition: background-color 0.2s ease;
            position: relative;
            min-height: 100px;
        }

        .calendar-table td:hover {
            background-color: #0056b3;
            color: white;
        } 

        .calendar-table td.empty {
            background: transparent;
            cursor: default;
        }

        .event-list {
            list-style: none;
            padding: 0;
            margin: 0.5rem 0 0;
            text-align: left;
            font-size: clamp(1.1rem, 3vw, 1.2rem);
        }

        .event-list li.holiday {
            background: linear-gradient(135deg, var(--orange), #d97706);
            color: white;
            padding: 0.2rem 0.5rem;
            border-radius: 0.25rem;
            margin: 0 auto;
            width: fit-content;
            margin-bottom: 5px;
        }

        .event-list li.event {
            background: linear-gradient(135deg, var(--yellow), #ca8a04);
            color: white;
            padding: 0.2rem 0.5rem;
            border-radius: 0.25rem;
            margin: 0 auto;
            width: fit-content;
            margin-bottom: 5px;
        }

        .modal {
            z-index: 1000000;
        }

        .modal-content-calendar {
            background: var(--light);
            border-radius: 0.75rem;
            max-width: 90%;
            width: 600px;
            max-height: 80vh;
            overflow-y: auto;
            box-shadow: 0 8px 24px rgba(0, 0, 0, 0.2);
            position: relative;
            animation: slideIn 0.3s ease-in-out;
        }

        .modal-content-dash {
            background: var(--light);
            border-radius: 0.75rem;
            max-width: 90%;
            width: 600px;
            max-height: 80vh;
            overflow-y: auto;
            box-shadow: 0 8px 24px rgba(0, 0, 0, 0.2);
            position: relative;
            animation: slideIn 0.3s ease-in-out;
        }

        .modal-header {
            background: linear-gradient(135deg, #007bff, #0056b3);
            padding: 1.5rem;
            border-top-left-radius: 0.75rem;
            border-top-right-radius: 0.75rem;
            color: white;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .modal-header h3 {
            margin: 0;
            font-weight: 600;
            font-size: clamp(2.4rem, 3vw, 2.5rem);
        }
        
        .modal-header .close-btn {
            background: transparent;
            border: none;
            font-size: clamp(2.4rem, 3vw, 2.5rem);
            color: white;
            cursor: pointer;
            transition: color 0.2s ease;
        }

        .modal-header .close-btn:hover {
            color: var(--red);
        }

        .modal-body {
            padding: 1.5rem;
        }

        .event-info {
            margin-bottom: 1rem;
            padding: 1rem;
            background: var(--grey);
            border-radius: 0.5rem;
        }

        .event-info p {
            margin: 0.5rem 0;
            font-size: clamp(1.4rem, 3vw, 1.5rem);
            color: var(--dark);
            white-space: pre-wrap;
        }

        .event-info p:nth-child(1) {
            font-size: clamp(1.9rem, 3vw, 2rem) !important;
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
        }

        .modal-footer .close-btn:hover {
            background: linear-gradient(135deg, #c82333, #b21f2d);
        }

        .delete-btn, .edit-btn {
            background: linear-gradient(135deg, #dc3545, #c82333);
            color: white;
            padding: 0.5rem 1rem;
            font-size: clamp(1.1rem, 3vw, 1.2rem);
            border-radius: 0.375rem;
            border: none;
            cursor: pointer;
            margin: 0.25rem;
        }

        .edit-btn {
            background: linear-gradient(135deg, #ffc107, #e0a800);
            color: #1f2937;
        }

        .delete-btn:hover {
            background: linear-gradient(135deg, #c82333, #b21f2d);
        }

        .edit-btn:hover {
            background: linear-gradient(135deg, #e0a800, #ca8a04);
        }

        .add-event-btn {
            margin: 1.5rem 1.5rem 1.5rem auto;
            padding: 0.75rem 1.5rem;
            background: linear-gradient(135deg, #28a745, #218838);
            color: white;
            border: none;
            border-radius: 0.375rem;
            font-size: clamp(1.1rem, 3vw, 1.2rem);
            display: flex;
            align-items: center;
            gap: 0.5rem;
            cursor: pointer;
        }

        .add-event-btn:hover {
            background: linear-gradient(135deg, #218838, #1e7e34);
        }

        .add-event-form label {
            display: block;
            margin-bottom: 0.5rem;
            font-weight: 600;
            color: var(--dark);
            text-align: left;
            font-size: clamp(1.1rem, 3vw, 1.2rem);
        }

        .add-event-form label span {
            color: #c82333;
        }

        .add-event-form input,
        .add-event-form textarea,
        .add-event-form select {
            width: 100%;
            padding: 0.75rem;
            margin-bottom: 1rem;
            border: 1px solid #ccc;
            border-radius: 0.375rem;
            font-size: clamp(1.1rem, 3vw, 1.2rem);
            color: var(--dark);
            background: var(--light);
        }

        .add-event-form input:focus,
        .add-event-form textarea:focus,
        .add-event-form select:focus {
            border-color: var(--blue);
            outline: none;
            box-shadow: 0 0 5px rgba(0, 123, 255, 0.5);
        }

        .add-event-form textarea {
            resize: vertical;
            min-height: 100px;
        }

        .add-event-form button {
            background: linear-gradient(135deg, #28a745, #218838);
            color: white;
            padding: 0.75rem 1.5rem;
            border: none;
            border-radius: 0.375rem;
            font-size: clamp(1.1rem, 3vw, 1.2rem);
            cursor: pointer;
        }

        .add-event-form button:hover {
            background: linear-gradient(135deg, #218838, #1e7e34);
        }

        .success-notification, .error-notification {
            background: #10b981;
            color: white;
            padding: 1rem;
            margin: 1.5rem;
            border-radius: 0.375rem;
            text-align: center;
            font-size: clamp(1.1rem, 3vw, 1.2rem);
            font-weight: 600;
        }

        .error-notification {
            background-color: var(--red);
        }

        #event_date::-webkit-calendar-picker-indicator,
        #edit_event_date::-webkit-calendar-picker-indicator {
            background-color: #FFCE26;
            padding: 6px;
            border-radius: 50%;
            cursor: pointer;
        }

        #event_date::-webkit-calendar-picker-indicator:hover,
        #edit_event_date::-webkit-calendar-picker-indicator:hover {
            background-color: #FFA500; 
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
                    <i class='bx bxs-dashboard' ></i>
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
            <li>
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
            <li class="active">
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
                    <i class='bx bxs-cog' ></i>
                    <span class="text">Settings</span>
                </a>
            </li>
            <li>
                <a href="javascript:void(0);" class="logout" onclick="showLogoutModal()">
                    <i class='bx bxs-log-out-circle' ></i>
                    <span class="text">Logout</span>
                </a>
            </li>
        </ul>
    </section>
    <!-- SIDEBAR -->

    <?php require_once './view/modal.php' ?>

    <section id="content">
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
                        <li><a href="./admin_main_dash.php">Home</a></li>
                        <li><i class='bx bx-chevron-right'></i></li>
                        <li><a class="active" href="#">Calendar</a></li>
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
                        <a href="?month=<?php echo $month == 1 ? 12 : $month - 1; ?>&year=<?php echo $month == 1 ? $year - 1 : $year; ?>">⏮ Previous</a>
                        <a href="?month=<?php echo $month == 12 ? 1 : $month + 1; ?>&year=<?php echo $month == 12 ? $year + 1 : $year; ?>">Next ⏭</a>
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
                        $dayCount = 1;
                        $currentDay = 1;
                        while ($currentDay <= $daysInMonth) {
                            echo '<tr>';
                            for ($i = 0; $i < 7; $i++) {
                                if (($currentDay == 1 && $i < $dayOfWeek) || $currentDay > $daysInMonth) {
                                    echo '<td class="empty"></td>';
                                } else {
                                    $currentDate = sprintf('%04d-%02d-%02d', $year, $month, $currentDay);
                                    $hasEvents = isset($eventsByDate[$currentDate]);
                                    echo '<td onclick="' . ($hasEvents ? "openModal('event-modal-$currentDate')" : "openAddEventModalWithDate('$currentDate')") . '">';
                                    echo $currentDay;
                                    if ($hasEvents) {
                                        echo '<ul class="event-list">';
                                        foreach ($eventsByDate[$currentDate] as $event) {
                                            $eventClass = $event['eventType'] === 'Holiday' ? 'holiday' : 'event';
                                            echo '<li class="' . $eventClass . '">' . htmlspecialchars($event['title']) . '</li>';
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
                <button class="add-event-btn" onclick="openModal('add-event-modal')">Add New Event</button>
            </div>

            <!-- Add Event Modal -->
            <div id="add-event-modal" class="modal" role="dialog" aria-labelledby="add-event-title">
                <div class="modal-content-calendar">
                    <div class="modal-header">
                        <h3 id="add-event-title">Add New Event</h3>
                        <button class="close-btn" onclick="closeModal('add-event-modal')" aria-label="Close modal">
                            <i class='bx bx-x'></i>
                        </button>
                    </div>
                    <div class="modal-body">
                        <form method="POST" class="add-event-form">
                            <label for="title">Title <span>*</span></label>
                            <input type="text" id="title" name="title" placeholder="Enter event title (e.g., Christmas Holiday)" required>
                            <label for="description">Description <span>(Optional)</span></label>
                            <textarea id="description" name="description" placeholder="Enter description (optional)"></textarea>
                            <label for="event_date">Event Date <span>*</span></label>
                            <input type="date" id="event_date" name="event_date" required>
                            <label for="event_type">Event Type <span>*</span></label>
                            <select id="event_type" name="event_type" required>
                                <option value="">- Select Type -</option>
                                <option value="Holiday">Holiday</option>
                                <option value="Event">Event</option>
                            </select>
                            <button type="submit" name="add_event">Add Event</button>
                        </form>
                    </div>
                    <div class="modal-footer">
                        <button class="close-btn" onclick="closeModal('add-event-modal')">Close</button>
                    </div>
                </div>
            </div>

            <!-- Edit Event Modal -->
            <div id="edit-event-modal" class="modal" role="dialog" aria-labelledby="edit-event-title">
                <div class="modal-content-calendar">
                    <div class="modal-header">
                        <h3 id="edit-event-title">Edit Event</h3>
                        <button class="close-btn" onclick="closeModal('edit-event-modal')" aria-label="Close modal">
                            <i class='bx bx-x'></i>
                        </button>
                    </div>
                    <div class="modal-body">
                        <form method="POST" class="add-event-form">
                            <input type="hidden" id="edit_event_id" name="eventID">
                            <label for="edit_title">Title</label>
                            <input type="text" id="edit_title" name="title" placeholder="Enter event title" required>
                            <label for="edit_description">Description</label>
                            <textarea id="edit_description" name="description" placeholder="Enter description (optional)"></textarea>
                            <label for="edit_event_date">Event Date</label>
                            <input type="date" id="edit_event_date" name="event_date" required>
                            <label for="edit_event_type">Event Type</label>
                            <select id="edit_event_type" name="event_type" required>
                                <option value="">Select Type</option>
                                <option value="Holiday">Holiday</option>
                                <option value="Event">Event</option>
                            </select>
                            <button type="submit" name="update_event">Update Event</button>
                        </form>
                    </div>
                    <div class="modal-footer">
                        <button class="close-btn" onclick="closeModal('edit-event-modal')">Close</button>
                    </div>
                </div>
            </div>

            <!-- Event Modals for Each Date -->
            <?php foreach ($eventsByDate as $date => $dateEvents): ?>
                <div id="event-modal-<?php echo $date; ?>" class="modal" role="dialog" aria-labelledby="modal-title-<?php echo $date; ?>">
                    <div class="modal-content-dash">
                        <div class="modal-header">
                            <h3 id="modal-title-<?php echo $date; ?>">Events on <?php echo date('F j, Y', strtotime($date)); ?></h3>
                            <button class="close-btn" onclick="closeModal('event-modal-<?php echo $date; ?>')" aria-label="Close modal">
                                <i class='bx bx-x'></i>
                            </button>
                        </div>
                        <div class="modal-body">
                            <?php foreach ($dateEvents as $event): ?>
                                <div class="event-info">
                                    <p><strong><?php echo htmlspecialchars($event['title']); ?> (<?php echo htmlspecialchars($event['eventType']); ?>)</strong></p>
                                    <p><?php echo htmlspecialchars($event['description'] ?? 'No description'); ?></p>
                                    <div>
                                        <button class="edit-btn" onclick="openEditEventModal(<?php echo $event['eventID']; ?>, '<?php echo htmlspecialchars($event['title'], ENT_QUOTES, 'UTF-8'); ?>', '<?php echo htmlspecialchars($event['description'] ?? '', ENT_QUOTES, 'UTF-8'); ?>', '<?php echo $event['eventDate']; ?>', '<?php echo $event['eventType']; ?>')">
                                            <i class='bx bx-edit'></i> Edit
                                        </button>
                                        <form method="POST" style="display: inline;">
                                            <input type="hidden" name="eventID" value="<?php echo $event['eventID']; ?>">
                                            <button type="submit" name="delete_event" class="delete-btn">
                                                <i class='bx bx-trash'></i> Archive
                                            </button>
                                        </form>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                        <div class="modal-footer">
                            <button class="close-btn" onclick="closeModal('event-modal-<?php echo $date; ?>')">Close</button>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </main>
    </section>

    <script>
        let selectedDate = null;

        function closeAllModals() {
            const modals = document.querySelectorAll('.modal');
            modals.forEach(modal => {
                modal.style.display = 'none';
                if (modal.id === 'add-event-modal') {
                    modal.querySelector('form').reset();
                    selectedDate = null;
                }
                if (modal.id === 'edit-event-modal') {
                    modal.querySelector('form').reset();
                }
            });
        }

        function openModal(modalId) {
            closeAllModals();
            const modal = document.getElementById(modalId);
            modal.style.display = 'flex';
        }

        function openAddEventModalWithDate(date) {
            closeAllModals();
            selectedDate = date;
            const modal = document.getElementById('add-event-modal');
            modal.style.display = 'flex';
            document.getElementById('event_date').value = date;
        }

        function openEditEventModal(eventID, title, description, date, type) {
            closeAllModals();
            const modal = document.getElementById('edit-event-modal');
            document.getElementById('edit_event_id').value = eventID;
            document.getElementById('edit_title').value = title;
            document.getElementById('edit_description').value = description;
            document.getElementById('edit_event_date').value = date;
            document.getElementById('edit_event_type').value = type;
            modal.style.display = 'flex';
        }

        function closeModal(modalId) {
            const modal = document.getElementById(modalId);
            modal.style.display = 'none';
            if (modalId === 'add-event-modal') {
                document.getElementById('add-event-modal').querySelector('form').reset();
                selectedDate = null;
            }
            if (modalId === 'edit-event-modal') {
                document.getElementById('edit-event-modal').querySelector('form').reset();
            }
        }

        window.onclick = function(event) {
            if (event.target.classList.contains('modal')) {
                closeModal(event.target.id);
            }
        }

        document.getElementById('switch-mode').addEventListener('change', function() {
            document.body.classList.toggle('dark-mode');
            localStorage.setItem('darkMode', this.checked ? 'enabled' : 'disabled');
        });

        if (localStorage.getItem('darkMode') === 'enabled') {
            document.body.classList.add('dark-mode');
            document.getElementById('switch-mode').checked = true;
        }

        <?php if (isset($_GET['show_add_modal']) && $_GET['show_add_modal'] === 'true'): ?>
            window.onload = function() {
                openModal('add-event-modal');
            };
        <?php endif; ?>

        <?php if (isset($_GET['show_edit_modal']) && $_GET['show_edit_modal'] === 'true' && isset($_GET['event_id'])): ?>
            window.onload = function() {
                <?php
                $eventID = filter_var($_GET['event_id'], FILTER_VALIDATE_INT);
                if ($eventID) {
                    $stmt = $dbConnection->prepare("SELECT title, description, eventDate, eventType FROM academic_events WHERE eventID = :eventID AND archived = 0");
                    $stmt->bindParam(':eventID', $eventID, PDO::PARAM_INT);
                    $stmt->execute();
                    $event = $stmt->fetch(PDO::FETCH_ASSOC);
                    if ($event) {
                        echo "openEditEventModal($eventID, '" . htmlspecialchars($event['title'], ENT_QUOTES, 'UTF-8') . "', '" . htmlspecialchars($event['description'] ?? '', ENT_QUOTES, 'UTF-8') . "', '" . $event['eventDate'] . "', '" . $event['eventType'] . "');";
                    }
                }
                ?>
            };
        <?php endif; ?>
    </script>
    <script src="./utils/script.js"></script>
</body>
</html>
