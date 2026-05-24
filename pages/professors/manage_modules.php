<?php
require_once './sessions/session_professor.php';
require_once '../../config/db_connection.php';
require_once './auto_logout.php';
require_once './check_status.php';

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
    if ($user) {
        $_SESSION['firstName'] = htmlspecialchars($user['firstName']);
        $_SESSION['userType'] = htmlspecialchars($user['userType']);
        $_SESSION['image'] = $user['image'] ? htmlspecialchars($user['image']) : './img/noprofile.png';
    } else {
        header("Location: ../../index.php");
        exit;
    }

    // Handle module upload
    if (isset($_POST['uploadModule']) && isset($_POST['teacherSectionID']) && isset($_GET['subjectID'])) {
        $teacherSectionID = filter_var($_POST['teacherSectionID'], FILTER_VALIDATE_INT);
        $subjectID = filter_var($_GET['subjectID'], FILTER_VALIDATE_INT);
        $description = isset($_POST['description']) ? trim($_POST['description']) : null;
        if (!$teacherSectionID || !$subjectID) {
            $_SESSION['error_message'] = "Invalid teacher section ID or subject ID.";
            header("Location: professorDash.php?subjectID=$subjectID");
            exit;
        }
        if (!isset($_FILES['moduleFile']) || $_FILES['moduleFile']['error'] !== UPLOAD_ERR_OK) {
            $_SESSION['error_message'] = "No file uploaded or upload error.";
            header("Location: professorDash.php?subjectID=$subjectID");
            exit;
        }
        $allowedTypes = [
            'application/pdf',
            'application/msword',
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'image/jpeg',
            'image/png',
            'video/mp4',
            'video/avi'
        ];
        $maxFileSize = 10 * 1024 * 1024; // 10MB
        $file = $_FILES['moduleFile'];
        $fileType = $file['type'];
        $fileSize = $file['size'];
        $fileName = basename($file['name']);
        $uploadDir = '../../Uploads/modules/';
        $filePath = $uploadDir . time() . '_' . preg_replace("/[^a-zA-Z0-9.]/", "_", $fileName);
        $baseUrl = 'http://localhost/capstone-lms';
        $publicFileUrl = str_replace('../../Uploads/modules/', $baseUrl . '/Uploads/modules/', $filePath);
        if (!in_array($fileType, $allowedTypes)) {
            $_SESSION['error_message'] = "Invalid file type. Allowed types: PDF, DOC/DOCX, JPG, PNG, MP4, AVI.";
            header("Location: professorDash.php?subjectID=$subjectID");
            exit;
        }
        if ($fileSize > $maxFileSize) {
            $_SESSION['error_message'] = "File size exceeds 10MB limit.";
            header("Location: professorDash.php?subjectID=$subjectID");
            exit;
        }
        if (!is_dir($uploadDir) && !mkdir($uploadDir, 0777, true)) {
            $_SESSION['error_message'] = "Failed to create upload directory.";
            header("Location: professorDash.php?subjectID=$subjectID");
            exit;
        }
        $checkStmt = $dbConnection->prepare("
            SELECT ts.teacherID, u.firstName, u.lastName, s.subjectName
            FROM teacher_section ts
            JOIN users u ON ts.teacherID = u.userID
            JOIN subjects s ON ts.subjectID = s.subjectID
            WHERE ts.teacherSectionID = :teacherSectionID
            AND ts.subjectID = :subjectID
            AND ts.teacherID = :userID
            AND ts.archived = 0
            AND u.archived = 0
            AND s.archived = 0
        ");
        $checkStmt->bindParam(':teacherSectionID', $teacherSectionID, PDO::PARAM_INT);
        $checkStmt->bindParam(':subjectID', $subjectID, PDO::PARAM_INT);
        $checkStmt->bindParam(':userID', $userID, PDO::PARAM_INT);
        $checkStmt->execute();
        $professorData = $checkStmt->fetch(PDO::FETCH_ASSOC);
        if (!$professorData) {
            $_SESSION['error_message'] = "Invalid assignment, subject, or professor not authorized.";
            header("Location: professorDash.php?subjectID=$subjectID");
            exit;
        }
        $professorName = $professorData['firstName'] . ' ' . $professorData['lastName'];
        $subjectName = $professorData['subjectName'];
        if (!move_uploaded_file($file['tmp_name'], $filePath)) {
            $_SESSION['error_message'] = "Failed to upload file.";
            header("Location: professorDash.php?subjectID=$subjectID");
            exit;
        }
        $insertStmt = $dbConnection->prepare("
            INSERT INTO modules (teacherSectionID, fileName, filePath, fileType, fileSize, description, uploadDate)
            VALUES (:teacherSectionID, :fileName, :filePath, :fileType, :fileSize, :description, NOW())
        ");
        $insertStmt->bindParam(':teacherSectionID', $teacherSectionID, PDO::PARAM_INT);
        $insertStmt->bindParam(':fileName', $fileName);
        $insertStmt->bindParam(':filePath', $filePath);
        $insertStmt->bindParam(':fileType', $fileType);
        $insertStmt->bindParam(':fileSize', $fileSize, PDO::PARAM_INT);
        $insertStmt->bindParam(':description', $description, PDO::PARAM_STR);
        if (!$insertStmt->execute()) {
            $_SESSION['error_message'] = "Failed to save module to database.";
            if (file_exists($filePath)) unlink($filePath);
            header("Location: professorDash.php?subjectID=$subjectID");
            exit;
        }
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
            $_SESSION['error_message'] = "Module uploaded, but no enrolled students found for this section.";
            header("Location: professorDash.php?subjectID=$subjectID");
            exit;
        }
        $webhookUrl = 'https://script.google.com/macros/s/AKfycbzkOfacozdWXck8FQRB--d2WZAhoUZUiimyLrtfGHbolM5cx41RZcrw8nG5UpqlrAX6WQ/exec';
        $moduleData = [
            'teacherSectionID' => $teacherSectionID,
            'fileName' => $fileName,
            'fileUrl' => $publicFileUrl,
            'description' => $description,
            'uploadDate' => date('Y-m-d H:i:s'),
            'studentEmails' => $studentEmails,
            'professorName' => $professorName,
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
            $_SESSION['error_message'] = "Module uploaded, but failed to contact notification service.";
            header("Location: professorDash.php?subjectID=$subjectID");
            exit;
        }
        $response = json_decode($result, true);
        if ($response && $response['result'] === 'success') {
            $_SESSION['success_message'] = "Module uploaded and notifications sent successfully.";
        } else {
            $_SESSION['error_message'] = "Module uploaded, but failed to send notifications: " . ($response['error'] ?? 'Unknown error');
        }
        header("Location: professorDash.php?subjectID=$subjectID");
        exit;
    }

    // Handle module update
    if (isset($_POST['updateModule']) && isset($_POST['moduleID'])) {
        $moduleID = filter_var($_POST['moduleID'], FILTER_VALIDATE_INT);
        $description = isset($_POST['description']) ? trim($_POST['description']) : null;
        $newFileUploaded = isset($_FILES['moduleFile']) && $_FILES['moduleFile']['error'] === UPLOAD_ERR_OK;
        if ($moduleID) {
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
                    $updateStmt = $dbConnection->prepare("UPDATE modules SET description = :description WHERE moduleID = :moduleID AND archived = 0");
                    $updateStmt->bindParam(':moduleID', $moduleID, PDO::PARAM_INT);
                    $updateStmt->bindParam(':description', $description, PDO::PARAM_STR);
                    if ($newFileUploaded) {
                        $allowedTypes = ['application/pdf', 'application/msword', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document', 'image/jpeg', 'image/png', 'video/mp4', 'video/avi'];
                        $maxFileSize = 10 * 1024 * 1024;
                        $file = $_FILES['moduleFile'];
                        $fileType = $file['type'];
                        $fileSize = $file['size'];
                        $fileName = basename($file['name']);
                        $uploadDir = '../../Uploads/modules/';
                        $newFilePath = $uploadDir . time() . '_' . preg_replace("/[^a-zA-Z0-9.]/", "_", $fileName);
                        if (!in_array($fileType, $allowedTypes)) {
                            $_SESSION['error_message'] = "Invalid file type. Allowed types: PDF, DOC/DOCX, JPG, PNG, MP4, AVI.";
                        } elseif ($fileSize > $maxFileSize) {
                            $_SESSION['error_message'] = "File size exceeds 10MB limit.";
                        } elseif (!is_dir($uploadDir) && !mkdir($uploadDir, 0777, true)) {
                            $_SESSION['error_message'] = "Failed to create upload directory.";
                        } elseif (move_uploaded_file($file['tmp_name'], $newFilePath)) {
                            $updateStmt = $dbConnection->prepare("UPDATE modules SET description = :description, fileName = :fileName, filePath = :filePath, fileType = :fileType, fileSize = :fileSize, uploadDate = NOW() WHERE moduleID = :moduleID AND archived = 0");
                            $updateStmt->bindParam(':moduleID', $moduleID, PDO::PARAM_INT);
                            $updateStmt->bindParam(':description', $description, PDO::PARAM_STR);
                            $updateStmt->bindParam(':fileName', $fileName);
                            $updateStmt->bindParam(':filePath', $newFilePath);
                            $updateStmt->bindParam(':fileType', $fileType);
                            $updateStmt->bindParam(':fileSize', $fileSize, PDO::PARAM_INT);
                            if (file_exists($module['filePath'])) {
                                unlink($module['filePath']);
                            }
                        } else {
                            $_SESSION['error_message'] = "Failed to upload new file.";
                            header("Location: professorDash.php");
                            exit;
                        }
                    }
                    if ($updateStmt->execute()) {
                        $_SESSION['success_message'] = "Module updated successfully.";
                    } else {
                        $_SESSION['error_message'] = "Failed to update module.";
                        if ($newFileUploaded && file_exists($newFilePath)) {
                            unlink($newFilePath);
                        }
                    }
                } else {
                    $_SESSION['error_message'] = "Invalid assignment.";
                }
            } else {
                $_SESSION['error_message'] = "Module not found.";
            }
        } else {
            $_SESSION['error_message'] = "Invalid module ID.";
        }
        header("Location: professorDash.php");
        exit;
    }

    // Handle module deletion (archive)
    if (isset($_GET['deleteModule']) && isset($_GET['moduleID'])) {
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
                    if (file_exists($module['filePath'])) unlink($module['filePath']);
                    $_SESSION['success_message'] = "Module deleted successfully.";
                } else {
                    $_SESSION['error_message'] = "Failed to delete module.";
                }
            } else {
                $_SESSION['error_message'] = "Module not found.";
            }
        }
        header("Location: professorDash.php");
        exit;
    }

} catch (PDOException $e) {
    error_log("Database Error: " . $e->getMessage() . " in " . $e->getFile() . " on line " . $e->getLine());
    $_SESSION['error_message'] = "Database error: " . htmlspecialchars($e->getMessage());
    header("Location: professorDash.php");
    exit;
}
?>

