<?php
require_once './sessions/session_professor.php';
require_once '../../config/db_connection.php';
require_once './auto_logout.php';
require_once './check_status.php';
require_once './academic_events.php';

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

    // Fetch to-do items for the user
    $todoStmt = $dbConnection->prepare("
        SELECT todoID, title, description, dueDate, status
        FROM todos
        WHERE userID = :userID AND archived = 0
        ORDER BY dueDate
    ");
    $todoStmt->bindParam(':userID', $userID, PDO::PARAM_INT);
    $todoStmt->execute();
    $todos = $todoStmt->fetchAll(PDO::FETCH_ASSOC);

    // Fetch academic events
    $eventStmt = $dbConnection->prepare("
        SELECT eventID, title, description, eventDate, eventType
        FROM academic_events
        WHERE archived = 0
        ORDER BY eventDate
    ");
    $eventStmt->execute();
    $events = $eventStmt->fetchAll(PDO::FETCH_ASSOC);

    // Organize to-do items and academic events by date
    $itemsByDate = [];
    foreach ($todos as $todo) {
        if ($todo['dueDate']) {
            $date = date('Y-m-d', strtotime($todo['dueDate']));
            $todo['type'] = 'todo';
            $itemsByDate[$date][] = $todo;
        }
    }
    foreach ($events as $event) {
        if ($event['eventDate']) {
            $date = date('Y-m-d', strtotime($event['eventDate']));
            $event['type'] = $event['eventType'] === 'Holiday' ? 'holiday' : 'event';
            $itemsByDate[$date][] = $event;
        }
    }

    // Determine completion status for each date (for to-dos only)
    $dateCompletionStatus = [];
    foreach ($itemsByDate as $date => $items) {
        $hasPending = false;
        foreach ($items as $item) {
            if ($item['type'] === 'todo' && $item['status'] === 'Pending') {
                $hasPending = true;
                break;
            }
        }
        $dateCompletionStatus[$date] = !$hasPending;
    }

} catch (PDOException $e) {
    error_log("Database Error: " . $e->getMessage() . " in " . $e->getFile() . " on line " . $e->getLine());
    $_SESSION['error_message'] = "Database error: " . htmlspecialchars($e->getMessage());
}

// Handle to-do creation
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_todo'])) {
    $titles = $_POST['title'] ?? [];
    $descriptions = $_POST['description'] ?? [];
    $dueDates = $_POST['due_date'] ?? [];
    $errors = [];

    // Validate each to-do entry
    foreach ($titles as $index => $title) {
        $title = trim($title);
        $description = trim($descriptions[$index] ?? '');
        $dueDate = $dueDates[$index] ?? '';

        if (empty($title)) {
            $errors[] = "Title for to-do item " . ($index + 1) . " is required.";
        }
        if (empty($dueDate)) {
            $errors[] = "Due date for to-do item " . ($index + 1) . " is required.";
        } elseif (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $dueDate)) {
            $errors[] = "Invalid due date format for to-do item " . ($index + 1) . ".";
        }

        if (empty($errors)) {
            try {
                $stmt = $dbConnection->prepare("
                    INSERT INTO todos (userID, title, description, dueDate, status)
                    VALUES (:userID, :title, :description, :dueDate, 'Pending')
                ");
                $stmt->bindParam(':userID', $userID, PDO::PARAM_INT);
                $stmt->bindParam(':title', $title, PDO::PARAM_STR);
                $stmt->bindParam(':description', $description, PDO::PARAM_STR);
                $stmt->bindParam(':dueDate', $dueDate, PDO::PARAM_STR);
                $stmt->execute();
            } catch (PDOException $e) {
                $errors[] = "Error adding to-do item " . ($index + 1) . ": " . htmlspecialchars($e->getMessage());
            }
        }
    }

    // Redirect to calendar.php with month and year
    $redirectUrl = "calendar.php?month=" . (int)$_GET['month'] . "&year=" . (int)$_GET['year'];
    if (empty($errors)) {
        $_SESSION['success_message'] = "To-do item(s) added successfully.";
    } else {
        $_SESSION['error_message'] = implode(" ", $errors);
        $redirectUrl .= "&show_add_modal=true";
    }
    header("Location: $redirectUrl");
    exit;
}

// Handle to-do status update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_status'])) {
    $todoID = filter_var($_POST['todoID'], FILTER_VALIDATE_INT);
    $status = $_POST['status'] === 'Completed' ? 'Completed' : 'Pending';
    try {
        $stmt = $dbConnection->prepare("
            UPDATE todos SET status = :status, updatedDate = CURRENT_TIMESTAMP
            WHERE todoID = :todoID AND userID = :userID
        ");
        $stmt->bindParam(':status', $status, PDO::PARAM_STR);
        $stmt->bindParam(':todoID', $todoID, PDO::PARAM_INT);
        $stmt->bindParam(':userID', $userID, PDO::PARAM_INT);
        $stmt->execute();
        $_SESSION['success_message'] = "To-do status updated successfully.";
        header("Location: calendar.php?month=" . (int)$_GET['month'] . "&year=" . (int)$_GET['year']);
        exit;
    } catch (PDOException $e) {
        $_SESSION['error_message'] = "Error updating to-do: " . htmlspecialchars($e->getMessage());
    }
}

// Handle to-do deletion
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_todo'])) {
    $todoID = filter_var($_POST['todoID'], FILTER_VALIDATE_INT);
    try {
        $stmt = $dbConnection->prepare("
            UPDATE todos SET archived = 1
            WHERE todoID = :todoID AND userID = :userID
        ");
        $stmt->bindParam(':todoID', $todoID, PDO::PARAM_INT);
        $stmt->bindParam(':userID', $userID, PDO::PARAM_INT);
        $stmt->execute();
        $_SESSION['success_message'] = "To-do item archived successfully.";
        header("Location: calendar.php?month=" . (int)$_GET['month'] . "&year=" . (int)$_GET['year']);
        exit;
    } catch (PDOException $e) {
        $_SESSION['error_message'] = "Error deleting to-do: " . htmlspecialchars($e->getMessage());
    }
}

// Calendar setup with validation
$month = isset($_GET['month']) ? (int)$_GET['month'] : date('n');
$year = isset($_GET['year']) ? (int)$_GET['year'] : date('Y');

// Validate month and year
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
        }

        .calendar-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1.5rem;
        }

        .calendar-header h2 {
            font-size: clamp(1.2rem, 3vw, 2.5rem);
            color: var(--dark);
            font-weight: 600;
        }

        .calendar-nav a {
            text-decoration: none;
            color: var(--blue);
            font-size: clamp(1rem, 3vw, 1.6rem);
            padding: 0.5rem 1rem;
            transition: color 0.2s ease, background-color 0.2s ease;
        }

        .calendar-nav a:hover {
            color: #2563eb;
            background-color: var(--grey);
            border-radius: 0.25rem;
        }

        .calendar-table {
            width: 100%;
            border-collapse: collapse;
            font-size: clamp(1rem, 3vw, 1.5rem);
        }

        .calendar-table th,
        .calendar-table td {
            padding: 0.75rem;
            border: 2px solid #007bff;
            text-align: center;
            vertical-align: top;
        }

        .calendar-table th {
            background: #007bff;
            color: white;
            font-weight: 600;
        }

        .calendar-table td:hover {
            background-color: #0056b3;
            color: white;
        }

        .calendar-table td {
            background-color: var(--grey);
            color: var(--dark);
            cursor: pointer;
            padding: 10px;
            transition: background-color 0.2s ease;
            position: relative;
            min-height: 100px;
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
            font-size: clamp(1rem, 3vw, 1.2rem);
        }

        .item-list li {
            margin-bottom: 0.3rem;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
            cursor: pointer;
        }

        .item-list li.completed {
            background: linear-gradient(135deg, #28a745, #218838);
            color: white;
            padding: 0.2rem 0.5rem;
            border-radius: 0.25rem;
            margin: 0 auto;
            width: fit-content;
            margin-bottom: 5px;
        }

        .item-list li.pending {
            background: linear-gradient(135deg, #dc3545, #c82333);
            color: white;
            padding: 0.2rem 0.5rem;
            border-radius: 0.25rem;
            margin: 0 auto;
            width: fit-content;
            margin-bottom: 5px;
        }

        .item-list li.holiday {
            background: linear-gradient(135deg, #f97316, #d97706);
            color: white;
            padding: 0.2rem 0.5rem;
            border-radius: 0.25rem;
            margin: 0 auto;
            width: fit-content;
            margin-bottom: 5px;
        }

        .item-list li.event {
            background: linear-gradient(135deg, #eab308, #ca8a04);
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

        @keyframes fadeIn {
            from { opacity: 0; }
            to { opacity: 1; }
        }

        .modal-content {
            background: var(--light);
            border-radius: 0.75rem;
            max-width: 90%;
            width: 600px;
            max-height: 80vh;
            overflow-y: auto;
            box-shadow: 0 8px 24px rgba(0, 0, 0, 0.2);
            position: relative;
            animation: slideIn 0.3s ease-in-out;
            transition: all 0.3s ease-in-out;
        }

        .modal-content.fullscreen {
            width: 100%;
            height: 100%;
            max-width: none;
            max-height: none;
            border-radius: 0;
            margin: 0;
            animation: expandFullscreen 0.5s cubic-bezier(0.4, 0, 0.2, 1);
        }

        @keyframes slideIn {
            from { transform: translateY(-20px); opacity: 0; }
            to { transform: translateY(0); opacity: 1; }
        }

        @keyframes expandFullscreen {
            from {
                width: 600px;
                height: 80vh;
                border-radius: 0.75rem;
                margin: auto;
                transform: scale(0.8);
            }
            to {
                width: 100%;
                height: 100%;
                border-radius: 0;
                margin: 0;
                transform: scale(1);
            }
        }

        @keyframes collapseFullscreen {
            from {
                width: 100%;
                height: 100%;
                border-radius: 0;
                margin: 0;
                transform: scale(1);
            }
            to {
                width: 600px;
                height: 80vh;
                border-radius: 0.75rem;
                margin: auto;
                transform: scale(0.8);
            }
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
            font-size: clamp(1.2rem, 3vw, 2.5rem);
            font-weight: 600;
        }

        .modal-header .close-btn, .modal-header .fullscreen-btn {
            background: transparent;
            border: none;
            color: white;
            font-size: clamp(1rem, 3vw, 2rem);
            cursor: pointer;
            transition: color 0.2s ease;
        }

        .modal-header .close-btn:hover, .modal-header .fullscreen-btn:hover {
            color: var(--red);
        }

        .modal-body {
            padding: 1.5rem;
        }

        .item-info {
            margin-bottom: 1rem;
            padding: 1rem;
            background: var(--grey);
            border-radius: 0.5rem;
            border-bottom: 3px solid #007bff;
        }

        .item-info p {
            margin: 0.5rem 0;
            font-size: clamp(1rem, 3vw, 1.6rem);
            color: var(--dark);
            text-align: left;
        }

        .item-info .items_title {
            font-size: clamp(1.2rem, 3vw, 2rem);
        }

        .status-completed {
            background: linear-gradient(135deg, #28a745, #218838);
            color: white;
            font-weight: 600;
            font-size: clamp(1rem, 3vw, 1.6rem);
            padding: 0.2rem 0.5rem;
            border-radius: 0.25rem;
        }

        .status-pending {
            background: linear-gradient(135deg, #dc3545, #c82333);
            color: white;
            font-weight: 600;
            padding: 0.2rem 0.5rem;
            font-size: clamp(1rem, 3vw, 1.6rem);
            border-radius: 0.25rem;
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
            font-size: clamp(1rem, 3vw, 1.6rem);
            font-weight: 500;
            cursor: pointer;
            transition: background-color 0.2s ease;
        }

        .modal-footer .close-btn:hover {
            background: linear-gradient(135deg, #c82333, #b21f2d);
        }

        .toggle-btn, .delete-btn {
            color: white;
            padding: 0.5rem 1rem;
            border-radius: 0.375rem;
            font-size: clamp(1rem, 3vw, 1.4rem);
            border: none;
            cursor: pointer;
            margin: 0.25rem;
            transition: background-color 0.3s ease;
        }

        .toggle-btn {
            background: linear-gradient(135deg, #28a745, #218838);
        }

        .delete-btn {
            background: linear-gradient(135deg, #dc3545, #c82333) !important;
        }

        .toggle-btn:hover {
            background: linear-gradient(135deg, #218838, #1e7e34);
        }

        .delete-btn:hover {
            background: linear-gradient(135deg, #c82333, #b21f2d) !important;
        }

        .add-todo-btn {
            margin: 1.5rem 1.5rem 1.5rem auto;
            padding: 0.75rem 1.5rem;
            background: linear-gradient(135deg, #28a745, #218838);
            color: white;
            border: none;
            border-radius: 0.375rem;
            font-size: clamp(1rem, 3vw, 1.6rem);
            display: flex;
            align-items: center;
            gap: 0.5rem;
            cursor: pointer;
            transition: var(--transition);
            box-shadow: var(--shadow-sm);
        }

        .add-todo-btn:hover {
            background: linear-gradient(135deg, #218838, #1e7e34);
        }

        .add-todo-form label {
            display: block;
            margin-bottom: 0.5rem;
            font-weight: 500;
            color: var(--dark);
            font-size: clamp(1rem, 3vw, 1.3rem);
            text-align: left;
        }

        .add-todo-form input,
        .add-todo-form textarea {
            width: 100%;
            padding: 0.75rem;
            margin-bottom: 1rem;
            border: 1px solid #ccc;
            border-radius: 0.375rem;
            font-size: clamp(1rem, 3vw, 1.3rem);
            color: var(--dark);
            background: var(--light);
            transition: border-color 0.3s;
        }

        .add-todo-form input:focus,
        .add-todo-form textarea:focus {
            border-color: var(--blue);
            outline: none;
            box-shadow: 0 0 5px rgba(0, 123, 255, 0.5);
        }

        .add-todo-form textarea {
            resize: vertical;
            min-height: 100px;
        }

        .add-todo-form button {
            background: linear-gradient(135deg, #28a745, #218838);
            color: white;
            padding: 0.75rem 1.5rem;
            border: none;
            border-radius: 0.375rem;
            font-size: clamp(1rem, 3vw, 1.3rem);
            cursor: pointer;
            transition: background-color 0.3s ease;
            margin-right: 0.5rem;
        }

        .add-todo-form button:hover {
            background: linear-gradient(135deg, #218838, #1e7e34);
        }

        .modal-body input[type="date"]::-webkit-calendar-picker-indicator {
            background-color: #FFCE26;
            padding: 6px;
            border-radius: 50%;
            cursor: pointer;
        }

        .modal-body input[type="date"]::-webkit-calendar-picker-indicator:hover {
            background-color: #FFA500;
        }

        #todo-entries {
            background-color: var(--grey);
            border-radius: 10px;
            margin-bottom: 10px;
        }

        .add-another-todo-btn {
            background: linear-gradient(135deg, #6f42c1, #5a32a8) !important;
            color: white;
            padding: 0.75rem 1.5rem;
            border: none;
            border-radius: 0.375rem;
            font-size: clamp(1rem, 3vw, 1.2rem);
            cursor: pointer;
            transition: background-color 0.3s ease;
            margin-bottom: 1rem;
        }

        .add-another-todo-btn:hover {
            background: linear-gradient(135deg, #5a32a8, #4a288f) !important;
        }

        .todo-entry {
            margin-bottom: 1.5rem;
            padding: 1rem;
            border: 1px solid var(--grey);
            border-radius: 0.375rem;
            position: relative;
        }

        .todo-entry-header {
            font-size: clamp(1rem, 3vw, 1.6rem);
            font-weight: 600;
            text-align: left;
            margin-bottom: 0.5rem;
            color: #007bff;
        }

        .remove-todo-btn {
            position: absolute;
            top: 0.5rem;
            right: 0.5rem;
            background: linear-gradient(135deg, #dc3545, #c82333) !important;
            color: white;
            border: none;
            border-radius: 0.25rem;
            padding: 0.25rem 0.5rem;
            cursor: pointer;
            font-size: clamp(1rem, 3vw, 1.2rem);
        }

        .remove-todo-btn:hover {
            background: linear-gradient(135deg, #c82333, #b21f2d) !important;
        }

        .success-notification, .error-notification {
            background: #10b981;
            color: white;
            padding: 1rem;
            margin: 1.5rem;
            border-radius: 0.375rem;
            font-size: clamp(1rem, 3vw, 1.2rem);
            text-align: center;
            font-weight: 600;
        }

        .error-notification {
            background-color: var(--red);
        }

        .legend-container {
            margin: 1.5rem;
            padding: 1rem;
            background: var(--light);
            border-radius: 0.75rem;
            border: 1px solid var(--grey);
        }

        .legend-container h3 {
            font-size: clamp(1.2rem, 3vw, 2.5rem);
            color: var(--dark);
            margin-bottom: 1rem;
        }

        .legend-item {
            display: flex;
            align-items: center;
            font-size: clamp(1rem, 3vw, 1.6rem);
            margin-bottom: 0.5rem;
            color: var(--dark);
        }

        .legend-color {
            width: 30px;
            height: 30px;
            margin-right: 0.5rem;
            border-radius: 0.25rem;
        }

        @media screen and (max-width: 768px) {
            .calendar-container {
                margin: 1rem 0rem;
                padding: 0.75rem;
            }

            .calendar-header {
                flex-direction: column;
                gap: 0.5rem;
            }

            .calendar-nav {
                display: flex;
                gap: 0.5rem;
            }

            .calendar-nav a {
                padding: 0.4rem 0.8rem;
            }

            .calendar-table th,
            .calendar-table td {
                padding: 0.4rem;
                min-height: 60px;
            }

            .item-list li {
                padding: 0.15rem 0.4rem;
                margin-bottom: 0.2rem;
            }

            .modal-content {
                width: 95%;
                max-height: 85vh;
            }

            .modal-body {
                padding: 1rem;
            }

            .add-todo-btn {
                margin: 0.75rem;
                padding: 0.6rem 1rem;
            }

            .add-todo-form input,
            .add-todo-form textarea {
                padding: 0.6rem;
            }

            .add-todo-form textarea {
                min-height: 80px;
            }

            .add-todo-form button,
            .add-another-todo-btn {
                padding: 0.6rem 1rem;
            }

            .todo-entry {
                padding: 0.75rem;
            }

            .remove-todo-btn {
                padding: 0.2rem 0.4rem;
            }

            .legend-container {
                margin: 0.75rem;
                padding: 0.75rem;
            }
        
        }

        @media screen and (max-width: 480px) {
            .legend-color {
                width: 10px;
                height: 10px;
            }
            .calendar-container {
                padding: 0.5rem;
                overflow-x: auto;
            }

            .calendar-nav a {
                padding: 0.3rem 0.6rem;
            }

            .calendar-table th,
            .calendar-table td {
                padding: 0.3rem;
                min-height: 50px;
            }
      
            .item-list li {
                padding: 0.1rem 0.3rem;
            }

            .modal-content {
                width: 98%;
                max-height: 90vh;
            }

            .modal-body {
                padding: 0.75rem;
            }

            .item-info {
                padding: 0.75rem;
            }

            .toggle-btn,
            .delete-btn {
                padding: 0.4rem 0.8rem;
            }

            .add-todo-btn {
                margin: 0.5rem;
                padding: 0.5rem 0.8rem;
            }

            .add-todo-form input,
            .add-todo-form textarea {
                padding: 0.5rem;
            }

            .add-todo-form textarea {
                min-height: 60px;
            }

            .add-todo-form button,
            .add-another-todo-btn {
                padding: 0.5rem 0.8rem;
            }

            .remove-todo-btn {
                top: 0.3rem;
                right: 0.3rem;
                padding: 0.15rem 0.3rem;
            }

            .success-notification,
            .error-notification {
                margin: 0.5rem;
                padding: 0.75rem;
            }

            .legend-container {
                margin: 0.5rem;
                padding: 0.5rem;
            }
        }
    </style>
    <title>Learnify - Professor Calendar</title>
</head>
<body>
    <section id="sidebar">
        <?php require_once './brand.php' ?>
        <ul class="side-menu top">
            <li>
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
            <li class="active">
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

    <section id="content">
        <nav>
            <i class='bx bx-menu'></i>
            <form action="search.php" method="get">
                <div class="form-input">
                    <input type="search" name="query" placeholder="Search users...." required>
                    <button type="submit" class="search-btn"><i class='bx bx-search'></i></button>
                </div>
            </form>
            <input type="checkbox" id="switch-mode" hidden>
            <label for="switch-mode" class="switch-mode"></label>
            <a href="./main_acc.php" class="profile">
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
                    <h1>Calendar</h1>
                    <ul class="breadcrumb">
                        <li><a href="./professor_main_dash.php">Home</a></li>
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
                                        $class = $dateCompletionStatus[$currentDate] ? 'has-todo-completed' : 'has-todo-pending';
                                    }

                                    echo '<td class="' . $class . '" onclick="' . ($hasItems ? "openModal('item-modal-$currentDate')" : "openAddTodoModalWithDate('$currentDate')") . '">';
                                    echo $currentDay;

                                    if ($hasItems) {
                                        echo '<ul class="item-list">';
                                        foreach ($itemsByDate[$currentDate] as $item) {
                                            $itemClass = $item['type'] === 'todo'
                                                ? ($item['status'] === 'Completed' ? 'completed' : 'pending')
                                                : $item['type'];
                                            echo '<li class="' . $itemClass . '" onclick="openModal(\'item-modal-' . $currentDate . '\')">'
                                                . htmlspecialchars($item['title']) .
                                                '</li>';
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
                <button class="add-todo-btn" onclick="openModal('add-todo-modal')">Add New To-Do</button>
            </div>

            <!-- Add To-Do Modal -->
            <div id="add-todo-modal" class="modal" role="dialog" aria-labelledby="add-todo-title">
                <div class="modal-content">
                    <div class="modal-header">
                        <h3 id="add-todo-title">Add New To-Do</h3>
                        <div>
                            <button class="fullscreen-btn" onclick="toggleFullscreen('add-todo-modal')" aria-label="Toggle fullscreen">
                                <i class='bx bx-fullscreen'></i>
                            </button>
                            <button class="close-btn" onclick="closeModal('add-todo-modal')" aria-label="Close modal">
                                <i class='bx bx-x'></i>
                            </button>
                        </div>
                    </div>
                    <div class="modal-body">
                        <form method="POST" class="add-todo-form" id="todo-form">
                            <div id="todo-entries">
                                <div class="todo-entry" data-index="0">
                                    <div class="todo-entry-header">To-Do Item 1</div>
                                    <label for="title[0]">Title</label>
                                    <input type="text" id="title[0]" name="title[0]" placeholder="Enter to-do title" required>
                                    <label for="description[0]">Description</label>
                                    <textarea id="description[0]" name="description[0]" placeholder="Enter description (optional)"></textarea>
                                    <label for="due_date[0]">Due Date</label>
                                    <input type="date" id="due_date[0]" name="due_date[0]" required>
                                </div>
                            </div>
                            <button type="button" class="add-another-todo-btn" onclick="addTodoEntry()">Add Another To-Do</button>
                            <button type="submit" name="add_todo">Save All To-Dos</button>
                        </form>
                    </div>
                    <div class="modal-footer">
                        <button class="close-btn" onclick="closeModal('add-todo-modal')">Close</button>
                    </div>
                </div>
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
                    <span>Completed To-Do</span>
                </div>
                <div class="legend-item">
                    <div class="legend-color" style="background: linear-gradient(135deg, #dc3545, #c82333);"></div>
                    <span>Pending To-Do</span>
                </div>
            </div>

            <!-- Item Modals for Each Date -->
            <?php foreach ($itemsByDate as $date => $dateItems): ?>
                <div id="item-modal-<?php echo $date; ?>" class="modal" role="dialog" aria-labelledby="modal-title-<?php echo $date; ?>">
                    <div class="modal-content" id="modal-content">
                        <div class="modal-header">
                            <h3 id="modal-title-<?php echo $date; ?>">Items on <?php echo date('F j, Y', strtotime($date)); ?></h3>
                            <div>
                                <button class="fullscreen-btn" onclick="toggleFullscreen('item-modal-<?php echo $date; ?>')" aria-label="Toggle fullscreen">
                                    <i class='bx bx-fullscreen'></i>
                                </button>
                                <button class="close-btn" onclick="closeModal('item-modal-<?php echo $date; ?>')" aria-label="Close modal">
                                    <i class='bx bx-x'></i>
                                </button>
                            </div>
                        </div>
                        <div class="modal-body">
                            <?php foreach ($dateItems as $item): ?>
                                <div class="item-info">
                                    <p class="items_title"><strong><?php echo htmlspecialchars($item['title']); ?> (<?php echo ucfirst($item['type']); ?>)</strong></p>
                                    <p><?php echo htmlspecialchars($item['description'] ?? 'No description'); ?></p>
                                    <br>
                                    <?php if ($item['type'] === 'todo'): ?>
                                        <p>Status: <span class="<?php echo $item['status'] === 'Completed' ? 'status-completed' : 'status-pending'; ?>">
                                            <?php echo htmlspecialchars($item['status']); ?>
                                        </span></p>
                                        <br>
                                        <form method="POST" style="display: inline;">
                                            <input type="hidden" name="todoID" value="<?php echo $item['todoID']; ?>">
                                            <input type="hidden" name="status" value="<?php echo $item['status'] === 'Completed' ? 'Pending' : 'Completed'; ?>">
                                            <button type="submit" name="update_status" class="toggle-btn">
                                                <i class='bx bx-check'></i> Mark as <?php echo $item['status'] === 'Completed' ? 'Pending' : 'Completed'; ?>
                                            </button>
                                        </form>
                                        <form method="POST" style="display: inline;">
                                            <input type="hidden" name="todoID" value="<?php echo $item['todoID']; ?>">
                                            <button type="submit" name="delete_todo" class="delete-btn">
                                                <i class='bx bx-trash'></i> Archive
                                            </button>
                                        </form>
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
        let todoCount = 1;
        const maxTodos = 5;
        let selectedDate = null;

        function addTodoEntry() {
            if (todoCount >= maxTodos) {
                alert('You can add up to ' + maxTodos + ' to-do items at once.');
                return;
            }

            const todoEntries = document.getElementById('todo-entries');
            const newEntry = document.createElement('div');
            newEntry.className = 'todo-entry';
            newEntry.dataset.index = todoCount;
            newEntry.innerHTML = `
                <div class="todo-entry-header">To-Do Item ${todoCount + 1}</div>
                <button type="button" class="remove-todo-btn" onclick="removeTodoEntry(${todoCount})">Remove</button>
                <label for="title[${todoCount}]">Title</label>
                <input type="text" id="title[${todoCount}]" name="title[${todoCount}]" placeholder="Enter to-do title" required>
                <label for="description[${todoCount}]">Description</label>
                <textarea id="description[${todoCount}]" name="description[${todoCount}]" placeholder="Enter description (optional)"></textarea>
                <label for="due_date[${todoCount}]">Due Date</label>
                <input type="date" id="due_date[${todoCount}]" name="due_date[${todoCount}]" value="${selectedDate || ''}" required>
            `;
            todoEntries.appendChild(newEntry);
            todoCount++;
        }

        function removeTodoEntry(index) {
            const todoEntries = document.getElementById('todo-entries');
            const entry = todoEntries.querySelector(`.todo-entry[data-index="${index}"]`);
            if (entry) {
                todoEntries.removeChild(entry);
                todoCount--;
                const entries = todoEntries.querySelectorAll('.todo-entry');
                entries.forEach((entry, i) => {
                    entry.dataset.index = i;
                    entry.querySelector('.todo-entry-header').textContent = `To-Do Item ${i + 1}`;
                    entry.querySelector('input[type="text"]').name = `title[${i}]`;
                    entry.querySelector('textarea').name = `description[${i}]`;
                    entry.querySelector('input[type="date"]').name = `due_date[${i}]`;
                    const removeBtn = entry.querySelector('.remove-todo-btn');
                    if (removeBtn) {
                        removeBtn.setAttribute('onclick', `removeTodoEntry(${i})`);
                    }
                });
            }
        }

        function openModal(modalId) {
            const modal = document.getElementById(modalId);
            modal.style.display = 'flex';
        }

        function openAddTodoModalWithDate(date) {
            selectedDate = date;
            const modal = document.getElementById('add-todo-modal');
            modal.style.display = 'flex';
            const todoEntries = document.getElementById('todo-entries');
            todoEntries.innerHTML = `
                <div class="todo-entry" data-index="0">
                    <div class="todo-entry-header">To-Do Item 1</div>
                    <label for="title[0]">Title</label>
                    <input type="text" id="title[0]" name="title[0]" placeholder="Enter to-do title" required>
                    <label for="description[0]">Description</label>
                    <textarea id="description[0]" name="description[0]" placeholder="Enter description (optional)"></textarea>
                    <label for="due_date[0]">Due Date</label>
                    <input type="date" id="due_date[0]" name="due_date[0]" value="${date}" required>
                </div>
            `;
            todoCount = 1;
        }

        function closeModal(modalId) {
            const modal = document.getElementById(modalId);
            modal.style.display = 'none';
            const modalContent = modal.querySelector('.modal-content');
            if (modalContent.classList.contains('fullscreen')) {
                modalContent.style.animation = 'collapseFullscreen 0.5s cubic-bezier(0.4, 0, 0.2, 1)';
                setTimeout(() => {
                    modalContent.classList.remove('fullscreen');
                    modalContent.style.animation = '';
                }, 500);
                const fullscreenBtn = modal.querySelector('.fullscreen-btn i');
                fullscreenBtn.classList.remove('bx-exit-fullscreen');
                fullscreenBtn.classList.add('bx-fullscreen');
            }
            if (modalId === 'add-todo-modal') {
                const todoEntries = document.getElementById('todo-entries');
                todoEntries.innerHTML = `
                    <div class="todo-entry" data-index="0">
                        <div class="todo-entry-header">To-Do Item 1</div>
                        <label for="title[0]">Title</label>
                        <input type="text" id="title[0]" name="title[0]" placeholder="Enter to-do title" required>
                        <label for="description[0]">Description</label>
                        <textarea id="description[0]" name="description[0]" placeholder="Enter description (optional)"></textarea>
                        <label for="due_date[0]">Due Date</label>
                        <input type="date" id="due_date[0]" name="due_date[0]" required>
                    </div>
                `;
                todoCount = 1;
                selectedDate = null;
            }
        }

        function toggleFullscreen(modalId) {
            const modal = document.getElementById(modalId);
            const modalContent = modal.querySelector('.modal-content');
            const fullscreenBtn = modal.querySelector('.fullscreen-btn i');

            if (modalContent.classList.contains('fullscreen')) {
                modalContent.style.animation = 'collapseFullscreen 0.5s cubic-bezier(0.4, 0, 0.2, 1)';
                setTimeout(() => {
                    modalContent.classList.remove('fullscreen');
                    modalContent.style.animation = '';
                }, 500);
                fullscreenBtn.classList.remove('bx-exit-fullscreen');
                fullscreenBtn.classList.add('bx-fullscreen');
            } else {
                modalContent.classList.add('fullscreen');
                modalContent.style.animation = 'expandFullscreen 0.5s cubic-bezier(0.4, 0, 0.2, 1)';
                setTimeout(() => {
                    modalContent.style.animation = '';
                }, 500);
                fullscreenBtn.classList.remove('bx-fullscreen');
                fullscreenBtn.classList.add('bx-exit-fullscreen');
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
                openModal('add-todo-modal');
            };
        <?php endif; ?>
    </script>
    <script src="./utils/script.js"></script>
</body>
</html>