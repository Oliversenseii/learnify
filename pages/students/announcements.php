<?php
require_once './sessions/session_student.php';
require_once '../../config/db_connection.php';
require_once './auto_logout.php';
require_once './check_status.php';
require_once './academic_events.php';
require_once './check_enrollment_status.php';

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

    $viewMode = isset($_GET['view']) && $_GET['view'] === 'archived' ? 'archived' : 'announcements';

    if (isset($_GET['announcementID']) && isset($_GET['action'])) {
        $announcementID = filter_var($_GET['announcementID'], FILTER_VALIDATE_INT);
        $action = $_GET['action'];
        
        if ($announcementID && in_array($action, ['mark_read', 'archive', 'unarchive'])) {
            if ($action === 'mark_read' && $viewMode === 'announcements') {
                $checkStmt = $dbConnection->prepare("
                    SELECT id FROM notification_views 
                    WHERE userID = :userID AND announcementID = :announcementID AND archived = 0
                ");
                $checkStmt->bindParam(':userID', $userID, PDO::PARAM_INT);
                $checkStmt->bindParam(':announcementID', $announcementID, PDO::PARAM_INT);
                $checkStmt->execute();
                
                if (!$checkStmt->fetch()) {
                    $insertStmt = $dbConnection->prepare("
                        INSERT INTO notification_views (userID, announcementID, archived) 
                        VALUES (:userID, :announcementID, 0)
                    ");
                    $insertStmt->bindParam(':userID', $userID, PDO::PARAM_INT);
                    $insertStmt->bindParam(':announcementID', $announcementID, PDO::PARAM_INT);
                    $insertStmt->execute();
                }
                $_SESSION['success_message'] = "Announcement marked as read.";
            } elseif ($action === 'archive' && $viewMode === 'announcements') {
                $checkStmt = $dbConnection->prepare("
                    SELECT id FROM notification_views 
                    WHERE userID = :userID AND announcementID = :announcementID
                ");
                $checkStmt->bindParam(':userID', $userID, PDO::PARAM_INT);
                $checkStmt->bindParam(':announcementID', $announcementID, PDO::PARAM_INT);
                $checkStmt->execute();
                
                if ($checkStmt->fetch()) {
                    $updateStmt = $dbConnection->prepare("
                        UPDATE notification_views 
                        SET archived = 1 
                        WHERE userID = :userID AND announcementID = :announcementID
                    ");
                    $updateStmt->bindParam(':userID', $userID, PDO::PARAM_INT);
                    $updateStmt->bindParam(':announcementID', $announcementID, PDO::PARAM_INT);
                    $updateStmt->execute();
                } else {
                    $insertStmt = $dbConnection->prepare("
                        INSERT INTO notification_views (userID, announcementID, archived) 
                        VALUES (:userID, :announcementID, 1)
                    ");
                    $insertStmt->bindParam(':userID', $userID, PDO::PARAM_INT);
                    $insertStmt->bindParam(':announcementID', $announcementID, PDO::PARAM_INT);
                    $insertStmt->execute();
                }
                $_SESSION['success_message'] = "Announcement archived.";
            } elseif ($action === 'unarchive' && $viewMode === 'archived') {
                $updateStmt = $dbConnection->prepare("
                    UPDATE notification_views 
                    SET archived = 0 
                    WHERE userID = :userID AND announcementID = :announcementID
                ");
                $updateStmt->bindParam(':userID', $userID, PDO::PARAM_INT);
                $updateStmt->bindParam(':announcementID', $announcementID, PDO::PARAM_INT);
                $updateStmt->execute();
                $_SESSION['success_message'] = "Announcement unarchived.";
            }
            header("Location: ./announcements.php?view=$viewMode");
            exit;
        }
    }

    $announcementQuery = "
        SELECT a.announcementID, a.teacherSectionID, a.title, a.content, a.createdDate, a.archived as announcement_archived,
               a.fileName, a.filePath, a.fileType, a.fileSize,
               CONCAT(u.firstName, ' ', u.lastName) AS teacherName, u.image AS teacherImage,
               s.subjectName,
               CASE 
                   WHEN nv.archived = 1 THEN 2
                   WHEN nv.id IS NOT NULL THEN 1 
                   ELSE 0 
               END as view_status
        FROM announcements a
        JOIN teacher_section ts ON a.teacherSectionID = ts.teacherSectionID
        JOIN users u ON ts.teacherID = u.userID
        JOIN subjects s ON ts.subjectID = s.subjectID
        JOIN student_section ss ON ts.sectionID = ss.sectionID
        LEFT JOIN notification_views nv ON a.announcementID = nv.announcementID AND nv.userID = :userID
        WHERE ss.userID = :userID AND ss.status = 'Enrolled' 
        AND a.archived = 0 AND ts.archived = 0 AND ss.archived = 0
    ";

    if ($viewMode === 'archived') {
        $announcementQuery .= " AND nv.archived = 1";
    } else {
        $announcementQuery .= " AND (nv.archived IS NULL OR nv.archived = 0)";
    }

    $announcementQuery .= " ORDER BY a.createdDate DESC";

    $announcementStmt = $dbConnection->prepare($announcementQuery);
    $announcementStmt->bindParam(':userID', $userID, PDO::PARAM_INT);
    $announcementStmt->execute();
    $announcements = $announcementStmt->fetchAll(PDO::FETCH_ASSOC);

    $unreadCountStmt = $dbConnection->prepare("
        SELECT COUNT(*) as unread_count
        FROM announcements a
        JOIN teacher_section ts ON a.teacherSectionID = ts.teacherSectionID
        JOIN users u ON ts.teacherID = u.userID
        JOIN subjects s ON ts.subjectID = s.subjectID
        JOIN student_section ss ON ts.sectionID = ss.sectionID
        LEFT JOIN notification_views nv ON a.announcementID = nv.announcementID AND nv.userID = :userID
        WHERE ss.userID = :userID AND ss.status = 'Enrolled' AND a.archived = 0 
        AND ts.archived = 0 AND ss.archived = 0 
        AND (nv.archived IS NULL OR nv.archived = 0)
        AND nv.id IS NULL
    ");
    $unreadCountStmt->bindParam(':userID', $userID, PDO::PARAM_INT);
    $unreadCountStmt->execute();
    $unreadCount = $unreadCountStmt->fetch(PDO::FETCH_ASSOC)['unread_count'];

    $archivedCountStmt = $dbConnection->prepare("
        SELECT COUNT(*) as archived_count
        FROM announcements a
        JOIN teacher_section ts ON a.teacherSectionID = ts.teacherSectionID
        JOIN users u ON ts.teacherID = u.userID
        JOIN subjects s ON ts.subjectID = s.subjectID
        JOIN student_section ss ON ts.sectionID = ss.sectionID
        LEFT JOIN notification_views nv ON a.announcementID = nv.announcementID AND nv.userID = :userID
        WHERE ss.userID = :userID AND ss.status = 'Enrolled' AND a.archived = 0 
        AND ts.archived = 0 AND ss.archived = 0 
        AND nv.archived = 1
    ");
    $archivedCountStmt->bindParam(':userID', $userID, PDO::PARAM_INT);
    $archivedCountStmt->execute();
    $archivedCount = $archivedCountStmt->fetch(PDO::FETCH_ASSOC)['archived_count'];

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
    <title>Learnify - <?php echo $viewMode === 'archived' ? 'Archived Notifications' : 'Announcements'; ?></title>

    <!-- YOUR FULL ORIGINAL CSS (UNTOUCHED) -->
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
            --light-blue: #e0f2fe;
            --google-blue: #4285f4;
            --shadow-sm: 0 2px 4px rgba(0, 0, 0, 0.1);
            --shadow-md: 0 4px 12px rgba(0, 0, 0, 0.15);
            --transition: all 0.3s ease;
            --archive-grey: #5f6368;
            --archive-bg: #f1f3f4;
        }

        .announcement-container {
            max-width: 800px;
            margin: clamp(0.5rem, 2vw, 1rem) auto;
            background-color: var(--light);
            border-radius: 8px;
            box-shadow: var(--shadow-sm);
            overflow: hidden;
            box-sizing: border-box;
            width: 100%;
        }

        .gmail-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: clamp(0.5rem, 2vw, 0.75rem) clamp(0.75rem, 2vw, 1rem);
            border-bottom: 1px solid #dadce0;
            flex-wrap: wrap;
            gap: clamp(0.5rem, 1vw, 0.75rem);
        }

        .gmail-header-left {
            display: flex;
            align-items: center;
            gap: clamp(0.5rem, 1.5vw, 0.75rem);
        }

        .announcement-count {
            font-size: clamp(1.1rem, 2vw, 1.2rem);
            color: var(--dark) !important;
            font-weight: 500;
        }

        .gmail-header-right {
            display: flex;
            align-items: center;
            gap: clamp(0.25rem, 1vw, 0.5rem);
        }

        .bulk-action-btn {
            background: none;
            border: 1px solid #dadce0;
            color: var(--dark);
            padding: clamp(0.3rem, 1vw, 0.4rem) clamp(0.5rem, 1.5vw, 0.6rem);
            border-radius: 4px;
            font-size: clamp(1.1rem, 2vw, 1.2rem);
            cursor: pointer;
            transition: var(--transition);
            display: inline-flex;
            align-items: center;
            gap: 0.25rem;
        }

        .bulk-action-btn:hover {
            background: var(--google-blue);
            color: white;
            border-color: var(--google-blue);
        }

        .bulk-action-btn.archive:hover {
            background: var(--archive-grey);
            border-color: var(--archive-grey);
        }

        .bulk-action-btn:disabled {
            opacity: 0.5;
            cursor: not-allowed;
        }

        .tab-container {
            display: flex;
            justify-content: center;
            gap: clamp(0.25rem, 1vw, 0.5rem);
            margin-bottom: clamp(0.5rem, 2vw, 0.75rem);
            padding: 0 clamp(0.75rem, 2vw, 1rem);
            flex-wrap: wrap;
        }

        .tab {
            padding: clamp(0.4rem, 1vw, 0.5rem) clamp(0.75rem, 1.5vw, 0.9rem);
            border: none;
            background: var(--light);
            color: var(--dark);
            font-size: clamp(1.1rem, 2vw, 1.2rem);
            font-weight: 500;
            border-radius: 4px;
            cursor: pointer;
            transition: var(--transition);
            position: relative;
        }

        .tab.active {
            background: var(--google-blue);
            color: white;
        }

        .tab:hover {
            background: white;
            color: var(--blue);
            border: 1px solid var(--blue);
        }

        .tab span.badge {
            background: var(--red);
            color: white;
            border-radius: 50%;
            padding: clamp(0.15rem, 0.5vw, 0.2rem) clamp(0.3rem, 0.8vw, 0.4rem);
            font-size: clamp(1.1rem, 2vw, 1.2rem);
            margin-left: clamp(0.3rem, 0.8vw, 0.4rem);
            vertical-align: middle;
        }

        .status-indicator {
            display: inline-flex;
            align-items: center;
            gap: 0.25rem;
            padding: clamp(0.15rem, 0.5vw, 0.2rem) clamp(0.3rem, 0.8vw, 0.4rem);
            border-radius: 12px;
            font-size: clamp(1.1rem, 2vw, 1.2rem);
            font-weight: 500;
            margin-left: clamp(0.3rem, 0.8vw, 0.4rem);
        }

        .status-unread {
            background: #ffebee;
            color: #d32f2f;
            border: 1px solid #d32f2f;
        }

        .status-read {
            background: #e8f5e8;
            color: #2e7d32;
            border: 1px solid #2e7d32;
        }

        .status-archived {
            background: #f5f5f5;
            color: var(--archive-grey);
            border: 1px solid var(--archive-grey);
        }

        .announcement-row {
            display: flex;
            align-items: flex-start;
            padding: clamp(0.5rem, 2vw, 0.75rem) clamp(0.75rem, 2vw, 1rem);
            border-bottom: 1px solid var(--dark-grey);
            cursor: pointer;
            transition: var(--transition);
            background-color: var(--grey);
            animation: slideIn 0.2s ease-out;
        }

        .announcement-row:hover {
            background: var(--light);
        }

        .announcement-row.unread {
            background-color: var(--grey);
            border-left: 3px solid var(--blue);
        }

        .announcement-row.read {
            background-color: var(--grey);
            border-left: 3px solid var(--green);
        }

        .announcement-row.archived {
            background-color: var(--grey);
            border-left: 3px solid var(--archive-grey);
            opacity: 0.7;
        }

        .icon-column {
            flex: 0 0 clamp(30px, 8vw, 40px);
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .announcement-icon {
            font-size: clamp(1.5rem, 3vw, 2rem);
            border: 1px solid var(--blue);
            border-radius: 50%;
            padding: clamp(6px, 1.5vw, 8px);
            margin-right: clamp(6px, 1.5vw, 8px);
        }

        .announcement-icon.unread {
            color: var(--blue);
        }

        .announcement-icon.read {
            color: var(--green);
        }

        .announcement-icon.archived {
            color: var(--archive-grey);
        }

        .content-column {
            flex: 1;
            min-width: 0;
            padding-right: clamp(0.5rem, 1.5vw, 0.75rem);
        }

        .announcement-header {
            display: flex;
            align-items: center;
            gap: clamp(0.5rem, 1.5vw, 0.75rem);
            margin-bottom: clamp(0.15rem, 0.5vw, 0.25rem);
            flex-wrap: wrap;
        }

        .announcement-header img {
            width: clamp(24px, 6vw, 28px);
            height: clamp(24px, 6vw, 28px);
            border-radius: 50%;
            object-fit: cover;
            flex-shrink: 0;
        }

        .sender-info {
            display: flex;
            align-items: center;
            gap: clamp(0.3rem, 1vw, 0.5rem);
            flex: 1;
            min-width: 0;
            flex-wrap: wrap;
        }

        .sender-name {
            font-weight: 500;
            color: var(--dark);
            font-size: clamp(1rem, 2vw, 1.2rem);
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .content-container {
            padding: 10px;
            background-color: var(--light);
            margin-bottom: 10px;
        }

        .subject-preview {
            display: flex;
            align-items: flex-start;
            gap: clamp(0.5rem, 1.5vw, 0.75rem);
            margin-bottom: clamp(0.15rem, 0.5vw, 0.25rem);
        }

        .subject-title {
            font-weight: 600;
            color: var(--dark);
            font-size: clamp(1.1rem, 2vw, 1.6rem);
            margin: 0;
            flex: 1;
            min-width: 0;
            line-height: 1.3;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .subject-title.unread {
            font-weight: 500;
        }

        .subject-title.archived {
            color: var(--archive-grey);
            font-style: italic;
        }

        .unread-indicator {
            width: clamp(6px, 1.5vw, 8px);
            height: clamp(6px, 1.5vw, 8px);
            background: var(--blue);
            border-radius: 50%;
            flex-shrink: 0;
            margin-top: clamp(0.2rem, 0.5vw, 0.3rem);
        }

        .content-preview {
            color: var(--dark);
            font-size: clamp(1.1rem, 2vw, 1.2rem);
            line-height: 1.4;
            margin: 0;
            display: -webkit-box;
            -webkit-box-orient: vertical;
            overflow: hidden;
            word-break: break-word;
        }

        .content-preview.unread {
            font-weight: 400;
        }

        .content-preview.archived {
            color: var(--archive-grey);
            font-style: italic;
        }

        .action-buttons {
            display: flex;
            gap: clamp(0.3rem, 1vw, 0.5rem);
            margin-top: clamp(0.3rem, 1vw, 0.5rem);
            flex-wrap: wrap;
        }

        .action-btn {
            background: none;
            border: 1px solid #dadce0;
            color: var(--dark);
            padding: clamp(0.2rem, 0.8vw, 0.3rem) clamp(0.4rem, 1vw, 0.5rem);
            border-radius: 4px;
            font-size: clamp(1.1rem, 2vw, 1.2rem);
            cursor: pointer;
            transition: var(--transition);
            display: inline-flex;
            align-items: center;
            gap: 0.25rem;
            font-family: 'Google Sans', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
        }

        .action-btn:hover {
            background: var(--blue);
            color: white;
            border-color: var(--blue);
        }

        .action-btn.archive:hover {
            background: var(--archive-grey);
            border-color: var(--archive-grey);
            color: white;
        }

        .action-btn.read {
            border-color: var(--green);
            color: var(--green);
        }

        .action-btn.read:hover {
            background: var(--green);
            color: white;
            border-color: var(--green);
        }

        .action-btn.unarchive {
            border-color: var(--archive-grey) !important;
            color: var(--archive-grey);
        }

        .action-btn.unarchive:hover {
            background: var(--purple);
            color: white;
            border-color: var(--purple);
        }

        .action-btn.archived {
            opacity: 0.5;
            cursor: not-allowed;
        }

        .date-column {
            flex: 0 0 clamp(60px, 15vw, 80px);
            text-align: right;
            font-size: clamp(1.1rem, 2vw, 1.2rem);
            color: var(--dark);
            display: flex;
            flex-direction: column;
            align-items: flex-end;
        }

        .date-text {
            white-space: nowrap;
        }

        .status-badge {
            font-size: clamp(1.1rem, 2vw, 1.2rem);
            padding: clamp(0.1rem, 0.5vw, 0.15rem) clamp(0.2rem, 0.8vw, 0.3rem);
            border-radius: 3px;
            background: var(--light-blue);
            color: var(--blue);
        }

        .status-badge.archived {
            background: var(--archive-bg);
            color: var(--archive-grey);
        }

        .section-header {
            font-size: clamp(1.1rem, 2vw, 1.2rem);
            color: var(--dark-grey);
            margin: clamp(0.75rem, 2vw, 1rem) 0 clamp(0.3rem, 1vw, 0.5rem);
            padding: clamp(0.5rem, 1.5vw, 0.75rem) clamp(0.75rem, 2vw, 1rem);
            background: var(--light);
            font-weight: 500;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            border-left: 3px solid transparent;
        }

        .section-header:first-child {
            margin-top: 0;
        }

        .empty-state {
            text-align: center;
            padding: clamp(1.5rem, 4vw, 2rem) clamp(1rem, 3vw, 1.5rem);
            color: var(--dark-grey);
            font-family: 'Google Sans', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
        }

        .empty-state i {
            font-size: clamp(2rem, 5vw, 2.5rem);
            margin-bottom: clamp(0.5rem, 1.5vw, 0.75rem);
            opacity: 0.5;
            color: var(--dark-grey);
        }

        .empty-state h3 {
            font-size: clamp(1.1rem, 2vw, 1.2rem);
            margin-bottom: clamp(0.3rem, 1vw, 0.5rem);
            color: var(--dark);
            font-weight: 400;
        }

        .empty-state p {
            font-size: clamp(1.1rem, 2vw, 1.2rem);
            color: var(--dark-grey);
        }

        .success-notification, .error-notification {
            padding: clamp(0.5rem, 1.5vw, 0.75rem) clamp(0.75rem, 2vw, 1rem);
            margin: clamp(0.5rem, 2vw, 1rem) auto;
            border-radius: 8px;
            max-width: 800px;
            font-family: 'Google Sans', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
        }

        .success-notification {
            background: #e8f5e8;
            color: #155724;
            border: 1px solid #c3e6c3;
        }

        .error-notification {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }

        #ann-count, .dashboard-header-badge {
            background: var(--red);
            color: white;
            border-radius: 50%;
            padding: clamp(0.15rem, 0.5vw, 0.25rem) clamp(0.3rem, 0.8vw, 0.5rem);
            font-size: clamp(1.1rem, 2vw, 1.2rem);
            font-weight: 600;
            min-width: 15px;
            text-align: center;
            margin-left: clamp(0.3rem, 0.8vw, 0.5rem);
            display: inline-flex;
            align-items: center;
            justify-content: center;
        }

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

        @keyframes slideIn {
            from {
                opacity: 0;
                transform: translateX(-10px);
            }
            to {
                opacity: 1;
                transform: translateX(0);
            }
        }

        @media screen and (max-width: 768px) {
            .announcement-container {
                padding: 0;
                margin: 0;
                margin-top: 10px;
            }

            .gmail-header {
                flex-direction: column;
                align-items: flex-start;
                padding: clamp(0.5rem, 1.5vw, 0.75rem);
            }

            .gmail-header-right {
                width: 100%;
                justify-content: flex-start;
            }

            .announcement-row {
                flex-direction: column;
                align-items: stretch;
                padding: clamp(0.5rem, 1.5vw, 0.75rem);
            }

            .icon-column {
                flex: 0 0 auto;
                align-self: flex-start;
            }

            .content-column {
                padding-right: 0;
            }

            .announcement-header {
                flex-direction: column;
                align-items: flex-start;
            }

            .sender-info {
                flex-direction: column;
                align-items: flex-start;
            }

            .date-column {
                flex: 0 0 auto;
                text-align: left;
                margin-top: clamp(0.3rem, 1vw, 0.5rem);
            }

            .section-header {
                padding: clamp(0.3rem, 1vw, 0.5rem) clamp(0.5rem, 1.5vw, 0.75rem);
            }

            .action-buttons {
                justify-content: flex-start;
            }
        }

        /* ==================== FILE PREVIEW MODAL (NEW) ==================== */
        #file-viewer-modal {
            position: fixed;
            top: 0; left: 0;
            width: 100%; height: 100%;
            background: rgba(0,0,0,0.85);
            z-index: 9999;
            display: flex;
            justify-content: center;
            align-items: center;
            backdrop-filter: blur(5px);
            padding: 1rem;
            box-sizing: border-box;
            display: none;
        }

        #file-viewer-modal .modal-content {
            background: var(--light);
            border-radius: 16px;
            width: 100%;
            height: 100%;
            overflow: hidden;
            box-shadow: 0 20px 60px rgba(0,0,0,0.3);
            display: flex;
            flex-direction: column;
        }

        #file-viewer-modal .modal-header {
            padding: 16px 20px;
            border-bottom: 1px solid #ccc;
            padding-bottom: 10px;
            color: var(--dark);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        #file-viewer-modal .modal-header h3 {
            margin: 0;
            font-size: 2rem;
            font-weight: 500;
        }

        #file-viewer-modal .close-btn {
            background: none;
            border: none;
            color: var(--dark);
            font-size: 2.5rem;
            cursor: pointer;
            width: 40px;
            height: 40px;
            display: flex;
            align-items: center;
            justify-content: center;
        }

         #file-viewer-modal .close-btn:hover {
            color: red;
         }

        #file-viewer-modal .modal-body {
            padding: 20px;
            overflow: auto;
            flex: 1;
            text-align: center;
        }

        #file-viewer-modal img {
            max-width: 100%;
            max-height: 80vh;
            border-radius: 8px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.3);
        }

        #file-viewer-modal embed {
            width: 100%;
            height: 700px;
            border: none;
        }

        .announcement-file img {
            max-width: 80px;
            max-height: 80px;
            border-radius: 8px;
            cursor: pointer;
            object-fit: cover;
            border: 1px solid #dadce0;
            transition: transform 0.2s;
        }

        .announcement-file img:hover {
            transform: scale(1.05);
        }

        .btn-preview {
            background: transparent;
            border: 1px solid #1a73e8;
            color: #1a73e8;
            padding: 6px 12px;
            border-radius: 6px;
            font-size: 0.95rem;
            cursor: pointer;
            transition: all 0.2s;
            display: inline-flex;
            align-items: center;
            gap: 6px;
        }

        .btn-preview:hover {
            background: #1a73e8;
            color: white;
        }
    </style>
</head>
<body>
    <!-- SIDEBAR -->
    <section id="sidebar">
        <?php require_once './brand.php' ?>
        <ul class="side-menu top">
            <?php require_once './dashboard_nav_item.php' ?>
            <li class="<?php echo $viewMode === 'announcements' ? 'active' : ''; ?>">
                <a href="./announcements.php?view=announcements">
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

    <!-- CONTENT -->
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
                    <h1><?php echo $viewMode === 'archived' ? 'Archived Notifications' : 'Announcements'; ?></h1>
                    <ul class="breadcrumb">
                        <li><a href="./studentDash.php">Home</a></li>
                        <li><i class='bx bx-chevron-right'></i></li>
                        <li><a class="active" href="./announcements.php?view=<?php echo $viewMode; ?>">
                            <?php echo $viewMode === 'archived' ? 'Archived Notifications' : 'Announcements'; ?>
                        </a></li>
                    </ul>
                </div>
            </div>

            <!-- TABS -->
            <div class="tab-container">
                <button class="tab <?php echo $viewMode === 'announcements' ? 'active' : ''; ?>" 
                        onclick="window.location.href='./announcements.php?view=announcements'">
                    Announcements
                    <?php if ($unreadCount > 0): ?>
                        <span class="badge" id="ann-count"><?php echo $unreadCount; ?></span>
                    <?php endif; ?>
                </button>
                <button class="tab <?php echo $viewMode === 'archived' ? 'active' : ''; ?>" 
                        onclick="window.location.href='./announcements.php?view=archived'">
                    Archived
                    <?php if ($archivedCount > 0): ?>
                        <span class="badge"><?php echo $archivedCount; ?></span>
                    <?php endif; ?>
                </button>
            </div>

            <!-- NOTIFICATIONS -->
            <?php if (isset($_SESSION['success_message'])): ?>
                <div class="success-notification">
                    <?php echo htmlspecialchars($_SESSION['success_message']); unset($_SESSION['success_message']); ?>
                </div>
            <?php endif; ?>
            <?php if (isset($_SESSION['error_message'])): ?>
                <div class="error-notification">
                    <?php echo htmlspecialchars($_SESSION['error_message']); unset($_SESSION['error_message']); ?>
                </div>
            <?php endif; ?>

            <div id="announcement-list" class="announcement-container">
                <!-- Header -->
                <div class="gmail-header">
                    <div class="gmail-header-left">
                        <span class="announcement-count">
                            <?php echo count($announcements); ?> 
                            <?php echo count($announcements) === 1 ? 'announcement' : 'announcements'; ?>
                        </span>
                    </div>
                    <div class="gmail-header-right">
                        <?php if ($viewMode === 'announcements'): ?>
                            <button class="bulk-action-btn archive" onclick="archiveAll()" id="archive-btn">
                                <i class='bx bx-archive'></i> Archive All
                            </button>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Announcements List -->
                <?php if (!empty($announcements)): ?>
                    <div class="section-header">
                        <i class='bx <?php echo $viewMode === 'archived' ? 'bx-archive' : 'bxs-news'; ?>'></i> 
                        <?php echo $viewMode === 'archived' ? 'Archived Notifications' : 'Announcements'; ?>
                    </div>
                    <?php foreach ($announcements as $announcement): ?>
                        <?php 
                        $statusClass = '';
                        $statusIcon = '';
                        $statusText = '';
                        $isArchived = false;
                        
                        if ($announcement['view_status'] == 2) {
                            $statusClass = 'archived';
                            $statusIcon = 'bx-archive';
                            $statusText = 'Archived';
                            $isArchived = true;
                        } elseif ($announcement['view_status'] == 1) {
                            $statusClass = 'read';
                            $statusIcon = 'bx-check-circle';
                            $statusText = 'Read';
                        } else {
                            $statusClass = 'unread';
                            $statusIcon = 'bx-news';
                            $statusText = 'New';
                        }
                        ?>
                        <div class="announcement-row <?php echo $statusClass; ?>" 
                             data-announcement-id="<?php echo $announcement['announcementID']; ?>"
                             data-view-status="<?php echo $announcement['view_status']; ?>">
                            <div class="icon-column">
                                <i class='bx <?php echo $statusIcon; ?> announcement-icon <?php echo $statusClass; ?>'></i>
                            </div>
                            <div class="content-column" onclick="viewAnnouncement(<?php echo $announcement['announcementID']; ?>)">
                                <div class="announcement-header">
                                    <img src="<?php echo $announcement['teacherImage'] ? htmlspecialchars($announcement['teacherImage']) : './img/noprofile.png'; ?>" 
                                         alt="Teacher Image" loading="lazy">
                                    <div class="sender-info">
                                        <span class="sender-name"><?php echo htmlspecialchars($announcement['teacherName']); ?></span>
                                        <span class="sender-name" style="font-weight: normal; color: #4A5568; font-size: 1.1rem;">
                                            • <?php echo htmlspecialchars($announcement['subjectName']); ?>
                                        </span>
                                        <?php if ($announcement['view_status'] == 0): ?>
                                            <span class="status-indicator status-unread">
                                                <i class='bx bx-dot'></i> New
                                            </span>
                                        <?php elseif ($announcement['view_status'] == 2): ?>
                                            <span class="status-indicator status-archived">
                                                <i class='bx bx-archive'></i> Archived
                                            </span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                <br>
                                <div class="content-container">
                                    <div class="subject-preview">
                                        <h3 class="subject-title <?php echo $statusClass; ?>">
                                            <?php echo htmlspecialchars($announcement['title']); ?>
                                        </h3>
                                        <?php if ($announcement['view_status'] == 0): ?>
                                            <div class="unread-indicator"></div>
                                        <?php endif; ?>
                                    </div>
                                    <p class="content-preview <?php echo $statusClass; ?>">
                                        <?php 
                                        $contentPreview = strip_tags($announcement['content']);
                                        echo strlen($contentPreview) > 120 ? substr($contentPreview, 0, 120) . '...' : $contentPreview;
                                        ?>
                                    </p>
                                </div>
                            
                                <?php if ($announcement['filePath']): ?>
                                    <div class="announcement-file">
                                        <?php
                                        $isImage = in_array($announcement['fileType'], ['image/jpeg', 'image/png', 'image/jpg', 'image/gif', 'image/webp']);
                                        $isPdf = $announcement['fileType'] === 'application/pdf';
                                        $fileIcon = "<i class='bx bxs-file' style='font-size: 1.8rem; color: #5f6368;'></i>";
                                        if ($isPdf) $fileIcon = "<i class='bx bxs-file-pdf' style='font-size: 1.8rem; color: #b71c1c;'></i>";
                                        if ($isImage) $fileIcon = "<i class='bx bxs-file-image' style='font-size: 1.8rem; color: #1a73e8;'></i>";
                                        ?>

                                        <?php if ($isImage): ?>
                                            <img src="<?php echo htmlspecialchars($announcement['filePath']); ?>" 
                                                 alt="Preview" 
                                                 onclick="event.stopPropagation(); openFileViewer('<?php echo htmlspecialchars($announcement['filePath']); ?>', '<?php echo htmlspecialchars($announcement['fileName']); ?>', '<?php echo $announcement['fileType']; ?>')">
                                        <?php elseif ($isPdf): ?>
                                            <button class="btn-preview" 
                                                    onclick="event.stopPropagation(); openFileViewer('<?php echo htmlspecialchars($announcement['filePath']); ?>', '<?php echo htmlspecialchars($announcement['fileName']); ?>', '<?php echo $announcement['fileType']; ?>')">
                                                <i class='bx bx-show'></i> Preview
                                            </button>
                                        <?php endif; ?>

                                        <a href="<?php echo htmlspecialchars($announcement['filePath']); ?>" 
                                           download 
                                           style="display: inline-flex; align-items: center; gap: 6px; color: #1a73e8; text-decoration: none; font-size: 1.1rem;">
                                            <?php echo $fileIcon; ?>
                                            <span style="max-width: 180px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;">
                                                <?php echo htmlspecialchars($announcement['fileName']); ?>
                                            </span>
                                        </a>
                                    </div>
                                <?php endif; ?>

                                <div class="action-buttons">
                                    <?php if ($viewMode === 'announcements' && $announcement['view_status'] == 0): ?>
                                        <button class="action-btn read" onclick="event.stopPropagation(); markAsRead(<?php echo $announcement['announcementID']; ?>)">
                                            <i class='bx bx-check-circle'></i> Mark as read
                                        </button>
                                        <button class="action-btn archive" onclick="event.stopPropagation(); archiveAnnouncement(<?php echo $announcement['announcementID']; ?>)">
                                            <i class='bx bx-archive'></i> Archive
                                        </button>
                                    <?php elseif ($viewMode === 'announcements' && $announcement['view_status'] == 1): ?>
                                        <button class="action-btn archive" onclick="event.stopPropagation(); archiveAnnouncement(<?php echo $announcement['announcementID']; ?>)">
                                            <i class='bx bx-archive'></i> Archive
                                        </button>
                                    <?php elseif ($viewMode === 'archived'): ?>
                                        <button class="action-btn unarchive" onclick="event.stopPropagation(); unarchiveAnnouncement(<?php echo $announcement['announcementID']; ?>)">
                                            <i class='bx bx-undo'></i> Unarchive
                                        </button>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <div class="date-column">
                                <div class="date-text">
                                    <?php 
                                    $date = new DateTime($announcement['createdDate']);
                                    $now = new DateTime();
                                    $interval = $now->diff($date);
                                    
                                    if ($interval->days == 0) {
                                        echo $date->format('g:i A');
                                    } elseif ($interval->days < 7) {
                                        echo $date->format('M j');
                                    } else {
                                        echo $date->format('M j, Y');
                                    }
                                    ?>
                                </div>
                                <?php if ($announcement['view_status'] == 1): ?>
                                    <span class="status-badge">Read</span>
                                <?php elseif ($announcement['view_status'] == 2): ?>
                                    <span class="status-badge archived">Archived</span>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div class="empty-state">
                        <i class='bx bx-news-off'></i>
                        <h3>No <?php echo $viewMode === 'archived' ? 'archived notifications' : 'announcements'; ?></h3>
                        <p>Check back later for updates from your teachers.</p>
                    </div>
                <?php endif; ?>
            </div>
        </main>
    </section>

    <!-- FILE VIEWER MODAL -->
    <div id="file-viewer-modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3 id="file-viewer-title">File Preview</h3>
                <button class="close-btn" onclick="closeFileViewer()">×</button>
            </div>
            <div class="modal-body">
                <div id="file-viewer-content"></div>
            </div>
        </div>
    </div>

    <script>
        const viewMode = '<?php echo $viewMode; ?>';

        function viewAnnouncement(announcementId) {
            const row = document.querySelector(`[data-announcement-id="${announcementId}"]`);
            const viewStatus = parseInt(row.dataset.viewStatus);
            
            if (viewMode === 'announcements' && viewStatus === 0) {
                markAsRead(announcementId);
            }
        }

        function markAsRead(announcementId) {
            fetch(`?announcementID=${announcementId}&action=mark_read&view=${viewMode}`, {
                method: 'GET',
                headers: { 'X-Requested-With': 'XMLHttpRequest' }
            }).then(() => {
                updateAnnouncementStatus(announcementId, 'mark_read');
                updateNotificationBadge();
            });
        }

        function archiveAnnouncement(announcementId) {
            if (confirm('Archive this announcement?')) {
                fetch(`?announcementID=${announcementId}&action=archive&view=${viewMode}`, {
                    method: 'GET'
                }).then(() => {
                    showSuccessMessage('Announcement archived');
                    setTimeout(() => location.reload(), 1000);
                });
            }
        }

        function unarchiveAnnouncement(announcementId) {
            if (confirm('Unarchive this announcement?')) {
                fetch(`?announcementID=${announcementId}&action=unarchive&view=${viewMode}`, {
                    method: 'GET'
                }).then(() => {
                    showSuccessMessage('Announcement unarchived');
                    setTimeout(() => location.reload(), 1000);
                });
            }
        }

        function archiveAll() {
            if (confirm('Archive all announcements?')) {
                document.querySelectorAll('.announcement-row:not(.archived)').forEach(row => {
                    const id = row.dataset.announcementId;
                    fetch(`?announcementID=${id}&action=archive&view=${viewMode}`, { method: 'GET' });
                });
                showSuccessMessage('All announcements archived');
                setTimeout(() => location.reload(), 1000);
            }
        }

        function updateAnnouncementStatus(announcementId, action) {
            const row = document.querySelector(`[data-announcement-id="${announcementId}"]`);
            if (!row) return;

            if (action === 'mark_read') {
                row.classList.remove('unread'); row.classList.add('read');
                row.querySelector('.announcement-icon').className = 'bx bx-check-circle announcement-icon read';
                row.querySelector('.unread-indicator')?.remove();
                row.querySelector('.subject-title').classList.remove('unread');
                row.querySelector('.action-buttons').innerHTML = `<button class="action-btn archive" onclick="event.stopPropagation(); archiveAnnouncement(${announcementId})"><i class='bx bx-archive'></i> Archive</button>`;
                row.querySelector('.sender-info .status-indicator')?.remove();
                const dateCol = row.querySelector('.date-column');
                if (!dateCol.querySelector('.status-badge')) {
                    dateCol.insertAdjacentHTML('beforeend', '<span class="status-badge">Read</span>');
                }
            }
        }

        function showSuccessMessage(msg) {
            const div = document.createElement('div');
            div.className = 'success-notification';
            div.textContent = msg;
            document.querySelector('main').insertBefore(div, document.querySelector('.announcement-container'));
            setTimeout(() => div.remove(), 3000);
        }

        function updateNotificationBadge() {
            const badge = document.getElementById('ann-count');
            if (badge) {
                const count = document.querySelectorAll('.announcement-row.unread').length;
                badge.textContent = count;
                badge.style.display = count > 0 ? 'inline-flex' : 'none';
            }
        }

        // FILE VIEWER
        document.addEventListener('DOMContentLoaded', function () {
            window.openFileViewer = function(src, name, type) {
                const modal = document.getElementById('file-viewer-modal');
                const content = document.getElementById('file-viewer-content');
                const title = document.getElementById('file-viewer-title');

                if (!modal || !content || !title) return;

                title.textContent = name;
                content.innerHTML = '';

                if (type.includes('pdf')) {
                    content.innerHTML = `<embed src="${src}#toolbar=0&navpanes=0&scrollbar=0" type="application/pdf">`;
                } else if (type.includes('image')) {
                    content.innerHTML = `<img src="${src}" alt="${name}">`;
                }

                modal.style.display = 'flex';
                document.body.style.overflow = 'hidden';
            };

            window.closeFileViewer = function() {
                const modal = document.getElementById('file-viewer-modal');
                if (modal) {
                    modal.style.display = 'none';
                    document.body.style.overflow = 'auto';
                }
            };

            document.getElementById('file-viewer-modal')?.addEventListener('click', function(e) {
                if (e.target === this) closeFileViewer();
            });

            document.addEventListener('keydown', function(e) {
                if (e.key === 'Escape') closeFileViewer();
            });

            updateNotificationBadge();
        });
    </script>
    <script src="./utils/script.js"></script>
</body>
</html>