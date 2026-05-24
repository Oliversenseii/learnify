<?php
require_once './sessions/session_admin.php';
require_once '../../config/db_connection.php';
require_once './auto_logout.php';
require_once './check_status.php';

// Validate session
if (!isset($_SESSION['userID'])) {
    error_log("Session error: userID not set");
    header("Location: ../../index.php");
    exit;
}

$userID = filter_var($_SESSION['userID'], FILTER_VALIDATE_INT);
if ($userID === false) {
    error_log("Session error: Invalid userID");
    session_destroy();
    header("Location: ../../index.php");
    exit;
}

try {
    // Get user details
    $stmt = $dbConnection->prepare("SELECT firstName, userType, image FROM users WHERE userID = :userID AND userType = 'Admin'");
    $stmt->bindParam(':userID', $userID, PDO::PARAM_INT);
    if (!$stmt->execute()) {
        throw new PDOException("Failed to fetch user details");
    }
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$user) {
        error_log("User not found or not an Admin: userID=$userID");
        header("Location: ../../index.php");
        exit;
    }
    $_SESSION['firstName'] = htmlspecialchars($user['firstName']);
    $_SESSION['userType'] = htmlspecialchars($user['userType']);
    $_SESSION['image'] = $user['image'] ? htmlspecialchars($user['image']) : './img/noprofile.png';

    // Handle private message submission with file upload (PDF and images)
    if (isset($_POST['submitMessage']) && isset($_POST['recipientID'])) {
        $recipientID = filter_var($_POST['recipientID'], FILTER_VALIDATE_INT);
        $messageContent = isset($_POST['messageContent']) ? trim($_POST['messageContent']) : '';
        $attachmentPath = null;

        // Handle file upload (PDF and images)
        if (isset($_FILES['attachment']) && $_FILES['attachment']['error'] === UPLOAD_ERR_OK) {
            $allowedTypes = ['application/pdf', 'image/png', 'image/jpeg', 'image/jpg'];
            $maxFileSize = 5 * 1024 * 1024; // 5MB
            $uploadDir = '../../lib/upload_files/';
            if (!is_dir($uploadDir)) {
                mkdir($uploadDir, 0755, true);
            }

            $file = $_FILES['attachment'];
            if (in_array($file['type'], $allowedTypes) && $file['size'] <= $maxFileSize) {
                $fileExt = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
                $uniqueFileName = uniqid('file_') . '.' . $fileExt;
                $destination = $uploadDir . $uniqueFileName;

                if (move_uploaded_file($file['tmp_name'], $destination)) {
                    $attachmentPath = $destination;
                } else {
                    $_SESSION['error_message'] = "Failed to upload file.";
                }
            } else {
                $_SESSION['error_message'] = "Only PDF, PNG, JPEG, and JPG files are allowed (max 5MB).";
            }
        } elseif ($_FILES['attachment']['error'] !== UPLOAD_ERR_NO_FILE) {
            $_SESSION['error_message'] = "Error uploading file.";
        }

        if ($recipientID && ($messageContent || $attachmentPath) && !isset($_SESSION['error_message'])) {
            // Verify recipient is a professor
            $checkProfessorStmt = $dbConnection->prepare("
                SELECT userID 
                FROM users 
                WHERE userID = :recipientID AND userType = 'Professor' AND archived = 0
            ");
            $checkProfessorStmt->bindParam(':recipientID', $recipientID, PDO::PARAM_INT);
            if (!$checkProfessorStmt->execute()) {
                throw new PDOException("Failed to verify recipient");
            }
            if ($checkProfessorStmt->rowCount() > 0) {
                $insertMsgStmt = $dbConnection->prepare("
                    INSERT INTO admin_messages (sender_id, recipient_id, content, attachment_path, created_at) 
                    VALUES (:senderID, :recipientID, :content, :attachmentPath, NOW())
                ");
                $insertMsgStmt->bindParam(':senderID', $userID, PDO::PARAM_INT);
                $insertMsgStmt->bindParam(':recipientID', $recipientID, PDO::PARAM_INT);
                $insertMsgStmt->bindParam(':content', $messageContent, PDO::PARAM_STR);
                $insertMsgStmt->bindParam(':attachmentPath', $attachmentPath, PDO::PARAM_STR);
                if ($insertMsgStmt->execute()) {
                    $_SESSION['success_message'] = "Message sent successfully.";
                } else {
                    throw new PDOException("Failed to insert message");
                }
            } else {
                $_SESSION['error_message'] = "Invalid recipient. Please select a valid Teacher.";
            }
        } else {
            $_SESSION['error_message'] = "Either message content or an attachment is required.";
        }
        header("Location: message_professor.php" . (isset($_GET['professorID']) ? "?professorID=" . $_GET['professorID'] : ""));
        exit;
    }

    // Handle private message editing (with file upload/remove, PDF and images)
    if (isset($_POST['editMessage']) && isset($_POST['messageID'])) {
        $messageID = filter_var($_POST['messageID'], FILTER_VALIDATE_INT);
        $messageContent = isset($_POST['messageContent']) ? trim($_POST['messageContent']) : '';
        $removeAttachment = isset($_POST['removeAttachment']) && $_POST['removeAttachment'] === '1';
        $attachmentPath = null;

        // Fetch current message to get existing attachment path
        $checkMessageStmt = $dbConnection->prepare("
            SELECT attachment_path FROM admin_messages 
            WHERE message_id = :messageID AND sender_id = :userID AND archived = 0
        ");
        $checkMessageStmt->bindParam(':messageID', $messageID, PDO::PARAM_INT);
        $checkMessageStmt->bindParam(':userID', $userID, PDO::PARAM_INT);
        if (!$checkMessageStmt->execute()) {
            throw new PDOException("Failed to verify message ownership");
        }
        $message = $checkMessageStmt->fetch(PDO::FETCH_ASSOC);
        if ($message) {
            $currentAttachmentPath = $message['attachment_path'];

            // Handle file upload (PDF and images)
            if (isset($_FILES['attachment']) && $_FILES['attachment']['error'] === UPLOAD_ERR_OK) {
                $allowedTypes = ['application/pdf', 'image/png', 'image/jpeg', 'image/jpg'];
                $maxFileSize = 5 * 1024 * 1024; // 5MB
                $uploadDir = '../../lib/upload_files/';
                if (!is_dir($uploadDir)) {
                    mkdir($uploadDir, 0755, true);
                }

                $file = $_FILES['attachment'];
                if (in_array($file['type'], $allowedTypes) && $file['size'] <= $maxFileSize) {
                    $fileExt = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
                    $uniqueFileName = uniqid('file_') . '.' . $fileExt;
                    $destination = $uploadDir . $uniqueFileName;

                    if (move_uploaded_file($file['tmp_name'], $destination)) {
                        $attachmentPath = $destination;
                        // Delete old attachment if it exists
                        if ($currentAttachmentPath && file_exists($currentAttachmentPath)) {
                            unlink($currentAttachmentPath);
                        }
                    } else {
                        $_SESSION['error_message'] = "Failed to upload new file.";
                    }
                } else {
                    $_SESSION['error_message'] = "Only PDF, PNG, JPEG, and JPG files are allowed (max 5MB).";
                }
            } elseif ($_FILES['attachment']['error'] !== UPLOAD_ERR_NO_FILE) {
                $_SESSION['error_message'] = "Error uploading file.";
            } elseif ($removeAttachment && $currentAttachmentPath) {
                // Remove attachment
                if (file_exists($currentAttachmentPath)) {
                    unlink($currentAttachmentPath);
                }
                $attachmentPath = null;
            } else {
                // Keep existing attachment
                $attachmentPath = $currentAttachmentPath;
            }

            if ($messageID && $messageContent && !isset($_SESSION['error_message'])) {
                $updateMsgStmt = $dbConnection->prepare("
                    UPDATE admin_messages 
                    SET content = :content, attachment_path = :attachmentPath, updated_at = NOW() 
                    WHERE message_id = :messageID
                ");
                $updateMsgStmt->bindParam(':messageID', $messageID, PDO::PARAM_INT);
                $updateMsgStmt->bindParam(':content', $messageContent, PDO::PARAM_STR);
                $updateMsgStmt->bindParam(':attachmentPath', $attachmentPath, PDO::PARAM_STR);
                if ($updateMsgStmt->execute()) {
                    $_SESSION['success_message'] = "Message updated successfully.";
                } else {
                    throw new PDOException("Failed to update message");
                }
            } else {
                $_SESSION['error_message'] = "Message content is required.";
            }
        } else {
            $_SESSION['error_message'] = "You don't have permission to edit this message.";
        }
        header("Location: message_professor.php" . (isset($_GET['professorID']) ? "?professorID=" . $_GET['professorID'] : ""));
        exit;
    }

    // Handle private message archiving
    if (isset($_POST['archiveMessage']) && isset($_POST['messageID'])) {
        $messageID = filter_var($_POST['messageID'], FILTER_VALIDATE_INT);
        if ($messageID) {
            $checkMessageStmt = $dbConnection->prepare("
                SELECT attachment_path FROM admin_messages 
                WHERE message_id = :messageID AND sender_id = :userID AND archived = 0
            ");
            $checkMessageStmt->bindParam(':messageID', $messageID, PDO::PARAM_INT);
            $checkMessageStmt->bindParam(':userID', $userID, PDO::PARAM_INT);
            if (!$checkMessageStmt->execute()) {
                throw new PDOException("Failed to verify message for archiving");
            }
            if ($checkMessageStmt->rowCount() > 0) {
                $message = $checkMessageStmt->fetch(PDO::FETCH_ASSOC);
                $archiveMsgStmt = $dbConnection->prepare("
                    UPDATE admin_messages 
                    SET archived = 1 
                    WHERE message_id = :messageID
                ");
                $archiveMsgStmt->bindParam(':messageID', $messageID, PDO::PARAM_INT);
                if ($archiveMsgStmt->execute()) {
                    // Delete attachment file when archiving
                    if ($message['attachment_path'] && file_exists($message['attachment_path'])) {
                        unlink($message['attachment_path']);
                    }
                    $_SESSION['success_message'] = "Message archived successfully.";
                } else {
                    throw new PDOException("Failed to archive message");
                }
            } else {
                $_SESSION['error_message'] = "You don't have permission to archive this message.";
            }
        } else {
            $_SESSION['error_message'] = "Invalid message ID.";
        }
        header("Location: message_professor.php" . (isset($_GET['professorID']) ? "?professorID=" . $_GET['professorID'] : ""));
        exit;
    }

    // Fetch professors for selection (include email and image)
    $professorStmt = $dbConnection->prepare("
        SELECT userID, firstName, lastName, CONCAT(firstName, ' ', lastName) AS professorName, email, image
        FROM users
        WHERE userType = 'Professor' AND archived = 0
        ORDER BY firstName
    ");
    if (!$professorStmt->execute()) {
        throw new PDOException("Failed to fetch professors");
    }
    $professors = $professorStmt->fetchAll(PDO::FETCH_ASSOC);

    // Fetch selected professor details
    $selectedProfessor = null;
    $selectedProfessorID = isset($_GET['professorID']) ? filter_var($_GET['professorID'], FILTER_VALIDATE_INT) : null;
    if ($selectedProfessorID) {
        $selectedProfStmt = $dbConnection->prepare("
            SELECT userID, firstName, lastName, email, image
            FROM users
            WHERE userID = :professorID AND userType = 'Professor' AND archived = 0
        ");
        $selectedProfStmt->bindParam(':professorID', $selectedProfessorID, PDO::PARAM_INT);
        if ($selectedProfStmt->execute()) {
            $selectedProfessor = $selectedProfStmt->fetch(PDO::FETCH_ASSOC);
        }
    }

    // Fetch private messages for a specific professor if selected
    $messages = [];
    if ($selectedProfessorID) {
        $messageStmt = $dbConnection->prepare("
            SELECT am.message_id, am.content, am.attachment_path, am.created_at, am.updated_at, am.sender_id, am.recipient_id, 
                   us.firstName AS senderFirstName, us.lastName AS senderLastName, us.image AS senderImage
            FROM admin_messages am
            JOIN users us ON am.sender_id = us.userID
            WHERE am.archived = 0 
            AND ((am.sender_id = :userID AND am.recipient_id = :professorID) OR (am.sender_id = :professorID AND am.recipient_id = :userID))
            ORDER BY am.created_at ASC
        ");
        $messageStmt->bindParam(':userID', $userID, PDO::PARAM_INT);
        $messageStmt->bindParam(':professorID', $selectedProfessorID, PDO::PARAM_INT);
        if (!$messageStmt->execute()) {
            throw new PDOException("Failed to fetch messages");
        }
        $messages = $messageStmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // Categorize attachments by month and type
    function categorizeAttachments($messages) {
        $media = [];
        $files = [];
        $imageExts = ['png', 'jpeg', 'jpg'];
        $fileExts = ['pdf'];

        foreach ($messages as $message) {
            if ($message['attachment_path']) {
                $fileExt = strtolower(pathinfo($message['attachment_path'], PATHINFO_EXTENSION));
                $month = date('F Y', strtotime($message['created_at']));
                $attachment = [
                    'path' => $message['attachment_path'],
                    'filename' => basename($message['attachment_path']),
                    'created_at' => $message['created_at']
                ];

                if (in_array($fileExt, $imageExts)) {
                    $media[$month][] = $attachment;
                } elseif (in_array($fileExt, $fileExts)) {
                    $files[$month][] = $attachment;
                }
            }
        }

        return ['media' => $media, 'files' => $files];
    }

    // Categorize attachments for the selected professor
    $attachments = $selectedProfessorID ? categorizeAttachments($messages) : ['media' => [], 'files' => []];

    // Notification logic: Fetch new private messages from professors
    $lastVisitMessages = $_SESSION['last_visit_messages']['admin_messages'] ?? '1970-01-01 00:00:00';
    $newMessagesStmt = $dbConnection->prepare("
        SELECT am.message_id, us.firstName, us.lastName, us.userID 
        FROM admin_messages am
        JOIN users us ON am.sender_id = us.userID
        WHERE am.archived = 0 
        AND am.recipient_id = :userID AND am.created_at > :lastVisit
        AND us.userType = 'Professor'
        ORDER BY am.created_at DESC
    ");
    $newMessagesStmt->bindParam(':userID', $userID, PDO::PARAM_INT);
    $newMessagesStmt->bindParam(':lastVisit', $lastVisitMessages, PDO::PARAM_STR);
    if (!$newMessagesStmt->execute()) {
        throw new PDOException("Failed to fetch new messages");
    }
    $newMessages = $newMessagesStmt->fetchAll(PDO::FETCH_ASSOC);

    // Update last visit timestamp for admin messages
    $_SESSION['last_visit_messages']['admin_messages'] = date('Y-m-d H:i:s');

} catch (PDOException $e) {
    error_log("Database Error in message_professor.php: " . $e->getMessage() . " at line " . $e->getLine());
    $_SESSION['error_message'] = "An error occurred while processing your request. Please try again later.";
    header("Location: adminDash.php");
    exit;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link href='https://unpkg.com/boxicons@2.0.9/css/boxicons.min.css' rel='stylesheet'>
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Roboto:wght@400;500;700&display=swap">
    <link rel="icon" href="../../img/learnify-logo.png" type="image/x-icon">
    <link rel="stylesheet" href="./utils/style.css">
    <link rel="stylesheet" href="./utils/logout.css">
    <link rel="stylesheet" href="./utils/animation_slide.css">
    <link rel="stylesheet" href="./utils/logo.css">
    <link rel="stylesheet" href="./css/scrollbar.css">
    <script src="./logout.js"></script>
    <title>Learnify - Messages to Teachers</title>
    <style>
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
            --chat-bg: #f5f6f5;
            --sent-bg: #d1e7ff;
            --received-bg: #ffffff;
        }

        .messenger-container {
            display: flex;
            height: calc(95vh - 100px);
            margin: 1rem;
            border-radius: 8px;
            box-shadow: var(--shadow-md);
            overflow: hidden;
        }

        .teacher-list {
            width: 30%;
            min-width: 250px;
            overflow-y: auto;
            background: var(--light);
        }

        .teacher-item {
            display: flex;
            align-items: center;
            padding: 1rem;
            border-bottom: 0.75px solid #ccc;
            border-radius: 5px;
            cursor: pointer;
            transition: var(--transition);
        }

        .teacher-item:hover {
            background: linear-gradient(135deg, var(--grey), gray);
        }

        .teacher-item.active {
            border-left: 4px solid #007bff;
        }
        
        .teacher-item.active .teacher-info h4 {
            color: #007bff;
        }
        
        .teacher-item img {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            margin-right: 1rem;
            object-fit: cover;
        }

        .teacher-info {
            flex-grow: 1;
        }

        .teacher-info h4 {
            margin: 0;
            font-size: clamp(1.1rem, 3vw, 1.2rem);
            font-weight: 500;
            color: var(--dark);
        }

        .teacher-info p {
            margin: 0.25rem 0 0;
            font-size: clamp(1.1rem, 3vw, 1.2rem);
            color: var(--text-secondary);
            display: none;
        }

        .notification-badge {
            background-color: var(--error);
            color: #fff;
            border-radius: 12px;
            padding: 0.2rem 0.6rem;
            font-size: 0.9rem;
            margin-left: auto;
        }

        .chat-area {
            flex-grow: 1;
            display: flex;
            flex-direction: row;
        }

        .chat-content {
            flex-grow: 1;
            display: flex;
            flex-direction: column;
            background-color: var(--grey);
            /* background-image: url("https://www.transparenttextures.com/patterns/white-wall-3.png"); */
        }

        .chat-header {
            padding: 1rem;
            display: flex;
            align-items: center;
            background: linear-gradient(135deg, var(--light), #007bff);
            gap: 1rem;
        }

        .chat-header img {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            object-fit: cover;
        }

        .chat-header h3 {
            margin: 0;
            font-size: clamp(1.2rem, 2vw, 1.3rem);
            font-weight: 500;
            color: var(--dark);
        }

        .chat-messages {
            flex-grow: 1;
            padding: 1rem;
            overflow-y: auto;
            display: flex;
            flex-direction: column;
            gap: 1rem;
        }

        .message {
            max-width: 70%;
            padding: 0.75rem;
            border-radius: 12px;
            position: relative;
            font-size: clamp(1.3rem, 3vw, 1.4rem);
            line-height: 1.5;
            color: var(--text);
        }

        .message.sent {
            background: var(--sent-bg);
            margin-left: auto;
            text-align: right;
            border-bottom-right-radius: 4px;
        }

        .message.received {
            background: var(--received-bg);
            margin-right: auto;
            border-bottom-left-radius: 4px;
        }

        .message-content {
            margin-bottom: 0.5rem;
        }

        .message-attachment {
            background: var(--background);
            border: 1px solid #ccc;
            border-radius: 8px;
            padding: 0.5rem;
            margin-top: 0.5rem;
            margin-bottom: 0.5rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
            cursor: pointer;
            transition: var(--transition);
        }

        .message-attachment:hover {
            background: red;
        }

        .message-attachment.sent {
            background: var(--sent-bg);
        }

        .message-attachment.received {
            background: var(--received-bg);
        }

        .message-attachment img {
            max-width: 60px;
            height: 60px;
            border-radius: 4px;
            width: 100%;
            cursor: pointer;
        }

        .message-attachment .attachment-info {
            flex-grow: 1;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .message-attachment .attachment-info i {
            font-size: clamp(1.2rem, 2vw, 1.3rem);
            color: var(--text-secondary);
        }

        .message-attachment .attachment-info span {
            font-size: clamp(0.9rem, 1.5vw, 1rem);
            color: var(--text);
        }

        .message-meta {
            font-size: clamp(0.8rem, 1.5vw, 0.9rem);
            color: var(--text-secondary);
            display: flex;
            gap: 0.5rem;
            align-items: right;
            border-top: 0.75px solid #ccc;
            padding-top: 5px;
        }

        .message-actions {
            display: flex;
            gap: 0.2rem;
        }

        .action-btn {
            background: none;
            border: none;
            color: var(--primary);
            font-size: clamp(0.8rem, 1.5vw, 0.9rem);
            cursor: pointer;
            padding: 0.25rem 0.5rem;
            border-radius: 4px;
            transition: var(--transition);
        }

        .action-btn:hover {
            background: var(--accent);
        }

        .action-btn.archive {
            color: var(--error);
        }

        .action-btn.archive:hover {
            background: #fce8e6;
        }

        .message-form {
            padding: 1rem;
            background-color: var(--light);
            display: flex;
            align-items: center;
            gap: 0.5rem;
            position: relative;
        }

        .message-form textarea {
            flex-grow: 1;
            padding: 1rem;
            border: 1px solid var(--border);
            border-radius: 20px;
            font-size: clamp(1.1rem, 3vw, 1.2rem);
            resize: none;
            height: 70px;
            background: var(--grey);
            color: var(--dark);
        }

        .message-form textarea:focus {
            border-color: var(--primary);
            outline: none;
        }

        .message-form input[type="file"] {
            display: none;
        }

        .message-form label[for="attachment"] {
            cursor: pointer;
            padding: 0.5rem;
            border-radius: 50%;
            background: transparent;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .message-form label[for="attachment"] i {
            font-size: clamp(1.8rem, 3vw, 2rem);
            color: var(--primary);
        }

        .message-form button {
            background: var(--primary);
            color: #fff;
            border: none;
            border-radius: 50%;
            width: 40px;
            height: 40px;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            transition: var(--transition);
            font-size: clamp(1.1rem, 2vw, 1.2rem);
        }

        .message-form button:hover {
            background: #1557b0;
        }

        .file-preview {
            font-size: clamp(0.9rem, 1.5vw, 1rem);
            color: var(--text-secondary);
            margin-left: 1rem;
        }

        .tabs-container {
            width: 250px;
            background: var(--light);
            overflow-y: auto;
        }

        .tabs {
            display: flex;
        }

        .tab {
            flex: 1;
            padding: 1rem;
            text-align: center;
            cursor: pointer;
            font-size: clamp(1.1rem, 3vw, 1.2rem);
            color: var(--dark);
            transition: var(--transition);
        }

        .tab.active {
            color: var(--primary);
            border-bottom: 2px solid var(--primary);
        }

        .tab:hover {
            background: linear-gradient(135deg, var(--grey), gray);
        }

        .tab-content {
            display: none;
            padding: 1rem;
        }

        .tab-content.active {
            display: block;
        }

        .month-group {
            margin-bottom: 1rem;
        }

        .month-group h4 {
            font-size: clamp(1.1rem, 3vw, 1.2rem);
            color: var(--dark);
            margin: 0.5rem 0;
        }

        .media-item img {
            width: 130px;
            height: 130px;
            padding: 5px;
            border-radius: 4px;
            border: 1px solid #ccc;
            margin: 0.5rem;
            cursor: pointer;
            background-color: var(--grey);
        }

        .file-item {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            margin: 0.5rem 0;
            background: var(--background);
            border: 1px solid var(--border);
            border-radius: 8px;
            padding: 0.5rem;
            cursor: pointer;
            transition: var(--transition);
        }

        .file-item:hover {
            background: var(--accent);
        }

        .file-item i {
            font-size: clamp(1.2rem, 2vw, 1.3rem);
            color: var(--text-secondary);
        }

        .file-item span {
            font-size: clamp(1.1rem, 3vw, 1.2rem);
            color: var(--text);
        }

        .modal {
            z-index: 1000000;
        }

        .modal.active {
            display: flex;
        }

        .modal.active .modal-content {
            transform: scale(1);
            opacity: 1;
            max-width: 700px;
            width: 100%;
        }

        .modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1rem;
            border-bottom: 1px solid var(--border);
            padding-bottom: 10px;
        }

        .modal-header h3 {
            font-size: clamp(1.5rem, 2vw, 1.8rem);
            font-weight: 500;
            color: var(--dark);
            margin: 0;
        }

        .close-btn {
            background: none;
            border: none;
            font-size: clamp(1.5rem, 2vw, 1.8rem);
            color: var(--text-secondary);
            cursor: pointer;
            transition: var(--transition);
        }

        .close-btn:hover {
            color: var(--error);
        }

        .modal .message-form {
            flex-direction: column;
            border: none;
            background: transparent;
        }

        .modal .message-form textarea {
            border-radius: 4px;
            width: 100%;
            max-width: 600px;
            font-size: clamp(1.1rem, 3vw, 1.2rem);
            height: 100px;
        }

        .modal .message-form input[type="file"] {
            display: block;
            padding: 1rem;
            font-size: clamp(1.1rem, 3vw, 1.2rem);
            border: 1px solid var(--border);
            border-radius: 4px;
            background: var(--grey);
        }

        .modal .message-form input[type="file"]::file-selector-button {
            background: linear-gradient(135deg, #007bff, #0056b3);
            color: white;
            padding: 5px 10px;
            border: none;
            border-radius: 4px;
            font-size: clamp(1.1rem, 3vw, 1.2rem);
            cursor: pointer;
        }

        .modal .message-form input[type="file"]::file-selector-button:hover {
            background: linear-gradient(135deg, #0056b3, #003d80);
        }

        .modal .message-form button {
            border-radius: 4px;
            width: auto;
            padding: 0.5rem 1rem;
            font-size: clamp(1.1rem, 3vw, 1.2rem);
            height: auto;
        }

        .modal .file-preview {
            margin: 0.5rem 0;
        }

        .success-notification, .error-notification {
            background-color: var(--success);
            color: #fff;
            padding: 1rem;
            margin: 1rem;
            border-radius: 4px;
            width: fit-content;
            margin: 0 auto;
            text-align: center;
            font-size: clamp(1.1rem, 3vw, 1.2rem);
        }

        .error-notification {
            background-color: var(--error);
        }

        .no-messages {
            text-align: center;
            color: var(--text-secondary);
            font-size: clamp(1.1rem, 3vw, 1.2rem);
            padding: 2rem;
        }

        @media (max-width: 768px) {
            .messenger-container {
                flex-direction: column;
                height: auto;
            }

            .teacher-list {
                width: 100%;
                max-height: 200px;
            }

            .chat-area {
                flex-direction: column;
            }

            .tabs-container {
                width: 100%;
                border-left: none;
                border-top: 1px solid var(--border);
            }

            .chat-content {
                height: calc(100vh - 300px);
            }

            .message {
                max-width: 85%;
            }

            .message-form textarea {
                font-size: 0.9rem;
            }
        }

        /* Styles for collapsible month groups */
        .month-group-header {
            display: flex;
            align-items: center;
            cursor: pointer;
        }

        .month-group-header i {
            margin-right: 0.5rem;
            transition: transform 0.2s ease;
            color: var(--error);
        }

        .month-group-header.collapsed i {
            transform: rotate(-90deg);
        }

        .month-group-content {
            display: block;
        }

        .month-group-content.collapsed {
            display: none;
        }

        .attachment-preview img {
            max-width: 100px;
            height: auto;
            border-radius: 4px;
            margin: 0.5rem 0;
        }

        .attachment-preview .attachment-info {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            background: var(--background);
            border: 1px solid var(--border);
            border-radius: 8px;
            padding: 0.5rem;
            margin: 0.5rem 0;
            cursor: pointer;
            transition: var(--transition);
        }

        .attachment-preview .attachment-info:hover {
            background: var(--accent);
        }

        /* Enhanced view modal styles */
        .view-modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.7);
            z-index: 1000001;
            justify-content: center;
            align-items: center;
        }

        .view-modal.active {
            display: flex;
        }

        .view-modal-content {
            background: var(--light);
            padding: 1.5rem;
            border-radius: 12px;
            max-width: 800px;
            width: 100%;
            height: 95vh;
            overflow: hidden;
            position: relative;
            box-shadow: var(--shadow-md);
            display: flex;
            flex-direction: column;
            gap: 1rem;
        }

        .view-modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            border-bottom: 1px solid var(--border);
            padding-bottom: 0.5rem;
        }

        .view-modal-header h3 {
            margin: 0;
            font-size: clamp(1.2rem, 2vw, 1.3rem);
            color: var(--dark);
            font-weight: 500;
        }

        .view-modal-close {
            background: var(--secondary);
            border: none;
            font-size: clamp(1.2rem, 2vw, 1.5rem);
            color: var(--text-secondary);
            cursor: pointer;
            padding: 0.5rem;
            border-radius: 50%;
            transition: var(--transition);
        }

        .view-modal-close:hover {
            background: var(--error);
            color: #fff;
        }

        .view-modal-download {
            background: var(--primary);
            color: #fff;
            border: none;
            padding: 0.5rem 1rem;
            border-radius: 4px;
            text-decoration: none;
            font-size: clamp(0.9rem, 1.5vw, 1rem);
            transition: var(--transition);
            margin-right: 0.5rem;
        }

        .view-modal-download:hover {
            background: #1557b0;
        }

        .view-modal-content img {
            max-width: 800px;
            width: 100%;
            max-height: 80vh;
            object-fit: contain;
            border-radius: 8px;
        }

        .view-modal-content iframe {
            max-width: 800px;
            width: 100%;
            height: 90vh;
            border: none;
            border-radius: 8px;
        }

        .view-modal-content p {
            font-size: clamp(1rem, 2vw, 1.1rem);
            color: var(--text);
            text-align: center;
        }
    </style>
</head>
<body>
    <section id="sidebar">
        <?php require_once './brand.php' ?>
        <ul class="side-menu top">
            <li>
                <a href="./adminDash.php">
                    <i class='bx bxs-dashboard'></i>
                    <span class="text">Dashboard</span>
                </a>
            </li>
            <li class="active">
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

    <?php require_once './view/modal.php' ?>

    <section id="content">
        <nav>
            <i class='bx bx-menu' aria-label="Toggle Sidebar"></i>
            <form action="search.php" method="get">
                <div class="form-input">
                    <input type="search" name="query" placeholder="Search users, subjects, tracks, sections..." required aria-label="Search">
                    <button type="submit" class="search-btn" aria-label="Search"><i class='bx bx-search'></i></button>
                </div>
            </form>
            <input type="checkbox" id="switch-mode" hidden aria-label="Toggle Dark Mode">
            <label for="switch-mode" class="switch-mode"></label>
            <a href="./main_acc.php" class="profile" aria-label="Profile">
                <img src="<?php echo $_SESSION['image']; ?>" alt="Profile Image">
                <div>
                    <p><?php echo htmlspecialchars($_SESSION['firstName']); ?></p>
                    <small>Admin</small>
                </div>
            </a>
        </nav>

        <main>
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

            <div class="messenger-container">
                <div class="teacher-list">
                    <?php if (!empty($professors)): ?>
                        <?php foreach ($professors as $professor): ?>
                            <?php
                                $newMessageCount = 0;
                                foreach ($newMessages as $newMessage) {
                                    if ($newMessage['userID'] == $professor['userID']) {
                                        $newMessageCount++;
                                    }
                                }
                            ?>
                            <a href="message_professor.php?professorID=<?php echo $professor['userID']; ?>" 
                               class="teacher-item <?php echo $selectedProfessorID == $professor['userID'] ? 'active' : ''; ?>">
                                <img src="<?php echo htmlspecialchars($professor['image'] ?: './img/noprofile.png'); ?>" alt="Teacher Profile">
                                <div class="teacher-info">
                                    <h4><?php echo htmlspecialchars(ucwords($professor['professorName'])); ?></h4>
                                    <p><?php echo htmlspecialchars($professor['email']); ?></p>
                                </div>
                                <?php if ($newMessageCount > 0): ?>
                                    <span class="notification-badge"><?php echo $newMessageCount; ?></span>
                                <?php endif; ?>
                            </a>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <p class="no-messages">No Teachers available.</p>
                    <?php endif; ?>
                </div>

                <div class="chat-area">
                    <?php if ($selectedProfessor): ?>
                        <div class="chat-content">
                            <div class="chat-header">
                                <img src="<?php echo htmlspecialchars($selectedProfessor['image'] ?: './img/noprofile.png'); ?>" alt="Selected Professor Profile">
                                <h3><?php echo htmlspecialchars(ucwords($selectedProfessor['firstName'] . ' ' . $selectedProfessor['lastName'])); ?></h3>
                            </div>
                            <div class="chat-messages" id="chat-messages">
                                <?php if (!empty($messages)): ?>
                                    <?php foreach ($messages as $message): ?>
                                        <div class="message <?php echo $message['sender_id'] == $userID ? 'sent' : 'received'; ?>">
                                            <div class="message-content">
                                                <?php echo nl2br(htmlspecialchars($message['content'] ?: '(No message content, see attachment)')); ?>
                                            </div>
                                            <?php if ($message['attachment_path']): ?>
                                                <div class="message-attachment <?php echo $message['sender_id'] == $userID ? 'sent' : 'received'; ?>" 
                                                     onclick="openViewModal('<?php echo htmlspecialchars($message['attachment_path']); ?>', '<?php echo in_array(strtolower(pathinfo($message['attachment_path'], PATHINFO_EXTENSION)), ['png', 'jpeg', 'jpg']) ? 'image' : 'file'; ?>', '<?php echo htmlspecialchars(basename($message['attachment_path'])); ?>')">
                                                    <?php
                                                        $fileExt = strtolower(pathinfo($message['attachment_path'], PATHINFO_EXTENSION));
                                                        $imageExts = ['png', 'jpeg', 'jpg'];
                                                        if (in_array($fileExt, $imageExts)) {
                                                            echo "<img src='" . htmlspecialchars($message['attachment_path']) . "' alt='Attachment Image'>";
                                                        }
                                                    ?>
                                                    <div class="attachment-info">
                                                        <i class='bx bx-file'></i>
                                                        <span><?php echo htmlspecialchars(basename($message['attachment_path'])); ?></span>
                                                    </div>
                                                </div>
                                            <?php endif; ?>
                                            <div class="message-meta">
                                                <?php echo date('M d, Y, h:i A', strtotime($message['updated_at'] ?: $message['created_at'])); ?>
                                                <?php if ($message['sender_id'] == $userID): ?>
                                                    <div class="message-actions">
                                                        <button class="action-btn edit-btn" onclick="openModal('edit-message-modal-<?php echo $message['message_id']; ?>')" aria-label="Edit Message">Edit</button>
                                                        <form method="POST" style="display:inline;">
                                                            <input type="hidden" name="messageID" value="<?php echo $message['message_id']; ?>">
                                                            <button type="submit" name="archiveMessage" class="action-btn archive" onclick="return confirm('Are you sure you want to archive this message?');" aria-label="Archive Message">Archive</button>
                                                        </form>
                                                    </div>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                        <?php if ($message['sender_id'] == $userID): ?>
                                            <div id="edit-message-modal-<?php echo $message['message_id']; ?>" class="modal" role="dialog" aria-labelledby="edit-message-modal-title-<?php echo $message['message_id']; ?>">
                                                <div class="modal-content">
                                                    <div class="modal-header">
                                                        <h3 id="edit-message-modal-title-<?php echo $message['message_id']; ?>">Edit Message</h3>
                                                        <button class="close-btn" onclick="closeModal('edit-message-modal-<?php echo $message['message_id']; ?>')" aria-label="Close">×</button>
                                                    </div>
                                                    <form class="message-form" method="POST" enctype="multipart/form-data">
                                                        <input type="hidden" name="messageID" value="<?php echo $message['message_id']; ?>">
                                                        <textarea name="messageContent" required aria-label="Edit Message Content"><?php echo htmlspecialchars($message['content']); ?></textarea>
                                                        <?php if ($message['attachment_path']): ?>
                                                            <div class="attachment-preview">
                                                                <p>Current Attachment:</p>
                                                                <div class="attachment-info" onclick="openViewModal('<?php echo htmlspecialchars($message['attachment_path']); ?>', '<?php echo in_array(strtolower(pathinfo($message['attachment_path'], PATHINFO_EXTENSION)), ['png', 'jpeg', 'jpg']) ? 'image' : 'file'; ?>', '<?php echo htmlspecialchars(basename($message['attachment_path'])); ?>')">
                                                                    <?php
                                                                        $fileExt = strtolower(pathinfo($message['attachment_path'], PATHINFO_EXTENSION));
                                                                        $imageExts = ['png', 'jpeg', 'jpg'];
                                                                        if (in_array($fileExt, $imageExts)) {
                                                                            echo "<img src='" . htmlspecialchars($message['attachment_path']) . "' alt='Current Attachment'>";
                                                                        }
                                                                    ?>
                                                                    <i class='bx bx-file'></i>
                                                                    <span><?php echo htmlspecialchars(basename($message['attachment_path'])); ?></span>
                                                                </div>
                                                                <label>
                                                                    <input type="checkbox" name="removeAttachment" value="1"> Remove current attachment
                                                                </label>
                                                            </div>
                                                        <?php endif; ?>
                                                        <input type="file" name="attachment" id="edit-attachment-<?php echo $message['message_id']; ?>" accept="application/pdf,image/png,image/jpeg,image/jpg" aria-label="Replace Attachment">
                                                        <div class="file-preview" id="edit-file-preview-<?php echo $message['message_id']; ?>"></div>
                                                        <button type="submit" name="editMessage">Save Changes</button>
                                                    </form>
                                                </div>
                                            </div>
                                        <?php endif; ?>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <p class="no-messages">No messages with this teacher.</p>
                                <?php endif; ?>
                            </div>
                            <form class="message-form" method="POST" enctype="multipart/form-data">
                                <input type="hidden" name="recipientID" value="<?php echo $selectedProfessorID; ?>">
                                <textarea name="messageContent" placeholder="Type a message..." aria-label="Message Content"></textarea>
                                <label for="attachment"><i class='bx bx-paperclip'></i></label>
                                <input type="file" id="attachment" name="attachment" accept="application/pdf,image/png,image/jpeg,image/jpg" aria-label="Attach File">
                                <div class="file-preview" id="file-preview"></div>
                                <button type="submit" name="submitMessage" aria-label="Send Message"><i class='bx bx-send'></i></button>
                            </form>
                        </div>
                        <div class="tabs-container">
                            <div class="tabs">
                                <div class="tab active" data-tab="media">Media</div>
                                <div class="tab" data-tab="files">Files</div>
                            </div>
                            <div class="tab-content active" id="media">
                                <?php if (!empty($attachments['media'])): ?>
                                    <?php foreach ($attachments['media'] as $month => $items): ?>
                                        <div class="month-group">
                                            <div class="month-group-header" onclick="toggleMonthGroup(this)">
                                                <i class='bx bx-chevron-down'></i>
                                                <h4><?php echo htmlspecialchars($month); ?></h4>
                                            </div>
                                            <div class="month-group-content">
                                                <?php foreach ($items as $item): ?>
                                                    <div class="media-item">
                                                        <img src="<?php echo htmlspecialchars($item['path']); ?>" alt="Media" 
                                                             onclick="openViewModal('<?php echo htmlspecialchars($item['path']); ?>', 'image', '<?php echo htmlspecialchars($item['filename']); ?>')">
                                                    </div>
                                                <?php endforeach; ?>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <p class="no-messages">No media files.</p>
                                <?php endif; ?>
                            </div>
                            <div class="tab-content" id="files">
                                <?php if (!empty($attachments['files'])): ?>
                                    <?php foreach ($attachments['files'] as $month => $items): ?>
                                        <div class="month-group">
                                            <div class="month-group-header" onclick="toggleMonthGroup(this)">
                                                <i class='bx bx-chevron-down'></i>
                                                <h4><?php echo htmlspecialchars($month); ?></h4>
                                            </div>
                                            <div class="month-group-content">
                                                <?php foreach ($items as $item): ?>
                                                    <div class="file-item" 
                                                         onclick="openViewModal('<?php echo htmlspecialchars($item['path']); ?>', 'file', '<?php echo htmlspecialchars($item['filename']); ?>')">
                                                        <i class='bx bx-file'></i>
                                                        <span><?php echo htmlspecialchars($item['filename']); ?></span>
                                                    </div>
                                                <?php endforeach; ?>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <p class="no-messages">No files.</p>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php else: ?>
                        <p class="no-messages">Select a teacher to start messaging.</p>
                    <?php endif; ?>
                </div>
            </div>

            <!-- View Modal for Images and Files -->
            <div id="view-modal" class="view-modal">
                <div class="view-modal-content">
                    <div class="view-modal-header">
                        <h3 id="view-modal-title">File Preview</h3>
                        <div>
                            <a id="view-modal-download" class="view-modal-download" href="#" download>Download</a>
                            <button class="view-modal-close" onclick="closeViewModal()" aria-label="Close">×</button>
                        </div>
                    </div>
                    <div id="view-modal-content"></div>
                </div>
            </div>
        </main>
    </section>

    <script src="./utils/script.js"></script>
    <script>
        // Modal handling
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

        // View modal handling
        function openViewModal(filePath, fileType, fileName) {
            const modal = document.getElementById('view-modal');
            const content = document.getElementById('view-modal-content');
            const title = document.getElementById('view-modal-title');
            const downloadLink = document.getElementById('view-modal-download');
            title.textContent = fileName || 'File Preview';
            if (fileType === 'image') {
                content.innerHTML = `<img src="${filePath}" alt="Media Preview">`;
            } else {
                content.innerHTML = `<iframe src="${filePath}" title="File Preview"></iframe>`;
            }
            downloadLink.href = filePath;
            downloadLink.download = fileName;
            modal.classList.add('active');
        }

        function closeViewModal() {
            const modal = document.getElementById('view-modal');
            modal.classList.remove('active');
            document.getElementById('view-modal-content').innerHTML = '';
            document.getElementById('view-modal-title').textContent = 'File Preview';
            document.getElementById('view-modal-download').href = '#';
        }

        // Collapsible month groups
        function toggleMonthGroup(header) {
            const content = header.nextElementSibling;
            const icon = header.querySelector('i');
            content.classList.toggle('collapsed');
            header.classList.toggle('collapsed');
        }

        // Auto-scroll to the bottom of the chat
        document.addEventListener('DOMContentLoaded', () => {
            const chatMessages = document.getElementById('chat-messages');
            if (chatMessages) {
                chatMessages.scrollTop = chatMessages.scrollHeight;
            }

            // Tab switching
            const tabs = document.querySelectorAll('.tab');
            const tabContents = document.querySelectorAll('.tab-content');

            tabs.forEach(tab => {
                tab.addEventListener('click', () => {
                    tabs.forEach(t => t.classList.remove('active'));
                    tabContents.forEach(c => c.classList.remove('active'));

                    tab.classList.add('active');
                    document.getElementById(tab.dataset.tab).classList.add('active');
                });
            });

            // File input preview for message form
            const fileInput = document.getElementById('attachment');
            const filePreview = document.getElementById('file-preview');
            fileInput.addEventListener('change', () => {
                if (fileInput.files.length > 0) {
                    filePreview.textContent = `Selected: ${fileInput.files[0].name}`;
                } else {
                    filePreview.textContent = '';
                }
            });

            // File input preview for edit modals
            document.querySelectorAll('input[type="file"][id^="edit-attachment-"]').forEach(input => {
                const messageId = input.id.replace('edit-attachment-', '');
                const editFilePreview = document.getElementById(`edit-file-preview-${messageId}`);
                input.addEventListener('change', () => {
                    if (input.files.length > 0) {
                        editFilePreview.textContent = `Selected: ${input.files[0].name}`;
                    } else {
                        editFilePreview.textContent = '';
                    }
                });
            });
        });
    </script>
</body>
</html>
<?php
$dbConnection = null;
?>
