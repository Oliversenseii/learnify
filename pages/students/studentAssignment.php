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

// Validate teacherSectionID
if (!isset($_GET['teacherSectionID']) || !filter_var($_GET['teacherSectionID'], FILTER_VALIDATE_INT)) {
    $_SESSION['error_message'] = "Invalid section ID.";
    header("Location: studentDash.php");
    exit;
}
$teacherSectionID = (int)$_GET['teacherSectionID'];

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

    // Verify student is enrolled in the section
    $checkStmt = $dbConnection->prepare("
        SELECT ts.sectionID, s.sectionName, s.sectionCode, sub.subjectName, sub.subjectCode 
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

    // Handle assignment submission
    if (isset($_POST['submitAssignment']) && isset($_POST['assignmentID'])) {
        $assignmentID = filter_var($_POST['assignmentID'], FILTER_VALIDATE_INT);

        // Verify assignment and check due date
        $checkAssignmentStmt = $dbConnection->prepare("
            SELECT assignmentID, maxScore, dueDate FROM assignments 
            WHERE assignmentID = :assignmentID AND teacherSectionID = :teacherSectionID AND archived = 0
        ");
        $checkAssignmentStmt->bindParam(':assignmentID', $assignmentID, PDO::PARAM_INT);
        $checkAssignmentStmt->bindParam(':teacherSectionID', $teacherSectionID, PDO::PARAM_INT);
        $checkAssignmentStmt->execute();
        $assignment = $checkAssignmentStmt->fetch(PDO::FETCH_ASSOC);
        if (!$assignment) {
            $_SESSION['error_message'] = "Invalid assignment.";
            header("Location: studentAssignment.php?teacherSectionID=$teacherSectionID");
            exit;
        }
        $currentDateTime = new DateTime('now', new DateTimeZone('America/Los_Angeles'));
        $dueDate = new DateTime($assignment['dueDate'], new DateTimeZone('America/Los_Angeles'));
        if ($currentDateTime > $dueDate) {
            $_SESSION['error_message'] = "This assignment is past its due date and cannot be submitted.";
            header("Location: studentAssignment.php?teacherSectionID=$teacherSectionID");
            exit;
        }

        // Check if already submitted
        $checkSubmissionStmt = $dbConnection->prepare("
            SELECT submissionID FROM assignment_submissions 
            WHERE assignmentID = :assignmentID AND studentID = :studentID AND archived = 0
        ");
        $checkSubmissionStmt->bindParam(':assignmentID', $assignmentID, PDO::PARAM_INT);
        $checkSubmissionStmt->bindParam(':studentID', $userID, PDO::PARAM_INT);
        $checkSubmissionStmt->execute();
        if ($checkSubmissionStmt->rowCount() > 0) {
            $_SESSION['error_message'] = "You have already submitted this assignment.";
            header("Location: studentAssignment.php?teacherSectionID=$teacherSectionID");
            exit;
        }

        // Handle file upload
        $filePath = null;
        $fileName = null;
        $fileType = null;
        $fileSize = null;
        if (isset($_FILES['submissionFile']) && $_FILES['submissionFile']['error'] === UPLOAD_ERR_OK) {
            $allowedTypes = ['application/pdf', 'image/jpeg', 'image/png'];
            $maxFileSize = 10 * 1024 * 1024; // 10MB
            $file = $_FILES['submissionFile'];
            $fileType = $file['type'];
            $fileSize = $file['size'];
            $fileName = time() . '_' . preg_replace("/[^a-zA-Z0-9.]/", "_", basename($file['name']));
            $uploadDir = '../../uploads/assignments/';
            $filePath = $uploadDir . $fileName;

            if (!in_array($fileType, $allowedTypes)) {
                $_SESSION['error_message'] = "Invalid file type. Only PDF, JPG, and PNG are allowed.";
                header("Location: studentAssignment.php?teacherSectionID=$teacherSectionID");
                exit;
            }
            if ($fileSize > $maxFileSize) {
                $_SESSION['error_message'] = "File size exceeds 10MB limit.";
                header("Location: studentAssignment.php?teacherSectionID=$teacherSectionID");
                exit;
            }
            if (!move_uploaded_file($file['tmp_name'], $filePath)) {
                $_SESSION['error_message'] = "Failed to upload file.";
                header("Location: studentAssignment.php?teacherSectionID=$teacherSectionID");
                exit;
            }
        }

        // Save submission
        $submissionStmt = $dbConnection->prepare("
            INSERT INTO assignment_submissions (assignmentID, studentID, filePath, fileName, fileType, fileSize, submissionDate) 
            VALUES (:assignmentID, :studentID, :filePath, :fileName, :fileType, :fileSize, NOW())
        ");
        $submissionStmt->bindParam(':assignmentID', $assignmentID, PDO::PARAM_INT);
        $submissionStmt->bindParam(':studentID', $userID, PDO::PARAM_INT);
        $submissionStmt->bindParam(':filePath', $filePath);
        $submissionStmt->bindParam(':fileName', $fileName);
        $submissionStmt->bindParam(':fileType', $fileType);
        $submissionStmt->bindParam(':fileSize', $fileSize);
        $success = $submissionStmt->execute();

        if ($success) {
            // Save score in assignment_scores (totalScore = 0 until graded)
            $scoreStmt = $dbConnection->prepare("
                INSERT INTO assignment_scores (assignmentID, studentID, totalScore, maxScore, recordedDate) 
                VALUES (:assignmentID, :studentID, 0, :maxScore, NOW())
            ");
            $scoreStmt->bindParam(':assignmentID', $assignmentID, PDO::PARAM_INT);
            $scoreStmt->bindParam(':studentID', $userID, PDO::PARAM_INT);
            $scoreStmt->bindParam(':maxScore', $assignment['maxScore'], PDO::PARAM_INT);
            $scoreStmt->execute();

            $_SESSION['success_message'] = "Assignment submitted successfully. Awaiting professor grading.";
        } else {
            $_SESSION['error_message'] = "Failed to submit assignment.";
        }
        header("Location: studentAssignment.php?teacherSectionID=$teacherSectionID");
        exit;
    }

    // Handle unsubmission
    if (isset($_POST['unsubmitAssignment']) && isset($_POST['assignmentID'])) {
        $assignmentID = filter_var($_POST['assignmentID'], FILTER_VALIDATE_INT);

        // Verify assignment and check due date
        $checkAssignmentStmt = $dbConnection->prepare("
            SELECT assignmentID, dueDate FROM assignments 
            WHERE assignmentID = :assignmentID AND teacherSectionID = :teacherSectionID AND archived = 0
        ");
        $checkAssignmentStmt->bindParam(':assignmentID', $assignmentID, PDO::PARAM_INT);
        $checkAssignmentStmt->bindParam(':teacherSectionID', $teacherSectionID, PDO::PARAM_INT);
        $checkAssignmentStmt->execute();
        $assignment = $checkAssignmentStmt->fetch(PDO::FETCH_ASSOC);
        if (!$assignment) {
            $_SESSION['error_message'] = "Invalid assignment.";
            header("Location: studentAssignment.php?teacherSectionID=$teacherSectionID");
            exit;
        }
        $currentDateTime = new DateTime('now', new DateTimeZone('America/Los_Angeles'));
        $dueDate = new DateTime($assignment['dueDate'], new DateTimeZone('America/Los_Angeles'));
        if ($currentDateTime > $dueDate) {
            $_SESSION['error_message'] = "Cannot unsubmit assignment past its due date.";
            header("Location: studentAssignment.php?teacherSectionID=$teacherSectionID");
            exit;
        }

        // Check if submission exists
        $checkSubmissionStmt = $dbConnection->prepare("
            SELECT submissionID, filePath FROM assignment_submissions 
            WHERE assignmentID = :assignmentID AND studentID = :studentID AND archived = 0
        ");
        $checkSubmissionStmt->bindParam(':assignmentID', $assignmentID, PDO::PARAM_INT);
        $checkSubmissionStmt->bindParam(':studentID', $userID, PDO::PARAM_INT);
        $checkSubmissionStmt->execute();
        $submission = $checkSubmissionStmt->fetch(PDO::FETCH_ASSOC);
        if (!$submission) {
            $_SESSION['error_message'] = "No submission found to unsubmit.";
            header("Location: studentAssignment.php?teacherSectionID=$teacherSectionID");
            exit;
        }

        // Delete submission
        $deleteSubmissionStmt = $dbConnection->prepare("
            UPDATE assignment_submissions SET archived = 1 
            WHERE assignmentID = :assignmentID AND studentID = :studentID AND archived = 0
        ");
        $deleteSubmissionStmt->bindParam(':assignmentID', $assignmentID, PDO::PARAM_INT);
        $deleteSubmissionStmt->bindParam(':studentID', $userID, PDO::PARAM_INT);
        $success = $deleteSubmissionStmt->execute();

        // Delete score
        $deleteScoreStmt = $dbConnection->prepare("
            UPDATE assignment_scores SET archived = 1 
            WHERE assignmentID = :assignmentID AND studentID = :studentID AND archived = 0
        ");
        $deleteScoreStmt->bindParam(':assignmentID', $assignmentID, PDO::PARAM_INT);
        $deleteScoreStmt->bindParam(':studentID', $userID, PDO::PARAM_INT);
        $success = $success && $deleteScoreStmt->execute();

        // Delete uploaded file
        if ($success && $submission['filePath'] && file_exists($submission['filePath'])) {
            unlink($submission['filePath']);
        }

        if ($success) {
            $_SESSION['success_message'] = "Assignment unsubmitted successfully. You can submit again.";
        } else {
            $_SESSION['error_message'] = "Failed to unsubmit assignment.";
        }
        header("Location: studentAssignment.php?teacherSectionID=$teacherSectionID");
        exit;
    }

    // Fetch assignments and student submissions
    $assignmentStmt = $dbConnection->prepare("
        SELECT a.assignmentID, a.title, a.description, a.dueDate, a.maxScore, a.filePath, a.fileName, a.fileType,
               s.submissionID, s.filePath AS submissionFilePath, s.fileName AS submissionFileName, s.fileType AS submissionFileType, s.fileSize, s.submissionDate,
               sc.totalScore
        FROM assignments a 
        LEFT JOIN assignment_submissions s ON a.assignmentID = s.assignmentID AND s.studentID = :userID AND s.archived = 0 
        LEFT JOIN assignment_scores sc ON a.assignmentID = sc.assignmentID AND sc.studentID = :userID AND sc.archived = 0
        WHERE a.teacherSectionID = :teacherSectionID AND a.archived = 0 
        ORDER BY a.dueDate DESC
    ");
    $assignmentStmt->bindParam(':teacherSectionID', $teacherSectionID, PDO::PARAM_INT);
    $assignmentStmt->bindParam(':userID', $userID, PDO::PARAM_INT);
    $assignmentStmt->execute();
    $assignments = $assignmentStmt->fetchAll(PDO::FETCH_ASSOC);

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
    <link rel="stylesheet" href="./css/scrollbar.css">
    <link rel="stylesheet" href="./utils/logo.css">
    <script src="./logout.js"></script>
    <title>Learnify - Student Assignments</title>
    <style>
        :root {
        --blue: #3C91E6;
        --green: #10b981;
        --red: #ef4444;
        --purple: #8b5cf6;
        --yellow: #f59e0b;
        --primary: #2B6CB0;
        --primary-dark: #1E4A7A;
        --white: #FFFFFF;
        --hover: #d5d5d5;
        --secondary-hover: #e6f0ff;
        --secondary-grey: #f8faff;
        --transition: all 0.3s ease;
        --shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06);
    }

    body.dark {
        --hover: #1a1e2e;
        --secondary-hover: #3b5998;
        --secondary-grey: #1a2332;
    }

    .admin-table {
        margin: 1.5rem;
        padding: 1rem;
        background: var(--light);
        border-radius: 0.5rem;
        box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
        overflow-x: auto;
    }

    .admin-table h2, .your-submission {
        padding: 10px;
        color: var(--dark);
        font-size: clamp(1.2rem, 3vw, 2rem) !important;
    }

    .admin-table table {
        width: 100%;
        border-collapse: collapse;
        font-size: clamp(1rem, 3vw, 1.2rem);
    }

    .admin-table th,
    .admin-table td {
        padding: 0.75rem;
        text-align: left;
        border-bottom: 1px solid var(--grey);
    }

    .admin-table th {
        background: #0056b3;
        color: white;
        font-weight: 600;
        text-transform: uppercase;
        letter-spacing: 0.05em;
    }

    .admin-table td {
        color: var(--dark);
        vertical-align: middle;
        text-transform: capitalize;
    }

    .admin-table tr.assignment-row {
        cursor: pointer;
        transition: background-color 0.2s ease, color 0.2s ease;
    }

    .admin-table tr.assignment-row:hover {
        background: linear-gradient(135deg, var(--secondary-hover), var(--hover));
    }

    .admin-table tr.assignment-row.highlighted {
        background: linear-gradient(135deg, var(--hover), var(--secondary-hover));
    }

    .admin-table tr.assignment-row.highlighted td {
        color: var(--dark);
    }

    .status-badge {
        display: inline-block;
        padding: 0.5rem 1rem;
        border-radius: 0.375rem;
        font-size: clamp(1rem, 3vw, 1.2rem);
        font-weight: 600;
        text-align: center;
    }

    .status-submitted {
        background: linear-gradient(135deg, #28a745, #218838);
        color: white;
    }

    .status-not-submitted {
        background: linear-gradient(135deg, #dc3545, #c82333);
        color: white;
    }

    .status-past-due {
        background: linear-gradient(135deg, #dc3545, #c82333);
        color: white;
    }

    .submitted-btn, .view-btn, .unsubmit-btn, .preview-btn {
        background: linear-gradient(135deg, #28a745, #218838);
        color: white;
        padding: 0.5rem 1rem;
        border-radius: 0.375rem;
        border: none;
        text-decoration: none;
        cursor: pointer;
        font-size: clamp(1rem, 3vw, 1.2rem);
        display: inline-flex;
        align-items: center;
        gap: 0.5rem;
        transition: background-color 0.3s ease;
        margin: 0.25rem;
    }

    .submitted-btn:hover, .preview-btn:hover {
        background: linear-gradient(135deg, #218838, #1e7e34);
    }

    .view-btn {
        background: linear-gradient(135deg, #6f42c1, #5a32a8);
    }

    .view-btn:hover {
        background: linear-gradient(135deg, #5a32a8, #4a288f);
    }

    .unsubmit-btn {
        background: linear-gradient(135deg, #f59e0b, #d97706);
    }

    .unsubmit-btn:hover {
        background: linear-gradient(135deg, #d97706, #b45309);
    }

    .submitted-btn:disabled, .unsubmit-btn:disabled {
        background: linear-gradient(135deg, #dc3545, #c82333);
        cursor: not-allowed;
    }

    .modal {
        display: none;
        position: fixed;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        background-color: rgba(0, 0, 0, 0.5);
        z-index: 1000000;
        justify-content: center;
        align-items: center;
        animation: fadeIn 0.3s ease-in-out;
    }

    .modal.fullscreen {
        background-color: rgba(0, 0, 0, 0.8);
    }

    @keyframes fadeIn {
        from { opacity: 0; }
        to { opacity: 1; }
    }

    .modal-content {
        background: var(--light);
        border-radius: 0.75rem;
        max-width: 90%;
        width: 800px;
        max-height: 85vh;
        overflow: hidden;
        box-shadow: 0 8px 24px rgba(0, 0, 0, 0.2);
        position: relative;
        animation: slideIn 0.3s ease-in-out;
        display: flex;
        flex-direction: column;
    }

    .modal-content.fullscreen {
        width: 100%;
        max-width: 100%;
        height: 100vh;
        max-height: 100vh;
        border-radius: 0;
        margin: 0;
    }

    .preview-modal-content {
        width: 90%;
        max-width: 1000px;
        height: 80vh;
    }

    @keyframes slideIn {
        from { transform: translateY(-20px); opacity: 0; }
        to { transform: translateY(0); opacity: 1; }
    }

    .modal-header {
        background: linear-gradient(135deg, var(--blue), #2563eb);
        padding: 1.5rem;
        border-top-left-radius: 0.75rem;
        border-top-right-radius: 0.75rem;
        color: white;
        display: flex;
        justify-content: space-between;
        align-items: center;
    }

    .modal-content.fullscreen .modal-header {
        border-radius: 0;
    }

    .modal-header h3 {
        margin: 0;
        font-size: clamp(1.5rem, 3vw, 2rem);
        font-weight: 600;
    }

    .modal-header .close-btn,
    .modal-header .fullscreen-btn {
        background: transparent;
        border: none;
        color: white;
        font-size: clamp(1.5rem, 3vw, 2rem);
        cursor: pointer;
        transition: color 0.2s ease;
        margin-left: 1rem;
    }

    .modal-header .close-btn:hover,
    .modal-header .fullscreen-btn:hover {
        color: var(--red);
    }

    .modal-body {
        display: flex;
        flex-direction: row;
        flex: 1;
        overflow: hidden;
    }

    .modal-left {
        width: 100%;
        padding: 1.5rem;
        overflow-y: auto;
    }

    .modal-left {
        border-right: 1px solid var(--grey);
    }

    .modal-body .assignment-info {
        margin-bottom: 1.5rem;
        padding: 1rem;
        background: rgba(255, 255, 255, 0.1);
        box-shadow: 0 4px 30px rgba(0, 0, 0, 0.1);
        border-radius: 0.5rem;
    }

    .modal-body .assignment-info p {
        margin: 0.5rem;
        font-size: clamp(1.2rem, 3vw, 1.5rem);
        color: var(--dark);
        text-align: left;
    }

    .modal-body .assignment-form {
        margin-top: 1rem;
        padding: 1.25rem;
        background: transparent;
        border: 1px solid var(--light);
        border-radius: 0.5rem;
        box-shadow: 0 2px 8px rgba(0, 0, 0, 0.05);
    }

    #submissionFile {
        margin-bottom: 1rem;
        font-size: clamp(1rem, 3vw, 1.2rem);
        width: 100%;
        padding: 0.5rem;
        border: 1px solid var(--dark-grey);
        border-radius: 0.375rem;
        background-color: var(--grey);
        cursor: pointer;
        margin-top: 10px;
    }

    #submissionFile[type="file"]::file-selector-button {
        background: linear-gradient(135deg, #007bff, #0056b3);
        color: white;
        padding: 5px 10px;
        border: none;
        border-radius: 4px;
        font-size: clamp(1rem, 3vw, 1.2rem);
        cursor: pointer;
    }

    #submissionFile[type="file"]::file-selector-button:hover {
        background: linear-gradient(135deg, #0056b3, #003d80);
    }

    #submissionFile[type="file"]:focus {
        outline: none;
        border-color: var(--blue);
        box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.2);
    }

    .modal-body .assignment-form .error {
        color: var(--red);
        font-size: clamp(1rem, 3vw, 1.2rem);
        margin-top: 0.25rem;
        display: none;
    }

    .modal-footer {
        padding: 1rem 1.5rem;
        border-top: 1px solid var(--grey);
        display: flex;
        justify-content: flex-end;
        gap: 1rem;
        background: var(--light);
        border-bottom-left-radius: 0.75rem;
        border-bottom-right-radius: 0.75rem;
    }

    .modal-content.fullscreen .modal-footer {
        border-radius: 0;
    }

    .modal-footer .submit-btn, .modal-footer .cancel-btn {
        padding: 0.75rem 1.5rem;
        border: none;
        border-radius: 0.375rem;
        font-size: clamp(1rem, 3vw, 1.1rem);
        font-weight: 500;
        cursor: pointer;
        transition: background-color 0.2s ease;
    }

    .modal-footer .submit-btn {
        background: linear-gradient(135deg, #007bff, #0056b3);
        color: white;
    }

    .modal-footer .submit-btn:hover {
        background: linear-gradient(135deg, #0056b3, #003d80);
    }

    .modal-footer .cancel-btn {
        background: linear-gradient(135deg, #dc3545, #c82333);
        color: white;
    }

    .modal-footer .cancel-btn:hover {
        background: linear-gradient(135deg, #c82333, #b21f2d);
    }

    .success-notification, .error-notification {
        background: linear-gradient(135deg, #28a745, #218838);
        color: white;
        padding: 1rem;
        margin: 1.5rem;
        border-radius: 0.375rem;
        text-align: center;
        font-size: clamp(1rem, 3vw, 1.2rem);
        font-weight: 600;
    }

    .error-notification {
        background: linear-gradient(135deg, #dc3545, #c82333);
    }

    .collapsible-content {
        display: none;
        background-color: var(--grey);
        box-shadow: 0 4px 30px rgba(0, 0, 0, 0.1);
        padding: 1.5rem;
        border-radius: 0.5rem;
        margin: 0.5rem 1rem;
        transition: max-height 0.3s ease-out;
        overflow: hidden;
    }

    .collapsible-content.active {
        display: block;
    }

    .collapsible-content h4 {
        margin: 0 0 1rem;
        color: var(--dark);
        font-size: clamp(1rem, 3vw, 1.2rem);
        border-bottom: 1px solid var(--dark);
        font-weight: 600;
    }

    .collapsible-content p {
        margin: 0.5rem 0;
        font-size: clamp(1rem, 3vw, 1.2rem);
        color: var(--dark);
    }

    .view-table {
        width: 100%;
        border-collapse: collapse;
        font-size: clamp(1rem, 3vw, 1.2rem);
        margin-top: 1rem;
    }

    .view-table th,
    .view-table td {
        padding: 0.75rem;
        border-bottom: 1px solid var(--grey);
        text-align: left;
    }

    .view-table th {
        background: #0056b3;
        color: white;
        font-weight: 600;
        text-transform: uppercase;
    }

    .view-table td {
        color: var(--dark);
    }

    .download-link {
        color: var(--blue);
        text-decoration: none;
    }

    .download-link:hover {
        text-decoration: underline;
    }

    .preview-container {
        width: 100%;
        height: 100%;
        display: flex;
        justify-content: center;
        align-items: center;
        overflow: hidden;
    }

    .preview-container iframe,
    .preview-container img {
        width: 100%;
        height: 100%;
        border: none;
        object-fit: contain;
    }

    .preview-placeholder {
        color: var(--dark-grey);
        font-size: clamp(1rem, 3vw, 1.2rem);
        text-align: center;
    }

    .back-btn {
        display: flex;
        align-items: center;
        text-decoration: none;
        color: var(--dark);
        font-size: clamp(1rem, 3vw, 1.1rem);
        padding: 0.5rem;
        border-radius: 0.375rem;
        transition: all 0.3s ease;
    }

    .back-btn:hover {
        background-color: var(--grey);
        color: var(--blue);
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

    .assignment-table {
        width: 100%;
        border-collapse: collapse;
        margin: 20px 0;
    }

    .assignment-table__header,
    .assignment-table__data {
        padding: 12px;
        text-align: left;
        background-color: var(--grey) !important;
        color: var(--dark) !important;
        border: 1px solid #ccc;
    }

    .assignment-table__header {
        width: 30%;
        font-weight: bold;
        vertical-align: top;
    }

    .assignment-table__data {
        width: 70%;
    }

    .assignment-table__download-link {
        color: #0066cc;
        text-decoration: none;
    }

    .assignment-table__download-link:hover {
        text-decoration: underline;
    }

    /* Responsive Table for Small Screens */
    @media screen and (max-width: 768px) {
        .admin-table {
            margin: 0.5rem;
            padding: 0.5rem;
            overflow-x: auto;
        }

        .admin-table table {
            display: block;
            width: 100%;
        }

        .admin-table thead {
            display: none;
        }

        .admin-table tbody {
            display: block;
        }

        .admin-table tr.assignment-row {
            display: block;
            margin-bottom: 1rem;
            padding: 1rem;
            background: var(--light);
            border-radius: 0.5rem;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
            border: 1px solid var(--grey);
        }

        .admin-table tr.assignment-row td {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            padding: 0.5rem 0;
            border-bottom: 1px solid var(--grey);
            font-size: clamp(0.9rem, 2.5vw, 1rem);
        }

        .admin-table tr.assignment-row td::before {
            content: attr(data-label);
            width: 30%;
            min-width: 100px;
            font-weight: bold;
            text-transform: uppercase;
            color: #0056b3;
            padding-right: 0.5rem;
        }

        .admin-table tr.assignment-row td:not(:last-child) {
            border-bottom: 1px solid var(--grey);
        }

        .admin-table tr.assignment-row td:last-child {
            border-bottom: none;
        }

        /* Assign data-label to each td */
        .admin-table tr.assignment-row td:nth-child(1)::before { content: "#"; }
        .admin-table tr.assignment-row td:nth-child(2)::before { content: "Title"; }
        .admin-table tr.assignment-row td:nth-child(3)::before { content: "Due Date"; }
        .admin-table tr.assignment-row td:nth-child(4)::before { content: "Max Points"; }
        .admin-table tr.assignment-row td:nth-child(5)::before { content: "Your Points"; }
        .admin-table tr.assignment-row td:nth-child(6)::before { content: "Status"; }
        .admin-table tr.assignment-row td:nth-child(7)::before { content: "Actions"; }

        .view-table {
            display: block;
            width: 100%;
        }

        .view-table thead {
            display: none;
        }

        .view-table tbody {
            display: block;
        }

        .view-table tr {
            display: block;
            margin-bottom: 1rem;
        }

        .view-table td {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            padding: 0.5rem 0;
            border-bottom: 1px solid var(--grey);
            font-size: clamp(0.9rem, 2.5vw, 1rem);
        }

        .view-table td::before {
            content: attr(data-label);
            width: 30%;
            min-width: 100px;
            font-weight: bold;
            text-transform: uppercase;
            color: #0056b3;
            padding-right: 0.5rem;
        }

        .view-table td:not(:last-child) {
            border-bottom: 1px solid var(--grey);
        }

        .view-table td:last-child {
            border-bottom: none;
        }

        /* Assign data-label to each td in view-table */
        .view-table td:nth-child(1)::before { content: "Action"; }
        .view-table td:nth-child(2)::before { content: "Submission Date"; }
        .view-table td:nth-child(3)::before { content: "Score"; }
        .view-table td:nth-child(4)::before { content: "Action"; }

        .collapsible-content {
            margin: 0;
            padding: 1rem;
            border-radius: 0.5rem;
        }

        .modal-content {
            flex-direction: column;
            width: 95%;
            max-height: 90vh;
        }

        .modal-left, .modal-right {
            width: 100%;
            height: auto;
        }

        .modal-left {
            border-right: none;
            border-bottom: 1px solid var(--grey);
        }

        .preview-modal-content {
            width: 95%;
            height: 70vh;
        }

        .modal-header h3 {
            font-size: clamp(1.2rem, 2.5vw, 1.5rem);
        }

        .modal-body .assignment-info p,
        .modal-body .assignment-form .error,
        .collapsible-content p,
        .collapsible-content h4,
        .view-table {
            font-size: clamp(0.9rem, 2.5vw, 1rem);
        }

        .submitted-btn, .view-btn, .unsubmit-btn, .preview-btn, .status-badge {
            font-size: clamp(0.8rem, 2.5vw, 0.9rem);
            padding: 0.4rem 0.8rem;
        }

        .modal-footer .submit-btn, .modal-footer .cancel-btn {
            font-size: clamp(0.8rem, 2.5vw, 0.9rem);
            padding: 0.6rem 1rem;
        }
    }

    @media screen and (max-width: 480px) {
        .admin-table {
            margin: 0.25rem;
            padding: 0.25rem;
        }

        .admin-table tr.assignment-row td::before,
        .view-table td::before {
            min-width: 80px;
            font-size: clamp(0.8rem, 2.5vw, 0.9rem);
        }

        .admin-table tr.assignment-row td,
        .view-table td {
            font-size: clamp(0.8rem, 2.5vw, 0.9rem);
            padding: 0.3rem 0;
        }

        .modal-content {
            width: 98%;
        }

        .preview-modal-content {
            width: 98%;
            height: 65vh;
        }

        .modal-header h3 {
            font-size: clamp(1rem, 2.5vw, 1.2rem);
        }

        .modal-body .assignment-info p,
        .modal-body .assignment-form .error,
        .collapsible-content p,
        .collapsible-content h4,
        .view-table {
            font-size: clamp(0.8rem, 2.5vw, 0.9rem);
        }

        .submitted-btn, .view-btn, .unsubmit-btn, .preview-btn {
            font-size: clamp(0.7rem, 2.5vw, 0.8rem);
            padding: 0.3rem 0.6rem;
        }

        .modal-footer .submit-btn, .modal-footer .cancel-btn {
            font-size: clamp(0.7rem, 2.5vw, 0.8rem);
            padding: 0.5rem 0.8rem;
        }

        .assignment-table {
            display: flex;
            width: 100%;
            flex-direction: column;
        }
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
                    <h1>Assignments</h1>
                    <ul class="breadcrumb">
                        <li><a style="color: var(--dark);" href="./studentDash.php">Home</a></li>
                        <li><i class='bx bx-chevron-right'></i></li>
                        <li><a style="color: var(--dark);" href="./studentDash.php">Class Details</a></li>
                        <li><i class='bx bx-chevron-right'></i></li>
                        <li><a class="active" href="#">Assignments</a></li>
                    </ul>
                </div>
                <a href="./studentDash.php" class="back-button">
                    <i class='bx bx-arrow-back'></i> Back to Dashboard
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

            <div class="admin-table">
                <h2><?php echo htmlspecialchars($section['subjectName'] . ' (' . $section['sectionName'] . ')'); ?></h2>
                <table>
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Title</th>
                            <th>Due Date</th>
                            <th>Max Points</th>
                            <th>Your Points</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!empty($assignments)): ?>
                            <?php foreach ($assignments as $index => $assignment): ?>
                                <?php
                                // Check if assignment has been submitted
                                $hasSubmitted = !empty($assignment['submissionID']);
                                // Check due date
                                $currentDateTime = new DateTime('now', new DateTimeZone('America/Los_Angeles'));
                                $dueDate = new DateTime($assignment['dueDate'], new DateTimeZone('America/Los_Angeles'));
                                $isPastDue = $currentDateTime > $dueDate;
                                ?>
                                <tr class="assignment-row" onclick="toggleCollapse('collapsible-<?php echo $assignment['assignmentID']; ?>', this)">
                                    <td><?php echo $index + 1; ?></td>
                                    <td><?php echo htmlspecialchars($assignment['title']); ?></td>
                                    <td><?php echo date('F j, Y, g:i A', strtotime($assignment['dueDate'])); ?></td>
                                    <td><?php echo htmlspecialchars($assignment['maxScore']); ?></td>
                                    <td>
                                        <?php 
                                        if ($hasSubmitted && isset($assignment['totalScore']) && $assignment['totalScore'] !== null) {
                                            echo htmlspecialchars($assignment['totalScore'] . '/' . $assignment['maxScore']);
                                        } elseif ($hasSubmitted) {
                                            echo 'Pending Grading';
                                        } else {
                                            echo '-';
                                        }
                                        ?>
                                    </td>
                                    <td>
                                        <span class="status-badge <?php echo $hasSubmitted ? 'status-submitted' : ($isPastDue ? 'status-past-due' : 'status-not-submitted'); ?>">
                                            <?php echo $hasSubmitted ? 'Submitted' : ($isPastDue ? 'Past Due' : 'Not Submitted'); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <?php if (!$hasSubmitted && !$isPastDue): ?>
                                            <button class="submitted-btn" onclick="openModal('submit-assignment-modal-<?php echo $assignment['assignmentID']; ?>'); event.stopPropagation();" aria-label="Submit Assignment">
                                                <i class='bx bx-upload'></i> Submit Assignment
                                            </button>
                                        <?php elseif ($isPastDue): ?>
                                            <button class="submit-btn" disabled>
                                                <i class='bx bx-lock'></i> Past Due
                                            </button>
                                        <?php else: ?>
                                            <!-- Empty for submitted assignments -->
                                        <?php endif; ?>
                                    </td>
                                </tr>
                                <tr>
                                    <td colspan="7">
                                        <div id="collapsible-<?php echo $assignment['assignmentID']; ?>" class="collapsible-content">
                                            
                                            <!-- TABLE HEADER SUB START -->
                                             <table class="assignment-table" style="overflow-x: auto;">
                                                <tr>
                                                    <th class="assignment-table__header">Description</th>
                                                    <td class="assignment-table__data">
                                                        <?php echo $assignment['description'] ? htmlspecialchars($assignment['description']) : 'No description'; ?>
                                                    </td>
                                                </tr>
                                                <tr>
                                                    <th class="assignment-table__header">Teacher's File</th>
                                                    <td class="assignment-table__data">
                                                        <?php if ($assignment['filePath'] && file_exists($assignment['filePath'])): ?>
                                                            <a href="<?php echo htmlspecialchars($assignment['filePath']); ?>" 
                                                            class="assignment-table__download-link" 
                                                            download="<?php echo htmlspecialchars($assignment['fileName']); ?>">
                                                                <?php echo htmlspecialchars($assignment['fileName']); ?>
                                                            </a>
                                                        <?php else: ?>
                                                            No file attached
                                                        <?php endif; ?>
                                                    </td>
                                                </tr>
                                            </table>
                                            <!-- TABLE HEADER SUB END -->
                                            <?php if ($hasSubmitted): ?>
                                                <h4 class="your-submission">Your Submission</h4>
                                                <table class="view-table">
                                                    <thead>
                                                        <tr>
                                                            <th>Action</th>
                                                            <th>Submission Date</th>
                                                            <th>Score</th>
                                                            <th>Action</th>
                                                        </tr>
                                                    </thead>
                                                    <tbody>
                                                        <tr>
                                                            <td>
                                                                <?php if ($assignment['submissionFilePath']): ?>
                                                                    <a class="view-btn" href="view_file.php?file=<?php echo htmlspecialchars($assignment['submissionFilePath']); ?>"><i class='bx bxs-file-doc'></i> View File</a>
                                                                <?php else: ?>
                                                                    No file submitted
                                                                <?php endif; ?>
                                                            </td>
                                                            <td><?php echo $assignment['submissionDate'] ? date('F j, Y, H:i', strtotime($assignment['submissionDate'])) : '-'; ?></td>
                                                            <td><?php echo isset($assignment['totalScore']) && $assignment['totalScore'] !== null ? htmlspecialchars($assignment['totalScore'] . '/' . $assignment['maxScore']) : 'Pending Grading'; ?></td>
                                                            <td>
                                                                <?php if (!$isPastDue): ?>
                                                                    <form method="POST" onsubmit="return confirm('Are you sure you want to unsubmit this assignment?');">
                                                                        <input type="hidden" name="assignmentID" value="<?php echo $assignment['assignmentID']; ?>">
                                                                        <button type="submit" name="unsubmitAssignment" class="unsubmit-btn"><i class='bx bx-undo'></i> Unsubmit</button>
                                                                    </form>
                                                                <?php else: ?>
                                                                    <button class="unsubmit-btn" disabled><i class='bx bx-lock'></i> Past Due</button>
                                                                <?php endif; ?>
                                                            </td>
                                                        </tr>
                                                    </tbody>
                                                </table>
                                            <?php else: ?>
                                                <p>No submission yet.</p>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                </tr>
                                <!-- Submit Assignment Modal -->
                                <?php if (!$hasSubmitted && !$isPastDue): ?>
                                    <div id="submit-assignment-modal-<?php echo $assignment['assignmentID']; ?>" class="modal" role="dialog" aria-labelledby="submit-assignment-title-<?php echo $assignment['assignmentID']; ?>">
                                        <div class="modal-content">
                                            <div class="modal-header">
                                                <h3 id="submit-assignment-title-<?php echo $assignment['assignmentID']; ?>">Submit Assignment: <?php echo htmlspecialchars($assignment['title']); ?></h3>
                                                <div>
                                                    <button class="fullscreen-btn" onclick="toggleFullscreen('submit-assignment-modal-<?php echo $assignment['assignmentID']; ?>')" aria-label="Toggle fullscreen">
                                                        <i class='bx bx-fullscreen'></i>
                                                    </button>
                                                    <button class="close-btn" onclick="closeModal('submit-assignment-modal-<?php echo $assignment['assignmentID']; ?>')" aria-label="Close modal">
                                                        <i class='bx bx-x'></i>
                                                    </button>
                                                </div>
                                            </div>
                                            <div class="modal-body">
                                                <div class="modal-left">
                                                    <div class="assignment-info">
                                                        <p><strong>Description:</strong> <?php echo $assignment['description'] ? htmlspecialchars($assignment['description']) : 'No description'; ?></p>
                                                        <p><strong>Due Date:</strong> <?php echo date('F j, Y, g:i A', strtotime($assignment['dueDate'])); ?></p>
                                                        <p><strong>Max Points:</strong> <?php echo htmlspecialchars($assignment['maxScore']); ?></p>
                                                        <p><strong>Professor's File:</strong> 
                                                            <?php if ($assignment['filePath'] && file_exists($assignment['filePath'])): ?>
                                                                <button class="preview-btn" onclick="openModal('preview-file-modal-<?php echo $assignment['assignmentID']; ?>')" aria-label="View Professor's File">
                                                                    <i class='bx bxs-file'></i> View Professor's File
                                                                </button>
                                                            <?php else: ?>
                                                                No file attached
                                                            <?php endif; ?>
                                                        </p>
                                                    </div>
                                                    <form class="assignment-form" method="POST" enctype="multipart/form-data" onsubmit="return confirmSubmission()">
                                                        <input type="hidden" name="assignmentID" value="<?php echo $assignment['assignmentID']; ?>">
                                                        <input id="submissionFile" type="file" name="submissionFile" accept=".pdf,.jpg,.png" required>
                                                        <p class="error">Please select a PDF, JPG, or PNG file to submit.</p>
                                                        <div class="modal-footer">
                                                            <button type="button" class="cancel-btn" onclick="closeModal('submit-assignment-modal-<?php echo $assignment['assignmentID']; ?>')">Cancel</button>
                                                            <button type="submit" class="submit-btn" name="submitAssignment">Submit Assignment</button>
                                                        </div>
                                                    </form>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    <!-- Preview File Modal -->
                                    <?php if ($assignment['filePath'] && file_exists($assignment['filePath'])): ?>
                                        <div id="preview-file-modal-<?php echo $assignment['assignmentID']; ?>" class="modal" role="dialog" aria-labelledby="preview-file-title-<?php echo $assignment['assignmentID']; ?>">
                                            <div class="modal-content preview-modal-content">
                                                <div class="modal-header">
                                                    <h3 id="preview-file-title-<?php echo $assignment['assignmentID']; ?>">Professor's File: <?php echo htmlspecialchars($assignment['fileName']); ?></h3>
                                                    <div>
                                                        <button class="fullscreen-btn" onclick="toggleFullscreen('preview-file-modal-<?php echo $assignment['assignmentID']; ?>')" aria-label="Toggle fullscreen">
                                                            <i class='bx bx-fullscreen'></i>
                                                        </button>
                                                        <button class="close-btn" onclick="closeModal('preview-file-modal-<?php echo $assignment['assignmentID']; ?>')" aria-label="Close modal">
                                                            <i class='bx bx-x'></i>
                                                        </button>
                                                    </div>
                                                </div>
                                                <div class="modal-body">
                                                    <div class="preview-container">
                                                        <?php if ($assignment['fileType'] === 'application/pdf'): ?>
                                                            <iframe src="<?php echo htmlspecialchars($assignment['filePath']); ?>" title="Professor's Assignment File"></iframe>
                                                        <?php elseif (in_array($assignment['fileType'], ['image/jpeg', 'image/png'])): ?>
                                                            <img src="<?php echo htmlspecialchars($assignment['filePath']); ?>" alt="Professor's Assignment File">
                                                        <?php else: ?>
                                                            <p class="preview-placeholder">File type not supported for preview.</p>
                                                        <?php endif; ?>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    <?php endif; ?>
                                <?php endif; ?>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="7">No assignments available.</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </main>
    </section>

    <script>
        function openModal(modalId) {
            const modal = document.getElementById(modalId);
            modal.style.display = 'flex';
        }

        function closeModal(modalId) {
            const modal = document.getElementById(modalId);
            const modalContent = modal.querySelector('.modal-content');
            modalContent.classList.remove('fullscreen');
            modal.classList.remove('fullscreen');
            modal.style.display = 'none';
            const fullscreenBtn = modal.querySelector('.fullscreen-btn i');
            fullscreenBtn.classList.remove('bx-exit-fullscreen');
            fullscreenBtn.classList.add('bx-fullscreen');
        }

        function toggleFullscreen(modalId) {
            const modal = document.getElementById(modalId);
            const modalContent = modal.querySelector('.modal-content');
            const fullscreenBtn = modal.querySelector('.fullscreen-btn i');
            
            modal.classList.toggle('fullscreen');
            modalContent.classList.toggle('fullscreen');
            
            if (modalContent.classList.contains('fullscreen')) {
                fullscreenBtn.classList.remove('bx-fullscreen');
                fullscreenBtn.classList.add('bx-exit-fullscreen');
            } else {
                fullscreenBtn.classList.remove('bx-exit-fullscreen');
                fullscreenBtn.classList.add('bx-fullscreen');
            }
        }

        function toggleCollapse(collapsibleId, rowElement) {
            const collapsible = document.getElementById(collapsibleId);
            const allCollapsibles = document.querySelectorAll('.collapsible-content');
            const allRows = document.querySelectorAll('.assignment-row');

            allCollapsibles.forEach(item => {
                if (item.id !== collapsibleId) {
                    item.classList.remove('active');
                    item.style.maxHeight = null;
                }
            });
            allRows.forEach(row => {
                if (row !== rowElement) {
                    row.classList.remove('highlighted');
                }
            });

            collapsible.classList.toggle('active');
            rowElement.classList.toggle('highlighted');
            if (collapsible.classList.contains('active')) {
                collapsible.style.maxHeight = collapsible.scrollHeight + 'px';
            } else {
                collapsible.style.maxHeight = null;
            }
        }

        function confirmSubmission() {
            const form = event.target;
            const fileInput = form.querySelector('input[type="file"]');
            const error = form.querySelector('.error');
            
            if (!fileInput.files.length) {
                error.style.display = 'block';
                return false;
            } else {
                error.style.display = 'none';
            }

            return confirm('Are you sure you want to submit the assignment? You cannot edit your submission after this unless you unsubmit before the due date.');
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
    </script>
    <script src="./utils/script.js"></script>
</body>
</html>