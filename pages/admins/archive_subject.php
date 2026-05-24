<?php
require_once './sessions/session_admin.php';
require_once '../../config/db_connection.php';

if (!isset($_GET['subjectID'])) {
    header('Location: data_subjects.php');
    exit();
}

$subjectID = $_GET['subjectID'];

$archiveSql = "UPDATE subjects SET archived = 1 WHERE subjectID = :subjectID";
$archiveStmt = $dbConnection->prepare($archiveSql);
$archiveStmt->bindParam(':subjectID', $subjectID, PDO::PARAM_INT);
$archiveStmt->execute();

if ($archiveStmt->execute()) {
    $_SESSION['success_message'] = "Subject archived successfully!";
} else {
    $_SESSION['error_message'] = "Error archiving subject. Please try again.";
}

$archiveStmt->closeCursor();
header('Location: data_subjects.php');
exit();
?>
