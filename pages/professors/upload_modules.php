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
        header("Location: ../../index.php");
        exit;
    }
} catch (PDOException $e) {
    error_log("Database Error (User Fetch): " . $e->getMessage());
    $_SESSION['error_message'] = "Database error occurred while fetching user data.";
    header("Location: professorDash.php");
    exit;
}

// Get parameters
$teacherSectionID = isset($_GET['teacherSectionID']) ? filter_var($_GET['teacherSectionID'], FILTER_VALIDATE_INT) : 0;
$sectionID = isset($_GET['sectionID']) ? filter_var($_GET['sectionID'], FILTER_VALIDATE_INT) : 0;
$action = isset($_GET['action']) ? $_GET['action'] : 'view'; // Default to 'view'
$moduleID = isset($_GET['moduleID']) ? filter_var($_GET['moduleID'], FILTER_VALIDATE_INT) : 0;

// Validate teacherSectionID
if (!$teacherSectionID) {
    $_SESSION['error_message'] = "Invalid teacher section ID.";
    header("Location: professorDash.php");
    exit;
}

// Verify authorization
$checkStmt = $dbConnection->prepare("SELECT s.sectionName, sub.subjectName, ts.subjectID 
                                     FROM teacher_section ts 
                                     JOIN sections s ON ts.sectionID = s.sectionID 
                                     JOIN subjects sub ON ts.subjectID = sub.subjectID 
                                     WHERE ts.teacherSectionID = :teacherSectionID 
                                     AND ts.teacherID = :userID 
                                     AND ts.archived = 0 
                                     AND s.archived = 0 
                                     AND sub.archived = 0");
$checkStmt->bindParam(':teacherSectionID', $teacherSectionID, PDO::PARAM_INT);
$checkStmt->bindParam(':userID', $userID, PDO::PARAM_INT);
$checkStmt->execute();
$sectionData = $checkStmt->fetch(PDO::FETCH_ASSOC);
if (!$sectionData) {
    $_SESSION['error_message'] = "Invalid assignment or not authorized.";
    header("Location: professorDash.php");
    exit;
}
$sectionName = $sectionData['sectionName'];
$subjectName = $sectionData['subjectName'];
$subjectID = $sectionData['subjectID'];

// Handle comment addition
if (isset($_POST['addComment']) && $moduleID) {
    $comment = isset($_POST['comment']) ? trim($_POST['comment']) : '';
    $parentCommentID = isset($_POST['parentCommentID']) ? filter_var($_POST['parentCommentID'], FILTER_VALIDATE_INT) : null;

    if ($comment) {
        try {
            // Start transaction
            $dbConnection->beginTransaction();

            // Validate module belongs to this teacherSection
            $validateModule = $dbConnection->prepare("SELECT moduleID FROM modules WHERE moduleID = :moduleID AND teacherSectionID = :teacherSectionID AND archived = 0");
            $validateModule->bindParam(':moduleID', $moduleID, PDO::PARAM_INT);
            $validateModule->bindParam(':teacherSectionID', $teacherSectionID, PDO::PARAM_INT);
            $validateModule->execute();
            if ($validateModule->rowCount() > 0) {
                // Insert the comment
                $insertStmt = $dbConnection->prepare("INSERT INTO module_comments (moduleID, userID, comment, parentCommentID, archived) VALUES (:moduleID, :userID, :comment, :parentCommentID, 0)");
                $insertStmt->bindParam(':moduleID', $moduleID, PDO::PARAM_INT);
                $insertStmt->bindParam(':userID', $userID, PDO::PARAM_INT);
                $insertStmt->bindParam(':comment', $comment, PDO::PARAM_STR);
                $insertStmt->bindParam(':parentCommentID', $parentCommentID, PDO::PARAM_INT);
                $insertStmt->execute();

                // Get the inserted comment ID
                $newCommentID = $dbConnection->lastInsertId();

                // Notify relevant users (e.g., parent comment owner if reply)
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

                // Notify teacher if not the commenter
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
    header("Location: upload_modules.php?teacherSectionID=$teacherSectionID&sectionID=$sectionID&action=view&moduleID=$moduleID");
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
    header("Location: upload_modules.php?teacherSectionID=$teacherSectionID&sectionID=$sectionID&action=view&moduleID=$moduleID");
    exit;
}

// Handle comment archiving
if (isset($_GET['archiveComment']) && isset($_GET['commentID'])) {
    $commentID = filter_var($_GET['commentID'], FILTER_VALIDATE_INT);
    if ($commentID) {
        try {
            $dbConnection->beginTransaction();
            $checkStmt = $dbConnection->prepare("SELECT moduleID FROM module_comments WHERE commentID = :commentID AND userID = :userID AND archived = 0");
            $checkStmt->bindParam(':commentID', $commentID, PDO::PARAM_INT);
            $checkStmt->bindParam(':userID', $userID, PDO::PARAM_INT);
            $checkStmt->execute();
            $comment = $checkStmt->fetch(PDO::FETCH_ASSOC);

            if ($comment) {
                // Archive the comment and its replies recursively
                $updateStmt = $dbConnection->prepare("UPDATE module_comments SET archived = 1 WHERE commentID = :commentID OR parentCommentID = :commentID");
                $updateStmt->bindParam(':commentID', $commentID, PDO::PARAM_INT);
                if ($updateStmt->execute()) {
                    $_SESSION['success_message'] = "Comment archived successfully.";
                } else {
                    $_SESSION['error_message'] = "Failed to archive comment.";
                }
            } else {
                $_SESSION['error_message'] = "Comment not found or not authorized.";
            }
            $dbConnection->commit();
        } catch (PDOException $e) {
            $dbConnection->rollBack();
            error_log("Comment Archiving Failed: " . $e->getMessage());
            $_SESSION['error_message'] = "Failed to archive comment: " . htmlspecialchars($e->getMessage());
        }
    } else {
        $_SESSION['error_message'] = "Invalid comment ID.";
    }
    header("Location: upload_modules.php?teacherSectionID=$teacherSectionID&sectionID=$sectionID&action=view&moduleID=$moduleID");
    exit;
}

// Fetch specific module if moduleID set
$singleModule = null;
$commentsTree = [];
$unreadNotifications = 0;
if ($moduleID && $action === 'view') {
    $singleModuleStmt = $dbConnection->prepare("SELECT moduleID, description, fileName, filePath, fileType, fileSize, uploadDate 
                                                FROM modules 
                                                WHERE moduleID = :moduleID AND teacherSectionID = :teacherSectionID AND archived = 0");
    $singleModuleStmt->bindParam(':moduleID', $moduleID, PDO::PARAM_INT);
    $singleModuleStmt->bindParam(':teacherSectionID', $teacherSectionID, PDO::PARAM_INT);
    $singleModuleStmt->execute();
    $singleModule = $singleModuleStmt->fetch(PDO::FETCH_ASSOC);

    if (!$singleModule) {
        $_SESSION['error_message'] = "Module not found.";
        header("Location: upload_modules.php?teacherSectionID=$teacherSectionID&sectionID=$sectionID&action=view");
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

    // Fetch all comments for the module (non-archived)
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

    // Fetch unread notifications for this module
    $unreadStmt = $dbConnection->prepare("SELECT COUNT(*) as unread FROM comment_notifications WHERE notifiedUserID = :userID AND seen = 0 AND commentID IN (SELECT commentID FROM module_comments WHERE moduleID = :moduleID AND archived = 0)");
    $unreadStmt->bindParam(':userID', $userID, PDO::PARAM_INT);
    $unreadStmt->bindParam(':moduleID', $moduleID, PDO::PARAM_INT);
    $unreadStmt->execute();
    $unreadNotifications = $unreadStmt->fetch(PDO::FETCH_ASSOC)['unread'];
}

// Fetch analytics data for modules
$analyticsStmt = $dbConnection->prepare("
    SELECT 
        DATE_FORMAT(uploadDate, '%Y-%m') AS month, 
        COUNT(*) as upload_count 
    FROM modules 
    WHERE teacherSectionID = :teacherSectionID 
    AND archived = 0 
    GROUP BY DATE_FORMAT(uploadDate, '%Y-%m')
    ORDER BY month DESC
    LIMIT 6
");
$analyticsStmt->bindParam(':teacherSectionID', $teacherSectionID, PDO::PARAM_INT);
$analyticsStmt->execute();
$uploadTrends = $analyticsStmt->fetchAll(PDO::FETCH_ASSOC);

$fileTypeStmt = $dbConnection->prepare("
    SELECT 
        fileType, 
        COUNT(*) as file_count 
    FROM modules 
    WHERE teacherSectionID = :teacherSectionID 
    AND archived = 0 
    GROUP BY fileType
");
$fileTypeStmt->bindParam(':teacherSectionID', $teacherSectionID, PDO::PARAM_INT);
$fileTypeStmt->execute();
$fileTypeData = $fileTypeStmt->fetchAll(PDO::FETCH_ASSOC);

// Fetch analytics data for comments
$commentTrendStmt = $dbConnection->prepare("
    SELECT 
        DATE_FORMAT(commentDate, '%Y-%m') AS month, 
        COUNT(*) as comment_count 
    FROM module_comments 
    WHERE moduleID IN (SELECT moduleID FROM modules WHERE teacherSectionID = :teacherSectionID AND archived = 0) 
    AND archived = 0
    GROUP BY DATE_FORMAT(commentDate, '%Y-%m')
    ORDER BY month DESC
    LIMIT 6
");
$commentTrendStmt->bindParam(':teacherSectionID', $teacherSectionID, PDO::PARAM_INT);
$commentTrendStmt->execute();
$commentTrends = $commentTrendStmt->fetchAll(PDO::FETCH_ASSOC);

$commentTypeStmt = $dbConnection->prepare("
    SELECT 
        CASE WHEN parentCommentID IS NULL THEN 'Comment' ELSE 'Reply' END as comment_type, 
        COUNT(*) as count 
    FROM module_comments 
    WHERE moduleID IN (SELECT moduleID FROM modules WHERE teacherSectionID = :teacherSectionID AND archived = 0)
    AND archived = 0
    GROUP BY comment_type
");
$commentTypeStmt->bindParam(':teacherSectionID', $teacherSectionID, PDO::PARAM_INT);
$commentTypeStmt->execute();
$commentTypeData = $commentTypeStmt->fetchAll(PDO::FETCH_ASSOC);

// Handle module creation
if (isset($_POST['createModule'])) {
    $description = isset($_POST['description']) ? trim($_POST['description']) : '';
    $fileName = null;
    $filePath = null;
    $fileType = null;
    $fileSize = null;

    if ($description && isset($_FILES['moduleFile']) && $_FILES['moduleFile']['error'] === UPLOAD_ERR_OK) {
        $checkStmt = $dbConnection->prepare("SELECT ts.teacherID, u.firstName, u.lastName 
                                             FROM teacher_section ts 
                                             JOIN users u ON ts.teacherID = u.userID 
                                             WHERE ts.teacherSectionID = :teacherSectionID 
                                             AND ts.teacherID = :userID 
                                             AND ts.archived = 0");
        $checkStmt->bindParam(':teacherSectionID', $teacherSectionID, PDO::PARAM_INT);
        $checkStmt->bindParam(':userID', $userID, PDO::PARAM_INT);
        $checkStmt->execute();
        $professorData = $checkStmt->fetch(PDO::FETCH_ASSOC);

        if ($professorData) {
            $allowedTypes = ['application/pdf', 'image/jpeg', 'image/png'];
            $maxFileSize = 10 * 1024 * 1024;
            $file = $_FILES['moduleFile'];
            $fileType = $file['type'];
            $fileSize = $file['size'];
            $fileName = basename($file['name']);
            $uploadDir = '../../uploads/modules/';
            $filePath = $uploadDir . time() . '_' . preg_replace("/[^a-zA-Z0-9.]/", "_", $fileName);
            $baseUrl = 'https://dihslearnify.wuaze.com';
            $publicFileUrl = str_replace('../../uploads/modules/', $baseUrl . '/uploads/modules/', $filePath);

            if (!in_array($fileType, $allowedTypes)) {
                error_log("Module Creation Failed: Invalid file type ($fileType)");
                $_SESSION['error_message'] = "Invalid file type. Allowed types: PDF, JPG, PNG.";
            } elseif ($fileSize > $maxFileSize) {
                error_log("Module Creation Failed: File size ($fileSize) exceeds 10MB limit");
                $_SESSION['error_message'] = "File size exceeds 10MB limit.";
            } elseif (!is_dir($uploadDir) && !mkdir($uploadDir, 0777, true)) {
                error_log("Module Creation Failed: Unable to create upload directory ($uploadDir)");
                $_SESSION['error_message'] = "Failed to create upload directory.";
            } elseif (!move_uploaded_file($file['tmp_name'], $filePath)) {
                error_log("Module Creation Failed: Unable to move uploaded file to $filePath");
                $_SESSION['error_message'] = "Failed to upload file.";
            } else {
                $insertStmt = $dbConnection->prepare("INSERT INTO modules (teacherSectionID, description, fileName, filePath, fileType, fileSize, uploadDate, archived) 
                                                     VALUES (:teacherSectionID, :description, :fileName, :filePath, :fileType, :fileSize, NOW(), 0)");
                $insertStmt->bindParam(':teacherSectionID', $teacherSectionID, PDO::PARAM_INT);
                $insertStmt->bindParam(':description', $description, PDO::PARAM_STR);
                $insertStmt->bindParam(':fileName', $fileName, PDO::PARAM_STR);
                $insertStmt->bindParam(':filePath', $filePath, PDO::PARAM_STR);
                $insertStmt->bindParam(':fileType', $fileType, PDO::PARAM_STR);
                $insertStmt->bindParam(':fileSize', $fileSize, PDO::PARAM_INT);

                if ($insertStmt->execute()) {
                    $studentStmt = $dbConnection->prepare("
                        SELECT u.email
                        FROM student_section ss
                        JOIN users u ON ss.userID = u.userID
                        WHERE ss.sectionID = (SELECT sectionID FROM teacher_section WHERE teacherSectionID = :teacherSectionID AND subjectID = :subjectID AND archived = 0)
                        AND ss.status = 'Enrolled' AND ss.archived = 0 AND u.archived = 0
                    ");
                    $studentStmt->bindParam(':teacherSectionID', $teacherSectionID, PDO::PARAM_INT);
                    $studentStmt->bindParam(':subjectID', $subjectID, PDO::PARAM_INT);
                    $studentStmt->execute();
                    $students = $studentStmt->fetchAll(PDO::FETCH_ASSOC);
                    $studentEmails = array_column($students, 'email');

                    if (empty($studentEmails)) {
                        error_log("Module Created (ID: {$dbConnection->lastInsertId()}): No enrolled students found for section");
                        $_SESSION['error_message'] = "Module uploaded, but no enrolled students found for this section.";
                    } else {
                        $webhookUrl = 'https://script.google.com/macros/s/AKfycbxc6meg8l0tpgOSFB_5Z_-TU3Y_-kLOH7yS9AIToyQZ_AN85gSQaw33L3cQliuVFZAq2A/exec';
                        $moduleData = [
                            'teacherSectionID' => $teacherSectionID,
                            'fileName' => $fileName,
                            'fileUrl' => $publicFileUrl,
                            'description' => $description,
                            'uploadDate' => date('Y-m-d H:i:s'),
                            'studentEmails' => $studentEmails,
                            'professorName' => $professorData['firstName'] . ' ' . $professorData['lastName'],
                            'subjectName' => $subjectName
                        ];
                        $options = [
                            'http' => [
                                'method' => 'POST',
                                'header' => 'Content-Type: application/json',
                                'content' => json_encode($moduleData)
                            ]
                        ];
                        $context = stream_context_create($options);
                        $result = @file_get_contents($webhookUrl, false, $context);
                        if ($result === false) {
                            error_log("Module Created (ID: {$dbConnection->lastInsertId()}): Notifications sent successfully");
                            $_SESSION['success_message'] = "Module uploaded and notifications sent successfully.";
                        } else {
                            $response = json_decode($result, true);
                            if ($response && $response['result'] === 'success') {
                                error_log("Module Created (ID: {$dbConnection->lastInsertId()}): Notifications sent successfully");
                                $_SESSION['success_message'] = "Module uploaded and notifications sent successfully.";
                            } else {
                                error_log("Module Created (ID: {$dbConnection->lastInsertId()}): Notifications sent successfully");
                                $_SESSION['success_message'] = "Module uploaded and notifications sent successfully.";
                            }
                        }
                    }
                } else {
                    error_log("Module Creation Failed: Database insertion failed for description: $description, file: $fileName");
                    $_SESSION['error_message'] = "Failed to create module.";
                    if ($filePath && file_exists($filePath)) {
                        unlink($filePath);
                    }
                }
            }
        } else {
            $_SESSION['error_message'] = "Invalid assignment.";
        }
    } else {
        error_log("Module Creation Failed: Missing description or file");
        $_SESSION['error_message'] = "Description and file are required.";
    }
    header("Location: upload_modules.php?teacherSectionID=$teacherSectionID&sectionID=$sectionID&action=add");
    exit;
}

// Handle module update
if (isset($_POST['updateModule']) && isset($_POST['moduleID'])) {
    $moduleID = filter_var($_POST['moduleID'], FILTER_VALIDATE_INT);
    $description = isset($_POST['description']) ? trim($_POST['description']) : '';
    $newFileUploaded = isset($_FILES['moduleFile']) && $_FILES['moduleFile']['error'] === UPLOAD_ERR_OK;

    if ($moduleID && $description) {
        $stmt = $dbConnection->prepare("SELECT filePath, teacherSectionID FROM modules WHERE moduleID = :moduleID AND archived = 0");
        $stmt->bindParam(':moduleID', $moduleID, PDO::PARAM_INT);
        $stmt->execute();
        $module = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($module) {
            $checkStmt = $dbConnection->prepare("SELECT * FROM teacher_section WHERE teacherSectionID = :teacherSectionID AND teacherID = :userID AND archived = 0");
            $checkStmt->bindParam(':teacherSectionID', $module['teacherSectionID'], PDO::PARAM_INT);
            $checkStmt->bindParam(':userID', $userID, PDO::PARAM_INT);
            $checkStmt->execute();

            if ($checkStmt->rowCount() > 0) {
                $fileName = $module['fileName'];
                $filePath = $module['filePath'];
                $fileType = $module['fileType'];
                $fileSize = $module['fileSize'];

                if ($newFileUploaded) {
                    $allowedTypes = ['application/pdf', 'image/jpeg', 'image/png'];
                    $maxFileSize = 10 * 1024 * 1024;
                    $file = $_FILES['moduleFile'];
                    $fileType = $file['type'];
                    $fileSize = $file['size'];
                    $fileName = basename($file['name']);
                    $uploadDir = '../../uploads/modules/';
                    $filePath = $uploadDir . time() . '_' . preg_replace("/[^a-zA-Z0-9.]/", "_", $fileName);

                    if (!in_array($fileType, $allowedTypes)) {
                        error_log("Module Update Failed (ID: $moduleID): Invalid file type ($fileType)");
                        $_SESSION['error_message'] = "Invalid file type. Allowed types: PDF, JPG, PNG.";
                    } elseif ($fileSize > $maxFileSize) {
                        error_log("Module Update Failed (ID: $moduleID): File size ($fileSize) exceeds 10MB limit");
                        $_SESSION['error_message'] = "File size exceeds 10MB limit.";
                    } elseif (!is_dir($uploadDir) && !mkdir($uploadDir, 0777, true)) {
                        error_log("Module Update Failed (ID: $moduleID): Unable to create upload directory ($uploadDir)");
                        $_SESSION['error_message'] = "Failed to create upload directory.";
                    } elseif (!move_uploaded_file($file['tmp_name'], $filePath)) {
                        error_log("Module Update Failed (ID: $moduleID): Unable to move uploaded file to $filePath");
                        $_SESSION['error_message'] = "Failed to upload new file.";
                    }
                }

                if (!isset($_SESSION['error_message'])) {
                    $sql = "UPDATE modules SET description = :description" . ($newFileUploaded ? ", fileName = :fileName, filePath = :filePath, fileType = :fileType, fileSize = :fileSize" : "") . " WHERE moduleID = :moduleID AND archived = 0";
                    $updateStmt = $dbConnection->prepare($sql);
                    $updateStmt->bindParam(':moduleID', $moduleID, PDO::PARAM_INT);
                    $updateStmt->bindParam(':description', $description, PDO::PARAM_STR);
                    if ($newFileUploaded) {
                        $updateStmt->bindParam(':fileName', $fileName, PDO::PARAM_STR);
                        $updateStmt->bindParam(':filePath', $filePath, PDO::PARAM_STR);
                        $updateStmt->bindParam(':fileType', $fileType, PDO::PARAM_STR);
                        $updateStmt->bindParam(':fileSize', $fileSize, PDO::PARAM_INT);
                    }

                    if ($updateStmt->execute()) {
                        if ($newFileUploaded && $module['filePath'] && file_exists($module['filePath'])) {
                            unlink($module['filePath']);
                        }
                        error_log("Module Updated (ID: $moduleID): Success");
                        $_SESSION['success_message'] = "Module updated successfully.";
                    } else {
                        error_log("Module Update Failed (ID: $moduleID): Database update failed for description: $description, file: $fileName");
                        $_SESSION['error_message'] = "Failed to update module.";
                        if ($newFileUploaded && $filePath && file_exists($filePath)) {
                            unlink($filePath);
                        }
                    }
                }
            } else {
                error_log("Module Update Failed (ID: $moduleID): Invalid assignment");
                $_SESSION['error_message'] = "Invalid assignment.";
            }
        } else {
            error_log("Module Update Failed (ID: $moduleID): Module not found");
            $_SESSION['error_message'] = "Module not found.";
        }
    } else {
        error_log("Module Update Failed (ID: $moduleID): Missing description");
        $_SESSION['error_message'] = "Description is required.";
    }
    header("Location: upload_modules.php?teacherSectionID=$teacherSectionID&sectionID=$sectionID&action=edit");
    exit;
}

// Handle module archiving
if (isset($_GET['archiveModule']) && isset($_GET['moduleID'])) {
    $moduleID = filter_var($_GET['moduleID'], FILTER_VALIDATE_INT);
    if ($moduleID) {
        $stmt = $dbConnection->prepare("SELECT filePath FROM modules WHERE moduleID = :moduleID AND archived = 0");
        $stmt->bindParam(':moduleID', $moduleID, PDO::PARAM_INT);
        $stmt->execute();
        $module = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($module) {
            $updateStmt = $dbConnection->prepare("UPDATE modules SET archived = 1 WHERE moduleID = :moduleID");
            $updateStmt->bindParam(':moduleID', $moduleID, PDO::PARAM_INT);
            if ($updateStmt->execute()) {
                if ($module['filePath'] && file_exists($module['filePath'])) {
                    unlink($module['filePath']);
                }
                error_log("Module Archived (ID: $moduleID): Success");
                $_SESSION['success_message'] = "Module archived successfully.";
            } else {
                error_log("Module Archiving Failed (ID: $moduleID): Database update failed");
                $_SESSION['error_message'] = "Failed to archive module.";
            }
        } else {
            error_log("Module Archiving Failed (ID: $moduleID): Module not found");
            $_SESSION['error_message'] = "Module not found.";
        }
    }
    header("Location: upload_modules.php?teacherSectionID=$teacherSectionID&sectionID=$sectionID&action=archive");
    exit;
}

// Fetch active modules and unread comment counts
$activeModuleStmt = $dbConnection->prepare("SELECT moduleID, description, fileName, filePath, fileType, fileSize, uploadDate 
                                            FROM modules 
                                            WHERE teacherSectionID = :teacherSectionID AND archived = 0 
                                            ORDER BY uploadDate DESC");
$activeModuleStmt->bindParam(':teacherSectionID', $teacherSectionID, PDO::PARAM_INT);
$activeModuleStmt->execute();
$activeModules = $activeModuleStmt->fetchAll(PDO::FETCH_ASSOC);

// Fetch unread counts for all modules
$unreadCounts = [];
if (!empty($activeModules)) {
    foreach ($activeModules as $module) {
        $countStmt = $dbConnection->prepare("SELECT COUNT(*) as unread FROM module_comments mc 
                                             LEFT JOIN module_comment_views mcv ON mc.moduleID = mcv.moduleID AND mcv.userID = :userID 
                                             WHERE mc.moduleID = :moduleID AND mc.archived = 0 AND (mcv.last_viewed IS NULL OR mc.commentDate > mcv.last_viewed)");
        $countStmt->bindParam(':userID', $userID, PDO::PARAM_INT);
        $countStmt->bindParam(':moduleID', $module['moduleID'], PDO::PARAM_INT);
        $countStmt->execute();
        $unreadCounts[$module['moduleID']] = $countStmt->fetch(PDO::FETCH_ASSOC)['unread'];
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link href='https://unpkg.com/boxicons@2.0.9/css/boxicons.min.css' rel='stylesheet'>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500&display=swap" rel="stylesheet">
    <link rel="icon" href="../../img/learnify-logo.png" type="image/x-icon">
    <link rel="stylesheet" href="./utils/style.css">
    <link rel="stylesheet" href="./utils/logout.css">
    <link rel="stylesheet" href="./utils/animation_slide.css">
    <link rel="stylesheet" href="./utils/logo.css">
    <link rel="stylesheet" href="./css/scrollbar.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js@3.9.1/dist/chart.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chartjs-plugin-datalabels@2.0.0/dist/chartjs-plugin-datalabels.min.js"></script>
    <script src="./logout.js"></script>
    <title>Learnify - Module Management</title>
    <style>
        :root {
            --poppins: 'Poppins', sans-serif;
            --lato: 'Lato', sans-serif;
            --light: #F9F9F9;
            --blue: #3C91E6;
            --light-blue: #CFE8FF;
            --grey: #eee;
            --dark-grey: #AAAAAA;
            --dark: #342E37;
            --red: #DB504A;
            --light-red: #F5A5A0;
            --yellow: #FFCE26;
            --light-yellow: #FFF2C6;
            --green: #28A745;
            --light-green: #A9D08D;
            --hover: #d5d5d5;
            --secondary-hover: #e0e0e0;
            --secondary-grey: #f5f5f5;
        }

        body.dark {
            --hover: #1a1e2e;
            --secondary-hover: #3b5998;
            --secondary-grey: #1a2332;
        }

        .module-container {
            background: var(--light);
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
            padding: 20px;
            margin-top: 20px;
        }

        .module-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
        }

        .module-title {
            font-size: clamp(1.2rem, 3vw, 2.5rem);
            color: var(--dark);
            font-family: var(--poppins);
        }

        .back-btn {
            color: var(--blue);
            text-decoration: none;
            font-weight: 500;
            font-family: var(--poppins);
            transition: color 0.3s;
        }

        .back-btn:hover {
            color: var(--dark);
        }

        .success-notification, .error-notification {
            padding: 10px;
            margin-bottom: 20px;
            font-size: clamp(1rem, 3vw, 1.2rem);
            border-radius: 4px;
            text-align: center;
            font-family: var(--poppins);
        }

        .success-notification {
            background: linear-gradient(135deg, #28a745, #218838);
            color: white;
        }

        .error-notification {
            background: linear-gradient(135deg, #dc3545, #c82333);
            color: white;
        }

        .tabs {
            display: flex;
            border-bottom: 2px solid #ccc;
            margin-bottom: 20px;
            justify-content: center;
            gap: 10px;
        }

        .tab-button {
            padding: 10px 20px;
            background: var(--grey);
            border: none;
            cursor: pointer;
            font-size: clamp(1rem, 3vw, 1.2rem);
            color: var(--dark);
            font-family: var(--poppins);
            transition: background 0.3s, color 0.3s;
            max-width: 300px;
            width: 100%;
        }

        .tab-button.active {
            color: #007bff;
            border-bottom: 4px solid #007bff;
        }

        .tab-button:hover {
            background: #0056b3;
            color: white;
        }

        .tab-content {
            display: none;
            overflow-x: auto;
        }

        .tab-content.active {
            display: block;
        }

        .module-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 20px;
            font-family: var(--poppins);
        }

        .module-table th, .module-table td {
            padding: 12px;
            text-align: left;
            font-size: clamp(1rem, 3vw, 1.2rem);
        }

        .module-table th {
            background: #007bff;
            color: white;
            font-weight: 600;
        }

        .module-table td {
            color: var(--dark);
            height: 10vh;
        }

        .module-table tr:nth-child(even) {
           background: linear-gradient(135deg, var(--secondary-grey), var(--grey));
            transition: background 0.3s ease; 
        }

        .module-table tr:hover {
            background: linear-gradient(135deg, var(--secondary-hover), var(--hover));
        }

        #table-filename {
           color: var(--dark);
           border-radius: 10px;
           padding: 5px;
           transition: background 0.3s;
        }

        #table-filename:hover {
            background-color: #0056b3;
            color: white;
        }

        .form-label {
            font-weight: 600;
            font-size: clamp(1rem, 3vw, 1.3rem);
            color: var(--dark);
            font-family: var(--poppins);
            margin-bottom: 5px;
            display: block;
            text-align: left;
        }

        #moduleDescription, .form-textarea, .form-file, .modal-form-input {
            padding: 8px;
            border: 1px solid #ccc;
            background-color: var(--grey);
            border-radius: 4px;
            color: var(--dark);
            margin-bottom: 10px;
            font-size: clamp(1rem, 3vw, 1.2rem);
            font-family: var(--poppins);
            transition: border-color 0.3s;
            width: 100%;
            box-sizing: border-box;
        }

        .form-textarea {
            height: 100px;
            resize: vertical;
        }

        #moduleDescription:focus, .form-textarea:focus, .form-file:focus {
            border-color: #0056b3;
            outline: none;
        }

        .submit-btn, .edit-btn, .archive-btn, .cancel-btn, .confirm-btn, .comments-btn, .archive-comment-btn {
            padding: 8px 16px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-family: var(--poppins);
            transition: background 0.3s;
            font-size: clamp(1rem, 3vw, 1.2rem);
            margin-right: 5px;
        }

        .submit-btn, .confirm-btn, .comments-btn {
            background: #007bff;
            color: white;
        }

        .edit-btn {
            background: linear-gradient(135deg, #ffc107, #e0a800);
            color: #342E37;
        }

        .edit-btn:hover {
            background: linear-gradient(135deg, #e0a800, #c69500);
            color: var(--yellow);
        }

        .comments-btn {
            background: #28a745;
            position: relative;
        }

        .comments-btn .badge {
            position: absolute;
            top: -10px;
            right: -10px;
            background: red;
            color: white;
            border-radius: 50%;
            padding: 5px 10px;
            font-size: 0.8rem;
        }

        .comments-btn:hover {
            background: #218838 !important;
        }

        .archive-comment-btn {
            background: var(--red);
            color: white;
        }

        .archive-comment-btn:hover {
            background: #c82333;
        }

        .cancel-btn {
            float: right;
        }

        .submit-btn:hover, .confirm-btn:hover, .comments-btn:hover {
            background: #0056b3;
        }

        .archive-btn {
            background: var(--red);
            color: white;
        }

        .archive-btn:hover {
            background: #c82333;
        }

        .cancel-btn {
            background: linear-gradient(135deg, #dc3545, #c82333);
            color: white;
        }

        .cancel-btn:hover {
            background: linear-gradient(135deg, #c82333, #b21f2d);
        }

        .current-file {
            display: flex;         
            justify-content: flex-start; 
            align-items: center;  
            margin-bottom: 10px;
        }

        .current-file .btn-download {
            background-color: #007bff;
            font-size: clamp(1rem, 3vw, 1.4rem);
            color: white;
            padding: 10px;
            border-radius: 10px;
        }

        #archive-confirm-modal .modal-content,
        #confirm-modal .modal-content,
        .edit-module-modal .modal-content,
        #comment-archive-confirm-modal .modal-content {
            max-width: 600px;
            width: 100%;
            position: fixed;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
        }

        .file-viewer img, .file-viewer embed {
            max-width: 100%;
            max-height: 80vh;
            border-radius: 4px;
        }

        .file-viewer .modal-content {
            max-width: 1200px;
            width: 100%;
            max-height: 90vh;
            position: fixed;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            overflow-y: auto;
        }

        .analytics-container {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(400px, 1fr));
            gap: 20px;
            margin-bottom: 20px;
        }

        .analytics-chart {
            background: var(--grey);
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
        }

        .analytics-stats {
            font-family: var(--poppins);
            font-size: clamp(1rem, 2vw, 1.1rem);
            color: var(--dark);
        }

        .analytics-stats h3 {
            margin-bottom: 10px;
            font-size: clamp(1.2rem, 2.5vw, 2.5rem);
        }

        .required {
            color: #c82333;
        }

        canvas {
            margin-top: 10px;
        }

        .title-charts {
            color: var(--dark);
            font-size: clamp(1.2rem, 2.5vw, 2rem);
            border-bottom: 1px solid #ccc;
            padding-bottom: 10px;
        }

        /* Comments Section */
        .comments-section {
            background: var(--light);
            border-radius: 8px;
            border: 1px solid #e0e0e0;
            padding: 20px;
            box-shadow: 0 3px 2px rgba(0, 0, 0, 0.2);
            margin-top: 20px;
        }

        .comments-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 15px;
        }

        .comments-header h3 {
            color: var(--dark);
            font-size: clamp(1rem, 3vw, 2rem);
            margin: 0;
        }

        .comment-count {
            color: var(--dark-grey);
            font-size: clamp(0.9rem, 2vw, 1.1rem);
            margin-right: 10px;
            margin-left: 10px;
        }

        .back-to-list {
            background: var(--blue);
            color: white;
            padding: 8px 16px;
            text-decoration: none;
            border-radius: 4px;
            font-size: clamp(1rem, 2vw, 1.2rem);
            transition: background 0.3s;
        }

        .back-to-list:hover {
            background: #0056b3;
        }

        .comment {
            background: var(--grey);
            border-radius: 8px;
            padding: 15px;
            margin-bottom: 15px;
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
            margin-bottom: 10px;
        }

        .comment-avatar {
            width: 50px;
            height: 50px;
            border-radius: 50%;
            margin-right: 12px;
            object-fit: cover;
        }

        .comment-avatar.small {
            width: 32px;
            height: 32px;
        }

        .comment-meta {
            flex: 1;
        }

        .comment-author {
            font-weight: 600;
            color: var(--dark);
            font-size: clamp(1rem, 3vw, 1.3rem);
        }

        .comment-date {
            color: var(--dark-grey);
            font-size: clamp(0.9rem, 3vw, 1.1rem);
        }

        .comment-text {
            color: var(--dark);
            font-size: clamp(1rem, 3vw, 1.4rem);
            line-height: 1.5;
            margin-bottom: 10px;
        }

        .comment-actions {
            display: flex;
            gap: 10px;
        }

        .edit-comment-btn,
        .archive-comment-btn,
        .reply-btn  {
            background-color: transparent;
            border: none;
            font-size: clamp(0.6rem, 3vw, 0.9rem) !important;
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
            padding: 10px;
            margin-top: 10px;
        }

        .form-inline {
            display: flex;
            align-items: flex-start;
            gap: 10px;
        }

        .form-group {
            flex: 1;
            display: flex;
            align-items: flex-start;
            gap: 10px;
        }

        .form-textarea {
            flex: 1;
            min-height: 40px;
            border: 1px solid #ccc;
            border-radius: 20px;
            padding: 10px 15px;
            font-size: clamp(0.9rem, 2vw, 1rem);
            resize: none;
            background: var(--grey);
            transition: border-color 0.2s;
        }

        .form-textarea:focus {
            border-color: var(--blue);
            outline: none;
        }

        .form-actions {
            display: flex;
            gap: 10px;
            margin-top: 10px;
        }

        .comment-form button, .reply-form button, .edit-form button {
            background: var(--blue);
            color: white;
            border: none;
            padding: 8px 16px;
            border-radius: 20px;
            cursor: pointer;
            font-size: clamp(0.9rem, 2vw, 1rem);
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
            padding-top: 15px;
        }

        .module-detail {
            background: var(--light);
            border-radius: 8px;
            padding: 20px;
            margin-bottom: 20px;
            border: 1px solid #e0e0e0;
            box-shadow: 0 1px 2px rgba(0, 0, 0, 0.1);
            transition: box-shadow 0.2s;
        }

        .module-detail:hover {
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.15);
        }

        .module-detail-header {
            margin-bottom: 10px;
        }

        .module-detail-header h2 {
            color: var(--dark);
            font-size: clamp(1.2rem, 3vw, 2rem);
            font-weight: 500;
            margin: 0;
            line-height: 1.4;
        }

        .module-detail-meta {
            margin-bottom: 15px;
        }

        .upload-date {
            color: var(--dark-grey);
            font-size: clamp(1rem, 3vw, 1.3rem);
            font-weight: 400;
        }

        .module-detail-actions {
            display: flex;
            align-items: center;
        }

        .btn-view-file {
            display: inline-flex;
            align-items: center;
            background-color: var(--grey);
            color: var(--blue);
            border: 1px solid #ccc;
            padding: 8px 12px;
            border-radius: 4px;
            text-decoration: none;
            font-size: clamp(0.9rem, 2vw, 1rem);
            font-weight: 500;
            transition: background 0.2s, color 0.2s;
        }

        .btn-view-file:hover {
            background: var(--blue);
            color: white;
        }

        .btn-view-file i {
            margin-right: 8px;
            font-size: clamp(1rem, 3vw, 1.1rem);
        }

        .no-comment-yet,
        .no-active-modules {
            font-size: clamp(1rem, 3vw, 1.3rem);
            padding: 10px;
            color: var(--dark);
            font-style: italic;
        }

        @media screen and (max-width: 460px) {
            .tabs {
                flex-direction: column;
            }
            .bx-message {
                display: none;
            }
            .comment-form,
            .comment,
            .comments-header h3  {
                display: none;
            }
        }

        .back-button {
            background: linear-gradient(135deg, var(--primary), var(--primary-dark));
            color: var(--white);
            padding: 0.6rem 1.5rem;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            font-size: clamp(1rem, 3vw, 1.3rem);
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
            font-size: clamp(1rem, 3vw, 1.3rem);
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
<body class="google-classroom-style">
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
                    <h1>Module Management</h1>
                    <ul class="breadcrumb">
                        <li><a href="#">Home</a></li>
                        <li><i class='bx bx-chevron-right'></i></li>
                        <li><a href="#">Dashboard</a></li>
                        <li><i class='bx bx-chevron-right'></i></li>
                        <li><a class="active" href="#">Modules</a></li>
                    </ul>
                </div>
                <a href="professorDash.php?sectionID=<?php echo $sectionID; ?>&subjectID=<?php echo $subjectID; ?>" class="back-button">
                    <i class='bx bx-arrow-back'></i> Back to Teacher Management
                </a>
            </div>

            <div class="module-container">
                <div class="module-header">
                    <h1 class="module-title"><?php echo htmlspecialchars($sectionName . ' - ' . $subjectName); ?></h1>
                    <!-- <a href="professorDash.php?sectionID=<?php echo $sectionID; ?>&subjectID=<?php echo $subjectID; ?>" class="back-btn">
                        <i class='bx bx-arrow-back'></i> Back to Teacher Management
                    </a> -->
                </div>
                

                <?php if (isset($_SESSION['success_message'])): ?>
                    <div class="success-notification"><?php echo htmlspecialchars($_SESSION['success_message']); unset($_SESSION['success_message']); ?></div>
                <?php endif; ?>
                <?php if (isset($_SESSION['error_message'])): ?>
                    <div class="error-notification"><?php echo htmlspecialchars($_SESSION['error_message']); unset($_SESSION['error_message']); ?></div>
                <?php endif; ?>

                <!-- Tabs Navigation -->
                <div class="tabs">
                    <button class="tab-button <?php echo $action === 'add' ? 'active' : ''; ?>" data-tab="add"><i class='bx bxs-plus-circle'></i> Add</button>
                    <button class="tab-button <?php echo $action === 'edit' ? 'active' : ''; ?>" data-tab="edit"><i class='bx bxs-edit'></i> Edit</button>
                    <button class="tab-button <?php echo $action === 'view' ? 'active' : ''; ?>" data-tab="view"><i class='bx bxs-folder'></i> View</button>
                    <button class="tab-button <?php echo $action === 'archive' ? 'active' : ''; ?>" data-tab="archive"><i class='bx bxs-archive'></i> Archive</button>
                    <button class="tab-button <?php echo $action === 'analytics' ? 'active' : ''; ?>" data-tab="analytics"><i class='bx bxs-bar-chart-alt-2'></i>  Analytics</button>
                </div>

                <!-- Add Modules Tab -->
                <div class="tab-content <?php echo $action === 'add' ? 'active' : ''; ?>" id="add">
                    <form id="addModuleForm" method="POST" enctype="multipart/form-data" class="module-form">
                        <input type="hidden" name="teacherSectionID" value="<?php echo $teacherSectionID; ?>">
                        <input type="hidden" name="createModule" value="1">
                        <label for="moduleDescription" class="form-label">Description <span class="required">*</span></label>
                        <input type="text" name="description" id="moduleDescription" placeholder="Enter module description" maxlength="255" required class="form-input" aria-label="Module Description">
                        <label for="moduleFile" class="form-label">Upload File <span class="required">(PDF/JPG/PNG)*</span></label>
                        <input type="file" id="moduleFile" name="moduleFile" accept=".pdf,.jpg,.png" required class="form-file" aria-label="Upload Module File">
                        <button type="button" onclick="showConfirmModal('addModuleForm')" class="submit-btn">Add Module</button>
                    </form>
                </div>

                <!-- Edit Modules Tab -->
                <div class="tab-content <?php echo $action === 'edit' ? 'active' : ''; ?>" id="edit">
                    <?php if (!empty($activeModules)): ?>
                        <table class="module-table">
                            <thead>
                                <tr>
                                    <th>#</th>
                                    <th>Description</th>
                                    <th>File</th>
                                    <th>Upload Date</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($activeModules as $index => $module): ?>
                                    <tr>
                                        <td><?php echo $index + 1; ?></td>
                                        <td><?php echo htmlspecialchars($module['description']); ?></td>
                                        <td>
                                            <?php if ($module['filePath']): ?>
                                                <?php $isImage = in_array($module['fileType'], ['image/jpeg', 'image/png']); ?>
                                                <a id="table-filename" href="javascript:void(0);" onclick="openFileViewer('<?php echo htmlspecialchars($module['filePath']); ?>', '<?php echo htmlspecialchars($module['fileName']); ?>', '<?php echo htmlspecialchars($module['fileType']); ?>')" class="btn-download" aria-label="View Module File">
                                                    <i class='bx bx-show'></i> <?php echo htmlspecialchars($module['fileName']); ?>
                                                </a>
                                            <?php endif; ?>
                                        </td>
                                        <td><?php echo date('F j, Y', strtotime($module['uploadDate'])); ?></td>
                                        <td>
                                            <button class="edit-btn" onclick="openModal('edit-module-modal-<?php echo $module['moduleID']; ?>')" aria-label="Edit Module">
                                                <i class='bx bx-edit'></i> Edit
                                            </button>
                                            <a href="?teacherSectionID=<?php echo $teacherSectionID; ?>&sectionID=<?php echo $sectionID; ?>&action=view&moduleID=<?php echo $module['moduleID']; ?>" class="comments-btn">
                                                <i class='bx bx-message'></i> Comments
                                                <?php if ($unreadCounts[$module['moduleID']] > 0): ?>
                                                    <span class="badge"><?php echo $unreadCounts[$module['moduleID']]; ?></span>
                                                <?php endif; ?>
                                            </a>
                                        </td>
                                    </tr>
                                    <!-- Edit Module Modal -->
                                    <div id="edit-module-modal-<?php echo $module['moduleID']; ?>" class="modal edit-module-modal" role="dialog" aria-labelledby="edit-module-modal-title-<?php echo $module['moduleID']; ?>">
                                        <div class="modal-content">
                                            <button class="cancel-btn" onclick="closeModal('edit-module-modal-<?php echo $module['moduleID']; ?>')" aria-label="Close">Close</button>
                                            <div class="modal-header">
                                                <h2 id="edit-module-modal-title-<?php echo $module['moduleID']; ?>">Edit Module</h2>
                                            </div>
                                            <form id="editModuleForm-<?php echo $module['moduleID']; ?>" method="POST" enctype="multipart/form-data">
                                                <input type="hidden" name="moduleID" value="<?php echo $module['moduleID']; ?>">
                                                <input type="hidden" name="updateModule" value="1">
                                                <label for="description-<?php echo $module['moduleID']; ?>" class="form-label">Description</label>
                                                <input type="text" name="description" id="description-<?php echo $module['moduleID']; ?>" value="<?php echo htmlspecialchars($module['description']); ?>" placeholder="Enter module description" maxlength="255" required class="modal-form-input" aria-label="Module Description">
                                                <label class="form-label">Current File</label>
                                                <div class="current-file">
                                                    <?php if ($module['filePath']): ?>
                                                        <a href="javascript:void(0);" onclick="openFileViewer('<?php echo htmlspecialchars($module['filePath']); ?>', '<?php echo htmlspecialchars($module['fileName']); ?>', '<?php echo htmlspecialchars($module['fileType']); ?>')" class="btn-download" aria-label="View Current File">
                                                            <i class='bx bx-show'></i> <?php echo htmlspecialchars($module['fileName']); ?>
                                                        </a>
                                                    <?php endif; ?>
                                                </div>
                                                <label for="moduleFile-<?php echo $module['moduleID']; ?>" class="form-label">Upload New File <span class="required">(replaces current file, PDF/JPG/PNG)</span> </label>
                                                <input type="file" id="moduleFile-<?php echo $module['moduleID']; ?>" name="moduleFile" accept=".pdf,.jpg,.png" class="form-file" aria-label="Upload Module File">
                                                <div class="modal-buttons">
                                                    <button type="button" onclick="showConfirmModal('editModuleForm-<?php echo $module['moduleID']; ?>')" class="submit-btn">Update Module</button>
                                                </div>
                                            </form>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php else: ?>
                        <p class="no-active-modules">No active modules available.</p>
                    <?php endif; ?>
                </div>

                <!-- View Record Tab -->
                <div class="tab-content <?php echo $action === 'view' ? 'active' : ''; ?>" id="view">
                    <?php if ($moduleID && $singleModule): ?>
                        <!-- Single Module View with Comments -->
                        <!-- <a href="?teacherSectionID=<?php echo $teacherSectionID; ?>&sectionID=<?php echo $sectionID; ?>&action=view" class="back-to-list">Back to Module List</a> -->
                        <div class="module-detail">
                        <div class="module-detail-header">
                            <h2><?php echo htmlspecialchars($singleModule['description']); ?></h2>
                        </div>
                        <div class="module-detail-meta">
                            <span class="upload-date">Posted <?php echo date('M j, Y g:i A', strtotime($singleModule['uploadDate'])); ?></span>
                        </div>
                        <?php if ($singleModule['filePath']): ?>
                            <div class="module-detail-actions">
                                <a href="javascript:void(0);" onclick="openFileViewer('<?php echo htmlspecialchars($singleModule['filePath']); ?>', '<?php echo htmlspecialchars($singleModule['fileName']); ?>', '<?php echo htmlspecialchars($singleModule['fileType']); ?>')" class="btn-view-file" aria-label="View Module File">
                                    <i class='bx bx-file'></i> <?php echo htmlspecialchars($singleModule['fileName']); ?>
                                </a>
                            </div>
                        <?php endif; ?>
                    </div>
                        <div class="comments-section">
                    <div class="comments-header">
                        <h3><i class='bx bx-comment'></i> Class Comments <span class="comment-count"><?php echo $totalComments; ?> <?php echo $totalComments === 1 ? 'Comment' : 'Comments'; ?><?php if ($unreadNotifications > 0): ?> (<?php echo $unreadNotifications; ?> new)<?php endif; ?></span></h3>
                        <a href="?teacherSectionID=<?php echo $teacherSectionID; ?>&sectionID=<?php echo $sectionID; ?>&action=view" class="back-to-list">Back to Module List</a>
                    </div>
                    <?php if (!empty($commentsTree)): ?>
                        <?php
                        function renderComments($comments, $level = 0, $userID, $moduleID, $teacherSectionID, $sectionID) {
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
                                            <button class="archive-comment-btn" onclick="showCommentArchiveConfirmModal(<?php echo $comment['commentID']; ?>)">Archive</button>
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
                                    <?php if (!empty($comment['children'])):
                                        renderComments($comment['children'], $level + 1, $userID, $moduleID, $teacherSectionID, $sectionID);
                                    endif; ?>
                                </div>
                            <?php endforeach;
                        }
                        renderComments($commentsTree, 0, $userID, $moduleID, $teacherSectionID, $sectionID);
                    else: ?>
                        <p class="no-comment-yet">No comments yet. Be the first to comment!</p>
                    <?php endif; ?>
                    <div class="comment-form main-comment-form">
                        <form method="POST" class="form-inline">
                            <input type="hidden" name="moduleID" value="<?php echo $moduleID; ?>">
                            <input type="hidden" name="addComment" value="1">
                            <div class="form-group">
                                <img src="<?php echo htmlspecialchars($_SESSION['image']); ?>" alt="Your Avatar" class="comment-avatar">
                                <textarea name="comment" placeholder="Write a comment..." required class="form-textarea"></textarea>
                            </div>
                            <div class="form-actions">
                                <button type="submit" class="submit-btn">Post Comment</button>
                            </div>
                        </form>
                    </div>
                </div>
                    <?php else: ?>
                        <?php if (!empty($activeModules)): ?>
                            <table class="module-table">
                                <thead>
                                    <tr>
                                        <th>#</th>
                                        <th>Description</th>
                                        <th>File</th>
                                        <th>Upload Date</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($activeModules as $index => $module): ?>
                                        <tr>
                                            <td><?php echo $index + 1; ?></td>
                                            <td><?php echo htmlspecialchars($module['description']); ?></td>
                                            <td>
                                                <?php if ($module['filePath']): ?>
                                                    <a id="table-filename" href="javascript:void(0);" onclick="openFileViewer('<?php echo htmlspecialchars($module['filePath']); ?>', '<?php echo htmlspecialchars($module['fileName']); ?>', '<?php echo htmlspecialchars($module['fileType']); ?>')" class="btn-download" aria-label="View Module File">
                                                        <i class='bx bx-show'></i> <?php echo htmlspecialchars($module['fileName']); ?>
                                                    </a>
                                                <?php endif; ?>
                                            </td>
                                            <td><?php echo date('F j, Y', strtotime($module['uploadDate'])); ?></td>
                                            <td>
                                                <a href="?teacherSectionID=<?php echo $teacherSectionID; ?>&sectionID=<?php echo $sectionID; ?>&action=view&moduleID=<?php echo $module['moduleID']; ?>" class="comments-btn">
                                                    <i class='bx bx-message'></i> Comments
                                                    <?php if ($unreadCounts[$module['moduleID']] > 0): ?>
                                                        <span class="badge"><?php echo $unreadCounts[$module['moduleID']]; ?></span>
                                                    <?php endif; ?>
                                                </a>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        <?php else: ?>
                            <p class="no-active-modules">No active modules available.</p>
                        <?php endif; ?>
                    <?php endif; ?>
                </div>

                <!-- Archive Modules Tab -->
                <div class="tab-content <?php echo $action === 'archive' ? 'active' : ''; ?>" id="archive">
                    <?php if (!empty($activeModules)): ?>
                        <table class="module-table">
                            <thead>
                                <tr>
                                    <th>#</th>
                                    <th>Description</th>
                                    <th>File</th>
                                    <th>Upload Date</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($activeModules as $index => $module): ?>
                                    <tr>
                                        <td><?php echo $index + 1; ?></td>
                                        <td><?php echo htmlspecialchars($module['description']); ?></td>
                                        <td>
                                            <?php if ($module['filePath']): ?>
                                                <a id="table-filename" href="javascript:void(0);" onclick="openFileViewer('<?php echo htmlspecialchars($module['filePath']); ?>', '<?php echo htmlspecialchars($module['fileName']); ?>', '<?php echo htmlspecialchars($module['fileType']); ?>')" class="btn-download" aria-label="View Module File">
                                                    <i class='bx bx-show'></i> <?php echo htmlspecialchars($module['fileName']); ?>
                                                </a>
                                            <?php endif; ?>
                                        </td>
                                        <td><?php echo date('F j, Y', strtotime($module['uploadDate'])); ?></td>
                                        <td>
                                            <button class="archive-btn" onclick="showArchiveConfirmModal(<?php echo $module['moduleID']; ?>)" aria-label="Archive Module">
                                                <i class='bx bx-archive'></i> Archive
                                            </button>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php else: ?>
                        <p class="no-active-modules">No active modules available to archive.</p>
                    <?php endif; ?>
                </div>

                <!-- Analytics Tab -->
                <div class="tab-content <?php echo $action === 'analytics' ? 'active' : ''; ?>" id="analytics">
                    <div class="analytics-container">
                        <div class="analytics-chart">
                            <h3 class="title-charts">Module Upload Trends</h3>
                            <canvas id="uploadTrendChart"></canvas>
                        </div>
                        <div class="analytics-chart">
                            <h3 class="title-charts">File Type Distribution</h3>
                            <canvas id="fileTypeChart"></canvas>
                        </div>
                        <div class="analytics-chart">
                            <h3 class="title-charts">Comment Trends</h3>
                            <canvas id="commentTrendChart"></canvas>
                        </div>
                        <div class="analytics-chart">
                            <h3 class="title-charts">Comment Type Distribution</h3>
                            <canvas id="commentTypeChart"></canvas>
                        </div>
                    </div>
                    <div class="analytics-stats">
                        <h3>Statistics</h3>
                    </div>
                    <table class="module-table">
                        <thead>
                            <tr>
                                <th>Total Modules</th>
                                <th>PDF Files</th>
                                <th>Image Files</th>
                                <th>Average File Size</th>
                                <th>Total Comments</th>
                                <th>Total Replies</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td><?php echo count($activeModules); ?></td>
                                <td><?php echo array_sum(array_map(function($item) { return $item['fileType'] == 'application/pdf' ? $item['file_count'] : 0; }, $fileTypeData)); ?></td>
                                <td><?php echo array_sum(array_map(function($item) { return in_array($item['fileType'], ['image/jpeg', 'image/png']) ? $item['file_count'] : 0; }, $fileTypeData)); ?></td>
                                <td>
                                    <?php
                                        $totalSize = array_sum(array_map(function($module) { return $module['fileSize']; }, $activeModules));
                                        $avgSize = count($activeModules) > 0 ? ($totalSize / count($activeModules)) / 1024 / 1024 : 0;
                                        echo number_format($avgSize, 2) . ' MB';
                                    ?>
                                </td>
                                <td><?php echo array_sum(array_map(function($item) { return $item['comment_type'] == 'Comment' ? $item['count'] : 0; }, $commentTypeData)); ?></td>
                                <td><?php echo array_sum(array_map(function($item) { return $item['comment_type'] == 'Reply' ? $item['count'] : 0; }, $commentTypeData)); ?></td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <!-- File Viewer Modal -->
                <div id="file-viewer-modal" class="modal file-viewer" role="dialog" aria-labelledby="file-viewer-title" style="display: none;">
                    <div class="modal-content">
                        <button class="cancel-btn" onclick="closeFileViewer()" aria-label="Close">Close</button>
                        <div class="modal-header">
                            <h2 id="file-viewer-title"></h2>
                        </div>
                        <div id="file-viewer-content"></div>
                    </div>
                </div>

                <!-- Confirmation Modal for Add/Edit -->
                <div id="confirm-modal" class="modal" role="dialog" aria-labelledby="confirm-modal-title">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h2 id="confirm-modal-title">Confirm Submission</h2>
                        </div>
                        <p>Are you sure you want to submit this module?</p>
                        <div class="modal-buttons">
                            <button class="confirm-btn" onclick="submitForm()">Confirm</button>
                            <button class="cancel-btn" onclick="closeConfirmModal()">Cancel</button>
                        </div>
                    </div>
                </div>

                <!-- Archive Confirmation Modal -->
                <div id="archive-confirm-modal" class="modal" role="dialog" aria-labelledby="archive-confirm-modal-title">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h2 id="archive-confirm-modal-title">Confirm Archive</h2>
                        </div>
                        <p>Are you sure you want to archive this module? This action will remove the module from active records and delete the associated file.</p>
                        <div class="modal-buttons">
                            <button class="confirm-btn" onclick="submitArchive()">Confirm</button>
                            <button class="cancel-btn" onclick="closeArchiveConfirmModal()">Cancel</button>
                        </div>
                    </div>
                </div>

                <!-- Comment Archive Confirmation Modal -->
                <div id="comment-archive-confirm-modal" class="modal" role="dialog" aria-labelledby="comment-archive-confirm-modal-title">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h2 id="comment-archive-confirm-modal-title">Confirm Comment Archive</h2>
                        </div>
                        <p>Are you sure you want to archive this comment? This action will also archive any replies.</p>
                        <div class="modal-buttons">
                            <button class="confirm-btn" onclick="submitCommentArchive()">Confirm</button>
                            <button class="cancel-btn" onclick="closeCommentArchiveConfirmModal()">Cancel</button>
                        </div>
                    </div>
                </div>
            </div>
        </main>
    </section>

    <script src="./utils/script.js"></script>
    <script>
        // Tab switching
        document.querySelectorAll('.tab-button').forEach(button => {
            button.addEventListener('click', () => {
                const tab = button.getAttribute('data-tab');
                document.querySelectorAll('.tab-content').forEach(content => content.classList.remove('active'));
                document.querySelectorAll('.tab-button').forEach(btn => btn.classList.remove('active'));
                document.getElementById(tab).classList.add('active');
                button.classList.add('active');
                window.history.pushState({}, '', `?teacherSectionID=<?php echo $teacherSectionID; ?>&sectionID=<?php echo $sectionID; ?>&action=${tab}`);
            });
        });

        // Comments and Replies 
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

        // Modal handling
        function openModal(modalId) {
            const modal = document.getElementById(modalId);
            if (modal) {
                modal.style.display = 'block';
                modal.style.animation = 'modalPop 0.3s ease';
                document.body.style.overflow = 'hidden';
            }
        }

        function closeModal(modalId) {
            const modal = document.getElementById(modalId);
            if (modal) {
                modal.style.animation = 'modalFadeOut 0.3s ease';
                setTimeout(() => {
                    modal.style.display = 'none';
                    modal.style.animation = '';
                    document.body.style.overflow = 'auto';
                }, 300);
            }
        }

        // File viewer modal
        function openFileViewer(filePath, fileName, fileType) {
            const modal = document.getElementById('file-viewer-modal');
            const title = document.getElementById('file-viewer-title');
            const content = document.getElementById('file-viewer-content');
            title.textContent = fileName;

            if (fileType === 'application/pdf') {
                content.innerHTML = `<embed src="${filePath}" type="application/pdf" width="100%" height="600px" />`;
            } else if (fileType === 'image/jpeg' || fileType === 'image/png') {
                content.innerHTML = `<img src="${filePath}" alt="${fileName}" />`;
            } else {
                content.innerHTML = `<p>Unsupported file type: ${fileType}</p>`;
            }

            modal.style.display = 'block';
            modal.style.animation = 'modalPop 0.3s ease';
            document.body.style.overflow = 'hidden';
        }

        function closeFileViewer() {
            const modal = document.getElementById('file-viewer-modal');
            if (modal) {
                modal.style.animation = 'modalFadeOut 0.3s ease';
                setTimeout(() => {
                    modal.style.display = 'none';
                    modal.style.animation = '';
                    document.body.style.overflow = 'auto';
                    document.getElementById('file-viewer-content').innerHTML = '';
                }, 300);
            }
        }

        // Confirmation modal for form submission
        let currentFormId = null;
        function showConfirmModal(formId) {
            currentFormId = formId;
            openModal('confirm-modal');
        }

        function closeConfirmModal() {
            closeModal('confirm-modal');
            currentFormId = null;
        }

        function submitForm() {
            if (currentFormId) {
                document.getElementById(currentFormId).submit();
            }
            closeConfirmModal();
        }

        // Archive confirmation modal
        let currentArchiveModuleId = null;
        function showArchiveConfirmModal(moduleID) {
            currentArchiveModuleId = moduleID;
            openModal('archive-confirm-modal');
        }

        function closeArchiveConfirmModal() {
            closeModal('archive-confirm-modal');
            currentArchiveModuleId = null;
        }

        function submitArchive() {
            if (currentArchiveModuleId) {
                window.location.href = `?teacherSectionID=<?php echo $teacherSectionID; ?>&sectionID=<?php echo $sectionID; ?>&action=archive&archiveModule=1&moduleID=${currentArchiveModuleId}`;
            }
            closeArchiveConfirmModal();
        }

        // Comment archive confirmation modal
        let currentCommentId = null;
        function showCommentArchiveConfirmModal(commentID) {
            currentCommentId = commentID;
            openModal('comment-archive-confirm-modal');
        }

        function closeCommentArchiveConfirmModal() {
            closeModal('comment-archive-confirm-modal');
            currentCommentId = null;
        }

        function submitCommentArchive() {
            if (currentCommentId) {
                window.location.href = `?teacherSectionID=<?php echo $teacherSectionID; ?>&sectionID=<?php echo $sectionID; ?>&action=view&moduleID=<?php echo $moduleID; ?>&archiveComment=1&commentID=${currentCommentId}`;
            }
            closeCommentArchiveConfirmModal();
        }

        // Chart.js for analytics
        document.addEventListener('DOMContentLoaded', () => {
            // Register the datalabels plugin globally
            Chart.register(ChartDataLabels);

            // Upload Trend Chart
            const uploadTrendCtx = document.getElementById('uploadTrendChart')?.getContext('2d');
            if (uploadTrendCtx) {
                const uploadTrendData = [
                    <?php
                    $filteredUploadTrends = array_filter($uploadTrends, function($item) {
                        return $item['upload_count'] > 0;
                    });
                    echo implode(',', array_map(function($item) {
                        return "{ month: '" . $item['month'] . "', upload_count: " . $item['upload_count'] . " }";
                    }, array_reverse($filteredUploadTrends)));
                    ?>
                ];
                new Chart(uploadTrendCtx, {
                    type: 'line',
                    data: {
                        labels: uploadTrendData.map(item => item.month),
                        datasets: [{
                            label: 'Modules Uploaded',
                            data: uploadTrendData.map(item => item.upload_count),
                            borderColor: '#FF6384',
                            backgroundColor: 'rgba(255, 99, 132, 0.2)',
                            fill: true,
                            tension: 0.4,
                            borderWidth: 2
                        }]
                    },
                    options: {
                        responsive: true,
                        plugins: {
                            legend: {
                                position: 'top',
                                labels: {
                                    font: {
                                        size: 18,
                                        family: 'Poppins'
                                    }
                                }
                            },
                            datalabels: {
                                align: 'top',
                                formatter: (value) => value > 0 ? value : '',
                                font: {
                                    size: 16,
                                    family: 'Poppins',
                                    weight: 'bold'
                                }
                            }
                        },
                        scales: {
                            y: {
                                beginAtZero: true,
                                title: {
                                    display: true,
                                    text: 'Number of Modules',
                                    font: {
                                        size: 16,
                                        family: 'Poppins'
                                    }
                                },
                                ticks: {
                                    font: {
                                        size: 14,
                                        family: 'Poppins'
                                    }
                                }
                            },
                            x: {
                                title: {
                                    display: true,
                                    text: 'Month',
                                    font: {
                                        size: 16,
                                        family: 'Poppins'
                                    }
                                },
                                ticks: {
                                    font: {
                                        size: 14,
                                        family: 'Poppins'
                                    }
                                }
                            }
                        },
                        layout: {
                            padding: 20
                        }
                    },
                    plugins: [ChartDataLabels]
                });
                uploadTrendCtx.canvas.style.width = '800px';
                uploadTrendCtx.canvas.style.height = '400px';
            }

            // File Type Distribution Chart
            const fileTypeCtx = document.getElementById('fileTypeChart')?.getContext('2d');
            if (fileTypeCtx) {
                const fileTypeData = [
                    <?php
                    $filteredFileTypeData = array_filter($fileTypeData, function($item) {
                        return $item['file_count'] > 0;
                    });
                    echo implode(',', array_map(function($item) {
                        return "{ fileType: '" . $item['fileType'] . "', file_count: " . $item['file_count'] . " }";
                    }, $filteredFileTypeData));
                    ?>
                ];
                const totalFileCount = fileTypeData.reduce((sum, item) => sum + item.file_count, 0);
                if (totalFileCount > 0) {
                    new Chart(fileTypeCtx, {
                        type: 'pie',
                        data: {
                            labels: fileTypeData.map(item => item.fileType),
                            datasets: [{
                                label: 'File Types',
                                data: fileTypeData.map(item => item.file_count),
                                backgroundColor: ['#FF6384', '#36A2EB', '#FFCE56'],
                                borderColor: ['#fff', '#fff', '#fff'],
                                borderWidth: 2
                            }]
                        },
                        options: {
                            responsive: true,
                            plugins: {
                                legend: {
                                    position: 'bottom',
                                    labels: {
                                        font: {
                                            size: 18,
                                            family: 'Poppins'
                                        }
                                    }
                                },
                                title: {
                                    display: true,
                                    text: 'File Type Distribution',
                                    font: {
                                        size: 20,
                                        family: 'Poppins',
                                        weight: 'bold'
                                    }
                                },
                                datalabels: {
                                    color: '#fff',
                                    font: {
                                        size: 16,
                                        family: 'Poppins',
                                        weight: 'bold'
                                    },
                                    formatter: (value, ctx) => {
                                        let sum = ctx.dataset.data.reduce((a, b) => a + b, 0);
                                        let percentage = sum > 0 ? (value / sum * 100).toFixed(1) + '%' : '';
                                        return percentage;
                                    }
                                }
                            },
                            onClick: (event, elements) => {
                                if (elements.length > 0) {
                                    const index = elements[0].index;
                                    const fileType = fileTypeData[index].fileType;
                                    window.location.href = `modules.php?file_type=${encodeURIComponent(fileType)}`;
                                }
                            }
                        },
                        plugins: [ChartDataLabels]
                    });
                } else {
                    fileTypeCtx.canvas.parentElement.style.display = 'none';
                }
            }

            // Comment Trend Chart
            const commentTrendCtx = document.getElementById('commentTrendChart')?.getContext('2d');
            if (commentTrendCtx) {
                const commentTrendData = [
                    <?php
                    $filteredCommentTrends = array_filter($commentTrends, function($item) {
                        return $item['comment_count'] > 0;
                    });
                    echo implode(',', array_map(function($item) {
                        return "{ month: '" . $item['month'] . "', comment_count: " . $item['comment_count'] . " }";
                    }, array_reverse($filteredCommentTrends)));
                    ?>
                ];
                new Chart(commentTrendCtx, {
                    type: 'bar',
                    data: {
                        labels: commentTrendData.map(item => item.month),
                        datasets: [{
                            label: 'Comments Posted',
                            data: commentTrendData.map(item => item.comment_count),
                            backgroundColor: '#36A2EB',
                            borderColor: '#fff',
                            borderWidth: 1
                        }]
                    },
                    options: {
                        responsive: true,
                        plugins: {
                            legend: {
                                position: 'top',
                                labels: {
                                    font: {
                                        size: 18,
                                        family: 'Poppins'
                                    }
                                }
                            },
                            datalabels: {
                                anchor: 'end',
                                align: 'top',
                                formatter: (value) => value > 0 ? value : '',
                                font: {
                                    size: 16,
                                    family: 'Poppins',
                                    weight: 'bold'
                                }
                            }
                        },
                        scales: {
                            y: {
                                beginAtZero: true,
                                title: {
                                    display: true,
                                    text: 'Number of Comments',
                                    font: {
                                        size: 16,
                                        family: 'Poppins'
                                    }
                                },
                                ticks: {
                                    font: {
                                        size: 14,
                                        family: 'Poppins'
                                    }
                                }
                            },
                            x: {
                                title: {
                                    display: true,
                                    text: 'Month',
                                    font: {
                                        size: 16,
                                        family: 'Poppins'
                                    }
                                },
                                ticks: {
                                    font: {
                                        size: 14,
                                        family: 'Poppins'
                                    }
                                }
                            }
                        },
                        layout: {
                            padding: 20
                        }
                    },
                    plugins: [ChartDataLabels]
                });
                commentTrendCtx.canvas.style.width = '800px';
                commentTrendCtx.canvas.style.height = '400px';
            }

            // Comment Type Distribution Chart
            const commentTypeCtx = document.getElementById('commentTypeChart')?.getContext('2d');
            if (commentTypeCtx) {
                const commentTypeData = [
                    <?php
                    $filteredCommentTypeData = array_filter($commentTypeData, function($item) {
                        return $item['count'] > 0;
                    });
                    echo implode(',', array_map(function($item) {
                        return "{ comment_type: '" . $item['comment_type'] . "', count: " . $item['count'] . " }";
                    }, $filteredCommentTypeData));
                    ?>
                ];
                const totalCommentCount = commentTypeData.reduce((sum, item) => sum + item.count, 0);
                if (totalCommentCount > 0) {
                    new Chart(commentTypeCtx, {
                        type: 'doughnut',
                        data: {
                            labels: commentTypeData.map(item => item.comment_type),
                            datasets: [{
                                label: 'Comment Types',
                                data: commentTypeData.map(item => item.count),
                                backgroundColor: ['#FF6384', '#36A2EB'],
                                borderColor: ['#fff', '#fff'],
                                borderWidth: 2
                            }]
                        },
                        options: {
                            responsive: true,
                            plugins: {
                                legend: {
                                    position: 'bottom',
                                    labels: {
                                        font: {
                                            size: 18,
                                            family: 'Poppins'
                                        }
                                    }
                                },
                                title: {
                                    display: true,
                                    text: 'Comment vs Reply Distribution',
                                    font: {
                                        size: 20,
                                        family: 'Poppins',
                                        weight: 'bold'
                                    }
                                },
                                datalabels: {
                                    color: '#fff',
                                    font: {
                                        size: 16,
                                        family: 'Poppins',
                                        weight: 'bold'
                                    },
                                    formatter: (value, ctx) => {
                                        let sum = ctx.dataset.data.reduce((a, b) => a + b, 0);
                                        let percentage = sum > 0 ? (value / sum * 100).toFixed(1) + '%' : '';
                                        return percentage;
                                    }
                                }
                            },
                            onClick: (event, elements) => {
                                if (elements.length > 0) {
                                    const index = elements[0].index;
                                    const commentType = commentTypeData[index].comment_type;
                                    window.location.href = `modules.php?comment_type=${encodeURIComponent(commentType)}`;
                                }
                            }
                        },
                        plugins: [ChartDataLabels]
                    });
                } else {
                    commentTypeCtx.canvas.parentElement.style.display = 'none';
                }
            }
        });

        // Close modals on outside click
        window.addEventListener('click', (event) => {
            if (event.target.classList.contains('modal')) {
                closeModal(event.target.id);
                closeFileViewer();
                closeConfirmModal();
                closeArchiveConfirmModal();
                closeCommentArchiveConfirmModal();
            }
        });

        // Handle browser back/forward buttons
        window.addEventListener('popstate', () => {
            const urlParams = new URLSearchParams(window.location.search);
            const action = urlParams.get('action') || 'view';
            document.querySelectorAll('.tab-content').forEach(content => content.classList.remove('active'));
            document.querySelectorAll('.tab-button').forEach(btn => btn.classList.remove('active'));
            document.getElementById(action).classList.add('active');
            document.querySelector(`.tab-button[data-tab="${action}"]`).classList.add('active');
        });
    </script>
</body>
</html>

<?php
ob_end_flush();
?>