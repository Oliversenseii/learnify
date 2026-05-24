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

try {
    // Get user details
    $stmt = $dbConnection->prepare("SELECT firstName, userType, image FROM users WHERE userID = :userID");
    $stmt->bindParam(':userID', $userID, PDO::PARAM_INT);
    $stmt->execute();
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$user) {
        header("Location: ../../index.php");
        exit;
    }
    $_SESSION['firstName'] = htmlspecialchars($user['firstName']);
    $_SESSION['userType'] = htmlspecialchars($user['userType']);
    $_SESSION['image'] = $user['image'] ? htmlspecialchars($user['image']) : './img/noprofile.png';

    // Validate teacherSectionID
    $teacherSectionID = isset($_GET['teacherSectionID']) ? filter_var($_GET['teacherSectionID'], FILTER_VALIDATE_INT) : null;
    if (!$teacherSectionID) {
        $_SESSION['error_message'] = "Invalid class ID.";
        header("Location: professorDash.php");
        exit;
    }

    // Verify teacherSectionID belongs to the professor
    $checkStmt = $dbConnection->prepare("SELECT ts.subjectID, s.sectionName, sub.subjectName 
                                         FROM teacher_section ts
                                         JOIN sections s ON ts.sectionID = s.sectionID
                                         JOIN subjects sub ON ts.subjectID = sub.subjectID
                                         WHERE ts.teacherSectionID = :teacherSectionID AND ts.teacherID = :userID AND ts.archived = 0");
    $checkStmt->bindParam(':teacherSectionID', $teacherSectionID, PDO::PARAM_INT);
    $checkStmt->bindParam(':userID', $userID, PDO::PARAM_INT);
    $checkStmt->execute();
    $sectionDetails = $checkStmt->fetch(PDO::FETCH_ASSOC);
    if (!$sectionDetails) {
        $_SESSION['error_message'] = "You don't have permission to view this class.";
        header("Location: professorDash.php");
        exit;
    }

    // Handle comment submission
    if (isset($_POST['submitComment']) && isset($_POST['content'])) {
        $content = trim($_POST['content']);
        if ($content) {
            $insertStmt = $dbConnection->prepare("
                INSERT INTO comments (teacherSectionID, userID, content, createdDate) 
                VALUES (:teacherSectionID, :userID, :content, NOW())
            ");
            $insertStmt->bindParam(':teacherSectionID', $teacherSectionID, PDO::PARAM_INT);
            $insertStmt->bindParam(':userID', $userID, PDO::PARAM_INT);
            $insertStmt->bindParam(':content', $content, PDO::PARAM_STR);
            if ($insertStmt->execute()) {
                $_SESSION['success_message'] = "Comment posted successfully.";
            } else {
                $_SESSION['error_message'] = "Failed to post comment.";
            }
        } else {
            $_SESSION['error_message'] = "Comment content is required.";
        }
        header("Location: commentsDash.php?teacherSectionID=$teacherSectionID&subjectID=" . htmlspecialchars($sectionDetails['subjectID']) . (isset($_GET['studentID']) ? "&studentID=" . $_GET['studentID'] : ""));
        exit;
    }

    // Handle comment editing
    if (isset($_POST['editComment']) && isset($_POST['commentID']) && isset($_POST['content'])) {
        $commentID = filter_var($_POST['commentID'], FILTER_VALIDATE_INT);
        $content = trim($_POST['content']);
        if ($commentID && $content) {
            $checkCommentStmt = $dbConnection->prepare("
                SELECT * FROM comments 
                WHERE commentID = :commentID AND userID = :userID AND archived = 0
            ");
            $checkCommentStmt->bindParam(':commentID', $commentID, PDO::PARAM_INT);
            $checkCommentStmt->bindParam(':userID', $userID, PDO::PARAM_INT);
            $checkCommentStmt->execute();
            if ($checkCommentStmt->rowCount() > 0) {
                $updateStmt = $dbConnection->prepare("
                    UPDATE comments 
                    SET content = :content" . (array_key_exists('updatedDate', $comments[0] ?? []) ? ", updatedDate = NOW()" : "") . " 
                    WHERE commentID = :commentID
                ");
                $updateStmt->bindParam(':commentID', $commentID, PDO::PARAM_INT);
                $updateStmt->bindParam(':content', $content, PDO::PARAM_STR);
                if ($updateStmt->execute()) {
                    $_SESSION['success_message'] = "Comment updated successfully.";
                } else {
                    $_SESSION['error_message'] = "Failed to update comment.";
                }
            } else {
                $_SESSION['error_message'] = "You don't have permission to edit this comment.";
            }
        } else {
            $_SESSION['error_message'] = "Comment content is required.";
        }
        header("Location: commentsDash.php?teacherSectionID=$teacherSectionID&subjectID=" . htmlspecialchars($sectionDetails['subjectID']) . (isset($_GET['studentID']) ? "&studentID=" . $_GET['studentID'] : ""));
        exit;
    }

    // Handle comment archiving
    if (isset($_POST['archiveComment']) && isset($_POST['commentID'])) {
        $commentID = filter_var($_POST['commentID'], FILTER_VALIDATE_INT);
        if ($commentID) {
            $checkCommentStmt = $dbConnection->prepare("
                SELECT * FROM comments 
                WHERE commentID = :commentID AND userID = :userID AND archived = 0
            ");
            $checkCommentStmt->bindParam(':commentID', $commentID, PDO::PARAM_INT);
            $checkCommentStmt->bindParam(':userID', $userID, PDO::PARAM_INT);
            $checkCommentStmt->execute();
            if ($checkCommentStmt->rowCount() > 0) {
                $archiveStmt = $dbConnection->prepare("
                    UPDATE comments 
                    SET archived = 1 
                    WHERE commentID = :commentID
                ");
                $archiveStmt->bindParam(':commentID', $commentID, PDO::PARAM_INT);
                if ($archiveStmt->execute()) {
                    $_SESSION['success_message'] = "Comment archived successfully.";
                } else {
                    $_SESSION['error_message'] = "Failed to archive comment.";
                }
            } else {
                $_SESSION['error_message'] = "You don't have permission to archive this comment.";
            }
        } else {
            $_SESSION['error_message'] = "Invalid comment ID.";
        }
        header("Location: commentsDash.php?teacherSectionID=$teacherSectionID&subjectID=" . htmlspecialchars($sectionDetails['subjectID']) . (isset($_GET['studentID']) ? "&studentID=" . $_GET['studentID'] : ""));
        exit;
    }

    // Handle private message submission
    if (isset($_POST['submitMessage']) && isset($_POST['recipientID']) && isset($_POST['messageContent'])) {
        $recipientID = filter_var($_POST['recipientID'], FILTER_VALIDATE_INT);
        $messageContent = trim($_POST['messageContent']);
        if ($recipientID && $messageContent) {
            $checkStudentStmt = $dbConnection->prepare("
                SELECT u.userID 
                FROM student_section ss 
                JOIN users u ON ss.userID = u.userID 
                WHERE ss.sectionID = (SELECT sectionID FROM teacher_section WHERE teacherSectionID = :teacherSectionID) 
                AND u.userID = :recipientID AND ss.status = 'Enrolled' AND ss.archived = 0 AND u.archived = 0
            ");
            $checkStudentStmt->bindParam(':teacherSectionID', $teacherSectionID, PDO::PARAM_INT);
            $checkStudentStmt->bindParam(':recipientID', $recipientID, PDO::PARAM_INT);
            $checkStudentStmt->execute();
            if ($checkStudentStmt->rowCount() > 0) {
                $insertMsgStmt = $dbConnection->prepare("
                    INSERT INTO private_messages (senderID, recipientID, teacherSectionID, content, createdDate) 
                    VALUES (:senderID, :recipientID, :teacherSectionID, :content, NOW())
                ");
                $insertMsgStmt->bindParam(':senderID', $userID, PDO::PARAM_INT);
                $insertMsgStmt->bindParam(':recipientID', $recipientID, PDO::PARAM_INT);
                $insertMsgStmt->bindParam(':teacherSectionID', $teacherSectionID, PDO::PARAM_INT);
                $insertMsgStmt->bindParam(':content', $messageContent, PDO::PARAM_STR);
                if ($insertMsgStmt->execute()) {
                    $_SESSION['success_message'] = "Message sent successfully.";
                } else {
                    $_SESSION['error_message'] = "Failed to send message.";
                }
            } else {
                $_SESSION['error_message'] = "Invalid recipient.";
            }
        } else {
            $_SESSION['error_message'] = "Message content and recipient are required.";
        }
        header("Location: commentsDash.php?teacherSectionID=$teacherSectionID&subjectID=" . htmlspecialchars($sectionDetails['subjectID']) . "&studentID=$recipientID");
        exit;
    }

    // Handle private message editing
    if (isset($_POST['editMessage']) && isset($_POST['messageID']) && isset($_POST['messageContent'])) {
        $messageID = filter_var($_POST['messageID'], FILTER_VALIDATE_INT);
        $messageContent = trim($_POST['messageContent']);
        if ($messageID && $messageContent) {
            $checkMessageStmt = $dbConnection->prepare("
                SELECT * FROM private_messages 
                WHERE messageID = :messageID AND senderID = :userID AND archived = 0
            ");
            $checkMessageStmt->bindParam(':messageID', $messageID, PDO::PARAM_INT);
            $checkMessageStmt->bindParam(':userID', $userID, PDO::PARAM_INT);
            $checkMessageStmt->execute();
            if ($checkMessageStmt->rowCount() > 0) {
                $updateMsgStmt = $dbConnection->prepare("
                    UPDATE private_messages 
                    SET content = :content 
                    WHERE messageID = :messageID
                ");
                $updateMsgStmt->bindParam(':messageID', $messageID, PDO::PARAM_INT);
                $updateMsgStmt->bindParam(':content', $messageContent, PDO::PARAM_STR);
                if ($updateMsgStmt->execute()) {
                    $_SESSION['success_message'] = "Message updated successfully.";
                } else {
                    $_SESSION['error_message'] = "Failed to update message.";
                }
            } else {
                $_SESSION['error_message'] = "You don't have permission to edit this message.";
            }
        } else {
            $_SESSION['error_message'] = "Message content is required.";
        }
        header("Location: commentsDash.php?teacherSectionID=$teacherSectionID&subjectID=" . htmlspecialchars($sectionDetails['subjectID']) . (isset($_GET['studentID']) ? "&studentID=" . $_GET['studentID'] : ""));
        exit;
    }

    // Handle private message archiving
    if (isset($_POST['archiveMessage']) && isset($_POST['messageID'])) {
        $messageID = filter_var($_POST['messageID'], FILTER_VALIDATE_INT);
        if ($messageID) {
            $checkMessageStmt = $dbConnection->prepare("
                SELECT * FROM private_messages 
                WHERE messageID = :messageID AND senderID = :userID AND archived = 0
            ");
            $checkMessageStmt->bindParam(':messageID', $messageID, PDO::PARAM_INT);
            $checkMessageStmt->bindParam(':userID', $userID, PDO::PARAM_INT);
            $checkMessageStmt->execute();
            if ($checkMessageStmt->rowCount() > 0) {
                $archiveMsgStmt = $dbConnection->prepare("
                    UPDATE private_messages 
                    SET archived = 1 
                    WHERE messageID = :messageID
                ");
                $archiveMsgStmt->bindParam(':messageID', $messageID, PDO::PARAM_INT);
                if ($archiveMsgStmt->execute()) {
                    $_SESSION['success_message'] = "Message archived successfully.";
                } else {
                    $_SESSION['error_message'] = "Failed to archive message.";
                }
            } else {
                $_SESSION['error_message'] = "You don't have permission to archive this message.";
            }
        } else {
            $_SESSION['error_message'] = "Invalid message ID.";
        }
        header("Location: commentsDash.php?teacherSectionID=$teacherSectionID&subjectID=" . htmlspecialchars($sectionDetails['subjectID']) . (isset($_GET['studentID']) ? "&studentID=" . $_GET['studentID'] : ""));
        exit;
    }

    // Fetch comments
    $commentStmt = $dbConnection->prepare("
        SELECT c.commentID, c.content, c.createdDate, 
               " . (array_key_exists('updatedDate', $comments[0] ?? []) ? 'c.updatedDate' : 'NULL AS updatedDate') . ",
               c.userID, u.firstName, u.lastName, u.image
        FROM comments c
        JOIN users u ON c.userID = u.userID
        WHERE c.teacherSectionID = :teacherSectionID AND c.archived = 0
        ORDER BY c.createdDate DESC
    ");
    $commentStmt->bindParam(':teacherSectionID', $teacherSectionID, PDO::PARAM_INT);
    $commentStmt->execute();
    $comments = $commentStmt->fetchAll(PDO::FETCH_ASSOC);

    // Fetch students for private message selection
    $studentStmt = $dbConnection->prepare("
        SELECT u.userID, CONCAT(u.firstName, ' ', u.lastName) AS studentName
        FROM student_section ss
        JOIN users u ON ss.userID = u.userID
        WHERE ss.sectionID = (SELECT sectionID FROM teacher_section WHERE teacherSectionID = :teacherSectionID)
        AND ss.status = 'Enrolled' AND ss.archived = 0 AND u.archived = 0
        ORDER BY u.firstName
    ");
    $studentStmt->bindParam(':teacherSectionID', $teacherSectionID, PDO::PARAM_INT);
    $studentStmt->execute();
    $students = $studentStmt->fetchAll(PDO::FETCH_ASSOC);

    // Fetch private messages for a specific student if selected
    $selectedStudentID = isset($_GET['studentID']) ? filter_var($_GET['studentID'], FILTER_VALIDATE_INT) : null;
    $messages = [];
    if ($selectedStudentID) {
        $messageStmt = $dbConnection->prepare("
            SELECT pm.messageID, pm.content, pm.createdDate, pm.senderID, pm.recipientID, 
                   us.firstName AS senderFirstName, us.lastName AS senderLastName, us.image AS senderImage
            FROM private_messages pm
            JOIN users us ON pm.senderID = us.userID
            WHERE pm.teacherSectionID = :teacherSectionID AND pm.archived = 0 
            AND ((pm.senderID = :userID AND pm.recipientID = :studentID) OR (pm.senderID = :studentID AND pm.recipientID = :userID))
            ORDER BY pm.createdDate DESC
        ");
        $messageStmt->bindParam(':teacherSectionID', $teacherSectionID, PDO::PARAM_INT);
        $messageStmt->bindParam(':userID', $userID, PDO::PARAM_INT);
        $messageStmt->bindParam(':studentID', $selectedStudentID, PDO::PARAM_INT);
        $messageStmt->execute();
        $messages = $messageStmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // Notification logic: Count new comments
    $lastVisitComments = $_SESSION['last_visit_comments'][$teacherSectionID] ?? '1970-01-01 00:00:00';
    $newCommentsStmt = $dbConnection->prepare("
        SELECT COUNT(*) as count 
        FROM comments 
        WHERE teacherSectionID = :teacherSectionID AND archived = 0 AND createdDate > :lastVisit
    ");
    $newCommentsStmt->bindParam(':teacherSectionID', $teacherSectionID, PDO::PARAM_INT);
    $newCommentsStmt->bindParam(':lastVisit', $lastVisitComments, PDO::PARAM_STR);
    $newCommentsStmt->execute();
    $newCommentsCount = $newCommentsStmt->fetchColumn();

    // Notification logic: Fetch new private messages with sender names
    $lastVisitMessages = $_SESSION['last_visit_messages'][$teacherSectionID] ?? '1970-01-01 00:00:00';
    $newMessagesStmt = $dbConnection->prepare("
        SELECT pm.messageID, us.firstName, us.lastName 
        FROM private_messages pm
        JOIN users us ON pm.senderID = us.userID
        WHERE pm.teacherSectionID = :teacherSectionID AND pm.archived = 0 
        AND pm.recipientID = :userID AND pm.createdDate > :lastVisit
        ORDER BY pm.createdDate DESC
    ");
    $newMessagesStmt->bindParam(':teacherSectionID', $teacherSectionID, PDO::PARAM_INT);
    $newMessagesStmt->bindParam(':userID', $userID, PDO::PARAM_INT);
    $newMessagesStmt->bindParam(':lastVisit', $lastVisitMessages, PDO::PARAM_STR);
    $newMessagesStmt->execute();
    $newMessages = $newMessagesStmt->fetchAll(PDO::FETCH_ASSOC);

    // Update last visit timestamps
    $_SESSION['last_visit_comments'][$teacherSectionID] = date('Y-m-d H:i:s');
    $_SESSION['last_visit_messages'][$teacherSectionID] = date('Y-m-d H:i:s');

} catch (PDOException $e) {
    error_log("Database Error: " . $e->getMessage());
    $_SESSION['error_message'] = "Database error occurred.";
    header("Location: professorDash.php");
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
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Roboto:wght@400;500;700&display=swap">
    <link rel="stylesheet" href="./utils/style.css">
    <link rel="stylesheet" href="./utils/semi-dash.css">
    <link rel="stylesheet" href="./utils/logout.css">
    <link rel="stylesheet" href="./utils/animation_slide.css">
    <link rel="stylesheet" href="./utils/logo.css">
    <link rel="stylesheet" href="./css/scrollbar.css">
    <script src="./logout.js"></script>
    <title>Learnify - Messages & Comments</title>
    <style>
        #content main .head-title .left h1 {
            font-size: clamp(2rem, 3vw, 2.5rem);
            font-weight: 600;
            margin-bottom: 10px;
            color: var(--dark);
        }
        :root {
            --primary: #1a73e8;
            --secondary: #f1f3f4;
            --background: #ffffff;
            --text: #202124;
            --text-secondary: #5f6368;
            --border: #dadce0;
            --accent: #e8f0fe;
            --success: #34a853;
            --error: #d93025;
            --shadow-sm: 0 1px 2px rgba(0, 0, 0, 0.1);
            --shadow-md: 0 2px 8px rgba(0, 0, 0, 0.15);
            --transition: all 0.2s ease;
        }

        .two-column-container {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 1.5rem;
            margin: 1.5rem;
            max-width: 1200px;
            margin-left: auto;
            margin-right: auto;
        }

        .comment-section, .message-section {
            padding: 1.5rem;
            background: var(--light);
            border-radius: 8px;
            box-shadow: var(--shadow-md);
            border: 1px solid rgba(0, 0, 0, 0.1);
        }

        .comment-section h3, .message-section h3 {
            font-size: 1.25rem;
            font-weight: 500;
            color: var(--dark);
            margin: 0 0 1rem;
            padding-bottom: 0.5rem;
            border-bottom: 1px solid var(--border);
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .notification-badge {
            background: linear-gradient(135deg, #dc3545, #c82333);
            color: #fff;
            border-radius: 12px;
            padding: 0.2rem 0.6rem;
            font-size: 0.75rem;
            font-weight: 500;
        }

        .notification-list-container {
            margin-bottom: 1rem;
        }

        .notification-toggle {
            background: linear-gradient(135deg, #dc3545, #c82333);
            color: #fff;
            padding: 0.5rem 1rem;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 0.875rem;
            font-weight: 500;
            width: 100%;
            text-align: left;
            display: flex;
            justify-content: space-between;
            align-items: center;
            transition: var(--transition);
        }

        .notification-toggle:hover {
            background: linear-gradient(135deg, #c82333, #b21f2d);
        }

        .notification-list {
            display: none;
            flex-direction: column;
            gap: 0.5rem;
            margin-top: 0.5rem;
            background: var(--background);
            border: 1px solid var(--border);
            border-radius: 4px;
            padding: 0.75rem;
        }

        .notification-list.active {
            display: flex;
        }

        .notification-item {
            font-size: 0.875rem;
            color: var(--text);
        }

        .comment-form, .message-form {
            display: flex;
            flex-direction: column;
            gap: 1rem;
            margin-bottom: 1.5rem;
            background: rgba(255, 255, 255, 0.1); 
            box-shadow: 0 4px 30px rgba(0, 0, 0, 0.1); 
            padding: 1rem;
            border-radius: 8px;
        }

        .comment-form textarea, .message-form textarea {
            width: 100%;
            padding: 0.75rem;
            border: 1px solid rgba(255, 255, 255, 0.2); 
            color: var(--dark);
            border-radius: 4px;
            font-size: 0.875rem;
            background-color: var(--grey);
            line-height: 1.5;
            min-height: 80px;
            resize: vertical;
            transition: var(--transition);
        }

        .comment-form textarea:focus, .message-form textarea:focus {
            border-color: var(--primary);
            box-shadow: 0 0 0 2px var(--accent);
            outline: none;
        }

        .message-form select {
            padding: 0.75rem;
            border: 1px solid var(--border);
            border-radius: 4px;
            font-size: 0.875rem;
            background: var(--background);
            transition: var(--transition);
        }

        .message-form select:focus {
            border-color: var(--primary);
            box-shadow: 0 0 0 2px var(--accent);
            outline: none;
        }

        .comment-form button, .message-form button {
            background: linear-gradient(135deg, #007bff, #0056b3);
            color: #fff;
            padding: 0.5rem 1rem;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 0.875rem;
            font-weight: 500;
            align-self: flex-end;
            transition: var(--transition);
        }

        .comment-form button:hover, .message-form button:hover {
            background: linear-gradient(135deg, #0056b3, #003d80);
            transform: translateY(-1px);
        }

        .student-list-container {
            margin-bottom: 1rem;
        }

        .student-list-toggle {
            background: linear-gradient(135deg, #007bff, #0056b3);
            color: #fff;
            padding: 0.5rem 1rem;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 0.875rem;
            font-weight: 500;
            width: 100%;
            text-align: left;
            display: flex;
            justify-content: space-between;
            align-items: center;
            transition: var(--transition);
        }

        .student-list-toggle:hover {
            background: linear-gradient(135deg, #0056b3, #003d80);
        }

        .student-list {
            display: none;
            flex-direction: column;
            gap: 0.5rem;
            margin-top: 0.5rem;
            max-height: 200px;
            overflow-y: auto;
        }

        .student-list.active {
            display: flex;
        }

        .student-item {
            padding: 0.75rem;
            background: var(--grey);
            border: 1px solid var(--border);
            border-radius: 4px;
            cursor: pointer;
            font-size: 0.875rem;
            color: var(--text);
            transition: var(--transition);
        }

        .student-item:hover {
            background: var(--light);
            border-color: var(--primary);
        }

        .student-item.active {
            border-color: var(--primary);
            font-weight: 500;
        }

        #student-list a {
            color: var(--dark) !important;
        }

        .comment-list, .message-list {
            display: flex;
            flex-direction: column;
            gap: 1rem;
        }

        .comment, .message {
            background: var(--light);
            padding: 1rem;
            border-radius: 8px;
            border: 1px solid var(--border);
            transition: var(--transition);
        }

        .comment-header, .message-header {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            margin-bottom: 0.5rem;
        }

        .comment-header img, .message-header img {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            object-fit: cover;
        }

        .comment-header .user-info, .message-header .user-info {
            flex-grow: 1;
        }

        .comment-header .user-info span, .message-header .user-info span {
            font-weight: 500;
            font-size: 0.875rem;
            color: var(--dark);
        }

        .comment-content, .message-content {
            font-size: 0.875rem;
            line-height: 1.5;
            color: var(--dark);
            margin-bottom: 0.5rem;
        }

        .comment-meta, .message-meta {
            font-size: 0.75rem;
            color: var(--dark-grey);
            display: flex;
            gap: 1rem;
            align-items: center;
        }

        .comment-actions, .message-actions {
            display: flex;
            gap: 0.5rem;
        }

        .no {
            color: var(--dark) !important;
        }

        .action-btn {
            background: none;
            border: none;
            color: var(--primary);
            font-size: 0.75rem;
            font-weight: 500;
            cursor: pointer;
            padding: 0.25rem 0.5rem;
            border-radius: 4px;
            transition: var(--transition);
        }

        .action-btn:hover {
            background: var(--accent);
            color: #1557b0;
        }

        .action-btn.archive {
            color: var(--error);
        }

        .action-btn.archive:hover {
            background: #fce8e6;
            color: #b71c1c;
        }

        .modal {
            z-index: 1000000;
        }

        .modal-content {
            max-width: 600px;
            width: 100%;
        }

        .modal.active {
            display: flex;
        }

        .modal.active .modal-content {
            transform: scale(1);
            opacity: 1;
        }

        .modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1rem;
        }

        .modal-header h3 {
            font-size: 1.25rem;
            font-weight: 500;
            margin: 0;
        }

        .close-btn {
            background: none;
            border: none;
            font-size: 1.5rem;
            color: var(--text-secondary);
            cursor: pointer;
            transition: var(--transition);
        }

        .close-btn:hover {
            color: var(--text);
        }

        .success-notification, .error-notification {
            background: #10b981;
            color: #fff;
            padding: 1rem;
            margin: 1rem 1.5rem;
            border-radius: 4px;
            text-align: center;
            font-size: 0.875rem;
            font-weight: 500;
            box-shadow: var(--shadow-sm);
        }

        .error-notification {
            background-color: var(--error);
        }

        @media (max-width: 768px) {
            .two-column-container {
                grid-template-columns: 1fr;
            }

            .comment-section, .message-section {
                margin: 1rem;
                padding: 1rem;
            }

            .comment-form, .message-form {
                padding: 0.75rem;
            }

            .comment-form textarea, .message-form textarea, .message-form select {
                font-size: 0.85rem;
            }

            .comment, .message {
                padding: 0.75rem;
            }

            .modal-content {
                width: 95%;
                padding: 1rem;
            }

            .student-list {
                max-height: 150px;
            }
        }
    </style>
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

    <section id="content">
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
                    <h1>Messages & Comments - <?php echo htmlspecialchars($sectionDetails['sectionName'] . ' - ' . $sectionDetails['subjectName']); ?></h1>
                    <ul class="breadcrumb">
                        <li><a href="./professorDash.php?subjectID=<?php echo htmlspecialchars($sectionDetails['subjectID']); ?>">Dashboard</a></li>
                        <li><i class='bx bx-chevron-right'></i></li>
                        <li><a class="active" href="#">Messages & Comments</a></li>
                    </ul>
                </div>
                <a href="./professorDash.php?subjectID=<?php echo htmlspecialchars($sectionDetails['subjectID']); ?>" class="btn-download" aria-label="Go back to dashboard">
                    <i class='bx bxs-left-arrow'></i>
                    <span class="text">Back</span>
                </a>
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

            <div class="two-column-container">
                <div class="comment-section">
                    <h3>Class Discussion <?php if ($newCommentsCount > 0): ?><span class="notification-badge"><?php echo $newCommentsCount; ?> New</span><?php endif; ?></h3>
                    <form class="comment-form" method="POST">
                        <input type="hidden" name="teacherSectionID" value="<?php echo $teacherSectionID; ?>">
                        <textarea name="content" placeholder="Add a comment..." required aria-label="Comment Content"></textarea>
                        <button type="submit" name="submitComment">Post</button>
                    </form>
                    <div class="comment-list">
                        <?php if (!empty($comments)): ?>
                            <?php foreach ($comments as $comment): ?>
                                <div class="comment">
                                    <div class="comment-header">
                                        <img src="<?php echo htmlspecialchars($comment['image'] ?: './img/noprofile.png'); ?>" alt="User Profile">
                                        <div class="user-info">
                                            <span><?php echo htmlspecialchars(ucwords($comment['firstName'] . ' ' . $comment['lastName'])); ?></span>
                                        </div>
                                    </div>
                                    <div class="comment-content">
                                        <?php echo htmlspecialchars($comment['content']); ?>
                                    </div>
                                    <div class="comment-meta">
                                        <?php echo date('M d, Y, h:i A', strtotime($comment['createdDate'])); ?>
                                        <?php if (!empty($comment['updatedDate'])): ?>
                                            <span>(Edited <?php echo date('M d, Y, h:i A', strtotime($comment['updatedDate'])); ?>)</span>
                                        <?php endif; ?>
                                        <?php if ($comment['userID'] == $userID): ?>
                                            <div class="comment-actions">
                                                <button class="action-btn edit-btn" onclick="openModal('edit-comment-modal-<?php echo $comment['commentID']; ?>')" aria-label="Edit Comment">Edit</button>
                                                <form method="POST" style="display:inline;">
                                                    <input type="hidden" name="commentID" value="<?php echo $comment['commentID']; ?>">
                                                    <button type="submit" name="archiveComment" class="action-btn archive" onclick="return confirm('Are you sure you want to archive this comment?');" aria-label="Archive Comment">Archive</button>
                                                </form>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                    <?php if ($comment['userID'] == $userID): ?>
                                        <div id="edit-comment-modal-<?php echo $comment['commentID']; ?>" class="modal" role="dialog" aria-labelledby="edit-comment-modal-title-<?php echo $comment['commentID']; ?>">
                                            <div class="modal-content">
                                                <div class="modal-header">
                                                    <h3 id="edit-comment-modal-title-<?php echo $comment['commentID']; ?>">Edit Comment</h3>
                                                    <button class="close-btn" onclick="closeModal('edit-comment-modal-<?php echo $comment['commentID']; ?>')" aria-label="Close">×</button>
                                                </div>
                                                <form class="comment-form" method="POST">
                                                    <input type="hidden" name="commentID" value="<?php echo $comment['commentID']; ?>">
                                                    <textarea name="content" required aria-label="Edit Comment Content"><?php echo htmlspecialchars($comment['content']); ?></textarea>
                                                    <button type="submit" name="editComment">Save Changes</button>
                                                </form>
                                            </div>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <p class="no">No comments available.</p>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="message-section">
                    <h3>Private Messages</h3>
                    <?php if (!empty($newMessages)): ?>
                        <div class="notification-list-container">
                            <button class="notification-toggle" onclick="toggleNotificationList()">New Messages (<?php echo count($newMessages); ?>) <i class='bx bx-chevron-down'></i></button>
                            <div class="notification-list" id="notification-list">
                                <?php foreach ($newMessages as $newMessage): ?>
                                    <div class="notification-item">
                                        New private message from <?php echo htmlspecialchars(ucwords($newMessage['firstName'] . ' ' . $newMessage['lastName'])); ?>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    <?php endif; ?>
                    <div class="student-list-container">
                        <button class="student-list-toggle" onclick="toggleStudentList()">Select Student <i class='bx bx-chevron-down'></i></button>
                        <div class="student-list" id="student-list">
                            <?php if (!empty($students)): ?>
                                <?php foreach ($students as $student): ?>
                                    <a href="commentsDash.php?teacherSectionID=<?php echo $teacherSectionID; ?>&studentID=<?php echo $student['userID']; ?>&subjectID=<?php echo htmlspecialchars($sectionDetails['subjectID']); ?>" 
                                       class="student-item <?php echo $selectedStudentID == $student['userID'] ? 'active' : ''; ?>">
                                        <?php echo htmlspecialchars(ucwords($student['studentName'])); ?>
                                    </a>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <p class="no">No students enrolled.</p>
                            <?php endif; ?>
                        </div>
                    </div>
                    <?php if ($selectedStudentID): ?>
                        <form class="message-form" method="POST">
                            <input type="hidden" name="recipientID" value="<?php echo $selectedStudentID; ?>">
                            <textarea name="messageContent" placeholder="Type your message..." required aria-label="Message Content"></textarea>
                            <button type="submit" name="submitMessage">Send</button>
                        </form>
                        <div class="message-list">
                            <?php if (!empty($messages)): ?>
                                <?php foreach ($messages as $message): ?>
                                    <div class="message">
                                        <div class="message-header">
                                            <img src="<?php echo htmlspecialchars($message['senderImage'] ?: './img/noprofile.png'); ?>" alt="Sender Profile">
                                            <div class="user-info">
                                                <span><?php echo htmlspecialchars(ucwords($message['senderFirstName'] . ' ' . $message['senderLastName'])); ?></span>
                                            </div>
                                        </div>
                                        <div class="message-content">
                                            <?php echo htmlspecialchars($message['content']); ?>
                                        </div>
                                        <div class="message-meta">
                                            <?php echo date('M d, Y, h:i A', strtotime($message['createdDate'])); ?>
                                            <?php if ($message['senderID'] == $userID): ?>
                                                <div class="message-actions">
                                                    <button class="action-btn edit-btn" onclick="openModal('edit-message-modal-<?php echo $message['messageID']; ?>')" aria-label="Edit Message">Edit</button>
                                                    <form method="POST" style="display:inline;">
                                                        <input type="hidden" name="messageID" value="<?php echo $message['messageID']; ?>">
                                                        <button type="submit" name="archiveMessage" class="action-btn archive" onclick="return confirm('Are you sure you want to archive this message?');" aria-label="Archive Message">Archive</button>
                                                    </form>
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                        <?php if ($message['senderID'] == $userID): ?>
                                            <div id="edit-message-modal-<?php echo $message['messageID']; ?>" class="modal" role="dialog" aria-labelledby="edit-message-modal-title-<?php echo $message['messageID']; ?>">
                                                <div class="modal-content">
                                                    <div class="modal-header">
                                                        <h3 id="edit-message-modal-title-<?php echo $message['messageID']; ?>">Edit Message</h3>
                                                        <button class="close-btn" onclick="closeModal('edit-message-modal-<?php echo $message['messageID']; ?>')" aria-label="Close">×</button>
                                                    </div>
                                                    <form class="message-form" method="POST">
                                                        <input type="hidden" name="messageID" value="<?php echo $message['messageID']; ?>">
                                                        <textarea name="messageContent" required aria-label="Edit Message Content"><?php echo htmlspecialchars($message['content']); ?></textarea>
                                                        <button type="submit" name="editMessage">Save Changes</button>
                                                    </form>
                                                </div>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <p class="no">No messages with this student.</p>
                            <?php endif; ?>
                        </div>
                    <?php else: ?>
                        <p class="no">Select a student to view or send messages.</p>
                    <?php endif; ?>
                </div>
            </div>
        </main>
    </section>

    <script src="./utils/script.js"></script>
    <script>
        function openModal(modalId) {
            const modal = document.getElementById(modalId);
            modal.classList.add('active');
            setTimeout(() => {
                modal.querySelector('.modal-content').style.transform = 'scale(1)';
                modal.querySelector('.modal-content').style.opacity = '1';
            }, 10);
        }

        function closeModal(modalId) {
            const modal = document.getElementById(modalId);
            modal.querySelector('.modal-content').style.transform = 'scale(0.95)';
            modal.querySelector('.modal-content').style.opacity = '0';
            setTimeout(() => {
                modal.classList.remove('active');
            }, 200);
        }

        function toggleStudentList() {
            const studentList = document.getElementById('student-list');
            studentList.classList.toggle('active');
            const toggleButton = document.querySelector('.student-list-toggle i');
            toggleButton.classList.toggle('bx-chevron-down');
            toggleButton.classList.toggle('bx-chevron-up');
        }

        function toggleNotificationList() {
            const notificationList = document.getElementById('notification-list');
            notificationList.classList.toggle('active');
            const toggleButton = document.querySelector('.notification-toggle i');
            toggleButton.classList.toggle('bx-chevron-down');
            toggleButton.classList.toggle('bx-chevron-up');
        }
    </script>
</body>
</html>
<?php
$dbConnection = null;
?>