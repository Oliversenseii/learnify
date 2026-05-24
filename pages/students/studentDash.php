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

    // Get user details and enrollment status
    $stmt = $dbConnection->prepare("
        SELECT u.firstName, u.userType, u.image, u.dashboard_view, ss.status 
        FROM users u
        LEFT JOIN student_section ss ON u.userID = ss.userID
        WHERE u.userID = :userID
        LIMIT 1
    ");
    $stmt->bindParam(':userID', $userID, PDO::PARAM_INT);
    $stmt->execute();
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($user) {
        $_SESSION['firstName'] = htmlspecialchars($user['firstName']);
        $_SESSION['userType'] = htmlspecialchars($user['userType']);
        $_SESSION['image'] = $user['image'] ? htmlspecialchars($user['image']) : './img/noprofile.png';
        $_SESSION['dashboard_view'] = isset($user['dashboard_view']) && in_array($user['dashboard_view'], ['table', 'card']) ? $user['dashboard_view'] : null;
        
        // Check enrollment status
        $status = $user['status'];
        if ($status === 'Pending' || $status === 'Dropped' || $status === 'Completed') {
            header("Location: ./status.php");
            exit;
        }
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

    // Count total classes for pagination
    $countSQL = "
        SELECT COUNT(DISTINCT ts.teacherSectionID) as total 
        FROM teacher_section ts
        JOIN users u ON ts.teacherID = u.userID
        JOIN sections s ON ts.sectionID = s.sectionID
        JOIN subjects sub ON ts.subjectID = sub.subjectID
        JOIN student_section ss ON ts.sectionID = ss.sectionID
        WHERE ss.userID = :userID AND ss.status = 'Enrolled' AND ts.archived = 0 AND u.archived = 0 AND s.archived = 0 AND sub.archived = 0 AND ss.archived = 0
    ";
    $countStmt = $dbConnection->prepare($countSQL);
    $countStmt->bindParam(':userID', $userID, PDO::PARAM_INT);
    $countStmt->execute();
    $totalRecords = $countStmt->fetch(PDO::FETCH_ASSOC)['total'];
    $totalPages = ceil($totalRecords / $recordsPerPage);

    // Fetch classes with pending task details for direct links
   $sql = "
    SELECT
        ts.teacherSectionID,
        ts.sectionID,
        s.sectionName,
        ts.subjectID,
        sub.subjectName,
        ts.teacherID,
        CONCAT(u.firstName, ' ', u.lastName) AS teacherName,
        u.image AS teacherImage,
        ts.startTime,
        ts.endTime,
        ts.day,
        ss.card_order,
        (
            SELECT COUNT(*)
            FROM quizzes q
            WHERE q.teacherSectionID = ts.teacherSectionID
              AND q.archived = 0
              AND (q.releaseDate IS NULL OR q.releaseDate <= CONVERT_TZ(NOW(), @@session.time_zone, '+08:00'))
              AND (q.dueDate IS NULL OR CONVERT_TZ(q.dueDate, @@session.time_zone, '+08:00') >= CONVERT_TZ(NOW(), @@session.time_zone, '+08:00'))
              AND NOT EXISTS (
                  SELECT 1 FROM quiz_answers qa
                  WHERE qa.quizID = q.quizID
                    AND qa.studentID = :userID
                    AND qa.archived = 0
              )
        ) as pending_quiz_count,
        (
            SELECT COUNT(*)
            FROM assignments a
            WHERE a.teacherSectionID = ts.teacherSectionID
              AND a.archived = 0
              AND (a.dueDate IS NULL OR CONVERT_TZ(a.dueDate, @@session.time_zone, '+08:00') >= CONVERT_TZ(NOW(), @@session.time_zone, '+08:00'))
              AND NOT EXISTS (
                  SELECT 1 FROM assignment_submissions asm
                  WHERE asm.assignmentID = a.assignmentID
                    AND asm.studentID = :userID
                    AND asm.archived = 0
              )
        ) as pending_assignment_count,
        (
            SELECT q.quizID
            FROM quizzes q
            WHERE q.teacherSectionID = ts.teacherSectionID
              AND q.archived = 0
              AND (q.releaseDate IS NULL OR q.releaseDate <= CONVERT_TZ(NOW(), @@session.time_zone, '+08:00'))
              AND (q.dueDate IS NULL OR CONVERT_TZ(q.dueDate, @@session.time_zone, '+08:00') >= CONVERT_TZ(NOW(), @@session.time_zone, '+08:00'))
              AND NOT EXISTS (
                  SELECT 1 FROM quiz_answers qa
                  WHERE qa.quizID = q.quizID
                    AND qa.studentID = :userID
                    AND qa.archived = 0
              )
            ORDER BY CONVERT_TZ(q.dueDate, @@session.time_zone, '+08:00') ASC
            LIMIT 1
        ) as first_pending_quiz_id,
        (
            SELECT a.assignmentID
            FROM assignments a
            WHERE a.teacherSectionID = ts.teacherSectionID
              AND a.archived = 0
              AND (a.dueDate IS NULL OR CONVERT_TZ(a.dueDate, @@session.time_zone, '+08:00') >= CONVERT_TZ(NOW(), @@session.time_zone, '+08:00'))
              AND NOT EXISTS (
                  SELECT 1 FROM assignment_submissions asm
                  WHERE asm.assignmentID = a.assignmentID
                    AND asm.studentID = :userID
                    AND asm.archived = 0
              )
            ORDER BY CONVERT_TZ(a.dueDate, @@session.time_zone, '+08:00') ASC
            LIMIT 1
        ) as first_pending_assignment_id
    FROM teacher_section ts
    JOIN users u ON ts.teacherID = u.userID
    JOIN sections s ON ts.sectionID = s.sectionID
    JOIN subjects sub ON ts.subjectID = sub.subjectID
    JOIN student_section ss ON ts.sectionID = ss.sectionID
    WHERE ss.userID = :userID 
      AND ss.status = 'Enrolled' 
      AND ts.archived = 0 
      AND u.archived = 0 
      AND s.archived = 0 
      AND sub.archived = 0 
      AND ss.archived = 0
    GROUP BY ts.teacherSectionID
    ORDER BY ss.card_order IS NULL, ss.card_order, ts.teacherSectionID
    LIMIT :offset, :recordsPerPage";
    $stmt = $dbConnection->prepare($sql);
    $stmt->bindParam(':userID', $userID, PDO::PARAM_INT);
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    $stmt->bindValue(':recordsPerPage', $recordsPerPage, PDO::PARAM_INT);
    $stmt->execute();
    $classes = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Debug: Log number of classes fetched
    error_log("Classes fetched: " . count($classes));

    // Calculate total pending count for header badge
    $totalPendingCount = 0;
    foreach ($classes as $class) {
        $totalPendingCount += ($class['pending_quiz_count'] ?? 0) + ($class['pending_assignment_count'] ?? 0);
    }

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
    <title>Learnify - Student Dashboard</title>
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
            --warning: #ED8936;
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

        /* Pending Tasks Badge in Header */
        .dashboard-header-badge {
            background: var(--warning);
            color: var(--white);
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
            background: var(--white);
            border-radius: 12px;
            box-shadow: var(--shadow);
            overflow-x: auto;
            background-color: var(--light);
        }

        table {
            width: 100%;
            border-collapse: collapse;
            min-width: 900px;
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

        /* Pending Tasks in Table - Clickable Badges */
        .pending-tasks-cell {
            display: flex;
            flex-wrap: wrap;
            gap: 0.5rem;
            align-items: center;
        }

        .pending-task-badge {
            background: var(--warning);
            color: var(--white);
            padding: 0.50rem 1rem;
            border-radius: 12px;
            font-size: clamp(1rem, 3vw, 1.2rem);
            font-weight: 500;
            display: flex;
            align-items: center;
            gap: 0.25rem;
            white-space: nowrap;
            text-decoration: none;
            transition: var(--transition);
            cursor: pointer;
            border: none;
        }

        .pending-task-badge:hover {
            transform: translateY(-1px);
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.2);
            text-decoration: none;
            color: var(--white);
        }

        .pending-task-badge.quiz {
            background: linear-gradient(135deg, #dc3545, #c82333);
        }

        .pending-task-badge.assignment {
            background: linear-gradient(135deg, var(--primary), var(--primary-dark));
        }

        .pending-task-badge i {
            font-size: clamp(1rem, 3vw, 1.2rem);
        }

        .no-pending-text {
            color: var(--success);
            font-style: italic;
        }

        .btn-view {
            padding: 0.5rem 1rem;
            background: var(--primary);
            color: var(--white);
            text-decoration: none;
            border-radius: 8px;
            font-size: clamp(1rem, 3vw, 1.2rem);
            transition: var(--transition);
            display: inline-block;
            text-align: center;
        }

        .btn-view:hover {
            background: var(--primary-dark);
            transform: translateY(-2px);
            text-decoration: none;
            color: var(--white);
        }

        /* Card View */
        .class-grid {
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
            text-decoration: none;
            color: inherit;
            display: block;
            height: auto;
            overflow-x: auto;
            overflow-y: auto;
        }

        .class-card.has-pending {
            border-left: 4px solid var(--warning);
            box-shadow: 0 4px 12px rgba(237, 137, 54, 0.2);
        }

        .class-card.dragging {
            opacity: 0.5;
            transform: scale(0.95);
        }

        .class-card:hover {
            transform: translateY(-4px);
            box-shadow: 0 6px 12px -2px rgba(0, 0, 0, 0.15);
            text-decoration: none;
            color: inherit;
        }

        .class-card:hover.has-pending {
            box-shadow: 0 6px 20px rgba(237, 137, 54, 0.3);
        }

        .class-card-header {
            background: linear-gradient(135deg, var(--primary), var(--primary-dark));
            color: var(--white);
            padding: 1.2rem;
            font-size: clamp(1.1rem, 3vw, 1.3rem);
            font-weight: 600;
            display: flex;
            justify-content: space-between;
            align-items: center;
            position: relative;
        }

        .class-card-header .section-id {
            font-size: 1rem;
            background: rgba(0, 0, 0, 0.2);
            padding: 0.25rem 0.5rem;
            border-radius: 0.25rem;
        }

        /* Pending Count Badge in Header */
        .pending-count-header {
            position: absolute;
            top: 12rem;
            right: 0.5rem;
            background: var(--warning);
            color: var(--white);
            border-radius: 50%;
            width: 24px;
            height: 24px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 0.75rem;
            font-weight: 600;
        }

        .class-card-body {
            padding: 1rem;
            color: var(--text);
        }

        .class-info {
            display: grid;
            grid-template-columns: 1fr;
            gap: 0.75rem;
        }

        .info-item {
            align-items: center;
            gap: 0.5rem;
            font-size: 1.2rem;
            margin-top: 10px;
            color: var(--dark);
        }

        .info-item i {
            display: none;
        }

        .info-item .label {
            font-weight: 600;
            text-align: left;
        }

        .info-item .value {
            font-weight: 400;
            padding-left: 0.5rem;
        }

        /* Pending Tasks in Cards - Clickable Items */
        .pending-tasks-container {
            margin-top: 0.75rem;
            padding: 01rem;
            background: rgba(237, 137, 54, 0.1);
            border-radius: 6px;
            border-left: 3px solid var(--warning);
        }

        .pending-tasks-title {
            font-size: 1.25rem;
            font-weight: 600;
            color: var(--warning);
            margin-bottom: 0.5rem;
            display: flex;
            align-items: center;
            gap: 0.25rem;
        }

        .pending-task-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 0.25rem 0;
            font-size: clamp(1rem, 3vw, 1.1rem);
            color: var(--text-secondary);
            cursor: pointer;
            transition: var(--transition);
            border-radius: 4px;
            padding: 0.25rem 0.5rem;
            margin: -0.25rem 0;
        }

        .pending-task-item:hover {
            background: rgba(237, 137, 54, 0.2);
            color: var(--text);
        }

        .pending-task-item .task-name {
            flex: 1;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
            display: flex;
            align-items: center;
            gap: 0.25rem;
            color: var(--dark);
        }

        .pending-task-item .task-name i {
            font-size: 1.1rem;
            width: 12px;
        }

        .pending-task-item .task-due {
            font-size: 1rem;
            color: var(--warning);
            font-weight: 500;
        }

        .teacher-img {
            width: 50px;
            height: 50px;
            border-radius: 50%;
            object-fit: cover;
            border: 2px solid var(--white);
        }

        .table-teacher-container {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            padding-bottom: 10px;
        }

        .teacher-container {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            padding-bottom: 10px;
            border-bottom: 1px solid #ccc;
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

        .notification-badge {
            background: linear-gradient(135deg, #dc3545, #c82333);
            color: var(--white);
            border-radius: 50%;
            padding: 0.3rem 0.6rem;
            font-size: 0.8rem;
            margin-left: 0.5rem;
            display: inline-block;
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
            font-size: clamp(1.1rem, 3vw, 1.3rem);
            border: none;
            cursor: pointer;
            padding: 0.75rem;
        }
        .close-btn {
            position: absolute;
            top: 1.2rem;
            right: 1.2rem;
            font-size: clamp(1.1rem, 3vw, 3rem);
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
            font-size: clamp(1.1rem, 3vw, 1.2rem);
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

        .schedule-teacher-container {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            margin-top: 0.5rem;
        }

        .schedule-teacher-img {
            width: 30px;
            height: 30px;
            border-radius: 50%;
            object-fit: cover;
            border: 2px solid var(--white);
        }

        .schedule-teacher-name {
            font-size: clamp(0.8rem, 2vw, 0.9rem);
            color: var(--text-secondary);
        }

        @media screen and (max-width: 1024px) {
            .class-grid {
                grid-template-columns: repeat(2, 1fr);
            }
        }

        @media screen and (max-width: 768px) {
            .tab-container, .table-container, .class-grid {
                padding: 0;
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

            .class-grid {
                grid-template-columns: 1fr;
            }
        }

        @media screen and (max-width: 480px) {
            .table-container, .class-grid {
                padding: 0;
                margin: 0;
                margin-top: 10px;
            }

            .class-card-header span {
                white-space: nowrap; 
                overflow: hidden; 
                text-overflow: ellipsis;
                max-width: 80%; 
            }

            .modal-content {
                width: 90%;
                padding: 0.75rem;
            }
            .pending-count-header {
                top: 13rem;
            }
            .pending-task-item {
                display: flex;
                flex-direction: column;
                justify-content: center;
            }
        }

        #content main .head-title .left .breadcrumb li a {
            color: var(--text-secondary);
            pointer-events: none;
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
            <li class="active">
                <a href="./studentDash.php">
                    <i class='bx bxs-dashboard'></i>
                    <span class="text">Dashboard<?php if ($totalPendingCount > 0): ?>
                        <span class="dashboard-header-badge"><?php echo $totalPendingCount; ?></span>
                    <?php endif; ?></span>
                </a>
            </li>
            <li>
                <a href="./announcements.php?viewed=true">
                    <i class='bx bxs-bell'></i>
                    <span class="text">Announcements</span>
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
                                    'teacherSectionID' => null,
                                    'teacherName' => '',
                                    'teacherImage' => './img/noprofile.png'
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
                                $teacherSectionID = htmlspecialchars($class['teacherSectionID']);
                                $teacherName = htmlspecialchars(ucwords($class['teacherName']));
                                $teacherImage = $class['teacherImage'] ? htmlspecialchars($class['teacherImage']) : './img/noprofile.png';

                                foreach ($timeSlots as $slot) {
                                    if (isset($slot['break'])) {
                                        continue; 
                                    }
                                    $slotStart = strtotime($slot['start']);
                                    $slotEnd = strtotime($slot['end']);

                                    if ($classStart >= $slotStart && $classEnd <= $slotEnd) {
                                        $schedule[$slot['label']][$day] = [
                                            'subject' => $subject,
                                            'teacherSectionID' => $teacherSectionID,
                                            'teacherName' => $teacherName,
                                            'teacherImage' => $teacherImage
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
                                        if ($subjectData['subject'] && $subjectData['teacherSectionID']) {
                                            echo "<td>";
                                            echo "<a href='classDetails.php?teacherSectionID=" . $subjectData['teacherSectionID'] . "' class='subject-link' aria-label='View " . $subjectData['subject'] . " Details'>" . $subjectData['subject'] . "</a>";
                                            echo "<div class='schedule-teacher-container'>";
                                            echo "<img src='" . $subjectData['teacherImage'] . "' alt='Teacher Profile' class='schedule-teacher-img'>";
                                            echo "<span class='schedule-teacher-name'>" . $subjectData['teacherName'] . "</span>";
                                            echo "</div>";
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
                    <h1>Student Dashboard</h1>
                    <ul class="breadcrumb">
                        <li><a href="#">Home</a></li>
                        <li><i class='bx bx-chevron-right'></i></li>
                        <li><a class="active" href="./studentDash.php">Dashboard</a></li>
                    </ul>
                </div>
                <div class="schedule-container">
                    <button class="back-button" onclick="showScheduleModal()" aria-label="View Time Schedule"><i class='bx bx-time'></i> View Time Schedule</button>
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
                                    <th>Teacher</th>
                                    <th>Day</th>
                                    <th>Time</th>
                                    <th>Pending Tasks</th>
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
                                    $hasPending = ($class['pending_quiz_count'] ?? 0) + ($class['pending_assignment_count'] ?? 0) > 0;
                                    ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($rowNumber++); ?></td>
                                        <td><?php echo htmlspecialchars($class['subjectName']); ?></td>
                                        <td><?php echo htmlspecialchars($class['sectionName']); ?></td>
                                        <td><?php echo htmlspecialchars(ucwords($class['teacherName'])); ?></td>
                                        <td><?php echo htmlspecialchars($class['day']); ?></td>
                                        <td><?php echo htmlspecialchars($formattedTime); ?></td>
                                        <td>
                                            <?php if ($hasPending): ?>
                                                <div class="pending-tasks-cell">
                                                    <?php if ($class['pending_quiz_count'] > 0 && $class['first_pending_quiz_id']): ?>
                                                        <a href="studentQuiz.php?teacherSectionID=<?php echo htmlspecialchars($class['teacherSectionID']); ?>&quizID=<?php echo htmlspecialchars($class['first_pending_quiz_id']); ?>" 
                                                           class="pending-task-badge quiz"
                                                           aria-label="Take first pending quiz for <?php echo htmlspecialchars($class['subjectName']); ?>">
                                                            <i class='bx bx-edit'></i>
                                                            <?php echo $class['pending_quiz_count']; ?> Quiz<?php echo $class['pending_quiz_count'] > 1 ? 'es' : ''; ?>
                                                        </a>
                                                    <?php endif; ?>
                                                    <?php if ($class['pending_assignment_count'] > 0): ?>
                                                        <a href="studentAssignment.php?teacherSectionID=<?php echo htmlspecialchars($class['teacherSectionID']); ?>" 
                                                           class="pending-task-badge assignment"
                                                           aria-label="Submit first pending assignment for <?php echo htmlspecialchars($class['subjectName']); ?>">
                                                            <i class='bx bx-file'></i>
                                                            <?php echo $class['pending_assignment_count']; ?> Assignment<?php echo $class['pending_assignment_count'] > 1 ? 's' : ''; ?>
                                                        </a>
                                                    <?php endif; ?>
                                                </div>
                                            <?php else: ?>
                                                <span class="no-pending-text">No pending tasks</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <a href="classDetails.php?teacherSectionID=<?php echo htmlspecialchars($class['teacherSectionID']); ?>" 
                                               class="btn-view"
                                               aria-label="View <?php echo htmlspecialchars($class['subjectName']); ?> Class Details">
                                                View
                                            </a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php else: ?>
                        <p class="no-classes">No classes found.</p>
                    <?php endif; ?>
                </div>
                <div class="class-grid tab-content" id="card">
                    <?php if (!empty($classes)): ?>
                        <?php foreach ($classes as $class): ?>
                            <?php
                            $startTime = date("g:ia", strtotime($class['startTime']));
                            $endTime = date("g:ia", strtotime($class['endTime']));
                            $formattedTime = "$startTime - $endTime";
                            $initials = strtoupper(substr($class['subjectName'], 0, 2));
                            $teacherImage = $class['teacherImage'] ? htmlspecialchars($class['teacherImage']) : './img/noprofile.png';
                            $hasPending = ($class['pending_quiz_count'] ?? 0) + ($class['pending_assignment_count'] ?? 0) > 0;
                            ?>
                            <a href="classDetails.php?teacherSectionID=<?php echo htmlspecialchars($class['teacherSectionID']); ?>" 
                               class="class-card <?php echo $hasPending ? 'has-pending' : ''; ?>" 
                               draggable="true" 
                               data-id="<?php echo htmlspecialchars($class['teacherSectionID']); ?>"
                               aria-label="View <?php echo htmlspecialchars($class['subjectName']); ?> Details">
                                <div class="class-card-header">
                                    <span class="subject-names"><?php echo htmlspecialchars($class['subjectName']); ?></span>
                                    <span class="card-initials"><?php echo htmlspecialchars($initials); ?></span>
                                    <?php if ($hasPending): ?>
                                        <div class="pending-count-header">
                                            <?php echo ($class['pending_quiz_count'] ?? 0) + ($class['pending_assignment_count'] ?? 0); ?>
                                        </div>
                                    <?php endif; ?>
                                </div>
                                <div class="class-card-body">
                                    <div class="class-info">
                                        <div class="info-item">
                                            <i class='bx bx-building'></i>
                                            <span class="label">Section:</span>
                                            <span class="value"><?php echo htmlspecialchars($class['sectionName']); ?></span>
                                        </div>
                                        <div class="info-item teacher-info">
                                            <i class='bx bx-user'></i>
                                            <span class="label">Teacher:</span>
                                            <div class="teacher-container">
                                                <img src="<?php echo $teacherImage; ?>" alt="Teacher Profile" class="teacher-img">
                                                <span class="value"><?php echo htmlspecialchars(ucwords($class['teacherName'])); ?></span>
                                            </div>
                                        </div>
                                        <div class="info-item">
                                            <i class='bx bx-calendar'></i>
                                            <span class="label">Day:</span>
                                            <span class="value"><?php echo htmlspecialchars($class['day']); ?></span>
                                        </div>
                                        <div class="info-item">
                                            <i class='bx bx-time'></i>
                                            <span class="label">Time:</span>
                                            <span class="value"><?php echo htmlspecialchars($formattedTime); ?></span>
                                        </div>
                                        <?php if ($hasPending): ?>
                                            <div class="pending-tasks-container">
                                                <div class="pending-tasks-title">
                                                    <i class='bx bx-task'></i>
                                                    Pending Tasks
                                                </div>
                                                <?php if ($class['pending_quiz_count'] > 0 && $class['first_pending_quiz_id']): ?>
                                                    <div class="pending-task-item" 
                                                         onclick="window.location.href='studentQuiz.php?teacherSectionID=<?php echo htmlspecialchars($class['teacherSectionID']); ?>&quizID=<?php echo htmlspecialchars($class['first_pending_quiz_id']); ?>'"
                                                         style="cursor: pointer;">
                                                        <span class="task-name">
                                                            <i class='bx bx-edit' style='color: #dc3545;'></i>
                                                            <?php echo $class['pending_quiz_count']; ?> Quiz<?php echo $class['pending_quiz_count'] > 1 ? 'es' : ''; ?> Due
                                                        </span>
                                                        <span class="task-due">Take Now</span>
                                                    </div>
                                                <?php endif; ?>
                                                <?php if ($class['pending_assignment_count'] > 0): ?>
                                                    <div class="pending-task-item" 
                                                         onclick="window.location.href='studentAssignment.php?teacherSectionID=<?php echo htmlspecialchars($class['teacherSectionID']); ?>'"
                                                         style="cursor: pointer;">
                                                        <span class="task-name">
                                                            <i class='bx bx-file' style='color: var(--primary);'></i>
                                                            <?php echo $class['pending_assignment_count']; ?> Assignment<?php echo $class['pending_assignment_count'] > 1 ? 's' : ''; ?> Due
                                                        </span>
                                                        <span class="task-due">Submit Now</span>
                                                    </div>
                                                <?php endif; ?>
                                            </div>
                                        <?php endif; ?>
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
                                    <th>Teacher</th>
                                    <th>Day</th>
                                    <th>Time</th>
                                    <th>Pending Tasks</th>
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
                                    $hasPending = ($class['pending_quiz_count'] ?? 0) + ($class['pending_assignment_count'] ?? 0) > 0;
                                    ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($rowNumber++); ?></td>
                                        <td><?php echo htmlspecialchars($class['subjectName']); ?></td>
                                        <!-- <td><?php echo htmlspecialchars($class['sectionName']); ?></td> -->
                                        <!-- <td><?php echo htmlspecialchars(ucwords($class['teacherName'])); ?></td> -->
                                        <td>
                                            <div class="table-teacher-container">
                                                <img src="<?php echo $teacherImage; ?>" alt="Teacher Profile" class="teacher-img">
                                                <span class="value"><?php echo htmlspecialchars(ucwords($class['teacherName'])); ?></span>
                                            </div>
                                        </td>
                                        <td><?php echo htmlspecialchars($class['day']); ?></td>
                                        <td><?php echo htmlspecialchars($formattedTime); ?></td>
                                        <td>
                                            <?php if ($hasPending): ?>
                                                <div class="pending-tasks-cell">
                                                    <?php if ($class['pending_quiz_count'] > 0 && $class['first_pending_quiz_id']): ?>
                                                        <a href="studentQuiz.php?teacherSectionID=<?php echo htmlspecialchars($class['teacherSectionID']); ?>&quizID=<?php echo htmlspecialchars($class['first_pending_quiz_id']); ?>" 
                                                           class="pending-task-badge quiz"
                                                           aria-label="Take first pending quiz for <?php echo htmlspecialchars($class['subjectName']); ?>">
                                                            <i class='bx bx-edit'></i>
                                                            <?php echo $class['pending_quiz_count']; ?> Quiz<?php echo $class['pending_quiz_count'] > 1 ? 'es' : ''; ?>
                                                        </a>
                                                    <?php endif; ?>
                                                    <?php if ($class['pending_assignment_count'] > 0): ?>
                                                        <a href="studentAssignment.php?teacherSectionID=<?php echo htmlspecialchars($class['teacherSectionID']); ?>" 
                                                           class="pending-task-badge assignment"
                                                           aria-label="Submit first pending assignment for <?php echo htmlspecialchars($class['subjectName']); ?>">
                                                            <i class='bx bx-file'></i>
                                                            <?php echo $class['pending_assignment_count']; ?> Assignment<?php echo $class['pending_assignment_count'] > 1 ? 's' : ''; ?>
                                                        </a>
                                                    <?php endif; ?>
                                                </div>
                                            <?php else: ?>
                                                <span class="no-pending-text">No pending tasks</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <a href="classDetails.php?teacherSectionID=<?php echo htmlspecialchars($class['teacherSectionID']); ?>" 
                                               class="btn-view"
                                               aria-label="View <?php echo htmlspecialchars($class['subjectName']); ?> Class Details">
                                                View
                                            </a>
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
                <div class="class-grid">
                    <?php if (!empty($classes)): ?>
                        <?php foreach ($classes as $class): ?>
                            <?php
                            $startTime = date("g:ia", strtotime($class['startTime']));
                            $endTime = date("g:ia", strtotime($class['endTime']));
                            $formattedTime = "$startTime - $endTime";
                            $initials = strtoupper(substr($class['subjectName'], 0, 2));
                            $teacherImage = $class['teacherImage'] ? htmlspecialchars($class['teacherImage']) : './img/noprofile.png';
                            $hasPending = ($class['pending_quiz_count'] ?? 0) + ($class['pending_assignment_count'] ?? 0) > 0;
                            ?>
                            <a href="classDetails.php?teacherSectionID=<?php echo htmlspecialchars($class['teacherSectionID']); ?>" 
                               class="class-card <?php echo $hasPending ? 'has-pending' : ''; ?>" 
                               draggable="true" 
                               data-id="<?php echo htmlspecialchars($class['teacherSectionID']); ?>"
                               aria-label="View <?php echo htmlspecialchars($class['subjectName']); ?> Details">
                                <div class="class-card-header">
                                    <span><?php echo htmlspecialchars($class['subjectName']); ?></span>
                                    <span class="card-initials"><?php echo htmlspecialchars($initials); ?></span>
                                    <?php if ($hasPending): ?>
                                        <div class="pending-count-header">
                                            <?php echo ($class['pending_quiz_count'] ?? 0) + ($class['pending_assignment_count'] ?? 0); ?>
                                        </div>
                                    <?php endif; ?>
                                </div>
                                <div class="class-card-body">
                                    <div class="class-info">
                                        <!-- <div class="info-item">
                                            <i class='bx bx-building'></i>
                                            <span class="label">Section:</span>
                                            <span class="value"><?php echo htmlspecialchars($class['sectionName']); ?></span>
                                        </div> -->
                                        <div class="info-item teacher-info">
                                            <i class='bx bx-user'></i>
                                            <!-- <span class="label">Teacher:</span> -->
                                            <div class="teacher-container">
                                                <img src="<?php echo $teacherImage; ?>" alt="Teacher Profile" class="teacher-img">
                                                <span class="value"><strong><?php echo htmlspecialchars(ucwords($class['teacherName'])); ?></strong>
                                                <br><?php echo htmlspecialchars($class['day']); ?> - <?php echo htmlspecialchars($formattedTime); ?>
                                                </span>
                                            </div>
                                        </div>
                                        <!-- <div class="info-item">
                                            <i class='bx bx-calendar'></i>
                                            <span class="label">Day:</span>
                                            <span class="value"><?php echo htmlspecialchars($class['day']); ?></span>
                                        </div>
                                        <div class="info-item">
                                            <i class='bx bx-time'></i>
                                            <span class="label">Time:</span>
                                            <span class="value"><?php echo htmlspecialchars($formattedTime); ?></span>
                                        </div> -->
                                        <?php if ($hasPending): ?>
                                            <div class="pending-tasks-container">
                                                <div class="pending-tasks-title">
                                                    <i class='bx bx-task'></i>
                                                    Pending Tasks
                                                </div>
                                                <?php if ($class['pending_quiz_count'] > 0 && $class['first_pending_quiz_id']): ?>
                                                    <div class="pending-task-item" 
                                                         onclick="window.location.href='studentQuiz.php?teacherSectionID=<?php echo htmlspecialchars($class['teacherSectionID']); ?>&quizID=<?php echo htmlspecialchars($class['first_pending_quiz_id']); ?>'"
                                                         style="cursor: pointer;">
                                                        <span class="task-name">
                                                            <i class='bx bx-edit' style='color: #dc3545;'></i>
                                                            <?php echo $class['pending_quiz_count']; ?> Quiz<?php echo $class['pending_quiz_count'] > 1 ? 'es' : ''; ?> Due
                                                        </span>
                                                        <span class="task-due">Take Now</span>
                                                    </div>
                                                <?php endif; ?>
                                                <?php if ($class['pending_assignment_count'] > 0): ?>
                                                    <div class="pending-task-item" 
                                                         onclick="window.location.href='studentAssignment.php?teacherSectionID=<?php echo htmlspecialchars($class['teacherSectionID']); ?>'"
                                                         style="cursor: pointer;">
                                                        <span class="task-name">
                                                            <i class='bx bx-file' style='color: var(--primary);'></i>
                                                            <?php echo $class['pending_assignment_count']; ?> Assignment<?php echo $class['pending_assignment_count'] > 1 ? 's' : ''; ?> Due
                                                        </span>
                                                        <span class="task-due">Submit Now</span>
                                                    </div>
                                                <?php endif; ?>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </a>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <p class="no-classes">No classes found.</p>
                    <?php endif; ?>
                </div>
            <?php endif; ?>

            <?php if ($totalPages > 1): ?>
                <div class="pagination">
                    <?php
                    if ($currentPage > 1) {
                        $prevPage = $currentPage - 1;
                        echo "<a href='studentDash.php?page=$prevPage' aria-label='Previous Page'>Previous</a>";
                    } else {
                        echo "<a class='disabled' aria-disabled='true'>Previous</a>";
                    }

                    for ($i = 1; $i <= $totalPages; $i++) {
                        $activeClass = $i == $currentPage ? 'active' : '';
                        echo "<a href='studentDash.php?page=$i' class='$activeClass' aria-label='Page $i'>$i</a>";
                    }

                    if ($currentPage < $totalPages) {
                        $nextPage = $currentPage + 1;
                        echo "<a href='studentDash.php?page=$nextPage' aria-label='Next Page'>Next</a>";
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
                const classGrid = document.querySelector('.class-grid');
                if (!classGrid) return;

                const cards = classGrid.querySelectorAll('.class-card');

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
                        const draggedCard = classGrid.querySelector(`[data-id="${draggedId}"]`);
                        const dropCard = card;

                        if (draggedCard !== dropCard) {
                            const allCards = [...classGrid.querySelectorAll('.class-card')];
                            const draggedIndex = allCards.indexOf(draggedCard);
                            const dropIndex = allCards.indexOf(dropCard);

                            if (draggedIndex < dropIndex) {
                                classGrid.insertBefore(draggedCard, dropCard.nextSibling);
                            } else {
                                classGrid.insertBefore(draggedCard, dropCard);
                            }

                            const updatedOrder = [...classGrid.querySelectorAll('.class-card')].map(c => c.dataset.id);
                            fetch('save_student_card_order.php', {
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

                    const pendingTaskItems = card.querySelectorAll('.pending-task-item');
                    pendingTaskItems.forEach(item => {
                        item.addEventListener('click', (e) => {
                            e.preventDefault();
                            e.stopPropagation();
                        });
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
$dbConnection = null;
?>
