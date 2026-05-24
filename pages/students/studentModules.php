<?php
date_default_timezone_set('Asia/Manila');

function formatPst(string $datetime, string $format = 'M j, Y g:i A'): string {
    $dt = new DateTime($datetime, new DateTimeZone('Asia/Manila'));
    return $dt->format($format);
}
require_once './sessions/session_student.php';
require_once '../../config/db_connection.php';
require_once './auto_logout.php';
require_once './check_status.php';
require_once './academic_events.php';
require_once './check_enrollment_status.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
if (!isset($_SESSION['image'])) {
    $_SESSION['image'] = './img/noprofile.png'; 
}

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

if (!isset($_GET['teacherSectionID']) || !filter_var($_GET['teacherSectionID'], FILTER_VALIDATE_INT)) {
    $_SESSION['error_message'] = "Invalid section ID.";
    header("Location: studentDash.php");
    exit;
}
$teacherSectionID = (int)$_GET['teacherSectionID'];
$moduleID = isset($_GET['moduleID']) ? filter_var($_GET['moduleID'], FILTER_VALIDATE_INT) : 0;

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

    $checkStmt = $dbConnection->prepare("
        SELECT ts.sectionID, s.sectionName, sub.subjectName
        FROM teacher_section ts 
        JOIN sections s ON ts.sectionID = s.sectionID 
        JOIN subjects sub ON ts.subjectID = sub.subjectID 
        JOIN student_section ss ON ts.sectionID = ss.sectionID 
        WHERE ts.teacherSectionID = :teacherSectionID AND ss.userID = :userID 
        AND ts.archived = 0 AND ss.archived = 0 AND s.archived = 0 AND sub.archived = 0 AND ss.status = 'Enrolled'
    ");
    $checkStmt->bindParam(':teacherSectionID', $teacherSectionID, PDO::PARAM_INT);
    $checkStmt->bindParam(':userID', $userID, PDO::PARAM_INT);
    $checkStmt->execute();
    $section = $checkStmt->fetch(PDO::FETCH_ASSOC);
    if (!$section) {
        $_SESSION['error_message'] = "You are not enrolled in this section.";
        header("Location: studentDash.php");
        exit;
    }

    if (isset($_POST['addComment']) && $moduleID) {
        $comment = isset($_POST['comment']) ? trim($_POST['comment']) : '';
        $parentCommentID = isset($_POST['parentCommentID']) ? filter_var($_POST['parentCommentID'], FILTER_VALIDATE_INT) : null;

        if ($comment) {
            try {
                $dbConnection->beginTransaction();

                $validateModule = $dbConnection->prepare("SELECT moduleID FROM modules m JOIN teacher_section ts ON m.teacherSectionID = ts.teacherSectionID JOIN student_section ss ON ts.sectionID = ss.sectionID WHERE m.moduleID = :moduleID AND ts.teacherSectionID = :teacherSectionID AND ss.userID = :userID AND m.archived = 0 AND ts.archived = 0 AND ss.status = 'Enrolled'");
                $validateModule->bindParam(':moduleID', $moduleID, PDO::PARAM_INT);
                $validateModule->bindParam(':teacherSectionID', $teacherSectionID, PDO::PARAM_INT);
                $validateModule->bindParam(':userID', $userID, PDO::PARAM_INT);
                $validateModule->execute();
                if ($validateModule->rowCount() > 0) {
                    $insertStmt = $dbConnection->prepare("INSERT INTO module_comments (moduleID, userID, comment, parentCommentID) VALUES (:moduleID, :userID, :comment, :parentCommentID)");
                    $insertStmt->bindParam(':moduleID', $moduleID, PDO::PARAM_INT);
                    $insertStmt->bindParam(':userID', $userID, PDO::PARAM_INT);
                    $insertStmt->bindParam(':comment', $comment, PDO::PARAM_STR);
                    $insertStmt->bindParam(':parentCommentID', $parentCommentID, PDO::PARAM_INT);
                    $insertStmt->execute();

                    // Get the inserted comment ID
                    $newCommentID = $dbConnection->lastInsertId();

                    // Notify relevant users
                    if ($parentCommentID) {
                        $parentStmt = $dbConnection->prepare("SELECT userID FROM module_comments WHERE commentID = :parentCommentID");
                        $parentStmt->bindParam(':parentCommentID', $parentCommentID, PDO::PARAM_INT);
                        $parentStmt->execute();
                        $parentUser = $parentStmt->fetch(PDO::FETCH_ASSOC);
                        if ($parentUser && $parentUser['userID'] != $userID) {
                            $notifyStmt = $dbConnection->prepare("INSERT INTO comment_notifications (commentID, notifiedUserID) VALUES (:commentID, :notifiedUserID)");
                            $notifyStmt->bindParam(':commentID', $newCommentID, PDO::PARAM_INT);
                            $notifyStmt->bindParam(':notifiedUserID', $parentUser['userID'], PDO::PARAM_INT);
                            $notifyStmt->execute();
                        }
                    }

                    // Notify teacher
                    $moduleStmt = $dbConnection->prepare("SELECT ts.teacherID FROM modules m JOIN teacher_section ts ON m.teacherSectionID = ts.teacherSectionID WHERE m.moduleID = :moduleID");
                    $moduleStmt->bindParam(':moduleID', $moduleID, PDO::PARAM_INT);
                    $moduleStmt->execute();
                    $teacher = $moduleStmt->fetch(PDO::FETCH_ASSOC);
                    if ($teacher && $teacher['teacherID'] != $userID) {
                        $notifyStmt = $dbConnection->prepare("INSERT INTO comment_notifications (commentID, notifiedUserID) VALUES (:commentID, :notifiedUserID)");
                        $notifyStmt->bindParam(':commentID', $newCommentID, PDO::PARAM_INT);
                        $notifyStmt->bindParam(':notifiedUserID', $teacher['teacherID'], PDO::PARAM_INT);
                        $notifyStmt->execute();
                    }

                    // Commit transaction
                    $dbConnection->commit();
                    $_SESSION['success_message'] = "Comment added successfully.";
                } else {
                    $dbConnection->rollBack();
                    $_SESSION['error_message'] = "Invalid module.";
                }
            } catch (PDOException $e) {
                $dbConnection->rollBack();
                error_log("Comment Addition Failed: " . $e->getMessage());
                $_SESSION['error_message'] = "Failed to add comment: " . htmlspecialchars($e->getMessage());
            }
        } else {
            $_SESSION['error_message'] = "Comment cannot be empty.";
        }
        header("Location: studentModules.php?teacherSectionID=$teacherSectionID&moduleID=$moduleID");
        exit;
    }

    // Handle comment editing
    if (isset($_POST['editComment']) && isset($_POST['commentID'])) {
        $commentID = filter_var($_POST['commentID'], FILTER_VALIDATE_INT);
        $comment = isset($_POST['comment']) ? trim($_POST['comment']) : '';

        if ($commentID && $comment) {
            $updateStmt = $dbConnection->prepare("UPDATE module_comments SET comment = :comment WHERE commentID = :commentID AND userID = :userID AND archived = 0");
            $updateStmt->bindParam(':comment', $comment, PDO::PARAM_STR);
            $updateStmt->bindParam(':commentID', $commentID, PDO::PARAM_INT);
            $updateStmt->bindParam(':userID', $userID, PDO::PARAM_INT);
            if ($updateStmt->execute() && $updateStmt->rowCount() > 0) {
                $_SESSION['success_message'] = "Comment updated successfully.";
            } else {
                $_SESSION['error_message'] = "Failed to update comment or not authorized.";
            }
        } else {
            $_SESSION['error_message'] = "Invalid input.";
        }
        header("Location: studentModules.php?teacherSectionID=$teacherSectionID&moduleID=$moduleID");
        exit;
    }

    // Handle comment archiving
    if (isset($_POST['archiveComment']) && isset($_POST['commentID'])) {
        $commentID = filter_var($_POST['commentID'], FILTER_VALIDATE_INT);

        if ($commentID) {
            try {
                $dbConnection->beginTransaction();
                $archiveStmt = $dbConnection->prepare("UPDATE module_comments SET archived = 1 WHERE commentID = :commentID AND userID = :userID");
                $archiveStmt->bindParam(':commentID', $commentID, PDO::PARAM_INT);
                $archiveStmt->bindParam(':userID', $userID, PDO::PARAM_INT);
                if ($archiveStmt->execute() && $archiveStmt->rowCount() > 0) {
                    // Archive child comments (replies) if any
                    $archiveChildrenStmt = $dbConnection->prepare("UPDATE module_comments SET archived = 1 WHERE parentCommentID = :commentID");
                    $archiveChildrenStmt->bindParam(':commentID', $commentID, PDO::PARAM_INT);
                    $archiveChildrenStmt->execute();
                    $dbConnection->commit();
                    $_SESSION['success_message'] = "Comment archived successfully.";
                } else {
                    $dbConnection->rollBack();
                    $_SESSION['error_message'] = "Failed to archive comment or not authorized.";
                }
            } catch (PDOException $e) {
                $dbConnection->rollBack();
                error_log("Comment Archiving Failed: " . $e->getMessage());
                $_SESSION['error_message'] = "Failed to archive comment: " . htmlspecialchars($e->getMessage());
            }
        } else {
            $_SESSION['error_message'] = "Invalid comment ID.";
        }
        header("Location: studentModules.php?teacherSectionID=$teacherSectionID&moduleID=$moduleID");
        exit;
    }

    // Fetch specific module if moduleID set
    $singleModule = null;
    $commentsTree = [];
    $totalComments = 0;
    $unreadNotifications = 0;
    if ($moduleID) {
        $singleModuleStmt = $dbConnection->prepare("
            SELECT moduleID, fileName, filePath, fileType, fileSize, description, uploadDate 
            FROM modules 
            WHERE moduleID = :moduleID AND teacherSectionID = :teacherSectionID AND archived = 0
        ");
        $singleModuleStmt->bindParam(':moduleID', $moduleID, PDO::PARAM_INT);
        $singleModuleStmt->bindParam(':teacherSectionID', $teacherSectionID, PDO::PARAM_INT);
        $singleModuleStmt->execute();
        $singleModule = $singleModuleStmt->fetch(PDO::FETCH_ASSOC);

        if (!$singleModule) {
            $_SESSION['error_message'] = "Module not found.";
            header("Location: studentModules.php?teacherSectionID=$teacherSectionID");
            exit;
        }

        // Update last viewed for comments
        $updateView = $dbConnection->prepare("INSERT INTO module_comment_views (userID, moduleID, last_viewed) VALUES (:userID, :moduleID, NOW()) ON DUPLICATE KEY UPDATE last_viewed = NOW()");
        $updateView->bindParam(':userID', $userID, PDO::PARAM_INT);
        $updateView->bindParam(':moduleID', $moduleID, PDO::PARAM_INT);
        $updateView->execute();

        // Mark notifications as seen
        $markSeen = $dbConnection->prepare("UPDATE comment_notifications SET seen = 1 WHERE notifiedUserID = :userID AND commentID IN (SELECT commentID FROM module_comments WHERE moduleID = :moduleID)");
        $markSeen->bindParam(':userID', $userID, PDO::PARAM_INT);
        $markSeen->bindParam(':moduleID', $moduleID, PDO::PARAM_INT);
        $markSeen->execute();

        // Fetch all comments for the module
        $commentsStmt = $dbConnection->prepare("
            SELECT mc.commentID, mc.comment, mc.commentDate, mc.parentCommentID, mc.userID, u.firstName, u.userType, u.image 
            FROM module_comments mc 
            JOIN users u ON mc.userID = u.userID 
            WHERE mc.moduleID = :moduleID AND mc.archived = 0
            ORDER BY mc.commentDate ASC
        ");
        $commentsStmt->bindParam(':moduleID', $moduleID, PDO::PARAM_INT);
        $commentsStmt->execute();
        $allComments = $commentsStmt->fetchAll(PDO::FETCH_ASSOC);

        // Calculate total comments (including replies)
        $totalComments = count($allComments);

        // Debug: Log fetched comments
        error_log("Fetched comments for moduleID $moduleID: " . json_encode($allComments));

        // Build nested comment tree
        function buildCommentTree($comments, $parentId = null) {
            $tree = [];
            foreach ($comments as $comment) {
                if ($comment['parentCommentID'] == $parentId) {
                    $comment['children'] = buildCommentTree($comments, $comment['commentID']);
                    $tree[] = $comment;
                }
            }
            return $tree;
        }
        $commentsTree = buildCommentTree($allComments);

        $unreadStmt = $dbConnection->prepare("SELECT COUNT(*) as unread FROM comment_notifications WHERE notifiedUserID = :userID AND seen = 0 AND commentID IN (SELECT commentID FROM module_comments WHERE moduleID = :moduleID AND archived = 0)");
        $unreadStmt->bindParam(':userID', $userID, PDO::PARAM_INT);
        $unreadStmt->bindParam(':moduleID', $moduleID, PDO::PARAM_INT);
        $unreadStmt->execute();
        $unreadNotifications = $unreadStmt->fetch(PDO::FETCH_ASSOC)['unread'];
    } else {
        $moduleStmt = $dbConnection->prepare("
            SELECT moduleID, fileName, filePath, fileType, fileSize, description, uploadDate 
            FROM modules 
            WHERE teacherSectionID = :teacherSectionID AND archived = 0 
            ORDER BY uploadDate DESC
        ");
        $moduleStmt->bindParam(':teacherSectionID', $teacherSectionID, PDO::PARAM_INT);
        $moduleStmt->execute();
        $modules = $moduleStmt->fetchAll(PDO::FETCH_ASSOC);

        $unreadCounts = [];
        if (!empty($modules)) {
            foreach ($modules as $module) {
                $countStmt = $dbConnection->prepare("SELECT COUNT(*) as unread FROM module_comments mc 
                                                     LEFT JOIN module_comment_views mcv ON mc.moduleID = mcv.moduleID AND mcv.userID = :userID 
                                                     WHERE mc.moduleID = :moduleID AND mc.archived = 0 AND (mcv.last_viewed IS NULL OR mc.commentDate > mcv.last_viewed)");
                $countStmt->bindParam(':userID', $userID, PDO::PARAM_INT);
                $countStmt->bindParam(':moduleID', $module['moduleID'], PDO::PARAM_INT);
                $countStmt->execute();
                $unreadCounts[$module['moduleID']] = $countStmt->fetch(PDO::FETCH_ASSOC)['unread'];
            }
        }
    }

} catch (PDOException $e) {
    error_log("Database Error: " . $e->getMessage() . " in " . $e->getFile() . " on line " . $e->getLine());
    $_SESSION['error_message'] = "Database error: " . htmlspecialchars($e->getMessage());
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
    <link rel="stylesheet" href="./utils/logout.css">
    <link rel="stylesheet" href="./utils/animation_slide.css">
    <link rel="stylesheet" href="./utils/logo.css">
    <link rel="stylesheet" href="./css/scrollbar.css">
    <script src="./logout.js"></script>
    <title>Learnify - Modules</title>
    <style>
        /* Success Message Notification */
        .success-notification {
            background: linear-gradient(135deg, #d4edda, #c3e6cb);
            color: #155724;
            border: 1px solid #c3e6cb;
            border-left: 5px solid #28a745;
            padding: clamp(12px, 2vw, 16px) clamp(15px, 3vw, 20px);
            margin: 1.5rem clamp(1rem, 3vw, 1.5rem);
            border-radius: 8px;
            font-size: clamp(1rem, 2.5vw, 1.1rem);
            font-weight: 500;
            box-shadow: var(--shadow-sm);
            display: flex;
            align-items: center;
            gap: 0.75rem;
            animation: slideInDown 0.4s ease-out, fadeOut 0.4s ease-out 4.6s forwards;
            position: relative;
            overflow: hidden;
            z-index: 10;
        }

        .success-notification::before {
            content: '\f00c';
            font-family: 'boxicons';
            font-size: clamp(1.3rem, 3vw, 1.5rem);
            color: #28a745;
            font-weight: bold;
        }

        .success-notification .close-success {
            position: absolute;
            top: 50%;
            right: 15px;
            transform: translateY(-50%);
            background: transparent;
            border: none;
            font-size: 1.4rem;
            color: #155724;
            cursor: pointer;
            opacity: 0.7;
            transition: opacity 0.2s;
        }

        .success-notification .close-success:hover {
            opacity: 1;
        }

        /* Auto-dismiss animation */
        @keyframes slideInDown {
            from {
                opacity: 0;
                transform: translateY(-20px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        @keyframes fadeOut {
            to {
                opacity: 0;
                transform: translateY(-10px);
                height: 0;
                padding: 0;
                margin: 0;
                border: 0;
            }
        }

        /* Responsive adjustments */
        @media screen and (max-width: 480px) {
            .success-notification {
                margin: 1rem;
                font-size: 1rem;
            }
        }
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
            --google-grey: #202124;
            --google-light-grey: #f1f3f4;
            --google-blue: #4285f4;
            --gradient-start: #e3f2fd;
            --gradient-end: #ffffff;
            --modal-bg: #e6f3f5;
            --footer-gradient-start: #4a90e2;
            --footer-gradient-end: #50e3c2;
            --shadow-sm: 0 2px 4px rgba(0, 0, 0, 0.1);
            --shadow-md: 0 4px 12px rgba(0, 0, 0, 0.15);
            --transition: all 0.3s ease;
            --hover: #d5d5d5;
            --secondary-hover: #e6f0ff;
            --secondary-grey: #f8faff;
        }

        body.dark {
            --hover: #1a1e2e;
            --secondary-hover: #3b5998;
            --secondary-grey: #1a2332;
        }

        .back-btn {
            display: inline-flex;
            align-items: center;
            padding: 0.5rem 1rem;
            background: var(--google-blue);
            color: white;
            text-decoration: none;
            border-radius: 4px;
            font-size: clamp(1.1rem, 3vw, 1.2rem);
            font-weight: 500;
            box-shadow: var(--shadow-sm);
            transition: background 0.3s, transform 0.2s;
        }

        .back-btn:hover {
            background: #357abd;
            transform: translateY(-2px);
        }

        .download-options {
            display: flex;
            align-items: center;
            gap: 1rem;
        }

        .btn-download-all {
            background-color: transparent;
            border: 1px solid #003d80;
            color: var(--dark);
            padding: 0.5rem 1rem;
            border-radius: 4px;
            cursor: pointer;
            font-size: clamp(1rem, 3vw, 1.1rem);
            font-weight: 500;
            box-shadow: var(--shadow-sm);
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            transition: background 0.3s, transform 0.2s;
        }

        .btn-download-all:hover {
            transform: translateY(-2px);
            background-color: var(--light);
        }

        .module-table {
            margin: 1.5rem;
            padding: 1.5rem;
            background: var(--light);
            border-radius: 0.5rem;
            box-shadow: var(--shadow-md);
            overflow-x: auto;
        }

        .module-table table {
            width: 100%;
            border-collapse: separate;
            border-spacing: 0;
            font-size: clamp(1.1rem, 3vw, 1.2rem);
            min-width: 700px;
            background-color: var(--light);
        }

        .module-table th,
        .module-table td {
            padding: 1rem;
            text-align: left;
        }

        .module-table th {
            background: #0056b3;
            color: white;
            font-weight: 500;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            position: sticky;
            top: 0;
            z-index: 10;
        }

        .module-table td {
            color: var(--dark);
            vertical-align: middle;
        }

        .module-table tr:nth-child(even) {
            background: linear-gradient(135deg, var(--secondary-grey), var(--grey));
            transition: background 0.3s ease; 
        }

        .module-table tr:hover {
            background: linear-gradient(135deg, var(--secondary-hover), var(--hover));
        }

        .module-table td.description {
            max-width: 200px;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .module-table td.description:hover {
            white-space: normal;
            overflow: visible;
            text-overflow: inherit;
            position: relative;
            z-index: 20;
        }

        .btn-download, .btn-view, .comments-btn {
            color: white;
            padding: 0.5rem 1rem;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: clamp(1rem, 3vw, 1.1rem);
            font-weight: 500;
            box-shadow: var(--shadow-sm);
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            position: relative;
            transition: background 0.3s, transform 0.2s;
            margin-right: 0.5rem;
        }

        .btn-download {
            background-color: transparent;
            border: 1px solid #003d80;
            color: var(--dark);
        }

        .btn-view {
            background: linear-gradient(135deg, #6f42c1, #5a32a8);
        }

        .comments-btn {
            background: linear-gradient(135deg, #28a745, #218838);
        }

        .comments-btn:hover {
            background: linear-gradient(135deg, #218838, #1e7e34);
        }

        .btn-view:hover {
            background: linear-gradient(135deg, #5a32a8, #4a288f);
        }

        .btn-download:hover {
            transform: translateY(-2px);
            background-color: var(--light);
        }

        .btn-download.downloading .bx-download {
            display: none;
        }

        .btn-download.downloading::after {
            content: '';
            width: 16px;
            height: 16px;
            border: 2px solid white;
            border-top: 2px solid transparent;
            border-radius: 50%;
            animation: spin 0.8s linear infinite;
            position: absolute;
            right: 10px;
        }

        .btn-download.downloading span {
            display: none;
        }

        .text-file-msg {
            margin-bottom: 10px;
            font-size: clamp(1rem, 3vw, 1.2rem);
            background-color: var(--grey);
            color: var(--dark);
            padding: 10px;
            border-left: 4px solid #0056b3;
            border-radius: 10px;
        }

        /* Comments Section */
        .comments-section {
            background: var(--light);
            border-radius: 8px;
            border: 1px solid #e0e0e0;
            padding: clamp(10px, 3vw, 15px);
            box-shadow: 0 3px 2px rgba(0, 0, 0, 0.2);
            margin-top: clamp(10px, 2vw, 15px);
            width: 100%;
            box-sizing: border-box;
        }

        .comments-header {
            align-items: center;
            display: flex;
            flex-wrap: wrap;
        }

        .comments-header h3 {
            color: var(--dark);
            font-size: clamp(1rem, 2.5vw, 1.5rem);
            margin: 0;
        }

        .comment-count {
            color: var(--dark-grey);
            font-size: clamp(0.8rem, 2vw, 1rem);
            margin: clamp(5px, 1vw, 8px);
        }

        .back-to-list {
            background: var(--blue);
            color: white;
            padding: clamp(6px, 1.5vw, 8px) clamp(10px, 2vw, 12px);
            text-decoration: none;
            float: right;
            border-radius: 4px;
            font-size: clamp(1.1rem, 1.8vw, 1.2rem);
            transition: background 0.3s;
        }

        .back-to-list:hover {
            background: #0056b3;
        }

        .comment {
            background: var(--grey);
            border-radius: 8px;
            padding: clamp(8px, 2vw, 12px);
            margin-bottom: clamp(8px, 2vw, 12px);
            border: 1px solid #e0e0e0;
            transition: box-shadow 0.2s;
            box-shadow: 0 3px 2px rgba(0, 0, 0, 0.2);
        }

        .comment:hover {
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
        }

        .comment-header {
            display: flex;
            align-items: center;
            margin-bottom: clamp(6px, 1.5vw, 8px);
            flex-wrap: wrap;
        }

        .comment-avatar {
            width: clamp(30px, 8vw, 40px);
            height: clamp(30px, 8vw, 40px);
            border-radius: 50%;
            margin-right: clamp(8px, 2vw, 10px);
            object-fit: cover;
        }

        .comment-avatar.small {
            width: clamp(20px, 5vw, 28px);
            height: clamp(20px, 5vw, 28px);
        }

        .comment-meta {
            flex: 1;
            min-width: 0; 
        }

        .comment-author {
            font-weight: 600;
            color: var(--dark);
            font-size: clamp(0.9rem, 2.5vw, 1.1rem);
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .comment-date {
            color: var(--dark-grey);
            font-size: clamp(0.7rem, 2vw, 0.9rem);
        }

        .comment-text {
            color: var(--dark);
            font-size: clamp(0.9rem, 2.5vw, 1.2rem);
            line-height: 1.5;
            margin-bottom: clamp(6px, 1.5vw, 8px);
            word-break: break-word; 
        }

        .comment-actions {
            display: flex;
            gap: clamp(6px, 1.5vw, 8px);
            flex-wrap: wrap;
        }

        .edit-comment-btn,
        .archive-comment-btn,
        .reply-btn {
            background-color: transparent;
            border: none;
            font-size: clamp(0.8rem, 2vw, 1rem) !important;
            cursor: pointer;
        }

        .edit-comment-btn,
        .reply-btn {
            color: #0056b3 !important;
        }

        .archive-comment-btn {
            color: var(--red);
        }

        .archive-comment-btn:hover {
            color: #c82333;
            background-color: transparent;
        }

        .comment-form, .reply-form, .edit-form {
            background: var(--light);
            border-radius: 8px;
            padding: clamp(8px, 2vw, 10px);
            margin-top: clamp(8px, 2vw, 10px);
        }

        .form-inline {
            display: flex;
            align-items: flex-start;
            gap: clamp(6px, 1.5vw, 8px);
            flex-direction: column; 
        }

        .form-group {
            flex: 1;
            display: flex;
            align-items: flex-start;
            gap: clamp(6px, 1.5vw, 8px);
            width: 100%;
        }

        .form-textarea {
            flex: 1;
            min-height: clamp(30px, 10vw, 40px);
            border: 1px solid #ccc;
            border-radius: 10px;
            padding: clamp(8px, 2vw, 10px);
            font-size: clamp(0.8rem, 2vw, 1rem);
            resize: none;
            background: var(--grey);
            color: var(--dark);
            transition: border-color 0.2s;
            width: 100%;
            box-sizing: border-box;
        }

        .form-textarea:focus {
            border-color: var(--blue);
            outline: none;
        }

        .form-actions {
            display: flex;
            gap: clamp(6px, 1.5vw, 8px);
            margin-top: clamp(6px, 1.5vw, 8px);
            flex-wrap: wrap;
        }

        .comment-form button, .reply-form button, .edit-form button {
            background: var(--blue);
            color: white;
            border: none;
            padding: clamp(6px, 1.5vw, 8px) clamp(10px, 2vw, 12px);
            border-radius: 20px;
            cursor: pointer;
            font-size: clamp(0.8rem, 1.8vw, 0.9rem);
            font-weight: 500;
            transition: background 0.2s;
        }

        .comment-form button:hover, .reply-form button:hover, .edit-form button:hover {
            background: #0056b3;
        }

        .cancel-btn {
            background: var(--light-grey);
            color: var(--dark);
            border: 1px solid #ccc;
        }

        .cancel-btn:hover {
            background: #e0e0e0;
        }

        .main-comment-form {
            border-top: 1px solid #e0e0e0;
            padding-top: clamp(10px, 2vw, 12px);
        }

        .module-detail {
            background: var(--light);
            border-radius: 8px;
            padding: clamp(10px, 3vw, 15px);
            border: 1px solid #e0e0e0;
            box-shadow: 0 1px 2px rgba(0, 0, 0, 0.1);
            transition: box-shadow 0.2s;
        }

        .module-detail:hover {
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.15);
        }

        .module-detail-header h2 {
            color: var(--dark);
            font-size: clamp(1.2rem, 2.5vw, 1.5rem);
            font-weight: 500;
            margin: 0;
            line-height: 1.4;
        }

        .module-detail-meta {
            margin-bottom: clamp(8px, 2vw, 10px);
        }

        .upload-date {
            color: var(--dark-grey);
            font-size: clamp(0.8rem, 2vw, 1rem);
        }

        .module-detail-actions {
            display: flex;
            align-items: center;
            gap: clamp(6px, 1.5vw, 8px);
            flex-wrap: wrap;
        }

        .btn-view-file {
            display: inline-flex;
            align-items: center;
            background-color: var(--grey);
            color: var(--blue);
            border: 1px solid #ccc;
            padding: clamp(6px, 1.5vw, 8px) clamp(8px, 2vw, 10px);
            border-radius: 4px;
            text-decoration: none;
            font-size: clamp(0.8rem, 1.8vw, 0.9rem);
            font-weight: 500;
            transition: background 0.2s, color 0.2s;
        }

        .btn-view-file:hover {
            background: var(--blue);
            color: white;
        }

        .btn-view-file i {
            margin-right: clamp(4px, 1vw, 6px);
            font-size: clamp(0.9rem, 2vw, 1rem);
        }

        .no-comment-yet {
            font-size: clamp(0.9rem, 2.5vw, 1.1rem);
            padding: clamp(6px, 1.5vw, 8px);
            color: var(--dark);
            font-style: italic;
        }

        .comments-btn .badge {
            position: absolute;
            top: -10px;
            right: -10px;
            background: red;
            color: white;
            border-radius: 50%;
            padding: 5px 10px;
            font-size: clamp(0.6rem, 1.5vw, 0.8rem);
        }

        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }

        @media screen and (max-width: 768px) {
           
            .download-options {
                flex-direction: column;
                align-items: flex-end;
            }

            .text-file-msg {
                width: 100%;
            }
        }

        @media screen and (max-width: 480px) {
            .btn-download, .btn-view, .comments-btn {
                margin-bottom: 6px;
            }

            .module-table, .module-detail {
                padding: o;
                margin: 0;
                margin-top: 10px;
            }

            .module-detail-actions {
                display: flex;
                flex-direction: column;
                gap: 10px;
            }

            .module-detail-header h2 {
                text-align: center;
            }
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
                <img src="<?php echo htmlspecialchars($_SESSION['image']); ?>" alt="Profile Image">
                <div>
                    <p><?php echo htmlspecialchars($_SESSION['firstName']); ?></p>
                    <small><?php echo htmlspecialchars($_SESSION['userType']); ?></small>
                </div>
            </a>
        </nav>

        <main>
            <div class="head-title">
                <div class="left">
                    <h1>Modules - <?php echo htmlspecialchars($section['subjectName'] . ' (' . $section['sectionName'] . ')'); ?></h1>
                    <ul class="breadcrumb">
                        <li><a href="./studentDash.php">Home</a></li>
                        <li><i class='bx bx-chevron-right'></i></li>
                        <li><a href="./studentDash.php">Class Details</a></li>
                        <li><i class='bx bx-chevron-right'></i></li>
                        <li><a class="active" href="#">Modules</a></li>
                    </ul>
                </div>
                <a href="./studentDash.php" class="back-button">
                    <i class='bx bx-arrow-back'></i> Back to Dashboard
                </a>
            </div>

            <?php if (isset($_SESSION['success_message'])): ?>
                <div class="success-notification" id="success-msg">
                    <?php
                        echo htmlspecialchars($_SESSION['success_message']);
                        unset($_SESSION['success_message']);
                    ?>
                    <button type="button" class="close-success" onclick="closeSuccessMsg()" aria-label="Close success message">&times;</button>
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

            <?php if ($moduleID && $singleModule): ?>
                <!-- Single Module View with Comments -->
                <div class="module-detail">
                    <a href="studentModules.php?teacherSectionID=<?php echo $teacherSectionID; ?>" class="back-to-list"><i class='bx bx-arrow-back'></i> Back to Module List</a>
                    <div class="module-detail-header">
                        <h2><?php echo htmlspecialchars($singleModule['description']); ?></h2>
                    </div>
                    <!-- <div class="module-detail-meta">
                        <span class="upload-date">Posted <?php echo date('M j, Y g:i A', strtotime($singleModule['uploadDate'])); ?></span>
                    </div> -->
                    <div class="module-detail-actions">
                        <a href="javascript:void(0);" onclick="openFileViewer('<?php echo htmlspecialchars($singleModule['filePath']); ?>', '<?php echo htmlspecialchars($singleModule['fileName']); ?>', '<?php echo htmlspecialchars($singleModule['fileType']); ?>')" class="btn-view-file" aria-label="View Module File">
                            <i class='bx bx-file'></i> <?php echo htmlspecialchars($singleModule['fileName']); ?>
                        </a>
                        <a href="<?php echo htmlspecialchars($singleModule['filePath']); ?>" class="btn-download" download aria-label="Download Module">
                            <i class='bx bx-download'></i> Download
                        </a>
                    </div>
                </div>
                <div class="comments-section">
                    <h3><span class="comment-count"><?php echo $totalComments; ?> <?php echo $totalComments === 1 ? 'Comment' : 'Comments'; ?><?php if ($unreadNotifications > 0): ?> (<?php echo $unreadNotifications; ?> new)<?php endif; ?></span>

                    <div class="comments-header">
                    </div>
                    <?php if (!empty($commentsTree)): ?>
                        <?php
                        function renderComments($comments, $level = 0, $userID, $moduleID, $teacherSectionID) {
                            foreach ($comments as $comment): ?>
                                <div class="comment" style="margin-left: <?php echo $level * 30; ?>px;">
                                    <div class="comment-header">
                                        <img src="<?php echo htmlspecialchars($comment['image'] ?: './img/noprofile.png'); ?>" alt="User Avatar" class="comment-avatar">
                                        <div class="comment-meta">
                                            <div class="comment-author">
                                                <?php 
                                                $displayType = ($comment['userType'] === 'Professor') ? 'Teacher' : $comment['userType'];
                                                echo htmlspecialchars($comment['firstName'] . ' (' . $displayType . ')'); 
                                                ?>
                                            </div>
                                            <!-- <div class="comment-date"><?php echo date('M j, Y g:i A', strtotime($comment['commentDate'])); ?></div> -->
                                        </div>
                                    </div>
                                    <div class="comment-text"><?php echo nl2br(htmlspecialchars($comment['comment'])); ?></div>
                                    <div class="comment-actions">
                                        <button class="reply-btn" onclick="toggleReplyForm(<?php echo $comment['commentID']; ?>)">Reply</button>
                                        <?php if ($comment['userID'] == $userID): ?>
                                            <button class="edit-comment-btn" onclick="toggleEditForm(<?php echo $comment['commentID']; ?>)">Edit</button>
                                            <button class="archive-comment-btn" onclick="showCommentArchiveConfirmModal(<?php echo $comment['commentID']; ?>)">Delete</button>
                                        <?php endif; ?>
                                    </div>
                                    <!-- Reply Form -->
                                    <div id="reply-form-<?php echo $comment['commentID']; ?>" class="comment-form reply-form" style="display: none;">
                                        <form method="POST" class="form-inline">
                                            <input type="hidden" name="moduleID" value="<?php echo $moduleID; ?>">
                                            <input type="hidden" name="parentCommentID" value="<?php echo $comment['commentID']; ?>">
                                            <input type="hidden" name="addComment" value="1">
                                            <div class="form-group">
                                                <img src="<?php echo htmlspecialchars(isset($_SESSION['image']) ? $_SESSION['image'] : './img/noprofile.png'); ?>" alt="Your Avatar" class="comment-avatar small">
                                                <textarea name="comment" placeholder="Write a reply..." required class="form-textarea"></textarea>
                                            </div>
                                            <div class="form-actions">
                                                <button type="submit" class="submit-btn">Post</button>
                                                <button type="button" class="cancel-btn" onclick="toggleReplyForm(<?php echo $comment['commentID']; ?>)">Cancel</button>
                                            </div>
                                        </form>
                                    </div>
                                    <!-- Edit Form -->
                                    <div id="edit-form-<?php echo $comment['commentID']; ?>" class="comment-form edit-form" style="display: none;">
                                        <form method="POST" class="form-inline">
                                            <input type="hidden" name="commentID" value="<?php echo $comment['commentID']; ?>">
                                            <input type="hidden" name="editComment" value="1">
                                            <div class="form-group">
                                                <img src="<?php echo htmlspecialchars(isset($_SESSION['image']) ? $_SESSION['image'] : './img/noprofile.png'); ?>" alt="Your Avatar" class="comment-avatar small">
                                                <textarea name="comment" required class="form-textarea"><?php echo htmlspecialchars($comment['comment']); ?></textarea>
                                            </div>
                                            <div class="form-actions">
                                                <button type="submit" class="submit-btn">Update</button>
                                                <button type="button" class="cancel-btn" onclick="toggleEditForm(<?php echo $comment['commentID']; ?>)">Cancel</button>
                                            </div>
                                        </form>
                                    </div>
                                    <?php if (!empty($comment['children'])): ?>
                                        <?php renderComments($comment['children'], $level + 1, $userID, $moduleID, $teacherSectionID); ?>
                                    <?php endif; ?>
                                </div>
                            <?php endforeach;
                        }
                        renderComments($commentsTree, 0, $userID, $moduleID, $teacherSectionID);
                    else: ?>
                        <p class="no-comment-yet">No comments yet. Be the first to comment!</p>
                    <?php endif; ?>
                    <div class="comment-form main-comment-form">
                        <form method="POST" class="form-inline">
                            <input type="hidden" name="moduleID" value="<?php echo $moduleID; ?>">
                            <input type="hidden" name="addComment" value="1">
                            <div class="form-group">
                                <img src="<?php echo htmlspecialchars(isset($_SESSION['image']) ? $_SESSION['image'] : './img/noprofile.png'); ?>" alt="Your Avatar" class="comment-avatar">
                                <textarea name="comment" placeholder="Write a comment..." required class="form-textarea"></textarea>
                            </div>
                            <div class="form-actions">
                                <button type="submit" class="submit-btn">Post Comment</button>
                            </div>
                        </form>
                    </div>
                </div>
            <?php else: ?>
                <div class="module-table">
                    <table>
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>File Name</th>
                                <th>Description</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (!empty($modules)): ?>
                                <?php foreach ($modules as $index => $module): ?>
                                    <tr>
                                        <td><?php echo $index + 1; ?></td>
                                        <td><?php echo htmlspecialchars($module['fileName']); ?></td>
                                        <td class="description"><?php echo $module['description'] ? htmlspecialchars($module['description']) : '-'; ?></td>
                                        <td>
                                            <a href="<?php echo htmlspecialchars($module['filePath']); ?>" class="btn-download" download aria-label="Download Module">
                                                <i class='bx bx-download'></i>
                                                <span>Download</span>
                                            </a>
                                            <a href="view_module.php?file=<?php echo htmlspecialchars($module['filePath']); ?>" class="btn-view">
                                                <i class='bx bxs-file-doc'></i>
                                                <span>View File</span>
                                            </a>
                                            <a href="studentModules.php?teacherSectionID=<?php echo $teacherSectionID; ?>&moduleID=<?php echo $module['moduleID']; ?>" class="comments-btn">
                                                <i class='bx bx-message'></i>
                                                <span>Comments</span>
                                                <?php if ($unreadCounts[$module['moduleID']] > 0): ?>
                                                    <span class="badge"><?php echo $unreadCounts[$module['moduleID']]; ?></span>
                                                <?php endif; ?>
                                            </a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                                <?php if (!empty($modules)): ?>
                                    <tr>
                                        <td colspan="3" class="text-file-msg">Click the button below to download all files instantly.</td>
                                        <td>
                                            <a href="./download_all_student.php?teacherSectionID=<?php echo htmlspecialchars($teacherSectionID); ?>" class="btn-download-all">
                                                <i class='bx bx-download'></i>
                                                <span>Download All</span>
                                            </a>
                                        </td>
                                    </tr>
                                <?php endif; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="4">No modules available.</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </main>
    </section>

    <script>
        document.querySelectorAll('.btn-download').forEach(button => {
            button.addEventListener('click', () => {
                button.classList.add('downloading');
                button.querySelector('span').textContent = 'Downloading...';
                setTimeout(() => {
                    button.classList.remove('downloading');
                    button.querySelector('span').textContent = 'Download';
                }, 2000);
            });
        });

        function toggleReplyForm(commentID) {
            const replyForm = document.getElementById(`reply-form-${commentID}`);
            const editForm = document.getElementById(`edit-form-${commentID}`);
            const mainCommentForm = document.querySelector('.main-comment-form');

            document.querySelectorAll('.reply-form, .edit-form').forEach(form => {
                if (form.id !== `reply-form-${commentID}`) {
                    form.style.display = 'none';
                }
            });

            if (replyForm.style.display === 'block') {
                replyForm.style.display = 'none';
            } else {
                replyForm.style.display = 'block';
                replyForm.querySelector('textarea').focus();
            }

            if (editForm) {
                editForm.style.display = 'none';
            }

            if (mainCommentForm) {
                mainCommentForm.style.display = 'block';
            }
        }

        function toggleEditForm(commentID) {
            const editForm = document.getElementById(`edit-form-${commentID}`);
            const replyForm = document.getElementById(`reply-form-${commentID}`);
            const mainCommentForm = document.querySelector('.main-comment-form');

            document.querySelectorAll('.reply-form, .edit-form').forEach(form => {
                if (form.id !== `edit-form-${commentID}`) {
                    form.style.display = 'none';
                }
            });

            if (editForm.style.display === 'block') {
                editForm.style.display = 'none';
            } else {
                editForm.style.display = 'block';
                editForm.querySelector('textarea').focus();
            }

            if (replyForm) {
                replyForm.style.display = 'none';
            }

            if (mainCommentForm) {
                mainCommentForm.style.display = 'block';
            }
        }

        function closeAllForms() {
            document.querySelectorAll('.reply-form, .edit-form').forEach(form => {
                form.style.display = 'none';
            });
            const mainCommentForm = document.querySelector('.main-comment-form');
            if (mainCommentForm) {
                mainCommentForm.style.display = 'block';
            }
        }

        function showCommentArchiveConfirmModal(commentID) {
            if (confirm('Are you sure you want to delete this comment? This action cannot be undone.')) {
                const form = document.createElement('form');
                form.method = 'POST';
                form.action = '';
                const inputCommentID = document.createElement('input');
                inputCommentID.type = 'hidden';
                inputCommentID.name = 'commentID';
                inputCommentID.value = commentID;
                const inputArchive = document.createElement('input');
                inputArchive.type = 'hidden';
                inputArchive.name = 'archiveComment';
                inputArchive.value = '1';
                form.appendChild(inputCommentID);
                form.appendChild(inputArchive);
                document.body.appendChild(form);
                form.submit();
            }
        }

        function openFileViewer(filePath, fileName, fileType) {
            if (!filePath || !fileName || !fileType) {
                alert('Invalid file information.');
                return;
            }

            const viewerUrl = `view_module.php?file=${encodeURIComponent(filePath)}&name=${encodeURIComponent(fileName)}`;
            window.open(viewerUrl, '_blank');
        }
        
        function closeSuccessMsg() {
            const msg = document.getElementById('success-msg');
            if (msg) {
                msg.style.animation = 'fadeOut 0.4s ease-out forwards';
                setTimeout(() => msg.remove(), 400);
            }
        }

        setTimeout(closeSuccessMsg, 5000);
    </script>

    <script src="./utils/script.js"></script>
</body>
</html>