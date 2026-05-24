<?php
require_once './sessions/session_admin.php';
require_once '../../config/db_connection.php';

if (!isset($dbConnection)) {
    error_log("Database connection not established in archive_teacher_section.php", 3, 'C:\xampp\htdocs\capstone-lms\logs\error.log');
    $_SESSION['error_message'] = "Database connection failed. Please contact the administrator.";
    header('Location: data_teacher_section.php');
    exit();
}

if (!isset($_GET['teacherSectionID']) || !is_numeric($_GET['teacherSectionID'])) {
    $_SESSION['error_message'] = "Invalid or missing teacher section ID.";
    header('Location: data_teacher_section.php');
    exit();
}

$teacherSectionID = (int)$_GET['teacherSectionID'];

$sql = "SELECT teacherSectionID FROM teacher_section WHERE teacherSectionID = :teacherSectionID AND archived = 0";
try {
    $stmt = $dbConnection->prepare($sql);
    $stmt->bindValue(':teacherSectionID', $teacherSectionID, PDO::PARAM_INT);
    $stmt->execute();
    $assignment = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$assignment) {
        $_SESSION['error_message'] = "Assignment not found or already archived.";
        header('Location: data_teacher_section.php');
        exit();
    }

    $updateSql = "UPDATE teacher_section SET archived = 1 WHERE teacherSectionID = :teacherSectionID";
    $updateStmt = $dbConnection->prepare($updateSql);
    $updateStmt->bindValue(':teacherSectionID', $teacherSectionID, PDO::PARAM_INT);
    $updateStmt->execute();

    $_SESSION['success_message'] = "Teacher section assignment archived successfully!";
} catch (PDOException $e) {
    error_log("Archive failed: " . $e->getMessage(), 3, 'C:\xampp\htdocs\capstone-lms\logs\error.log');
    $_SESSION['error_message'] = "Failed to archive assignment. Please try again.";
} finally {
    header('Location: data_teacher_section.php');
    exit();
}
?>