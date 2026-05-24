<?php
require_once './sessions/session_admin.php';
require_once '../../config/db_connection.php';

$academicSessions = ['2025 - 2026', '2026 - 2027', '2027 - 2028', '2028 - 2029', '2029 - 2030'];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['sectionID']) && isset($_POST['academic_session'])) {
    $sectionID = (int)$_POST['sectionID'];
    $academicSession = $_POST['academic_session'];

    if (!in_array($academicSession, $academicSessions)) {
        $_SESSION['error_message'] = 'Invalid academic session.';
        header('Location: data_student_section.php?academic_session=' . urlencode($academicSession));
        exit;
    }

    try {
        $dbConnection->beginTransaction();

        // Update all students in the section to 'Completed' status
        $sql = "UPDATE student_section 
                SET status = 'Completed' 
                WHERE sectionID = :sectionID 
                AND academicSession = :academicSession 
                AND archived = 0";
        $stmt = $dbConnection->prepare($sql);
        $stmt->bindValue(':sectionID', $sectionID, PDO::PARAM_INT);
        $stmt->bindValue(':academicSession', $academicSession, PDO::PARAM_STR);
        $stmt->execute();

        $dbConnection->commit();
        $_SESSION['success_message'] = 'Section marked as complete, and all students updated to Completed status.';
    } catch (Exception $e) {
        $dbConnection->rollBack();
        $_SESSION['error_message'] = 'Error marking section as complete: ' . $e->getMessage();
    }
} else {
    $_SESSION['error_message'] = 'Invalid request.';
}

header('Location: data_student_section.php?academic_session=' . urlencode($academicSession));
exit;
?>